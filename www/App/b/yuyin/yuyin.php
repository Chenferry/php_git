<?php
//语音控制入口文件
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

function start($pos)
{
	if( NULL == $pos )
	{
		return 'pos is null';
	}
	$GLOBALS['dstpSoap']->setModule('yuyin');
	return $GLOBALS['dstpSoap']->start($pos);
}
function getDict()
{
	$ret = array();
	$ret['userword'] = array();
	$ret['userword']['name'] ='default';
	
	$GLOBALS['dstpSoap']->setModule('yuyin','dict');
	$ret['userword']['words'] = $GLOBALS['dstpSoap']->genYuyinDict();
	return $ret;
}

function yuyin($yuyin)
{
	if( NULL == $yuyin )
	{
		return array('name is null');
	}
	$GLOBALS['dstpSoap']->setModule('yuyin');
	return $GLOBALS['dstpSoap']->yuyin($yuyin,NULL,$GLOBALS['curUserID']);
}
util::startSajax( array('yuyin','start','getDict'));

?>