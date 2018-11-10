<?php

//网口事件暂时没处理。
class clientInterFace
{
	/************系统管理操作接口***********************/
	//上电时根据数据库信息初始化防火墙和ACL列表。该函数只在上电时调用
	//如果mac不为空，则该函数表示的是初始化设备时调用。这时需要特殊处理正在初始化设备的那个mac地址
	static function initClientsACL($mac=NULL)
	{
		if( APP_FJ == HIC_APP )
		{
			//分机不在这儿处理初始化
			//应该是在和主机连接上后，就找出当前所有的地址，向主机发送请求后再处理
			//如果和主机的通讯断了，应该是默认允许
			return;
		}
		if( NULL != $mac )
		{
			//先把该MAC设置为特殊的主人设备。
			//且名称要特别设置，让后续真正的主人一看就能清楚安装人员的手机还没处理，还可控制家庭
			include_once('b/homeLang.php');
			$id = self::clientConnect($mac, $_SERVER['REMOTE_ADDR'],HOME_INITUSER_NAME);
			self::allowClient($id,DEV_CLIENT_LONG);
		}
		else
		{
			$c	= new TableSql('homeclient');
			$GLOBALS['dstpSoap']->setModule('frame');
			if ( !$GLOBALS['dstpSoap']->isBindUser() )
			{
				return;
			}
		}
		
		//置初始访问权限
		$GLOBALS['dstpSoap']->setModule('local','firewall');
		$GLOBALS['dstpSoap']->rejectAll();
		
		//对已经连上线的设备进行处理
		//查找当前所有在线的MAC开始处理
		$dhcp = file_get_contents('/tmp/dhcp.leases');
		$dhcp = explode("\n",$dhcp);
		$online   = array();
		$iplist   = array();
		foreach($dhcp as &$d)
		{
			//1440691663 a0:a8:cd:f1:83:58 192.168.1.156 Lenovo-PC 01:a0:a8:cd:f1:83:58
			list($a,$mac,$ip,$name,$d) = explode(' ',$d);
			$mac = strtoupper(trim($mac));
			$online[$mac] = array('ip'=>$ip,'name'=>$name);
		}
		
		self::initOnlineList($online);
	
		return;
	}
	
	static function initOnlineList($online,$fjid=0)
	{
		$c	= new TableSql('homeclient');
		$clientList = $c->queryAll('MAC,SOURCES');
		foreach( $clientList as &$client )
		{
			$mac = $client['MAC'];
			if( isset( $online[$mac] ) )
			{
				self::clientConnect($mac,$online[$mac]['ip'],$online[$mac]['name'],$client['SOURCES']);
			}
			else
			{
				self::clientOffline($mac);
			}	
		}		
	}

	/**********与通知接口************************/



	//MAC新连线，MAC/IP/NAME。
	static function clientConnect($mac,$ip,$name,$source=DEV_CONNECTHIC_SSID,$fjid=0)
	{
		$mac = strtoupper(trim($mac));
		if ( DEV_CONNECTHIC_LAN == $source )
		{
			//直接允许，不做管理处理
			$GLOBALS['dstpSoap']->setModule('local','firewall');
			$GLOBALS['dstpSoap']->allowMac($mac,true);
			return true;
		}
		$GLOBALS['dstpSoap']->setModule('frame');
		$isBind = $GLOBALS['dstpSoap']->isBindUser();

		
		//需要检查是否已经在设备表中已经存在,其来源要更新为DEV_CONNECTHIC_DEVSSID
		//如果已经在设备表中存在，比如摄像头先从隐藏SSID再转普通SSID
		if( DEV_CONNECTHIC_SSID == $source )
		{
			$c = new TableSql('homedev','ID');
			$info = $c->query('ID','PHYADDR=?',array($mac));
			if( NULL != $info )
			{
				$source = DEV_CONNECTHIC_DEVSSID;
			}		
		}
		
		//首先判断是否已经在表中存在记录，如果不存在，则写通知信息
		$c	= new TableSql('homeclient','ID');
		$info = $c->query('ID,PERIOD,EXTID','MAC=?',array($mac));
		if (  NULL == $info )
		{
			//刚连接的设备加入黑名单，访问权限置为不可访问
			//但如果还没初始化，则应该允许访问
			if ( $isBind )
			{
				$GLOBALS['dstpSoap']->setModule('local','firewall');
				$GLOBALS['dstpSoap']->setMacPeriod($mac,DEV_CLIENT_INIT,true,$fjid);
			}

			$info = array();
			$info['IP']     = trim($ip);
			$info['MAC']    = trim($mac);
			$info['NAME']   = trim($name);
			$info['PERIOD'] = DEV_CLIENT_INIT;
			$info['SOURCES']= $source;
			$info['EXTID']  = $fjid;
			$info['CTIME']  = time();
			$attrid = $c->add($info);
			if( !validID($attrid) )
			{
				return true;
			}
			//隐藏ssid连入的都做为一个设备，应该根据协议自动发入网请求，这儿不处理
			if( DEV_CONNECTHIC_DEVSSID == $source )
			{
				return true;
			}
	
			//同时告警处理
			//include_once('b/homeLang.php');
			//$GLOBALS['dstpSoap']->setModule('frame','alarm');
			//$GLOBALS['dstpSoap']->alarm(INVALID_ID,$attrid,HOME_DEV_WIFICONNECT, array('m'=>'home','s'=>'client'));
			return $attrid;
		}
		//用户重新连接有可能改变了名称和连接方式
		$info['IP']  = trim($ip);
		$info['MAC'] = trim($mac);
		//$info['NAME']   = trim($name);
		$info['SOURCES']= $source;
		$c->update($info);
		
		if ( $isBind )
		{
			$GLOBALS['dstpSoap']->setModule('local','firewall');
			$GLOBALS['dstpSoap']->setMacPeriod($mac,$info['PERIOD'],true,$fjid);
		}
		
		switch ( $info['PERIOD'] )
		{
			case DEV_CLIENT_LONG:
				//报告状态
				$GLOBALS['dstpSoap']->setModule('home','sysend');
				$GLOBALS['dstpSoap']->reportClientStatus($info['ID'], 1);
				break;
			case DEV_CLIENT_DEV: 
				// $cDev  = new TableSql('homedev','ID');
				// //根据mac地址，更新其logic地址
				// $devinfo = array();
				// $devinfo['LOGICADDR'] = $ip;
				// $cDev->update($devinfo,NULL,'PHYADDR=?',array($mac));
				break;
			case DEV_CLIENT_PC:
			case DEV_CLIENT_TEMP:
			case DEV_CLIENT_INIT:
			case DEV_CLIENT_REJECT:
			case DEV_CLIENT_REQUEST:
			default:
				break;			
		}

		return $info['ID'];
	}

	//MAC掉线
	static function clientOffline($mac,$fjid=0)
	{
		$mac = strtoupper(trim($mac));
		$c	= new TableSql('homeclient','ID');
		$info = $c->query('ID,PERIOD,SOURCES','MAC=?',array($mac));
		if ( NULL == $info )
		{
			$GLOBALS['dstpSoap']->setModule('local','firewall');
			$GLOBALS['dstpSoap']->setMacPeriod($mac,DEV_CLIENT_INIT,false,$fjid);
			return false;
		}

		$GLOBALS['dstpSoap']->setModule('local','firewall');
		$GLOBALS['dstpSoap']->setMacPeriod($mac,$info['PERIOD'],false,$fjid);

		$info['IP'] = NULL;
		$c->update($info);

		switch ( $info['PERIOD'] )
		{
			case DEV_CLIENT_LONG:
				switch( $info['SOURCES'] )
				{
					case DEV_CONNECTHIC_SSID:
						$GLOBALS['dstpSoap']->setModule('home','sysend');
						$GLOBALS['dstpSoap']->reportClientStatus($info['ID'], 0);
						break;
					case DEV_CONNECTHIC_DEVSSID:
						//应该发送设备离线告警
						break;
					default:
						break;
				}

				break;
			case DEV_CLIENT_DEV: //设备的离线告警也不在这儿处理，而是由协议处理
				$cDev  = new TableSql('homedev','ID');
				//根据mac地址，更新其logic地址
				$devinfo = array();
				$devinfo['LOGICADDR'] = NULL;
				$cDev->update($devinfo,NULL,'PHYADDR=?',array($mac));
				break;
			case DEV_CLIENT_PC:
			case DEV_CLIENT_TEMP:
			case DEV_CLIENT_REJECT:
			case DEV_CLIENT_REQUEST:
			default:
				break;
		}
		return true;
	}



	/************用户操作接口***********************/
	//用户对通知表中的mac地址进行操作
	//period:用户允许的设备身份。
	static function allowClient($id,$period=DEV_CLIENT_INIT)
	{
		$c	= new TableSql('homeclient','ID');
		if( !is_numeric($id) )
		{
			//根据mac地址获取id
			$info = $c->query('*','MAC=?',array($id));
			//不知为什么，经常会出现允许了，但在client表中没数据
			if( NULL == $info && DEV_CLIENT_DEV == $period )
			{
				$info = array();
				$info['SOURCES'] = DEV_CONNECTHIC_DEVSSID;
				$info['PERIOD']  = DEV_CLIENT_DEV;
				$info['EXTID']   = 0;
				$info['MAC']     = $id;
				$info['NAME']    = 'dev';
				$info['CTIME']   = time();
				$info['ID']      = $c->add($info);
			}
			$id = $info['ID'];
		}
		else
		{
			$info = $c->query('*','ID=?',array($id));
		}
		
		if(validID($id))
		{
			//清除通知告警
			$GLOBALS['dstpSoap']->setModule('frame','alarm');
			$GLOBALS['dstpSoap']->alarm(INVALID_ID, $id, DEV_ALARM_CLEAN, array('m'=>'home','s'=>'client'));
		}

		if ( NULL == $info )
		{
			return false;
		}

		//先清除掉原先的访问权限设置，再重新设置新权限
		$orgPeriod = $info['PERIOD'];
		if( $orgPeriod != $period )
		{
			$GLOBALS['dstpSoap']->setModule('local','firewall');
			$GLOBALS['dstpSoap']->setMacPeriod($info['MAC'],$orgPeriod,false,$info['EXTID']);
		}
		if( NULL != $info['IP'] )
		{
			$GLOBALS['dstpSoap']->setModule('local','firewall');
			$GLOBALS['dstpSoap']->setMacPeriod($info['MAC'],$period,true,$info['EXTID']);
		}

		//如果用户允许为主人设备。则需要在sysend中添加属性
		if( (DEV_CLIENT_LONG == $period) && (DEV_CLIENT_LONG != $orgPeriod))
		{
			$GLOBALS['dstpSoap']->setModule('home','sysend');
			$GLOBALS['dstpSoap']->addClientDev($id,$info['NAME']);
		}
		if( (DEV_CLIENT_LONG == $period) || (DEV_CLIENT_LONG == $orgPeriod))
		{
			//更新手机在线状态
			$GLOBALS['dstpSoap']->setModule('home','sysend');
			$GLOBALS['dstpSoap']->reportClientStatus($id, (NULL==$info['IP'])?0:1 );
		}

		//如果拒绝了，则删除表记录
		if ( DEV_CLIENT_INIT == $period )
		{
			$c->delByID($id);
		}
		else
		{
			//更新数据表记录
			$info = array();
			$info['ID']     = $id;
			$info['PERIOD'] = $period;
			$c->update($info);
		}
		return true;
	}
	
	static function changeName($id,$name)
	{
		$c	= new TableSql('homeclient','ID');
		$info = array();
		$info['ID']   = $id;
		$info['NAME'] = $name;
		$c->update($info);
		return true;
	}

	/***********其他模块要求的接口**********************************/
	//告警通知时返回给用户的设备名称
	static function getAlarmAttrName($devid,$attrid)
	{
		$c	= new TableSql('homeclient','ID');
		return $c->queryValue('NAME','ID=?',array($attrid));
	}

}
?>