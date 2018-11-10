<?php
include_once(dirname(dirname(__DIR__)).'/c/3rdparty/BAE/BOS/uConf.php');

/* 系统临时文件路径 */
$DstpDir['tempDir'] = sys_get_temp_dir();//tempnam
define('INSTALL_VER_XML',  '../config/dstpVersion.xml');  // 安装标记文件
define('INSTALL_DSTP_URL', '../config/dstpurl');  // 安装标记文件

//最大的允许运行时间，通过ini_set设置也只能达到的最大上限
define('MAX_EXEC_TIME', 100);

//在dstpCfgCustom文件中，必须实现如下配置
/*

class BAEConf
{
    public static $app = array(
            'id'   => 'xxxxxxx',    
            'name' => 'xxxxxx', 
            'ak'   => 'Z1lGtCnWNRkdpp5Docig5ZOK',   
            'sk'   => 'BISrjlV3ZyXbXdAss1ZNA7K6UtwzO5ig',   
    );

    public static $db = array(
            'id'   => 'xxxxxxxxxxxxxx',
            'host' => 'sqld.duapp.com',
            'port' => '4050',       
            'driver' => 'mysql',
    );

    public static $cache = array(
            'id' => 'xxxxxxxxxxxxxxx',
            'host' => 'cache.duapp.com',
            'port' => '20243',      
    );

    public static $android  = array(
        'ak'    => 'UZE2Us3TPIjDoRfq3GogbgPq',
        'sk'    => '75Th0dH8FHpmxnDfMprnIFSxPQRqz4yS',
    );
    
    
    public static $ios = array(
            'ak'   => 'ktGvoDwNDZWHA1udjzaupPnV',   
            'sk'   => 'dEPzVrOeh41dWoWx9oYferZyhbxaRH7I',   
    );
    
    public static  $bos= array(
                'credentials' => array(
                'ak' => 'Z1lGtCnWNRkdpp5Docig5ZOK',
                'sk' => 'BISrjlV3ZyXbXdAss1ZNA7K6UtwzO5ig',
                ),
                'endpoint' => 'http://bj.bcebos.com',
        );
}

    public static $log = array(
            'level' => 16,
    );
        'BOS_CONFIG'    =>
    array(
        'credentials' => array(
            'ak' => 'Z1lGtCnWNRkdpp5Docig5ZOK',
            'sk' => 'BISrjlV3ZyXbXdAss1ZNA7K6UtwzO5ig',
        ),
        'endpoint' => 'http://bj.bcebos.com',
    ),
}

*/
class commonSys
{
	static function getCloudDBCfg()
	{
		$db = &BAEConf::$db;

        $dbCfg = array();   
        $dbCfg['dsn']  = $db['driver'].':dbname='.$db['id'].';host='.$db['host'].';port='.$db['port'];
        $dbCfg['user'] = $db['user'];
        $dbCfg['psw']  = $db['psw'];
        $dbCfg['opt']  = array();
        
        return $dbCfg;  
    }
    
    //保存错误信息到日志
    static function logFile(&$err)
    {   
		return;//暂时屏蔽，这个接口好像有变化
        include_once 'BAE/BaeLog.class.php';
        $secret = array("user"=>BAEConf::$app['ak'], "passwd"=>BAEConf::$app['sk'] );
        $log = BaeLog::getInstance($secret);
        $log->setLogLevel(BAEConf::$log['level']);
        $log->Warning($err);
        return;
    }
    
    static function getCacheDriver(&$driver)
    {
		if( 'i' == HIC_LOCAL )
		{
			return 'memcached';
		}
        return 'file';
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

        $schemm = 'http';
        if ( isset($_SERVER['REQUEST_SCHEME']) )
        {
            $schemm = $_SERVER['REQUEST_SCHEME'];
        }
        //$port = $_SERVER['SERVER_PORT']; //这个云环境下对外几乎是确定了。反而内部不知会不会有不一样
        $host = $_SERVER['HTTP_HOST'];
        $dstpurl = $schemm.'://'.$host.$addr.$url;

        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $dstpurl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_exec($ch);  
        if (curl_errno($ch)) {
           logErr(curl_error($ch)) ;
        } else {
           curl_close($ch);
        }
        return;
    }
}


/////////////////////////环境的特殊设置/////////////////////////////////////////
//BAE不支持SOAP。需要模拟
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

?>