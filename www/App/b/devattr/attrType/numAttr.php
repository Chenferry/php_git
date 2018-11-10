<?php
class numAttrType
{
	static $cfg  = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>NULL);
	static $page = 'num'; 

	//命令格式char alarm
	static $packfmt   = 'n';
	static $unpackfmt = 'n';
	
	//解析附加信息
	//  ushort 数值类型 0整数/1小数。如果小数，表示传来数值的扩大倍数
	//  long   显示范围最小值
	//  long   显示范围最大值
	//  uchar  显示单位长度。0表示无需显示单位
	//  uchar* 显示单位信息
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$ret = array();
		//告警阈值，附加告警信息长度
		$ret = unpack('ntype/lmin/lmax/Ccalclen',$value);
		if( 0 != $ret['type'] )
		{
			$ret['min'] /= $ret['type'];
			$ret['max'] /= $ret['type'];
			
			//修改记录中的VF和CF
			$c = new TableSql('homeattr','ID'); 
			$info = array();
			$info['ID'] = $attrid;
			$info['VF'] = TABLE_FIELD_FLOAT;
			$c->update($info);
		}

		$ret['calc'] = substr($value,11,$ret['calclen']);
		$value = substr($value,11+$ret['calclen']);

		$unit = unpack('Cunitlen',$value);
		
		$ret['unit']      = NULL;
		if( 0 != $unit['unitlen'] )
		{
			$ret['unit'] = substr($value,1,$unit['unitlen']);
		}
		unset($ret['calclen']);
		return $ret;
	}
	
	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( NULL != $cfg['calc'] )
		{
			$calc = str_replace('X',$value,$cfg['calc']);
			$value = eval("return $calc;");
			if( 0 != $cfg['type'] )
			{
				$value /= $cfg['type'];
			}
		}
		
		//暂时注释掉tsdb调用，后续要根据是否允许，以及设备定义信息来决定如何存储

        //$hicid=HICInfo::getHICID();
        //include_once('tsdb/tsdb.php');
        //$t=new TSDB();
        //$t->insert("attr$attrid",$value,$hicid);

        return $value;
	}
	
	//现在只有虚拟设备才可能有执行
	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);

		$c  = new TableSql('homeattr','ID');
		$iv = intval($c->queryValue('ATTRINT','ID=?',array($attrid)));
		switch($value['action'])
		{
			case 'add':
				$iv += intval($value['value']);
				break;
			case 'sub':
				$iv -= intval($value['value']);
				break;
			case 'init':
			default:
				$iv = intval($value['value']);
				break;
		}
		$info = array();
		$info['ID']      = $attrid;
		$info['ATTRINT'] = $iv;
		$c->update($info);
		
		noticeAttrModi($attrid);
		
		return false;
	}

	
	//辅助前台显示的信息
	//比如pm2.5，得到的是数值，但需要根据数值转换为一个表示等级的枚举值
	//比如可能是一个多维数组，指示空调可选模式/温度列表等
	static function getOtherInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		return array('min'=>$cfg['min'],'max'=>$cfg['max'],'unit'=>$cfg['unit']);
	}
}
?>