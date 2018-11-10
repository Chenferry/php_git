<?php
class yzbjConn
{
	static $init=false;
	static $attrConn=array();
	static $connAttr=array();
	
	////////////////////////////////////////////////////
	static function init()
	{
		self::$init = true;
		
        //设定定时器，5秒发送心跳包
        server::startTimer(array(__CLASS__, 'keepliveProc'),1000000*5);	
	}
	
	static function onRead($id,&$info)
	{
		//每次读到消息，都相当于一次心跳
		self::$attrConn[ self::$connAttr[$id] ]['live'] = 5;
		
		while(  NULL !== ($msg = server::getInfo($info,"\r\n")) )
		{
			//检查消息合法性

			$len = unpack('Nlen',substr($msg,2,4));
			$len = $len['len'];
			$cmd = ord($msg[6]);
			
			switch($cmd)
			{
				case 0xE4://当前状态
					$rsp = unpack('Z'.$len.'json', substr($msg,7,$len));
					$rsp = json_decode($rsp['json'],true);
					self::playStatus(self::$connAttr[$id],$rsp);
					break;
				case 0xD8://播放列表
					$rsp = unpack('Z'.$len.'json', substr($msg,7,$len));
					$rsp = json_decode($rsp['json'],true);
					self::playList(self::$connAttr[$id],$rsp);
					break;
				case 0xD0://心跳
				default:
					break;
			}

		}

	}

    static function onClose($id)
	{
		$attrid = self::$connAttr[$id];
		unset(self::$connAttr[$id]);
		unset(self::$attrConn[$attrid]);
	}
	
	/////////////////////////////////
	//当前播放状态
	private static function playStatus($attrid,&$info)
	{
		//Array
		//(
		//	[alumid] => 14
		//	[duration] => 415296
		//	[musicid] => 181
		//	[playing] => 0
		//	[time] => 259265
		//	[artist] => Alexsandro DaSilva
		//	[name] => Dacota Sunlight
		//	[path] => /storage/emulated/0/æ­Œæ›²/05 youzhuan.mp3
		//	[ex_FlagLocal] => 8
		//	[ex_EQMode] => 1
		//	[ex_PlayMode] => 3
		//	[ex_VolumeMax] => 30
		//	[ex_VolumeCur] => 15
		//	[ex_Mute] => 0
		//)
		if($info['ex_FlagLocal'] == 8)	$info['ex_FlagLocal'] = 0;
		Cache::set('bjyys_'.$attrid,$info,60);
	}
	//获得播放列表
	private static function playList($attrid,&$info)
	{
		$yymap = array('local'=>0,'usb'=>2,'sd'=>1,'favorite'=>6);
		if( !isset( $yymap[strtolower($info['name'])] ) )
		{
			return;
		}
		$list = array();
		foreach( $info['list'] as &$l )
		{
			$list[ $l['id'] ] = $l['name'];
		}
		$playlist = Cache::get('bjyylist_'.$attrid);
		if( false == $playlist ) $playlist = array();
		$playlist[ $yymap[strtolower($info['name'])] ] = $list;
		Cache::set('bjyylist_'.$attrid,$playlist);
	}
	
	/////////////////////////////////
	//构造心跳消息包内容
	static function getKeeplivepack($attrid=NULL)
	{
		$info = NULL;
		$msg = self::packMsginfo(0xD0,$info);
		self::sendtoAttr($attrid,$msg);
	}

	//请求获取播放列表消息包
	static function getPlayList($attrid=NULL)
	{
		$info = NULL;
		$msg = self::packMsginfo(0xD7,$info);
		self::sendtoAttr($attrid,$msg);
	}
	//发送播放控制:播放，暂停，上一首，下一首，音量大，音量小
	static function sendPlayctrl($cmd,$attrid=NULL)
	{
		$cmd['value'] =  intval($cmd['value']);
		$info = NULl;
		switch( $cmd['m'] )
		{
			case 'group':
				$newcmd = array();
				$newcmd['m']     = 'yx';
				$newcmd['value'] = $cmd['ex_EQMode'];
				self::sendPlayctrl($newcmd,$attrid);

				$newcmd['m']     = 'yl';
				$newcmd['value'] = $cmd['ex_VolumeCur'];
				self::sendPlayctrl($newcmd,$attrid);
				
				if( validID($cmd['musicid']) )
				{
					$newcmd['m']     = 'play';
					$newcmd['value'] = $cmd['musicid'];
					self::sendPlayctrl($newcmd,$attrid);
				}
				else
				{
					$newcmd['m']     = 'ctrl';
					$newcmd['value'] = $cmd['playing'];
					self::sendPlayctrl($newcmd,$attrid);
				}

				break;
			case 'mode': //播放模式
				$modemap=array(0=>17,1=>18,2=>15,3=>16);
				$info = '{"OPTION":{"PLAY_ACTIVITY_TAG":1,"music_option":'.$modemap[$cmd['value']].'},"ACTION":"action.music.intent.option"}';
				break;
			case 'yx': //EQ模式
				$eqmap = array( 0=>19,1=>23,2=>22,3=>20,4=>21 );
				$info =	'{"OPTION":{"PLAY_ACTIVITY_TAG":1,"music_option":'. $eqmap[$cmd['value']].'},"ACTION":"action.music.intent.option"}';
				break;
			case 'play': //播放指定歌曲,Value为ID
				$info = '{"OPTION":{"play_play_musicid":'.$cmd['value'].',"PLAY_ACTIVITY_TAG":1,"music_option":2000},"ACTION":"action.music.intent.option"}';
				break;
			case 'ctrl': //播放暂停上一首下一首 
				$info = '{"OPTION":{"PLAY_ACTIVITY_TAG":1,"music_option":'.$cmd['value'].'},"ACTION":"action.music.intent.option"}';
				break;
			case 'yl': //音量控制
				$cmd['value'] = intval($cmd['value']);//右转音量范围0-30
				$info = '{"OPTION":{"seekbar_position":'.$cmd['value'].',"PLAY_ACTIVITY_TAG":1,"music_option":2002},"ACTION":"action.music.intent.option"}';
				break;
			case 'yy': //音源控制
				if( 8 == $cmd['value'] )
				{
					return;
				}
				$localmap = array( 8=>11011,0=>11011,1=>9,2=>10,3=>7,6=>11013 );
				$info = '{"OPTION":{"PLAY_ACTIVITY_TAG":1,"music_option":'.$localmap[$cmd['value']].'},"ACTION":"action.music.intent.option"}';
				break;
		}
		$msg = self::packMsginfo(0xE1,$info);
		self::sendtoAttr($attrid,$msg);
	}
	
	/////////////////////////////////


	//根据消息内容进行组包
	private static function packMsginfo($cmd,$info)
	{
		static $seq = 1;
		$msg  = chr(0xE2).chr(0xE2).pack('N',(5+strlen($info) )).pack('C',$cmd).$info.pack('n',$seq++);
		//加校验码
		$msg .= chr(0x00).chr(0x00);//self::calccrc16($msg);
		//加停止位
		$msg .= chr(0x0D).chr(0x0A);

		return $msg;
	}
	private static function sendtoAttr($attrid,&$info)
	{
		if( NULL == $attrid )
		{
			return $info;
		}
		if( !isset( self::$attrConn[$attrid] ) )
		{
			return $info;
		}
		$cid = self::$attrConn[$attrid]['cid'];
		server::writeconn( $cid, $info );
		return NULL;
	}
	//当背景音乐有消息到达时
	static function onStatus($attrid)
	{
		//检查类是否已经初始化
		if( !self::$init )
		{
			self::init();
		}
		//检查是否已经有指定的
		if( isset( self::$attrConn[$attrid] ) )
		{
			return true;
		}
		//获取设备地址，和背景音乐建立连接
		$c = new TableSql('homeattr','ID');
		$devid = $c->queryValue('DEVID','ID=?',array($attrid));
		if( NULL == $devid )
		{
			return;
		}
		$c = new TableSql('homedev','ID');
		$ip = $c->queryValue('LOGICADDR','ID=?',array($devid));
		if( NULL == $ip )
		{
			return;
		}
        $addr  = "tcp://$ip:8982";
        $cid= server::setupConn('yzbjConn',$addr);
		if( !validID($cid) )
		{
			return false;
		}
		self::$attrConn[$attrid] = array();
		self::$attrConn[$attrid]['cid']   = $cid; 
		self::$attrConn[$attrid]['live']  = 5; 

		self::$connAttr[$cid] = $attrid;
		
		self::getPlayList($attrid);

		return false;
	}

	//定时发送心跳包
	static function keepliveProc()
	{
		$closeList = array();
		foreach(self::$connAttr as $cid=>$attrid)
		{
			self::$attrConn[$attrid]['live']--;
			if( 0 == self::$attrConn[$attrid]['live'] )
			{
				$closeList[] = $cid;
				continue;
			}
			self::getKeeplivepack($attrid);
		}
		foreach($closeList as $cid)
		{
			server::closeEventConn($cid);
		}
	}
	
	//定时获取播放列表
}

//右转背景音乐
class yzbjAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT);
	static $page = 'bjyy'; 
	static $name = DEV_SYSNAME_BJYY;
	
	function addAttrNotice($attrid)
	{
		$c = new TableSql('homeattr','ID');
		$info = array();
		$info['ID']   = $attrid;
		$info['ICON'] = 'bjyy';
		$c->update($info);
		return true;
	}	

	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$attrid=NULL)
	{
		return Cache::get('bjyys_'.$attrid);
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		if( 'status' == $value )
		{
			return pack('C',10);
		}
		if(is_array($value))
		{
			$value = serialize($value);
		}
		//如果连接还没建立，则返回不处理
		$info = "yzbj:$attrid@$value\n";
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendMsgBySocket(HIC_SERVER_DELAY,$info);
			
		return false;
	}

	//把设备上报的状ji态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$GLOBALS['dstpSoap']->setModule('devattr','attr');
		$GLOBALS['dstpSoap']->execAttr($attrid,'status');	

		//检查指定的连接是否已经建立，如果还没建立，则调用建立
		$r = yzbjConn::onStatus($attrid);
		if(!$r)
		{
			return false;
		}
		$status = Cache::get('bjyys_'.$attrid);
		if( false == $status )
		{
			return 0;
		}
		return $status['playing'];
	}
	
	static function getDetail($value,$id)
	{
		$playlist = Cache::get('bjyylist_'.$id);
		$a = array(
			'yy' => array(
				0=>ATTRCFG_BJYY_BENDI,
				1=>ATTRCFG_BJYY_SD,
				2=>ATTRCFG_BJYY_USB,
				//3=>'AUX',
				6=>ATTRCFG_BJYY_SHOUCANNG,
			),

			'yx' => array(
				0 => ATTRCFG_BJYY_PUTONG,
				1 => ATTRCFG_BJYY_GUDIAN,
				2 => ATTRCFG_BJYY_JUESHI,
				3 => ATTRCFG_BJYY_YAOGUN,
				4 => ATTRCFG_BJYY_LIUXING,
			),
			'bfms' => array(0,1,2,3),
			'pl'=>$playlist,
			'status' => array(
				0=>ATTRCFG_BJYY_ZANTING,
				1=>ATTRCFG_BJYY_BOFANG
			),
			'online'=>-1,
		);
		
		return $a;
	}

	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array(
			'fy' => array('有点','太','一点','点','一些'),
			'dz'=>array('第','最后','倒数','最后第','倒数第','上一首','下一首','换','换一','暂停','暂停播放','停止播放','停止','播放','打开','继续','继续播放','开始','开始播放','关闭','关掉','退出','随机','随机播放','单曲','单曲循环','顺序','顺序播放','循环','循环播放','普通','古典','爵士','摇滚','流行','大','吵','小','高','低','声音','音量','随便','随便放一','随便播放一','静音'),
	);
	static function getYuyinDict($id)
	{
		$ret = array();
		$playlist = Cache::get('bjyylist_'.$id);

		foreach($playlist as &$play)
		{
			if( NULL == $play )
			{
				continue;
			}
			foreach($play as $key=>&$value)
			{
				if( NULL == $value )
				{
					continue;
				}				
				$ret[] = array('word'=>$value,'attr'=>'song');
			}
		}
		return $ret;
	}

	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$value 	 =  array();		

		$ret  	 =  true;
		$volume  =  0;
		$adjust  =  0;
		$select  =  0;
		foreach($yuyin as &$word)
		{
			switch( $word['word'] )
			{
				case '打开':
				case '播放':
				case '开始':
				case '继续':
					$value = array(
						'm' => 'ctrl',
						'value' => '0',
					);
					$info = '开始播放';
					break;					
				case '暂停':
				case '停止':
				case '关闭':
				case '关掉':
				case '退出':
				case '暂停播放':
				case '停止播放':
					$value = array(
						'm' => 'ctrl',
						'value' => '1',
					);
					$info = '停止播放';
					break;					
				case '上一首':
				case '下一首':		
					$value['m'] = 'ctrl';
					$value['value'] = $word['word'] == '上一首' ? 2 : 3;
					$playlist = Cache::get('bjyylist_'.$attrid);
					$curstatus = Cache::get('bjyys_'.$attrid);
					$list = array_keys($playlist[$curstatus['ex_FlagLocal']]);
					$musicid = $curstatus['musicid'];
					$id = array_flip($list)[intval($musicid)];
					if( $word['word'] == '上一首' )
					{
						$curid = $id == 0 ? sizeof($list)-1 : $id-1; 			
					}
					else
					{
						$curid = $id == (sizeof($list)-1) ? 0 : $id+1; 								
					}
					$info = '接下来为您播放：'.$playlist[$curstatus['ex_FlagLocal']][$list[$curid]];
					break;					
				case '换':
				case '换一':
				case '随便放一':
				case '随便播放一':
					$playlist = Cache::get('bjyylist_'.$attrid);
					$all = array();
					foreach($playlist as $valve)
					{
						foreach($valve as $k=>$v)
						{
							$all[$k] = $v;
						}
					}
					$id = array_rand($all,1);
					$value = array(
						'm' => 'play',
						'value' => $id,
					);
					$info = '现在为您播放音乐：'.$all[$id];
					break;					
				case '第':
					$select = 1;
					$_SESSION['bj'] = 1;										
					break;	
				case '最后':
				case '倒数':
				case '最后第':
				case '倒数第':
					$select = -1;
					$_SESSION['bj'] = -1;										
					break;	
				case '顺序':
				case '顺序播放':
					$value = array(
						'm' => 'mode',
						'value' => '0',
					);
					$info = '已经为您切换到顺序播放模式';
					break;					
				case '随机':
				case '随机播放':
					$value = array(
						'm' => 'mode',
						'value' => '1',
					);
					$info = '已经为您切换到随机播放模式';
					break;					
				case '单曲':
				case '单曲循环':
					$value = array(
						'm' => 'mode',
						'value' => '2',
					);
					$info = '已经为您切换到单曲循环模式';
					break;					
				case '循环':
				case '循环播放':
					$value = array(
						'm' => 'mode',
						'value' => '3',
					);
					$info = '已经为您切换到循环播放模式';
					break;					
				case '普通':
					$value = array(
						'm' => 'yx',
						'value' => '0',
					);
					$info = '切换至普通音乐';
					break;
				case '古典':
					$value = array(
						'm' => 'yx',
						'value' => '1',
					);
					$info = '切换至古典音乐';
					break;
				case '爵士':
					$value = array(
						'm' => 'yx',
						'value' => '2',
					);
					$info = '切换至爵士音乐';
					break;
				case '摇滚':
					$value = array(
						'm' => 'yx',
						'value' => '3',
					);
					$info = '切换至摇滚音乐';
					break;
				case '流行':
					$value = array(
						'm' => 'yx',
						'value' => '4',
					);
					$info = '切换至流行音乐';
					break;
				case '点':
				case '一点':
				case '一些':
					$adjust = 1;
					break;					
				case '有点':
					$adjust = -1;
					break;					
				case '太':
					$adjust = -2;
					break;					
				case '大':
				case '吵':
				case '高':
					$volume = 1;
					break;					
				case '小':
				case '低':
					$volume = -1;
					break;					
				case '音量':
				case '声音':
					$volume = 1;
					$_SESSION['bj'] = '音量';										
					break;
				case '静音':
					$value = array(
						'm' => 'yl',
						'value' => 0,			
					);
					$info = '已为您切换至静音模式！';					
					$_SESSION['bj'] = '音量';										
					break;				
				default:
					if( !in_array('sz',$word['attr'])  && !in_array('song',$word['attr']) )
					{
						break;
					}		
					if( in_array('sz',$word['attr']) )
					{
						$num = $word['word'];
						break;
					}
					if( in_array('song',$word['attr']) )
					{
						$playlist = Cache::get('bjyylist_'.$attrid);
						$all = array();
						foreach($playlist as $valve)
						{
							foreach($valve as $k=>$v)
							{
								$all[$v] = $k;
							}
						}
						$value = array(
							'm' => 'play',
							'value' => $all[$word['word']],
						);			
						$info = '现在为您播放音乐：'.$word['word'];
					}	
			}
		}
		if( ($volume != 0 || $_SESSION['bj'] == '音量') && $num )		
		{
			$num = $num<1 ? $num*30 : ($num>30 ? 30 : $num);
			$value = array(
				'm' => 'yl',
				'value' => $num,			
			);
  			$info = '当前音量：'.$num;
		}
		if( ($select != 0 || $_SESSION['bj'] == 1 || $_SESSION['bj'] == -1)&& $num )		
		{
			if( $select == 0 )
			{
				$select = $_SESSION['bj'];
			}
			$playlist = Cache::get('bjyylist_'.$attrid);
			$curstatus = Cache::get('bjyys_'.$attrid);
			$list = array_keys($playlist[$curstatus['ex_FlagLocal']]);
			if( $select == -1 )
			{
				$list = array_reverse($list);
			}
			$num = $num<0 ? 1 : ( $num>sizeof($list) ? sizeof($list) : intval($num) );
			$value = array(
				'm' => 'play',
				'value' => $list[$num-1],
			);			
			$info = '现在为您播放音乐：'.$playlist[$curstatus['ex_FlagLocal']][$value['value']];
		}
		if( $volume != 0 && $adjust != 0 )
		{
			$cur = Cache::get('bjyys_'.$attrid)['ex_VolumeCur'];
			$value['m'] = 'yl';
			$value['value'] = intval($cur)+$adjust*2*$volume;
			$value['value'] = $value['value']>30 ? 30 : ( $value['value']<0 ? 0 : $value['value'] );
  			$info = '当前音量：'.$value['value'];
		}
		if( $value )
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,$value);
		}
		else
		{
			$ret = false;
			$info = YUYIN_OP_CMDFAIL.'  背景音乐的使用说明：'.'<br>古典，流行…<br>太（有点）大<br>音量 10（0-30）<br>播放，暂停，换<br>上一首，下一首<br>循环(播放)，随机(播放)…';
		}
		return array('ret'=>$ret,'info'=>$info);
	}	

}
?>