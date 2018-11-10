<?php
@ini_set ( 'max_execution_time', 1 * 60 * 60 );
@ignore_user_abort ( true );

include_once ('../../a/config/dstpCommonInfo.php');
header("Content-type:text/html;charset=utf-8");

if( isset($_GET['opt'] ))
{
    $opt = $_GET['opt'];
    if ($opt == 'read') {
        $sysDate = `date`;
        $hwTime = `hwclock -r`;
        debug("系统时间:$sysDate");
        debug("硬件芯片时间:$hwTime");
    } else if ($opt == 'sync') {
        $hwTime = `hwclock -w`;
        sleep(1);
        // debug("设置硬件芯片时间成功");
        $sysDate = `date`;
        $hwTime = `hwclock -r`;
        debug("系统时间:$sysDate");
        debug("硬件芯片时间:$hwTime");
    } else {
        $sysDate = `date`;
        $hwTime = `hwclock -r`;
        debug("系统时间:$sysDate");
        debug("硬件芯片时间:$hwTime");
    }
} else {
    $sysDate = `date`;
    $hwTime = `hwclock -r`;
    debug("系统时间:$sysDate");
    debug("硬件芯片时间:$hwTime");
}
$mac = HICInfo::getPHYID();
$wifi = SSID::getSSID();
$wifi = $wifi['name'];
$dbm = SSID::getMinSignal();
debug("mac地址:$mac");
debug("WiFi:$wifi");
debug("信号强度:$dbm");
die();


?>