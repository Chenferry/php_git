<?php
//强制重建zigbee网络
@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);
	
include_once('/www/App/a/config/dstpCommonInfo.php');

//DEV_CMD_SYS_CLOSE_PERMITJOIN
//DEV_CMD_SYS_OPEN_PERMITJOIN
$cmd = DEV_CMD_SYS_OPEN_PERMITJOIN;

$GLOBALS['dstpSoap']->setModule('home','if');
$GLOBALS['dstpSoap']->sendDevSySMsg(0,$cmd);

$c    = new TableSql('homedev','ID');
$c->join('homeattr','homeattr.DEVID=homedev.ID');
$all  = $c->queryAllList('SUBHOST','SYSNAME=?',array('fj'));
foreach($all as $a)
{
	$GLOBALS['dstpSoap']->setModule('home','if');
	$GLOBALS['dstpSoap']->sendDevSySMsg($a,$cmd);
}

die('reset');

?>