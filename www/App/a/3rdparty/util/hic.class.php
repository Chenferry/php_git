<?php


include_once('util/hic.'.HIC_LOCAL.'.class.php');
  
class HICInfo  extends inHIC	
{
	//getBHost
	//getPeerHost
	static function  getAHost()
	{
		if ( isset($_SERVER['REQUEST_URI']) )
		{
			return $_SERVER['HTTP_HOST'];
		}
		return a_jia_sx;
	}
	static function  getCHost()
	{
		return c_jia_sx;
	}
	static function  getSHost()
	{
		return s_jia_sx;
	}
	static function createUrl($host,$dir)
	{
		return 'http://'.$host.'/App/'.$dir;
	}
	static function createUIUrl($host,$dir)
	{
		return 'http://'.$host.'/App/UI/zh_cn/'.$dir;
	}
	static function getAUI()
	{
		return self::createUIUrl( self::getAHost(),'a' );
	}
	static function getBUI()
	{
		return self::createUIUrl( self::getBHost(),'b' );
	}
	static function getCUI()
	{
		return self::createUIUrl( self::getCHost(),'c' );
	}
	static function getCurAUrl()
	{
		return self::createUrl( $_SERVER['HTTP_HOST'],'a' );
	}
	static function getAUrl()
	{
		return self::createUrl( self::getAHost(),'a' );
	}
	static function getBUrl()
	{
		return self::createUrl( self::getBHost(),'b' );
	}
	static function getCUrl()
	{
		return self::createUrl( self::getCHost(),'c' );
	}
	static function getSUrl()
	{
		//return 'https://'.self::getSHost().'/App/c';
		//暂时没数字证书,为免提示干扰用户，先不加密
		return 'http://'.self::getSHost().'/App';
	}
	static function getPeerUrl()
	{
		$abc = ('b'==HIC_LOCAL)?'c':'b';
		return self::createUrl( self::getPeerHost(),$abc );
	}
	
	static function getPHYID($hicid=NULL)
	{
		return parent::getPHYID($hicid);
	}
	static function getSecure($hicid=NULL)
	{
		$hicid = self::getHICID($hicid);
		return parent::getSecure($hicid);
	}
	static function getHICID($hicid=NULL)
	{
		if ( NULL != $hicid )
		{
			return $hicid;
		}
		$hicid = getSysUid();
		if (validID($hicid)) {
			return $hicid;
		}
		if( isset($GLOBALS['curHICID']) )
		{
			return $GLOBALS['curHICID'];
		}
		if( isset($_SESSION['logingHICID'] ) )
		{
			return $_SESSION['logingHICID'] ;
		}
		$GLOBALS['curHICID'] = parent::getHICID();
		return $GLOBALS['curHICID'];
	}
	//获取新的hicid
	static function getNewHICID()
	{
		$GLOBALS['curHICID'] = parent::getHICID();
		return $GLOBALS['curHICID'];
	}	
	//HIC密码加密方式
	static function cryptPsw($psw)
	{
		return md5(trim($psw));
	}
}
?>