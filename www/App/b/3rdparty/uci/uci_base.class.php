<?php 
/**
 * base uci class
 */


class uci_base{
	/**验证mac地址
	  *
	  * @return boolean
	  * @author  ldh
	  */
	 static function validMAC($mac) {
	 	return preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/',$mac);
		
	 }
	/**
	 * 提取mac地址
	 *
	 * @return array
	 * @author ldh 
	 */
	static function filterMac($in){
		if(preg_match_all('/([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}/',$in,$macs))
		{//正则表达式提取mac地址
			return array_unique($macs[0]);
		}
	}
	/**
	 * 提取ip地址
	 *
	 * @return array
	 * @author ldh 
	 */
	static function filterIP($in){
		if(preg_match_all('/((25[0-5]|2[0-4]\d|[0-1]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[0-1]?\d\d?)/',$in,$ips))
		{//正则表达式提取mac地址
			return array_unique($ips[0]);
		}
	}

	//根据IP地址获取设备名称和MAC
	static function getInfoByIP($ip,&$mac,&$name)
	{	
		if( DSTP_DEBUG )
		{
			$name = 'wifi';
			$mac  = '00:11:22:33:44:55';
			return;
		}
		$ipAddress=$_SERVER['REMOTE_ADDR'];
		//run the external command, break output into lines
		$arp=`arp -a | grep "$ip"`;
     	$mac=self::filterMac($arp);
		if($mac!=false)
		{
	  		$mac =  strtoupper(trim($mac[0]));
		}
		return false;
	}

	//根据MAC地址获取设备名称和IP
	static function getInfoByMAc($mac,&$ip,&$name)
	{
		if( DSTP_DEBUG )
		{
			$name = 'wifi';
			$ip	  = '127.0.0.1';
			return;
		}
		$mac=strtolower(trim($mac));
		$arp   = `arp -a | grep "$mac"`;
		$name  = mb_substr($arp,0,10);
		$ipList= self::filterIP($arp);
		$ip   = $ipList[0];
		$ipvalue = ip2long($ip);
		if( $ipvalue > ip2long('192.168.93.255') || $ipvalue < ip2long('192.168.93.1') )
		{
			foreach( $ipList as $i )
			{
				$iv = ip2long($i);
				if( $iv < ip2long('192.168.93.255') && $iv > ip2long('192.168.93.1') )
				{
					$ip = $i;
					break;
				}
			}
		}
		$mac  = strtoupper($mac);	
		return;
	}

	
	static function restart()
	{
		`reboot`;
	}
    
   	/**@return string interface internet IP
         * there can be multi wans/lans
   	 */
   	public static function getInterfaceIP($if){
        //return `ifconfig eth0.2 | grep "inet addr" | cut -f 2 -s -d":" | cut -f 1 -s -d" "`;
        $json=`ifstatus $if`;
        $jsonarr=json_decode($json,true);
        $wanIp = $jsonarr['ipv4-address'][0]['address'] ;
        if (empty($wanIp)) {
        	return NULL;
        }
        return $wanIp;
   	}
}
?>