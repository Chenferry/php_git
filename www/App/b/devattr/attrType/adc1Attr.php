<?php
//ADC电路通用处理......
class adc1AttrType
{
	static $cfg  = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>NULL);
	static $page = 'num'; 

	//命令格式char alarm
	static $packfmt   = 'n';
	static $unpackfmt = 'n';
	
	//解析附加信息
	//ushort 告警阈值
	//uchar  附加告警信息的长度

	//  uchar  数值类型 0整数/1小数。如果小数，表示传来数值的扩大倍数
	//  ushort 显示范围最小值
	//  ushort 显示范围最大值
	//  uchar  显示单位长度。0表示无需显示单位
	//  uchar* 附加告警信息的内容
	//  uchar* 显示单位信息
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$ret = array();
		//告警阈值，附加告警信息长度
		$ret = unpack('nyz/Calarmlen/Ctype/nmin/nmax/Cunitlen',$value);
		if( 0 != $ret['type'] )
		{
			$ret['yz']  /= $ret['type'];
			$ret['min'] /= $ret['type'];
			$ret['max'] /= $ret['type'];
			
			//修改记录中的VF和CF
			$c = new TableSql('homeattr','ID'); 
			$info = array();
			$info['VF'] = TABLE_FIELD_FLOAT;
			$c->update($info);
		}
		$ret['unit']      = NULL;
		$ret['alarminfo'] = NULL;
		if( 0 != $ret['alarmlen'] )
		{
			$ret['alarminfo'] = substr($value,9,$ret['alarmlen']);
		}
		if( 0 != $ret['unitlen'] )
		{
			$ret['unit'] = substr($value,9+$ret['alarmlen'],$ret['unitlen']);
		}
		unset($ret['alarmlen']);
		unset($ret['unitlen']);
		return $ret;
	}
	
	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($id));
		$cfg = unserialize($cfg);
		if( 0 != $cfg['type'] )
		{
			$value /= $cfg['type'];
		}
		return $value;

	}

	//根据状态信息判断是否告警状态
	static function getAlarmInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($id));
		$cfg = unserialize($cfg);
		if( $cfg['yz'] < $cfg['min'] )
		{
			return DEV_ALARM_IGNORE;
		}
		if($value < $cfg['yz'])
		{
			return DEV_ALARM_ALARM;
		}
		return DEV_ALARM_CLEAN;
	}
	
	//辅助前台显示的信息
	//比如pm2.5，得到的是数值，但需要根据数值转换为一个表示等级的枚举值
	//比如可能是一个多维数组，指示空调可选模式/温度列表等
	static function getOtherInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($id));
		$cfg = unserialize($cfg);
		return array('min'=>$cfg['min'],'max'=>$cfg['max'],'unit'=>$cfg['unit']);
	}
}
?>