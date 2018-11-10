<?php
//设置上网方式和密码
include_once('../../a/config/dstpCommonInfo.php');  

//检测是否绑定了用户，如果已经绑定，则需要检测登录
$GLOBALS['dstpSoap']->setModule('frame');
$isBind = $GLOBALS['dstpSoap']->isBindUser();
if ( $isBind )
{
	include_once('../../a/config/dstpUserCheck.php');  
}
include_once('uci/uci.class.php');

function netSetting($params)
{
	if( $GLOBALS['isBind'] )
	{
		$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
		if( true !== $r )
		{
			return -3;
		}
	}
	$ret = 0;
	if (empty($params)) {
		return array(
	                'ret' => array(
	                	'isSuccess' => false,
	                	'statusCode' => -1,
	                	'description' => '参数不能为空'
	                	)
	            );
	}

	// $data = json_decode($params);
	$data = $params;
	$wanType  = $data['wantype'];
	$connWay = strtolower(trim($wanType));
	$username  = $data['username'];
	$password  = $data['password'];
	$ssid  = $data['ssid'];
	$apn  = $data['apn'];

	if ('static' == $connWay) {
		$ip  = $data['ip'];
		$route  = $data['route'];
		if (empty($ip) || empty($route)) {
			return array(
		                'ret' => array(
		                	'isSuccess' => false,
		                	'statusCode' => -1,
		                	'description' => 'ip地址或route不能为空'
		                	)
		            );
		}
		$mask  = $data['mask'] ? $data['mask'] : '255.255.255.255';
		$dns1  = $data['dns1'] ? $data['dns1'] : '0.0.0.0';
		$dns2  = $data['dns2'] ? $data['dns2'] : '0.0.0.0';
	}

	$ssid = trim($ssid);
	if ( NULL == $ssid )
	{
		return array(
	                'ret' => array(
	                	'isSuccess' => false,
	                	'statusCode' => -1,
	                	'description' => '中继方式ssid不能为空'
	                	)
	            );
	}

	switch( $connWay )
	{
		case 'pppoe':
			PPPoE::setAccount($username,$password);
			break;
		case 'dhcp':
			wan::setDHCP();
			break;
		case 'static':
			wan::setStatic($ip,$mask,$route,$dns1,$dns2);
			break;
		case 'apcli':
			$ret = SSID::setApcli($ssid, $password);
			break;
		case 'xg':
			if ('3gnet' == $apn) {
				//联通
				$apn = '3gnet';
				$dialnumber = '*99#';
				$service = 'umts';
			} else if ('ctnet' == $apn) {
				//电信
				$apn = 'ctnet';
				$dialnumber = '#777';
				$service = 'evdo';
			} else if ('cmnet' == $apn) {
				//移动
				$apn = 'cmnet';
				$dialnumber = '*99***1#';
				$service = 'umts';
			} else {
				$ret = -3;
				return $ret;
				// return 'unknown apn type';
				$apn = strtolower(trim($apn));
				$service = strtolower(trim($service));
				$dialnumber = strtolower(trim($dialnumber));
			}
			
			$ret = PPPoE::setxgAccount($apn, $service, $dialnumber, $username, $password);
			break;
		default:
			break;
	}
	if ($ret < 0) {
		//-1 参数错误 -2 不能连接信道为11的ssid -3 未知错误
		return $ret;
	}
	if ($connWay != 'apcli') {
		$ret = SSID::disableApcli();
	}

	wan::restartNetwork();
	//判断SSID是否有变化.只有在向导时才可以修改SSID。其它的时候只能通过云端修改
	$ossid    = SSID::getSSID();
	if ( $ssid != $ossid['name'] && isset($_GET['guide']) && $connWay != 'apcli')
	{
		$GLOBALS['dstpSoap']->setModule('local','local');
		$GLOBALS['dstpSoap']->setHICName($ssid, !isset($_GET['guide']));
	}

	// //配置apcli无线连接 需要重启系统才能连接上网，原因不明
	// if (strtolower($wanType) == 'apcli') {
	// 	uci_base::restart();
	// 	return;
	// }

	//如果是无线连接方式则需要down掉有线连接的wan口
	if ($connWay == 'apcli') {
		wan::downWireWanPort();
	}

	//设置网络连接后，尝试获取网络连接状态，最多等待25s
	$connSt = false;
	for ($i=0; $i < 5; $i++) {
		$connSt = isConnect();
		if ($connSt) {
			return $connSt;
		}
	    sleep(5);
	}
	return $connSt;
}

function setNetwork($ssid,$wanType,$username,$password,$apn='3gnet')
{
	if( $GLOBALS['isBind'] )
	{
		$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
		if( true !== $r )
		{
			return $r;
		}
	}
	$ret = 0;
	$ssid = trim($ssid);
	if ( NULL == $ssid && isset($_GET['guide']))
	{
		return 'SSID IS NULL';
	}

	$connWay = strtolower(trim($wanType));
	switch( $connWay )
	{
		case 'pppoe':
			PPPoE::setAccount($username,$password);
			break;
		case 'dhcp':
			wan::setDHCP();
			break;
		case 'static':
			break;
		case 'apcli':
			$ret = SSID::setApcli($ssid, $password);
			break;
		case 'xg':
			if ('3gnet' == $apn) {
				//联通
				$apn = '3gnet';
				$dialnumber = '*99#';
				$service = 'umts';
			} else if ('ctnet' == $apn) {
				//电信
				$apn = 'ctnet';
				$dialnumber = '#777';
				$service = 'evdo';
			} else if ('cmnet' == $apn) {
				//移动
				$apn = 'cmnet';
				$dialnumber = '*99***1#';
				$service = 'umts';
			} else {
				return 'unknown apn type';
				$apn = strtolower(trim($apn));
				$service = strtolower(trim($service));
				$dialnumber = strtolower(trim($dialnumber));
			}
			
			$ret = PPPoE::setxgAccount($apn, $service, $dialnumber, $username, $password);
			break;
		default:
			break;
	}
	if ($connWay != 'apcli') {
		$ret = SSID::disableApcli();
	}
	//wan::restart();
	wan::restartNetwork();
	//判断SSID是否有变化.只有在向导时才可以修改SSID。其它的时候只能通过云端修改
	$ossid    = SSID::getSSID();
	if ( $ssid != $ossid['name'] && isset($_GET['guide']) && $connWay != 'apcli')
	{
		$GLOBALS['dstpSoap']->setModule('local','local');
		$GLOBALS['dstpSoap']->setHICName($ssid, !isset($_GET['guide']));
	}

	if ($ret < 0) {
		//-1 参数错误 -2 不能连接信道为11的ssid -3 未知错误
		return $ret;
	}
	// //配置apcli无线连接 需要重启系统才能连接上网，原因不明
	// if (strtolower($wanType) == 'apcli') {
	// 	uci_base::restart();
	// 	return;
	// }

	//如果是无线连接方式则需要down掉有线连接的wan口
	if ($connWay == 'apcli') {
		wan::downWireWanPort();
	}

	//设置网络连接后，尝试获取网络连接状态，最多等待25s
	$connSt = false;
	for ($i=0; $i < 5; $i++) {
		$connSt = isConnect();
		if ($connSt) {
			return $connSt;
		}
	    sleep(5);
	}
	return $connSt;
}

function isConnect()
{
	$GLOBALS['dstpSoap']->setModule('local','local');
	return $GLOBALS['dstpSoap']->isConnect();
}

function getScanWifiInfo()
{
	return SSID::getScanWifiListInfo();
}

function getApcliInfo()
{
	$cfgWifi    = SSID::getApcliCfgInfo();
	$connSt     = isConnect();
	$cfgWifi['isConnect'] = $connSt;
	return $cfgWifi;
}

function getAllApInfo()
{
	$wifiArry   = array();
	$wifiList   = SSID::getScanWifiListInfo();
	$cfgWifi    = SSID::getApcliCfgInfo();
	$connSt     = isConnect();

	$cfgWifi['isConnect'] = $connSt;
	$wifiArry['cfgwifi']  = $cfgWifi;
	$wifiArry['wifilist'] = $wifiList;
	return $wifiArry;
}

/**
 * 设置或者修改wifi信息
 * @param ssid 修改wifi网络名称
 * @param encryption 加密方式或者是否开启加密 0表示未加密 1表示加密
 * @param key 加密密钥，不能少于6位
 * @return 0 OK -1 参数错误，-2 密码不符合规范 -3 未知错误
 */
function changeWifiCfg($ssid,$encryption,$key,$hidden=0)
{
	if( $GLOBALS['isBind'] )
	{
		$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
		if( true !== $r )
		{
			return -1;
		}
	}
	$ret  = 0;
	$ssid = trim($ssid);
	//$enc  = trim($encryption);
	$pwd  = trim($key);
	
	if (empty($ssid))
	{
		return -1;
	}

	if ((1 == $encryption) && (empty($pwd) || (strlen($pwd) < 8))) {
		return -2;
	}

	if (1 == $encryption) {
		$enc = "psk2";
	} else {
		$enc = "none";
	}

	$ret = SSID::setWifi($ssid, $enc, $pwd, $hidden);
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
	return SSID::getSSID();
}

function getNetSetInfo()
{
	$ssid    = SSID::getSSID();
	$wanType = trim(wan::getType());
	if( NULL == $wanType )
	{
		$wanType = 'pppoe';
	}

	$wanMac = trim(wan::getHWAddr());
	$ppoecfg  = PPPoE::getAccount();
	//$wifiList = SSID::getScanWifiListInfo();
	$apcliCfg = SSID::getApcliCfgInfo();
	if ((!empty($apcliCfg)) && (trim($apcliCfg['apCliEnable']) == 1)) {
		$wanType = 'apcli';
	}	
	$xgCfg = getxgAccountInfo();
	$cfg = array();
	$cfg['ssid'] = $ssid['name'];
	$cfg['wanType'] = $wanType;
	$cfg['wanMac'] = $wanMac;
	$cfg['pppoecfg'] = $pppoecfg;
	$cfg['apcliCfgInfo'] = $apcliCfg;
	$cfg['xgInfo'] = $xgCfg;
	$cfg['hasXG'] = false;
	if (defined('HIC_SYS_HAVE4G') && (true == HIC_SYS_HAVE4G)) {
		$cfg['hasXG'] = true;
	}
	return $cfg;
}


function getxgAccountInfo()
{
	$xgAccount = PPPoE::getxgAccount();
	if (null == $xgAccount) {
		return null;
	} else {
		return $xgAccount;
	}
}

util::startSajax( array('getNetSetInfo','setNetwork','isConnect','getScanWifiInfo',
	'getApcliInfo','getAllApInfo','changeWifiCfg','getDevWifiInfo','netSetting') );

?>