<?php
//
class rtspAttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>NULL,'rep'=>1);
	static $page = 'rtsp'; 
	
	//摄像头会报状态，表示当前是否录像。但这个只是作为显示用，不做触发用，所以cfg中的r为0
	//static $unpackfmt = 'c';
	
	private static function getString(&$value,&$ret,$name)
	{
		$len = unpack('Clen',$value);
		$len = $len['len'];
		$ret[$name] = substr($value,1,$len);		
		$value = substr($value,1+$len);
		return;
	}
	
	static function sysMaintence()
	{
		//每天清除摄像头的鉴权token
		$c = new TableSql('homecammertoken');
		$c->del('STIME<?',array((time()-3600*2)));
	}

	
	static function delAttrNotice($attrid,$devid,$attrindex)
	{
		$c = new TableSql('homecammerdwd');
		$c->del('ATTRID=?',array($attrid));
		
		$c = new TableSql('homecammertoken');
		$c->del('ATTRID=?',array($attrid));
		
		//删除服务器上该摄像头的相关截图信息

		return true;
	}
	
	static function parseAdditonInfo($value,$attrid)
	{
		//长度：第一码流；长度；第二码流；长度：第三码流
		$value = $value['info'];

		$len = unpack('Clen',$value);
		$len = $len['len'];
		if( 0 != $len )//
		{
			$ret = array();
			self::getString($value,$ret,'c1');
			self::getString($value,$ret,'c2');
			self::getString($value,$ret,'c3');
			return $ret;
		}
		
		//0,区别于最早的摄像头，第一个字节为0表示附加信息按照下列格式
		//char,附加消息版本，1和2则为如下格式
		//char[15]，摄像头能力：转动，对讲，预置点,截图，录像
		//char, rtsp1len
		//char[rtsp1len], rtsp1 url
		//char, rtsp2len
		//char[rtsp2len], rtsp2 url
		//char, rtsp3len
		//char[rtsp3len], rtsp3 url
		//char, uidlen
		//char[uidlen], uid
		//char, userlen
		//char[userlen], user
		//char, pswlen
		//char[pswlen], psw
		
		$value = substr($value,1);
		$ret   = unpack('Cver/Czhuan/Cdj/Cyz/Cjt/Clx/C10blzd',$value);

		$value = substr($value,16);

		self::getString($value,$ret,'c1');
		self::getString($value,$ret,'c2');
		self::getString($value,$ret,'c3');

		self::getString($value,$ret,'uid');
		self::getString($value,$ret,'user');
		self::getString($value,$ret,'psw');

		return $ret;
	}
	
	private static function genCamerToken($camerid)
	{
		$name  = "rtsp-$camerid";
		$token = Cache::get( $name );
		if( false != $token )
		{
			return $token;
		}
		//生成一个随机鉴权ID给链接
		$c = new TableSql('homecammertoken');
		$token = mt_rand().mt_rand().mt_rand().mt_rand().mt_rand();
		$info = array();
		$info['ATTRID'] = $camerid;
		$info['STIME']  = time();
		$info['TOKEN']  = $token;
		$c->add($info);
		Cache::set($name,$token,3600);
		return $token;		
	}
	
	private static function getRtspUrl($id)
	{
		//首先判断是否HIC的位置，在以下情况里，给出的RTSP连接IP为HIC自身某个对外端口
		//1. 手机就在路由器下   (192.168.93.1:554)
		//2. HIC直接暴露在公网  (WANIP:554)
		//3. 手机和HIC有共同的外网地址。这个直接导向外网地址可能会有问题无法穿透  (WANIP:554)
		$hicid = HICInfo::getHICID(); 
		$token = self::genCamerToken($id);
		$ctrlhost   = 'jia.mn';
		$rtsphost   = NULL;
		$ctrlport   = HIC_SERVER_RTSPCTRL;
		$rtspport   = HIC_SERVER_RTSP;

		$ip = $_SERVER['REMOTE_ADDR'];
		if ( isset($_SERVER['HTTP_CLIENTIP']) )
		{
			$ip = $_SERVER['HTTP_CLIENTIP'];//BAE下remote_addr是内部地址
		}

		$ipint = intval(ip2long($ip));
		if( $ipint < ip2long('192.168.1.1') || $ipint > ip2long('192.168.255.255'))
		{
			$proxy = Cache::get("ProxyServer");
			if( false == $proxy )
			{
				$GLOBALS['dstpSoap']->setModule('app','hic');
				$proxy   = $GLOBALS['dstpSoap']->getProxyServer();
				Cache::set("ProxyServer",$proxy,86400*3);
			}
			if( NULL != $proxy )
			{
				$rtsphost = $proxy['PROXY'];
				$ctrlhost = $proxy['PROXY'];
				$rtspport = $proxy['RTSPPORT'];
				$ctrlport = $proxy['RTSPCTRL'];
			}
		}
		
		//1.手机就在HIC后面，这个可以直接访问摄像头
		
		
		//获取码流配置
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($id));
		$cfg = unserialize($cfg);
		
		$ctrlurl = "$hicid@$id@$ctrlhost@$ctrlport@$token";
		
		//使用第三方app的处理
		if( isset($cfg['ver']) )
		{
			$uid  = $cfg['uid'];
			$user = $cfg['user'];
			$psw  = $cfg['psw'];
			if (intval($cfg['ver']) == 3) {
				//大云智联摄像头
				return "icarertsp://$uid@$user@$psw@$ctrlurl";
			} else {
				return "hicrtsp://$uid@$user@$psw@$ctrlurl";
			}
		}
		
		//标准rtsp处理
		if( $ipint > ip2long('192.168.1.1') && $ipint <= ip2long('192.168.255.255'))	
		{
			if( false == $cfg )
			{
				$cfg = array(
					'c1'=>'xxx/1/h264major',
					'c2'=>'xxx/1/h264minor',
					'c3'=>'xxx/1/h264minor'
				);
			}

			$c = new TableSql('homeattr','ID');
			$devid = $c->queryValue('DEVID','ID=?',array($id));
			if(!validID($devid))
			{
				return false;
			}

			$c  = new TableSql('homedev','ID');
			$camerip = $c->queryValue('LOGICADDR','ID=?',array($devid));
			if ( NULL == $camerip )
			{
				return false;
			}

			if( isset($GLOBALS['allremote'] ))
			{
				$rtspurl = str_replace('xxx',$camerip,$cfg['c3']);
			}
			else
			{
				$rtspurl = str_replace('xxx',$camerip,$cfg['c1']);
			}
			return "wdnrtsp://$rtspurl@$ctrlurl"; //海思方案，固定该地址
		}
		
		return "wdnrtsp://$rtsphost:$rtspport/$token/$id/$hicid/@$ctrlurl";		
	}

	static function getViewInfo($value,$id)
	{
		if( (false !== strpos($_SERVER['PHP_SELF'],'group.php')))
		{
			return unserialize($value);
		}
		
		return self::getRtspUrl($id);
	}


	//把设备上报的状态信息转为数据库信息
	//摄像头报告状态时直接回当前SSID信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		//新摄像头的心跳无需这儿再回复，现在接到wifi的消息包就马上回复心跳了
		if( '_' == $value )
		{
			return false;
		}
		$cmd = unpack('Ccmd',$value);
		$cmd = $cmd['cmd'];
		switch($cmd)
		{
			case 0:
			case 1:
				//这个表示是否录像状态
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($attrid,NULL);		
				return $cmd;
				break;
			case 5:
				//swver:
				//1.0.0:最开始版本，hxws
				//1.1.0:修正修改WIFI设置会把flash写满的问题，hxws
				
				//hwver：
				//V1.0.0 hxws

				//报告自己的硬件版本与软件版本。更新到数据库中存储
				$info = unpack("Ccmd/Z20hwver/Z20swver",$value);
				$c = new TableSql('homeattr','ID');
				$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
				$cfg = unserialize($cfg);
				$cfg['hwver'] = $info['hwver'];
				$cfg['swver'] = $info['swver'];
				$info = array();
				$info['ID']      = $attrid;
				$info['CFGINFO'] = serialize($cfg);
				$c->update($info);

				return false;
				break;
		}
		return false;
	}
	
	private static function converCMDInfo($value,$attrid)
	{
		//根据附加信息的版本好，决定返回值格式
		//附加信息版本为1的程序，把不同命令用index来代替了。因为当时摄像头只有一个属性这样简单处理没问题
		//但index应该用来指示属性，而不是用来指示功能，所以新修改的版本在命令中添加一个字节指示功能
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( (!isset($cfg['ver'])) || ( 1 == $cfg['ver'] ) )
		{
			return $value;
		}
		return pack('C',$value['index']).$value['value'];
	}

	//如果控制字为空时，表示报告状态时的处理，需要把index改为0
	static function getCMDInfo($value,$id)
	{
		//这个表示下发ssid
		if( NULL == $value )
		{
			$value = array();
			$value['index'] = 0;
			include_once('uci/uci.class.php');	
			$ssid = SSID::getSSID();
			$value['value'] = pack("Z32Z10Z33",trim($ssid['name']),trim($ssid['encryption']),trim($ssid['password']));
			return self::converCMDInfo($value,$id);
		}

		//这个表示截图
		if( 'prtsc' ==  $value )
		{
			$value = array();
			$value['index'] = 2;
			$value['value'] = NULL;
			return self::converCMDInfo($value,$id);
		}

		if( is_array($value) )
		{
			$info = $value;
		}
		else
		{
			$info = unserialize($value);
		}
		
		if( is_array($info) )
		{
			$value = array();
			switch($info['op'])
			{
				case 'zhuan':
					$value['index'] = 1;
					$value['value'] = pack('C',$info['info']);
					break;
				case 'prtsc': //截图
					$value['index'] = 2;
					$value['value'] = NULL;
					break;
				case 'menling': //门铃
					//发送门铃通知
					include_once('b/homeLang.php');
					$rtspinfo = array();
					$rtspinfo['TITLE']       = ATTRCFG_CAMER_CALLING;
					$rtspinfo['DESCRIPTION'] = ATTRCFG_CAMER_CALLING;
					$rtspurl = self::getRtspUrl($id);
					$rtspurl = "$rtspurl@http://jia.mn/UI/indexstatus.html?rtspid=$id";
					//$GLOBALS['dstpSoap']->setModule('app','push');
					//$GLOBALS['dstpSoap']->sendNotice($rtspinfo,HICInfo::getHICID(),$rtspurl);
					// include_once('plannedTask/PlannedTask.php');
					// $planTask = new PlannedTask('delay','push');
					// $planTask->sendNotice($rtspinfo,HICInfo::getHICID(),$rtspurl);
					return false;
					break;
				case 'xh':  //开始巡航。	
					$info['action'] = 0;
					$info['info']   = 97;
					//break;  //巡航实际是发送指定预置点.直接走下一个分支
				case 'pos': //预置位相关操作
					$value['index'] = 3;
					//action:0:转到预置点，1：设置，2.清除
					//info:预置点，从0开始
					
					if( 0 != $info['action'] )
					{
						$c = new TableSql('homecammerdwd');
						$c->del('ATTRID=? AND DWD=?',array($id,$info['info']));						
						if( 1== $info['action'] )
						{
							$dwdb = array();
							$dwdb['ATTRID'] = $id;
							$dwdb['DWD']    = $info['info'];
							$dwdb['NAME']   = trim($info['dwdname']);
							$c->add($dwdb);							
						}
						noticeAttrModi($id);
						statusNotice('dict');
					}
					$value['value'] = pack('cc',$info['action'],$info['info']);
					break;
				case 'record': //录像相关操作
					$value['index'] = 4;
					//action:0，停止录像，1，开始录像
					$value['value'] = pack('c',$info['action']);
					break;
				case 'dj': //录像相关操作
					$value['index'] = 5;
					$value['value'] = pack('N',strlen($info['info'])).$info['info'];
					break;
				case 'update':
					$value['index'] = 6;
					$value['value'] = pack('a100a20la32',
								$info['info']['path'],
								$info['info']['host'],
								$info['info']['port'],
								strtolower($info['info']['md5'])
								);
					break;					
				default:
					$value = false;
					break;
			}
			return self::converCMDInfo($value,$id);
		}

		//这个分支表示转动控制等命令。这个命令是APP传来的，已经是二进制数据，所以无需pack
		return self::converCMDInfo($value,$id);
	}

	static function getOtherInfo($value,$id)
	{
		$other = array();
		$c = new TableSql('homecammerdwd');
		$other['dwd'] = $c->queryAll('*','ATTRID=? AND NAME IS NOT NULL ORDER BY DWD',array($id));
		return $other;
	}
	//替换摄像头的时候相应的易至点和随机鉴权字串也要替换
	static function replaceAttrNotice($oldid,$newid)
	{
		$c = new TableSql('homecammertoken');
		$result = $c->queryAll('*',"ATTRID in ($oldid,$newid)");
		foreach($result as $key => $value)
		{
			$info = array();
			$info['ATTRID'] = $value['ATTRID'] == $oldid ? $newid : $oldid;
			$c->update($info,null,'ATTRID=? AND STIME=? AND TOKEN=?',array_values($value));
		}
		$c = new TableSql('homecammerdwd');
		$result = $c->queryAll('*',"ATTRID in ($oldid,$newid)");
		foreach($result as $key => $value)
		{
			$info = $value;
			$info['ATTRID'] = $value['ATTRID'] == $oldid ? $newid : $oldid;
			$c->update($info,null,'ATTRID=? AND DWD=? AND NAME=?',array_values($value));
		}
	}
	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'dz'=>array('左','右','上','下','抬头','截图','抓拍','转'));
	static function getYuyinDict($id)
	{
		$ret = array();
		$c = new TableSql('homecammerdwd');
		$modes = $c->queryAllList('NAME','ATTRID=?',array($id));
		if( NULL == $modes )
		{
			return $ret;
		}

		foreach($modes as &$mode)
		{
			if( NULL == $mode )
			{
				continue;
			}
			$ret[] = array('word'=>$mode,'attr'=>'dwd');
		}

		return $ret;
	}	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$ret  = false;
		$info = NULL;
		$dz   = false;
		
		$c = new TableSql('homecammerdwd');
		foreach($yuyin as &$word)
		{
			$dwd = in_array('dwd',$word['attr']);
			if(!$dwd)
			{
				continue;
			}
			//检测是否该摄像头的定位点
			$dwdinfo = $c->query('*','ATTRID=? AND NAME=?',array($attrid,$word['word']));
			if( NULL == $dwdinfo )
			{
				continue;
			}

			$cmd = array();
			$cmd['op']     = 'pos';
			$cmd['action'] = 0;
			$cmd['info']   = $dwdinfo['DWD'];

			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,$cmd);
			return array('ret'=>true,'info'=>'转到指定点');
	
		}
		foreach($yuyin as &$word)
		{
			$dz = in_array('dz',$word['attr']);
			if(!$dz)
			{
				continue;
			}
			$cmd = array();
			$cmd['op']     = 'zhuan';
			$cmd['info']   = -1;

			$ret = true;
			switch( $word['word'] )
			{
				case '上':
				case '抬头':
					$info = '上转';
					$cmd['info']   = 0;
					break;
				case '下':
					$info = '下转';
					$cmd['info']   = 1;
					break;
				case '左':
					$info = '左转';
					$cmd['info']   = 2;
					break;
				case '右':
					$info = '右转';
					$cmd['info']   = 3;
					break;
				case '转':
					$info = '继续转';
					$cmd['info']   = $_SESSION["rtsp-$id-dz"]['info'];
					break;
				case '截图':
				case '抓拍':
					$info = '截图';
					$cmd = 'prtsc';
					break;
				default:
					$ret = false;
					break;
			}
			if($ret)
			{
				$_SESSION["rtsp-$id-dz"] = $cmd;
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($attrid,$cmd);
			}
			else
			{
				$info = YUYIN_OP_CMDFAIL;
			}
			break;
		}
		if(!$dz)
		{
			$info = YUYIN_OP_CMDFAIL;
		}
		return array('ret'=>$ret,'info'=>$info);
	}	
}

?>