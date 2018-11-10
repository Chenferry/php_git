<?php
//语音播报
class yybb1AttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'yybb'; 

	
	static function getViewInfo($value,$attrid)
	{
		//显示当前是开启还是关闭语音识别功能
		$c = new TableSql('homeattr','ID');	
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid)); 
		$cfg = unserialize($cfg);
		if( false == $cfg )
		{
			return '_start';
		}
		return $cfg['start'];
	}
	
	//把设备上报的状态信息转为数据库信息
	//摄像头报告状态时直接回当前SSID信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$value = trim($value);
		if( NULL == $value )
		{
			//发送wifi设置
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,'_live');			
			return false;
		}
		if( '_wifi' == $value )
		{
			//发送wifi设置
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,'_wifi');			
			return false;
		}

		$c = new TableSql('homeattr','ID');	
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid)); 
		$cfg = unserialize($cfg);
		if( isset($cfg['start']) && ( '_stop' == $cfg['start'] ))
		{
			//禁止语音识别
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,'_fail');			
			return false;
		}
		$GLOBALS['yuyinexec'] = true;
		$GLOBALS['dstpSoap']->setModule('yuyin');
		$result = $GLOBALS['dstpSoap']->yuyin($value,"yyid-$attrid");
		if( $GLOBALS['yuyinexec'] )
		{
			unset($GLOBALS['yuyinexec']);
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,'_ok');			
		}
		else
		{
			unset($GLOBALS['yuyinexec']);
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,'_fail');			
		}
		return false;
	}

	//如果控制字为空时，表示报告状态时的处理，需要把index改为0
	static function getCMDInfo($value,$attrid)
	{
		$value = trim($value);
		$result = false;
		switch($value)
		{
			case '_live':
				$result = pack("C",0);
				break;			
			case '_start':
			case '_stop':
				//开启或者关闭语音识别
				$c = new TableSql('homeattr','ID');	
				$cfg = $c->query('ID,ATTRSET','ID=?',array($attrid)); 
				$cfg['ATTRSET'] = unserialize($cfg['ATTRSET']);
				if( false == $cfg['ATTRSET'] )
				{
					$cfg['ATTRSET'] = array();
				}
				$cfg['ATTRSET']['start'] = $value;
				$cfg['ATTRSET'] = serialize($cfg['ATTRSET']);
				$c->update($cfg);
				noticeAttrModi($attrid);
				break;
			case '_ok':
				$result = pack("CC",1,1);
				break;
			case '_fail':
				$result = pack("CC",1,0);
				break;
			case '_wifi':
				include_once('uci/uci.class.php');	
				$ssid = SSID::getSSID();
				$encryption = trim($ssid['encryption']);
				$password  =  trim($ssid['password']);
				$name      =  trim($ssid['name']);				
				$result = pack("CC",3,strlen($name)).$name.pack('C',strlen($encryption)).$encryption.pack('C',strlen($password)).$password;
				break;
			default:
				$result = pack("CC",2,strlen($value)).$value;
				break;
		}
		return $result;
	}
}

 

?>