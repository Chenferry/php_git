<?php
//修改用户权限
include_once('../../a/config/dstpCommonInclude.php');  

//设置用户权限信息
//{ 
//   type:10(管理员)/20(系统设置)/30(普通用户),
//  //当type为30时info里面会有type和room的值
//   info:{
//      type：1（此用户能控制的房间）/ 2(不能控制的房间),
//      room: [1,2,...]//房间id
//  }
//}

function updateAccess($access,$username='')
{
	include_once('a/commonLang.php');
	//当前用户如果不是管理员，则不能修改
	$c = new TableSql('homeaccess');
	$type = $c->queryValue('USERTYPE','USERID=?',array($GLOBALS['curUserID']));
	if( (USER_TYPE_SYSTEM==$type) || ( USER_TYPE_COMMON == $type ))
	{
		return USER_ACCESS_ERR;
	}
	if( NULL == $type ) //用户没设置，则获取默认权限
	{
		$type = $c->queryValue('USERTYPE','USERID=?',array(-1));
		if( (USER_TYPE_SYSTEM==$type) || ( USER_TYPE_COMMON == $type ))
		{
			return USER_ACCESS_ERR;
		}
	}
	
	$userid = -1;//默认权限
	if( NULL != $username )
	{
		$userid = getUserID($username);
		if( !validID($userid) )
		{
			return 'username fail';
		}
	}


    $GLOBALS['dstpSoap'] -> setModule('setting','setting');
	return $GLOBALS['dstpSoap'] -> updateAccess($userid,$access);
}

//得到用户权限
function getAccess($username='')
{
	$GLOBALS['dstpSoap'] -> setModule('setting','setting');
	return $GLOBALS['dstpSoap']->getUserAccess(getUserID($username));
}

util::startSajax( array('updateAccess','getAccess') );

?>