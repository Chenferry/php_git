<?php
//加载设备页面。get可能参数
//id:attr id

include_once('../../a/config/dstpCommonInclude.php');  
include_once('class.attr.inc.php');  

function getBjyyStatus($attrid)
{
	//如果页面获取当前状态的时候上报速度慢，则下发命令通知设备增加上报状态速度
	if( Cache::get('bjyystatus'.$attrid) != 'on')
	{
		$cmd = array('m'=>'report','value'=>1);
		$GLOBALS['dstpSoap']->setModule('devattr','attr');
		$GLOBALS['dstpSoap']->execAttr($attrid,$cmd);
	}
	Cache::set('bjyystatus'.$attrid,'on',10);
	return Cache::get('bjyys_'.$attrid);
}
//获得在线音乐列表
function onlineList($attrid,$cmd)
{
	$onlinelist = Cache::get('onlinelist_'.$attrid);
	if( $onlinelist == false )
	{
		$time = time();
		while( ( !$onlinelist && (time()-$time<5) ) )
		{
			$onlinelist = Cache::get('onlinelist_'.$attrid);
			sleep(1);
		}
	}
	return $onlinelist;
}
util::startSajax( array('getBjyyStatus','onlineList') );

?>