<?php
/*
	协议包头。
	WIFI设备实际不使用这三个包头信息，而是由socket连接信息获取。但出于代码一致性
	发送和接受结构中仍然保持这些信息
		char   phyaddr[8]    PHYADDR
		char   logicaddr[2]  LOGICADDR
		char   rssi[1]       RSSI

	HIC包头
		char  seq        seq
		char  secury[8]  key
		uchar cmd        cmd
	msg
		attr解析部分
*/


//系统内部协议
class sysifInterFace
{
	//根据HIC头部，设备信息，消息内容，构造发送
	static function sendMsg(&$devinfo,$cmd,&$msg)
	{
		$addr = unserialize($devinfo['PHYADDR']);
		if( !is_string($addr['m']) || !is_string($addr['s']) || !is_string($addr['f']) )
		{
			//后面调用经常发现有时addr不是字串
			return; 
		}
		$GLOBALS['dstpSoap']->setModule($addr['m'], $addr['s']);
		return $GLOBALS['dstpSoap']->{$addr['f']}($cmd,$msg,$devinfo['ID']);
	}
}


class hicMsg
{
	//把php消息转换为二进制码流。消息结构看命令定义地方
	static function genDevMsg($cmd,&$msg,$phydev)
	{
		switch ( $cmd )
		{
			case DEV_CMD_HIC_CTRL_DEV:
				$info  = NULL;
				
				//24G由于包长度有限，取消比较容易去掉的一个休眠时间控制字节
				if( PHYDEV_TYPE_24G != $phydev )
				{
					if( Cache::get('mssleep') )
					{
						$stime = Cache::get('mssleep');
						Cache::del('mssleep');
					}
					else
					{
						$stime = Cache::get('devsleep');
						if ( false == $stime )
						{
							$stime = 4; //没有登陆时的休眠时间
						}					
					}
					$info  = pack('v',$stime*1000);
				}
				$info .= pack('C',count($msg));
				foreach( $msg as &$ctrl )
				{
					$info .= pack('C',$ctrl['ATTRINDEX']);
					$info .= pack('C',strlen($ctrl['SENDATTR']));
					$info .= $ctrl['SENDATTR'];
				}
				$msg = $info;
				break;
			case DEV_CMD_HIC_CONFIRM:
				$msg = pack('lll',$msg['LOGICID'],$msg['CHID'],$msg['HCID']);
				break;
			case DEV_CMD_SYS_RM_DEV_ASSOC:
				$msg = pack('H16', $msg);
				break;
			case DEV_CMD_SYS_HICID:
				$msg = pack('ll',$msg['hic'],$msg['sub']);
				break;
			case DEV_CMD_DEV_CHECKHICID_RSP:
				$msg = pack('l',$msg);
				break;
			case DEV_CMD_DEV_ATTR_CONF_RSP:
				$msg = pack('C',$msg);
				break;
			case DEV_CMD_HIC_GROUP_CTRL_DEV:
				$msg = pack('vC',$msg['gid'],strlen($msg['msg'])).$msg['msg'];
				break;
			case DEV_CMD_HIC_GROUP_DEV:
				$msg = pack('vCCCC',$msg['gid'],$msg['index'][0],$msg['index'][1],$msg['index'][2],$msg['index'][3]);
				break;
			case DEV_CMD_HIC_GET_ATTRLIST: //空
			case DEV_CMD_HIC_JOIN_CONFIRM: //空
			case DEV_CMD_HIC_GET_STATUS:   //空
			case DEV_CMD_HIC_GET_MAC:      //空
			default:
				break;
		}
		return $msg;
	}
	
	//把二进制码流转为PHP数组。消息结构看命令定义地方
	static function genHICMsg($cmd,&$msg,&$header)
	{
		switch ( $cmd )
		{
			case DEV_CMD_DEV_STATUS:
			case DEV_CMD_DEV_GETCMD://同status
				if(NULL==$msg)
				{
					break;
				}
				$num = unpack('Cnum',$msg);
				$num = $num['num'];
				$statusList = array();
				$sPos = 1;
				for($i=0; $i<$num;$i++)
				{
					$status           = unpack('C1ATTRINDEX/C1LEN',substr($msg,$sPos));
					$status['STATUS'] = substr($msg,$sPos+2,$status['LEN']);
					$statusList[]   = $status;
					$sPos = $sPos+2+$status['LEN'];
				}
				$msg = $statusList;
				break;
			case DEV_CMD_DEV_ADD:
				if( isset($header['PHYADDR']) )
				{
					if( PHYDEV_TYPE_24G == $header['PHYDEV'] )
					{
						$msg = unpack('a12NAME/a2VER',$msg);
						$msg['ISPOWER'] = 0xff;
						$msg['VER']     = '1.2';
						$msg['SN']      = '123';
					}
					else
					{
						$msg = unpack('a30NAME/a30SN/a3VER/C1ISPOWER',$msg);
					}
				}
				else //如果添加消息头没物理地址的，则在该消息中需要添加物理地址
				{
					$msg = unpack('a30NAME/a30SN/a3VER/C1ISPOWER/H16PHYADDR',$msg);
					$header['PHYADDR'] = $msg['PHYADDR'];
					unset($msg['PHYADDR']);
				}
				$msg['NAME'] = trim($msg['NAME']);
				$msg['SN']   = trim($msg['SN']);
				$msg['VER']  = trim($msg['VER']);
				break;
			case DEV_CMD_DEV_REPORT_SN:
				$a   = unpack("H*",$msg);
				$msg = array();
				$msg['SN']   = $a[1];
				$msg['RSSI'] = $header['RSSI'];
				break;
			case DEV_CMD_DEV_JOIN:
			case DEV_CMD_DEV_JOIN1:
				$num = unpack('Cnum',$msg);
				$num = $num['num'];
				$attrList = array();
				for($i=0; $i<$num;$i++)
				{
					if( DEV_CMD_DEV_JOIN == $cmd )
					{
						$attr = unpack('C1ATTRINDEX/a30NAME/a10SYSNAME',substr($msg,1+$i*41,41));
					}
					else
					{
						$attr = unpack('C1ATTRINDEX/a18NAME/a6SYSNAME',substr($msg,1+$i*25,25));
					}
					$attr['NAME']    = trim($attr['NAME']);
					$attr['SYSNAME'] = trim($attr['SYSNAME']);
					$attrList[] = $attr;
				}
				$msg = $attrList;
				break;
			case DEV_CMD_DEV_JOIN2:
				$len  = unpack('Clen',$msg);
				$len  = $len['len'];
				$sn   = substr($msg,1,$len);
				$msg  = substr($msg,1+$len);
				$num = unpack('Cnum',$msg);
				$num = $num['num'];
				$msg  = substr($msg,1);
				$attrList = array();
				for( $i = 0; $i < $num; $i++ )
				{
					$attr = array();
					$attr['ATTRINDEX'] = $i;
					$attr['SYSNAME']   = $sn;

					$len  = unpack('Clen',$msg);
					$len  = $len['len'];
					$name = substr($msg,1,$len);
					$msg  = substr($msg,1+$len);				
					$attr['NAME'] = $name;
					
					$attrList[] = $attr;
				}
				$msg = $attrList;
				break;
			case DEV_CMD_DEV_JOIN3:
				//这个现在是针对24G芯片设定的，后面其它芯片的长度可以放宽
				$msg = unpack('Cnum/a9NAME/a4SYSNAME',$msg);
				$attrList = array();
				for( $i = 0; $i < $msg['num']; $i++ )
				{
					$msg['ATTRINDEX'] = $i;
					if( 'cd' == trim($msg['SYSNAME']) )//
					{
						$msg['SYSNAME'] = 'color';
					}
					$attrList[] = $msg; 
				}
				$msg = $attrList;
				break;
			case DEV_CMD_DEV_LEAVE://空
				break;
			case DEV_CMD_DEV_MAC_CONF:	
				if( !isset($header['PHYADDR']) )
				{
					$mac = unpack('H16PHYADDR',$msg);
					$header['PHYADDR'] = $mac['PHYADDR'];
				}
				break;
			case DEV_CMD_SYS_DEV_JOIN:
			case DEV_CMD_SYS_DEV_JOIN1:
				$mac = unpack('H16PHYADDR',$msg);
				$header['JOINPHYADDR'] = $mac['PHYADDR'];
				if( DEV_CMD_SYS_DEV_JOIN == $cmd )
				{
					//逻辑地址非正确地址，不能用来更新
					$header['LOGICADDR'] = 0;
				}
				else //DEV_CMD_SYS_DEV_JOIN1
				{
					$header['PHYADDR'] = $mac['PHYADDR'];
				}
				break;
			case DEV_CMD_SYS_HICIDCONFIRM:
				break;
			case DEV_CMD_DEV_ATTR_CONF:
				$ret = unpack('Cindex/Clen',$msg);
				$ret['info'] = substr($msg,2);
				if( 14 == $ret['index'] ) //表示布局配置信息
				{
					$cfg = unpack('Cver/Csubnum/Cmainnum',$ret['info']);
					$num = $cfg['subnum'];
					$sPos = 3;
					$statusList = array();

					//获取附加属性布局方式
					for($i=0; $i<$num;$i++)
					{
						$status  = unpack('CATTRINDEX/CLAYOUT/CMAININDEX',substr($ret['info'],$sPos));
						if( $status['LAYOUT'] > 60 ) //这个表示tips的长度，不是layout
						{
							$len  = $status['LAYOUT']-60;
							$status['TIPS'] = substr($ret['info'],$sPos+3,$len);
							$sPos = $sPos+$len;
						}
						$sPos = $sPos+3;
						$statusList[]   = $status;
					}
					//获取主属性名称
					$num = $cfg['mainnum'];
					for($i=0; $i<$num;$i++)
					{
						$status  = unpack('CATTRINDEX/CNLEN/CPLEN/CTLEN',substr($ret['info'],$sPos));
						$len = $status['NLEN'];
						if( $status['NLEN'] > 60 )
						{
							$status['NLEN'] = $status['NLEN'] - 60;
							$status['NAME'] = substr($ret['info'],$sPos+4,$status['NLEN']);
							$status['PAGE'] = substr($ret['info'],$sPos+4+$status['NLEN'],$status['PLEN']);
							$status['TIPS'] = substr($ret['info'],$sPos+4+$status['NLEN']+$status['PLEN'],$status['TLEN']);
							$sPos = $sPos+4+$status['NLEN']+$status['PLEN']+$status['TLEN'];
						}
						else
						{
							$status['NAME'] = substr($ret['info'],$sPos+2,$status['NLEN']);
							$sPos = $sPos+2+$status['NLEN'];
						}
						$statusList[]   = $status;
					}
					
					$ret['info'] = $statusList;					
				}
				if( 15 == $ret['index'] ) //表示图标信息
				{
					$num = unpack('Cnum',$ret['info']);
					$num = $num['num'];
					$statusList = array();
					$sPos = 1;
					for($i=0; $i<$num;$i++)
					{
						$status  = unpack('CATTRINDEX/CLEN',substr($ret['info'],$sPos));
						$len = $status['LEN'];
						$status['ICON'] = substr($ret['info'],$sPos+2,$status['LEN']);
						$icon = unpack("a$len",$status['ICON']);
						$status['ICON'] = trim($icon[1]);
						$statusList[]   = $status;
						$sPos = $sPos+2+$status['LEN'];
					}
					$ret['info'] = $statusList;					
				}
				$msg = $ret;
				break;
			case DEV_CMD_DEV_ATTR_ICON:
				if(NULL==$msg)
				{
					break;
				}
				$num = unpack('Cnum',$msg);
				$num = $num['num'];
				$statusList = array();
				$sPos = 1;
				for($i=0; $i<$num;$i++)
				{
					$status           = unpack('C1ATTRINDEX/C1LEN',substr($msg,$sPos));
					$status['ICON']   = substr($msg,$sPos+2,$status['LEN']);
					$statusList[]     = $status;
					$sPos = $sPos+2+$status['LEN'];
				}
				$msg = $statusList;
				break;
			case DEV_CMD_DEV_GROUP_CONF:
				$ret = unpack('vindex',$msg);
				$msg = $ret['index'];
				break;
			default://空
				break;
		}
		return $msg;
	}

	//接到消息后的处理
	static function procMsg(&$header,$id,$cmd,&$msg)
	{
		switch ( $cmd )
		{
			case DEV_CMD_DEV_ADD:
				$msg['PHYDEV']    = $header['PHYDEV'];
				$msg['SUBHOST']   = $header['SUBHOST'];
				$msg['PHYADDR']   = $header['PHYADDR'];
				$msg['LOGICADDR'] = $header['LOGICADDR'];
				$msg['RSSI']      = $header['RSSI'];

				$GLOBALS['dstpSoap']->setModule('home','end');
				$id = $GLOBALS['dstpSoap']->recordEnd($msg);
				if( !validID($id) )
				{
					break;
				}
				//新IP设备刚连接上来时，是不存在设备ID
				//而IP设备的subhost直接指定为设备ID。所以这儿需要修改其相关信息
				if( -1 == $msg['SUBHOST'] )
				{
					$devinfo = array();
					$devinfo['ID']      = $id;
					$devinfo['SUBHOST'] = $id;
					$c = new TableSql('homedev','ID');
					$c->update($devinfo);
					
					//修改HICMsgProc中的记录
					if ( property_exists('HICMsgProc', 'phyList') )
					{
						foreach( HICMsgProc::$phyList as $phy=>$connid )
						{
							if( $phy == $header['PHYADDR'] )
							{
								HICMsgProc::setConnHost($connid,HICInfo::getHICID(),$id,PHYDEV_TYPE_IP);
							}
						}
					}
				}
				break;
			case DEV_CMD_DEV_JOIN:
			case DEV_CMD_DEV_JOIN1:
			case DEV_CMD_DEV_JOIN2:
			case DEV_CMD_DEV_JOIN3:
				$GLOBALS['dstpSoap']->setModule('home','end');
				$GLOBALS['dstpSoap']->receiveConfirmAck($id,$msg);
				break;
			case DEV_CMD_DEV_LEAVE:
				break;
			case DEV_CMD_DEV_GETCMD:
				$GLOBALS['dstpSoap']->setModule('home','end');
				$GLOBALS['dstpSoap']->getDevCtrl($id,$msg);
				break;
			case DEV_CMD_DEV_STATUS:
				$GLOBALS['dstpSoap']->setModule('home','end');
				$GLOBALS['dstpSoap']->updateDevStatus($id,$msg);
				break;
			case DEV_CMD_DEV_ATTR_CONF:
				$GLOBALS['dstpSoap']->setModule('home','end');
				$GLOBALS['dstpSoap']->setDevConf($id,$msg);
				break;
			case DEV_CMD_DEV_ATTR_ICON:
				$GLOBALS['dstpSoap']->setModule('home','end');
				$GLOBALS['dstpSoap']->setAttrIcon($id,$msg);
				break;
			case DEV_CMD_DEV_GROUP_CONF:
				$GLOBALS['dstpSoap']->setModule('smart','devgroup');
				$GLOBALS['dstpSoap']->devGroupRsp($id,$msg);
				break;
			case DEV_CMD_DEV_CHECKHICID:
				$hicid = HICInfo::getHICID();
				$GLOBALS['dstpSoap']->setModule('home','if');
				$GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_DEV_CHECKHICID_RSP,$hicid);
				break;
			case DEV_CMD_DEV_REPORT_SN:
				$GLOBALS['dstpSoap']->setModule('home','end');
				$GLOBALS['dstpSoap']->updateDevSN($id,$msg);
			default:
				break;
		}
	}	
}
 
//HIC与终端的通信接口
class ifInterFace
{
	/* 系统设备的特殊发送消息接口
	 * 系统设备就没有解包等处理，直接发送到相应接口即可
	 * 输入参数：sender:系统设备设备信息
	 *           cmd:系统命令
	 * 			 msg:要发送的信息
	 */
	static function sendSysEndMsgToHIC(&$sender,$cmd,&$msg)
	{
		$header = array();
		$header['SUBHOST']  = 0;
		$header['LOGICADDR']= NULL;
		$header['PHYDEV']   = PHYDEV_TYPE_SYS;
		$header['PHYADDR']  = serialize($sender);
		
		$GLOBALS['dstpSoap']->setModule('home','end');
		$id = $GLOBALS['dstpSoap']->getDevidFromAddr($header['PHYADDR'], NULL);
		
		return hicMsg::procMsg($header,$id,$cmd,$msg);
	}

	/* 向协调器发送的系统管理信息
	 * 输入参数：cmd:系统命令
	 * 			 msg:要发送的信息
	 */
	static function sendDevSySMsg($host,$cmd,$msg,$logicaddr=0,$phydev=PHYDEV_TYPE_ZIGBEE)
	{
		//构造消息发送
		$info = array();
		$info['PHYDEV']    = $phydev;
		$info['PHYADDR']   = '0000000000000000';
		$info['LOGICADDR'] = $logicaddr;
		$info['SUBHOST']   = $host;
		$info['TMSI']      = 0;
		$info['VER']       = '1.2';
		//$info['RSSI']    = 0;	

		return self::sendMsg($info,$cmd,$msg);
	}

	//广播消息接口
	static function sendMsgToGroup($host,$gid,$msg,$phydev=PHYDEV_TYPE_ZIGBEE)
	{
		$info = array();
		$info['gid'] = $gid;
		$info['msg'] = $msg;
		return self::sendDevSySMsg($host,DEV_CMD_HIC_GROUP_CTRL_DEV,$info,0,$phydev);
	}

	
	//往指定端口发送socket数据，发送完就关闭
	static function sendMsgBySocket($port,$info,$server='127.0.0.1')
	{
		//发送数据给串口代理进程
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($socket < 0) 
		{
			return false;
		}
		$result = socket_connect($socket, $server, $port);
		if ($result < 0) 
		{
			socket_close($socket);
			return false;
		}
		
		//关闭连接后，系统内核尽可能的发送未发送完的数据
		$arrOpt = array('l_onoff' => 0, 'l_linger' => 1);
		socket_set_option($socket, SOL_SOCKET, SO_LINGER, $arrOpt);
		
		socket_write($socket, $info);

		socket_shutdown($socket, 2);
		socket_close($socket);
		
		return true;
	}

	//向命令发送消息
	//$dev:如果是数字，表示设备ID，否则为已经读取的设备信息
	static function sendMsg($dev,$cmd,$msg=NULL)
	{
		if ( is_numeric( $dev ) )
		{
			$c = new TableSql('homedev','ID');
			$dev = $c->query('ID,PHYDEV,SUBHOST,VER,PHYADDR,LOGICADDR,LOGICID,TMSI', 
								'ID=?',array($dev));
		}
		if ( NULL == $dev )
		{
			return false;
		}
		
		//系统设备先简单处理下。后续系统设备相关的也修改为二进制流数据方便处理
		if (PHYDEV_TYPE_SYS == $dev['PHYDEV'])
		{
			return sysifInterFace::sendMsg($dev,$cmd,$msg);
		}
		
		
		$msg = hicMsg::genDevMsg($cmd,$msg,$dev['PHYDEV']);
		
		//根据host查找是直接调用还是通过socket接口发送
		//很多问题反应，小当可能有部分不处理，但APP是可以
		//怀疑是这部分判断在某些情况下有误，暂时注释掉
		//if ( property_exists('HICMsgProc', 'hostList') )
		//{
		//	if( isset( HICMsgProc::$hostList[ $dev['PHYDEV'] ][ $dev['SUBHOST'] ] ) )
		//	{
		//		return HICMsgProc::sendMsg($dev,$cmd,$msg);
		//	}
		//}
		if( 'b' == HIC_LOCAL )
		{
			//通过socket接口发送
			$map  = Cache::get('subhostport');
			$port = $map[ $dev['SUBHOST'] ][ $dev['PHYDEV'] ];
			if(!validID($port))
			{
				return false;
			}
			$server = '127.0.0.1';
		}
		else
		{
			//这个需要获取当前的ip和端口
			$itemid = $GLOBALS['SYSDB']['ITEMID'];
			$c      = new TableSql('hic_hicitem','ID');
			$item   = $c->query('SERVER,DEVSPORT','ID=?',array($itemid));
			$port   = $item['DEVSPORT'];
			$server = $item['SERVER'];
		}
		$info = array();
		$info['dev'] = $dev;
		$info['cmd'] = $cmd;
		$info['msg'] = $msg;
		$info = serialize($info);
		return self::sendMsgBySocket($port,$info,$server);
	}
	
	//接到消息后的处理
	static function procMsg(&$header,$id,$cmd,&$msg)
	{
		$msg = hicMsg::genHICMsg($cmd,$msg,$header);
		
		return hicMsg::procMsg($header,$id,$cmd,$msg);
	}

}
?>