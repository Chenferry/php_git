<?php
//需要libevent扩展
///////////////////////////////////////////
if( 'cli'!=PHP_SAPI )
{
    exit(-1);
}

//避免重入

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
require_once($dstpCommonInfo);
require_once( dirname(__FILE__).'/proxyserver.php' );

if( APP_ZJ != HIC_APP )
{
	exit(-1);
}

///////////////////////////////////////////////////

//接收HIC发来的手机位置报告信息
//与status建立保活，转发status转来的状态变化消息

class status extends clientMgt
{
	static $client;
	static $addr     = NULL;
	static $livetime = 90;//保活时间，秒为单位
    static $timeout  = false;
    //代理转发过来的指示当前位置信息.flag表示当前页面
    static function hicNotice($pos)
    {
        $default = 1;

        setDevSleep($default);
    }
	
	static function init()
	{
		//定时发心跳包
        server::startTimer(array('status', 'keeplive'),1000000*5);
		
		self::$addr = "tcp://127.0.0.1:".HIC_SERVER_STATUS;
		clientMgt::init(__CLASS__);
	}

	static function keeplive()
	{
		if( !self::$client )
		{
			return;
		}
        $wi = "live\n";
        server::writeconn( self::$client, $wi);			
	}
	
	static function onConn($id)
	{
        $wi = "stubconnect\n";
        server::writeconn( $id, $wi);			
	}

    static function onRead($id,&$info)
    {
		self::$timeout = false;

        $cmd = server::getInfo($info,"\n");
        while(  NULL !== $cmd )
        {
            $cmd = trim($cmd);
			if( 'live' != $cmd )
			{
				$wi = "$cmd\n";
				server::writeconn( hic::$client, $wi);	
			}
            $cmd = server::getInfo($info,"\n");         
        }
        return true;        
    }	
}

class rtspctrl
{
    static $mapInfo=array();
	static $prxList=array();
	
	private static function checkToken($cid,$token)
	{
		list($token,$userid) = explode(':',$token);
		$c = new TableSql('homecammertoken');
		$id = $c->queryValue('ATTRID', 'ATTRID=? AND TOKEN=? AND STIME>?',
				array($cid,$token,(time()-3600*3)));

		return	validID($id);
	}
	
	private static function yuyinFromCamer($id,$camerid,&$yuyin)
	{
		$GLOBALS['dstpSoap']->setModule('yuyin');
		$result = $GLOBALS['dstpSoap']->yuyin($yuyin,"stubid-$id",self::$mapInfo[$id]['userid']);
		
		$info = pack("CC",0, count($result));//语音回复命令字为0
		foreach($result as &$r)
		{
			$info .= pack("C",strlen($r));
			$info .= $r;
		}
		$info .= "\n";
		server::writeconn($id,$info);
	}
	//转到定位点
	private static function duijiang($id,$camerid,&$info)
	{
		$info = base64_decode($info);
		$value = array();
		$value['op']     = 'dj';

		//语音对讲一次最多只能传送64K的信息
		$start = 0;
		$len   = strlen($info);
		while( $start < $len )
		{
			$value['info']   = substr($info,$start,63*1024);
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($camerid,$value); 
			$start = $start+63*1024;
		}
		return;
	}
	    
    //接到指令后，向摄像头发送控制信息
    private static function sendCtrlCmd($camerid,&$info)
    {
		$info = substr($info,0,-1);
		if( NULL == $info )
		{
			$info = 0;
		}
		else
		{
			$info = unpack("c",$info);
			$info = $info[1];
		}
		
		$cmd = array();
		$cmd['op']     = 'zhuan';
		$cmd['info']   = $info;		
		
        $GLOBALS['dstpSoap']->setModule('devattr','attr');
        return $GLOBALS['dstpSoap']->execAttr($camerid,$cmd); 
    }
	
	//设置定位点
	private static function setDWD($id,$camerid,&$info)
	{
		$dwd    = unpack("Cdwd/Clen",$info);

		//iphone上，定位点如果设置0，传递一直有问题。改为10传递过来先规避
		if( 9 == $dwd['dwd'] )
		{
			$dwd['dwd'] = 0;
		}

		$value = array();
		$value['op'] = 'pos';
		if( 0 == $dwd['len'] ) //删除定位点
		{
			$value['info']   = $dwd['dwd'];
			$value['action'] = 2;
		}
		else
		{
			$value['info']   = $dwd['dwd'];
			$value['action'] = 1;
			$value['dwdname']= substr($info,2);
		}

        $GLOBALS['dstpSoap']->setModule('devattr','attr');
        return $GLOBALS['dstpSoap']->execAttr($camerid,$value); 
		
	}

	//转到定位点
	private static function gotoDWD($id,$camerid,&$info)
	{
		$dwd    = unpack("Cdwd",$info);
		if( 9 == $dwd['dwd'] )
		{
			$dwd['dwd'] = 0;
		}		
		
		$value = array();
		$value['op']     = 'pos';
		$value['action'] = 0;
		$value['info']   = $dwd['dwd'];

        $GLOBALS['dstpSoap']->setModule('devattr','attr');
        return $GLOBALS['dstpSoap']->execAttr($camerid,$value); 
	}
	
	private static function getAllCamerLink($id)
	{
		$a = $_SERVER['REMOTE_ADDR'];
		//区分访问者是远程还是内网
		$remote = self::$mapInfo[$id]['remote'];
		$GLOBALS['allremote'] = $remote;

		$c = new TableSql('homeattr','ID');
		$camerList = $c->queryAllList('ID','SYSNAME=? AND INUSE=1',array('rtsp'));
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$GLOBALS['dstpSoap']->setAttrType('rtsp');
		$result = array();
		foreach( $camerList as $cid )
		{
			$link = $GLOBALS['dstpSoap']->getViewInfo(NULL,$cid);
			if( NULL == $link )
			{
				continue;
			}
			$result[] = $link;
		}
		unset($GLOBALS['allremote']);
		$info = pack("CC",2, count($result));//语音回复命令字为0
		$result = implode('|',$result);

		$info .= $result;
		$info .= "\n";
		server::writeconn($id,$info);
		return ;
	}

	
    //接到指令后，向摄像头发送控制信息。新版APP处理，包含语音
    private static function procCtrlCmd($id,$camerid,&$info)
    {
		$cmd = substr($info,0,3);
		switch($cmd)
		{
			case 'yy:':
				$info = substr($info,3);
				return self::yuyinFromCamer($id,$camerid,$info);
				break;
			case 'dj:':
				$info = substr($info,3);
				return self::duijiang($id,$camerid,$info);
				break;
			case 'dw:':
				$info = substr($info,3);
				return self::setDWD($id,$camerid,$info);
				break;
			case 'zd:':
				$info = substr($info,3);
				return self::gotoDWD($id,$camerid,$info);
				break;
			case 'get':
				return self::sendDWDInfo($id,$camerid);
				break;
			case 'all':
				return self::getAllCamerLink($id);
				break;
			default: //转动
				return self::sendCtrlCmd($camerid,$info);
				break;
		}
		return;
    }
	
	private static function sendDWDInfo($conn,$camerid)
	{
		$c = new TableSql('homecammerdwd');
		$result = $c->queryAll('*','ATTRID=? AND NAME IS NOT NULL',array($camerid));
		if( NULL == $result )
		{
			$info = pack("CC",1,0);//语音回复命令字为0
			$info .= "\n";
			server::writeconn($conn,$info);
			return;
		}
		
		$info = pack("CC",1,count($result));//语音回复命令字为0
		foreach($result as &$r)
		{
			$info .= pack("CC",$r['DWD'],strlen($r['NAME']));
			$info .= $r['NAME'];
		}
		$info .= "\n";
		server::writeconn($conn,$info);
		return;
	}

	
    static function hicNotice($info)
    {
        list($camerid,$userid,$token,$flag) = explode('@',$info);
		if( NULL == $token ) //最原始版本的APP
		{
			$info = $info."\n";
			return  self::sendCtrlCmd($camerid,$info);
		}
		
		//新版APP，直接建立代理连接
		if( !self::checkToken($camerid,$token) )
		{
			return false;
		}
        $proxyS = $GLOBALS['stubCfg']['PROXY'];
        $proxyP = $GLOBALS['stubCfg']['DATAPORT'];
        $pxy   = "tcp://$proxyS:$proxyP";
        
        $pxyid = server::setupConn('rtspctrl',$pxy);
		if( !validID($pxyid) )
		{
			return false;
		}
		self::$prxList[$pxyid] = true;
		$obj = array(array('attr'=>'attr','obj'=>$camerid));
		Cache::set("stubid-$pxyid",$obj);

        $info ="$flag\n";
        server::writeconn($pxyid,$info);
        self::$mapInfo[$pxyid] = array('hic'=>$hicid,'userid'=>$userid, 'camer'=>$camerid,'token'=>true,'remote'=>true);
    }
    
    static function onAccept($id,$flag=NULL)
    {
		//如果是通过hicNotice函数进行了鉴权，则第一句鉴权已经在前面处理掉了
		//这儿实际得到的是一个语音语句，应该传递执行
		if( isset(self::$prxList[$id]) )
		{
			if( !self::$mapInfo[$id]['token'] ) //旧版，不做语音控制
			{
				self::sendCtrlCmd(self::$mapInfo[$id]['camer'],$flag);
			}
			else
			{
				//新版APP，连接时已鉴权。可做语音控制
				self::procCtrlCmd($id,self::$mapInfo[$id]['camer'],$flag);
			}
			return;
		}
        //flag应该是hicid-camerid-token-userid这样形式
        @list($hicid,$camerid,$token,$userid) = explode('-',$flag);
        if ( (HICInfo::getHICID() != $hicid) 
            || ( !validID($camerid) ))
        {
            server::closeEventConn($id);
            return;
        }
		//如果有输入token，表示新版，则必须输入正确
		if( NULL != $token 
		&& !self::checkToken($camerid,$token) )
		{
            server::closeEventConn($id);
            return;
		}
		
		$obj = array(array('attr'=>'attr','obj'=>$camerid));
		Cache::set("stubid-$id",$obj);
		
		$remote = true;
		$conn = server::getconn($id);
		if ( $conn )
		{
			$addr = stream_socket_get_name($conn,true);
			list($ip,$port) = explode(':',$addr);
			$ipint = ip2long($ip);
			if( $ipint > ip2long('192.168.1.1') 
				&& $ipint <= ip2long('192.168.255.255') )
			{
				$remote = false;
			}
		}

		if( !validID($userid) )
		{
			$userid = INVALID_ID;
		}
		$valid = (NULL != $token);
        self::$mapInfo[$id] = array('hic'=>$hicid, 'userid'=>$userid, 'camer'=>$camerid,'token'=>$valid,'remote'=>$remote);
    }

    static function onRead($id,&$info) //read
    {
        while(  NULL !== ($cmd = server::getInfo($info,"\n")) )
        {
			if( !self::$mapInfo[$id]['token'] ) //旧版，不做语音控制
			{
				self::sendCtrlCmd(self::$mapInfo[$id]['camer'],$cmd);
			}
			else
			{
				//新版APP，连接时已鉴权。可做语音控制
				self::procCtrlCmd($id,self::$mapInfo[$id]['camer'],$cmd);
			}
        }
		return;
    }
    
    static function onClose($id) 
    {
		Cache::del("stubid-$id");
        if(isset(self::$mapInfo[$id]))
        {
            unset(self::$mapInfo[$id]);
        }
		if( isset(self::$prxList[$id]) )
		{
            unset(self::$prxList[$id]);
		}
    }
}
class hicconn
{
    static function hicNotice($flag)
    {
		debug("hicconn hicNotice($flag)");
        if( NULL == $flag )
        {
            return ;
        }
		list($flag,$acceptflag) = explode('@',$flag);
        $proxyS = $GLOBALS['stubCfg']['PROXY'];
        $proxyP = $GLOBALS['stubCfg']['DATAPORT'];
		$sPort  = HIC_SERVER_RWIFI;
		if ( defined('HIC_SYS_POWER') ) 
		{
			$sPort  = HIC_SERVER_EXTRSTART;
		}
        $web   = "tcp://127.0.0.1:".$sPort;
        $pxy   = "tcp://$proxyS:$proxyP";
		
       
        $webid = server::setupConn('hicconn',$web);
        $pxyid = server::setupConn('hicconn',$pxy);
        server::mapConn($webid,$pxyid);
		debug("webid:$webid,pxyid:$pxyid");
        
        $info ="$flag\n";
        server::writeconn($pxyid,$info);
		$info = "$acceptflag\n";
        server::writeconn($webid,$info);
    }	
}


class web
{
    static function hicNotice($flag)
    {
        if( NULL == $flag )
        {
            return ;
        }
        $proxyS = $GLOBALS['stubCfg']['PROXY'];
        $proxyP = $GLOBALS['stubCfg']['DATAPORT'];
        $web   = "tcp://127.0.0.1:80";
        $pxy   = "tcp://$proxyS:$proxyP";
        
        $webid = server::setupConn('web',$web);
        $pxyid = server::setupConn('web',$pxy);
        server::mapConn($webid,$pxyid);
        
        $info ="$flag\n";
        server::writeconn($pxyid,$info);
    }
}


class hic extends clientMgt
{
	static $client;
	static $addr     = NULL;
	static $livetime = 60;//保活时间，秒为单位
    static $timeout  = false;

	static function init()
	{
		//定时发心跳包
        server::startTimer(array('hic', 'hickeeplive'),1000000*20);
		
		clientMgt::init(__CLASS__);
	}
	
	static function hickeeplive()
	{
		//经常发现有进程看不出任何问题，但实际和服务器无法通讯
		//杀掉后马上就恢复正常。怀疑是定时器有问题导致心跳检测没正常运行
		//通过监控进程发现该cache没及时更新就直接杀掉
		Cache::set('proxystublive','live',90);
		if( !self::$client )
		{
			return;
		}
        $wi = "live\n";
        server::writeconn( self::$client, $wi);		
	}
	
	static function getConnAddr()
	{
        $GLOBALS['stubCfg'] = self::getDefaultProxy();
        if ( NULL == $GLOBALS['stubCfg'] )
        {
            return false;
        }
        if(isset($GLOBALS['stubCfg']['TIMEOUT']))
        {
            self::$livetime = $GLOBALS['stubCfg']['TIMEOUT'];
        }
		
        $proxyS = $GLOBALS['stubCfg']['PROXY'];
        $proxyP = $GLOBALS['stubCfg']['CTRLPORT'];
        self::$addr = "tcp://$proxyS:$proxyP";
		return true;
	}    

	//连接后马上写标记
	static function onConn($id)
	{
        $wi = self::getHICID()."\n";
        server::writeconn( $id, $wi);
	}
	
    static function onRead($id,&$info)
    {
		self::$timeout = false;
		
        $cmd = server::getInfo($info,"\n");
        while(  NULL !== $cmd )
        {
            $cmd = trim($cmd);
            list($If,$flag) = explode(':',$cmd);
            if( method_exists($If,'hicNotice'))
            {
                $If::hicNotice($flag);
            }
            else
            {
                //debug("live");
            }   
            $cmd = server::getInfo($info,"\n");         
        }

        return true;        
    }

    private function getHICID()
    {
        return HICInfo::getHICID(); 
    }

    private function getDefaultProxy()
    {
		//控制调用频率。发现不知为什么，有大量这个调用导致服务器负荷特别高
		$callctrl = Cache::get("getProxyServertime");
		if( false != $callctrl )
		{
			return false;
		}
		Cache::set("getProxyServertime",'call',12);
		//如果还没初始化，则也不需要去调用
		$c     = new TableSql('hic_hic','ID');
		$hicid = $c->queryValue('ID');
		$hicid = intval($hicid);
		if( !validID($hicid) )
		{
			return false;
		}
			
        $GLOBALS['dstpSoap']->setModule('app','hic');
        $proxy = $GLOBALS['dstpSoap']->getProxyServer();
		Cache::set("ProxyServer",$proxy,86400*3);
		return $proxy;
    }
}

server::regIf('hic',      0,    false);
server::regIf('web',      0,    false);
server::regIf('status',   0,    false);
//server::regIf('rtsp',     HIC_SERVER_RTSP,     false);
server::regIf('rtspctrl', HIC_SERVER_RTSPCTRL, true);
server::start();  

?> 