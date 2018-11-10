<?php

//背景音乐
class bjyyAttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT);
	static $page = 'bjyy'; 
	static $name = DEV_SYSNAME_BJYY;

	//解析附加信息
	static function parseAdditonInfo($value,$attrid)
	{
		//音源音效上报格式：总长度+索引+信息长度+信息内容+索引+信息长度+信息内容……
		//比如：16 00 06 XX 01 06 XX 
		//播放模式上报格式：总长度+播放模式序号
		//比如：03 01 02 03（三种播放模式：1（随机播放），2（单曲循环），3（顺序播放））
		$info = $value['info'];
		$key = array('yy','yx','bfms');
		$i = 0;
		do
		{
			$len = unpack('Clen',$info)['len'];
			$cur = substr($info,1,$len);
			if( $i != 2 )
			{
				do
				{
					$curinfo = unpack('Ctime/Clen',$cur);
					$cfg[$key[$i]][$curinfo['time']] = substr($cur,2,$curinfo['len']);
					$cur = substr($cur,$curinfo['len']+2);
				}while($cur);			
			}
			else
			{
				$cfg[$key[$i]] = array_values(unpack("C*",$cur));
			}
			$info = substr($info,$len+1);
			$i++;
		}while($info);
		self::updateCfg($attrid,$cfg); //保存附加信息	
	}

	//获得播放列表
	private static function playList($attrid,&$info)
	{
		if( !isset( $info['name']) )
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
		$playlist[$info['name']] = $list;
		Cache::set('bjyylist_'.$attrid,$playlist);
		noticeAttrModi($attrid);
	}	

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
		Cache::set('bjyys_'.$attrid,$info,90);

	}

	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$attrid=NULL)
	{
		return Cache::get('bjyys_'.$attrid);
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		if( $value=='9' ) return pack('C',$value);
		$value = unserialize($value);	
		$cmd['value'] =  intval($value['value']);

		switch( $value['m'] )
		{		
			case 'group'://情景模式促发
				if( $value['playing'] == 1 )
				{
					return pack('CN',1,1);			
				}
				else
				{
					return pack('CNNNN',10,$value['ex_EQMode'],$value['ex_FlagLocal'],$value['musicid'],$value['ex_VolumeCur']);
				}
			case 'list': //获取音乐列表
				$cmd['m'] = 0;
				break;			
			case 'mode': //播放模式
				$cmd['m'] = 4;
				break;
			case 'yx': //EQ模式
				$cmd['m'] = 3;
				break;
			case 'play': //播放指定歌曲,Value为ID
				$cmd['m'] = 2;
				break;
			case 'ctrl': //播放暂停上一首下一首 
				$cmd['m'] = 1;
				break;
			case 'yl': //音量控制
				$cmd['m'] = 5;
				break;
			case 'yy': //音源控制
				$cmd['m'] = 6;
				break;
			case 'report': //上报状态速度控制（1:3s,0:1min） 
				$cmd['m'] = 8;
				break;				
			default:
				return false;
				break;
		}
			
		return pack('CN',$cmd['m'],$cmd['value']);
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		//退出背景音乐页面，通知设备降低上报状态速度	
		if( Cache::get('bjyystatus'.$attrid) == false )
		{
			$cmd = array('m'=>'report','value'=>0);
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,$cmd);
		}
		else
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,'9');
		}
		
		
		$info = unpack('Ccmd/Clen/Ctime',$value);

		if( $info != false )
		{
			$msg = substr($value,4);
			$playlist = Cache::get('playlist_'.$attrid);
			if( false == $playlist )
			{
				$playlist = '';
			}
			$playlist .=$msg;

			if( $info['time'] != $info['len']-1 )
			{
				Cache::set('playlist_'.$attrid,$playlist);
				return false;
			}
			else
			{
				$playlist = json_decode($playlist,true);
				Cache::del('playlist_'.$attrid);
			}
			
			switch($info['cmd'])
			{
				case 0:
					self::playStatus($attrid,$playlist);
					break;
				case 1:
					self::playList($attrid,$playlist);
					break;
				case 2:
					unset($playlist['name']);
					Cache::set('onlinelist_'.$attrid,$playlist,60*24*60);
					break;
			}
		}

		return false;
	}
	
	static function getDetail($value,$id)
	{
		//进入背景音乐页面，通知设备增加上报状态速度	
		$cmd = array('m'=>'report','value'=>'1');
		$GLOBALS['dstpSoap']->setModule('devattr','attr');
		$GLOBALS['dstpSoap']->execAttr($id,$cmd);

		$cfg = self::queryCfg($id); //查询数据库信息
		$online = isset(array_flip($cfg['yy'])['在线']) ? array_flip($cfg['yy'])['在线'] : '-1';
		$playlist = Cache::get('bjyylist_'.$id);
		$a = array(
			'yy'   => $cfg['yy'],
			'yx'   => $cfg['yx'],
			'bfms' => $cfg['bfms'],
			'pl'   => $playlist,
			'status' => array(
				0=>ATTRCFG_BJYY_ZANTING,
				1=>ATTRCFG_BJYY_BOFANG
			),
			'online'=> $online,
		);			
		return $a;
	}

	//查询数据库信息
	static function queryCfg($attrid)
	{
		$c   = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid));
		return unserialize($cfg);		
	}
	
	//更新数据库信息
	static function updateCfg($attrid,$cfg)
	{
		$info            = array();
		$info['ID']      = $attrid;
		$info['ATTRSET'] = serialize($cfg);
		$c = new TableSql('homeattr','ID');
		$c->update($info);			
	}

//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array(
			'fy' => array('有点','太','一点','点','一些'),
			'dz' =>array('第','最后','倒数','最后第','倒数第','上一首','下一首','换','换一','暂停','暂停播放','停止播放','停止','播放','打开','继续','继续播放','开始','开始播放','关闭','关掉','退出','随机','随机播放','单曲','单曲循环','顺序','顺序播放','循环','循环播放','普通','古典','爵士','摇滚','流行','大','吵','小','高','低','声音','音量','随便','随便放一','随便播放一','静音'),
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
				case '随机':
				case '随机播放':
				case '单曲':
				case '单曲循环':
				case '循环':
				case '循环播放':
					$cfg 	= self::queryCfg($attrid); //查询数据库信息
					$bfms1	= array_combine($cfg['bfms'],$cfg['bfms']);
					$bfms2	= array(0=>'顺序',1=>'随机',2=>'单曲',3=>'循环');
					$bfms 	= array_intersect_key($bfms2,$bfms1);
					$word 	= mb_substr($word['word'],0,2);
					if( in_array($word,$bfms) )
					{
						$value = array(
							'm' 	=> 'mode',
							'value' => array_search($word,$bfms),
						);
						$info = '已经为您切换到'.$word.'播放模式';						
					}
					else
					{
						return array('ret'=>false,'info'=>'该设备不支持该播放模式！');
					}
					break;					
				case '普通':
				case '古典':
				case '爵士':
				case '摇滚':
				case '流行':
					$cfg = self::queryCfg($attrid); //查询数据库信息
					if( in_array($word['word'],$cfg['yx']) )
					{
						$value = array(
							'm' 	=> 'yx',
							'value' => array_search($word['word'],$cfg['yx']),
						);
						$info = '切换至'.$word['word'].'模式';
					}
					else
					{
						return array('ret'=>false,'info'=>'该设备不支持该音效！');
					}
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