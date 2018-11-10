<?php 

class Cache 
{
	static private $driver = NULL;
	static private $config = NULL;
	static private $cCache = NULL;
	
	static function setCacheCfg($driver=NULL, $config=NULL)
	{
		self::$driver = $driver;
		self::$config = $config;
		
		//重置cache驱动
		if ( NULL != self::$cCache )
		{
			self::setDriver( $driver, $config );
		}
		return;
	}
	static function getCacheCfg(&$driver, &$config)
	{
		$driver = self::$driver;
		$config = self::$config;
		if ( NULL != self::$driver )
		{
			$driver = commonSys::getCacheDriver();;
		}
		return;
	}
	
	static private function setDriver($driver=NULL, $config=NULL)
	{
		$driver = commonSys::getCacheDriver($driver);
			
		include_once( dirname(__FILE__).'/cache_'.$driver.'.php' );
			
		self::$cCache = new Cache_driver($config);
	}

	static private function getDriver( )
	{
		if ( NULL == self::$cCache )
		{
			self::setDriver( self::$driver, self::$config );
		}

		return self::$cCache;
	}
	
	static private function setid(&$id)
	{
		if ( CLU_LOCAL == DSTP_CLU )
		{
			return;
		}

		//有些cache是可以各系统共用的.放到单独函数去实现了
		$id = getSysUid().'_'.$id;
		return;
	}

	static function get($id)
	{	
		self::setid($id);
		return self::getDriver()->get($id);
	}

	static function set($id, $data, $ttl = 86400)
	{
		self::setid($id);
		return self::getDriver()->set($id, $data, $ttl);
	}

	static function del($id)
	{
		self::setid($id);
		return self::getDriver()->del($id);
	}

	static function delAll($id)
	{
		if ( CLU_LOCAL == DSTP_CLU )
		{
			return self::del($id);
		}
		//云系统下，删除所有同名cache。暂时如此处理
		return self::del($id);
	}

	//有些cache是可以各系统共用的无需改名
	static function get1($id)
	{	
		return self::getDriver()->get($id);
	}

	static function set1($id, $data, $ttl = 86400)
	{
		return self::getDriver()->set($id, $data, $ttl);
	}
	static function del1($id)
	{
		return self::getDriver()->del($id);
	}

	static function clean()
	{
		return self::getDriver()->clean();
	}
	
}
?>