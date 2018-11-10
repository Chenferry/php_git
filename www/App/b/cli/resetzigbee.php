<?php
//强制重建zigbee网络
@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);
	
include_once('/www/App/a/config/dstpCommonInfo.php');


Cache::set('zigbeeresetflag', true, 30);

$GLOBALS['dstpSoap']->setModule('home','if');
$GLOBALS['dstpSoap']->sendDevSySMsg(0,DEV_CMD_SYS_HICID,array('hic'=>0,'sub'=>0));

$c    = new TableSql('homedev','ID');
$c->join('homeattr','homeattr.DEVID=homedev.ID');
$all  = $c->queryAllList('SUBHOST','SYSNAME=?',array('fj'));
foreach($all as $a)
{
	$GLOBALS['dstpSoap']->setModule('home','if');
	$GLOBALS['dstpSoap']->sendDevSySMsg($a,DEV_CMD_SYS_HICID,array('hic'=>0,'sub'=>0));
}

die('reset');

?>