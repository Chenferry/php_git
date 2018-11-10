<?php
include_once('../../a/config/dstpCommonInclude.php');  

function allowdev($devid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->addEnd($devid);
}
function stopDev($devid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->stop($devid);
}
function restartDev($devid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->restart($devid);
}
function delDev($devid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->del($devid);
}
function setRoom($devid,$roomid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$roomid = intval($roomid);
	$info = array();
	$info['ID']     = intval($devid);
	$info['ROOMID'] = $roomid;

	//修改属性的房间，对应的定时模式和联动模式中的房间的名字也要做出对应的改变
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	$GLOBALS['dstpSoap']->getSmartName($devid,$smartname,$roomid);

	$c = new TableSql('homedev','ID');
	$c->update($info);
	
	statusNotice('dict');
	statusNotice('roomAttrMap');
	return true;
}


function changeName($devid,$name,$flag=true)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	$name = trim($name);
	if ( NULL == $name )
	{
		include_once('b/homeLang.php');
		return HOME_NAME_NULL;
	}
	$info = array();
	$info['ID']     = intval($devid);
	$info['NAME']   = $name;
	$c = new TableSql('homedev','ID');
	$c->update($info);
	$ststus = $c->queryValue('STATUS','ID=?',array(intval($devid)));
	if( $status == 0 )
	{
		Cache::del('alarmDevList');
	}
	
	//如果该设备只有一个属性，或者只有一个主属性，那么同步修改主属性名称
	if( $flag==true )
	{
		$c = new TableSql('homeattr','ID');
		$attr = $c->queryAllList('ID','DEVID=?',array($devid));
		if( 1 !=  count($attr) ) //只有一个属性
		{
			$c->join('homeattrlayout','homeattrlayout.ATTRID=homeattr.ID');
			$subattr = $c->queryAllList('ID','MAINID!=? AND DEVID=?',array(0,$devid));
			$attr  = array_diff($attr,$subattr);
		}
		if( 1 == count($attr) ) //要嘛只有一个属性，要嘛只有一个主属性
		{
			changeAttrName($attr[0],$name,true,false);
		}
	}

	statusNotice('dict');
	return true;
}

//给属性设置别名，多个别名以,分开
function setAttrAlias($attrid,$alias)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	//把中文的逗号换成英文的
	$alias = str_replace('，',',',$alias);
	$alias = explode(',',$alias);
	$set   = array();
	foreach( $alias as $a )
	{
		if( NULL == $a )
		{
			continue;
		}
		$set[] = $a;
	}

	$info = array();
	$info['ID']   = $attrid;
	$info['YYBM'] = implode(',',$set);
	$c = new TableSql('homeattr','ID');
	$c->update($info);

	//通知重建词典
	statusNotice('dict');

	return true;
}

function changeAttrName($attrid,$name,$mattr=false,$flag=true)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	$name = trim($name);
	if ( NULL == $name )
	{
		include_once('b/homeLang.php');
		return HOME_NAME_NULL;
	}
	
	//检测待修改属性是否是一个独立的主属性
	$isMainAttr = false;
	$c = new TableSql('homeattrlayout','ID');
	$isMainAttr = $c->queryAllList('ATTRID','(ATTRID=? AND MAINID=?) OR ( MAINID=? AND LAYOUT=? )',
									array($attrid,0,$attrid,0));
	if( count($isMainAttr) > 1 )
	{
		$isMainAttr = false;
	}
	else
	{
		$isMainAttr = true;
	}


	$c = new TableSql('homeattr','ID');
	$result = $c->query('NAME,DEVID,ATTRINDEX','ID=?',array($attrid));	
	//如果该属性对应的设备只有一个属性或只有一个主属性且该属性为主属性，
	//那么修改属性的名字同时修改设备的名字
	$attr = $c->queryAllList('ID','DEVID=?',array($result['DEVID']));
	if( 1 !=  count($attr) )
	{
		$c->join('homeattrlayout','homeattrlayout.ATTRID=homeattr.ID');
		$subattr = $c->queryAllList('ID','MAINID!=? AND DEVID=?',array(0,$result['DEVID']));
		$attr  = array_diff($attr,$subattr);
	}
	if( (true == $flag) && (count($attr)==1) && $isMainAttr )
	{
		changeName($result['DEVID'],$name,false);
	}

	//如果修改了公共属性名，且该属性是一个主属性，则连属性名字需要同步修改
	//如果是修改了属性名，且该属性是一个主属性，则连公共属性名字需要同步修改
	//如果属性是一个从属性，则只修改属性名
	//如果属性是一个并列主属性，则只修改公共属性名或者属性名
	
	//独立主属性则属性名和公共属性名都需要修改
	$modiAttr  = true;
	$modiMAttr = true;
	if( !$isMainAttr )
	{
		if($mattr)
		{
			$modiAttr  = false;
			$modiMAttr = true;			
		}
		else
		{
			$modiAttr  = true;
			$modiMAttr = false;			
		}
	}
	
	if( $modiMAttr ) //修改布局对应的公共属性名
	{
		$info         = array();
		$info['NAME'] = $name;
		$c = new TableSql('homeattrlayout');
		$c->update($info,NULL,'MAINID=? AND ATTRID=?',array(0,$attrid));
		Cache::del('attrlayout');
	}
	
	$oldname = $result['NAME'];	
	if( $modiAttr && ($oldname != $name) )
	{
		$info         = array();
		$info['ID']   = intval($attrid);
		$info['NAME'] = $name;
		$c = new TableSql('homeattr','ID');
		$c->update($info);
	}
	else
	{
		$modiAttr = false;
	}
	
	//如果修改了实际属性名，需要处理后续相关信息
	if( !$modiAttr )
	{
		noticeAttrModi($attrid);
		return true;
	}

	if( $result['DEVID'] == -2 )
	{
		statusNotice('devgroup');
		$info = array();
		$info['DGID']   = intval($result['ATTRINDEX']);
		$info['NAME']   = $name;
		$c = new TableSql('smartdevgroup','DGID');
		$c->update($info);
	}
	
	//清空用户首页收藏缓存
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$GLOBALS['dstpSoap']->cleanFavoriteCache();

	//修改属性的名字时修改相对应的智能模式中属性的名字
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	$GLOBALS['dstpSoap']->changeSmartAttrName($attrid,$oldname,$name);
	
	statusNotice('dict');
	noticeAttrModi($attrid);
	return true;
}

function stopAttr($attrid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->setAttrStatus($attrid,0);
}
function startAttr($attrid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->setAttrStatus($attrid,1);
}


function getDevList()
{
	$devList = array();
	$devList['add']  = array();
	$devList['wait'] = array();
	$devList['sys']  = array();
	$devList['dev']  = array();
	
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$roomOrder = $GLOBALS['dstpSoap']->getRoomOrder();
	foreach( $roomOrder as $order )
	{
		$devList['dev'][$order] = array();
	}
	
	//获取可访问权限
	$userRoom = $roomOrder;
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$access = $GLOBALS['dstpSoap']->getUserAccess($GLOBALS['curUserID']);
	if( USER_TYPE_COMMON == $access['type'] )
	{
		if(ROOM_SYSDEV_WHITE == $access['info']['type'])
		{
			$userRoom = $access['info']['room'];
		}
		else
		{
			$userRoom = array_diff($roomOrder,$access['info']['room']);
		}
	}
	


	$c = new TableSql('homedev','ID');
	$result  = $c->queryAll('ID,NAME,STATUS,ROOMID,PHYDEV,SN,RSSI','ID > 0');

	$c = new TableSql('homeattr','ID');
	foreach ($result as &$dev) 
	{
		if( DEV_STATUS_INIT == $dev['STATUS']  )
		{
			$devList['add'][] = $dev;
			continue;
		}
		if( DEV_STATUS_WAITACK == $dev['STATUS']  )
		{
			$devList['wait'][] = $dev;
			continue;
		}

		//如果房间不存在则归为未分区
		if( !in_array( $dev['ROOMID'], $roomOrder) )
		{
			$dev['ROOMID'] = ROOM_SYSDEV_UNADDR;
		}
		
		//如果房间不可控制，则不显示
		if( !in_array( $dev['ROOMID'], $userRoom) )
		{
			continue;
		}
		$dev['INUSE'] = 1;
		if(DEV_STATUS_STOP == $dev['STATUS']  )
		{
			$dev['INUSE'] = 0;
		}
		$dev['ONLINE'] = true;
		if(DEV_STATUS_OFFLINE == $dev['STATUS']  )
		{
			$dev['ONLINE'] = false;
		}

		$dev['sub'] = $c->queryAll('ID,NAME,INUSE,CANDEL,SYSNAME,ICON','DEVID=? ORDER BY ATTRINDEX',
										array($dev['ID']));
		

		$devList['dev'][$dev['ROOMID']][] = $dev; 
	}

	foreach( $roomOrder as $order )
	{
		if( NULL == $devList['dev'][$order] )
		{
			unset($devList['dev'][$order]);
		}
	}
	

	return $devList;	
}

function addNumDev($name)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	$name = trim($name);
	if( NULL == $name )
	{
		return 'name is null';
	}
	
	$addr = array('m'=>'home','s'=>'sysend','f'=>'receiveHICCmd');
	$GLOBALS['dstpSoap']->setModule('home','end');
	$devid= $GLOBALS['dstpSoap']->getDevidFromAddr(serialize($addr), NULL);
	
	//查找一个系统里不存在的attrindex
	$c = new TableSql('homeattr','ID');
	$index  = 0;
	$attrid = INVALID_ID;
	do{
		$index = mt_rand(10000,mt_getrandmax())*(-1);
		$attrid = $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($devid,$index));
	}while( validID($attrid) );
	

	//往系统中增加一个可删除的数字设备
	$attr = array();
	$attr['NAME']      = $name;
	$attr['SYSNAME']   = 'num';
	$attr['ATTRINDEX'] = $index;
	$attr['CANDEL']    = 1;
	$attr['CFGINFO']   = serialize( array('min'=>-100,'max'=>100));
	$attr['ISR'] = 1;
	$attr['ISC'] = 1;
	$attr['ISS'] = 0;
	$GLOBALS['dstpSoap']->setModule('home','end');
	$GLOBALS['dstpSoap']->addDevAttList($devid,array($attr));
	return true;
}
//替换属性操作
function replaceAttr($oldid,$newid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	//更新属性信息
	$sysname = updateAttr($oldid,$newid);
	//更新设备信息
	updateDev($oldid,$newid);
	//更新设备组属性状态
	updateDevGroup($oldid,$newid);
	//针对不同的属性对细节进行处理
	$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
	$GLOBALS['dstpSoap']->setAttrType($sysname);
	$GLOBALS['dstpSoap']->replaceAttrNotice($oldid,$newid);	
	statusNotice('roomAttrMap');
	noticeAttrModi($oldid);
	noticeAttrModi($newid);
	return true;
}

/****************内部调用函数************************/
//更新属性信息
function updateAttr($oldid,$newid)
{
	$c = new TableSql('homeattr','ID');
	$result = $c->queryAll('ID,DEVID,ATTRINDEX,SYSNAME,CFGINFO,CANDEL,ISR,ISS,ISC,ATTRINT,ATTRFLOAT,ATTRSTR',"ID in ($oldid,$newid)");
	foreach($result as $key => $value) 
	{
		$attr = $value;
		$attr['ID'] = $value['ID'] == $oldid ? $newid : $oldid;
		$c->update($attr);
	}
	return $result[0]['SYSNAME'];
}
//更新设备信息
function updateDev($oldid,$newid)
{
	$c = new TableSql('homeattr','ID');
	$oldDevid = $c->queryValue('DEVID','ID=?',array($oldid));
	$newDevid = $c->queryValue('DEVID','ID=?',array($newid));
	$recordNum = $c->getRecordNum("DEVID in ($oldDevid,$newDevid)");
	if($recordNum == 2)
	{
		$c = new TableSql('homedev','ID');
		$result = $c->queryAll('ID,NAME,ROOMID',"ID in ($oldDevid,$newDevid)");
		foreach($result as $key => $value) 
		{
			$dev = $value;
			$dev['ID'] = $value['ID'] == $oldDevid ? $newDevid : $oldDevid;
			$c->update($dev);
		}
	}
}
//更新设备组信息
function updateDevGroup($oldid,$newid)
{
	$c = new TableSql('homeattr','ID');
	$oldDevid = $c->queryValue('DEVID','ID=?',array($oldid));
	$newDevid = $c->queryValue('DEVID','ID=?',array($newid));
	$c = new TableSql('smartdevgroupattr');
	$status = $c->queryAll('*',"ATTRID in ($newid,$oldid)");
	$dgid = $c->queryAllList('DGID',"ATTRID in ($newid,$oldid)");
	foreach ($status as $key => $value) 
	{	
		switch( $value['DGSTATUS'] )
		{
			case 0: //不设置组播组->设置为不设置组播组但还没得到确认
				$dgstatus = 4;
				break;
			case 2:	//设置了组播组且已回应->设置了组播组但还没回应
				$dgstatus = 1;
				break;
			default:
				break;
		}
		if( isset($dgstatus) )
		{
			$update['DEVID'] = $value['ATTRID'] == $newid ? $newDevid : $oldDevid;
			$update['DGSTATUS'] = $dgstatus;
			$c->update($update,null,'DGID=? and ATTRID=? and DGSTATUS=?',array($value['DGID'],$value['ATTRID'],$value['DGSTATUS']));			
		}
		//如果这两个设备不同时出现在该设备组中，则需要删除该设备
		if( array_count_values($dgid)[$value['DGID']] != 2 )
		{
			$other = array();
			$other['DGID'] = $value['DGID'];
			$other['ATTRID'] = $value['ATTRID'] == $newid ? $oldid : $newid;
			$other['DEVID'] = $other['ATTRID'] == $newid ? $newDevid : $oldDevid;
			$other['DGSTATUS'] = 3;
			$c->add($other);
		}
	}
	//更新维护设备组信息
	$GLOBALS['dstpSoap'] -> setModule('smart','devgroup');
	$GLOBALS['dstpSoap'] -> sysMaintence();
}
util::startSajax( array('getDevList','allowdev','stopDev','restartDev','delDev','setRoom','changeName','changeAttrName','startAttr','stopAttr','addNumDev','replaceAttr','setAttrAlias') );

?>