<?php
require_once(dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php');
include_once(dirname(dirname(dirname(__FILE__))).'/a/config/dstpCfgCustom.php');
include_once('uci/uci.class.php');
function restartProc($proc)
{
    if( 'apevent' == $proc || 'ser2net' == $proc  )
    {
        `killall $proc`;
        usleep(100000);
        `/etc/init.d/$proc start`;
        return;
    } else if ('iwevent' == $proc) {
        `killall iw`;
        usleep(100000);
        `iw event | php-cli -q /www/App/b/cli/iwevent.php &`;
        return;
    }

    //先删除原来的，再
    $pidList = `pgrep -f '$proc'`;
    $pidList = explode("\n",$pidList);
    array_pop($pidList);
    foreach($pidList as $pid)
    {
        `kill -9 $pid`;
    }

    if( false === strpos($proc,'.php') )
    {
        `php-cli -q /www/App/b/cli/$proc.php >/dev/null 2>&1 &`;
    }
    else
    {
        `php-cli -q /www/App/b/cli/$proc >/dev/null 2>&1 &`;
    }	

    return;
}

/* procList中的进程名必须在init.d中有相应的服务名称存在 */
$procList  = array('apevent','ser2net');
if( APP_FJ == HIC_APP )
{
    $procList[] = 'extstub';
    $procList[] = 'extserver';
}
if( APP_ZJ == HIC_APP )
{
    $procList[] = 'proxystub';
    $procList[] = 'hicstatus';
    $procList[] = 'hicserver';
    $procList[] = 'hicdelay';
    $procList[] = 'bcastserver';



    if ( defined('HIC_SYS_POWER') ) 
    {
        for( $i = 0; $i < HIC_SYS_POWER; $i++ )
        {
            $procList[] = "extcommon.php $i";
        }
    }
}
if ( defined('HIC_SYS_LED') )
{
    $procList[] = 'sysled';
}   
if ( defined('HIC_SYS_IW_EVENT') )
{
    $procList[] = 'iwevent';
}   


if( APP_ZJ == HIC_APP )
{
    //检测进程是否正常运行定时写cache
    $r = Cache::get('proxystublive');
    if( false == $r )
    {
        restartProc('proxystub');
    }
    $r = Cache::get('hicstatuslive');
    if( false == $r )
    {
        restartProc('hicstatus');
    }
    // if( !defined('HIC_SYS_NOZIGBEE') )
    // {
    //     //检测进程是否正常运行定时写cache
    //     $r = Cache::get('hicserverlive');
    //     if( false == $r )
    //     {
    //         restartProc('hicserver');
    //     }
    // }
}


$pidMaxMem = '20000';
$pidMaxCpu = '60';
//检测内存
foreach($procList as &$proc)
{
    $pidList = `pgrep -f '$proc'`;
    $pidList = explode("\n",$pidList);
    array_pop($pidList);

    foreach($pidList as $pid)
    {
        $info = `ps -wl | grep $pid | grep '$proc' | grep -v 'grep'`;
        $info = trim($info);
        $mem  = `echo $info | awk '{print $5}'`;
        $mem  = trim($mem);
        if( $mem > $pidMaxMem )
        {
            //重启
            `kill -9 $pid`;
            restartProc($proc);
            //`/etc/init.d/$proc start`;
        }

        //检测状态，如果是T,Z，则强制删除重启
        $status  = `echo $info | awk '{print $1}'`;
        $status  = strtoupper(trim($status));
        if( 'T' == $status || 'Z' == $status)
        {
            `kill -9 $pid`;
            restartProc($proc);
            //`/etc/init.d/$proc start`;
        } else if ('DW' == $status) {
            //如果是D,DW，因为无法kill，所以也无法再拉起进程，只能重启系统
            // $tt = date('y-m-d h:i:s',time());
            // `echo "reboot due process Down1 at $tt" >> /www/crash`;
            `reboot -f`;
        }
    }

    array_pop($pidList);
    if( NULL == $pidList )
    {
        restartProc($proc);
        //`/etc/init.d/$proc start`;
        continue;
    }
}
//检测CPU占用率
foreach($procList as &$proc)
{
    $pidList = `top -n 1 | grep '$proc'`;
    $pidList = explode("\n",$pidList);
    array_pop($pidList);
    foreach($pidList as $pid)
    {
        $cpu  = `echo $pid | awk '{print $7}'`;
        $cpu  = intval(trim($cpu)); 
        if( $cpu > $pidMaxCpu )
        {
            $id  = `echo $pid | awk '{print $1}'`;
            $id  = trim($id); 
            `kill -9 $id`;			
            restartProc($proc);
            //`/etc/init.d/$proc start`;
        }
    }
}
//监控PHP-fpm避免挂死

//检测php相关进程挂死
$status = `ps | grep php | awk '{print $4}'`;
$status  = strtoupper(trim($status));
$statusList = explode("\n",$status);
foreach ($statusList as $key => $value) {
    if ('DW' == $value) {
        //如果是DW,D，因为无法kill，所以也无法再拉起进程，只能重启系统
        // $tt = date('y-m-d h:i:s',time());
        // `echo "reboot due process Down2 at $tt" >> /www/crash`;
        `reboot -f`;
    }
}

function GetHttpStatusCode($url)
{ 
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_URL,$url);//获取内容url 
    curl_setopt($curl,CURLOPT_HEADER,1);//获取http头信息 
    curl_setopt($curl,CURLOPT_NOBODY,1);//不返回html的body信息 
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);//返回数据流，不直接输出 
    curl_setopt($curl,CURLOPT_TIMEOUT,1); //超时时长，单位秒 
    curl_exec($curl);
    $rtn= curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);
    return  $rtn;
}

$url = 'http://'.a_jia_sx.'/App/a/config/dbConfig.php';

if( GetHttpStatusCode($url) != 200 )
{
    `killall php-cgi`;
    usleep(100000);
    `/etc/init.d/lighttpd restart`;
}

//监控PHP-CGI避免挂死
$pidMaxMem = 100000;
$pidMaxCpu = 90;

$pidList = `top -n 1 | grep php-cgi`;
$pidList = explode("\n",$pidList);
array_pop($pidList);
foreach( $pidList as $pid )
{
    $mem  = `echo $pid | awk '{print $5}'`;
    $mem  = intval(trim($mem)); 
    if( $mem > $pidMaxMem )
    {
        //重启
        $id  = `echo $pid | awk '{print $1}'`;
        $id  = trim($id); 
        `kill -9 $id`;
    }
    $cpu = `echo $pid | awk '{print $7}'`;
    $cpu = intval(trim($cpu));
    if( $cpu > $pidMaxCpu )
    {
        //重启
        $id  = `echo $pid | awk '{print $1}'`;
        $id  = trim($id); 
        `kill -9 $id`;
    }
}

//总内存占用不能太大，否则干掉所有业务进程
$sysMaxMem = 105000;
if ( defined('HIC_SYS_POWER') ) 
{//每一个进程多分配100M内存
    $sysMaxMem = $sysMaxMem + HIC_SYS_POWER*100*1024;
}

for ($i=0; $i < 3; $i++) { 
    $mem = `free | grep Mem`;
    $mem = trim($mem);
    $mem  = `echo $mem | awk '{print $3}'`;
    $mem  = intval(trim($mem)); 
    if( $mem > $sysMaxMem)
    {
        //处理业务时有可能峰值超过定义的可使用内存，需要尝试3次，每次都超过则判定为超过可使用内存
        sleep(1);
    }
    else
    {
        break;
    }
}

if (3 <= $i) {
    //清除系统缓存
    `sync && echo 1 > /proc/sys/vm/drop_caches`;

    `killall php-cgi`;
    `/etc/init.d/lighttpd restart`;

    `killall php-cli`;//等下一次monitor启动了
    //foreach( $procList as $proc )
    //{
    //	if( 'apevent' == $proc || 'ser2net' == $proc  )
    //	{
    //		continue;
    //	}
    //	restartProc($proc);
    //}
}

//现在会出现有种情况，比如proxystub进程，用ps命令显示不出来，也不正常工作
//但手动拉起该进程时，又提示出错，说端口已经被占用
//通过试验，killall php-cli后，该进程能再被拉起
//当进程运行到这儿时，理论上几个进程应该都要能检测到
//如果检测不到，就认为出现了前述错误，直接删掉所有等下一次monitor重拉
//$checkList  = array('proxy','hicstatus','hicserver');
foreach($procList as &$checkproc)
{
    if( 'apevent' == $checkproc || 'ser2net' == $checkproc || 'bcastserver' == $checkproc )
    {
        // 大主机或已经注册的主机有可能没有这三个进程,无需检查
        continue;
    }
    $pidList = `pgrep -f '$checkproc'`;
    $pidList = explode("\n",$pidList);
    array_pop($pidList);
    array_pop($pidList);
    if( NULL == $pidList )
    {
        `killall php-cli`;
    }
}

function setNetCfgAccordPortLinkSt()
{
    /*检测分机是否已经连接上外网，如果没有则先设置一个静态的ip地址,这样才能通过无线访问分机系统,
    注意这个时候有线网口必须断开，只能通过无线访问*/
    //有线连接方式lan口获得的ip,分机的时所有interfaces都同在一个br-lan下	
    $wan1Ip = uci_base::getInterfaceIP('lan');
    //有线wan口是否link up
    $boardName = trim(`cat /tmp/sysinfo/board_name`);
    if ('MT7628' == $boardName) {
        $cmdResStr = `switch reg r 0x80`;
        $portLinkSt = hexdec(substr($cmdResStr, 33));
        if ($portLinkSt & (1 << 25) ) {
            //mt7628 有线wan口用的是端口0,根据datasheet获取端口连接状态
            $wan1LinkSt = 1;
        } else {
            $wan1LinkSt = 0;
        }

    } else {
        $cmdResStr = `switch reg r 0x3408`;
        $wan1LinkSt = intval(substr($cmdResStr, 35));
    }

    //无线连接方式wan2口获得的ip	
    $wan2Ip = uci_base::getInterfaceIP('wan2');
    //TODO 无线中继是否还处于连接状态 iwpriv apcli0 show connStatus DBGPRINT注释掉需要改驱动

    $staticIp = '192.168.93.1';
    $ip = uci_base::getInterfaceIP('lan');
    $mode = network::getBridgeIpMode();

    if ($wan1LinkSt != 0) {
        if ((!empty($wan1Ip) && ($wan1Ip != '0.0.0.0'))) {
            if ($mode != 'dhcp') {
                //分机只能是dhcp无论是有线还是无线中继方式
                dhcp::setBridgeDhcpMode('off');
                network::setBridgeIpMode('dhcp');
                network::restart();
            }
        } else {
            //如果wan口link up但是获取不到ip，则需要设置静态ip查看吗
        }
    } else {
        if ($mode != 'static') {
            dhcp::setBridgeDhcpMode('on');
            network::setBridgeIpMode('static');
            network::restart();
        }
    }
}

function reConnZhujiWhenErr()
{
    $cmdResStr = `netstat -antp|grep 2887 | wc -l`;
    $errTimes = intval($cmdResStr);
    if ($errTimes >= 8) {
        `killall php-cli`;
        `sleep 1`;
        `php-cli -q /www/App/b/cli/monitor.php >/dev/null 2>&1 &`;
    }
}

if( APP_FJ == HIC_APP )
{
    // setNetCfgAccordPortLinkSt();
    reConnZhujiWhenErr();
} else {
    if (defined('HIC_SYS_HAVE4G') && (true == HIC_SYS_HAVE4G)) {
        changeNetPriority();
    }
}

function checkNetConnOK($url,$timeout)
{
    $retStr = `ping -c 1 -s 1 -w $timeout $url | grep packet`;
    if (!empty($retStr) && (false !== strpos($retStr, '1 packets received'))) {
        return true;
    } else {
        return false;
    }
}

function changeNetPriority()
{
    $timeout = 3;
    $testUrl = 'www.baidu.com';
    for ($i=0; $i < 3; $i++) {
        //尝试3次，如果都不行那说明网络不通 
        $isWanConn = checkNetConnOK($testUrl,$timeout);
        if ($isWanConn) {
            break;
        }
    }

    if ($i >= 3 && $isWanConn == false) {
        //重启所有网络
        $res = `ifup wan`;
        $res .= `ifup wan2`;
        $res .= `ifup ppp`;
        $res .= `sleep 3`;
    }

    //有线连接方式wan口信息
    $wireWanInfo = wan::getWanIfaceInfo('wan');
    // $wireWanPri  = (int)(trim(`uci get setting.sysinfo.wireNetLevel`));
    $wireWanPri  = 1;

    //无线连接方式wan2口信息 
    $wifiWanInfo = wan::getWanIfaceInfo('wan2');
    // $wifiWanPri  = (int)(trim(`uci get setting.sysinfo.wifiNetLevel`));
    $wifiWanPri  = 2;
    // 3/4G连接方式ppp口信息   
    $simWanInfo = wan::getWanIfaceInfo('ppp');
    // $simWanPri  = (int)(trim(`uci get setting.sysinfo.simNetLevel`));
    $simWanPri  = 3;

    //获取当前默认网关
    $curGyIp = '0.0.0.0';
    $routeStr = trim(`ip route show |grep default`);
    if (!empty($routeStr)) {
        $routeArray = explode(' ', $routeStr);
        $curGyIp = trim($routeArray[2]);
    }

    /*
     * 有线wan口没有有效的IP地址，则临时设置来lan口，以便通过有线进入ssh/web进行设置
     */
    $boardName = trim(`cat /tmp/sysinfo/board_name`);
    if ('MT7628' == $boardName) {
        /*由于硬件设计的原因，只有单口的7628需要这样设置*/
        if (empty($wireWanInfo)) {
            `brctl addif br-lan eth0.1`;
        } else {
            `brctl delif br-lan eth0.1`;
        }
    }

    /*
     * 判断网络优先级，level值越小优先级越高，基于3/4G网络来对比，然后进行优先级设置
     */  
    $netPri = array($simWanPri, $wifiWanPri, $wireWanPri);
    $netPri = sort($netPri);
    if ($netPri[0] == $simWanPri) {
        //sim 优先级最高
        if (!empty($simWanInfo)) {
            //SIM网络有效则设置默认网关地址为SIM的
            $gyIp = $simWanInfo['gyIp'];
            if ($curGyIp != $gyIp) {
                `ip route del default`;
                `ip route add default via $gyIp`;
            }
        } else if ($netPri[1] == $wifiWanPri) {
            //wifi 优先级次之
            if (!empty($wifiWanInfo)) {
                //有效则设置默认路由的wifi网络
                $gyIp = $wifiWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            } else if (!empty($wireWanInfo)) {
                //有线网络优先级最低且有效设置默认路由的有线网络
                $gyIp = $wireWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            }
        } else {
            //有线网络优先级次之
            if (!empty($wireWanInfo)) {
                //设置默认路由的有线网络
                $gyIp = $wireWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            } else if (!empty($wifiWanInfo)) {
                //设置默认路由的wifi网络
                $gyIp = $wifiWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            }
        }
    } else if ($netPri[0] == $wifiWanPri) {
        //无线网络优先级最高
        if (!empty($wifiWanInfo)) {
            //设置默认路由的wifi网络
            $gyIp = $wifiWanInfo['gyIp'];
            if ($curGyIp != $gyIp) {
                `ip route del default`;
                `ip route add default via $gyIp`;
            }
        } else if ($netPri[1] == $simWanPri) {
            //sim网络优先级次之
            if (!empty($simWanInfo)) {
                //SIM网络有效则设置默认网关地址为SIM的
                $gyIp = $simWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            } else if (!empty($wireWanInfo)) {
                //有线最低
                $gyIp = $wireWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            }
        } else {
            //有线网络优先级次之
            if (!empty($wireWanInfo)) {
                //设置默认路由的有线网络
                $gyIp = $wireWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            } else if (!empty($simWanInfo)) {
                //设置默认路由的wifi网络
                $gyIp = $simWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            }
        }
    } else {
        //有线网络优先级最高
        if (!empty($wireWanInfo)) {
            //设置默认路由的有线网络
            $gyIp = $wireWanInfo['gyIp'];
            if ($curGyIp != $gyIp) {
                `ip route del default`;
                `ip route add default via $gyIp`;
            }
        } else if ($netPri[1] == $simWanPri) {
            //sim优先级次之
            if (!empty($simWanInfo)) {
                //SIM网络有效则设置默认网关地址为SIM的
                $gyIp = $simWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            } else if (!empty($wifiWanInfo)) {
                //设置默认路由的wifi网络
                $gyIp = $wifiWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            }
        } else {
            //wifi优先级次之
            if (!empty($wifiWanInfo)) {
                //设置默认路由的wifi网络
                $gyIp = $wifiWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            } else if (!empty($simWanInfo)) {
                //SIM网络有效则设置默认网关地址为SIM的
                $gyIp = $simWanInfo['gyIp'];
                if ($curGyIp != $gyIp) {
                    `ip route del default`;
                    `ip route add default via $gyIp`;
                }
            }
        }
    }
}


?>
