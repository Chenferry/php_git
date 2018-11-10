<?php  

//HIC消息处理过程，包括发送缓存，消息重发和接收处理
//为了充分发挥多核优势，不同方向来源的消息是在多个进程中执行。
//当然一个进程也可能合并处理多个方向来源的消息

/****************分机考虑***************************
 * 每个分机，除了一个网关属性，同时对于每个转发通道，都注册一个特定属性的转发属性
 * 设备的SUBHOST记录为网关的设备ID
 * 获取转发连接时，以$conn[SUBHOST][PHYDEV]为下标获取对应的连接通道
 *
 * ZIGBEE设备需要报送一个分机ID。这个以ATTRID为准
 * 给ZIGBEE发送分机ID时，因为SUBHOST记录的是网关设备ID
 * 如果查找该网关设备ID无对应zigbee的属性，则发送该网关的设备ID
 * 否则查找该网关对应于ZIGBEE的属性ID做为分机ID
 *
 * 替换ZIGBEE分机时，分机ID实际选择的是zigbee属性，
 * 查找原分机的 SUBHOST,把SUBHOST为该ID的zigbee设备替换为新的SUBHOST
 *
 * 每个协调器网关都需要定时发心跳和服务器有个交互做为保活。
 * Zigbee现在是由网关自己主动下发。
 * 需要和协调器程序一起处理下，由协调器自己来请求
 ***************************************************/

/****************异常考虑***************************
 * 进程有多个地方调用了setSysUid，这个地方需要确保不重入。
 * 但定时器，消息触发等几个不同入口是否会导致函数重入暂时还未确定
 ***************************************************/

/****************临时处理***************************
 * 休眠时间设置暂时直接去掉。直接就是设置为2秒。后面再来处理
 ***************************************************/
 
 
//因为系统中可能有多个进程，每个进程中，需要指定一个端口监听其它进程需要通过本进程发送的消息
class HICMsgRec
{
	//读完整后才写进去，避免乱序。写完就关闭，这时读取是最完整的了
	static function onClose($id)
	{
		$info = server::getrinfo($id);
		$info = unserialize($info);
		if( false == $info )
		{
			return;
		}
		
		//这个整个函数HICMsgProc::sendMsg都不需要调用到其它文件
		//所以也无需设置。后面要处理
		//if( 'i' == HIC_LOCAL )
		//{
		//	setSysUid( $info['dev']['CLOUDID'] );
		//}

		HICMsgProc::sendMsg($info['dev'],$info['cmd'],$info['msg']);
		return;
	}
}

/*******************************************
 * connList[$id] = array(
 * 		'IP'
 *      'MAC'
 *      'HICID'
 *      'SUBHOST'
 *		'PHYDEV'
 *      'SEQ'
 *      'LIVE'
 *	);
 *
 * $hostList[$phydev][$host] = $id;
 *
 * $cacheList[$id] = $msgCache;
 *
 *******************************************/


//协议消息的处理过程
class HICMsgProc
{
	//本进程所连接的中转信息
	static $connList = array();
	static $hostList = array();
	//当前有消息缓存的所有连接ID
	static $cacheList= array(); 
	//直连的IP设备暂时都把ID信息保存在一个数组里
	static $phyList  = array();
	
	//现在在accept时，经常连接开头就是一堆特殊乱码
	//目前也没发现这些乱码从哪里生成如何防止需要过滤
	//先进行一些简单规则判断进行过滤
	private static function checkMacFlag(&$mac)
	{
		//现在mac过来都是ascii码，如果不是就是有问题
		if ( 'ASCII' != mb_detect_encoding($mac, array('ASCII')) )
		{
			return false;
		}
		//现在传来的mac地址有几种写法方式
		//长度不一，但基本都是16或者17字节，简单限定在12到18之间
		if ( ( 12 > strlen($mac) ) && ( 18 < strlen($mac) ) )
		{
			return false;
		}
		return true;
	}

	
	static function setConnHost($id,$hicid,$host,$phydev)
	{
		if( !isset(self::$hostList[$host]) )
		{
			self::$hostList[$host] = array();
		}
		if( !isset( self::$hostList[$phydev] ) )
		{
			self::$hostList[$phydev] = array();
		}
		self::$hostList[$phydev][$host] = $id;

		if( !isset(self::$connList[$id]) )
		{
			self::$connList[$id] = array();
		}
		self::$connList[$id]['SUBHOST'] = $host;
		self::$connList[$id]['PHYDEV']  = $phydev;
		self::$connList[$id]['SEQ']     = 2;//seq的0和1不能使用
		self::$connList[$id]['LIVE']    = 0;//seq的0和1不能使用
		self::$connList[$id]['HICID']   = $hicid;
		
		self::setSubHostPort($host,$phydev);
	}
	
	static function sendToHost(&$dev,&$info)
	{
		if( -1 != $dev['SUBHOST'] )
		{
			$client = self::$hostList[ $dev['PHYDEV'] ][ $dev['SUBHOST'] ];
		}
		else
		{
			//要从phylist中找出ID
			foreach( self::$phyList as $phy=>$connid )
			{
				if( $phy == $dev['PHYADDR'] )
				{
					$client = $connid;
				}
			}			
		}
		return server::writeconn( $client, $info );
	}

	static function init() //init
	{
        //2秒检查一次有没长时间未回应消息，重发
        server::startTimer(array(__CLASS__, 'msgsendCheck'),1000000*2);	

		//每分钟检查一次所有已经没有消息的连接
        server::startTimer(array(__CLASS__, 'connCheck'),1000000*60);	

		if( 'b' == HIC_LOCAL )
		{
			//每次启动时，都给协调器报告下当前的HICID
			server::startTimer(array(__CLASS__, 'sendHICID'),  1000000*15);
			//b环境中，还要考虑红外或者单火这类的。暂时先沿用以前的处理
			//后续也需要改为和门锁一样的处理。在进入页面时就由页面直接发送
			//这儿只控制页面退出后的停止发送
			server::startTimer(array(__CLASS__, 'sleeptimeCheck'),1000000*1);			
		}
		else
		{
			server::startTimer(array(__CLASS__, 'sleeptimeCheck'),1000000*60);			
		}
	}

	static function onAccept($id,$flag=NULL)
	{
		debug("onAccept($id,$flag=NULL)");
		self::$connList[$id] = array();
		if( 'console' == $flag )
		{
			//通过串口直接连上的，默认zigbee
			$phydev = PHYDEV_TYPE_ZIGBEE;
			if(defined('HIC_CONSOLE_PHYDEV'))
			{
				$phydev = HIC_CONSOLE_PHYDEV;
			}
			self::setConnHost($id,0,0,$phydev);

			$hicid = HICInfo::getHICID();
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg(0,
									DEV_CMD_SYS_HICID,
									array('hic'=>$hicid,'sub'=>0),0,$phydev);			
			
			//给该透传通道下发关闭加入命令
			$c = new TableSql('sysinfo');
			$r = $c->queryValue('DISNETJOIN');
			$cmd = intval($r)?DEV_CMD_SYS_CLOSE_PERMITJOIN:DEV_CMD_SYS_OPEN_PERMITJOIN;
			$GLOBALS['dstpSoap']->setModule('setting','setting');
			$GLOBALS['dstpSoap']->openDevSearchMap($cmd,$subhost,$phydev);

			return;
		}
		
		//后面这些都是通过IP连接上来的
		//没报MAC的老摄像头
		$conn   = server::getconn($id);
		$remote = stream_socket_get_name ( $conn , true );
		list($ip,$port)    = explode(':',$remote);

		if( NULL == $flag )
		{
			$name = NULL;
			include_once('uci/uci.class.php');
			uci_base::getInfoByIP($ip,$flag,$name);		
		}

		if( false === strpos($flag,'-') )
		{
			if( 'i' == HIC_LOCAL )
			{
				//单品服务器需要按照新规范过来才能连接
				server::closeEventConn($id);
				return;
			}

			$flag = strtoupper(trim($flag));
			//现在在mac前会有一些乱码特殊符号很长，导致错误。这儿要判断下
			if( !self::checkMacFlag($flag) )
			{
				server::closeEventConn($id);
				return;
			}
			
			$c = new TableSql('homedev','ID');
			$subhost = $c->queryValue('ID','PHYADDR=?',array($flag));
			if( !validID($subhost) )
			{
				$subhost = -1;//设备管理中还没记录的连接
			}

			//直接接入的IP设备。
			//后面要改下，已经有ID的设备，SUBHOST直接修改为设备ID
			self::setConnHost($id,0,$subhost,PHYDEV_TYPE_IP);

			self::$connList[$id]['MAC']   = $flag;
			self::$connList[$id]['IP']    = $ip;
			self::$phyList[$flag] = $id;

			//根据mac地址，更新其logic地址
			$devinfo = array();
			$devinfo['LOGICADDR'] = $ip;
			$c->update($devinfo,NULL,'PHYADDR=?',array($flag));

			return;
		}
		

		//普通连接的flag就是mac
		//如果是中转进程，其flag为mac-phydev-token-hicid-index
		//c.Token的计算同其它控制消息的8个字节的密钥。根据注册时的信息生成。
		//如果还没加入，token为FFFF
		//mac-phydev-token为最开始的分机，其token固定。需要考虑兼容
		
		//根据mac获取对应的分机ID
		list($mac,$phydev,$token,$hicid,$attrindex) = explode('-',$flag);
		//根据hicid和token，判断是否合法
		if( NULL == $hicid && 'i' == HIC_LOCAL )
		{
			server::closeEventConn($id);
			return;
		}

		if( NULL == $phydev )
		{
			server::closeEventConn($id);
			return;
		}

		//现在在mac前会有一些乱码特殊符号很长，导致错误。这儿要判断下
		$mac = strtoupper(trim($mac));
		if( !self::checkMacFlag($mac) )
		{
			server::closeEventConn($id);
			return;
		}

		if( 'i' == HIC_LOCAL )
		{
			setSysUid( $hicid );
		}

		//根据MAC获取当前分机的ID，如果获取不到，直接关闭当前连接
		$c = new TableSql('homedev','ID');
		$subhost = $c->queryValue('ID','PHYADDR=?',array($mac));

		//token为FFFF，hicid为空
		if( 'i' == HIC_LOCAL && 'FFFF' != $token )
		{
			if( !validID($subhost) )
			{
				//8266模块原来的代码有问题，获取的模块mac地址取错了，获得的是AP的MAC地址
				//但模块对外实际用的是Station的地址，这会导致主机上防火墙判断mac时有误
				//所以新代码都修正为取Station地址。
				//但原来模块如果已经加入系统，其记录的mac地址已经是错误的AP地址
				//如果原来模块升级后，重新连接报告的MAC地址就会不同于数据库记录
				//为了保持原来已经加入模块的正确处理，在请求发现mac地址不对的时候
				//把当前MAC地址的第一个字节加2变成AP地址再查找下,如果查找得到，MAC地址直接更新

				//这段代码存在一段时间，直到确认模块都升级完成后才可以去掉
				$a = $mac;
				$m = substr($a,0,2);
				$m = hexdec($m);
				$m = $m+2;
				$m = strtoupper(dechex($m)) ;			
				$mac = $m.substr($a,2);
				$subhost = $c->queryValue('ID','PHYADDR=?',array($mac));
				if( validID($subhost) )
				{
					//如果首个字节加2可以找到，就认为是原来的模块报告了错误mac信息
					//把原来的mac地址更新为新的Station的mac地址
					$c = new TableSql('homedev','ID');
					$upinfo = array();
					$upinfo['PHYADDR'] = $a;
					$c->update($upinfo,NULL,'PHYADDR=?',array($mac));
				}



				$rsp = 'fail';
				server::writeconn( $id, $rsp );
				server::closeEventConn($id);
			}
			//必须token是正确的
			$token = pack("H*",$token);
			if(!HICProto::checkHICHeader($devid,$token))
			{
				$rsp = 'fail';
				server::writeconn( $id, $rsp );
				server::closeEventConn($id);
			}
		}

		if( !validID($subhost) )
		{
			$subhost = -1;//设备管理中还没记录的连接
			self::$phyList[$mac] = $id;
		}

		self::setConnHost($id,$hicid,$subhost,$phydev);
		self::$connList[$id]['MAC']     = $mac;
		self::$connList[$id]['IP']      = $ip;

		//zigbee的透传通道，需要获取对应的tran属性ID做为发给zigbee协调器的subid
		if( (PHYDEV_TYPE_ZIGBEE == $phydev || PHYDEV_TYPE_24G == $phydev) && NULL != $hicid )
		{
			$c = new TableSql('homeattr','ID');
			$subid = $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',
										array($subhost,$attrindex));
			self::$connList[$id]['SUBATTR'] = $subid;
			
			//发送HICID和SUBATTRID。这儿看下是否需要延时发送
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg($subhost,
				DEV_CMD_SYS_HICID,
				array('hic'=>$hicid,'sub'=>$subid),0,$phydev);

			//给该透传通道下发关闭加入命令
			$c = new TableSql('sysinfo');
			$r = $c->queryValue('DISNETJOIN');
			$cmd = intval($r)?DEV_CMD_SYS_CLOSE_PERMITJOIN:DEV_CMD_SYS_OPEN_PERMITJOIN;
			$GLOBALS['dstpSoap']->setModule('setting','setting');
			$GLOBALS['dstpSoap']->openDevSearchMap($cmd,$subhost,$phydev);
		}
	}

	static function onClose($id)
	{
		$phydev = self::$connList[$id]['PHYDEV'];
		unset( self::$connList[$id] );
		foreach( self::$hostList[$phydev] as $subhost=>&$host )
		{
			if( $host['id'] == $id )
			{
				unset( self::$hostList[$phydev][$subhost] );
				break;
			}
		}
	}
	
	////////////相关辅助函数/////////////////
	//清除协调器关联表中多余的MAC地址
	static function cleanAssocMac($host,$info)
	{
		return;//暂时屏蔽该功能 
		$num = unpack('Cnum',$info);
		$num = $num['num'];
		$info = substr($info,1);
		$c = new TableSql('homedev','ID');
		for($i=0;$i<$num;$i++)
		{
			$mac = unpack('H16mac',$info);
			$mac = $mac['mac'];
			$info = substr($info,8);
			
			$macList = $c->queryAllList('PHYADDR','PHYDEV=?',
							array(PHYDEV_TYPE_ZIGBEE));

			$devid = $c->queryValue('ID','PHYADDR=?',array($mac));
			if( validID($devid) )
			{
				continue;
			}
			//表里已经找不到该MAC，删除
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg($host,DEV_CMD_SYS_RM_DEV_ASSOC,$mac);
		}
	}
	
	//连接如果长时间没通讯，服务器直接断开其连接
	static function connCheck()
	{
		$list = array();
		foreach( self::$connList as $id=>&$conn )
		{
			if( $conn['LIVE'] > 3 )
			{
				$list[] = $id;
				continue;
			}
			$conn['LIVE']++;
		}

		foreach( $list as $id )
		{
			server::closeEventConn($id);
		}
	}


	private static function setSubHostPort($subhost,$phydev)
	{
		//item服务器中，每个item的端口地址都是已经确定
		if( 'i' == HIC_LOCAL )
		{
			return;
		}
		$port = server::getIFPort('HICMsgRec');
		$map  = Cache::get('subhostport');
		$map[$subhost][$phydev] = $port;
		Cache::set('subhostport',$map,0x7fffffff);
	}
	//消息缓存名字
	private static function getCName($host)
	{
		return 'msgcache_'.$host;
	}
	//根据消息中的物理和逻辑地址找不到相应的设备时的处理
	private static function procInvalidDevid($id,$cmd,&$header,&$msg)
	{
		//判断是否协调器回应的hicid
		if( (PHYDEV_TYPE_ZIGBEE == $header['PHYDEV']) 
			&& (0 == $header['LOGICADDR']) )
		{
			if ( method_exists('rdio', 'procDevMsg') && ('b' == HIC_LOCAL ) )	
			{
				rdio::procDevMsg();
			}
			//判断命令字DEV_CMD_MAC_REPORT
			if( DEV_CMD_MAC_REPORT == $cmd )
			{
				server::startTimer(array(__CLASS__, 'cleanAssocMac'),  1000000*300,
									array($header['SUBHOST'],$msg),false);
			}
			
			
			return true;
		}
		
		if( DEV_CMD_DEV_CHECKHICID == $cmd )
		{
			//没加入的，直接回应当前hicid即可
			$header['LOGICID']= 0;
			$header['TMSI']   = 0;
			$hicid = HICInfo::getHICID();
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($header,DEV_CMD_DEV_CHECKHICID_RSP,$hicid);
			return true;
		}
	
	
		//zigbee入网时的设备信息，先把该mac保存，后续发现是第三方zigbee设备，则清除出网络
		if( DEV_CMD_SYS_DEV_JOIN == $cmd || DEV_CMD_SYS_DEV_JOIN1 == $cmd || DEV_CMD_SYS_DEV_JOIN3 == $cmd )
		{
			if( NULL == trim($header['JOINPHYADDR']) )
			{
				return true;
			}

			$info = array();
			$info['JOINTIME'] = time();
			$info['SUBHOST']  = $header['SUBHOST'];
			$info['PHYADDR']  = $header['JOINPHYADDR'];
			$c   = new TableSql('homeexceptdev');
			$c->add($info);
			return true;
		}
		
		//这个是设备已经被删除，无需再发确认，否则会死循环
		if( DEV_CMD_DEV_MAC_CONF == $cmd )
		{
			if (NULL == $msg) {
				return false;
			}
			$mac = unpack('H16PHYADDR',$msg);
			$header['PHYADDR'] = $mac['PHYADDR'];
			$devid = self::getDevIDFromHeader( $header );

			return false;
		}
					
		//使用中有发现，不知为什么物理地址和逻辑地址不一致
		//向该逻辑地址请求MAC地址来更新
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendDevSySMsg($header['SUBHOST'],DEV_CMD_HIC_GET_MAC,NULL,$header['LOGICADDR']);
		return false;		
	}	
	
	static function getDevIDFromHeader($header)
	{
		$GLOBALS['dstpSoap']->setModule('home','end');
		return $GLOBALS['dstpSoap']->getDevidFromAddr($header['PHYADDR'],$header['LOGICADDR'],$header['SUBHOST']);
	}	

	////////////接收消息处理流程/////////////
	static function onRead($id,&$info)
	{
		//心跳检测，清除长期未通讯的连接
		self::$connList[$id]['LIVE'] = 0;

		if( 'i' == HIC_LOCAL )
		{
			setSysUid( self::$connList[$id]['HICID'] );
		}
		debug("on read:$id");
		$msg = NULL;
		while( HICProto::onRead($info,$msg) )
		{
			$header = NULL;
			$hic    = NULL;
			
			//分离出协议包头
			HICProto::getProtoHeader($msg,$header);

			//分离出HIC包头
			HICProto::getHICHeader($msg,$hic);
			debug("cmd :$hic[cmd]");

			//如果是心跳包，则直接回应
			if( DEV_CMD_SYS_KEEPLIVE == $hic['cmd'] )
			{
				$rsp = 'a';
				server::writeconn( $id, $rsp );
				continue;
			}
			
			$header['SUBHOST'] = self::$connList[$id]['SUBHOST'];
			$header['PHYDEV']  = self::$connList[$id]['PHYDEV'];
			switch( $header['PHYDEV'] )
			{
				case PHYDEV_TYPE_ZIGBEE:
					break;
				case PHYDEV_TYPE_IP:
					$header['PHYADDR']   = self::$connList[$id]['MAC'];
					$header['LOGICADDR'] = self::$connList[$id]['IP'];

					//如果是WIFI的，每接到消息后都顺手发送一个心跳回应做为保活
					//因为在状态消息中，现在都没任何回应。
					//原来的代码如果有非法字符，则无法回应。所以不能回复
					if( 0 !=  self::$connList[$id]['HICID'] )
					{
						$rsp = 'a';
						server::writeconn( $id, $rsp );
					}
					break;
				case PHYDEV_TYPE_24G:
				default:
					break;
			}
			
			self::procHICMsg($id,$header,$hic,$msg);
		}
		
	}
	
	//处理接到的消息包，在该函数中，需要
	//完成密钥检查，回应确认，调用处理
	static function procHICMsg($id,&$header,&$hic,&$msg)
	{
		$cmd  = $hic['cmd'];
		$host = $header['SUBHOST'];
        
		if( 'i' == HIC_LOCAL && DEV_CMD_DEV_ADD == $cmd)
		{//单品只能加一定数量的设备
			if( isset($header['PHYADDR']) )
			{
				$phyaddr = $header['PHYADDR'];
			}
			else
			{
				$phyaddr = unpack('a30NAME/a30SN/a3VER/C1ISPOWER/H16PHYADDR',$msg);
				$phyaddr = $phyaddr['PHYADDR'];
			}

			$c = new TableSql('homedev','ID');
			$GLOBALS['dstpSoap']->setModule('home','end');
			$devid = $GLOBALS['dstpSoap']->getDevidFromAddr($phyaddr,$header['LOGICADDR'],$header['SUBHOST']);
			if ( !validID($devid) )
			{
				$num = $c->getRecordNum('PHYDEV=? AND SUBHOST=?',array($header['PHYDEV'],$host));
				if ($num >= 20)
				{
					return false;
				}
			}
		}

		//获取设备ID，如果获取不到的处理
		$devid = self::getDevIDFromHeader( $header );
		if ( !validID($devid) && DEV_CMD_DEV_ADD!=$cmd )
		{
			if( ( PHYDEV_TYPE_IP== $header['PHYDEV'] ) )
			{
				//8266模块原来的代码有问题，获取的模块mac地址取错了，获得的是AP的MAC地址
				//但模块对外实际用的是Station的地址，这会导致主机上防火墙判断mac时有误
				//所以新代码都修正为取Station地址。
				//但原来模块如果已经加入系统，其记录的mac地址已经是错误的AP地址
				//如果原来模块升级后，重新连接报告的MAC地址就会不同于数据库记录
				//为了保持原来已经加入模块的正确处理，在请求发现mac地址不对的时候
				//把当前MAC地址的第一个字节加2变成AP地址再查找下,如果查找得到，MAC地址直接更新

				//这段代码存在一段时间，直到确认模块都升级完成后才可以去掉
				$a = $header['PHYADDR'];
				$m = substr($a,0,2);
				$m = hexdec($m);
				$m = $m+2;
				$m = strtoupper(dechex($m)) ;			
				$header['PHYADDR'] = $m.substr($a,2);
				$devid = self::getDevIDFromHeader( $header );
				if( !validID($devid) )
				{
					//加2也找不到，就不需要再找了，确实还没加入
					$header['PHYADDR'] = $a;
					return self::procInvalidDevid($id,$cmd,$header,$msg);
				}
				//如果首个字节加2可以找到，就认为是原来的模块报告了错误mac信息
				//把原来的mac地址更新为新的Station的mac地址
				$c = new TableSql('homedev','ID');
				$upinfo = array();
				$upinfo['ID']      = $devid;
				$upinfo['PHYADDR'] = $a;
				$c->update($upinfo);
				$header['PHYADDR'] = $a;
			}
			else
			{
				//这个地方需要处理
				return self::procInvalidDevid($id,$cmd,$header,$msg);
			}
		}
		
		//取出交换密钥，根据命令字判断是否进行鉴权
		if ( !HICProto::checkHICHeader($devid,$hic['key']) 
			&& ( ((DEV_CMD_DEV_CHECKHICID != $cmd)) && (DEV_CMD_DEV_ADD!=$cmd)  ) )
		{
			return false;
		}
		
		//seq这个字节位置最开始的版本中被当作消息版本号，0和1已经被使用。
		//但后台实际没处理过消息版本。现在改为消息序列，0和1不使用
		if( $hic['seq'] > 1 && isset(self::$cacheList[$id])) 
		{
			//组播消息需要重新处理
			$cachemsg = &self::$cacheList[$id];
			if( isset( $cachemsg[$hic['seq']]['dev'] ) )
			{
				$devindex = array_search($devid,$cachemsg[$hic['seq']]['dev']);
				unset( $cachemsg[$hic['seq']]['dev'][$devindex] );
				if ( NULL == $cachemsg[$hic['seq']]['dev'] )
				{
					unset( $cachemsg[$hic['seq']] );
				}
			}
			else
			{
				unset( $cachemsg[$hic['seq']] );
			}
		}
		
		$GLOBALS['dstpSoap']->setModule('home','if');
		return $GLOBALS['dstpSoap']->procMsg($header,$devid,$cmd,$msg);
	}
	
	////////////发送消息处理流程//////////////
	static function sendMsg(&$dev,$cmd,&$msg)
	{
		if ( is_numeric( $dev ) )
		{
			$c = new TableSql('homedev','ID');
			$dev = $c->query('PHYDEV,SUBHOST,VER,PHYADDR,LOGICADDR,LOGICID,TMSI', 
								'ID=?',array($dev));
		}
		
		$host   = $dev['SUBHOST'];
		$phydev = $dev['PHYDEV'];
		if( !isset(self::$hostList[$phydev][$host]) )
		{
			return false;
		}

		$client =  self::$hostList[$phydev][$host];
		$seq    = &self::$connList[$client]['SEQ'];
		//更新消息序列号
		$seq++;
		if( $seq > 250 ) //最多能到255。但保留几个做未来特殊用途
		{
			$seq = 3; //0和1原来已经被使用
		}
		
		//生成HIC消息头部
		$info  = HICProto::genHICHeader($dev,$cmd,$seq);
		//加入消息体
		$info .= $msg;
		
		HICProto::genProtoHeader($info);
		
		//判断设备软件版本，1.1及以下的不需要缓存

		$cmdchr = ord($info[22]);
		if( (version_compare('1.2',$dev['VER']) > 0) 
			|| ( ( DEV_CMD_HIC_GROUP_CTRL_DEV != $cmdchr )
					&& (DEV_CMD_HIC_CTRL_DEV != $cmdchr) ) )
		{
			return self::sendToHost($dev,$info);
		}
		
		
		$devList =  array();
		if ( DEV_CMD_HIC_GROUP_CTRL_DEV == $cmdchr )
		{
			$dgid = $info[23].$info[24];//
			$dgid = unpack("vdgid", $dgid);
			$dgid = $dgid['dgid'];
			
			//查找到当前$group对应的，在线的dev
			$c = new TableSql('homedev','ID');
			$c->join('smartdevgroupattr','smartdevgroupattr.DEVID=homedev.ID');
			$devList = $c->queryAllList('ID','DGID=? AND STATUS=?', array($dgid,DEV_STATUS_RUN));
			$devList = array_unique($devList);
		}
		
		if( !isset(self::$cacheList[$client]) )
		{
			self::$cacheList[$client] = array();
		}
		self::$cacheList[$client][$seq] = array('t'=>time(),'i'=>$info);
		if( NULL != $devList )
		{
			self::$cacheList[$client][$seq]['dev'] = $devList;
		}

		return self::sendToHost($dev,$info);
	}

	////////////消息重发处理处理//////////////////
	//对单个还未回复的设备重发组播控制消息	
	//这个后续协调器需要修改代码，这儿只需要修改逻辑地址，就可以直接透传出去
	//现在协调器基于命令判断，而不是基于地址判断是否透传，无法直接透传出去
	private static function resendGroupMsg($client,$msg,&$devList)
	{
		HICProto::getProtoHeader($msg,$header);

		//去掉最后一个校验码
		$msg=substr($msg, 0, -1);
		
		$cdev = new TableSql('homedev','ID');
		foreach( $devList as $devid )
		{
			//直接修改发送地址，重新计算校验码发送出去
			$dev = $cdev->query('PHYDEV,PHYADDR,LOGICADDR,SUBHOST','ID=?',array($devid));
			$pHeader = pack('H16n1c1', $dev['PHYADDR'], $dev['LOGICADDR'],1 );
			$info = $pHeader.$msg;
			HICProto::genProtoHeader($info);
			
			//后续考虑把新生成的消息直接保存在devlist中存起来
			
			self::sendToHost($dev,$info);
		}
	}
		
	static function msgsendCheck()
	{
		$t = time();
		foreach( self::$cacheList as $client=>&$msg )
		{
			if( NULL == $msg )
			{
				unset( self::$cacheList[$client] );
				continue;
			}

			if( 'i' == HIC_LOCAL )
			{
				setSysUid( self::$connList[$client]['HICID'] );
			}
		
			//距离2到5秒间的重发
			foreach( $msg as $seq=>&$m )
			{
				//7秒后就不再缓存重发
				if( $t > ($m['t']+7) )
				{
					unset( $msg[$seq] );
					continue;
				}
				
				//2秒内的也暂不处理
				if( $t < ($m['t']+2) )
				{
					continue;
				}
				
				$info = $m['i'];
				//组播全部重发对系统压力太大。如果漏回的不多，则单播出去
				if( isset($m['dev']) && 5 > count($m['dev']))
				{
					self::resendGroupMsg($client,$info,$m['dev']);
				}
				else
				{
					server::writeconn( $client, $info );
				}
			}
		}
	}

	////////////本进程需要的定时维护处理//////////////////////
	
	//给连接中的所有电池控制设备发送休眠时间设置
	//当有变化时，直接发送快速响应通知。这儿只发送停止快速响应通知
	static function sleeptimeCheck()
	{
		$ms = Cache::get1('msnotice');
		foreach( $ms as $msid=>&$info )
		{
			//如果门锁还在页面，暂时不处理
			$check = Cache::get1("msnotice_$msid");
			if( false != $check )
			{
				continue;
			}
			//给该门锁发送停止快速响应命令
			if( DSTP_CLU == CLU_CLOUD )
			{
				setSysUid( $info['sys'] );
			}
			Cache::set('mssleep',4.5);
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsg($info['dev'],DEV_CMD_HIC_CTRL_DEV,array());
			
			//从记录中删除该门锁
			unset($ms[$msid]);
		}
		Cache::set1('msnotice',$ms);
		
		//主机是否支持关闭网络
		
		
		if( 'b' != HIC_LOCAL )
		{
			return;
		}
		
		
		static $sleeptime = -1;
		$stime = Cache::get('devsleep');
		if ( false == $stime )
		{
			$stime = 5; //没有登陆时的休眠时间
		}
		if( $stime == $sleeptime )
		{
			return;//时间没改变，无需发送
		}
		$sleeptime = $stime;
		
		foreach( self::$connList as &$conn )
		{
			if( PHYDEV_TYPE_ZIGBEE != $conn['PHYDEV']   )
			{
				continue;
			}

			//查找所有需要通知的设备
			$c   = new TableSql('homedev','ID');
			$cms = new TableSql('homeattr','ID');
			$msdev = $cms->queryAllList('DEVID','SYSNAME=?',array('ms'));
			if( $msdev )
			{
				$msdev = implode(',',$msdev);
				$devList = $c->queryAll('PHYDEV,SUBHOST,VER,PHYADDR,LOGICADDR,LOGICID,TMSI',
								'STATUS=? AND POWERTYPE=? AND SUBHOST=? AND ID NOT IN( $msdev )',
								array(DEV_STATUS_RUN,POWER_BAT_CTRL,$conn['SUBHOST']));
				
			}
			else
			{
				$devList = $c->queryAll('PHYDEV,SUBHOST,VER,PHYADDR,LOGICADDR,LOGICID,TMSI',
								'STATUS=? AND POWERTYPE=? AND SUBHOST=?',
								array(DEV_STATUS_RUN,POWER_BAT_CTRL,$conn['SUBHOST']));
			}
			foreach( $devList as &$dev )
			{
				$GLOBALS['dstpSoap']->setModule('home','if');
				$GLOBALS['dstpSoap']->sendMsg($dev,DEV_CMD_HIC_CTRL_DEV,array());			
			}
		}
	}

	//给所有zigbee相关连接发送hicid
	static function sendHICID()
	{
		static $hicid = 0;

		//获取HICID
		if( !validID($hicid) )
		{
			$c     = new TableSql('hic_hic','ID');
			$hicid = intval( $c->queryValue('ID') );
		}

		//激活时，需要检测zigbee通讯是否正常，要求至少要有一个设备加入
		//但这时的hicid还不存在，新协调器程序对于非法hicid，会设置一个让设备无法加入
		//的特殊panid，导致该检测无法通过
		//如果hicid非法且还未激活，需要发送一个合法的hicid，保证协调器能生成正常panid让检测通过
		if( !validID($hicid) )
		{
			$GLOBALS['dstpSoap']->setModule('local','sn');
			if( false == $GLOBALS['dstpSoap']->getSN() )
			{
				$hicid = 93;//一个随机的hicid
			}
		}
		
		//是否强制要求复位
		$resetflag = Cache::get('zigbeeresetflag');
		if( false !== $resetflag )
		{
			$hicid = 0;
		}

		foreach( self::$connList as &$conn )
		{
			//这个正好做心跳.IP直连设备则无需发送
			if( PHYDEV_TYPE_ZIGBEE != $conn['PHYDEV'] )
			{
				continue;
			}
			
			if( isset( $conn['SUBATTR'] ) )
			{
				$subid = $conn['SUBATTR'];
			}
			else
			{
				$subid = $conn['SUBHOST'];
			}
			
			if( !validID($hicid) )
			{
				$subid = 0;
			}
			
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg($conn['SUBHOST'],
				DEV_CMD_SYS_HICID,
				array('hic'=>$hicid,'sub'=>$subid),0,$conn['PHYDEV']);
		}
	}
}


?>