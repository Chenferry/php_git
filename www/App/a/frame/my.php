<?php
include_once('../../a/config/dstpCommonInclude.php');
//该文件后面会调用C的接口，可能导致整个session挂住
session_write_close();

function getHICList()
{
	$r = array();
	$GLOBALS['dstpSoap']->setModule('app','hic');
	$r['list']   = $GLOBALS['dstpSoap']->getUserBindHIC($GLOBALS['curUserID']);
	$r['curhic'] = $GLOBALS['curHICID'];
	return $r;
}

function getHICCfg()
{
	$logoinfo = NULl;
	if ( 'c' == HIC_LOCAL )
	{
		$GLOBALS['dstpSoap']->setModule('app','init');
		$logoinfo = $GLOBALS['dstpSoap']->getLogoInfo(HICInfo::getPHYID());	
	}
	else
	{
		$logoinfo = &$GLOBALS['hicCfg'];
	}

	if( NULL == $logoinfo )
	{
		include_once('a/commonLang.php'); 
		$logoinfo = array('IDFLAG'=>'', 'NAME'=>HIC_NAME);
	}
	if( 'NULL' == $logoinfo['HELPNAME'] || NULL == $logoinfo['HELPNAME'] )
	{
		unset($logoinfo['HELPNAME']);
		unset($logoinfo['HICHELP']);
	}
	unset($logoinfo['HICDOMAIN']);
	unset($logoinfo['IDFLAG']);
	return $logoinfo;
}

function getNameInfo()
{
	$hicList  = getHICList();
	$hicList  = $hicList['list'];
	$logoinfo = getHICCfg();

	$r['sysName']   =  $logoinfo['NAME'];
	$r['userName']  =  $GLOBALS['curUserName'];
	$r['hicName']   =  'null';
	$r['hicCfg']    =  $logoinfo;

	foreach ( $hicList as &$hic )
	{
		if( $hic['ID'] == $GLOBALS['curHICID'] )
		{
			$r['hicName'] =  $hic['NAME'];
		}
	}
	return $r;
}
function getBindUserList()
{
	if ( CLU_CLOUD == DSTP_CLU ) //c和i需要过滤不相干用户信息
	{
		$c = new TableSql('hic_hicbind');
		$idlist = $c->queryAllList('USERID','HICID=?',array(HICInfo::getHICID()));

		$c = new TableSql('hic_user','ID');
		$user = array();
		if ( NULL != $idlist )
		{
			$idlist = implode(',',$idlist);
			$user =  $c->queryAllList('NAME',"ID IN ($idlist)");	
		}
	}
	else
	{
		$c = new TableSql('hic_user','ID');
		$user =  $c->queryAllList('NAME');
	}

	return $user;
}

util::startSajax( array('getNameInfo','getHICList','getBindUserList'));

?>