<?php
include_once('../../a/config/dstpCommonInclude.php');  

function addRoom($name)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	if( NULL == trim($name) )
	{
		return false;
	}
	Cache::del('roomList');
	$info = array();
	$info['NAME'] = trim($name);
	$c = new TableSql('homeroom','ID');
	$index = $c->queryValue('ROOMINDEX','NAME IS NOT NULL ORDER BY ROOMINDEX DESC');
	$info['ROOMINDEX'] = $index+1;
	$r = $c->add($info);
	statusNotice('dict');
	return $r;
}

function delRoom($id)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$c = new TableSql('homedev','ID');
	$c->join('homeattr','homeattr.DEVID=homedev.ID');
	$devid = $c->queryAll('homedev.ID','homedev.ROOMID=?',array(intval($id)));
	foreach ( $devid as $v ) 
	{
		//删除房间，和原来该房间下设备相关的定时模式和联动模式中房间的名字换成未分区
		$GLOBALS['dstpSoap']->setModule('smart','smart');
		$GLOBALS['dstpSoap']->getSmartName($v['ID'],$smartname,'-1');
	}

	//删除了房间信息，可能需要删除相关的映射表重新生成
	Cache::del('roomList');
	Cache::del('roomAttrMap');
	statusNotice('roomAttrMap');

	$c = new TableSql('homeroom','ID');
	$r = $c->delByID($id);
	$c = new TableSql('homedev','ID');
	$info['ROOMID'] = -1;
	$c->update($info,NULL,'ROOMID=?',array($id)); 	
	statusNotice('dict');
	return $r;
}
//修改房间名字
function changeRoomName($id,$name)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	if( NULL == trim($name) )
	{
		return false;
	}
	$c = new TableSql('homeroom','ID');
	$info = array();
	$info['NAME'] = trim($name);
	$result = $c->update($info,NULL,'ID=?',array($id));
	Cache::del('roomList');
	return $result;
}
//修改房间顺序
function changeRoomOrder($list)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$c = new TableSql('homeroom','ID');
	foreach ($list as $key => $value) 
	{
		$c->update(array('ROOMINDEX'=>$key+1),null,'ID=?',array($value));
	}
	statusNotice('status');
}

util::startSajax( array('addRoom','delRoom','changeRoomName','changeRoomOrder') );

	
?>