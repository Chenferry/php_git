<?php

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);
include_once( dirname(__FILE__).'/proxyserver.php' );

//ra1: new station xx:xx:xx:xx:xx:xx
//ra1: del station xx:xx:xx:xx:xx:xx
$addFlag = 'new station';
$delFlag = 'del station';
while (true) {
    $line = trim(fgets(STDIN));
    if (strpos($line, $addFlag) !== false) {
        //new client come
        if (strpos($line, "ra1") !== false) {
            //show wifi
            $arrInfo = explode(" ", $line);
            $mac = $arrInfo[3];
            // debug("show mac:$mac");
            `php-cli -q /www/App/b/cli/newmac.php add show $mac`;
        } else {
            //hide wifi
            $arrInfo = explode(" ", $line);
            $mac = $arrInfo[3];
            // debug("hide mac:$mac");
            `php-cli -q /www/App/b/cli/newmac.php add hide $mac`;
        }
        
    } else if (strpos($line, $delFlag) !== false) {
        //client leave
        $arrInfo = explode(" ", $line);
        $mac = $arrInfo[3];
        // debug("del mac:$mac");
        `php-cli -q /www/App/b/cli/newmac.php del mac $mac`;
    } else {
        //unknow info
    }
}

?>