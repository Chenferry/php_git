<?php
$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);
include_once('uci/uci.class.php');  

function BcastServer()
{
    $GLOBALS['dstpSoap']->setModule('frame');
    $isReg  = $GLOBALS['dstpSoap']->isBindUser();
    if ($isReg != false) {
        exit();
    }

    $port = getRealPort(HIC_SERVER_BCAST);
    $socket = stream_socket_server("udp://255.255.255.255:$port", $errno, $errstr, STREAM_SERVER_BIND);
    if (!$socket) {
        die("$errstr ($errno)");
    }

    do {
        $pkt = stream_socket_recvfrom($socket, 1024, 0, $peer);
        // debug("recv msg:$pkt");
        $GLOBALS['dstpSoap']->setModule('frame');
        $isReg  = $GLOBALS['dstpSoap']->isBindUser();
        if ($isReg != false) {
            exit();
        }
        $wifi = SSID::getSSID();
        // debug("response $peer msg:".$wifi['name']);
        $ipaddr = explode(':',$peer);
        $ip = 'udp://' . $ipaddr[0] . ':' . $port;
        // debug("response $ip msg:".$wifi['name']);
        // stream_socket_sendto($socket, $wifi['name'], 0, $peer);
        $fp = stream_socket_client($ip, $errno, $errstr);
        if (!$fp) {
            debug("ERROR: $errno - $errstr");
        } else {
            fwrite($fp, $wifi['name']);
            fclose($fp);
        }
    } while ($pkt !== false);
}

BcastServer();

?>