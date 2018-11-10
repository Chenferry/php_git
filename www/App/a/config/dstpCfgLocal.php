<?php

/* 系统临时文件路径 */
$GLOBALS['DstpDir']['tempDir'] = (FALSE !== stripos( PHP_OS, 'WIN' ) ) ? getenv('TEMP') : '/tmp';//tempnam

define('INSTALL_VER_XML',  '../../a/config/dstpVersion.xml');  // 安装标记文件
define('INSTALL_DSTP_URL', '../../a/config/dstpurl');  // 安装标记文件

//最大的允许运行时间，通过ini_set设置也只能达到的最大上限
define('MAX_EXEC_TIME', 100);

class commonSys
{
	static function getCloudDBCfg()
	{
		include('dbConfig.php');
		
		$dbopt = array();
		//sqlsrv 干啥非和别人整成不一样的写法
		switch( $dbDriver )
		{
			case 'sqlsrv':
				$dsn   = $dbDriver.':Database='.$dbName.';server='.$dbHost;
				$dbopt = array( 'TransactionIsolation' => PDO::SQLSRV_TXN_READ_UNCOMMITTED );
				break;
			case 'sqlite':
				$dsn   = $dbDriver.':'.$dbName;
				break;
			default:
				$dsn   = $dbDriver.':dbname='.$dbName.';host='.$dbHost;
				if( NULL != $dbPort )
				{
					$dsn .= ';port='.$dbPort;
				}
				//$dbopt = array( PDO::ATTR_PERSISTENT => true );
				break;
		}
	
		$dbCfg = array();	
		$dbCfg['dsn']  = $dsn;
		$dbCfg['user'] = $dbUser;
		$dbCfg['psw']  = $dbUserPsw;
		$dbCfg['opt']  = $dbopt;
		
		return $dbCfg;	
	}
	//保存错误信息到日志
	static function logFile(&$err)
	{
		file_put_contents('/tmp/hicerrlog.txt',$err."\n", FILE_APPEND  );
	}
	
	static function getCacheDriver(&$driver)
	{
		if( 'i' == HIC_LOCAL )
		{
			return 'memcached';
		}
		if( !DSTP_DEBUG )
		{
			return 'file';//HIC中直接使用文件缓存。不再加入APC
		}
	}
	
	//异步调用一个dstp的内部地址
	static function asyncCallDstpUrl(&$addr,&$url)
	{
		if ( is_array($url) )
		{
			foreach( $url as $u )
			{
				commonSys::asyncCallDstpUrl($addr,$u);
			}
			return;
		}
		$host=$_SERVER['HTTP_HOST'];
	
		$timeout = 3;
		$ip   = $_SERVER['SERVER_ADDR'];
		if ( '::1' == $ip ) $ip = '127.0.0.1';
		$port = $_SERVER['SERVER_PORT'];
		
		$fp = fsockopen($ip, $port, $errno, $errstr, $timeout);
		if ( $fp )
		{
			$dstpurl = $addr.$url;
			$http_message="GET $dstpurl HTTP/1.0\r\nHost: $host\r\n\r\n"; 
			fwrite($fp, $http_message);
			fclose($fp);
		}
		
		/*
		$s=stream_socket_client("$ip:$port", $errno, $errstr, $timeout,
	             STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT);
	    if ( $s )
	    {
	    	stream_set_timeout( $s, $timeout ); 
			$http_message="GET $addr HTTP/1.0\r\nHost: $host\r\n\r\n"; 
	        fwrite($s, $http_message);
	    }
	    */
	    return;       
		
	}
}


class uploadIO
{
	private $isPub;//是否可通过外链直接访问

   	function __construct($isPub=false)  
   	{
   		$this->isPub = $isPub;
   	}
   	function mkdir($dir)
   	{
		if ( !is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return;
   	}
   	function unlink($file)
   	{
   		@unlink($file);
   	}
	//这个函数要求要目录为空时才能删除。正好系统函数rmdir符合这个要求，不能修改为循环判断删除
   	function rmdir($dir)
   	{
   		@rmdir($dir);
   	}

   	function cleandir($dir)
   	{
		$handle = opendir( $dir );
		if ( !$handle )
		{
			return false;
		}
	    while ( false !== ( $item = readdir( $handle ) ) ) {
		    if ( $item == "." || $item == ".." ) 
		    {
		   		continue;
			}
			if ( is_dir( "$dir/$item" ) ) 
			{
				self::cleandir( "$dir/$item" );
			} 
			else 
			{
				@unlink( "$dir/$item" );
			}
	   }
	   closedir( $handle );
	   @rmdir( $dir );
   	}

   	function isExist($file)
   	{
   		return file_exists($file);
   	}
   	
   	function download($fileName,$echo=true)
   	{
		$file = fopen($fileName,"rb"); 
		if ( false === $file )
		{ 
			return false;
		} 
		
		@set_time_limit(0); 

		$str = NULL;
		while (!feof($file))
		{
			if($echo)
			{
				echo fread($file, 8192);
			}
			else
			{
				$str .= fread($file, 8192);
			}
		}
		fclose($file);
		if($echo)
		{
			return true;
		}
		return $str;
   	}
   		
	function getUploadDir() 
	{
		if ( $this->isPub )
		{
			return $this->getImageUpDir();
		}
		$upDir=$_SERVER['DOCUMENT_ROOT'];
		$upDir=dirname($upDir); //附件不能放在www可访问到的地方。暂时先放根目录上层。可以考虑数据库配置

		$upDir.='/dstpuploadfiles/';
		if ( !is_dir($upDir)){
			@mkdir($upDir, 0777, true);
		}

		return $upDir;
	}
	
	function getImageUpDir()
	{
		$upDir  = $GLOBALS['DstpDir']['DocDir'].'/ckimages/';

		if (isset($GLOBALS['SYSDB']['SYSUID'])) 
		{
			$upDir = $upDir.$GLOBALS['SYSDB']['SYSUID'].'/';
			if ( !is_dir($upDir)){
				@mkdir($upDir, 0777, true);
			}
		}
	
		return $upDir;
	}
	
	function writeToIO(&$destFile, &$fileInfo)
	{
		return file_put_contents($destFile, $fileInfo);
	}
	
	function moveUploadFile($upFile, $destFile)
	{
		if ( $this->isPub )
		{
			return $this->moveUploadImage($upFile, $destFile);
		}
		return move_uploaded_file($upFile, $destFile);
	}
	
	function moveUploadImage($upFile, $destFile)
	{
		if( move_uploaded_file($upFile, $destFile))
		{
			$destFile = str_replace($GLOBALS['DstpDir']['DocDir'], '..', $destFile );
			return $destFile;
		}
		return NULL;
	}
}
?>