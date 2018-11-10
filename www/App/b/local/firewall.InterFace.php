<?php
include_once('uci/uci.class.php');

class firewallInterFace
{
	/**
	 * 端口映射
	 * @param integer $outport 外网端口
	 * @param integer $inport 路由器端口
	 * @param string $proto	TCP/UDP
	 * @param string $outip	外网IP
	 * @param string $inip 路由器客户端IP，如192.168.1.149
	 * @return boolean
	 */
	static function map($outport,$inport,$proto=NULL,$outip=null,$inip=null)
	{
		if( NULL ==  $proto )
		{
			return (firewall::map('tcp', $outport,$inport, $outip, $inip)
					&& firewall::map('udp', $outport,$inport, $outip, $inip));
		}
		return firewall::map($proto, $outport,$inport, $outip, $inip);
	}	
	
	/**
	 *
	 * @param integer $outport 外网端口
	 * @param integer $inport 路由器端口
	 * @param string $proto TCP/UDP
	 * @param string $outip 外网IP
	 * @param string $inip 路由器客户端IP，如192.168.1.149
	 * @return boolean
	 */
	static function unmap($outport,$inport,$proto=NULL,$outip=null,$inip=null)
	{
		if( NULL ==  $proto )
		{
			return (firewall::unmap('tcp', $outport,$inport, $outip, $inip)
					&& firewall::unmap('udp', $outport,$inport, $outip, $inip));
		}
		return firewall::unmap($proto, $outport,$inport, $outip, $inip);
	}
	/**
	 * 允许的用户取消隔离
	 * @param string $mac MAC地址
	 */
	private static function addEntry($mac)
	{
		`iwpriv ra1 set ACLAddEntry=$mac`;
	}
	/**
	 * 未允许的用户隔离客户端
	 * @param string $mac MAC地址
	 */
	private static function delEntry($mac)
	{
		`iwpriv ra1 set ACLDelEntry=$mac`;
	}
	
	/**
	 * 设置mac地址的访问权限。防火墙使用黑名单机制。无线转发使用白名单机制
	 * @param string $add 指定mac地址上线或者下线处理
	 *
	 */
	static function setMacPeriod($mac,$period,$add=true,$fjid=0)
	{
		
		if( 0 != $fjid  )
		{
			$cmd = array();
			$cmd['action'] = 'wifimac';
			$cmd['mac']    = $mac;
			$cmd['period'] = $period;
			$cmd['add']    = $add;
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($fjid,$cmd);
			return;
		}
		
		$wifiInfo = SSID::getSSID();
		$enc = trim($wifiInfo['encryption']);
		switch( $period )
		{
			case DEV_CLIENT_LONG:
			case DEV_CLIENT_PC:
			case DEV_CLIENT_DEV:
				//默认就是全部可访问，所以无需处理
				self::addEntry($mac);
				firewall::allow_mac($mac);
				break;
			case DEV_CLIENT_TEMP:
				//防火墙可访问不处理，但内网不可访问
				self::delEntry($mac);
				//系统现在使用的是黑名单机制。当上线时，实际上设置其限制，下线时删除所有现在
				if($add)
				{
					firewall::deny_mac($mac,true);//添加对内网的访问禁止
				}
				else
				{
					firewall::allow_mac($mac,true);//去除对内网访问的禁止
				}
				break;
			case DEV_CLIENT_INIT:
				//获取无线网络加密信息，如果不加密则禁止访问同时添加转发黑名单，如果加密则白名单
				//同时重定向到web页面
				if (empty($enc) || ('none' == $enc)) {
					//禁止访问
					self::delEntry($mac);
					if($add) //添加转发黑名单
					{
						firewall::deny_mac($mac);
					}
					else
					{
						firewall::allow_mac($mac);
					}
				} else {
					if ($add) {
						firewall::addRedirectToWebPort($mac);
					} else {
						firewall::cancelRedirectToWebPort($mac);
					}
				}
				break;			
			case DEV_CLIENT_REQUEST:
				if (empty($enc) || ('none' == $enc)) {
					//禁止访问
					self::delEntry($mac);
					if($add) //添加转发黑名单
					{
						firewall::deny_mac($mac);
					}
					else
					{
						firewall::allow_mac($mac);
					}					
				} else {
					firewall::cancelRedirectToWebPort($mac);
				}
				break;			
			case DEV_CLIENT_REJECT:
			default:
				//禁止访问
				self::delEntry($mac);
				if($add) //添加转发黑名单
				{
					firewall::deny_mac($mac);
				}
				else
				{
					firewall::allow_mac($mac);
				}
				break;			
		}
		return true;
	}

	
	/**
	 * 在路由器初始化或上电时，防火墙初始化为拒绝所有的客户端连接WIFI
	 */
	static function rejectAll()
	{
		return firewall::lanReject();
	}	
	
	
}
?>