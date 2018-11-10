<?php 
class dhcp extends network{
//	private $dhcp;
	//private $mac;
	private $client;
	private $clients;
	/**
	 * get all dhcp client
	 * @return string[]
	 */
	
	
	public function __construct(){
		
		}
	
	
	/**get dhcp
	 * 
	 * @return string[] dhcp info
	 */
	public static function getDHCP(){
		$dhcpleases=file('/tmp/dhcp.leases');
		return $dhcpleases;
	}


	/**
	 * 开启关闭分机lan侧dhcp功能
	 * @param string $mode can be on or off
	 * @return string
	 */
   	public static function setBridgeDhcpMode($mode){
   		if ($mode == 'on') {
			$result = `uci set dhcp.lan.start='100'`;
			$result .=`uci set dhcp.lan.limit='100'`;
			$result .=`uci set dhcp.lan.leasetime='12h'`;
			$result .=`uci set dhcp.lan.ignore='0'`;
			$result .=`uci commit dhcp`;
			return $result;
   		} else {
			$result =`uci set dhcp.lan.ignore='1'`;
			$result .=`uci delete dhcp.lan.start`;
			$result .=`uci delete dhcp.lan.limit`;
			$result .=`uci delete dhcp.lan.leasetime`;
			$result .=`uci commit dhcp`;
			return $result;
   		}
   	}
	
	
}

?>