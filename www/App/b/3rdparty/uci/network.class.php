<?php
class network extends uci_base {
	/**
	 * 重启动network
	 * @return string
	 */
	public static function restart() {
		return `/etc/init.d/network restart && /etc/init.d/proxy restart`;
	}
	
	public static function getLanip()
	{
		return trim(`uci get network.lan.ipaddr`);
	}
    	
	/**
	 * 重启动wan口
	 * @return string
	 */	
	public static function wanIfup() {
		return `ifup wan &&  /etc/init.d/proxy restart`;
	}
	/**
	 * get eth0 macaddr
	 * @param number $port        	
	 * @return string macaddr
	 */
	public static function getMac($port = 0) {
		if ( DSTP_DEBUG )
		{
			return '00:11:22:33:'.mt_rand(10,99).':'.mt_rand(10,99);
		}
		if ($port == 4) {
			$ifconfig = `ifconfig eth0.2 | grep HWaddr`;
    		}
    		else{
			$ifconfig = `ifconfig br-lan | grep HWaddr`;
		}
		$macs = parent::filterMac ( $ifconfig );
		return trim(strtoupper( $macs[0] ));
	}
	
	/**
	 * set eth0 macaddr
	 * @param string $mac        	
	 * @param number $port        	
	 * @return string
	 */
    	
	public static function setMac($mac, $port = 4) {
		if ($port == 4) {
			return "set mac " . $mac . "in port " . $port . `uci set network.wan.macaddr=$mac` . `uci commit network`;
		} else {
			return "set mac " . $mac . "in port " . $port . `uci set network.lan.macaddr=$mac` . `uci commit network`;
		}
    		
	}
	/**
	 * get wifi macaddr
	 * @return string wlan0 mac addr
	 */
	public static function getWiFiMac() {
		return self::mac_match ( `ifconfig wlan0 | grep HWaddr` );
	}
	
	/**
	 * set wifi macaddr
    	* @param string macaddr $mac
	 * @return string
	 */
	public static function setWiFiMac($mac) {
    	
		return "set mac in wlan0 " . $mac . `uci set wireless.@wifi-device[0].macaddr=$mac` . `uci commit wireless`;
	}

	/**
	 * 设置分机桥接模式获取ip地址方式
	 * @param string get ip addr manner $mode can be dhcp or static
	 * @return string
	 */
   	public static function setBridgeIpMode($mode){
   		if ($mode == 'dhcp') {
			$result = `uci set network.lan.ifname='eth0.1 eth0.2 apcli0'`;
			$result .=`uci set network.lan.proto='dhcp'`;
			$result .=`uci set network.lan._orig_ifname='eth0.1 ra0 ra1 ra2'`;
			$result .=`uci delete network.lan.ipaddr`;
			$result .=`uci delete network.lan.netmask`;
			$result .=`uci commit network`;
			return $result;
   		} else {
			$result = `uci set network.lan.ifname='eth0.1'`;
			$result .=`uci set network.lan.proto='static'`;
			$result .=`uci set network.lan._orig_ifname='eth0.1'`;
			$result .=`uci set network.lan.ipaddr='192.168.93.1'`;
			$result .=`uci set network.lan.netmask='255.255.255.0'`;
			$result .=`uci commit network`;
			return $result;
   		}
   	}
	
	/**
	 * 获取分机桥模式ip地址的获取方式
	 * @return string|NULL
	 */
	public static function getBridgeIpMode(){
		$mode = trim(`uci get network.lan.proto`);
		return $mode;
	}

}
?>