<?php

class settingInterFace
{
	static function checkExecAccess($userid,$objid,$type='attr')
	{
		if( 'i' == HIC_LOCAL )
		{
			return true;
		}
		$access = self::getUserAccess($userid);
		if( USER_TYPE_SYSTEM >= $access['type'] )
		{
			return true;
		}

		switch( $type )
		{
			case 'group':
				return $access['info']['group'];
				break;
			case 'devgroup':
				return $access['info']['devgroup'];
				break;
			case 'attr':
				//根据房间信息判断处理
				$c = new TableSql('homeattr','ID');
				$c->join('homedev','homeattr.DEVID=homedev.ID');
				$attrinfo = $c->query('ROOMID,DEVID','homeattr.ID=?',array($id) );
				if( -1 == $attrinfo['DEVID'] )
				{
					return $access['info']['devgroup'];
				}

				//判断设备是否在可执行房间内
				if(ROOM_SYSDEV_WHITE == $access['info']['type'])
				{
					return in_array( $attrinfo['ROOMID'], $access['info']['room'] );
				}
				else
				{
					return !in_array( $attrinfo['ROOMID'], $access['info']['room'] );
				}
				
				return false;
				break;
		}
		return false;		
	}
	//得到用户默认权限
	static function getUserAccess($userid=INVALID_ID)
	{
		if( 'i' == HIC_LOCAL )
		{
			return array('type'=>USER_TYPE_ADMIN);
		}

		if( !$userid )	$userid = INVALID_ID;
		
		$access = Cache::get("homeaccess_$userid");
		if( false != $access )
		{
			return $access;
		}
		
		$c = new TableSql('homeaccess');
		$value = $c->query('USERTYPE,ROMETYPE,ROMEID','USERID=?',array($userid));
		if( NULL == $value )
		{
			return array('type'=>USER_TYPE_ADMIN);
		}

		$userinfo = array();
		$userinfo['type'] = $value['USERTYPE'];
		if(USER_TYPE_COMMON == $value['USERTYPE'])
		{
			$userinfo['info']['type']  = $value['ROMETYPE'];
			$userinfo['info']['room']  = explode(',',$value['ROMEID']);

			$allow = false;
			if(ROOM_SYSDEV_WHITE == $value['ROMETYPE']) 
			{
				$allow = true;
			}
			$userinfo['info']['group']    = !$allow;
			$userinfo['info']['devgroup'] = !$allow;
			$userinfo['info']['sys']      = !$allow;
			if( in_array(ROOM_SYSDEV_SMARTGROUP, $userinfo['info']['room']) )
			{
				$userinfo['info']['group'] = $allow;
			}
			if( in_array(ROOM_SYSDEV_DEVGROUP, $userinfo['info']['room']) )
			{
				$userinfo['info']['devgroup'] = $allow;
			}
			if( in_array(ROOM_SYSDEV_SYS, $userinfo['info']['room']) )
			{
				$userinfo['info']['sys'] = $allow;
			}
		}
		
		Cache::set("homeaccess_$userid",$userinfo,86400);
		
		return $userinfo;
	}

	//设置用户权限信息
	static function updateAccess($userid=INVALID_ID,$access=array())
	{
		if( !$userid )	$userid = INVALID_ID;
		if( !$access )	$access = self::getUserAccess();	//默认权限
		
		//设置完后，不允许出现没有用户为管理员情况
		$c = new TableSql('homeaccess');
		if( USER_TYPE_ADMIN != $access['type'] )
		{
			$otherid = $c->queryValue('USERID','USERID!=? AND USERID!=? AND USERTYPE=?',
									array($userid,-1,USER_TYPE_ADMIN));
			if( NULL == $otherid )
			{
				include_once('a/commonLang.php');
				return USER_ACCESS_ERR;
			}
		}
		
		//删除后重加
		$c->del('USERID=?',array($userid));
		
		$u['USERID']    = $userid;
		$u['USERTYPE']  = $access['type'];
		if( isset($access['info']) && $access['type'] == USER_TYPE_COMMON)
		{
			$u['ROMETYPE']  = $access['info']['type'];
			$u['ROMEID']    = implode(',',$access['info']['room']);
		}
		$c->add($u);
		Cache::del("homeaccess_$userid");
		
		$hicid =HICInfo::getHICID();
		$GLOBALS['dstpSoap'] -> setModule('app','hic');
		$GLOBALS['dstpSoap']->updateAccess($access['type'],$userid,$hicid);
	
		return true;
	}

	//如果是在开放查找设备时，这个地方需要延时后就关闭查找
	static function openDevSearchMap($cmd,$subhost=INVALID_ID,$phydev=NULL)
	{
		$r = Cache::get("openDevSearch");
		if( false != $r )
		{
			return;
		}
		return self::openDevSearch(cmd,$phydev,$subhost);
	}
	//允许主机打开或者关闭入网请求
	//
	static function openDevSearch($cmd,$phydev=NULL,$subhost=INVALID_ID)
	{
		//DEV_CMD_SYS_CLOSE_PERMITJOIN
		//DEV_CMD_SYS_OPEN_PERMITJOIN
		//$cmd = DEV_CMD_SYS_CLOSE_PERMITJOIN;
		
		//如果是默认打开，且当前是要发送关闭命令，则无需处理
		$c = new TableSql('sysinfo');
		$r = $c->queryValue('DISNETJOIN');
		if( (0 == intval($r) ) && ( DEV_CMD_SYS_CLOSE_PERMITJOIN == $cmd ) )
		{
			return;
		}
		
		//已经指定了分机，则直接往该分机发送
		if( INVALID_ID!=$subhost )
		{
			$GLOBALS['dstpSoap']->setModule('home','if');
			return $GLOBALS['dstpSoap']->sendDevSySMsg($subhost,$cmd,NULL,0,$phydev);
		}
		
		if( !defined('HIC_SYS_NOZIGBEE') )//主机默认通道
		{
			$phydev = PHYDEV_TYPE_ZIGBEE;
			if(defined('HIC_CONSOLE_PHYDEV'))
			{
				$phydev = HIC_CONSOLE_PHYDEV;
			}
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg(0,$cmd,NULL,0,$phydev);		
		}
		//先不考虑phydev，默认往所有类型透传通道发		
		$c = new TableSql('homeattr','ID');
		$all  = $c->queryAll('DEVID,CFGINFO','SYSNAME=?',array('tran'));
		foreach($all as &$a)
		{
			$p = unserialize($a['CFGINFO']);
			if( false == $p )
			{
				$p = PHYDEV_TYPE_ZIGBEE;
			}
			else
			{
				$p = $p['phydev'];
			}
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg($a['DEVID'],$cmd,NULL,0,$p);
		}		
		return;
	}
	
	//获得房间顺序
	static function getRoomOrder()
	{
		$c = new TableSql('homeroom','ID');
		$r = $c->queryAllList('ID','NAME IS NOT NULL ORDER BY ROOMINDEX');
		//未分区确定第一个，系统和设备组最后两个
		array_unshift($r,ROOM_SYSDEV_UNADDR);
		$r[] = ROOM_SYSDEV_DEVGROUP;
		$r[] = ROOM_SYSDEV_SYS;
		return $r;
	}

	static function cleanFavoriteCache()
	{
		$c = new TableSql('hic_user');
		$userList = $c->queryAllList('ID');	
		foreach( $userList as $userid )
		{
			$cachename = 'favorite_'.$userid;
			Cache::del($cachename);
		}
		return;
	}
	//当属性删除时，如果有灯具遥控控制该设备，则删除灯具遥控对该设备的控制信息
	static function resetdjyk($id)
	{
		$c    = new TableSql('homeattr','ID');
		$djyk = $c->queryAll('ID,ATTRSET','SYSNAME=?',array('djyk'));
		foreach($djyk as $key => $value) 
		{
			$cfg = unserialize($value['ATTRSET']);
			$update = false;
			foreach($cfg['button'] as $k => $v)
			{
				if( $v['AID']==$id )
				{
					$update = true;
					$cfg['button'][$k] = array('AID'=>'-1','MID'=>NULL);
				}
			}
			if($update)
			{
				$value['ATTRSET'] = serialize($cfg);
				$c->update($value);
			}
		}
	}
	//设置或者修改wifi信息
	static function changeWifiCfg($ssid,$encryption,$key,$hidden=0)
	{
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

	//属性名改变时，相关名字要重取
	static function setFavoriteName($result)
	{
		if( NULL == $result )
		{
			return array();
		}
		$c    = new TableSql('homeattr','ID');
		$c1   = new TableSql('smartgroup','ID');
		foreach( $result as &$f )
		{
			if( MAX_INT_VALUE > $f['id'] )
			{
				$f['NAME']  = $c->queryValue('NAME','ID=?',array($f['id']));
				$f['orgID'] = $f['id'];
				$f['type']  = 0;
			}
			else
			{
				$f['NAME']  = $c1->queryValue('NAME','ID=?',array($f['id'] - MAX_INT_VALUE));
				$f['orgID'] = $f['id'] - MAX_INT_VALUE;
				$f['type']  = 1;
			}
		}
		return $result;
	}
	
	//当属性或者情景模式删除时，要把主页中favorite列表中的删除并且保存数据库
	static function resetFavorite($id)
	{
		$c 	= new TableSql('homefavorite');
		$favorite = $c->queryAll('*');
		foreach ($favorite as $key=>$value) {
			$f = unserialize($value['FAVORITE']);
			foreach ($f as $k=>$v) {
				if( $v['id'] == $id )
				{
					unset($f[$k]);
					$c->del('USERID=?',array($value['USERID']));
					$cachename = 'favorite_'.$value['USERID'];
					Cache::del($cachename);			
					statusNotice('status');
					$info = array();
					$info['USERID'] 	= $value['USERID'];
					$info['FAVORITE'] 	= serialize($f);
					$c->add($info);	

					$favoriteList = self::setFavoriteName($f);
					Cache::set($cachename,$favoriteList);			
					break;
				}
			}
		}			
	}

	static function getDevWifiInfo()
	{
		$ssid = SSID::getSSID();
		if ( NULL == (trim($ssid['encryption'])) 
			|| ('none' == trim($ssid['encryption']))) 
		{
			$ssid['encryption'] = 0;
		}
		else 
		{
			$ssid['encryption'] = 1;
		}

		return $ssid;
	}

	static function getUserFavorite($userid)
	{
		//情景模式
		$cachename = 'favorite_'.$userid;
		$cache 	   = Cache::get($cachename);
		if( false !== $cache )
		{
			//$cache = self::setFavoriteName($cache);
			return $cache;
		}
		
		$result  = array();
		$c 		 = new TableSql('homefavorite');
		$result  = $c->queryValue('FAVORITE','USERID=?',array($GLOBALS['curUserID']));
		@$result = unserialize($result);
		if( false === $result )
		{
			//不提供默认值
			//$c     = new TableSql('smartgroup','ID');
			//$group = $c->queryAll('ID,NAME','ISSHOW=1');
			//$favorite = array();
			//foreach( $group as &$g )
			//{
			//	$favorite[] = array('type'=>1,'id'=>$g['ID'], 'NAME'=>$g['NAME'] );
			//}
            //
			//$c     = new TableSql('homeattr','ID');
			//$attrList = $c->queryAll('ID,ICON');
			//foreach( $attrList as $attrinfo )
			//{
			//	$favorite[] = array('type'=>0,'id'=>$attrinfo['ID'],'ICON'=>$attrinfo['ICON']);
			//}
            //
			//shuffle($favorite);
			//$result = array_chunk($favorite,7)[0];
            //
			//$info = array();
			//$info['USERID'] 	= $userid;
			//$info['FAVORITE']	= serialize($result);
			//$c = new TableSql('homefavorite');
			//$c->add($info);			
		}
		$result = self::setFavoriteName($result);
		
		Cache::set($cachename,$result);
		return $result;
	}

	static function checkUpdateRoomInfo()
	{
		//如果有变更，proxystub中会把缓存清掉，这儿就会重新生成
		self::getRoomAttrMap();
	}
	//每日重新清除下房间缓存
	static function maintenceRoomInfo()
	{
		Cache::del('roomList');
		Cache::del('roomAttrMap');
		self::getRoomAttrMap();
	}

	//获取当前所有房间信息
	static function getRoomList()
	{
		$roomList = Cache::get('roomList');
		if( false !== $roomList )
		{
			return $roomList;
		}
		
		$c = new TableSql('homeroom','ID');
		$room = $c->queryAll();
		$roomList = array();
		$roomList[INVALID_ID] = '';
		foreach( $room as &$r )
		{
			$roomList[$r['ID']] =  $r['NAME'];
		}
		Cache::set('roomList',$roomList);
		return $roomList;
	}

	static function getRoomListShow()
	{
		include_once('b/homeLang.php');
		$roomList = self::getRoomList();
		$roomList[ROOM_SYSDEV_UNADDR] 	  = HOME_SYSDEV_UNADDR;
		$roomList[ROOM_SYSDEV_DEVGROUP]   = HOME_SYSDEV_DEVGROUP;		
		$roomList[ROOM_SYSDEV_SYS]		  = HOME_SYSDEV_SYS;
		$roomList[ROOM_SYSDEV_SMARTGROUP] = HOME_SYSDEV_SMARTGROUP;
		return $roomList;
	}
	
	//获取当前房间和可见属性的映射关系。如果是未定义的，则roomid为-1
	//$onlyCtrl:是否只获取能被控制的设备还是所有设备
	static function getRoomAttrMap($onlyCtrl = false)
	{
		$roomAttrMap = Cache::get('roomAttrMap');
		//只获取可控制设备的没缓存，直接再获取
		if( false !== $roomAttrMap && !$onlyCtrl)
		{
			return $roomAttrMap;
		}

		$roomList = self::getRoomListShow();

		$roomAttrMap = array();
		$c  = new TableSql('homeattr','ID');
		$c->join('homedev','homedev.ID=homeattr.DEVID');
		if( !$onlyCtrl )
		{
			$idList = $c->queryAll('homeattr.ID as ID,ROOMID,PHYDEV',
				'(STATUS=? OR STATUS=?) AND (homeattr.INUSE=1) ORDER BY ROOMID,homedev.ID',
				array(DEV_STATUS_RUN,DEV_STATUS_OFFLINE)
				);
		}
		else
		{
			$idList = $c->queryAll('homeattr.ID as ID,ROOMID,PHYDEV',
				'STATUS=? AND homeattr.INUSE=1 AND ( (SYSNAME!=? AND SYSNAME!=? AND ISC=?) OR ( SYSNAME=? ) ) ORDER BY ROOMID,homedev.ID',
				array(DEV_STATUS_RUN,'gj','rtsp',1,'ms')
				);
		}
		$roomAttrMap[ROOM_SYSDEV_UNADDR] = array();
		$roomAttrMap[ROOM_SYSDEV_DEVGROUP] = array();
		$roomAttrMap[ROOM_SYSDEV_SYS] = array();
		
		foreach( $idList as &$idinfo )
		{

			$roomid = $idinfo['ROOMID'];
			//if( PHYDEV_TYPE_SYS == $idinfo['PHYDEV'] )
			//{
			//	$roomid = ROOM_SYSDEV_SYS;
			//}
			if( NULL == $roomid )
			{
    			$roomid = ROOM_SYSDEV_UNADDR;
    		}

			if( !isset($roomList[$roomid ]) )
			{
				$roomid = ROOM_SYSDEV_UNADDR;
			}

			$roomAttrMap[$roomid][] = $idinfo['ID'];
		}
		

		//设备组需要单独挑出
		$c  = new TableSql('homeattr','ID');
		$devgroupList = $c->queryAllList('ID','DEVID=?',array(ROOM_SYSDEV_DEVGROUP));

		foreach( $devgroupList as $dg )
		{
			$roomAttrMap[ROOM_SYSDEV_DEVGROUP][] = $dg;
		}
		
		if( NULL == $roomAttrMap[ROOM_SYSDEV_UNADDR] )
		{
			unset( $roomAttrMap[ROOM_SYSDEV_UNADDR] );
		}
		if( NULL == $roomAttrMap[ROOM_SYSDEV_DEVGROUP] )
		{
			unset( $roomAttrMap[ROOM_SYSDEV_DEVGROUP] );
		}
		if( NULL == $roomAttrMap[ROOM_SYSDEV_SYS] )
		{
			unset( $roomAttrMap[ROOM_SYSDEV_SYS] );
		}
		
		
		if( !DSTP_DEBUG && !$onlyCtrl ) //更新触发在proxy，debug模式下无法更新，所以不设置缓存
		{
			Cache::set('roomAttrMap',$roomAttrMap);
		}

		return $roomAttrMap;
	}
	
}
?>