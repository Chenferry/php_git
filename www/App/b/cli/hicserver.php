<?php  
//串口通讯接口。该文件只实现串口读取和分发。串口发送由dev接口负责
//只能被命令行调用。这儿应该还要判断下不能重复调用
if( 'cli'!=PHP_SAPI )
{
	exit(-1);
}
$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);
include_once( dirname(__FILE__).'/proxyserver.php' );
include_once('util/hic.proto.php');
include_once( dirname(__FILE__).'/hicproc.php' );

//和hicdelay中的psyse差别在于，psyse中处理的都是发往外网的请求，可能被阻塞
//这儿的请求都是本地的，需要马上得到执行
include_once( dirname(__FILE__).'/hicdelayClass.php' );

if( APP_ZJ != HIC_APP )
{
	exit(-1);
}
  

///////////////////////////////////////////////////////

class rdio extends clientMgt
{
	static $client;
	static $addr     = NULL;//'tcp://127.0.0.1:'.HIC_SERVER_RDIO;
	static $livetime = 90;//保活时间，秒为单位
    static $timeout  = false;

	static $hicidRes=false;//是否收到协调器回复的HICID确认消息
	
	static function init()
	{
		//如果是大主机，则无需启动该进程，直接退出
		if( defined('HIC_SYS_NOZIGBEE') )
		{
			//直接判断处理自动激活
			$GLOBALS['dstpSoap']->setModule('local','sn');
			if( false == $GLOBALS['dstpSoap']->getSN() )
			{
				$GLOBALS['dstpSoap']->setModule('local','sn');
				$r = $GLOBALS['dstpSoap']->activeSN();			
			}
			return true;
		}

		self::$addr  = 'tcp://127.0.0.1:'.HIC_SERVER_RDIO;
		clientMgt::init(__CLASS__);
	}
	
	static function onRead($id,&$info)
	{
		self::$timeout = false;
		return HICMsgProc::onRead($id,$info);
	}
	
	//连接后马上写标记
	static function onConn($id)
	{
        HICMsgProc::onAccept($id,'console');
	}

	//经常会发现和串口一直无法通讯上。但如果杀掉进程重新拉起后就可以
	static function hasTimeout()
	{
		exit();
	}

	//接到一个完整包后处理。主要是处理hicid的回应消息
	static function procDevMsg()
	{
		// Cache::set('hicserverlive','live',120);
		//如果逻辑地址为空，则为协调器发来的.目前只有一条消息，简单处理
		if( self::$hicidRes )
		{
			return;
		}
		/* 如果还没激活，就需要写测试结果。激活时需要检测串口通讯是否正常 */
		$GLOBALS['dstpSoap']->setModule('local','sn');
		if( false == $GLOBALS['dstpSoap']->getSN() )
		{
			file_put_contents('/tmp/testHICOK',"ok");
			
			//需要保证能够正常无线通讯，也就是至少要有一个zigbee设备接入
			$c = new TableSql('homedev','ID');
			$dev = $c->queryValue('ID','PHYDEV=?',array(PHYDEV_TYPE_ZIGBEE));
			if( !validID($dev) )
			{
				//如果还没任何zigbee设备的加入信息，无法确定模块的无线是否正常
				//暂时也不激活，留着下一次再继续检测
				return;
			}
			//自动尝试去激活
			$GLOBALS['dstpSoap']->setModule('local','sn');
			$r = $GLOBALS['dstpSoap']->activeSN();
			if( true !== $r )
			{
				return; 
			}
			//如果激活成功，则继续下面处理
		}
		
		self::$hicidRes = true;
		return;
	}
	
}


server::regIf('HICMsgRec',  getRealPort(HIC_SERVER_SWIFI), false); //其它进程发送信息到本进程
server::regIf('HICMsgProc', getRealPort(HIC_SERVER_RWIFI), 68);    //MAC地址字串的可能最大长度 //10:07:23:C8:E2:F4
server::regIf('psys',       getRealPort(HIC_SERVER_DELAY), false); 

if( 'b' == HIC_LOCAL )
{
	server::regIf('rdio',   0,                false); //2888
}

server::start();
?>