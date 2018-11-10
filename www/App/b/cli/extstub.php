<?php  
//负责串口消息转发处理
if( 'cli'!=PHP_SAPI )
{
	exit(-1);
}

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);
include_once( dirname(__FILE__).'/proxyserver.php' );
include_once( dirname(__FILE__).'/extcfg.php' );
include_once('util/hic.proto.php');

//分机中采用
if( APP_FJ != HIC_APP )
{
	exit(-1);
}

class fjactive  extends clientMgt
{
	static $client;
	static $addr     = NULL;//'tcp://127.0.0.1:'.HIC_SERVER_RDIO;
	static $livetime = 60;//设置tcp超时重新连接时间为60秒
	static $timeout  = false;
	
	static $hicidRes = false;
	
	static function init()
	{
		server::startTimer(array(__CLASS__, 'sendHICID'),  1000000*3);
		self::$addr  = 'tcp://127.0.0.1:'.HIC_SERVER_RDIO;
		clientMgt::init(__CLASS__);
	}
	static function sendHICID()
	{
		if( NULl == self::$client )
		{
			return;
		}

		$hicinfo = unserialize(file_get_contents('/usr/db/addFlag'));
		$hicid   = intval($hicinfo['hicId']);
		$info    = rdio::initHicidInfo($hicid);

		server::writeconn(self::$client,$info);		
	}

	static function onRead($id,&$info)
	{
		//只要能读到，就表示通讯成功，可以开始自动激活
		$GLOBALS['dstpSoap']->setModule('local','sn');
		if( false != $GLOBALS['dstpSoap']->getSN() )
		{
			exit();
		}

		//自动尝试去激活
		$GLOBALS['dstpSoap']->setModule('local','sn');
		$GLOBALS['dstpSoap']->activeSN();				
		//激活后，就自动退出，开始rdio的进程处理
		exit();
	}
}

class rdio extends clientMgt
{
	static $client;
	static $addr     = NULL;//'tcp://127.0.0.1:'.HIC_SERVER_RDIO;
	static $livetime = 50;//设置tcp超时重新连接时间为60秒
	static $timeout  = false;

	static function init()
	{
		self::$addr  = 'tcp://127.0.0.1:'.HIC_SERVER_RDIO;
		clientMgt::init(__CLASS__);
	}	
	
	function initHicidInfo($hicid)
	{
		$phydev = PHYDEV_TYPE_ZIGBEE;
		if( defined('FJ_TRAN_PHYDEV') )
		{
			$phydev = FJ_TRAN_PHYDEV;
		}
		$dev = array();
		$dev['PHYDEV']    = $phydev;
		$dev['PHYADDR']   = '0000000000000000';
		$dev['LOGICADDR'] = 0;
		$dev['TMSI']      = 0;
		
		$seq   = 0;
		$cmd   = DEV_CMD_SYS_HICID;		
		$msg   = pack('l',$hicid);
		$info  = HICProto::genHICHeader($dev,$cmd,$seq);
		$info .= $msg;
		HICProto::genProtoHeader($info);
		return $info;
	}
	
	//连接后马上写标记
	static function onConn($id)
	{
		//查找当前hicid
		//$hicinfo = unserialize(file_get_contents('/usr/db/addFlag'));
		//$hicid= intval($hicinfo['hicId']);
		
		//$info = self::initHicidInfo($hicid);
		//server::writeconn($id,$info);

		//检查两个连接是否都已经就绪。如果已经就绪，则映射
		if( NULL != rdio::$client && NULL != hicConn::$client )
		{
			server::mapConn(rdio::$client,hicConn::$client);
		}
	}
}

class hicConn extends clientMgt
{
	static $client;
	static $addr     = NULL;//'tcp://192.168.93.1:'.HIC_SERVER_EXTHOST;
	static $livetime = 30;  //设置tcp超时重新连接时间为60秒
	static $timeout  = false;
	
	static $fjconninfo = NULL;
	
	static function init()
	{
		clientMgt::init(__CLASS__);
	}
	
	static function getConnAddr()
	{
		self::$fjconninfo = Cache::get('fjconninfo');
		if( false == self::$fjconninfo )
		{
			return false;
		}
		
		//如果不是本地的直接返回
		if( !self::$fjconninfo['LOCAL'] )
		{
			return false;
		}
		$server = self::$fjconninfo['SERVER'];
		$port   = self::$fjconninfo['DEVRPORT'];
		
		self::$addr  = "tcp://$server:$port";
		return true;
	}
	
	//连接后马上写标记
	static function onConn($id)
	{
		$attrindex = 1;
		$hicid     = self::$fjconninfo['HICID'];

		//检查两个连接是否都已经就绪。如果已经就绪，则映射
		if( NULL != rdio::$client && NULL != hicConn::$client )
		{
			server::mapConn(rdio::$client,hicConn::$client);
		}
		
		//MAC-PHYDEV-TOKEN
		$mac    = HICInfo::getPHYID();
		$token  = extProtoClass::genSecury();
		$token  = unpack('H*',$token);
		$token  = $token[1];		
		$phydev = PHYDEV_TYPE_ZIGBEE;
		if( defined('FJ_TRAN_PHYDEV') )
		{
			$phydev = FJ_TRAN_PHYDEV;
		}
		$wi = "$mac-$phydev-$token-$hicid-$attrindex\n";
		server::writeconn( $id, $wi);
	}
}

$GLOBALS['dstpSoap']->setModule('local','sn');
if( false == $GLOBALS['dstpSoap']->getSN() )
{
	server::regIf('fjactive',  0,    false);
}
else
{
	server::regIf('rdio',      0,    false);
}

server::regIf('hicConn',   0,    false);
server::start();

?>