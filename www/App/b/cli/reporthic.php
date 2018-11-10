<?php
//强制重建zigbee网络
@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);
	
include_once('/www/App/a/config/dstpCommonInfo.php');

$GLOBALS['dstpSoap']->setModule('delay','syslocal');
$GLOBALS['dstpSoap']->reportHICStatus();

die('report');

?>