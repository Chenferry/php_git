<?php
//情景模式设置
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

function saveGroup($id,$name,$attrList)
{
	if( NULL == $name )
	{
		return 'name is null';
	}
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return $r;
	}

	$GLOBALS['dstpSoap']->setModule('smart','group');
	$r = $GLOBALS['dstpSoap']->saveGroup($id,$name,$attrList);

	//清空用户首页收藏缓存
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$GLOBALS['dstpSoap']->cleanFavoriteCache();
	
	statusNotice('dict');
	return $r;
}

function getGroupInfo($id,$show=true)
{
	$info     = NULL;
	$attrList = NULL;
	$ctrlList = NULL;
	if( validID($id))
	{
		$csmart = new TableSql('smartsmart','ID'); 
		$c      = new TableSql('smartgroup','ID'); 
		$info   = $c->queryByID($id);
		$c = new TableSql('smartgroupattr','ID'); 
		$attrList = $c->queryAll('*','GROUPID=? ORDER BY ID', array($id));
		$c1 = new TableSql('smartgroupattrid'); 
		foreach( $attrList as &$attr )
		{
			if( $attr['ATTRID'] < MAX_SEP_VALUE || $attr['ATTRID'] > MAX_INT_VALUE )
			{
				$attr['ATTRID'] = array($attr['ATTRID']);
			}
			else
			{
				$GLOBALS['dstpSoap']->setModule('smart','devgroup');
				$attr['ATTRID'] = $GLOBALS['dstpSoap']->queryDev($attr['ATTRID']-MAX_SEP_VALUE);
			}
			//把控制字转为页面显示格式
			$attr['ATTR']   = unserialize($attr['ATTR']);
			$attr['CONARR'] = unserialize($attr['CONARR']);
			$attr['PLAN']   = unserialize($attr['PLAN']);
		}
	}

	//获取房间和设备名字信息
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$roomList = $GLOBALS['dstpSoap']->getRoomListShow();

	$c           = new TableSql('smartgroup','ID');
	$groupList   = $c->queryAll('ID,NAME','ISSHOW=1'); 

	//单独挑出设备组
	$c = new TableSql('homeattr','ID'); 
	$devgroupList = $c->queryAll('ID,NAME,SYSNAME,ICON','DEVID=? ORDER BY SYSNAME',array(ROOM_SYSDEV_DEVGROUP));


	$c = new TableSql('homeattr','ID'); 
	$c->join('homedev','homeattr.DEVID=homedev.ID');
	$ctrlList = $c->queryAll('homeattr.ID as ID,DEVID,homeattr.NAME as NAME,ROOMID,SYSNAME','ISC=1  ORDER BY SYSNAME,ROOMID');

	foreach( $devgroupList as &$dg )
	{
		$dg['ROOMID'] = ROOM_SYSDEV_DEVGROUP;
		array_unshift($ctrlList,$dg);
	}
	foreach( $groupList as &$dg )
	{
		$dg['ID']      = floatval($dg['ID']+MAX_INT_VALUE);
		$dg['ROOMID']  = ROOM_SYSDEV_SMARTGROUP;
		$dg['SYSNAME'] = 'qj';
		$dg['ICON']    = 'qj';
		array_unshift($ctrlList,$dg);
	}
	$GLOBALS['dstpSoap']->setModule('smart','smart');
	$condList = $GLOBALS['dstpSoap']->getCondAttrList($roomList);
	
	$r = array();
	$r['id']   = $id;
	$r['show'] = $show;
	$r['info'] = $info;
	$r['roomList'] = $roomList;
	$r['attrList'] = $attrList;
	$r['ctrlList'] = $ctrlList;
	$r['condList'] = $condList;
	$r['groupList'] = $groupList;

	$r['opArray'] = $GLOBALS['opArray'];
	return $r;
}

util::startSajax( array('getGroupInfo','saveGroup'));

?>