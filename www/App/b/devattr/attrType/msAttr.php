<?php 
//门锁
/* 门锁数据保存：
 * homeattr中的ATTRSET字段
 * 0.超级密码 password
 * //1.报警设置
 * 
 * homeattr中的CFGINFO字段
 * 支持的开锁方式   : typelist
 * 每种开锁方式能力 : typeinfo
 * 门锁能力         : power
 
 * 1.用户信息 //dev_lockuser
 * 2.开锁记录 //dev_locklog
 * 3.用户日志 //dev_locklog
 * 4.报警信息 //dev_locklog
 */
/* detail=>array(
	//用户记录-type:用户类型(0:指纹,1:密码,2门卡),id:用户id,name:用户名字,dateline:截至日期(默认-1),times:剩余次数(默认-1),isalarm:是否报警用户(默认0),status:是否待删除状态(默认0)
	//记录保存时，以用户类型为下标，做成二维数组
	'user'=> array(
		'type' = array(
			0=>array('id'=>,'name'=>,'dateline'=>-1,'times'=>-1,'isalarm'=>false),
			......
		)
		...
	),
	//开锁记录-time:开锁时间,usertype:用户类型,userid:用户id,username:用户名字
	//记录保存时，以告警日期为下标，做成二维数组
	'ksrecord'=>array(
		'day'=>array(
			0=>array('time'=>,'usertype'=>,'userid'=>,'username'=>,'isalarm'),
			......		
		)
	),
	//报警记录-time:报警时间,text:报警类型
	//记录保存时，以告警日期为下标，做成二维数组
	'alrecord'=>array(
		'day'=>array(
			0=>array('time'=>,'text'=>),
			......
		),
	),
	//报警设置-type:报警类型,con:报警状态(开启:1(默认),关闭:0)
	'alarm'=>array(
		0=>array('type'=>,'con'=>1),
		......
	)
	//用户类型(0:指纹,1:密码,2门卡)
	'typecfg'=>array(
		0=>'指纹',
		1=>'密码',
		2=>'门卡',
		3=>'无线',
		......		
	)
	//门锁的可能状态信息
	'valuecfg'=>array(
		0=>array()
	)
) */
//门锁操作
const MS_ACTION_KS         = 0;//开锁
const MS_ACTION_ALARM      = 1;//告警
const MS_ACTION_ADDUSER    = 2;//添加用户
const MS_ACTION_DELUSER    = 3;//删除用户
const MS_ACTION_USERRENAME = 4;//用户改名
const MS_ACTION_USERMODI   = 5;//修改用户
const MS_ACTION_PSWMODI    = 6;//修改管理员密码
const MS_ACTION_XIEPOALARM = 50;//胁迫开锁告警


//用户删除原因
const MS_DELUSER_REASON_APP   = 0;//APP指令删除
const MS_DELUSER_REASON_CSYJ  = 1;//次数用尽删除
const MS_DELUSER_REASON_GQ    = 2;//到期删除

include_once('b/homeLang.php');

class msAttrType
{
	static $cfg = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM_CHAR,'cf'=>TABLE_FIELD_ENUM_CHAR);
	static $page = 'ms';
	static $name = DEV_SYSNAME_MS;
	
	//所有告警类型
	static $msalarm =array(
		0=>ATTRCFG_MS_ALARM_SCBJ,
		1=>ATTRCFG_MS_ALARM_XSBJ,
		2=>ATTRCFG_MS_ALARM_FSBJ,
		3=>ATTRCFG_MS_ALARM_FCBJ,
		4=>ATTRCFG_MS_ALARM_MCBJ,
		5=>ATTRCFG_MS_ALARM_ZDBJ,
		6=>ATTRCFG_MS_ALARM_XTBJ,
		7=>ATTRCFG_MS_ALARM_DCBJ,
		8=>ATTRCFG_MS_ALARM_CSGMBJ,
	);

	//所有开锁类型
	static $kstype = array(
		 0 => ATTRCFG_MS_USER_ZHIWEN,
		 1 => ATTRCFG_MS_USER_MIMA,
		 2 => ATTRCFG_MS_USER_MENKA,
		 3 => ATTRCFG_MS_USER_RENLIAN,
		 4 => ATTRCFG_MS_USER_SHENWEN,
		 5 => ATTRCFG_MS_USER_YAOKONG,
		-1 => ATTRCFG_MS_USER_WUXIAN,
		-2 => ATTRCFG_MS_USER_LOCAL,
	);
	
	
	//删除门锁相关记录
	static function delAttrNotice($attrid,$devid,$attrindex)
	{
		//删除门锁所涉及的联动设置
		$c = new TableSql('dev_lockuser');
		$userList = $c->queryAll('USERID,USERTYPE,USERNAME','ATTRID=?',array($attrid));
		foreach($userList as &$user)
		{
			self::delSmart($attrid,$user['USERTYPE'],$user['USERID'],$user['USERNAME']);
		}

		$c = new TableSql('dev_lockuser');
		$c->del('ATTRID=?',array($attrid));
		$c = new TableSql('dev_locklog');
		$c->del('ATTRID=?',array($attrid));
	}
	
	//每天清除两个月前的开锁记录，告警记录和门锁用户操作记录
	static function sysMaintence()
	{
		//清除次数用尽和过期门锁用户。需要当前在线门锁才处理
		//$c = new TableSql('dev_locklog');
		
		//清除门锁记录
		$c = new TableSql('dev_locklog');
		$c->del('OTIME<?',array( time()-86000*60 ));
	}

	//解析附加信息，附加信息中包含门锁支持的能力
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$value = unpack('Cver/Cmima/Cmenka/Czhiwen/Crenlian/Cshengwen/Cyaokong/Cks7/Cks8/Calarm1/Calarm2/Cpower1/Cpower2',$value);
		
		$cfg = array();
		$cfg['typeinfo'] = array();//每种开锁方式的能力
		$cfg['power']    = array();//门锁能力
		
		$kstype = array(
			0 => 'zhiwen' ,
			1 => 'mima' ,
			2 => 'menka' ,
			3 => 'renlian',
			4 => 'shengwen',
			5 => 'yaokong',
		   -1 => '无线用户',
		   -2 => '门锁本地',
		);
		foreach( $kstype as $typeid=>$type )
		{
			if( 0 == $value[$type] )
			{
				continue;
			}
			$v = $value[$type];

			//某种门锁能力如果具有数据采集功能，需要下发查询获取型号信息
			if( ($v && 0b00010000) || ($v && 0b00100000) )
			{
				include_once('plannedTask/PlannedTask.php');
				$cmd = array('m' => 0x0c,'ut'=>$typeid);		
				$planTask = new PlannedTask('devattr','attr',1+$typeid);//时间错开下，避免瞬间下发
				$planTask->execAttr($attrid,$cmd);			
			}
			
			$v = array_reverse(str_split(decbin($v)));
			$cfg['typeinfo'][$typeid] = array_reverse(array_pad($v,4,0));
		}
		
		
		$cfg['power'] = $value['power1'];//门锁能力

		//接通板子的时候就与设备数据进行同步
		include_once('plannedTask/PlannedTask.php');
		$cmd = array('m' => '9');		
		$planTask = new PlannedTask('devattr','attr',1);
		$planTask->execAttr($attrid,$cmd);
		
		return $cfg;
	}
	
	//根据开锁用户ID和类型，生成一个唯一标记作为状态
	static function statusValue($id,$type)
	{
		if( -1 == $type )
		{
			return $id;
		}
		return 0x7F000000+intval($type)*10000+intval($id);
	}

	//把数据库信息通过pack转化为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		if( !$value ) return false;
		$cmd = false;
		switch( $value['m'] )
		{
			case 0://开锁：other:{ m:'0',pw:'密码' } 
				//（1）向门锁下发命令
				//字节	1 Byte	1 Byte 4 Byte
				//定义	type	con    id
				$cmd = pack('CCV',1,1,$GLOBALS['curUserID']);
				break;
			case 1://添加用户：other:{ m:'1',pw:'密码',type:'密码类型',cfg:'用户信息' }
				//（2）远程用户添加 2
				//字节	1 Byte	1 Byte
				//定义	type	ut
				$ut   = $value['type'];
				$cmd = pack('CC',2,$ut);
				
				$value['cfg']['type'] = $ut;
				//cache中保存添加信息。当回应时，可以根据添加信息写日志
				$value['cfg']['userid']   = $GLOBALS['curUserID'];
				$value['cfg']['username'] = $GLOBALS['curUserName'];
				Cache::set("msuseradd_$attrid", $value['cfg'], 60);
				break;	
			case 7://APP添加密码用户：other{ m:'7',pw:'门锁管理密码',password:'用户密码',cfg:'用户信息'	}
				//bin2hex($value['password']);
				$ut = 1;
				$pw = array_map('ord',str_split($value['password'],1));
				$cmd = pack('CC',7,sizeof($pw));
				foreach($pw as $v)
				{
					$cmd .= pack('C',$v);
				}

				//cache中保存添加信息。当回应时，可以根据添加信息写日志
				$value['cfg']['userid']   = $GLOBALS['curUserID'];
				$value['cfg']['username'] = $GLOBALS['curUserName'];

				Cache::set("msuseradd_$attrid", $value['cfg'], 60);
				break;
			case 2://修改用户信息 other:{ m:'7',pw:'密码','name','dateline','times','isalarm','id','type'}
				self::updateUser($attrid,$value);  //修改用户
				noticeAttrModi($attrid);
				return false;
				break;
			case 3://删除：other:{ m:'3',pw:'密码',id:'用户id',type:'用户类型',reason:删除原因 } 
				//（3）远程用户删除 3
				//字节	1 Byte	1 Byte	1 Byte
				//定义	type	ut		n		
				//以前用户id是一个字节，现在都改为2个字节
				//为了兼容，现在下发的需要保证字节序避免出错
				$ut  = $value['type'];
				$n   = $value['id'];
				$cmd = pack('CCv',3,$ut,$n);

				//cache中保存添加信息。当回应时，可以根据添加信息写日志
				$cfg = array();
				if( !isset($value['reason']) )
				{
					$cfg['reason']   = MS_DELUSER_REASON_APP;
					$cfg['username'] = $GLOBALS['curUserName'];
				}
				else
				{
					$cfg['reason']   = intval($value['reason']);
				}
				Cache::set("msuserdel_$attrid_$ut_$n", $cfg, 30);
				break;	
			case 5://修改超级密码：other:{ m:'5',pw:'密码',newpw:'新密码'}
			case 6://添加超级密码：other:{ m:'6',pw:'密码'}
				self::updatePassword($attrid,$value);
				return false;
				break;
			case 8://APP设置是否禁止近端开锁：other{ m:'8',pw:'门锁管理密码'}
				//$cfg = self::queryCfg($attrid); //查询数据库信息
				//$cmd = $cfg['jdks'] == '1' ? pack('CC',8,0) : pack('CC',8,1);
				break;
			case 9://查询用户信息
				$cmd = pack('C',9);
				break;
			case 10://停用启用用户：other:{ m:'10',pw:'密码',id:'用户id',type:'密码类型',value:'停用0/启用1' } 
				$cmd = pack('CCCn',10,$value['value'],$value['type'],$value['id']);
				break;
				
			case 0x0b://开始采集指纹数据，并同时发送给其它门锁{ m:0x0b,type:'用户类型',target:'目标门锁ID' } 
				//生成一个采集标记
				$cmd = pack('CC',0x0b,$value['type']);
				//如果要同时写到目标门锁，需要设置一个cache
				if( validID($value['dest']) )
				{
					Cache::set("mscj_$attrid-$value[type]",$value['dest']);
				}
				Cache::del("mscjfinish-$attrid-$value[type]-$value[dest]");
				Cache::del("mscjrecv-$value[dest]");
				Cache::del("mscjdata-$attrid-$value[type]");
				break;
			case 0x0c://获取采集模块型号信息
				$cmd = pack('CC',0x0C,$value['ut']);
				break;
			case 0x0d://回复确认收到的采集信息{ m:0x0d,ut:'',index:''}
				$cmd = pack('CCC',0x0d,$value['ut'],$value['index']);
				break;
			case 0x0e://将采集数据传给门锁MCU {ut,flag,total,index,len,data}

				$targetinfo = Cache::get("mscjrecv-$attrid");
				$curindex   = $targetinfo['curindex'];
				if( $value['index'] != ($curindex+1) )
				{
					return false;
				}
				$cmd  = pack('CCCCC',0x0e,$value['ut'],$value['total'],$value['index'],$value['len']);
				$cmd .= $value['data'];
				$datareallen = strlen( $value['data'] );
				$datallen = $value['len'];
				
				$value['renum']--;
				if( $value['renum'] > 0 )
				{
					include_once('plannedTask/PlannedTask.php');
					$planTask = new PlannedTask('devattr','attr',8);
					$planTask->execAttr($attrid,$value);
				}		
				break;
			case 0x10://发送指定的指纹门卡信息到门锁{m:0x10,ut:0,data:}
				$cjid = mt_rand(1000000,10000000);
				
				//构造接收到的采集数据包
				$totallen = strlen($value['data']);
				$total = intval(($totallen-1)/100)+1;
				$datainfo = array();
				for($i=1; $i<=$total;$i++)
				{
					$datainfo[$i] = substr($value['data'],100*($i-1),100);
				}
				Cache::set("mscjdata-$cjid-$value[ut]",$datainfo,600);
				
				
				$cjrecv = array( 'total'  => $total,
								'cjid'    => $cjid,
								'curindex'=> 0 
								);
				Cache::set("mscjrecv-$attrid",$cjrecv,600);
				
				//开始发送第一条
				$info['len']   = strlen( $datainfo[$info['index']] );
				$info['index'] = 1;
				$info['total'] = $total;
				$info['ut']    = $value['ut'];
				$info['m']     = 0x0e;
				$info['renum'] = 15;
				$info['data']  = $datainfo[$info['index']];
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($targetid,$info);
				break;
			//////////////以下接口为内部调用，并非前台发来	
			case 100://自动回锁	other:{ m:'100' }
				self::onLock($attrid);
				return false;
				break;
			case 101://自动删除用户 other:{ m:'101',reason,ut,n,execnum }
				self::autoDelUser($value['reason'],$attrid,$value['ut'],$value['n'],$value['execnum']);
				return false;
				break;
		
			default:
				return false;
				break;
		}

		return $cmd;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$status = false;
		
		$type = unpack("Ct",$value);
		$type = $type['t'];

		switch($type)
		{
			case 0://开锁上报。要考虑APP开锁的上报格式。回锁还没上报
				$info = strlen($value) == 4 ? unpack('Ct/Cut/nn',$value) : unpack('Ct/Cut/Cn',$value);					
				//更新锁的状态：根据开锁用户ID和类型，组成一个唯一标记作为状态	
				if( $info['n'] == 0 && $info['ut'] ==0 )
				{
					break;
				}
				$status = self::saveKsrecord($attrid,$info);
				break;
			case 1://添加用户上报
				$info = strlen($value) == 5 ? unpack('Ct/Cut/nn/Cr',$value) : unpack('Ct/Cut/Cn/Cr',$value);					
				//如果不是添加成功，则无需处理
				if( 0 != $info['r'] || 0 >= $info['n'] )
				{
					return false;
				}

				self::addUser($attrid,$info);
				noticeAttrModi($attrid);			
				return false;
				break;					
			case 2://网络密码错误上报
				$info = unpack('Ct/Cpt',$value);
				$pt = $info['pt'];
				//上报错误信息
				return false;
				break;
			case 3://查询用户？？什么时候查询或者上报
				$info = unpack('Ct/Cut',$value);
				$userinfo = array_reverse(str_split(substr(array_values(unpack("H*",$value))[0],4)));
				$result = array($info['ut']=>array());
				foreach( $userinfo as $key=>$value )
				{
					if(base_convert($value, 16, 10) != 0)
					{
						$arr = array_pad(array_reverse(str_split(base_convert($value, 16, 2))),4,'0');
						foreach( $arr as $k=>$v )
						{
							if( $v == 1 )
								$result[$info['ut']][] = $key*4+$k+1;
						}
					}
				}
				self::compare($attrid,$result);
				return false;
				break;
			case 5://异常告警上报
				$info = unpack('Ct/Cat',$value);
				//保存记录
				self::saveAlrecord($attrid,$info);
				noticeAttrModi($attrid);			
				//发送手机通知
				return false;
				break;
			case 6://删除用户上报
				$info = strlen($value) == 5 ? unpack('Ct/Cut/nn/Cr',$value) : unpack('Ct/Cut/Cn/Cr',$value);
				//如果不是删除成功，则无需处理
				if( 0 != $info['r'] )
				{
					//给APP设置反馈立即回应
					return false;
				}
				self::delUser($attrid,$info);

				noticeAttrModi($attrid);			
				return false;
				break;
			case 7:
				$info = unpack('Ct/Ccon',$value);
				switch( $info['con'] )
				{
					case 0:
						$status = '0';
						break;
					case 1://初始化
						self::resetLock($attrid);
						return false;
					default:
						return false;
						break;
				}
				break;
			case 8://无线开锁上报
				$info = unpack('Ct/ln',$value);
				$info['ut'] = -1;
				$status = self::saveKsrecord($attrid,$info); //保存开锁记录					
				Cache::set("doorlock_$attrid", 'ks', 5);										
				break;				
			case 9://门铃
				$info = unpack('Ct/Cn',$value);
				if( $info['n'] == 0 ) 
				{
					self::alarm($attrid,ATTRCFG_MS_DB);		
				}				
				break;
			case 0x0c: //回复采集模块型号信息
				$info = unpack('Ct/Cut/a8type',$value);
				//写入采集模块型号信息
				$c = new TableSql('homeattr','ID');
				$msInfo = $c->query('ID,CFGINFO','ID=?',array($attrid));
				$cfg = unserialize($msInfo['CFGINFO']);
				$cfg['recordtype'][$info['ut']] = $info['type'];
				$msInfo['CFGINFO'] =  serialize( $msInfo['CFGINFO'] );
				$c->update($msInfo);
				break;
			case 0x0d: //采集模块获取的数据
				//数据段不超过100字节
				//1个字节的用户类型
				//1个字节的采集标记
				//1个字节的总包数
				//1个字节包序号
				//1个字节实际数据长度
				//从第5字节开始为实际数据		
				$info = unpack('Ct/Cut/Ctotal/Cindex/Clen',$value);
				$targetid = Cache::get("mscj_$attrid-$info[ut]");
				$datainfo = Cache::get("mscjdata-$attrid-$info[ut]");
				if( false == $datainfo )
				{
					$cjrecv = array( 'total'  => $info['total'],
									'cjid'    => $attrid,
									'curindex'=> 0 
									);
					Cache::set("mscjrecv-$targetid",$cjrecv);
					$datainfo = array();
				}

				$datainfo[$info['index']] = substr($value,5);
				Cache::set("mscjdata-$attrid-$info[ut]",$datainfo,240);
				
				//向采集器发送接受确认命令
				$info['m'] = 0x0d;
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($attrid,$info);				

				//按顺序向目标门锁发送数据
				
				$targetinfo = Cache::get("mscjrecv-$targetid");
				$curindex   = $targetinfo['curindex'];
				if( $info['index'] == ($curindex+1) )
				{
					$info['m']     = 0x0e;
					$info['renum'] = 15;
					$info['data'] = $datainfo[$info['index']];
					$GLOBALS['dstpSoap']->setModule('devattr','attr');
					$GLOBALS['dstpSoap']->execAttr($targetid,$info);				
				}

				break;
			case 0x0e://确认回复接受采集数据
				//1个字节用户类型
				//1个字节发送标记
				//1个字节确认序号
				$info = unpack('Ct/Cut/Cindex',$value);
				
				//查找是否还有需要下发的门锁数据
				$targetinfo = Cache::get("mscjrecv-$attrid");
				$cjid       = $targetinfo['cjid'];
				$curindex   = $targetinfo['curindex'];
				$total      = $targetinfo['total'];
				//如果已经全部回复完成，写录入完成
				if( $total == ($info['index']) )
				{
					Cache::set("mscjfinish-$cjid-$info[ut]-$attrid",true);
					//删除其它相关cache
					Cache::del("mscjrecv-$attrid");
					Cache::del("mscjdata-$cjid-$info[ut]");
				}
				else
				{
					//继续在当前index下发
					$targetinfo['curindex'] = $info['index'];
					Cache::set("mscjrecv-$attrid",$targetinfo);
					
					
					$sendindex = $info['index']+1;
					$datainfo = Cache::get("mscjdata-$cjid-$info[ut]");
					if( isset($datainfo[$sendindex]) )
					{
						$data = $datainfo[$sendindex];
						$sendinfo          = array(); 
						$sendinfo['m']     = 0x0e;
						$sendinfo['renum'] = 15;
						$sendinfo['ut']    = $info['ut'];
						$sendinfo['total'] = $targetinfo['total'];
						$sendinfo['index'] = $info['index']+1;
						$sendinfo['len']   = strlen($data);
						$sendinfo['data']  = $data;
						$GLOBALS['dstpSoap']->setModule('devattr','attr');
						$GLOBALS['dstpSoap']->execAttr($attrid,$sendinfo);				
					}						
				}
				
				break;
			//////////以下为内部调用接口///////////	
			default:
				return false;
				break;
			//下面几个指令暂未处理	
			//case 4://设置报警回复。这一项没有设置
			//	$info = unpack('Ct/Cat/Car',$value);
			//	if( $info['ar']==0 )
			//	{
			//		return false;
			//	}
			//	self::saveAlarm($attrid,$info['at']);
			//	return false;
			//	break;
			//case 10://修改允许/禁止近端开锁是否成功
			//	$info = unpack('Ct/Cn',$value);
			//	if( $info['n'] == 0 ) 
			//	{
			//		$cfg = self::queryCfg($attrid); //查询数据库信息
			//		$cfg['jdks'] = $cfg['jdks'] == '1' ? 0 : 1;
			//		self::updateCfg($attrid,$cfg); //更新数据库信息	
			//	}				
			//	return false;					
			//	break;		
			//case 11://停用启用用户设置
			//	$info = unpack('Ct/Cut/nn/Cr',$value);
			//	$cfg = self::queryCfg($attrid); //查询数据库信息
			//	foreach( $cfg['user'][$info['ut']] as $k=>$v )
			//	{
			//		if( $info['n'] == $v['id'])
			//		{
			//			$cfg['user'][$info['ut']][$k]['status'] = $info['r'];
			//			self::updateCfg($attrid,$cfg); //更新数据库信息	
			//			break;
			//		}
			//	}			
			//	return false;					
		}
		return $status;
	}
	
	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$id)
	{
		if( $value == '0' || !isset($value) )
		{
			return 2;
		}
		return 1;
	}	

	//辅助前台显示的信息
	static function getDetail($value,$attrid=NULL)
	{
		//如果超级密码存在。则设置password的信息
		$cfg = array();
		$c 	 = new TableSql('homeattr','ID');
		$set = $c->query('ATTRSET,CFGINFO','ID=?',array($attrid));
		$attrset = unserialize($set['ATTRSET']);
		$cfgset  = unserialize($set['CFGINFO']);
		if ( isset($attrset['password']) )
		{
			$cfg['password'] = 1;
		}

		$cfg['typecfg']  = array();
		$cfg['typeinfo'] = $cfgset['typeinfo'];
		foreach( $cfg['typeinfo'] as $type=>&$info )
		{
			$cfg['typecfg'][$type] = self::$kstype[$type];
		}
		$cfg['typecfg'][-1] = self::$kstype[-1];
		
		//根据门锁用户信息组织状态信息栏
		$cfg['valuecfg'] = array();
		$cfg['valuecfg']['0'] = ATTRCFG_MS_STATUS_SHANGSUO;
		$cfg['valuecfg']['-1'] = ATTRCFG_MS_STATUS_GUANLIYUAN;
		
		$cfg['user'] = array();
		$c = new TableSql('dev_lockuser');
		$userList = $c->queryAll('*','ATTRID=?',array($attrid));
		foreach( $userList as &$user )
		{
			$vid = self::statusValue($user['USERID'],$user['USERTYPE']);
			$cfg['valuecfg'][$vid] = $user['USERNAME'];
			
			if( !isset( $cfg['user'][ $user['USERTYPE'] ] ) )
			{
				$cfg['user'][ $user['USERTYPE'] ] = array();
			}
			$dateline = ( -1 ==  $user['ENDTIME'])?-1: $user['ENDTIME']*1000;
			$isalarm  = intval($user['ISALARM']);
			$cfg['user'][ $user['USERTYPE'] ][] = array(
				'dateline' => $dateline,
				'id'       => $user['USERID'],
				'isalarm'  => $isalarm,
				'name'     => $user['USERNAME'],
				'times'    => ( -1 == $user['USENUM'] )?'':$user['USENUM'],
				'status'   => $user['STATUS'],
			);
		}
		
		$userinfo = array();
		$c = new TableSql('hic_user','ID');
		if( 'b' == HIC_LOCAL )
		{
			$userinfo = $c->queryAll('*');
		}
		else
		{
			//hic_user是无限制访问，这会导致所有用户都被取出
			$cbind = new TableSql('hic_hicbind');
			$userList = $cbind->queryAllList('USERID','HICID=?',array( getSysUid() ));
			if( NULL != $userList )
			{
				$userList = implode(',',$userList);
				$userinfo = $c->queryAll('*',"ID IN ($userList)");
			}
		}
		foreach( $userinfo as $k=>&$v)
		{
			$vid = self::statusValue($v['ID'],-1);
			$cfg['valuecfg'][$vid] = $v['NAME'];
		}
		
		$c = new TableSql('dev_locklog');
		$logList = $c->queryAll('*','ATTRID=? ORDER BY OTIME DESC',array($attrid));
		foreach($logList as &$log )
		{
			$index = 'userlog';
			switch( intval($log['ACTION']) )
			{
				case MS_ACTION_KS:
					$index = 'ksrecord';
					break;
				case MS_ACTION_ALARM:
					$index = 'alrecord';
					$log['INFO'] = self::$msalarm[ $log['INFO'] ];
					break;
				case MS_ACTION_XIEPOALARM:
					$index = 'alrecord';
					break;
				default: //用户操作日志
					break;
			}
			if( !isset( $cfg[$index]  ) )
			{
				$cfg[$index] = array();
			}
			$day  = date("Y-m-d",$log['OTIME']);
			$time = date("H:i",$log['OTIME']);
			if( !isset( $cfg[$index][$day] ) )
			{
				$cfg[$index][$day] = array();
			}
			$cfg[$index][$day][] =  array('text'=>$log['INFO'], 'time'=>$time);
		}

		return $cfg;
	}

	static function getOtherInfo($value,$attrid=NULL)
	{
		//根据门锁用户信息组织状态信息栏
		$cfg['valuecfg'] = array();
		$cfg['valuecfg']['0'] = ATTRCFG_MS_STATUS_SHANGSUO;
		$cfg['valuecfg']['-1'] = ATTRCFG_MS_STATUS_GUANLIYUAN;
		return $cfg;
		
	}
	
	/**********************************************/
	//门锁离线后，当重新上线时获取所有用户信息与数据库信息相匹配。
	static function compare($attrid,$result)
	{
		$c = new TableSql('dev_lockuser');
		foreach( $result as $ut=>&$uidList )
		{
			if( NULL == $uidList )
			{
				$uidList = array();
			}
			$info = array();
			$info['t']  = 3;
			//查询当前ut的所有用户id
			$orgList = $c->queryAllList('USERID','USERTYPE=? AND ATTRID=?', array($ut,$attrid) );
			//同步新添加用户
			$adduser = array_diff($uidList,$orgList);
			foreach($adduser as $add)
			{
				$info['ut'] = $ut;
				$info['n']  = $add;
				$ui = self::addUser($attrid,$info);				
			}

			//删除已经不存在的用户
			$deluser = array_diff($orgList,$uidList);
			foreach($delUser as $del)
			{
				$info['ut'] = $ut;
				$info['n']  = $del;
				self::delUser($attrid,$info);
			}
		}
	}

	//user=>array('ut','n')
	static function log($attrid,$action,&$user,$info)
	{
		if( NULL === $info )
		{
			return;
		}
		//保存开门记录
		$log = array();
		$log['ATTRID']   = $attrid;
		$log['OTIME']    = time();
		$log['ACTION']   = $action;
		$log['USERTYPE'] = $user['ut'];
		$log['USERID']   = $user['n'];
		$log['INFO']     = $info;
		
		$c = new TableSql('dev_locklog');
		$c->add($log);
	}

	//保存开锁记录 'Ct/Cut/nn'
	static function saveKsrecord($attrid,$info)
	{
		//更新锁的状态：根据开锁用户ID和类型，组成一个唯一标记作为状态				
		$status = '-1';
		if( $info['n'] == 0 && $info['ut'] == 3 )
		{
			$status = '-1';
		}
		else
		{
			$status = self::statusValue($info['n'],$info['ut']);
		}		
		
		//五秒之后自动上锁
		$cmd = array('m'=>100);
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask('devattr','attr',5);
		$planTask->execAttr($attrid,$cmd);
		
		//获取指定的用户配置信息，检查
		$ksuser  = NULL;
		$times   = -1;
		$isalarm = false;
		if( -1 == $info['ut'] ) //无线用户
		{
			$ksuser = getUserName($info['n']);
		}
		else if( 0 == $info['n'] ) //门锁管理员
		{
			$ksuser = ATTRCFG_MS_STATUS_GUANLIYUAN;
		}
		else
		{
			$c  = new TableSql('dev_lockuser');
			$ui = $c->query('ISALARM,USENUM,USERNAME','ATTRID=? AND USERTYPE=? AND USERID=?',
							array($attrid,$info['ut'],$info['n']));
			if( NULL == $ui )
			{
				//直接往记录表中增添改用户
				//添加门锁用户'Ct/Cut/nn/Cr'
				$usercfg = array();
				$usercfg['t']  = 3;
				$usercfg['ut'] = $info['ut'];
				$usercfg['n']  = $info['n'];
				$ui = self::addUser($attrid,$info);
			}				
			$ksuser  = $ui['USERNAME'];
			$times   = intval($ui['USENUM']);
			$isalarm = $ui['ISALARM'];
		}
		
		//保存开锁记录
		self::log($attrid,MS_ACTION_KS,$info,$ksuser);
		
		//查看是否异常用户，如果是，则告警		
		if( $isalarm )
		{
			//向指定的用户和手机发送告警信息
			//暂时发所有手机
			$ainfo = $ksuser.ATTRCFG_MS_ALARM_YICHANGKS;
			self::log($attrid,MS_ACTION_XIEPOALARM,$info,$ainfo);
			self::alarm($attrid,$ainfo);
		}
		
		if( -1 != $times )
		{
			$times--;
			if( 0 >= $times ) //次数用尽，进行删除。更新
			{
				$times = 0;//次数用尽直接置为0.这时的times可能因为上次删除错误为负数了
			}
			//更新使用次数
			$ui = array('USENUM'=>$times); 
			$c  = new TableSql('dev_lockuser');
			$c->update($ui,NULL,'ATTRID=? AND USERTYPE=? AND USERID=?',
						array($attrid,$info['ut'],$info['n']));
			if( 0 == $times )
			{
				//发送删除指令
				self::autoDelUser(MS_DELUSER_REASON_CSYJ,$attrid,$info['ut'],$info['n']);

			}
		}
		
		return $status;
	}

	//保存告警记录'Ct/Cat'
	static function saveAlrecord($attrid,$info)
	{
		$user = array( 'ut'=>-1,'n'=>0 );
		self::log($attrid,MS_ACTION_ALARM,$user,$info['at']);
		self::alarm($attrid,self::$msalarm[$info['at']]);
	}
	
	//添加门锁用户'Ct/Cut/nn/Cr'
	static function addUser($attrid,$info)
	{
		//本地添加xxxx
		//xxx远程添加xxx
		//同步添加指纹用户x
		$cfg = Cache::get("msuseradd_$attrid");
		if( false == $cfg ) //门锁本地添加上报或者同步上报
		{
			//设置默认值
			$cfg = array();
			$cfg['username'] = ATTRCFG_MS_MS;
			$cfg['isalarm']  = false;
			$cfg['times']    = -1;
			$cfg['dateline'] = -1;
			$cfg['local']    = ATTRCFG_MS_LOCAL;
			if( 3 == $info['t'] ) //同步上报
			{
				$cfg['local']    = ATTRCFG_MS_SYNC;
			}
		}
		else  //无线用户添加
		{
			$cfg['local']    = ATTRCFG_MS_REMOTER;
			Cache::del("msuseradd_$attrid");
			Cache::set("adduser_$attrid",'ok',15);
		}
		$cfg['m']    = 1;
		$cfg['id']   = $info['n'];
		$cfg['type'] = $info['ut'];
		return self::updateUser($attrid,$cfg);
	}
	
	//修改用户信息 {'m'=>2(修改)/1/7,'name','dateline','times','isalarm','status','id','type'}
	static function updateUser($attrid,$cfg)
	{
		$c = new TableSql('dev_lockuser');
		$msuser = array( 'ut'=>$cfg['type'], 'n'=>$cfg['id'] );

		if( 2 == $cfg['m'] ) //修改用户
		{
			$cfg['local']    = ATTRCFG_MS_REMOTER;
			$cfg['username'] = $GLOBALS['curUserName'];
			$orgname = $c->queryValue('USERNAME',
										'ATTRID=? AND USERTYPE=? AND USERID=?',
										array($attrid,$cfg['type'],$cfg['id']));
		}

		if( 'false' == $cfg['isalarm'] ) $cfg['isalarm'] = false;
		
		if( -1 != $cfg['dateline'] ) //后台传来时间是毫秒
		{
			$cfg['dateline'] /= 1000;
		}
		$cfg['dateline'] = intval($cfg['dateline']);

		if( NULL == $cfg['name'] )
		{
			$cfg['name'] = self::$kstype[$cfg['type']].$cfg['id'];
		}
		if( NULL == $cfg['times'] ) $cfg['times'] = -1;
		
		//添加用户信息
		$user = array();
		$user['ATTRID']    = $attrid;
		$user['USERID']    = $cfg['id'];
		$user['USERTYPE']  = $cfg['type'];
		$user['ISALARM']   = $cfg['isalarm']?1:0;
		$user['STATUS']    = 1;//暂时没处理停用启用，默认启用
		$user['USENUM']    = $cfg['times'];
		$user['STARTTIME'] = 0;//还未处理开始时间
		$user['ENDTIME']   = $cfg['dateline'];
		$user['USERNAME']  = $cfg['name'];
		
		$action = MS_ACTION_ADDUSER;
		if( 2 == $cfg['m'] ) //修改用户
		{
			$c->update( $user,NULL,
						'ATTRID=? AND USERTYPE=? AND USERID=?',
						array($attrid,$cfg['type'],$cfg['id']));

			//如果改名，需要先设置日志
			if( $orgname != $cfg['name'] )
			{
				$log = sprintf( ATTRCFG_MS_RENAMEUSER, $cfg['username'],$orgname, $cfg['name']);
				self::log($attrid,MS_ACTION_USERRENAME,$msuser,$log);
			}
			//如果改了其它信息，设置修改信息
			$action = MS_ACTION_USERMODI;
			$log    = sprintf(ATTRCFG_MS_MODIUSER,$cfg['username'], $cfg['name']);
			
		}
		else
		{
			$exist = $c->query('ATTRID','ATTRID=? AND USERID=? AND USERTYPE=?',
						array($user['ATTRID'],$user['USERID'],$user['USERTYPE']));
			if( NULL == $exist )
			{
				$c->add($user);
				//添加日志
				$log = sprintf( ATTRCFG_MS_USERINFO,
								$cfg['username'],$cfg['local'],
								ATTRCFG_MS_ADD, $cfg['name']
								);
				
			}
		}
		self::log($attrid,$action,$msuser,$log);
		//发送告警信息
		self::alarm($attrid,$log);
		
		
		//检测有效期，设置定时删除
		if( -1 != $cfg['dateline'] )
		{
			//设置到期删除
			//{ m:'101',reason,ut,n,execnum }
			$cmd = array('m'=>101,'reason'=>MS_DELUSER_REASON_GQ,'ut'=>$msuser['ut'],'n'=>$msuser['n'] );
			$expire = date('Y-m-d H:i:s',$cfg['dateline']);
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('devattr','attr',$expire);
			$planTask->execAttr($attrid,$cmd);
		}

		return $user;
	}

	//删除用户'Ct/Cut/nn/Cr'
	static function delUser($attrid,&$info)
	{
		$username = ATTRCFG_MS_MS; //默认门锁
		$local    = ATTRCFG_MS_LOCAL;
		$ut = $info['ut'];
		$n  = $info['n'];
		
		if( 3 == $info['t'] ) 
		{
			$local = ATTRCFG_MS_SYNC;
		}

		$cfg = Cache::get("msuserdel_$attrid_$ut_$n");
		if( false != $cfg ) //门锁本地添加上报或者同步上报
		{
			Cache::del("msuserdel_$attrid_$ut_$n");
			switch( $cfg['reason'] )
			{
				case MS_DELUSER_REASON_CSYJ: //次数用尽
					$local    = ATTRCFG_MS_CSYJ;
					break;
				case MS_DELUSER_REASON_GQ: //过期删除
					$local    = ATTRCFG_MS_GQ;
					break;
				case MS_DELUSER_REASON_APP: //无线删除
				default:
					Cache::set("deluser_$ut_$n_$attrid",'ok',15);
					$username = $cfg['username'];
					$local    = ATTRCFG_MS_REMOTER;
					break;
			}
		}
		
		//删除指定用户
		$c = new TableSql('dev_lockuser');
		$msuser = $c->queryValue('USERNAME',
								'ATTRID=? AND USERTYPE=? AND USERID=?',
								array($attrid,$ut,$n));
		$c->del('ATTRID=? AND USERTYPE=? AND USERID=?',array($attrid,$ut,$n));

		//写日志
		$log = sprintf( ATTRCFG_MS_USERINFO,$username,$local, ATTRCFG_MS_DEL,$msuser );
		self::log($attrid,MS_ACTION_DELUSER,$info,$log);
		//发送告警信息
		self::alarm($attrid,$log);		
		
		//删除对应的智能模式
		self::delSmart($attrid,$ut,$n);
	}
	
	//设置门锁管理员密码
	static function updatePassword($attrid,$value)
	{
		$c = new TableSql('homeattr','ID');
		$info = $c->query('ID,ATTRSET','ID=?',array($attrid));
		$info['ATTRSET'] = unserialize($info['ATTRSET']);
		if( false == $info['ATTRSET'] )
		{
			$info['ATTRSET'] = array();
		}
		switch( $value['m'] )
		{
			case 5:
				$info['ATTRSET']['password'] = $value['newpw'];	
				break;
			case 6:
				$info['ATTRSET']['password'] = $value['pw'];	
				break;
			default:
				break;
		}
		$info['ATTRSET'] = serialize($info['ATTRSET']);
		
		$c->update($info);
		
		//写用户日志
		$msuser = array( 'ut'=>-1, 'n'=>$GLOBALS['curUserID'] );
		$log = sprintf(ATTRCFG_MS_MODIPSW,$GLOBALS['curUserName']);
		self::log($attrid,MS_ACTION_PSWMODI,$msuser,$log);
	}
	
	//门锁的报警通知
	static function alarm($attrid,$info)
	{
		if( NULL === $info )
		{
			return;
		}
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->sendNotice(NULL,$attrid,$info);
	}

	//删除门锁用户所对应的联动列表
	static function delSmart($attrid,$type,$id,$username=NULL)
	{
		$c = new TableSql('smartdev');
		$c1  = new TableSql('smartsmart','ID'); 
		$c2  = new TableSql('dev_lockuser'); 
		
		
		
		$vid = self::statusValue($id,$type);
		$delid = $c->queryAllList('SID','ATTRID=?',array($attrid));
		foreach ($delid as $sid) 
		{
			$user = $qcond['sub']['0']['sub']['0']['VALUE1'];
			if( !in_array($vid,$user) )
			{
				continue;
			}
			if( 1 == sizeof($user) )
			{
				$c->del('SID=?',array($sid));
				$c1->del('ID=?',array($sid));
				continue;
			}

			$info = $c1->query('ID,NAME,COND,QCOND','ID=?',array($sid));
			$qcond = unserialize($info['QCOND']);
			
			unset($user[array_flip($user)[$vid]]);
			$qcond['sub']['0']['sub']['0']['VALUE1'] = array_values($user);
			$cond = unserialize($info['COND']);
			$cond['cond'][0]['cond'][0] = str_replace(','.$vid,"",$cond['cond'][0]['cond'][0]);
			$cond['cond'][0]['cond'][0] = str_replace($vid.',',"",$cond['cond'][0]['cond'][0]);

			$info['QCOND'] = serialize($qcond);
			$info['COND'] = serialize($cond);
			
			if( NULL == $username )
			{
				$username = $c2->queryValue('USERNAME',
									'ATTRID=? AND USERTYPE=? AND USERID=?',
									array( $attrid,$type,$id ));
				
			}

			$info['NAME'] = str_replace($username,"",$info['NAME']);

			$c1->update($info);
				
		}
	}

	
	//////////////////////////////////////////////////////////
	//初始化门锁
	static function resetLock($attrid)
	{
		//清空管理员密码，和所有记录信息
		$info = array();
		$info['ID']     = $attrid;
		$info['ATTRSET']= NULL;
		$c = new TableSql('homeattr','ID');
		$c->update($info);
		
		$c = new TableSql('dev_lockuser');
		$c->del('ATTRID=?',array($attrid));
		$c = new TableSql('dev_locklog');
		$c->del('ATTRID=?',array($attrid));

		self::alarm($attrid,ATTRCFG_MS_RESET);							
		return;
	}
	
	//次数用尽或者过期删除指定用户,
	//execnum。已经执行了多少次。多次尝试删除，如果还是不成功，则报错
	static function autoDelUser($reason,$attrid,$ut,$n,$execnum=0)
	{
		//首先判断用户是否需要删除。如果用户已经删除或者条件不满足，直接返回
		$c = new TableSql('dev_lockuser');
		$uiinfo = $c->query('USENUM,ENDTIME,USERNAME',
						'ATTRID=? AND USERTYPE=? AND USERID=?',
						array($attrid,$ut,$n)
						);
		if( NULL == $uiinfo  )
		{
			return;
		}
		//如果没超时次数也没用尽，则无需删除
		if( (0 != $uiinfo['USENUM']) && ( ( time() < $uiinfo['ENDTIME'] ) || ( -1 == $uiinfo['ENDTIME'] ) ) )
		{
			return;
		}

		$execnum++;
		//如果已经尝试删除次数太多，记录删除识别日志
		if( $execnum > 5 )
		{
			$msuser = array('ut'=>$ut,'n'=>$n);
			$log = sprintf(ATTRCFG_MS_DELFAIL,$uiinfo['USERNAME']);
			self::log($attrid,MS_ACTION_DELUSER,$msuser,$log);
			//发送告警信息
			self::alarm($attrid,$log);
			return;
		}		
		//发送删除命令
		$cmd = array();
		$cmd['m']      = 3;
		$cmd['id']     = $n;
		$cmd['type']   = $ut;
		$cmd['reason'] = $reason;
		$GLOBALS['dstpSoap']->setModule('devattr','attr');
		$GLOBALS['dstpSoap']->execAttr($attrid,$cmd);		
		
		//{ m:'101',reason,ut,n,execnum }
		$cmd = array('m'=>101,'reason'=>$reason,'ut'=>$ut,'n'=>$n,'execnum'=>$execnum );
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask('devattr','attr',30);
		$planTask->execAttr($attrid,$cmd);
	}
	
	//开锁五秒之后自动回锁
	static function onLock($attrid)
	{
		$c = new TableSql('homeattr','ID');	
		$info = $c->query('DEVID,ATTRINDEX','ID=?',array($attrid));
		$status = array();
		$status['ATTRINDEX'] = $info['ATTRINDEX'];
		$status['STATUS']    = pack("C2",7,0);
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->updateDevStatus($info['DEVID'],array($status));
	}
}
?>