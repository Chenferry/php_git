<?php
/**
 * 百度云推送API
 * SendWifiNotice()---发送手机上线通知
 * pushMessageDevs($devs,$title,$description)--推送通知到所有设备
 * pushMessageDev($dev,$title,$description)---推送通知到一个设备
 *
 * @package push
 * @author  ldh
 */

class pushInterFace
{
    // const IOSPUSH_KEY = 'wedone123@mac';
    static $package = 'com.test.smarthic.app';
    static $IosPushKey;
    static $BrandAKSK;
    static function sendNotice($info,$hicid=NULL,$url=NULL)
    {

        $GLOBALS['dstpSoap']->setModule('app','terminal');
        $devs=$GLOBALS['dstpSoap']->getHomeTerminals($hicid);//获得所有主人设备
        self::pushMessageDevs($devs, $info['TITLE'],$info['DESCRIPTION'],$url );//推送通知
        return ;
    }

    //从服务器上获取ios的pem验证文件，并存到pemPath目录下
    private static function getIOSKey($company,$voip)
    {
        //如果是c，就直接返回
        if('c'==HIC_LOCAL){
            self::$IosPushKey=BAEConf::$ios['pushkey'];
            return ;
        }
        $path=dirname(dirname(__FILE__))."/data/iospush";

        //文件夹不存在的话就创建一下
        if (!is_dir($path)){
            mkdir ($path,0777,true);
        }
        if(isset($voip))
        {
            $pemPath="$path/$company-dis-voip.pem";
        }
        else
        {
            $pemPath="$path/$company-dis.pem";
        }
        $keyPath="$path/$company-pushKey";


        if(file_exists($pemPath) && file_exists($keyPath))
        {
            //如果本地已经有了,直接读取本地文件
            self::$IosPushKey=file_get_contents($keyPath);
        }
        else
        {
            //如果没有，则去服务器获取文件并存到本地
            $GLOBALS['dstpSoap']->setModule('app','pushinfo');
            $res = $GLOBALS['dstpSoap']->getIOSKey('huidang');
            $res=json_decode($res,true);
            file_put_contents($pemPath,$res['pem']);
            file_put_contents($keyPath,$res['pushKey']);
            self::$IosPushKey=$res['pushKey'];
        }
        return ;
    }



    private static function getBrandKey($company,$brands)
    {
        $GLOBALS['dstpSoap']->setModule('app','pushinfo');
        self::$BrandAKSK = $GLOBALS['dstpSoap']->getBrandAKSK($company,$brands);
    }



    /**
     * 推送消息给多个设备
     * $devs 设备数组
     */
    static function pushMessageDevs($devs,$title,$description,$url=NULL)
    {
        $count=count($devs);
        for($i=0;$i<$count;$i++){
            $devs[$i]['BRAND']=$GLOBALS['brandList'][$devs[$i]['BRAND']];
            if('OTHERS'!=$devs[$i]['BRAND']){
                $brands[]=$devs[$i]['BRAND'];
            }
            if(trim($devs[$i]['COMPANY'])!=''){
                $company=trim($devs[$i]['COMPANY']);
            }
        }

        self::getBrandKey($company,$brands);
        $iosarray = array();
        foreach($devs as $dev)
        {
            switch( $dev['BRAND'] )
            {
                case 'IPHONE':
                    $iosarray[] = $dev;
                    break;
                case 'XIAOMI':
                case 'HUAWEI':
                case 'OPPO':
                case 'VIVO':
                case 'MEIZU':
                    self::pushBrandMessage($dev,$title,$description,$url);
                    break;
                case 'OTHERS':
                default:
                    $devs['BRAND']='XIAOMI';
                    self::pushBrandMessage($dev,$title,$description,$url);
                    break;
            }
            
        }

        if( count($iosarray) )
        {
            self::pushMsgToIosDevice($iosarray,$title,$description,$url);
        }
        return;
    }
    
    //给指定的用户发送打开摄像头连接
    private static function getRtspCallingURL($url,$userid,$hicid=NULL)
    {
        $hicid  = HICInfo::getHICID($hicid);
        $c = new TableSql('hic_frameautologin');
        $token  = $c->queryValue('LOGINFLAG','USERID=? AND HICID=?',array($userid,$hicid));
        if( NULL == $token )
        {
            return NULL;
        }
        $burl   = HICInfo::getBHost($hicid);
        $t      = time();
        //http://jia.mn/UI/indexstatus.html?rtspid=xxx&hictoken=xxx&burl=xxx'
        return "$url&hictoken=$token&burl=$burl&time=$t";
    }


    static function pushBrandMessage($dev,$title,$description,$url=null)
    {   
        $brand=$dev['BRAND'];
        //SVN自动将XIAOMI.php改为xiaomi.php，所以只能用函数转一下
        require_once(strtolower("push/$brand/$brand.php"));
        $brand::$package=self::$package;
        $brand::$aksk=self::$BrandAKSK[$brand];
        if(NULL != $url){
            $rtspurl = self::getRtspCallingURL($url,$dev['USERID']);
            if( NULL == $rtspurl )
            {
                return;
            }
        }
      
        $res=$brand::send($dev['CHANNELID'],$title,$description,$rtspurl);     
    }

    //确认推送是否送达
    public static function confirm($id)
    {

        //查询推送是否到达
        $GLOBALS['dstpSoap']->setModule('app','push');
        $res = $GLOBALS['dstpSoap']->repeat($id);
        //推送已经到达
        if($res===NULL)
        {
            return ;
        }   

        //推送超时未送达，再推一次
        $dev=json_decode($res["DEV"],true);
        $dev["RETRY"]=$res["RETRY"];
        $dev["REPUSHID"]=$id;
        $devs[]=$dev;
        self::pushMessageDevs($devs, $res["TITLE"],$res['DESCRIPTION'],$res["URL"] );//推送通知
    }


    //如果是第一次推送就发给服务器记下来，并添加定时任务
    private static function record($dev,$title,$description,$url)
    {
        if(isset($dev["RETRY"]))
        {
            //已经是重新发送的推送就不需要再创建一条记录了，直接取现有ID
            $id=$dev["REPUSHID"];
        }
        else
        {
            //把信息报上服务器
            $GLOBALS['dstpSoap']->setModule('app','push');
            $id = $GLOBALS['dstpSoap']->record($dev,$title,$description,$url);
        }

        //一定时间后确认是否送达
        include_once('plannedTask/PlannedTask.php');
        $planTask = new PlannedTask('cli','push',30);
        $planTask->confirm($id);
        return $id;
    }




    /**
     * 用APN给IOS设备发送推送消息
     */
    private static function pushMsgToIosDevice($devs,$title,$description,$url)
    {
      
        $pemname    = '-dis.pem';
        $tokenfield = 'CHANNELID';
        $body['aps'] = array(
            'alert' => array(
                'title' => $title,
                'body' 	=> $description,
            ),
            'sound' => 'default',
        );
        if( $url)
        {
            $pemname    = '-dis-voip.pem';
            $tokenfield = 'VOIPTOKEN';
            $body['aps'] = array(
                'message' => $title,
                'page' => $url,
            );

        }

        $pemdir = dirname(dirname(__FILE__)).'/data/iospush';

        $iosdevs = array();

        foreach($devs as $dev)
        {
            if( NULL == $dev['COMPANY'] )
            {
                $dev['COMPANY'] = 'huidang';
            }
            $file = $pemdir.'/'.$dev['COMPANY'].$pemname;
            self::getIOSKey( $dev['COMPANY'],$url);
            if( !is_file($file) )
            {
                continue;
            }
            if(!isset($iosdevs[$dev['COMPANY']]))
            {
                $iosdevs[$dev['COMPANY']] = array();
            }

            $iosdevs[$dev['COMPANY']][] = $dev;
        }

        $payload = json_encode($body);
        foreach($iosdevs as $company=>$value)
        {
            $pemfile = $pemdir.'/'.$company.$pemname;
            $ctx = stream_context_create();
            stream_context_set_option($ctx, 'ssl', 'local_cert', $pemfile);
            stream_context_set_option($ctx, 'ssl', 'passphrase', self::$IosPushKey);
            stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
            $fp = stream_socket_client("ssl://gateway.push.apple.com:2195", $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
            foreach($value as $iosdev)
            {
                if( NULL == $iosdev[$tokenfield] )
                {
                    continue;
                }

                if( NULL != $url )
                {
                    $rtspurl = self::getRtspCallingURL($url,$iosdev['USERID']);
                    if( NULL == $rtspurl )
                    {
                        continue;
                    }
                    $body['aps']['page'] = $rtspurl;
                }
                $msg = chr(0) . pack('n', 32) . pack('H*', $iosdev[$tokenfield]) . pack('n', strlen($payload)) . $payload;
                $result = fwrite($fp, $msg, strlen($msg));
            }
            fclose($fp);
        }
    }

}