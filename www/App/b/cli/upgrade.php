<?php

@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);
	
include_once('/www/App/a/config/dstpCommonInfo.php');

$GLOBALS['dstpSoap']->setModule('local','upgrade');
$GLOBALS['dstpSoap']->upgradeRouter();
$ver = $GLOBALS['dstpSoap']->getHICVersion();
debug("fw:".$ver['fw']);
debug("db:".$ver['db']);
debug("version:".$ver['hic']);
die("ok");

?>