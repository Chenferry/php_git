<?php
//展示一键组合设置
include_once('../../a/config/dstpCommonInclude.php');  

//删除组合操作
function delSmart($id)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	return $GLOBALS['dstpSoap']->delSmart($id);
}

function startSmart($id)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$c = new TableSql('smartsmart','ID'); 
	$info = array();
	$info['ID']     = intval($id);
	$info['INUSE']  = 1;
	statusNotice('smart');
	return $c->update($info);
}

function stopSmart($id)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$c = new TableSql('smartsmart','ID'); 
	$info = array();
	$info['ID']     = intval($id);
	$info['INUSE']  = 0;
	$info['INEXEC'] = 0;
	statusNotice('smart');
	return $c->update($info);
}

function getSmartList()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	//判断是否存在indexnum，如果存在，则分页查找
	$c = new TableSql('smartsmart','ID'); 
	$group = $c->queryAll('ID,NAME,INEXEC,INUSE','SAVEFROM=?',array(SMART_FROM_NOR));
	return $group;
}

function getSmartPlanList($attrid,$from)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	if( !validID($attrid) ) //获取所有设备的定时列表
	{
		return getSmartAllPlan($from);
	}
	$c = new TableSql('smartdev'); 
	$idList = $c->queryAllList('SID','ATTRID=?',array($attrid));
	if(NULL == $idList)
	{
		return array();
	}
	$c = new TableSql('smartsmart'); 
	$idList = implode(',', $idList);
	return $c->queryAll('ID,NAME,INEXEC,INUSE',"ID IN ($idList) AND SAVEFROM=?",array($from));
}

function getSmartAllPlan($from)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$c = new TableSql('smartsmart'); 
	return $c->queryAll('ID,NAME,INEXEC,INUSE',"SAVEFROM=?",array($from));
}

function getRelSmartList()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$c = new TableSql('smartsmart'); 
	return $c->queryAll('ID,NAME,INEXEC,INUSE','SAVEFROM=?',array(SMART_FROM_REL));
}


util::startSajax( array('getRelSmartList','getSmartList','delSmart','startSmart','stopSmart') );

//设备定时;attrid=devid，from = plan
//设备联动:attrid=devid,from = simple
//定时任务:from = plan
//智能模式:空
//设备定时列表:attrid=-1,from = plan
//设备联动列表:attrid=-1,from = simple

?>