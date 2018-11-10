<?php
	include_once('../../a/config/dstpCommonInfo.php');  
	include_once('uci/uci.class.php');	
	

	$reqUrl = $_GET['redirectUrl'];
	$ip = $_SERVER['REMOTE_ADDR'];
	if ( isset($_SERVER['HTTP_CLIENTIP']) )
	{
		$ip = $_SERVER['HTTP_CLIENTIP'];//BAE下remote_addr是内部地址
	}

	$c	= new TableSql('homeclient','ID');
	$info = $c->query('ID,PERIOD','IP=?',array($ip));
	if( NULL == $info )
	{
		$mac = NULL;
		$name = NULL;
		uci_base::getInfoByIP($ip,$mac,$name);
		$info = $c->query('ID,PERIOD','MAC=?',array($mac));
		if( NULL != $info )
		{
			$info['IP'] = $ip;
			$c->update($info);
		}
	}
	$period = $info['PERIOD'];
	switch( $period )
	{
		case DEV_CLIENT_REJECT:
			die();
			break;
		case DEV_CLIENT_INIT:
		case DEV_CLIENT_REQUEST:
		default:
			break;
	}
	
	
	function requestAllow($name)
	{
		$name = trim($name);
		if( NULL == $name )
		{
			$res = array(
				'result' => false,
				'desc' => '告诉下主人您的名字吧',
				);
			return $res;
		}


		$ip = $_SERVER['REMOTE_ADDR'];
		if ( isset($_SERVER['HTTP_CLIENTIP']) )
		{
			$ip = $_SERVER['HTTP_CLIENTIP'];//BAE下remote_addr是内部地址
		}
		$c	= new TableSql('homeclient','ID');
		$info = $c->query('ID,PERIOD,MAC,EXTID','IP=?',array($ip));
		if( NULL == $info )
		{
			$res = array(
				'result' => false,
				'desc' => '好像有点问题，您可以重连下wifi试下',
				);
			return $res;			
		}
		if( DEV_CLIENT_INIT == $info['PERIOD'] )
		{
			//一定要保证告警设置成功了才能修改权限
			$info['NAME']   = substr($name,0,21);
			$c->update($info);

			include_once('b/homeLang.php');
			$GLOBALS['dstpSoap']->setModule('frame','alarm');
			$r = $GLOBALS['dstpSoap']->alarm(INVALID_ID,$info['ID'],HOME_DEV_WIFICONNECT, array('m'=>'home','s'=>'client'));
			if(!$r)
			{
				$res = array(
					'result' => false,
					'desc' => '发送请求失败，请一会再试',
					);
				return $res;
			}

			//一定要保证告警设置成功了才能修改权限
			$info['PERIOD'] = DEV_CLIENT_REQUEST;
			$c->update($info);
		}

		$wifiInfo = SSID::getSSID();
		$enc = trim($wifiInfo['encryption']);

		//redirect to user request url when wifi is enc
		if (!empty($enc) || ('none' != $enc)) {
			firewall::cancelRedirectToWebPort(trim($info['MAC']));
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('local','firewall', 0);
			$planTask->setMacPeriod($info['MAC'],$info['PERIOD'],false,$info['EXTID']);
			//sleep(1);
			//header("Location: ".$reqUrl);
		}
		$res = array(
			'result' => true,
			'desc' => '加入成功',
			);
		return $res;
	}
	
	function getRequestStatus()
	{
		$isRequest = false;	
		$isEnc = false;	
		if( DEV_CLIENT_REQUEST == $GLOBALS['period'] )
		{
			$isRequest = true;	
		}
		if (empty($enc) || ('none' == $enc)) {
			$isEnc = false;	
		} else {
			$isEnc = true;	
		}
		return array('isRequest' => $isRequest, 'isEnc' => $isEnc, 'redirectUrl' => $reqUrl);
	}
	
	util::startSajax( array('getRequestStatus','requestAllow'));
?>