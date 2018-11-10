<?php
/**
*手机上线通知脚本，在有客人手机登录的时候通知
*
*/
if(PHP_SAPI!='cli') die('invalid opration');

require_once('/www/App/a/config/dstpCommonInfo.php'); 
require_once('/www/App/b/3rdparty/uci/uci.class.php');

function sendMacInfoToPort($mac,$ip,$name,$source,$action,$port = 0)
{
	$msg = array(
		'action' => $action,
		'mac'    => $mac,
		'ip'     => $ip,
		'name'   => $name,
		'source' => $source,
	);
	$msg = serialize($msg);
	//$msg = pack("a3a17a15a30C1",$msg['action'],$msg['mac'],$msg['ip'],$msg['name'],$msg['source']);
	$GLOBALS['dstpSoap']->setModule('home','if');
	$GLOBALS['dstpSoap']->sendMsgBySocket(HIC_SERVER_SWIFI,$msg);

	//$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);    
	//$connection = socket_connect($socket, $host, $port);   
	//socket_write($socket, $msg);  
	//socket_close($socket);	
}	

$mac	= $_SERVER['argv'][3];	//获取MAC地址
$mac    = strtoupper(trim($mac));
$status = $_SERVER['argv'][2];  //SSID状态，hide或show
$action = trim($_SERVER['argv'][1]);
$GLOBALS['dstpSoap']->setModule('home','client');

if('add'==$action)
{	
	//wifi连线处理
	$ip   = NULL;
	$name = NULL;

	//获取IP和名称.
	//分机属于桥接模式无法通过arp方式获取到ip地址和name
	if( APP_FJ != HIC_APP )
	{
		$i = 0;
		uci_base::getInfoByMAc($mac,$ip,$name);
		while($i++ < 5 ) //第一次连接时，dhcp更新可能比较慢
		{
			sleep(1);
			uci_base::getInfoByMAc($mac,$ip,$name);
			if( false !=$name )
			{
				break;
			}
		}
	}

	if(false==$ip )
	{
		$ip = '255.255.255.255';
	}
	if ( false==$name )
	{
		$name = '?';
	}

	$source = DEV_CONNECTHIC_SSID;
	if( 'hide' == $status )
	{
		$source = DEV_CONNECTHIC_DEVSSID;
	}

	if( APP_FJ == HIC_APP ) 
	{
		sendMacInfoToPort($mac,$ip,$name,$source,$action);
	}
	else
	{
		$GLOBALS['dstpSoap']->clientConnect($mac,$ip,$name,$source);
	}
}
if('del'==$action)
{
	//wifi断线处理
	if( APP_FJ == HIC_APP )
	{
		sendMacInfoToPort($mac,'255.255.255.255','?',DEV_CONNECTHIC_SSID,$action);
	}
	else {
		$GLOBALS['dstpSoap']->clientOffline($mac);
	}
}

?>
	
