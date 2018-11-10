<?php
include_once ('../../a/config/dstpCommonInfo.php');

if ($_SERVER ['REMOTE_ADDR'] != '127.0.0.1')
	exit ( '0' );


`/etc/init.d/firewall restart`;
`/etc/init.d/network restart`;

include_once ('uci/uci.class.php');
$name = NULL;
$mac = NULL;
uci_base::getInfoByIP ( $_SERVER ['REMOTE_ADDR'], $mac, $name );
$GLOBALS['dstpSoap']->setModule('home','client');
$GLOBALS['dstpSoap']->initClientsACL($mac);

//查找所有手机的在线状态，重新设置

//更新天气状态

//$GLOBALS['dstpSoap']->setModule('local','dev');
//$GLOBALS['dstpSoap']->backupHICCfg();
exit('1');
?>