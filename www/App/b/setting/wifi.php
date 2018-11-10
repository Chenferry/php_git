<?php
include_once('../../a/config/dstpCommonInfo.php');  
if ( !isset($_GET['guide']) )
{
	include_once('../../a/config/dstpUserCheck.php');  
}

//判断是否激活
$GLOBALS['dstpSoap']->setModule('local','sn');
if( false == $GLOBALS['dstpSoap']->getSN() )
{
	//导向选择模式
	$url = '../../b/setting/sn.php';
	header('Location:'.$url);
	die();
}

$GLOBALS['dstpSoap']->setModule('local','local');
if ($GLOBALS['dstpSoap']->isConnectToCloud() && isset($_GET['guide']))
{
	//导向选择模式
	$url = '../../b/setting/dev.php?guide=1';
	header('Location:'.$url);
	die();
}
//检测是否绑定了用户，如果已经绑定，直接显示主页面
$GLOBALS['dstpSoap']->setModule('frame');
if ( $GLOBALS['dstpSoap']->isBindUser() && isset($_GET['guide']))
{
	$url = '../../a/frame/mainframe.php';
	header('Location:'.$url);
	die();
}

include_once('uci/uci.class.php');

/**
 * 设置或者修改wifi信息
 * @param ssid 修改wifi网络名称
 * @param encryption 加密方式或者是否开启加密 0表示未加密 1表示加密
 * @param key 加密密钥，不能少于6位
 * @return 0 OK -1 参数错误，-2 密码不符合规范 -3 未知错误
 */
function changeWifiCfg($ssid,$encryption,$key,$hidden=0)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return -1;
	}
	$GLOBALS['dstpSoap']->setModule('local','local');
	$GLOBALS['dstpSoap']->setHICName($ssid);
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$ret = $GLOBALS['dstpSoap']->changeWifiCfg($ssid,$encryption,$key,$hidden);
	if (0 == $ret) {
		//配置正确则先返回结果给UI，然后子进程再执行网络重启生效
		$pid = pcntl_fork();
		if ($pid > 0) {
			return $ret;
		} else if (0 == $pid) {
			sleep(1);
			network::restart();
		} else {
			logErr('fun:' . __FUNCTION__ . ' line:' . __LINE__ . ' cound not fork!');
		}
	}

	return $ret;
}

function getDevWifiInfo()
{
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	return $GLOBALS['dstpSoap']->getDevWifiInfo();
}

util::startSajax( array('changeWifiCfg','getDevWifiInfo') );


?>