<?php
//清除所有添加记录
@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);
	
include_once('/www/App/a/config/dstpCommonInfo.php');

$c	= new TableSql('homedev','ID');
$initList = $c->queryAllList('ID', 'STATUS=?', array(DEV_STATUS_INIT));
foreach( $initList as $initid )
{
	$GLOBALS['dstpSoap']->setModule('home','end');
	$GLOBALS['dstpSoap']->del($initid);
}
die('del ok');				

?>