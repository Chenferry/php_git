<?php
/* ϵͳ��ʱ�ļ�·�� */
$DstpDir['tempDir'] = SAE_TMP_PATH;//tempnam
define('INSTALL_VER_XML',  'saekv://config/dstpVersion.xml');  // ��װ����ļ�
define('INSTALL_DSTP_URL', 'saekv://config/dstpurl');  // ��װ����ļ�

//������������ʱ�䣬ͨ��ini_set����Ҳֻ�ܴﵽ���������
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

	//���������Ϣ����־
	static function logFile(&$err)
	{
		sae_set_display_errors(false);//�ر���Ϣ���
	   	sae_debug($err."\n");//��¼��־
		sae_set_display_errors(true);//��¼��־���ٴ���Ϣ������������ֹ�����Ĵ�����Ϣ����ʾ
		return;
	}
	
	//������õ�cache����
	static function getCacheDriver(&$driver)
	{
		return 'memcached';
	}

	//�첽����һ��dstp���ڲ���ַ
	static function asyncCallDstpUrl(&$addr,&$url)
	{
		$schemm = 'http';
		if ( isset($_SERVER['REQUEST_SCHEME']) )
		{
			$schemm = $_SERVER['REQUEST_SCHEME'];
		}
		$host = $_SERVER['HTTP_HOST'];
		//$port = $_SERVER['SERVER_PORT']; //����ƻ����¶��⼸����ȷ���ˡ������ڲ���֪�᲻���в�һ��
		
		$dstpurl = $schemm.'://'.$host.$addr;
		$queue = new SaeTaskQueue('dstptaskqueue');//�˴���test������Ҫ�����߹���ƽ̨���Ƚ���
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
	private $isPub;//�Ƿ��ͨ������ֱ�ӷ���
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
   			return true; //ֻ���ļ�Ϊ�ղ�����ɾ��Ŀ¼
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
	//��δʵ�֡�
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




/////////////////////////��������������/////////////////////////////////////////


//SAE��֧��SOAP����Ҫģ��
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