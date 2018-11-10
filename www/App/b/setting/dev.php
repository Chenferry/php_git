<?php
@ini_set ( 'max_execution_time', 1 * 60 * 60 );
@ignore_user_abort ( true );

// 设置无线开关和SSID。向导中同时可以选择恢复或者启用
include_once ('../../a/config/dstpCommonInfo.php');

// 检测是否绑定了用户，如果已经绑定，直接显示主页面
$GLOBALS ['dstpSoap']->setModule ( 'frame' );
$isBind = $GLOBALS['dstpSoap']->isBindUser();
if ( $isBind )
{
	include_once('../../a/config/dstpUserCheck.php');  
}


include_once ('uci/uci.class.php');

//走到这儿网络已经联通，获取一次天气预报信息
//include_once('plannedTask/PlannedTask.php');
//$planTask = new PlannedTask('home','envend', 5);
//$planTask->reportDevInfo();		

/*************************************/
function genNewDev()
{
	/* 判断设备是否已经注册成功，如果已经注册成功 */
	// $c = new
}
/**下载配置文件
 * @return boolean
 */
//如果user和psw为空，表示根据token所指示用户操作
function restoreCfg($user,$psw,$hicid)
{
	$GLOBALS['dstpSoap']->setModule('local','sn');
	$sn = $GLOBALS['dstpSoap']->getSN();

	$phyid = HICInfo::getPHYID ();
	
	if( NULL == $user && NULL == $psw )
	{
		$psw = getHICToken(); //表示根据token进行恢复操作
	}
	$GLOBALS ['dstpSoap']->setModule ( 'app', 'init' );
	$file = $GLOBALS ['dstpSoap']->restoreCfg ( $user, $psw, $hicid, $phyid ,$sn); // 获取下载文件
	if (! $file) 
	{
		return $GLOBALS ['dstpSoap']->getErr ();
	}
	if( $file['c'] == false )
	{
		return '该用户在恢复出厂之前没有备份，请选择其他用户进行还原或者重新注册新用户！';
	}
	$GLOBALS ['dstpSoap']->setModule ( 'local', 'dev' );
	$res=$GLOBALS ['dstpSoap']->restoreHICCfg ( $file);

	//向服务器报一次心跳
	$GLOBALS['dstpSoap']->setModule('local','local');
	$GLOBALS['dstpSoap']->reportHICStatusDelay();
	
	//强制proxystub马上去连接
	Cache::del("getProxyServertime");
	
	//和云服务器同步设备上的用户和登录信息
	$GLOBALS ['dstpSoap']->setModule ( 'local', 'dev' );
	$r = $GLOBALS ['dstpSoap']->syncHicInfo ();

	
	//马上获取天气预报
	include_once('plannedTask/PlannedTask.php');
	$planTask = new PlannedTask('home','envend');
	$planTask->reportDevInfo();
    include_once('procd/service.class.php');
    service::dbBackup();

    return 0;
}

function checkLogin($user,$psw)
{
	$GLOBALS ['dstpSoap']->setModule ( 'app', 'hic' );
	if( NULL == $user && NULL == $psw )
	{
		$token = getHICToken();
		$r = $GLOBALS ['dstpSoap']->checkLoginCookie ( $token);
	}
	else
	{
		$r = $GLOBALS ['dstpSoap']->checkLoginUser ( $user, $psw );
	}
	return $r;
}

// 启用新设备
//如果user和psw为空，表示根据token所指示用户操作
function startDev($user,$psw,$newuser=true,$ssid,$encryption,$key,$hidden=0)
{
	$GLOBALS ['dstpSoap']->setModule ( 'local', 'dev' );
	$hicid = $GLOBALS ['dstpSoap']->initNewHic ();
	if ( !validID( $hicid ) )
	{
		include_once ('a/commonLang.php');
		return sprintf(HIC_ERR_INIT, $GLOBALS ['dstpSoap']->getErr());
	}
	
	// 新用户注册。
	if ( $newuser )
	{
		$GLOBALS ['dstpSoap']->setModule ( 'app', 'reg' );
		$r = $GLOBALS ['dstpSoap']->regBindUser ( $user, $psw );
		if ( false == $r )
		{
			return $GLOBALS ['dstpSoap']->getErr ();
		}
	}
	else
	{
		$GLOBALS ['dstpSoap']->setModule ( 'app', 'hic' );
		if( NULL == $user && NULL == $psw )
		{
			$token = getHICToken();
			$r = $GLOBALS ['dstpSoap']->checkLoginCookie ( $token);
		}
		else
		{
			$r = $GLOBALS ['dstpSoap']->checkLoginUser ( $user, $psw );
		}
		if (!$r)
		{
			include_once ('a/commonLang.php');
			return USER_PSW_ERR;
		}

		$GLOBALS ['dstpSoap']->setModule ( 'app', 'reg' );
		if( NULL == $user && NULL == $psw )
		{
			list($userid,$time,$oldhicid,$rand,$flag) = explode('-', $token);
			$r = $GLOBALS ['dstpSoap']->bindUser ( $userid,false,false );
		}
		else
		{
			$r = $GLOBALS ['dstpSoap']->bindUser ( $user );
		}
	}

	//向服务器报一次心跳
	$GLOBALS['dstpSoap']->setModule('local','local');
	$GLOBALS['dstpSoap']->reportHICStatusDelay();
	
	// 和云服务器同步设备上的用户和登录信息
	$GLOBALS ['dstpSoap']->setModule ( 'local', 'dev' );
	$r = $GLOBALS ['dstpSoap']->syncHicInfo ();
	
	//马上获取天气预报
	include_once('plannedTask/PlannedTask.php');
	$planTask = new PlannedTask('home','envend');
	$planTask->reportDevInfo();
	
	$GLOBALS['dstpSoap']->setModule('local','local');
	$GLOBALS['dstpSoap']->setHICName($ssid);

	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$GLOBALS['dstpSoap']->changeWifiCfg($ssid,$encryption,$key,$hidden);
	
		
	firewallInit ();
	stopBcastSer();
	return 0;
}

/**
 * 放火墙初始化
 * @author ldh
 */
function firewallInit() {
	$name = NULL;
	$mac = NULL;
	uci_base::getInfoByIP ( $_SERVER ['REMOTE_ADDR'], $mac, $name );
	if( NULL == $mac )
	{
		//这儿有问题，因为系统之前还没安装，如果手机连接到分机上实际上也是无法设置
		$c	= new TableSql('homeclient');
		$mac = $c->queryValue('MAC','IP=?',array($_SERVER ['REMOTE_ADDR']));
	}
	
	$GLOBALS ['dstpSoap']->setModule ( 'home', 'client' );
	$GLOBALS ['dstpSoap']->initClientsACL ( $mac );
}

/**
 * 停止bcastserver监听广播安装进程
 * @return [type] [description]
 */
function stopBcastSer()
{
    $pid = `pgrep -f 'bcastserver.php'`;
    `kill -9 $pid`;
}
// 查询用户当前所绑定的设备
function getUserDev($user,$psw)
{
	if( NULL == $user )
	{
		$psw = getHICToken();
	}
	$GLOBALS ['dstpSoap']->setModule ( 'app', 'init' );
	$r = $GLOBALS ['dstpSoap']->getUserBindList ( $user, $psw );
	if ( false === $r )
	{
		$result = array('is'=>false,'info'=>$GLOBALS ['dstpSoap']->getErr ());
		return $result;
	}
	foreach ($r as $key => $value) 
	{
		$r[$key]['CTIME'] = Date('Y-m-d H:i',$value['CTIME']);
	}
	return array('is'=>true,'info'=>$r);
}
function getWifiInfo()
{
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$ssid    =$GLOBALS['dstpSoap']->getDevWifiInfo();
	$GLOBALS ['dstpSoap']->setModule ( 'frame' );
	$isBind = $GLOBALS['dstpSoap']->isBindUser();
	if( !$isBind )
	{
		//默认让用户要输入wifi密码
		$ssid['encryption'] = 1;
	}
	return $ssid;
}
util::startSajax( array('checkLogin','getWifiInfo','startDev','getUserDev','restoreCfg'));

?>