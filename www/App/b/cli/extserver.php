<?php 
if( 'cli'!=PHP_SAPI )
{
	exit(-1);
}
$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);
include_once( dirname(__FILE__).'/proxyserver.php' );
include_once( dirname(__FILE__).'/extcfg.php' );
include_once('util/hic.proto.php');
include_once( dirname(__FILE__).'/hicproc.php' );

class extlisten
{
	//读完整后才写进去，避免乱序。写完就关闭，这时读取是最完整的了
	static function onClose($id)
	{
		$info  = server::getrinfo($id);
		$info = unserialize($info);
		self::reportNewMacAction($info);
	}
	
	static function reportNewMacAction($msg)
	{
		$attrNum   = 1;
		$attrIndex = 0;
		//上报new mac 动作 add or del
		$status    = pack('C1a3a17a15a30C1',5,$msg['action'],$msg['mac'],$msg['ip'],$msg['name'],$msg['source']);
		
		$msg = pack('CCC',$attrNum,$attrIndex,strlen($status)).$status;
		connHic::sendMsg(DEV_CMD_DEV_STATUS,$msg);
		
	}
	
	static function reSendAllNewMacToHost()
	{
	    $action = 'add';
	    //分机属于桥接模式无法通过arp方式获取到ip地址和name
	    $ip = '255.255.255.255';
	    $name = '?';
	    $source = DEV_CONNECTHIC_SSID;

	    `iptables -L -t nat|grep "multiport dports www,https" > /tmp/newmac`;
	    `iptables -L -t nat|grep "tcp dpt:www" >> /tmp/newmac`;
	    `iptables -L -t nat|grep "tcp dpt:https" >> /tmp/newmac`;
	    $file = fopen("/tmp/newmac", "r");
	    if ($file == FALSE) {
	    	return 0;
	    }
	    while (!feof($file)) {
	        $line = trim(fgets($file));
	        $tempArry = explode('MAC ', $line);
	        $tempArry = explode(' ', $tempArry[1]);

	        $mac = trim($tempArry[0]);
	        if (empty($mac)) {
	        	continue;
	        }
	        $msg = array(
	        	'action' => $action,
	        	'mac'    => $mac,
	        	'ip'     => $ip,
	        	'name'   => $name,
	        	'source' => $source,
	        );
	        self::reportNewMacAction($msg);
	    }
	    fclose($file);
	}
	
}


/**
* 分机系统连接主机
*/
class connHic extends clientMgt
{
	static $dispatch=NULL;
	
	static $client;
	static $addr     = NULL;//'tcp://www.jia.mn:'.HIC_SERVER_RWIFI;
	static $livetime = 30;//设置tcp超时重新连接时间为30秒
	static $timeout  = false;

	static function init()
	{
		self::$dispatch = NULL;
		//设定定时器，1秒后就开始启动连接
		clientMgt::init(__CLASS__);
	}
	
	static function getConnAddr()
	{
		//如果前面已经获取发现是在外网，则该请求要大幅度延时
		if( NULL!=self::$dispatch && !self::$dispatch['LOCAL'] )
		{
			return NULL;
		}
		
		//向指定的URL发送地址分配请求
		$mac = HICInfo::getPHYID();
		$url = 'http://jia.mn/App/a/frame/dispatch.php?mac=$mac&hicid=0&userid=0&isssl=0';
		$info =  file_get_contents($url);
		self::$dispatch = json_decode($info,true);
		if( false == self::$dispatch )
		{
			return NULL;
		}
		
		//如果是外网服务器，则直接返回
		if( !self::$dispatch['LOCAL'] )
		{
			return NULL;
		}
		
		$server = self::$dispatch['SERVER'];
		$port   = self::$dispatch['DEVRPORT'];
		self::$addr = "tcp://$server:$port";
		return true;
	}
	

	static function onConn($id)
	{
		//连接上就上报mac-phydev-token-hicid-index		
		$mac    = HICInfo::getPHYID();
		$phydev = PHYDEV_TYPE_IP;
		$hicid  = self::$dispatch['HICID'];
		$index  = 0;
		
		//判断是否已经注册，如果注册，获取token，否则默认为FFFF
		$token  = 'FFFF';
		if( file_exists('/usr/db/addFlag') )
		{
			$token  = extProtoClass::genSecury();
			$token  = unpack('H*',$token);
			$token  = $token[1];
		}
		
		$info = "$mac-$phydev-$token-$hicid-$index\n";
		server::writeconn( $id, $info);
		

		//开始启动分机工作
		commExtSys::init();
	}

	static function onRead($id, &$info)
	{
		self::$timeout = false;
		
		$msg = NULL;
		while( HICProto::onRead($info,$msg) )
		{
			$header = NULL;
			$hic    = NULL;

			//分离出协议包头
			HICProto::getProtoHeader($msg,$header);
			//需要判断协调器的hicid回复

			//分离出HIC包头
			HICProto::getHICHeader($msg,$hic);

			//校验key是否合法
			$key = $hic['key'];
			if (!extProtoClass::checkSecuryCode($key) 
				&& (DEV_STATUS_WORK == commExtSys::$extStatus))
			{
				debug(date('y-m-d h:i:s',time()) . ' ' . __FUNCTION__ . ' ' . __LINE__ . " check fail cmd:$cmd");
				//return;
			}

			commExtSys::handleMsg($hic['cmd'], $msg);
		}		
	}
	
	static function sendMsg($cmd,&$msg,$seq=0)
	{
		if( NULL == self::$client )
		{
			return;
		}
		return extProtoClass::sendMsg(self::$client,$cmd,$msg,$seq);
	}
}


/**
* 分机系统负责监听new mac并且发送给connHic，然后由connHic发给主机
*/

/**
* 分机系统公用
*/
class commExtSys
{
	static $extStatus;

	function init()
	{
		self::$extStatus = DEV_STATUS_INIT;
		//判断是否已经入网。如果还没入网，则发送入网请求
		if( file_exists('/usr/db/addFlag') )
		{
			self::$extStatus = DEV_STATUS_WORK;

			//启动透传连接
			self::finishJoin();
			
		}
		else
		{
			//启动加入网络流程
			self::devAdd();
		}
		//启动心跳检测。或者在定时中完成入网流程
		server::startTimer(array(__CLASS__, 'heardBeat'),  1000000*15);
	}
	
	
	//完成入网工作流程.写加入标记，启动透传流程
	static function finishJoin()
	{
		self::$extStatus = DEV_STATUS_WORK;
		file_put_contents('/usr/db/addFlag',1);
		
		//报告所有当前MAC
		extlisten::reSendAllNewMacToHost();
		
		//写cache启动透传连接
		Cache::set('fjconninfo',connHic::$dispatch);
	}
	
	static function heardBeat()
	{
		switch( self::$extStatus )
		{
			case DEV_STATUS_INIT:
				return self::devAdd();
				break;
			case DEV_STATUS_JOIN:
				return self::devJoin();
				break;
			case DEV_STATUS_APPEND:
				return self::attrConf();
				break;
			case DEV_STATUS_WORK:
				Cache::set('connnetstatus', 'true',32);	
			default:
				return self::devStatus();
				break;
		}
		return;

	}
	
	static function devAdd()
	{
		$i = &$GLOBALS['DevInfo'];
		$msg = pack('a30a30a3C',$i['name'],$i['sn'],$i['ver'],$i['power']);
		connHic::sendMsg(DEV_CMD_DEV_ADD,$msg);
	}
	static function devJoin()
	{
		self::$extStatus = DEV_STATUS_JOIN;
		$attrList = &$GLOBALS['devAttrDef'];
		$msg      =  pack('C1',count($attrList));
		foreach ( $attrList as &$attr ) 
		{ 
			$msg .= pack('C1a30a10',$attr['index'],$attr['name'],$attr['attr']);
		}
		connHic::sendMsg(DEV_CMD_DEV_JOIN,$msg);
		
	}	
	//index，表示是否回应后处理
	static function attrConf($index=0xFFFF)
	{
		self::$extStatus = DEV_STATUS_APPEND;
		static $confirmIndex = 0;
		if( 0xFFFF != $index )
		{
			$confirmIndex |= (1 << $index);
		}
		//继续发送附加信息
		foreach( $GLOBALS['devAppendInfo'] as $index=>&$info )
		{
			$tmp = 1 << $index;
			//如果已经确认，则继续
			if( $confirmIndex & $tmp )
			{
				continue;
			}
			
			//发送当前附加信息
			$msg = pack('CC',$index,strlen($info)).$info;
			return connHic::sendMsg(DEV_CMD_DEV_ATTR_CONF,$msg);
		}
		
		//如果全部已经确认完成，则设置为工作状态，启动透传连接
		self::finishJoin();
	}

	static function devStatus()
	{
		$msg = pack('C1C1C1',1,0,1) . pack('C',0);
		connHic::sendMsg(DEV_CMD_DEV_STATUS,$msg);
	}

	function handleMsg($cmd,&$msg)
	{
		switch($cmd)
		{
			case DEV_CMD_HIC_CONFIRM: //加入确认
				//$msg = unpack('llogic/lchid/lhcid',$msg);
				file_put_contents('/usr/db/key',$msg);
				self::devJoin();//发送属性信息
				break;
			case DEV_CMD_HIC_JOIN_CONFIRM:
				self::attrConf();
				break;
			case DEV_CMD_DEV_ATTR_CONF_RSP:
				$msg = unpack('C',$msg);	
				self::attrConf($msg[1]);
				break;
			case DEV_CMD_HIC_CTRL_DEV:
				self::devCtrl($msg);
				break;
			default:
				break;
		}
	}
	
	static function devCtrl($msg)
	{
		$info = unpack('vsleeptime/Cnum',substr($msg,0,3));
		$sPos = 3;
		for($i=0; $i<$info['num'];$i++)
		{
			$ainfo = unpack('C1INDEX/C1LEN',substr($msg,$sPos));
			$cmd   = substr($msg,$sPos+2,$ainfo['LEN']);
			$sPos  = $sPos+2+$ainfo['LEN'];
			
			//调用相关属性处理函数进行处理
			$attr  = $GLOBALS['devAttrDef'][ $ainfo['INDEX'] ]['attr'];
			$class = $attr.'AttrClass';
			$class::devCtrl($cmd);
		}
	}
}

server::regIf('connHic',   0, false); 
server::regIf('extlisten', HIC_SERVER_SWIFI, false); 

server::start();

?>