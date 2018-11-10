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
		//ɾ�����䣬��ԭ���÷������豸��صĶ�ʱģʽ������ģʽ�з�������ֻ���δ����
		$GLOBALS['dstpSoap']->setModule('smart','smart');
		$GLOBALS['dstpSoap']->getSmartName($v['ID'],$smartname,'-1');
	}

	//ɾ���˷�����Ϣ��������Ҫɾ����ص�ӳ�����������
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
//�޸ķ�������
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
//�޸ķ���˳��
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