<?php
//展示一键组合设置
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

//直接触发执行
function execGroup($id)
{
	//为了避免重复触发，在3秒内不重复执行
	$r = Cache::get('execgrouplimit'.$id);
	if( false != $r )
	{
		return false;
	}
	Cache::set('execgrouplimit'.$id,time(),3);
	
	//进行权限检查
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],$id,'group');
	if( !$r )
	{
		return false;
	}
	
	$GLOBALS['dstpSoap']->setModule('smart','group');
	return $GLOBALS['dstpSoap']->execGroup($id);
}

//删除组合操作
function delGroup($id)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('smart','group');
	return $GLOBALS['dstpSoap']->delGroup($id);
}

function getGroupList()
{
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],INVALID_ID,'group');
	if( !$r )
	{
		return array();//没有执行情景模式的权限，直接返回空
	}	
	
	
	//判断是否存在indexnum，如果存在，则分页查找
	$c = new TableSql('smartgroup','ID'); 
	$offset=0;
	$num=-1;
	//if ( isset($_GET['mini']) )
	//{
	//	$num=4;	 
	//	$count = $c->getRecordNum('ISSHOW=1');
	//	
	//	$offset = intval(ceil($count/$num));
	//	$offset = $num*( (intval($_GET['mini'])-1) % $offset );
	//}
	$group = $c->queryAll('*', 'ISSHOW=1 ORDER BY ID', array(), $offset, $num);
	return $group;
}

function getPlanList()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$c = new TableSql('smartsmart'); 
	return $c->queryAll('ID,NAME,INEXEC,INUSE',"SAVEFROM=?",array(SMART_FROM_PLAN));

}
function getSmartList()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$c = new TableSql('smartsmart','ID'); 
	return $c->queryAll('ID,NAME,INEXEC,INUSE','SAVEFROM=?',array(SMART_FROM_NOR));
}

function getCtrlList()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$c = new TableSql('homeattr','ID'); 
	$c->join('homedev','homeattr.DEVID=homedev.ID');
	$ctrlList = $c->queryAll('homeattr.ID as ID,DEVID,homeattr.NAME as NAME,ROOMID,SYSNAME','ISC=1  ORDER BY SYSNAME,ROOMID');

	$c = new TableSql('homeattr','ID'); 
	$devgroupList = $c->queryAll('ID,NAME,SYSNAME,ICON','DEVID=? ORDER BY SYSNAME',array(ROOM_SYSDEV_DEVGROUP));
	foreach( $devgroupList as &$dg )
	{
		$dg['ROOMID'] = ROOM_SYSDEV_DEVGROUP;
		array_unshift($ctrlList,$dg);
	}
	
	$c           = new TableSql('smartgroup','ID');
	$groupList   = $c->queryAll('ID,NAME','ISSHOW=1'); 
	foreach( $groupList as &$dg )
	{
		$dg['ID']      = floatval($dg['ID']+MAX_INT_VALUE);
		$dg['ROOMID']  = ROOM_SYSDEV_SMARTGROUP;
		$dg['SYSNAME'] = 'qj';
		$dg['ICON']    = 'qj';
		array_unshift($ctrlList,$dg);
	}
	
	return $ctrlList;
}

function getCondList()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$roomList = $GLOBALS['dstpSoap']->getRoomListShow();

	$GLOBALS['dstpSoap']->setModule('smart','smart');
	$condList = $GLOBALS['dstpSoap']->getCondAttrList($roomList);
	return $condList;
	
}
//获得可以替换的属性类型列表
function getReplaceattrs()
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return array();
	}
	$c = new TableSql('homeattr','ID');
	$type = $c->queryAllList('SYSNAME');
	$type = array_unique($type);
	$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
	$result = array();
	foreach ($type as $key => $value) 
	{
		$GLOBALS['dstpSoap']->setAttrType($value);
		$cfg = $GLOBALS['dstpSoap']->getCfg();	
		if( isset($cfg['rep']) && $cfg['rep'] )
		{
			$result[] = $value;
		}
	}
	return $result;
}

function getModeList()
{
	$result = array();
	$result['group'] = getGroupList();

	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		$result['smart']     = array();
		$result['plan']      = array();
		$result['ctrlList']  = array();
		$result['condList']  = array();
		$result['opArray']   = array();
		$result['replaceattrs'] = array();
	}
	else
	{
		$result['smart'] = getSmartList();
		$result['plan']  = getPlanList();
		$result['ctrlList']  = getCtrlList();
		$result['condList']  = getCondList();
		$result['opArray']   = &$GLOBALS['opArray'];
		$result['replaceattrs'] = getReplaceattrs();
	}

	return $result;
}

util::startSajax( array('getModeList','execGroup','delGroup'));

?>