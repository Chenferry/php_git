<?php
//需要libevent扩展
///////////////////////////////////////////
if( 'cli'!=PHP_SAPI )
{
    exit(-1);
}

//和hicserver中的psys差别在于，这儿psyse中处理的都是发往外网的请求，可能被阻塞
$GLOBALS['psysAllowBlock'] =  true;

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
require_once($dstpCommonInfo);
require_once( dirname(__FILE__).'/proxyserver.php' );
require_once( dirname(__FILE__).'/hicdelayClass.php' );


if( APP_ZJ != HIC_APP )
{
	exit(-1);
}

/////摄像头截图处理。每传一次图片建立一次连接，传完即断////////////////////////
class prtsc
{
	static $devMap = array();

	static function onAccept($id)
	{
		$conn   = server::getconn($id);
		$remote = stream_socket_get_name ( $conn , true );
		list($ip,$port)    = explode(':',$remote);
		//根据IP获取摄像头的设备ID		
		$GLOBALS['dstpSoap']->setModule('home','end');
		$devid = $GLOBALS['dstpSoap']->getDevidFromAddr(NULL,$ip);
		if ( !validID($devid) )
		{
			server::closeEventConn($id);
			return;
		}
		self::$devMap[$id] = $devid;

		return;
	}
	//读完整后才写进去，避免乱序。写完就关闭，这时读取是最完整的了
	static function onClose($id)
	{
		if( !isset( self::$devMap[$id] ) )
		{
			return;
		}
		//获取指定摄像头设备ID
		$devid = self::$devMap[$id];
		$img = server::getrinfo($id);
		
		//获取摄像头的属性信息
		$c = new TableSql('homeattr','ID');  
		//这儿要求摄像头只有一个属性，或者需要时第一个属性
		$attrid = $c->queryValue('ID','DEVID=?',array($devid));

		//这个应该避免直接调用，以免因为网络阻塞，导致整个服务进程挂掉
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask('app','hic', 0);
		$planTask->saveScreen($devid,$attrid,$img);	

		unset(self::$devMap[$id]);
	}
}

server::regIf('psys',  getRealPort(HIC_SERVER_DELAY_E), false); 
if( 'b' == HIC_LOCAL )
{
	server::regIf('prtsc', HIC_SERVER_SCREEN,false); 
}

server::start();  

?> 