<?php
@ini_set ( 'max_execution_time', 1 * 60 * 60 );
@ignore_user_abort ( true );

// 设置无线开关和SSID。向导中同时可以选择恢复或者启用
include_once ('../../a/config/dstpCommonInfo.php');
header("Content-type:text/html;charset=utf-8");

//如果是直接修改idflag的，则预先处理
if( isset($_GET['idflag'] ) && isset($_GET['check'] ) )
{
	$idflag = $_GET['idflag'];
	$check  = $_GET['check'];
	$GLOBALS['dstpSoap']->setModule('local','sn');
	$r = $GLOBALS['dstpSoap']->changeLogo($idflag,$check);
	if(!$r)
	{
		echo $GLOBALS['dstpSoap']->getErr();
	}
	else
	{
		echo $r;
	}
	die();
}


//已经激活的，直接导向登陆页面
$GLOBALS['dstpSoap']->setModule('local','sn');
if( false != $GLOBALS['dstpSoap']->getSN() )
{
	debug("设备已经激活成功");
	die ();
}

//判断是否具备激活条件
if( !defined('HIC_SYS_NOZIGBEE') )
{
	//串口需要能连接通讯
	if( !file_exists('/tmp/testHICOK') )
	{
		debug("串口通讯还未成功");
		die();
	}
	//要有设备加入，保证无线收发正常
	$c = new TableSql('homedev','ID');
	$dev = $c->queryValue('ID','PHYDEV=?',array(PHYDEV_TYPE_ZIGBEE));
	if( !validID($dev) )
	{
		debug("系统未收到设备请求，请检查是否有设备能正常加入");
		die();
	}
}

//直接自动激活，如果激活错误，显示提示信息
$GLOBALS['dstpSoap']->setModule('local','sn');
$r = $GLOBALS['dstpSoap']->activeSN(trim($custominfo));
if(!$r)
{
	debug($GLOBALS['dstpSoap']->getErr());
	die();
}

$GLOBALS['dstpSoap']->setModule('local','sn');
if( false != $GLOBALS['dstpSoap']->getSN() )
{
	debug("设备已经激活成功");
	die ();
}
else
{
	debug("设备激活失败");
	die ();
}


?>