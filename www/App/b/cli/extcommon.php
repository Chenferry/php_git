<?php  
//串口通讯接口。该文件只实现串口读取和分发。串口发送由dev接口负责
//只能被命令行调用。这儿应该还要判断下不能重复调用
if( 'cli'!=PHP_SAPI )
{
	exit(-1);
}

$GLOBALS['extportindex'] = intval($_SERVER['argv'][1]);

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);

//如果不是有大性能的主机，则直接返回
//HIC_SYS_POWER这个数字表示系统性能能力，性能越强，则可以启动越多的分机监听进程
if ( !defined('HIC_SYS_POWER') ) 
{
	exit(-1);
}
if( HIC_SYS_POWER <= $GLOBALS['extportindex']  )
{
	exit(-1);
}

include_once( dirname(__FILE__).'/proxyserver.php' );
include_once('util/hic.proto.php');
include_once( dirname(__FILE__).'/hicproc.php' );


server::regIf('HICMsgProc', HIC_SERVER_EXTRSTART+$GLOBALS['extportindex'], true);    //MAC地址字串的可能最大长度 
server::regIf('HICMsgRec',  HIC_SERVER_EXTSSTART+$GLOBALS['extportindex'], false); //其它进程发送信息到本进程
server::start();
?>