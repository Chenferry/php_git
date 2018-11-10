<?php 
 class SSID extends network{
	public $encryption;//SSID encryption method
	public $password;//SSID password
	private static $enum=array('none','wep','psk','psk2','mixed-wpa');//encryption method
	
	

	/**
	 * get password and encryption method of the SSID
	 * @return SSID|password and encryption method
	 */
	public static function getSSID(){
		if ( DSTP_DEBUG )
		{
			return array('name'=>'ssid','encryption'=>'none');
		}
		$ssid=array();
		$ssid['name']			=	`uci get wireless.@wifi-iface[1].ssid`;
		$ssid['name']			=	trim($ssid['name']);
		$ssid['encryption']	    =	`uci get wireless.@wifi-iface[1].encryption`;
		$ssid['encryption']		=	trim($ssid['encryption']);
		$ssid['password']		=	`uci get wireless.@wifi-iface[1].key`;
		$ssid['password']		=	trim($ssid['password']);
		$ssid['hidden']		    =	`uci get wireless.@wifi-iface[1].hidden`;
		$ssid['hidden']		    =	$ssid['hidden'] ? 1 : 0;
		return $ssid;
		
	}
	
	/**
	 * Specifies the transmission power in array of dBm within 2 and 20
	 * @param int $value: dB value
	 * return message string
	 */
	public static function setWiFiPower($value){
		$power=intval($value);
		if($power<=100){
			$result=`uci set wireless.@wifi-device[1].txpower=$power`.`uci commit wireless`;
			if($power==0)
			{
				self::disabled();
			}else{
				self::enabled();
			}
			return $result;
		}else{
			return "the value within 0~100";
		}
	}

	/**
	 * get the transmission  power
	 * @return int unit:dbm
	 */
	public static function getWiFiPower(){
		return intval(`uci get wireless.@wifi-device[1].txpower`);
	}
	
	
	
	
	 /**
	  * get minimum client signal dBm value
	  * @return int
	  */
	public static function getMinSignal(){
		// $dbm=`iwpriv ra0 show stainfo && dmesg -c`;
		$dbm=`iwpriv ra0 stat |grep 'RSSI'`;

		if(preg_match_all('/-[0-9]{2}[ ]{1,12}-[0-9]{2}/', $dbm,$dbms)){

			foreach($dbms[0] as $d){
				if(preg_match_all('/-[0-9]{2}/',$d,$values)){
					$ds[]=max($values[0]);
				}
			}
			return min($ds);
		}
		return 0;
	}
	
	/**
	 * set SSID
	 * @param  string $name 	:the name of SSID by the user filled
	 * for an open network, wep for WEP, psk for WPA-PSK, or psk2 for WPA2-PSK.
	 * 			paramter:encryption
	 *    	 * sample:$key=new SSIDEncryption($encryption,$password);
	 *    				$result=setSSID($name);
	 */
	public static function setSSID($name){
			$name	= trim($name);
			$result=`uci set wireless.@wifi-iface[1].ssid="$name"`;
			$result.=`uci commit wireless`;
			return $result;
	}
		/**
	 * set ecryption
	 * @param  string $name 	:the name of SSID by the user filled
	 * for an open network, wep for WEP, psk for WPA-PSK, or psk2 for WPA2-PSK.
	 * 			paramter:encryption
	 *    	 * sample:$key=new SSIDEncryption($encryption,$password);
	 *    				$result=setSSID($name);
	 */
	public static function setEcrypt($encryption=null,$password=null){
		if($password==false || strlen($password) <8){
			$result.=`uci set wireless.@wifi-iface[1].encryption='none'`;
			$result.='no set password for the length of password less than 8.';
		}else{
		$result.=`uci set wireless.@wifi-iface[1].encryption='$encryption'`;
		$result.=`uci set wireless.@wifi-iface[1].key='$password'`;
				}
		$result.=`uci commit wireless`;
		return $result;
	}
	/**
	 * enable SSID
	 * @return string
	 */
	public static function disabled(){
		return 'wifi enable:'.`uci set wireless.@wifi-device[1].disabled=1`.`uci commit wireless`;
	}
	
	/**
	 * disable SSID
	 * @return string
	 */
	public static function enabled(){
		return 'wireless disable:'.`uci delete wireless.@wifi-device[1].disabled`.`uci commit wireless`;
	}


	public static function restart(){
	    return `wifi up`;
	}

	/**
	 * scan wifi info and save it to /tmp/apscan
	 * 
	 */
	public static function scanWifiInfo()
	{
		static $scanTimes = 0;

		`iwpriv apcli0 set SiteSurvey=0`;
		sleep(3);
		`iwpriv apcli0 get_site_survey > /tmp/apscan`;
		// if (10 > $scanTimes) {
		// 	`iwpriv apcli0 get_site_survey >> /tmp/apscan`;
		// 	$scanTimes++;
		// } else {
		// 	`iwpriv apcli0 get_site_survey > /tmp/apscan`;
		// 	$scanTimes = 0;
		// }
		
	} 

	/**
	 * get scan wifi list info
	 * @return wifi list array
	 */
	public static function getScanWifiListInfo()
	{
		$wifiList = array();

		self::scanWifiInfo();
		$file = fopen('/tmp/apscan', 'r');
		if (FALSE == $file) {
			return -1;
		}

		while (!feof($file)) {
			$line = trim(fgets($file));
			if (empty($line)) {
				continue;
			}

			//only keep one space
			$line = preg_replace("/\s(?=\s)/","\\1", $line);
        	//echo $line . "\n";
        	$strArry = explode(" ",$line); 
        	//print_r($strArry);
        	/* Array [0] => channel [1] => SSID [2] => BSSID [3] => Security 
        	   [4] => Siganl(%)W-Mode [5] => ExtCH [6] => NT [7] => WPS [8] => DPID */
        	$arryCount = sizeof($strArry);
        	if (9 != $arryCount) {
        		continue;
        	}

        	//删除信号强度这一元素，方便后面去重处理
        	array_splice($strArry,4,1);
        	//print_r($strArry);
        	$wifiList[] = $strArry;
		}

		$arr =  UTIL::arrayUniqueCommon($wifiList); 
    	array_splice($arr, 0, 1);
		return $arr;
	}

	/**
	 * get scan ap wifi detail info
	 * @param string $ssid : the name of ssid which user selected
	 * @return int 0 OK -1 not found -2 channel is 11 etc.
	 */
	public static function getScanApWifiInfo($ssid, &$channel, &$authMode, &$encType)
	{
    	/* Array [0] => channel [1] => SSID [2] => BSSID [3] => Security 
    	   [4] => Siganl(%)W-Mode [5] => ExtCH [6] => NT [7] => WPS [8] => DPID */
		$wifiList = self::getScanWifiListInfo();
		if (empty($wifiList)) {
			return -1;
		}
        
        $cnt = sizeof($wifiList);	
		for ($i=0; $i < $cnt; $i++) { 
			if ($ssid != $wifiList[$i][1]) {
				continue;
			}
    		$channel = $wifiList[$i][0];
    		if (8 <= $channel) {
    			return -2;
    		}
    		$security = $wifiList[$i][3];
        	$security = explode("/",$security);
        	//WPA2PSK或NONE可直接赋值，其他需要转换
    		$authMode = $security[0];
    		//change authmode to wifi driver recognize string
    		if ("WEP" == $security[0] || "WEPAUTO" == $security[0] || "WEPSHARED" == $security[0]) {
        		$authMode = "WEPAUTO";
    		} else if ("SHARED" == $security[0]) {
        		$authMode = "SHARED";
    		} else if ("WPA1PSKWPA2PSK" == $security[0]
        		|| "WPA2PSK" == $security[0] || "WPAPSKWPA2PSK" == $security[0]) {
        		$authMode = "WPA2PSK";
        	} else if ("WPA1PSK" == $security[0] || "WPAPSK" == $security[0]) {
        		$authMode = "WPAPSK";
        	} else if ("WPA2" == $security[0]) {
        		$authMode = "WPA2";
        	} else if ("WPA1" == $security[0] || "WPA" == $security[0]) {
        		$authMode = "WPA";
        	} else {
        		$authMode = "NONE";
        	}

    		$encType  = $security[1];
    		if ("WEPAUTO" == $authMode || "SHARED" == $authMode) {
    			$encType  = "WEP";
    		} else if ("TKIP" == $encType) {
    			$encType  = "TKIP";
    		} else if ("AES" == $encType || "TKIPAES" == $encType) {
    			$encType  = "AES";
    		} else {
    			$encType  = "NONE";
    		}
    		
    		break;
		}

		if ($i >= $cnt) {
			return -1;
		}

		return 0;
	}

	/**
	 * set apcli0 info to connect to internet
	 * @param  string $ssid 	:the name of SSID by the user filled
	 * @param  string $password :the password of setting apcli0's by user filled
	 * @return 0 OK -1 param wrong -2 can not set channel to 11 -3 unknown error
	 */
	public static function setApcli($ssid, $password, $defId=1){
			$ssid	= trim($ssid);
			$password	= trim($password);
			if (empty($ssid)) {
				return -1;
			}

			$ret = self::getScanApWifiInfo($ssid, $channel, $authMode, $encType);
			if (0 != $ret) {
				return $ret;
			}

			//iwpriv set apcli
			`iwpriv apcli0 set ApCliEnable=0`;
			`iwpriv apcli0 set ApCliAuthMode="$authMode"`;
			`iwpriv apcli0 set ApCliEncrypType="$encType"`;
			`iwpriv apcli0 set ApCliSsid="$ssid"`;
			if ("WEPAUTO" != $authMode) {
				`iwpriv apcli0 set ApCliWPAPSK="$password"`;
			} else {
				`iwpriv apcli0 set ApCliDefaultKeyID="$defId"`;
				switch ($defId) {
					case 1:
						`iwpriv apcli0 set ApCliKey1="$password"`;
						break;
					case 2:
						`iwpriv apcli0 set ApCliKey2="$password"`;
						break;
					case 3:
						`iwpriv apcli0 set ApCliKey3="$password"`;
						break;
					case 4:
						`iwpriv apcli0 set ApCliKey4="$password"`;
						break;
					
					default:
						`iwpriv apcli0 set ApCliKey1="$password"`;
						break;
				}
			}
			
			`iwpriv apcli0 set ApCliEnable=1`;

			//save apcli config
			$boardName = trim(`cat /tmp/sysinfo/board_name`);
			if ('MT7628' == $boardName) {
				$wifiDev = 'mt7628';
			} else if ('MT7620' == $boardName) {
				$wifiDev = 'mt7620';
			} else if ('R615N' == $boardName) {
				$wifiDev = 'wifi0';
			} else if ('r602n' == $boardName) {
				$wifiDev = 'radio0';
			} else {
				$wifiDev = 'mt7620';
			}
			
			$result = `uci set wireless.$wifiDev.channel="$channel"`;
			$result .= `uci set wireless.@wifi-iface[0].ApCliEnable=1`;
			$result .= `uci set wireless.@wifi-iface[0].ApCliSsid="$ssid"`;
			$result .= `uci set wireless.@wifi-iface[0].ApCliAuthMode="$authMode"`;
			$result .= `uci set wireless.@wifi-iface[0].ApCliEncrypType="$encType"`;
			if ("WEPAUTO" != $authMode) {
				$result .= `uci set wireless.@wifi-iface[0].ApCliWPAPSK="$password"`;
			} else {
				$result .= `uci set wireless.@wifi-iface[0].ApCliDefaultKeyID="$defId"`;
				switch ($defId) {
					case 1:
						$result .= `uci set wireless.@wifi-iface[0].ApCliKey1="$password"`;
						break;
					case 2:
						$result .= `uci set wireless.@wifi-iface[0].ApCliKey2="$password"`;
						break;
					case 3:
						$result .= `uci set wireless.@wifi-iface[0].ApCliKey3="$password"`;
						break;
					case 4:
						$result .= `uci set wireless.@wifi-iface[0].ApCliKey4="$password"`;
						break;
					
					default:
						$result .= `uci set wireless.@wifi-iface[0].ApCliKey1="$password"`;
						break;
				}
			}
			$result .= `uci commit wireless`;
			if (empty($result)) {
				return 0;
			}
			else
			{
				return -3;
			}
	}


	/**
	 * disable apcli0
	 * @return 0 OK -3 unknown error
	 */
	public static function disableApcli(){
			//iwpriv set apcli
			`iwpriv apcli0 set ApCliEnable=0`;

			//save apcli config
		        $result = `uci set wireless.@wifi-iface[0].ApCliEnable=0`;
			$result .= `uci commit wireless`;
			if (empty($result)) {
				return 0;
			}
			else
			{
				return -3;
			}
	}

	/**
	 * get password and encryption method of the SSID
	 * @return SSID|password and encryption method
	 */
	public static function getApcliCfgInfo(){

		$apcliInfo = array();
		$apcliInfo['apCliEnable']		=	`uci get wireless.@wifi-iface[0].ApCliEnable`;
		$apcliInfo['apCliSsid']			=	`uci get wireless.@wifi-iface[0].ApCliSsid`;
		$apcliInfo['apCliAuthMode']	    =	`uci get wireless.@wifi-iface[0].ApCliAuthMode`;
		$apcliInfo['apCliEncrypType']	=	`uci get wireless.@wifi-iface[0].ApCliEncrypType`;
		$apcliInfo['apCliWPAPSK']		=	`uci get wireless.@wifi-iface[0].ApCliWPAPSK`;
		return $apcliInfo;
		
	}


	/**
	 * set show wifi info
	 * @param  string $ssid 	:the name of SSID by the user filled
	 * @param  string $encryption :the show wifi's encryption mode
	 * @param  string $key :the password of setting the show wifi's by user filled
	 * @return 0 OK -1 param wrong -2 password cannot empty or length less than 8 -3 unknown error
	 */
	public static function setWifi($ssid,$encryption,$key,$hidden)
	{
		$ssid	     = trim($ssid);
		$encryption	 = trim($encryption);
		$key	     = trim($key);
		if (empty($ssid) || empty($encryption)) {
			return -1;
		}

		if (('none' != $encryption) && (empty($key) || strlen($key) < 8)) {
			return -2;
		}

		//save wifi config
		$result  = `uci set wireless.@wifi-iface[1].ssid='$ssid'`;
		$result .= `uci set wireless.@wifi-iface[1].encryption='$encryption'`;
		$result .= `uci set wireless.@wifi-iface[1].key='$key'`;
		$result .= `uci set wireless.@wifi-iface[1].hidden='$hidden'`;
		$result .= `uci commit wireless`;
		if (empty($result)) {
			return 0;
		}
		else
		{
			return -3;
		}
	}
	
}
?>
