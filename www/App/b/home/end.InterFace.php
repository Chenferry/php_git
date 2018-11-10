<?php
//该文件实现系统设备的管理接口
class endInterFace
{
	//根据地址获取设备id
	//host如果为false，则表示不考虑host
	static function getDevidFromAddr($phy,$logic,$host=false)
	{
		$c = new TableSql('homedev','ID');
		if( NULL != $phy )
		{
			//优先使用物理地址查询
			$info = $c->query('ID,LOGICADDR,SUBHOST','PHYADDR=?',array($phy));
			if( false === $host )
			{
				$host = $info['SUBHOST'];//如果不考虑，直接置为当前值就行
			}

			if( NULL != $info 
			&& ( ($info['LOGICADDR'] != $logic) || ( $info['SUBHOST'] != $host ) ) 
			)
			{
				$info['SUBHOST']   = $host;
				$info['LOGICADDR'] = $logic;
				$c->update($info);
			}
			return $info['ID'];
		}
		if( false === $host )
		{
			return $c->queryValue('ID','LOGICADDR=?',array($logic));
		}
		return $c->queryValue('ID','LOGICADDR=? AND SUBHOST=?',array($logic,$host));
	}
	//设备离线通知
	static function devOffline($id)
	{
		$c = new TableSql('homedev','ID');

		$info = array();
		$info['ID']     =  $id;
		$info['STATUS'] =  DEV_STATUS_OFFLINE;
		$c->update($info);
		statusNotice('dev');
		statusNotice('roomAttrMap');

		//$GLOBALS['dstpSoap']->setModule('frame','alarm');
		//$GLOBALS['dstpSoap']->alarm($id,INVALID_ID,DEV_ALARM_OFFLINE, array('m'=>'home','s'=>'end'));
	}
	//设备上线通知
	static function devOnline($id,$logic=NULL)
	{
		$c = new TableSql('homedev','ID');
		$info = array();
		$info['ID']     = $id;
		$info['STIME']  = time();
		$info['STATUS'] =  DEV_STATUS_RUN;
		$c->update($info);
		statusNotice('dev');
		statusNotice('roomAttrMap');

		//设备上线时，更新其未处理的组消息		
		$GLOBALS['dstpSoap']->setModule('smart','devgroup');
		$GLOBALS['dstpSoap']->updateDevGroupDevAttr($id);
		
		//清除告警。暂时没处理升级自动升级，先暂时由这儿来清除
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->alarm($id,INVALID_ID,DEV_ALARM_CLEAN, array('m'=>'home','s'=>'end'));
	}
	//添加设备。简单添加纪录，只有确认后才会填充其他信息
	static function recordEnd($dev)
	{
		if( (NULL == trim($dev['NAME'])) || (NULL == trim($dev['SN']))
				|| (NULL == trim($dev['VER'])))
		{
			return INVALID_ID;
		}

		$r = json_encode($dev['NAME']);
		if( FALSE  === $r )
		{
			return INVALID_ID;
		}

		//SN是二进制数据，需要先转为16进制数据
		if( '123'!= $dev['SN'] )
		{
			$a = unpack("H*",$dev['SN']);
			$dev['SN'] = $a[1];

			//根据chipid判断是否允许该设备使用
			
		}


		//dev里需要有PHYDEV,NAME,SN,LOGICADDR,PHYADDR
		// 首先判断该设备是否已经有信息 
		$c = new TableSql('homedev','ID');
		$id = self::getDevidFromAddr($dev['PHYADDR'],$dev['LOGICADDR'],$dev['SUBHOST']);
		if ( validID($id) )
		{
			$dev = array();
			$dev['ID']    = $id;
			$dev['ATIME'] = time();
			$c->update($dev);

			//判断状态
			$status = $c->queryValue('STATUS','ID=?',array($id));
			switch ( $status )
			{
				//主人还没确认，继续等待
				case DEV_STATUS_INIT:
					//如果是用户直接通过smartconfig配置的设备，直接允许无需确认。
					if( 'i' == HIC_LOCAL && PHYDEV_TYPE_IP == $dev['PHYDEV']  )
					{
						self::addEnd($id); 
					}
					break;
				//确认过，有可能是对方没收到。继续直接下发确认	
				case DEV_STATUS_WAITACK:
					self::sendConfirm($id);
					break;
				default:
					//如果是已经加入状态，则该设备可能是被重新置位。
					//为了防止伪造，需要主人重新确认。暂没处理MAC伪造，先直接回复确认消息
					//$info = array();
					//$info['ID']      = $id;
					//$info['STATUS']  = DEV_STATUS_INIT;
					//$c->update($info);
					//self::addEnd($id);
					//break;

					//有可能同一个设备误复位，所以加入时，需要把组播组重置.这儿需要使用hook以免代码太多硬编码
					$GLOBALS['dstpSoap']->setModule('smart','devgroup');
					$GLOBALS['dstpSoap']->resetDevGroup($id);

					self::sendConfirm($id);

					
					if( PHYDEV_TYPE_IP == $dev['PHYDEV'] )
					{//误复位重新加入也需要重新通知
						statusNotice('adddev');
					}
					break;
			}

			statusNotice('dev');

			return $id;
		}
		
		$dev['ATIME']    = time();
		$dev['STIME']    = time();
		$dev['ETIME']    = time();
		if (PHYDEV_TYPE_SYS == $dev['PHYDEV'])
		{
			$dev['ROOMID']  = ROOM_SYSDEV_SYS;
			$dev['LOGICID'] = md5('1');
			$dev['CHID']    = $dev['LOGICID'];
			$dev['HCID']    = $dev['LOGICID'];
			$dev['STATUS']  = DEV_STATUS_WAITACK;
			$id = $c->add($dev);
			if ( !validID($id) )
			{
				return false;
			}
			//系统设备直接下发确认
			return self::sendConfirm($id); 
		}
		
		//如果是设备，直接把wifi信息改为设备类型
		if( 'b' == HIC_LOCAL && PHYDEV_TYPE_IP == $dev['PHYDEV'] )
		{
			$GLOBALS['dstpSoap']->setModule('home','client');
			$GLOBALS['dstpSoap']->allowClient($dev['PHYADDR'],DEV_CLIENT_DEV);
		}
		
		//如果不是在添加设备状态，直接返回
		$r = Cache::get("openDevSearch");
		if( false == $r )
		{
			return INVALID_ID;
		}
		
		$dev['ROOMID'] = ROOM_SYSDEV_UNADDR;
		$dev['STATUS'] = DEV_STATUS_INIT;
		$id = $c->add($dev);
		
		//所有设备，只要进入到添加页面，直接自动添加进入
		self::addEnd($id); 
		statusNotice('dev');
		statusNotice('adddev');
		return $id;
		
		//如果是用户直接通过smartconfig配置的设备，直接允许无需确认。
		if( 'i' == HIC_LOCAL && PHYDEV_TYPE_IP == $dev['PHYDEV']  )
		{
			self::addEnd($id); 
			statusNotice('dev');
			return $id;
		}
		statusNotice('dev');
		
		//向主人发送确认通知
		//include_once('b/homeLang.php');
		//$info = array();
		//$info['TITLE'] = HOME_DEV_NEW;
		//$info['DESCRIPTION'] = sprintf(HOME_DEV_NEWINFo,$dev['NAME']);
		
		//$GLOBALS['dstpSoap']->setModule('app','push');
		//$GLOBALS['dstpSoap']->sendNotice($info);

		include_once('b/homeLang.php');
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->alarm($id,INVALID_ID,HOME_DEV_WIFICONNECT, array('m'=>'home','s'=>'end'));

		//启动定时器，如果有设备连接上网，但是长时间未加入则删除记录并且清除其警告信息
		include_once('plannedTask/PlannedTask.php');
		$time = time()+60*60*12;
		$planTask = new PlannedTask('home','end',date('Y-m-d H:i',$time));		
		$planTask->cleanRecord($id);

		return $id;
	}

	//长时间未加入的设备要自动删除并清除警告
	static function cleanRecord($id)
	{		
		$c = new TableSql('homedev','ID');
		$status = $c->queryValue('STATUS','ID=?',array($id));
		if( $status == '0' )
		{
			self::del($id);
		}
	}
	
	//确认设备使用
	static function addEnd($id)
	{
		$c = new TableSql('homedev','ID');
		//判断ID是否存在
		$di = $c->query('ID,NAME,STATUS,LOGICID,PHYDEV,PHYADDR','ID=?',array($id));
		if ( NULL == $di ) //没有相关设备，直接返回
		{
			return false;
		}
		
		//修改设置其允许权限
		if( PHYDEV_TYPE_IP == $di['PHYDEV'] && 'b' == HIC_LOCAL )
		{
			$GLOBALS['dstpSoap']->setModule('home','client');
			$GLOBALS['dstpSoap']->allowClient( $di['PHYADDR'],DEV_CLIENT_DEV);
		}
		
		//如果已经确认过，可能是用户重复点击。则无需再处理
		if ( DEV_STATUS_INIT != $di['STATUS'] )
		{
			return true;
		}
		
		//修改记录
		$info = array();
		$info['ID']      = $id;
		$info['STATUS']  = DEV_STATUS_WAITACK;
		if( NULL == $di['LOGICID'] )
		{
			//LOGICID,CHID和HCID都是4个字节,数据库中不能保存二进制，这儿保存数字字串，使用时需要解码
			//避免生成0
			$info['LOGICID'] = mt_rand(1,mt_getrandmax())*(mt_rand(0,1)?1:-1);
			$info['CHID']    = mt_rand(1,mt_getrandmax())*(mt_rand(0,1)?1:-1);
			$info['HCID']    = mt_rand(1,mt_getrandmax())*(mt_rand(0,1)?1:-1);
		}
		$c->update($info);
		
		//清除发给用户的告警通知
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->alarm($id,INVALID_ID,DEV_ALARM_CLEAN, array('m'=>'home','s'=>'end'));
		
		//下发确认命令，等待回应
		self::sendConfirm($id);

		statusNotice('devgroup');
		statusNotice('roomAttrMap');
		statusNotice('status');

		//if( PHYDEV_TYPE_IP == $di['PHYDEV'] )
		//{
		//	statusNotice('adddev');
		//	//$adddev = Cache::get('adddevinfo');
		//	//if( false == $adddev )
		//	//{
		//	//	$adddev = array();
		//	//}
		//	//$adddev[] = $di['NAME'];
		//	//Cache::set('adddevinfo',$adddev,60);
		//}
		
		return true;
	}
	
	//向设备下发加入确认命令
	static function sendConfirm($id)
	{
		$c    = new TableSql('homedev','ID');
		$info = $c->query('*','ID=?',array($id));
		if ( NULL == $info )
		{
			return false;
		}
		//if ( DEV_STATUS_WAITACK != $info['STATUS'] )
		//{
		//	return true; //可能已经确认过无需再重复
		//}
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsg($info,DEV_CMD_HIC_CONFIRM,$info);
		
		//如果一直没接到确认消息，设备会重复发送请求加入消息，那儿回复即可，无需这儿重复下发 
		return true;
		
		//启动定时器，如果迟迟没收到回复再重新下发
		//include_once('plannedTask/PlannedTask.php');  // Smarty class.
		//$time = array();
		//$time['cyc']   = PLAN_TIME; 
		//$time['other'] = 2; 
		//$planTask = new PlannedTask('home','end', $time);
		//$planTask->sendConfirm($id);
	}
	
	static function receiveConfirmAck($id,$attrList)
	{
		//先查当前状态，如果是
		$c    = new TableSql('homedev','ID');
		$di = $c->query('ID,STATUS,PHYDEV,PHYADDR','ID=?',array($id));
		if( NULL == $di )
		{
			return true;
		}
		
		$status = $di['STATUS'];
		if ( DEV_STATUS_WAITACK != $status )
		{
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_HIC_JOIN_CONFIRM);
			return true; //可能已经确认过无需再重复
		}

		//如果是wifi设备，需要更新mac访问权限
		if( ( PHYDEV_TYPE_IP == $di['PHYDEV'] ) && ( 'b'==HIC_LOCAL ) )
		{
			//要更新下设备的ip地址。从homeclient中获取
			$c = new TableSql('homeclient','ID');
			$ip = $c->queryValue('IP','MAC=?',array($di['PHYADDR']));
			
			$c    = new TableSql('homedev','ID');
			$devinfo = array();
			$devinfo['ID'] = $id;
			$devinfo['LOGICADDR'] = $ip;
			$c->update($devinfo);

			$GLOBALS['dstpSoap']->setModule('home','client');
			$GLOBALS['dstpSoap']->allowClient( $di['PHYADDR'],DEV_CLIENT_DEV);
		}
		
		//因为没做原子操作，为了避免重复添加，这儿需要先删再加
		$c = new TableSql('homeattr','ID');
		$c->del('DEVID=?',array($id));
		self::addDevAttList($id,$attrList);

		//因为可能重复添加,清除发给用户的告警通知
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->cleanDevAlarm($id);

		$info = array();
		$info['ID']     = $id;
		$info['STATUS'] = DEV_STATUS_RUN;
		$c    = new TableSql('homedev','ID');
		$c->update($info);
		
		statusNotice('dict');
		statusNotice('roomAttrMap');

		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_HIC_JOIN_CONFIRM);
	}
	
	static function addDevAttList($id,$attrList)
	{
		//记录属性列表
		$attrkey = array('NAME','SYSNAME','ATTRINDEX','CANDEL',
						'CFGINFO','INUSE','ICON','YYBM','ISR','ISC','ISS');
		$c = new TableSql('homeattr','ID');
		foreach( $attrList as &$attr )
		{
			if( ('i' == HIC_LOCAL) && ( 'dpms' == $attr['SYSNAME'] ) )
			{
				$attr['SYSNAME'] = 'ms';
			}

			//根据sysname获取其它相关配置信息
			$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
			$cfg = $GLOBALS['dstpSoap']->getAttrTypeCfg($attr['SYSNAME']);
			if( NULL == $cfg )
			{
				continue;
			}

			$info = array();
			$info['DEVID']  = $id;
			$info['ISR']  	= $cfg['r'];
			$info['ISC']  	= $cfg['c'];
			$info['ISS']  	= $cfg['s'];
			$info['VF']   	= $cfg['vf'];
			$info['CF']   	= $cfg['cf'];
			foreach( $attrkey as &$key )
			{
				if( array_key_exists($key,$attr) )
				{
					$info[$key] = $attr[$key];
				}
			}
			$attrid = $c->add($info);
			
			//属性添加回掉通知
			$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
			$GLOBALS['dstpSoap']->setAttrType($attr['SYSNAME']);
			$GLOBALS['dstpSoap']->addAttrNotice($attrid);
			
			noticeAttrModi($attrid);
		}
		
		//查找设备受控属性个数
		$cnum = $c->getRecordNum('DEVID=? AND ISC=? AND SYSNAME!=?',array($id,1,'gj'));

		//根据ispower和attr是否受控，设置POWERTYPE
		$c   = new TableSql('homedev','ID');
		$info = $c->query('ID,ISPOWER','ID=?',array($id));
		if ( DEV_POWER_POWER != $info['ISPOWER'] )
		{
			if( 0 != $cnum )
			{
				$info['POWERTYPE'] = POWER_BAT_CTRL;
			}
			else
			{
				$info['POWERTYPE'] = POWER_BAT_RPT;
			}
			$c->update($info);
		}
		
		statusNotice('dict');
		statusNotice('roomAttrMap');
		return true;
	}
	
	//设置属性的额外信息
	static function setDevConf($id,$info)
	{
		if( 14 == $info['index'] )
		{
			return self::setAttrLayout($id,$info['info']);
		}

		if( 15 == $info['index'] )
		{
			return self::setAttrIcon($id,$info['info']);
		}

		$c = new TableSql('homeattr','ID');
		$attr = $c->query('ID,SYSNAME,CFGINFO','DEVID=? AND ATTRINDEX=?',array($id,$info['index']));
		if( NULL == $attr )
		{
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_DEV_ATTR_CONF_RSP,$info['index']);
			return false;
		}
		
		//解析附加信息
		//解析是否存在布局设计字段，保存布局设计字段
		$cfg = unserialize( $attr['CFGINFO'] );
		if( false == $cfg ) //如果已经加入了，则无需再重复添加。如果有变化，需要删掉重加
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
			$GLOBALS['dstpSoap']->setAttrType($attr['SYSNAME']);
			$v = $GLOBALS['dstpSoap']->parseAdditonInfo($info,$attr['ID']);
			if ( false !== $v )
			{
				$attr['CFGINFO'] = serialize($v);
				$c->update($attr);
			}
		}
		
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_DEV_ATTR_CONF_RSP,$info['index']);
		return true;
	}
	
	static function setAttrLayout($id,$layoutList)
	{
		$info = array();
		$c1 = new TableSql('homeattr','ID');
		$c  = new TableSql('homeattrlayout');
		foreach( $layoutList as &$layout )
		{
			$info = array();
			$info['ATTRID'] = $c1->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($id,$layout['ATTRINDEX']));
			if( array_key_exists('LAYOUT',$layout) )
			{
				$mainid = $c1->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($id,$layout['MAININDEX']));
				$info['MAINID'] = $mainid;
				$info['LAYOUT'] = $layout['LAYOUT'];
			}
			else
			{
				$info['MAINID'] = 0;
				$info['LAYOUT'] = 0;
				$info['NAME']   = $layout['NAME'];
				if( NULL != $layout['PAGE'] )
				{
					$info['PAGE']   = $layout['PAGE'];
				}
			}
			if( NULL != $layout['TIPS'] )
			{
				$info['TIPS']   = $layout['TIPS'];
			}
			
			noticeAttrModi($info['ATTRID']);
			$c->add($info);
		}
		Cache::del('attrlayout');
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_DEV_ATTR_CONF_RSP,14);
		return;
	}
	
	static function setAttrIcon($id,$iconList)
	{
		$info = array();
		$c = new TableSql('homeattr','ID');
		foreach( $iconList as &$icon )
		{
			$info['ICON'] = $icon['ICON'];
			$c->update($info,NULL,'DEVID=? AND ATTRINDEX=?',array($id,$icon['ATTRINDEX'])); 
		}
		
		//图标修改后，要更新下属性信息及时更新图标信息
		$attrList = $c->queryAllList('ID','DEVID=?',array($id));
		foreach($attrList as $attrid)
		{
			noticeAttrModi($attrid);
		}
		
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_DEV_ATTR_CONF_RSP,15);
		return;
	}
	
	static function updateDevSN($id,$info)
	{
		$info['ID'] = $id;
		$c = new TableSql('homedev','ID');
		$c->update($info);
	}
	
	//删除指定属性,id是attrid
	static function delAttr($id,$force=false)
	{
		$c = new TableSql('homeattr','ID');
		$info = $c->query('ID,DEVID,CANDEL,SYSNAME,ATTRINDEX','ID=?',array($id));
		if( !$info['CANDEL'] && !$force )
		{
			return false;
		}	

		//删除属性，相对于主页的信息也要修改
		$GLOBALS['dstpSoap']->setModule('setting','setting');
		$GLOBALS['dstpSoap']->resetFavorite($id);
		$GLOBALS['dstpSoap']->resetdjyk($id);

		// 先找出该属性对应的组ID,然后更新PREID
		$c = new TableSql('smartgroupattr','ID'); 
  		$gId = $c->queryValue('GROUPID','ATTRID=?',array($id));
		$c->del('ATTRID=?',array($id));
		$list = $c->queryAll('ID,PREID','GROUPID=?',array($gId));
		$upInfo = array();
		for ($i=1; $i < count($list); $i++) { 
			if ($list[$i]['PREID'] != $list[$i-1]['ID']) {
				$upInfo['PREID'] = $list[$i-1]['ID'];
				$c->update($upInfo,NULL,'ID=?',array($list[$i]['ID']));
			}
		}

		//删除指定属性时，删除相对应的智能模式
		$c = new TableSql('smartdev');
		$c1 = new TableSql('smarttriger');
		$c2 = new TableSql('smartsmart','ID');
		$delid = $c->queryAll('SID','ATTRID=?',array($id));
		foreach ($delid as $k => $v) 
		{
			$isdel = $c->queryAll('SID','SID=?',array($v['SID']));
			$isdel1 = $c1->queryAll('SID','SID=?',array($v['SID']));
  			$from = $c2->query('*','ID=?',array($v['SID']));  
			if( ($from['SAVEFROM'] == 1 && sizeof($isdel) == 1 && sizeof($isdel1) == 1) || ($from['SAVEFROM'] == 2 && sizeof($isdel) == 1 && sizeof($isdel1) == 0) )
			{
				$GLOBALS['dstpSoap']->setModule('smart','smart');
				$GLOBALS['dstpSoap']->delSmart($v['SID']);								
			}
			//删除和该属性相关的普通的智能模式的条件
			if( $from['SAVEFROM'] == 0 )
			{
				$qcond 	= unserialize($from['QCOND']);
				$cond 	= unserialize($from['COND']);

				foreach( $qcond['sub'] as $key => $value ) 
				{
					if( $value['sub'][0]['ID'] == $id )
					{
						$c->del('ATTRID=?',array($id));   
						unset($qcond['sub'][$key]);
						unset($cond['cond'][$key]);
						$info['ID']     = $from['ID'];
						$info['COND']   = serialize($cond);
						$info['QCOND']  = serialize($qcond);
						$c2->update($info);
					}
				}				
			}
		}



		//清除发给用户的告警通知
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->alarm($info['DEVID'],id,DEV_ALARM_CLEAN, array('m'=>'home','s'=>'end'));
		
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$GLOBALS['dstpSoap']->setAttrType($info['SYSNAME']);
		$GLOBALS['dstpSoap']->delAttrNotice($id,$info['DEVID'],$info['ATTRINDEX']);
		$df = $GLOBALS['dstpSoap']->getDel();
		if ( NULL != $df )
		{
			$GLOBALS['dstpSoap']->setModule($df['m'], $df['s']);
			$GLOBALS['dstpSoap']->{$df['f']}( $info['DEVID'],$info['ATTRINDEX'] );
		}

		//删除属性layout相关信息
		$clay = new TableSql('homeattrlayout');
		$clay->del('ATTRID=?',array($id));
		
		Cache::del('attrlayout');
		statusNotice('dict');
		statusNotice('devgroup');
		statusNotice('roomAttrMap');

		$c = new TableSql('homeattr','ID');
		return $c->del('ID=?',array($id));
	}	
	
	//删除设备，同时删除相关属性.force:就是系统设备也强制删除
	static function del($id,$force=false)
	{
		$c    = new TableSql('homedev','ID');
		$di  = $c->query('SUBHOST,PHYDEV,PHYADDR','ID=?',array($id));
		if( NULL == $di )
		{
			return false;
		}
		if( (PHYDEV_TYPE_SYS == $di['PHYDEV']) && ( !$force ) )
		{
			//系统设备不能删除
			return false;
		}

		$c    = new TableSql('homeattr','ID');
		//删除属性前，找出该设备的所有相关特殊删除属性先清理
		$delAttr = $c->queryAll('ID,SYSNAME,ATTRINDEX','DEVID=?',array($id));
		foreach( $delAttr as &$del )
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
			$GLOBALS['dstpSoap']->setAttrType($del['SYSNAME']);
			$GLOBALS['dstpSoap']->delAttrNotice($del['ID'],$id,$del['ATTRINDEX']);
			$df = $GLOBALS['dstpSoap']->getDel();
			if ( NULL != $df )
			{
				$GLOBALS['dstpSoap']->setModule($df['m'], $df['s']);
				$GLOBALS['dstpSoap']->{$df['f']}($id,$del['ATTRINDEX']);
			}

			self::delAttr($del['ID'],true);	
		}
		
		//清除发给用户的告警通知
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->cleanDevAlarm($id);	

		$c->del('DEVID=?',array($id));
		
		//针对不同设备类型的特殊处理
		switch( $di['PHYDEV'] )
		{
			case PHYDEV_TYPE_IP: //wifi设备删除时，要同时删除其网络访问权限
				$GLOBALS['dstpSoap']->setModule('home','client');
				$GLOBALS['dstpSoap']->allowClient( $di['PHYADDR'], DEV_CLIENT_INIT);
				break;
			case PHYDEV_TYPE_ZIGBEE: //zigbee设备删除时，要通知协调器从关联表中删除该设备，以免关联表太大
				$GLOBALS['dstpSoap']->setModule('home','if');
				$GLOBALS['dstpSoap']->sendDevSySMsg($di['SUBHOST'],DEV_CMD_SYS_RM_DEV_ASSOC,$di['PHYADDR']);
				break;
			default:
				break;
		}
		
		statusNotice('dict');
		statusNotice('devgroup');
		statusNotice('roomAttrMap');
		statusNotice('status');
		
		//删除设备时，从设备组中删除相关信息。这儿后续需要添加hook处理		
		$GLOBALS['dstpSoap']->setModule('smart','devgroup');
		$GLOBALS['dstpSoap']->delDevGroupDev($id);

		// 最后删除设备
		$c = new TableSql('homedev','ID');
		$c->del('ID=?',array($id));
		
		return true;
	}
	
	//停用设备
	static function stop($id)
	{
		$c    = new TableSql('homeattr','ID');
		$attrid = $c->queryAll('ID','DEVID=?',array($id));
		foreach ($attrid as $key => $value) {
			//删除属性，相对于主页的信息也要修改
			$GLOBALS['dstpSoap']->setModule('setting','setting');
			$GLOBALS['dstpSoap']->resetFavorite($value['ID']);
		}
		$c    = new TableSql('homedev','ID');
		$info = $c->query('ID,STATUS','ID=?',array($id));
		if( NULL == $info )
		{
			return false;
		}
		if ( DEV_STATUS_RUN != $info['STATUS'] 
			&& DEV_STATUS_OFFLINE != $info['STATUS'] 
			&& DEV_STATUS_POWER != $info['STATUS'] )
		{
			return false;
		}

		//清除发给用户的告警通知
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->cleanDevAlarm($id);

		$info['STATUS'] = DEV_STATUS_STOP;
		$c->update($info);

		statusNotice('dev');
		statusNotice('roomAttrMap');

		return true;
	}
	//重新启用设备
	static function restart($id)
	{
		$c    = new TableSql('homedev','ID');
		$info = $c->query('ID,STATUS','ID=?',array($id));
		if( NULL == $info )
		{
			return false;
		}
		if ( DEV_STATUS_STOP != $info['STATUS'] )
		{
			return false;
		}
		$info['STATUS'] = DEV_STATUS_OFFLINE;
		$c->update($info);

		statusNotice('dev');
		statusNotice('roomAttrMap');

		return true;
	}

	function setAttrStatus($attrid,$status,$fromgj=false)
	{
		$c = new TableSql('homeattr','ID');
		$attrino = $c->query('DEVID,SYSNAME','ID=?',array($attrid));
		if( -1 == $attrino['INUSE'] )
		{
			//-1表示小家电的功能设备，不能停用
			return false;
		}

		$info = array();
		$info['ID']     = intval($attrid);
		$info['INUSE']  = intval($status)?1:0;
		//设备停用时，状态要清除
		$info['ATTRINT']   = NULL;
		$info['ATTRFLOAT'] = NULL;
		$info['ATTRSTR']   = NULL;
		$info['SENDATTR']  = NULL;	
		$c->update($info);

		//删除属性，相对于主页的信息也要修改
		$GLOBALS['dstpSoap']->setModule('setting','setting');
		$GLOBALS['dstpSoap']->resetFavorite($attrid);
		//停用时，相关告警也需要清除。这儿就不判断是否停用了，反正启用时清除也没问题
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->alarm($attrino['DEVID'],$attrid,DEV_ALARM_CLEAN);
		
		statusNotice('dev');
		statusNotice('roomAttrMap');

		return true;
	}

	//向设备发送控制命令
	//attrList:二维数组 array('id','cmd')
	static function sendMsg($id,$attrList)
	{
		$c    = new TableSql('homedev','ID');
		$info = $c->query('ID,STATUS,PHYDEV,SUBHOST,VER,PHYADDR,LOGICADDR,LOGICID,TMSI',
							'ID=?',array($id));
		if ( DEV_STATUS_STOP == $info['STATUS'] 
			|| DEV_STATUS_INIT == $info['STATUS']
			|| DEV_STATUS_WAITACK == $info['STATUS']
		)
		{
			return false;
		}
		
		$c    = new TableSql('homeattr','ID');

		$msg = array();
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		foreach( $attrList as &$attr )
		{
			$ctrlinfo = $c->query('ATTRINDEX,SYSNAME','ID=?',array($attr['ID']));

			$GLOBALS['dstpSoap']->setAttrType($ctrlinfo['SYSNAME']);
			$ctrlinfo['SENDATTR'] = $GLOBALS['dstpSoap']->getCMDInfo($attr['ATTR'],$attr['ID']);
			if( false === $ctrlinfo['SENDATTR'] )
			{
				continue;
			}
			if( is_array($ctrlinfo['SENDATTR']) )//这种情况下，表示需要修改index
			{
				$ctrlinfo['ATTRINDEX'] = $ctrlinfo['SENDATTR']['index'];
				$ctrlinfo['SENDATTR']  = $ctrlinfo['SENDATTR']['value'];
			}

			$msg[] = $ctrlinfo;
		}
		if( $msg != NULL )
		{
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($info,DEV_CMD_HIC_CTRL_DEV,$msg);		
		}
		return true;
	}
	//根据设备请求获取缓存的命令
	static function getDevCtrl($id,$statusmsg)
	{
		$c = new TableSql('homedev','ID');	
		$info = $c->query('ID,STATUS','ID=?',array($id));
		if ( NULL == $info )
		{
			return;
		}
		
		//HIC还没收到确认加入以及属性信息
		if( DEV_STATUS_WAITACK == $info['STATUS'] )
		{
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_HIC_GET_ATTRLIST);
		}

		if ( DEV_STATUS_RUN != $info['STATUS'] 
			&& DEV_STATUS_OFFLINE != $info['STATUS']
			&& DEV_STATUS_POWER != $info['STATUS'])
		{
			return;
		}

		$info['ETIME'] = time();
		$c->update($info);
		if ( DEV_STATUS_OFFLINE == $info['STATUS'] )
		{
			self::devOnline($id);
		}
		
		//$c    = new TableSql('homeattr','ID');
		//$msg = $c->queryAll('ID,SYSNAME,ATTRINDEX,SENDATTR','DEVID=? AND SENDATTR IS NOT NULL',array($id));
		//$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		//foreach( $msg as $key=>&$attr)
		//{
		//	$GLOBALS['dstpSoap']->setAttrType($attr['SYSNAME']);
		//	//把数据库存储的控制信息转为设备实际控制信息
		//	$attr['SENDATTR'] = $GLOBALS['dstpSoap']->getCMDInfo( unserialize($attr['SENDATTR']),$attr['ID'] );
		//	if( false === $attr['SENDATTR'] )
		//	{
		//		unset($msg[$key]);
		//		continue;
		//	}
		//	if( is_array($attr['SENDATTR']) )//这种情况下，表示需要修改index
		//	{
		//		$attr['ATTRINDEX'] = $attr['SENDATTR']['index'];
		//		$attr['SENDATTR']  = $attr['SENDATTR']['value'];
		//	}
		//}
		//$info = array();
		//$info['SENDATTR'] = '';
		//$c->update($info,NULL,'DEVID=?',array($id));
		$msg = array();
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_HIC_CTRL_DEV,$msg);
		
		//控制下发命令也有可能带状态报告信息
		if( NULL != $statusmsg )
		{
			self::updateDevStatus($id,$statusmsg);
		}
	}
	//设备报告状态
	//attrList结构见cmd接口中genHICMsg的解析
	static function updateDevStatus($id,$statusList)
	{
		//更新设备状态
		$c = new TableSql('homedev','ID');	
		$info = $c->query('ID,STATUS,PHYDEV','ID=?',array($id));
		if ( NULL == $info )
		{
			return;
		}
		
		//HIC还没收到确认加入以及属性信息
		if( DEV_STATUS_WAITACK == $info['STATUS'] )
		{
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_HIC_GET_ATTRLIST);
		}
		
		if ( DEV_STATUS_RUN != $info['STATUS'] 
			&& DEV_STATUS_OFFLINE != $info['STATUS']
			&& DEV_STATUS_POWER != $info['STATUS'])
		{
			return;
		}
		if ( DEV_STATUS_OFFLINE == $info['STATUS'] )
		{
			self::devOnline($id);
		}
		
		//在消息刚到达时，需要更新数据表存储临时密钥。直接在那保存，省掉一次数据库读写
		//unset($info['STATUS']);
		//$info['ETIME']     = time();
		//$c->update($info);
		
		$smartTriger = array();
		$c = new TableSql('homeattr','ID');	
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		foreach( $statusList as &$status )
		{
			//根据devid和attrindex获取sysname
			$attr = $c->query('ID,SYSNAME,INUSE,ATTRSTR','DEVID=? AND ATTRINDEX=?',array($id,$status['ATTRINDEX'])); 
			if ( NULL == $attr )
			{
				continue;
			}
			if ( !$attr['INUSE'] )
			{
				continue;
			}
			//根据sysname解析status
			$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
			$GLOBALS['dstpSoap']->setAttrType($attr['SYSNAME']);
			if( PHYDEV_TYPE_SYS == $info['PHYDEV']) //为了代码方便，系统设备发送接收都不进行格式处理
			{
				$v = $status['STATUS'];
			}
			else
			{
				$v = $GLOBALS['dstpSoap']->getStatusInfo($status['STATUS'],$attr['ID']);
				if( false === $v )
				{
					//设备上报的数据如果没经过转化，不可能为PHP中的false值。转化后的false值为约定不后续处理
					continue;
				}
			}
			
			//这个地方是否可以改为，如果状态没变化，就无需下面的处理？
			if( $attr['ATTRSTR'] == $v ) //这个地方是否需要判断0和NULL？
			{
				continue;
			}
			//存储信息到表中。这儿理论要根据存储类型决定放哪个字段
			$attr['ATTRINT']   = intval($v);
			$attr['ATTRFLOAT'] = floatval($v);
			$attr['ATTRSTR']   = $v;
			//这儿的更新数据无需保存到flash，所以最后一个参数置为false
			$c->update1($attr);
			
			statusNotice('dev');
			noticeAttrModi($attr['ID']);

			$smartTriger[] = $attr['ID'];

			//判断设备是否需要处理告警信息
			$alarm = $GLOBALS['dstpSoap']->getAlarmInfo($v,$attr['ID']);
			if( DEV_ALARM_IGNORE != $alarm)
			{
				$GLOBALS['dstpSoap']->setModule('frame','alarm');
				$GLOBALS['dstpSoap']->alarm($id,$attr['ID'],$alarm);
			}
		}
		
		//检测智能模式是否触发
		if ( NULL != $smartTriger )
		{
			$GLOBALS['dstpSoap']->setModule('smart','smart');
			$GLOBALS['dstpSoap']->checkAttrTriger($smartTriger);
		}
		return true;
	}
	
	//item服务器中扫描告警设备
	static function scanEndOnlineInI()
	{
		if( 'i' != HIC_LOCAL )
		{
			return;
		}
		setSysUid(0);
		$c = new TableSql('homedev','ID');
		//已经确认使用的，离线则发告警。ISPOWER为0或者1的，检测间隔为50秒
		$etime = time()-235; //设备60秒报一次状态。5次没心跳则告警。总间隔时间必须大于1分钟，因为状态接受进程有可能中断1分钟后再重启
		$all = $c->queryAll('ID,ISPOWER,ETIME,CLOUDID','STATUS=? AND ETIME<?',array(DEV_STATUS_RUN,$etime));
		foreach( $all as &$a )
		{
			switch(intval($a['ISPOWER']))
			{
				case 0:
				case 0xFF:
					setSysUid($a['CLOUDID']);
					self::devOffline($a['ID']);
					break;
				default: //ispower如果非0和0xFF，则表示其状态报告时ispower分钟上报一次
					if( $a['ETIME'] < ( $etime - $a['ISPOWER']*250)  )
					{
						setSysUid($a['CLOUDID']);
						self::devOffline($a['ID']);
					}
					break;
			}
		}
		setSysUid(0xFFFFFFFF);
		return true;		
	}


	//扫描告警设备
	static function scanEndOnline()
	{
		if( 'b' != HIC_LOCAL )
		{
			return;
		}
		//刚上电时不能扫描告警。因为刚上电时，状态报告信息大量失去会误报
		$time =`cat /proc/uptime`;
		list($uptime) = explode(' ',$time);
		if ( intval($uptime) < 300 ) //上电5分钟后再执行离线扫描
		{
			return false;
		}
		
		$c = new TableSql('homedev','ID');
		//如果是离线一段时间且未确认添加，则直接删除

		//已经确认使用的，离线则发告警。ISPOWER为0或者1的，检测间隔为50秒
		$etime = time()-235; //设备25秒报一次状态。5次没心跳则告警。总间隔时间必须大于1分钟，因为状态接受进程有可能中断1分钟后再重启
		$all = $c->queryAll('ID,ISPOWER,ETIME','STATUS=? AND PHYDEV!=?',array(DEV_STATUS_RUN,PHYDEV_TYPE_SYS));
		foreach( $all as &$a )
		{
			switch(intval($a['ISPOWER']))
			{
				case 0:
				case 0xFF:
					if( $a['ETIME'] < $etime  )
					{
						self::devOffline($a['ID']);
					}
					break;
				default: //ispower如果非0和0xFF，则表示其状态报告时ispower分钟上报一次
					if( $a['ETIME'] < ( $etime - $a['ISPOWER']*250)  )
					{
						if ( $uptime > $a['ISPOWER']*250 )
						{
							self::devOffline($a['ID']);
						}
					}
					break;
			}			
		}
		return true;		
	}
	
	/***********其他模块要求的接口**********************************/
	//告警通知时返回给用户的设备名称
	static function getAlarmAttrName($devid,$attrid)
	{
		if( validID($attrid) )
		{
			$GLOBALS['dstpSoap']->setModule('devattr');
			return $GLOBALS['dstpSoap']->getAlarmAttrName($devid,$attrid);
		}

		$c = new TableSql('homedev','ID');
		$dname = $c->query('NAME,ROOMID','ID=?',array($devid));
		$devname  = $dname['NAME'];
		$roomname = NULL;
        
		if ( validID($dname['ROOMID']) )
		{
			$c = new TableSql('homeroom','ID');
			$name  = $c->queryValue('NAME','ID=?',array($dname['ROOMID']));
			$devname  = $name.'-'.$devname;
		}
        
		return $devname ;
	}
	
}
?>