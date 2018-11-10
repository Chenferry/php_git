<?php
//加载设备页面。get可能参数
//id:attr id

include_once('../../a/config/dstpCommonInclude.php');  
include_once('class.attr.inc.php');  

function execAttr($id,$cmd)
{
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],$id,'attr');
	if( !$r )
	{
		return false;
	}	
	$GLOBALS['dstpSoap']->setModule('devattr','attr');
	return $GLOBALS['dstpSoap']->execAttr($id,$cmd);
}
function delAttr($id)
{
	$GLOBALS['dstpSoap']->setModule('home','end');
	return $GLOBALS['dstpSoap']->delAttr($id);
}
function getSingleAttr($id)
{
	$c = new TableSql('homeattr','ID');
	$attr = $c->query('*','ID=?',array($id));
	$info = attrType::getAttrShow($attr,$id,true);
	unset($attr['ATTRINDEX']);
	unset($attr['CFGINFO']);
	unset($attr['VF']);
	unset($attr['CF']);
	unset($attr['ATTRINT']);
	unset($attr['ATTRFLOAT']);
	unset($attr['ATTRSTR']);
	unset($attr['ATTRSET']);
	unset($attr['SENDATTR']);
	if( NULL == $attr['ICON'] )
	{
		$attr['ICON'] = $attr['SYSNAME'];
	}
	$info['attr'] = $attr;
	return $info;
}

//获取合并多个属性的信息，传进来的是主属性的ID
function getAttr($id)
{
	$r = getSingleAttr($id);
	$c   = new TableSql('homeattrlayout','ID');
	$idList   = $c->queryAllList('ATTRID','MAINID=?',array($id));
	if( NULL != $idList )
	{
		$r['sub'] = array();
		foreach($idList as $aid )
		{
			$r['sub'][$aid] = getSingleAttr($aid);
		}	
	}
	return $r;
}

util::startSajax( array('execAttr','delAttr','getAttr'));


?>