<?php
//情景开关
class qjAttrType
{
	static $cfg = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR);

	static $packfmt   = 'c';
	static $unpackfmt = 'c';
	static $page      = 'group'; 
	static $name      = DEV_SYSNAME_QJ;

	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');	
		$value = $c->queryValue('ATTRSET','ID=?',array($attrid)); 		
		$value = unserialize($value);
		if(!isset($value['GROUPID']))
		{
			$value['GROUPID'] = '-1';
		}
		return $value;
	}


	//情景开关在执行转换时直接就执行相关操作
	static function getDBInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');	
		$attr = $c->queryValue('ATTRSET','ID=?',array($attrid)); 
		$attr = unserialize($attr);
		if(!$attr)
		{
			$attr = array();
		}

		switch($value['type'])
		{
			case 0: //保存情景模式
				if(!$attr['ISGROUP']) //先删除原来的设置
				{
					$GLOBALS['dstpSoap']->setModule('smart','group');
					$GLOBALS['dstpSoap']->delGroup($attr['GROUPID']);
				}
				
				if ( !is_numeric($value['value']) )
				{
					$attr['ISGROUP'] = false;
					$GLOBALS['dstpSoap']->setModule('smart','group');
					$attr['GROUPID'] = $GLOBALS['dstpSoap']->saveGroup(INVALID_ID,'',$value['value'],false);
				}
				else
				{
					$attr['ISGROUP'] = true;
					$attr['GROUPID'] = $value['value'];
				}

				$info = array();
				$info['ID']      = $attrid;
				$info['ATTRSET'] = serialize($attr);
				
				$c = new TableSql('homeattr','ID');	
				$c->update($info); 
				noticeAttrModi($attrid);
				break;
			case 1: //执行
			default:
				if( !validID($attr['GROUPID']) )
				{
					break;
				}
				$GLOBALS['dstpSoap']->setModule('smart','group');
				$GLOBALS['dstpSoap']->execGroup($attr['GROUPID']);
				break;
		}
		return false;
	}

	static function getCMDInfo($value,$attrid=NULL)
	{
		return false;
	}
	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		if(!intval($value) )
		{
			return false;
		}
		if( $value != 1 )
		{
			$last = Cache::get('qj-'.$attrid) ? : array();
			foreach( $last as $k => $v ) 
			{
				if( time()-$v > 30 )	unset($last[$k]);
			}
			if( in_array($value,array_keys($last)) ) return false;
			$last[$value] = time();
			Cache::set('qj-'.$attrid,$last,30);			
		}

		//获取当前的value值，得到情景模式ID
		$c = new TableSql('homeattr','ID');	
		$attr = $c->queryValue('ATTRSET','ID=?',array($attrid)); 
		$attr = unserialize($attr);
		if(!$attr)
		{
			return false;
		}
		$GLOBALS['dstpSoap']->setModule('smart','group');
		$GLOBALS['dstpSoap']->execGroup($attr['GROUPID']);
		
		return false;//情景开关的value值保存的是关联情景ID，并不需要状态，直接返回false
	}

	//辅助前台显示的信息
	//比如pm2.5，得到的是数值，但需要根据数值转换为一个表示等级的枚举值
	//比如可能是一个多维数组，指示空调可选模式/温度列表等
	static function getOtherInfo($value,$attrid=NULL)
	{
		//需要获取情景模式列表
		$c     = new TableSql('smartgroup','ID'); 
		return $c->queryAll('ID,NAME','ISSHOW=1');
	}

	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'dz'=>array('执行','开始'));
	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$GLOBALS['dstpSoap']->setModule('devattr','attr');
		$GLOBALS['dstpSoap']->execAttr($attrid,1);
		return array('ret'=>true,'info'=>'开始');
	}		
}

?>