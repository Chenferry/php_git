<?php 
require_once dirname ( __FILE__ ) . '/' . 'wan.class.php';
 class PPPoE extends wan{
	public $username;
	public $password;
	
	/**
	 * 
	 * @param string $username
	 * @param string $password
	 
	public function __construct($username=null,$password=null){
		if($username!=false && $password!=false){
			$this->username=$username;
			$this->password=$password;
		}
				
	}
*/	
	/**
	 * SET PPPoE
	 * @param  PPPoEAccount $account: pppoe account from ISP
	 */
	public static function setAccount($username,$password){
		if($username!=false && $password!=false){
			$result = `uci delete network.wan.ipaddr`;
			$result .= `uci delete network.wan.netmask`;
			$result .= `uci delete network.wan.gateway`;
			$result .= `uci delete network.wan.dns`;

			$result .= `uci set network.wan.proto='pppoe'`;
			$result .= `uci set network.wan.username='$username'`;
			$result .= `uci set network.wan.password='$password'`;
			$result .= `uci commit network`;
			return $result;
		}else{
			return 'account was not set';
		}
	}
	
	/**
	 * get PPPoE
	 * @return string|NULL
	 */
	public static function getAccount(){
		$ppp['username']=`uci get network.wan.username`;
		$ppp['password']=`uci get network.wan.password`;
		if($ppp['username']!=false && $ppp['password']!=false)
			return $ppp;
		else return null;
		
	}

	/**
	 * 设置3g/4g/xg上网方式
	 * @param  PPPoEAccount $account: pppoe account from ISP
	 */
	public static function setxgAccount($apn, $service = 'umts', $dialnumber = '*99#',
		$username = '', $password = '', $pincode = ''){
		if($apn!=false){
		$result = `uci set network.ppp.ifname='ppp0'`;
		$result .=`uci set network.ppp.proto='3g'`;
		$result .=`uci set network.ppp.pppd_options='noipdefault'`;
		$result .=`uci set network.ppp.device='/dev/ttyUSB3'`;
		$result .=`uci set network.ppp.apn='$apn'`;
		$result .=`uci set network.ppp.service='$service'`;
		$result .=`uci set network.ppp.dialnumber='$dialnumber'`;

		if (!empty($username)) {
			$result.=`uci set network.ppp.username='$username'`;
			$result.=`uci set network.ppp.password='$password'`;
		}
		// $result .=`uci set network.wan.pincode='$pincode'`;

		$result.=`uci set network.ppp.auto='1'`;
		$result.=`uci commit network`;
		return $result;
		}else{
			return 'account was not set';
		}
	}
	
	/**
	 * 获取3g/4g/xg上网方式信息
	 * @return string|NULL
	 */
	public static function getxgAccount(){
		$ppp['username']=trim(`uci get network.ppp.username`);
		$ppp['password']=trim(`uci get network.ppp.password`);
		$ppp['service']=trim(`uci get network.ppp.service`);
		$ppp['dialnumber']=trim(`uci get network.ppp.dialnumber`);
		$ppp['apn']=trim(`uci get network.ppp.apn`);
		if($ppp['apn']!=false)
			return $ppp;
		else return null;
		
	}

}
?>
