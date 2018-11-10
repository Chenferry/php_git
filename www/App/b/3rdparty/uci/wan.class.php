<?php
require_once dirname ( __FILE__ ) . '/' . 'network.class.php';
class wan extends network{
    private static $protocols=array('static','dhcp','ppp','pptp','pppoa','pppoe','3g');

    public static function protocols(){
        return self::$protocols;
    }

    public static function setProto($p){
        return `uci set network.wan.proto='$p'`.`uci commit network`;
    }

    /**
     * 
     * @return string error message
     */

    public static function setDHCP(){
        $result = `uci delete network.wan.ipaddr`;
        $result .=`uci delete network.wan.netmask`;
        $result .=`uci delete network.wan.gateway`;
        $result .=`uci delete network.wan.dns`;
        $result .=`uci set network.wan.proto='dhcp'`;
        $result .=`uci commit network`;

        return $result;
    }

    /**
     * 
     * @return string in protocol by set; 
     */
    public static function getType(){
        return `uci get network.wan.proto`;
    }

    public static function getInterface()
    {
        $value=`ifconfig | grep pppoe-wan -c`;
        if($value>0)
            return 'pppoe-wan';
        return 'eth0.2';
    }

    /**@return string wan internet IP
     * there can be multi wans
     */
    public static function getIP(){
        if ( DSTP_DEBUG )
        {
            return '127.0.0.1';
        }
        //return `ifconfig eth0.2 | grep "inet addr" | cut -f 2 -s -d":" | cut -f 1 -s -d" "`;
        $json=`ifstatus wan`;
        $jsonarr=json_decode($json,true);
        $wanIp = $jsonarr['ipv4-address'][0]['address'] ;
        if (empty($wanIp)) {
            $json=`ifstatus wan2`;
            $jsonarr=json_decode($json,true);
            $wanIp = $jsonarr['ipv4-address'][0]['address'] ;
        }
        return $wanIp;
    }


    /**@return string wan hw addr
     * there can be multi wans
     */
    public static function getHWAddr(){
        if ( DSTP_DEBUG )
        {
            return '00:11:22:33:'.mt_rand(10,99).':'.mt_rand(10,99);
        }

        $boardName = trim(`cat /tmp/sysinfo/board_name`);
        if ('MT7628' == $boardName) {
            //mt7628 wan port is eth0.1
            $wanPort = 'eth0.1';
        } else if ('MT7620' == $boardName) {
            $wanPort = 'eth0.2';
        } else if ('R615N' == $boardName) {
            $wifiDev = 'eth0';
        } else if ('r602n' == $boardName) {
            $wifiDev = 'eth0';
        } else {
            $wanPort = 'eth0.2';
        }

        $mac = `cat /sys/class/net/$wanPort/address`;
        $mac = strtoupper($mac);

        return $mac;
    }

    /**@return string gatway
     * there can be multi gatways
     */
    public static function getDefGyIP(){
        if ( DSTP_DEBUG )
        {
            return '127.0.0.1';
        }
        //return `ifconfig eth0.2 | grep "inet addr" | cut -f 2 -s -d":" | cut -f 1 -s -d" "`;
        $json=`ifstatus wan`;
        $jsonarr=json_decode($json,true);
        $gyIp = $jsonarr['route'][0]['nexthop'] ;
        if (empty($gyIp)) {
            $json=`ifstatus wan2`;
            $jsonarr=json_decode($json,true);
            $gyIp = $jsonarr['route'][0]['address'] ;
        }
        return $gyIp;
    }

    /**
     adjust if protocol is dhcp by wan internet IP address 
     */
    public static function isDHCP(){
        $parttern='/^192\.168\.([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])/';
        if(preg_match($parttern,self::getIP())){
            return true;
        }else{
            return false;
        }
    }

    public static function restart(){
        include_once 'procd/service.class.php';
        if(false==`ifup wan`)
        {
            sleep(10);
            return service::ProxyRestart();	 		
        }
        return false;
    }

    public static function restartNetwork() {
        return `/etc/init.d/network restart && /etc/init.d/proxy restart`;
    }

    //关闭有线连接的wan口
    public static function downWireWanPort() {
        return `ifdown wan`;
    }

    /**@return string wan info
     */
    public static function getWanIfaceInfo($iface){
        $json=`ifstatus $iface`;
        $jsonarr=json_decode($json,true);
        $wanIp = $jsonarr['ipv4-address'][0]['address'];
        if (empty($wanIp)) {
            return null;
        }

        $gyIp = $jsonarr['route'][0]['nexthop'];
        if (empty($gyIp)) {
            return null;
        }

        $dnsIp = $jsonarr['dns-server'][0][0];
        if (empty($dnsIp)) {
            return null;
        }

        return array('wanIp' => $wanIp, 'gyIp' => $gyIp, 'dnsIp' => $dnsIp);

    }

    public static function setStatic($ip,$mask='255.255.255.0',$route,$dns1='0.0.0.0',$dns2='0.0.0.0')
    {
        $result = `uci set network.wan.proto=static`;
        $result .=`uci set network.wan.ipaddr=$ip`;
        $result .=`uci set network.wan.netmask=$mask`;
        $result .=`uci set network.wan.gateway=$route`;
        //注意dns设置顺序主dns需要放在最后设置
        if ('0.0.0.0' != $dns2) {
            $result .=`uci add_list network.wan.dns=$dns2`;
        }
        if ('0.0.0.0' != $dns1) {
            $result .=`uci add_list network.wan.dns=$dns1`;
        }
        $result .=`uci commit network`;
        return $result;

    }

}
?>
