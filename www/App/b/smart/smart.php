<?php
//情景模式设置
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

//定时任务
function savePlan($id,$name,$op1,$plan)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	if( NULL == $name )
	{
		return HOME_NAME_NULL;
	}
	
	$attrList = array();
	
	$op2 = NULL;
	
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	return $GLOBALS['dstpSoap']->saveSmart($id,$name,$attrList,$plan,$op1,$op2,SMART_FROM_PLAN);	
}
//属性的定时任务
function saveAttrPlan($id,$attrid,$name,$value,$plan)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	if( NULL == $name )
	{
		return HOME_NAME_NULL;
	}
	
	$op1 = array();
	$op1['delay'] = 0;
	$op1['alarm'] = 0;
	$op1['group'] = array();
	$op1['group'][] = array( 'ATTRID'=>$attrid, 'ATTR'=>$value );

	$op2 = array();
	$op2['delay'] = 0;
	$op2['alarm'] = 0;
	$op2['group'] = array();
	
	$attrList = array();
	$attrList['sub']    = array( array('ID'=>$attrid) );
	
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	return $GLOBALS['dstpSoap']->saveSmart($id,$name,$attrList,$plan,$op1,$op2,SMART_FROM_ATTRPLAN);	
}
//联动模式
function saveSimpleSmart($id,$name,$attrList,$plan,$op1,$op2)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	if( NULL == $name )
	{
		return HOME_NAME_NULL;
	}
	
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	return $GLOBALS['dstpSoap']->saveSmart($id,$name,$attrList,$plan,$op1,$op2,SMART_FROM_REL);
}
function saveSmart($id,$name,$attrList,$plan,$op1,$op2)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}
	if( NULL == $name )
	{
		return HOME_NAME_NULL;
	}
		
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	return $GLOBALS['dstpSoap']->saveSmart($id,$name,$attrList,$plan,$op1,$op2,SMART_FROM_NOR);
}
function setSmartStatus($id,$status)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$c = new TableSql('smartsmart','ID'); 
	$info = array();
	$info['ID']    = intval($id);
	$info['INUSE'] = intval($status);
	if(!$info['INUSE'])
	{
		$info['INEXEC'] = 0;
	}
	$c->update($info);
	
	//如果启用，需要先判断是否已经可以触发重新触发
	if( $info['INUSE'] )
	{
		$GLOBALS['dstpSoap']->setModule('smart','smart');
		$r = $GLOBALS['dstpSoap']->checkSmartStatus($id);
		if( $r )
		{
			$GLOBALS['dstpSoap']->execSmart($id,1);
		}
	}
	
	return true;
}

function getSmartInfo($id=INVALID_ID)
{
	$r = array();
	$r['id']       = $id; 
	$r['info']     = NULL; //智能模式基本信息
	$r['delay']    = NULL; //满足条件后，延时多久执行
	$r['plan']     = NULL; //设置的时间条件数组
	$r['condList'] = NULL; //设置的条件数组
	$r['isFromGroup'] = false;     //设置的操作是否从情景模式引用还是直接设置
	$r['isFromGroup2']= false;     //设置的操作是否从情景模式引用还是直接设置
	$r['groupID']    = INVALID_ID; //智能模式对应的情景模式操作
	$r['groupList']  = NULL;       //用户已设置的所有情景模式
	$r['groupID2']   = INVALID_ID; //智能模式对应的情景模式操作
	$r['groupList2'] = NULL;       //用户已设置的所有情景模式	
	if ( validID($id) )
	{
		$c = new TableSql('smartsmart','ID'); 
		$r['info']      = $c->queryByID($id);
		$r['condList']  = unserialize($r['info']['QCOND']);
		$r['groupID']   = $r['info']['GROUPID'];
		$r['groupID2']  = $r['info']['GROUPID2'];
		$r['delay']     = $r['info']['DELAYS'];
		$r['delay2']    = $r['info']['DELAYS2'];
		$r['plan']      = unserialize($r['info']['PLANCFG']);
	}	
	//获取房间和设备名字信息
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$r['roomList'] = $GLOBALS['dstpSoap']->getRoomListShow();

	$c     = new TableSql('smartgroup','ID'); 
	$group = $c->queryByID($r['groupID']);
	$r['isFromGroup']  = intval($group['ISSHOW']);
	$group = $c->queryByID($r['groupID2']);
	$r['isFromGroup2'] = intval($group['ISSHOW']);

	if ( INVALID_ID == $r['groupID2'] )
	{
		$r['groupID2'] = -2; //页面要求两个ID一定要不一样
	}

	$r['groupList']    = $c->queryAll('ID,NAME','ISSHOW=1'); 

	$r['opArray']     = &$GLOBALS['opArray'];
	$r['filterArray'] = &$GLOBALS['filterArray'];

	return $r;
}
util::startSajax( array('getSmartInfo','savePlan','saveSmart','saveAttrPlan','saveSimpleSmart','setSmartStatus'));

?>