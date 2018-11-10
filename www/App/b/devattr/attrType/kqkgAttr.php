<?php
//空气开关

//手动开启/关闭，
//远程开启/关闭
//报警信息（过流保护，过压保护，欠压保护，漏电保护，短路保护，过载保护，超过功率限制，过温度保护）
//const KQKG_ALARM_OVERV   = 1;//过压保护


class kqkgAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT);
	static $page = 'kqkg'; 
	static $name = DEV_SYSNAME_KG;

	static $alarm =array(
		1 => '高压',
		2 => '低压',
		3 => '过流',
		4 => '高温',
		5 => '短路',
		6 => '漏电',
		7 => '过载',
		8 => '功率过限',
		100=> '关闭',
		101=> '打开',
	);	
	
	//系统定期调用清理该类型属性的数据
	static function sysMaintence()
	{
		return;
	}
	
	//设备附加信息上报时处理
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$cfg   = unpack('Cver/a5flag/Clddl/Cldcs/Cwd',$value);
		return array('power'=>$cfg);//sn=array(0=>xxx),'name'=>array(0=>xxxx)
		
		//$cfg = array(
		//	'power' =>array('flag'=>,'lddl'=>,'ldcs'=>,'wd'=>),
		//	'dev'   =>array( 
		//		0=> array('max'=>64,'sn'=>'aaa'),//支持的最大电流/sn号
		//	),
		//	'name' => array()
		//);
		//
		//$attrset=>array(
		//	'dev'  => array(
		//		0=>array('setlddl'=>,'lddl'=>,'setgl'=>,'gl'),//设置的漏电电流，确认的漏电电流，设置的功率，确认了的功率
		//	),
		//	'status' = array(
		//		0=>
		//	)
		//);
	}

	//新增属性时的处理
	static function addAttrNotice($attrid)
	{
		return;
	}
	
	//删除属性时的处理
	static function delAttrNotice($attrid,$devid,$attrindex)
	{
		//删除属性时，连带数据库上保存的时间序列也一并删除
		return;
	}
	
	//修改空开回路名称
	private static function changeName($attrid,$index,$name)
	{
		if( NULL == trim($name) )
		{
			return false;
		}
		if( 0 >= $index )
		{
			return false;
		}
		$c   = new TableSQL('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( false == $cfg )
		{
			return false;
		}
		if( !array_key_exists($index,$cfg['dev']) )
		{
			return false;
		}
		$cfg['name'][$index] = $name;
		
		$info = array();
		$info['ID']      = $attrid;
		$info['CFGINFO'] = serialize($cfg);
		$c->update($info);

		return true;
	}
	
	//用户设置信息的确认
	private static function confirmUserSet($attrid,$info)
	{
		//字节数	1 Byte	1 Byte	2 Byte
		//定义	地址	确认类型	确认值
		$info = unpack('Cindex/Ctype/vinfo',$info);
		$c = new TableSQL('homeattr','ID');
		$set = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$set = unserialize($set);
		$key = 'lddl';
		if( 1 == $info['type'] )
		{
			$key = 'gl';
		}

		if( $info['info'] == $set['dev'][ $info['index'] ][$key] )
		{
			return;
		}

		
		$set['dev'][ $info['index'] ][$key] = $info['info'];
		
		$info = array();
		$info['ID']      = $attrid;
		$info['ATTRSET'] = serialize($set);
		$c->update($info);
		
		return;
	}
	
	private static function procUserSet($attrid,$value,$key)
	{
		//{ 'm'=2,'index'=>0,'lddl'=>, 'times'=> }
		//	'dev'  => array(
		//		0=>array('setlddl'=>,'lddl'=>,'setgl'=>,'gl'),//设置的漏电电流，确认的漏电电流，设置的功率，确认了的功率
		//	),
		$r = false;
		$c = new TableSQL('homeattr','ID');
		$set = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$set = unserialize($set);
		if( !isset($set['dev']) )
		{
			$set['dev'] = array();
		}

		if( !isset($set['dev'][$value['index']]) )
		{
			$set['dev'][$value['index']] = array();
		}
		
		$setkey = 'setlddl';
		if( 'gl' == $key )
		{
			$setkey = 'setgl';
		}

		//如果当前设置值和数据库不一致，这个需要更新数据库
		if( $set['dev'][$value['index']][$setkey] != $value[$key]  )
		{
			$set['dev'][$value['index']][$setkey] = $value[$key];
			$info = array();
			$info['ID']      = $attrid;
			$info['ATTRSET'] = serialize($set);
			$c->update($info);			
		}
		
		//当前待设置值和实际值不一致，则需要下发进行延迟处理看是否已经确认
		if( $set['dev'][$value['index']][$key] != $value[$key]  )
		{
			if( 'lddl' == $key )
			{
				$r = pack('CCC',2,0,intval($value[$key]));
			}
			else
			{
				$r = pack('CCv',3,$value['index'],intval($value[$key]));
			}
			if( $value['times'] < 5 )
			{
				$value['times']++;
				include_once('plannedTask/PlannedTask.php');
				$planTask = new PlannedTask('devattr','attr',$value['times']+10);
				$planTask->execAttr($attrid,$value);
			}				
		}
		else
		{
			//如果是用户设置的，则一定需要下发
			if( 0 == $value['times'] )
			{
				if( 'lddl' == $key )
				{
					$r = pack('CCC',2,0,intval($value[$key]));
				}
				else
				{
					$r = pack('CCv',3,$value['index'],intval($value[$key]));
				}			
			}
		}
		
		
		return $r;
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		if(!is_array($value) ) $value = unserialize($value);
		switch( $value['m'] )
		{
			case 1: //空开改名 { 'm'=1,'index'=>x,'name'=> }
				self::changeName($attrid, $value['index'], $value['name']);
				return false;
				break;
			case 2: //设置漏电电流{ 'm'=2,'index'=>0,'lddl'=> }
			case 3: //设置最大功率{ 'm'=3,'index'=>x,'gl'=> }
				if( !isset($value['times']) )
				{
					$value['times'] = 0;
				}
				$key = 'lddl';
				if( 3 == $value['m'] )
				{
					$key = 'gl';
				}
				return self::procUserSet($attrid,$value,$key);
				break;
			case 4://远程开关空开{ 'm'=4,'action'=>array( index=>open ) } //open:0关，1开，2反转，-1保持不动
				//如果有开又有关，则需要拆分成两条消息发出
				$openindex  = NULL;
				$closeindex = NULL;
				foreach( $value['action'] as $i=>$a )
				{
					switch($a)
					{
						case 0:
							$closeindex |= (1<<$i);
							break;
						case 1:
							$openindex |= (1<<$i);
							break;
						default:
							break;
					}
				}
				
				//没有什么需要控制的
				if( NULL == $openindex && NULL == $closeindex )
				{
					return false;
				}

				//只有一个，直接发出。如果两个都有，则先发一个，一个延时后发
				if( NULL != $openindex && NULL == $closeindex )
				{
					return pack('CNC',1,$openindex,1);
				}
				if( NULL != $closeindex && NULL == $openindex )
				{
					return pack('CNC',1,$closeindex,0);
				}
				//走到这儿，是两个都有，无法一下子发送两条过去，
				//一条延时再发
				$cmd = array('m'=>101,'open'=>1,'index'=>$openindex);
				include_once('plannedTask/PlannedTask.php');
				$planTask = new PlannedTask('devattr','attr',1);
				$planTask->execAttr($attrid,$cmd);
				
				//一条直接发送			
				return pack('CNC',1,$closeindex,0);
				break;
			case 5: //禁止远程开关{ 'm'=5,'index'=>x }
				break;
			case 6: //测试漏电电流{ 'm'=6,'index'=>0 }
				return pack('CC',4,$value['index']);
				break;
				
			/////////////////////////////////////////	
			case 100: //SN查询{ 'm'=100,'index'=>x }
				return pack('CC',8,$value['index']);
				break;
			case 101://开关空开，到这儿的，动作都只有一个{ 'm'=101,'open'=>x,'index'=>x }
				return pack('CNC',1,$value['index'],$value['open']);
				break;
			case 103:
				return pack('CC',8,0);
			case 102://检测sn是否发送完全有丢失。如果丢了重发('m'=>102,count'=>$num,'times'=>)
				$r = Cache::get("kqkg_sn_$attrid");
				if( false == $r )
				{
					return false;
				}
				if( count($r) == $value['count'] )
				{
					Cache::del("kqkg_sn_$attrid");
					return false;
				}
				//检测还没有的sn信息，发送请求
				for( $i = 0; $i < $value['count']; $i++ )
				{
					if( isset( $r[$i] ) )
					{
						continue;
					}
					//设置延时继续检查
					if( $value['times'] < 15 )
					{
						$value['times']++;
						include_once('plannedTask/PlannedTask.php');
						$planTask = new PlannedTask('devattr','attr',$value['times']+2);
						$planTask->execAttr($attrid,$value);
					}
					
					return pack('CC',8,$i);
				}
				break;
		}
		
		//
		return NULL;
	}
	
	//处理空开的的开关事件
	private static function procKKEvent($attrid,$sn,$event)
	{
		//把事件分为开和关
		$alarm = $event&0b00001111;
		$open  = $event&0b11110000;
		//开关如果有变化，需要记录
		
		//处理其它告警事件
		if( 0 == $alarm )
		{
			return;
		}
		
		$log = array();
		$log['ATTRID']   = $attrid;
		$log['OTIME']    = time();
		$log['ACTION']   = $alarm;
		$log['USERID']   = -1;
		$log['INFO']     = 'local';
		$log['SN']       = $sn;
		
		$c = new TableSql('dev_kqkglog');
		$c->add($log);
		
		$info = self::$alarm[ $alarm ];
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->sendNotice(NULL,$attrid,$info);
		
		return;
	}
	
	//保存信息到tsdb
	private static function procTSDB($attrid,&$info,&$cfg)
	{
		
	}
	
	private static function procStatus($attrid,$value)
	{
		$c       = new TableSQL('homeattr','ID');
		$attr    = $c->query('CFGINFO,ATTRSET','ID=?',array($attrid));
		$cfg     = unserialize($attr['CFGINFO']);
		$cfg     = $cfg['sn'];
		$attrset = unserialize($attr['ATTRSET']);
		$attr    = &$attrset['status'];
		
		//字节数	1 Byte	1 Byte	2 Byte	2 Byte	1 Byte	2 Byte	2 Byte	2 Byte
		//定义	地址	开关事件	电压	电流	温度	电量	漏电流	功率
		while( NULL != $value )
		{
			$info = unpack('Cindex/Cevent/vdy/vdl/Cwd/vpower/vlddl/vgl',$value);
			$info['dl']   /= 10;
			
			$info['index'] &= 0b01111111;//协议的地址最高位表示手动关了的事件
			$attr[ $info['index'] ] = $info;
			
			//处理开关事件
			self::procKKEvent($attrid,$cfg['dev'][$info['index']]['sn'],$info['event']);
			
			//保存功率电量信息
			self::procTSDB($attrid,$info,$cfg);
			
			$value = substr($value,13);
		}
		
		//更新最新状态信息到ATTRSET
		$info = array();
		$info['ID']      = $attrid;
		$info['ATTRSET'] = serialize($attrset);
		$c->update($info);		
		
		return $attr[0]['gl'];
		
	}
	
	//完成了所有sn信息接受后，就写入处理
	private static function finishSNInfo($attrid,&$sninfo)
	{
		$c   = new TableSQL('homeattr','ID');
		$cfg = $c->query('CFGINFO,ATTRSET','ID=?',array($attrid));
		$set = unserialize($cfg['ATTRSET']);
		$cfg = unserialize($cfg['CFGINFO']);
		
		//如果sn有变化，则同步调整attrset信息
		foreach( $cfg['dev'] as $index=>&$old )
		{
			if( !isset($sninfo[$index]) )
			{
				unset( $cfg['name'][$index] );
				unset( $set['dev'][$index] );
				unset( $set['status'][$index] );
			}
			//如果sn有变化，就删掉原来的功率和漏电电流设置
			if( $old['sn'] != $sninfo[$index]['sn'] )
			{
				unset( $set['status'][$index] );
				$set['dev'][$index]['lddl'] = 0;
				$set['dev'][$index]['gl']   = 0;
			}
		}

		$cfg['dev'] = $sninfo;
		$info = array();
		$info['ID']      = $attrid;
		$info['CFGINFO'] = serialize($cfg);
		$info['ATTRSET'] = serialize($set);
		$c->update($info);	

		Cache::del("kqkg_sn_$attrid");
		return;
	}
	
	//设备SN信息上报
	private static function procSNInfo($attrid,$value)
	{
		//字节数	1 Byte	1 Byte	1 Byte	8 Byte	...
		//定义	分机总数	地址1	最大电流1	序列号1	地址...
		$num   = unpack('Cnum',$value);
		$num   = $num['num'];

		$sninfo = Cache::get("kqkg_sn_$attrid");
		if( false == $sninfo )
		{
			$sninfo = array();
			//设置延时检测sn是否完成，否则发送消息请求sn信息
			$cmd = array('m'=>102,'count'=>$num,'times'=>0);
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('devattr','attr',15);
			$planTask->execAttr($attrid,$cmd);
		}
		$value = substr($value,1);

		while( NULL != $value )
		{
			$info  = unpack('Cindex/Cmax/a8sn',$value);
			$sninfo[ $info['index'] ] = $info;
			$value = substr($value,10);
		}
		
		//已经收集完整了sn信息
		if( count($sninfo) == $num )
		{
			self::finishSNInfo($attrid,$sninfo);
		}
		else
		{
			Cache::set("kqkg_sn_$attrid",$sninfo);
		}
		
		//返回继续等待其它sn信息到来
		return;
	}
	
	//把设备上报的状态信息转为数据库信息
	//状态返回的是当前总功率
	static function getStatusInfo($value,$attrid=NULL)
	{
		if( 1 >= strlen($value) )
		{
			return false;
		}

		$cmd   = unpack('Ccmd',$value);
		$cmd   = $cmd['cmd'];
		$value =  substr($value,1);
		switch( $cmd )
		{
			case 0: //设备状态上报
				return self::procStatus($attrid,$value);
				break;
			case 1: //设备SN信息上报
				return self::procSNInfo($attrid,$value);
				return false;
				break;
			case 2: //确认设置成功
				self::confirmUserSet($attrid,$value);
				return false;
				break;
			default:
				return false;
				break;
		}
		
		return false;
	}
	
	private static function showKKStatus($index,&$info,&$status)
	{
		if( !isset($status[$index]) )
		{
			$info['open']  = 0;
			$info['power'] = 0;
			$info['other'] = array(
				'dl'  => 0,  //电流
				'dy' => 0,   //电压
				'gl' => 0,   //功率
				'wd' => 0,   //温度
			);
			return;
		}
	    //unpack('Cindex/Cevent/vdy/vdl/Cwd/vpower/vlddl/vgl',$value);
		$info['open']  = $status[$index]['event']>>4;
		$info['power'] = $status[$index]['power'];
		$info['other'] = array(
			'dl' => $status[$index]['dl'],  //电流
			'dy' => $status[$index]['dy'],   //电压
			'gl' => $status[$index]['gl'],   //功率
			'wd' => $status[$index]['wd'],   //温度
		);
		return;
	}
	
	private static function getKKEvent($attrid,$sn,&$info)
	{
		$info['alarm'] = array();

		$c = new TableSql('dev_kqkglog');
		$logList = $c->queryAll('*','ATTRID=? ORDER BY OTIME DESC',array($attrid));
		foreach($logList as &$log )
		{
			if( $log['SN'] != $SN )
			{
				continue;
			}
			$log['ACTION'] = self::$alarm[ $log['ACTION'] ];

			$day  = date("Y-m-d",$log['OTIME']);
			$time = date("H:i",$log['OTIME']);
			if( !isset( $info['alarm'][$day] ) )
			{
				$info['alarm'][$day] = array();
			}
			$info['alarm'][$day][] =  array('text'=>$log['ACTION'], 'time'=>$time);
		}		
	}
	//查看设备时，返回的详细信息
	static function getDetail($value,$id)
	{
		$c    = new TableSQL('homeattr','ID');
		$attr = $c->query('CFGINFO,ATTRSET','ID=?',array($id));
		$cfg  = unserialize($attr['CFGINFO']);
		$attr = unserialize($attr['ATTRSET']);

		$r = $cfg;
		$r['info']  = array(); 
		$r['unit']  = array( 
				'power'=> array('name'=>'电量', 'unit'=>'度'),
				'dl'   => array('name'=>'电流', 'unit'=>'A' ),
				'dy'   => array('name'=>'电压', 'unit'=>'V' ),
				'gl'   => array('name'=>'功率', 'unit'=>'瓦'),
				'wd'   => array('name'=>'温度', 'unit'=>'C' ),
				);
		$count =  count( $cfg['dev'] );
		for($i = 0; $i < $count; $i++)
		{
			$r['info'][$i]  = array();
		}
		foreach( $r['info'] as $i=>&$info )
		{
			if( NULL == $cfg['name'][$i] )
			{
				$cfg['name'][$i] = "switch$i";
			}
			$info['name']  = $cfg['name'][$i];
			$info['maxld'] = $attr['dev'][$i]['lddl'];//如果还没设置的要处理
			$info['maxgl'] = $attr['dev'][$i]['gl'];
			
			self::showKKStatus($i,$info,$attr['status']);
			self::getKKEvent($id,$cfg['dev'][$i]['sn'], $info);
			
		}
		
		return $r;
		//return array(
		//	'power' => array('lddl'=>1,'ldcs'=>1,'wd'=>1),//漏电电流设置/漏电电流测试/wd信息
		//	'dev'   => array( 
		//		0=> array('max'=>64,'sn'=>'aaa'),//支持的最大电流/sn号
		//	),
		//	'unit'  => array( 
		//		'power'=> array('name'=>'电量', 'unit'=>'度'),
		//		'dl'   => array('name'=>'电流', 'unit'=>'A' ),
		//		'dy'   => array('name'=>'电压', 'unit'=>'V' ),
		//		'gl'   => array('name'=>'功率', 'unit'=>'瓦'),
		//		'wd'   => array('name'=>'温度', 'unit'=>'C' ),
		//		),
		//	'info'  => array(
		//		0 => array( 
		//					'name'=> ''
		//					'open'   => 0,   //电量
		//					'power'  => 0,   //电量
		//					'other'=>array(
		//						'dl'  => 0,  //电流
		//						'dy' => 0,   //电压
		//						'gl' => 0,   //功率
		//						'wd' => 0,   //温度
		//					),
		//					'maxld' => 0,//设置的漏电电流
		//					'maxgl' => 0,//设置的最大功率
		//					'alarm' => array(),//事件信息
		//					), 
		//	),//信息
		//);
	}

	//////////////////////语音控制相关////////////////////////////////////

		
}

 

?>