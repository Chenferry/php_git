<?php
/* 系统临时文件路径 */
$DstpDir['tempDir'] = SAE_TMP_PATH;//tempnam
define('INSTALL_VER_XML',  'saekv://config/dstpVersion.xml');  // 安装标记文件
define('INSTALL_DSTP_URL', 'saekv://config/dstpurl');  // 安装标记文件

//最大的允许运行时间，通过ini_set设置也只能达到的最大上限
define('MAX_EXEC_TIME', 30);

class commonSys
{
	static function getCloudDBCfg()
	{
		$dbDriver = 'mysql';
		$dbHost   = SAE_MYSQL_HOST_M;
		$dbName   = SAE_MYSQL_DB;
		$dbUser   = SAE_MYSQL_USER;
		$dbUserPsw= SAE_MYSQL_PASS;
		
		$dbCfg = array();	
		$dbCfg['dsn']  = $dbDriver.':dbname='.$dbName.';host='.$dbHost.';port='.SAE_MYSQL_PORT;
		$dbCfg['user'] = $dbUser;
		$dbCfg['psw']  = $dbUserPsw;
		$dbCfg['opt']  = array();
		
		return $dbCfg;	
	}

	//保存错误信息到日志
	static function logFile(&$err)
	{
		sae_set_display_errors(false);//关闭信息输出
	   	sae_debug($err."\n");//记录日志
		sae_set_display_errors(true);//记录日志后再打开信息输出，否则会阻止正常的错误信息的显示
		return;
	}
	
	//获得适用的cache驱动
	static function getCacheDriver(&$driver)
	{
		return 'memcached';
	}

	//异步调用一个dstp的内部地址
	static function asyncCallDstpUrl(&$addr,&$url)
	{
		$schemm = 'http';
		if ( isset($_SERVER['REQUEST_SCHEME']) )
		{
			$schemm = $_SERVER['REQUEST_SCHEME'];
		}
		$host = $_SERVER['HTTP_HOST'];
		//$port = $_SERVER['SERVER_PORT']; //这个云环境下对外几乎是确定了。反而内部不知会不会有不一样
		
		$dstpurl = $schemm.'://'.$host.$addr;
		$queue = new SaeTaskQueue('dstptaskqueue');//此处的test队列需要在在线管理平台事先建好
		$array = array();
		if ( is_array($url) )
		{
			foreach($url as &$u)
			{
				$array[] = array('url'=>$dstpurl.$u);
			}
		}
		else
		{
			$array[] = $dstpurl.$url;
		}
		$queue->addTask($array); 
		$ret = $queue->push();
		return;	
	}
}

class uploadIO
{
	private $isPub;//是否可通过外链直接访问
	private $s;
	private $domain;

   	function __construct($isPub=false)  
   	{
   		$this->isPub  = $isPub;
		$this->s      = new SaeStorage();
		$this->domain = $this->getStorageDomain( $this->isPub );
   	}		

   	function mkdir($dir)
   	{
		return;
   	}
   	function unlink($file)
   	{
   		return $this->s->delete($this->domain, $file);
   	}
   	function rmdir($dir)
   	{
   		$n = $this->s->getFilesNum($this->domain, $dir);
   		if ( 0 != $n )
   		{
   			return true; //只有文件为空才允许删除目录
   		}
   		return $this->s->deleteFolder($this->domain, $dir);
   	}
   	function cleandir($dir)
	{
		return $this->s->deleteFolder($this->domain, $dir);
	}
   	function isExist($file)
   	{
   		return $this->s->fileExists ($this->domain, $file);
   	}
   	
   	function download($file,$echo=true)
   	{
		if ( $echo )
		{
			echo $this->s->read($this->domain, $file);
			return true;
		}
		return $this->s->read($this->domain, $file);
   	}


 
	function getUploadDir() 
	{
		if (isset($GLOBALS['SYSDB']['SYSUID'])) 
		{
			return $GLOBALS['SYSDB']['SYSUID'].'/';
		}
		return NULL;
	}
	//暂未实现。
	function writeToIO(&$destFile, &$fileInfo)
	{
		return true;
	}
	
	function moveUploadFile($upFile, $destFile)
	{
		return $this->upToStore($this->domain,$upFile, $destFile);
	}
	
	////////////////////////////////
	private function upToStore($domain,$upFile, $destFile)
	{
		return $this->s->upload ($domain, $destFile, $upFile);
	}
	private function getStorageDomain( )
	{
		if ($this->isPub)
		{
			$domain = 'dstpimage';
		}
		else
		{			
			if (isset($GLOBALS['SYSDB']['SYSUID'])) 
			{
				$domain = 'dstpup'.intval((intval($GLOBALS['SYSDB']['SYSUID'])%4)+1);
			}
			else
			{
				$domain = 'dstpup1';
			}
		}
		return $domain;
	}

}




/////////////////////////环境的特殊设置/////////////////////////////////////////


//SAE不支持SOAP。需要模拟
if (!class_exists('SoapFault') )
{
	class SoapFault
	{
		var $faultcode;
		var $faultstring;
	   	function __construct($faultcode,$faultstring)  
	   	{
	   		$this->faultcode = $faultcode;
	   		$this->faultstring = $faultstring;
	   	}		
	}
	function is_soap_fault($a)
	{
		return ($a instanceof SoapFault);
	}
}

if (!class_exists('Memcached') )
{
	class Memcached
	{
		private $mmc;
	   	function __construct()         {$this->mmc = memcache_init(); if (!$this->mmc){die('MMC init fail');} 	}
		function get($id)              {return memcache_get($this->mmc, $id);}
		function add($id, $value, $ttl){return memcache_set($this->mmc, $id, $value, 0, $ttl);}
		function del($id)              {return memcache_delete($this->mmc, $id);}
		function clean($id)            {return memcache_flush($this->mmc);}
	}
}


?>