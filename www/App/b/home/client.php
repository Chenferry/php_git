<?php
//加载设备页面。get可能参数
include_once('../../a/config/dstpCommonInclude.php');  

//为了提高效率，应该允许一次性传入id列表
function allowClient($id,$period=0)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('home','client');
	return 	$GLOBALS['dstpSoap']->allowClient($id,$period);
}

function changeName($id,$name)
{
	$name = trim($name);
	if( NULL == $name )
	{
		return false;
	}
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
		
	$GLOBALS['dstpSoap']->setModule('home','client');
	return 	$GLOBALS['dstpSoap']->changeName($id,$name);
}


util::startSajax( array('allowClient','changeName'));

?>