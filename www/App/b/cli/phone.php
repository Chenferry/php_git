<?php 

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);

if ($argc < 3) {
    echo "params error\r\n";
    echo "usage: php-cli phone.php sms phone message\r\n";
    echo "usage: php-cli phone.php dial phone\r\n";
    return;
}

include_once('plannedTask/PlannedTask.php');
$planTask = new PlannedTask('delay','phone', 0);

if (trim($argv[1]) == 'sms') {
    $recvPhone = trim($argv[2]);
    $smsContent = trim($argv[3]);
    $planTask->sendSmsInfo($recvPhone,$smsContent); 
} else if (trim($argv[1]) == 'dial') {
    $recvPhone = trim($argv[2]);
    $planTask->dial($recvPhone); 
} else {
}

 ?>