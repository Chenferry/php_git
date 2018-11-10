<?php
//调用方式
/* 
$planTask = new PlannedTask('task',NULL,$time);
$planTask->getNameByID($para);

$para是接口参数
$time可能如下取值:
1.array:参数如果是一个数组。表示每日/每周/每月/每年/每分钟/定点执行等类型的周期任务。其中
	array['cyc']   : 执行周期，取值PLAN_DAY/PLAN_WEEK/PLAN_MONTH/PLAN_YEAR/PLAN_TIME/PLAN_ONCE，
						分别对应每日/每周/每月/每年/每分钟/定点执行。
						如果不存在或是其它值，则默认为每天PLAN_DAY
	array['other'] : 对于不同TYPE的取值和解释如下
						每日     / 每周          / 每月      / 每年                      / 每分钟     / 定点
						INT      / INT(0-6)      / INT(1-31) / MM-DD|MM                  / INT        / YYYY-MM-DD
						间隔几天 / 周几(周日为0) / 几号      / 年中第几天|指定月份每一天 / 间隔几分钟 / 指定日期。
						如果不存在或是其它非法值，则取默认值1或当天
	array['time']  : 执行时间，格式"HH:MM"，如果没有，默认为"00:00"
	array['etime'] : 结束时间，格式"HH:MM"，如果没有，默认为"24:00"。这个只对外部有用，对计划本身没含义忽略
	array['hday']  : 遇到假期的处理，取值PLAN_HDAY_IGNORE/PLAN_HDAY_DELAY/PLAN_HDAY_CANCEL/PLAN_WORKDAY_CANCEL
	array['ahead'] : 提前多长时间处理，单位为秒。负数表示延后处理
	array['start'] : 循环开始处理时间，YYYY-MM-DD H:i。如果只填YYYY-MM-DD，则H:i为00:00。如果为空，默认当前开始
	array['end']   : 循环结束处理时间，YYYY-MM-DD H:i。如果只填YYYY-MM-DD，则H:i为24:00。如果为空，默认永不结束
2.数字或者字串
	NULL：等同于数字0
	数字：表示缓存后延迟指定秒数后交给系统立即执行的任务。
			  如果某个动作结果不需要返回给用户，则可以先缓存下交由系统执行，提供页面的用户响应速度
	str ：表示定点执行的任务，其中str是一个'YYYY-MM-DD HH:MM' 格式的字串，表示执行时间，小时以24小时表示.
			  如果填"HH:MM",表示当天时间
*/


class PlannedTask
{
	var $taskInfo;
	var $batch;
	var $w;
	var $client;
	var $orgUserID;
	
	var $weekDay = array( 'Sunday','Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
	
	function __construct($module=NULL,$serviceName=NULL, $time=NULL )
	{
		$this->batch = new TableSql('batchproc','ID');
		$this->client= &$GLOBALS['dstpSoap'];

		$this->taskInfo = array();					
		$this->taskInfo['DSTPMODULE'] = $module;
		if ( NULL == $serviceName )
		{
			$serviceName = $module;
		}
		$this->taskInfo['SERVICE'] = $serviceName;
		
		if ( isset($GLOBALS['curUserID']) )
		{
			$this->taskInfo['USERID'] = $GLOBALS['curUserID'];
		}

		$this->getPlanInfo($time);
	}
	
	function setModule($module=NULL,$serviceName = NULL)
	{
		$this->taskInfo['DSTPMODULE'] = $module;
		if ( NULL == $serviceName )
		{
			$serviceName = $module;
		}
		$this->taskInfo['SERVICE'] = $serviceName;
		return true;
	}
	function setTime($time=NULL)
	{
		return $this->getPlanInfo($time);
	}
	
	function getAllExecTime(&$time,&$result)
	{
		if( PLAN_ONCE == $time['cyc'] )
		{
			$result[] = $time['scyc'];
			return;
		}
		if ( 0 >= $time['other'] )
		{
			return false;
		}
		

		$stime = $time['start'];//查询开始
		$etime = $time['end'];
		if ( $stime >= $etime )
		{
			return false;
		}
		
		$scyc = $time['scyc'];//周期
		$ecyc = $time['ecyc'];//周期
		if ( $scyc > $ecyc )
		{
			return false;
		}

		$starttime = $stime - $space;
		$starttime = $stime > $scyc ? $stime:$scyc;
		if ( PLAN_DAY == $time['cyc'] )
		{
			//每日执行的和开始日期有关。所以需要特别处理保证起始时间是正确的
			$starttime = $scyc;
			if ( $scyc < $stime )
			{
				$starttime = $starttime + intval(( $stime - $starttime )/$space)*$space;
			}
		}

		$endtime = $etime > $ecyc ? $ecyc:$etime;		
		$time['curtime'] = $starttime;	
		$execTime = $this->getFirstExecTime($time);		
		while( $execTime <= $endtime )
		{
			$result[] = $execTime;
			$time['curtime'] = $execTime+1;
			$execTime = $this->getFirstExecTime($time);
		}	
		return true;		
	}
	
	function getFirstExecTime($time=NULL,$level=0)
	{
		if ( $level > 31 )
		{
			return 0; //有可能设置错误，比如每周六又要忽略节假日，就可能导致循环
		}
		$execTime = $this->getPlanInfo($time);
		if ( !is_array($time) )
		{
			return $execTime;
		}
		if ( !isset($time['hday']) )
		{
			return $execTime;
		}
		if ( PLAN_HDAY_IGNORE == $time['hday'] )
		{
			return $execTime;
		}

		//判断指定时刻是否为工作日
		include_once('workDays/workDays.class.php'); 
		$curDay =  UTIL::getCurTime($execTime); 
		$cWorkDays = new workDaysCls;
		$isWorkday = $cWorkDays->isWorkDay( $curDay );
		
		//根据工作日设置，判断当前计算时间是否需要重调整
		if ( ($isWorkday 
				&& (PLAN_HDAY_CANCEL == $time['hday']  || PLAN_HDAY_DELAY == $time['hday'] )) 
			|| ( !$isWorkday && PLAN_WORKDAY_CANCEL == $time['hday']  )
			)
		{
			return $execTime;
		}

		switch($time['hday'])
		{
			case PLAN_HDAY_DELAY:
				$nextwd = $cWorkDays->getNextWorkDay( $curDay );
				return $execTime + ( $nextwd - $curDay )*SEC_EVERY_DAY;
				break;
			case PLAN_HDAY_CANCEL:
				$nextwd = $cWorkDays->getNextWorkDay( $curDay );
				$time['curtime'] = $execTime + ( $nextwd - $curDay )*SEC_EVERY_DAY;
				return $this->getFirstExecTime($time,$level++);
				break;
			case PLAN_WORKDAY_CANCEL:
				//只在假日触发。计算下一个节假日
				$nextwd = $cWorkDays->getNextHoliday( $curDay );
				$time['curtime'] = $execTime + ( $nextwd - $curDay )*SEC_EVERY_DAY;
				return $this->getFirstExecTime($time,$level++);
				break;
			case PLAN_HDAY_IGNORE:	
			default:
				break;
		}
		
		return 0;
	}
	
	function reset($module=NULL, $serviceName=NULL, $time=NULL )
	{
		$this->setModule($module);
		$this->setTime($time);
	}

	//根据time信息设定计划类型和执行的时间信息
	private function getPlanInfo($time)
	{
		//直接输入数字的，表示取相对当前时间的偏移
		if (  NULL!=$time && is_numeric($time))
		{
			$time = time()+intval($time);
			$time = date('Y-m-d H:i:s',$time);
		}
		$curTime = NULL;

		if ( is_array($time) )
		{
			$this->taskInfo['TYPE'] = intval($time['cyc']);
			if(!in_array( $this->taskInfo['TYPE'], 
					array(PLAN_ONCE,PLAN_DAY,PLAN_WEEK,PLAN_MONTH,PLAN_YEAR,PLAN_TIME)))
			{
				$this->taskInfo['TYPE'] = PLAN_DAY;
			}

			$this->taskInfo['RUNTIME'] = trim($time['time']); //这儿要检查书写格式是否正确
			if ( NULL == $this->taskInfo['RUNTIME'] )
			{
				$this->taskInfo['RUNTIME'] = '00:00';
			}

			if ( PLAN_ONCE == $this->taskInfo['TYPE'] )
			{
				$this->taskInfo['RUNTIME'] = $time['other'].' '.$this->taskInfo['RUNTIME'];
				unset($time['other']);
			}

			$this->taskInfo['DAY'] = intval($time['other']);
			if ( PLAN_YEAR == $this->taskInfo['TYPE'] ) //这个的other是mm-dd形式，需要转为天数
			{
				$ds = array(0,31,60,91,121,152,182,213,244,274,305,335,366);
				list($m,$d) = explode('-',$time['other']);
				$m = intval($m);
				$d = intval($d);
				if ( !isset($ds[$m]) )
				{
					$m = 1;
				}
				$this->taskInfo['DAY'] = $ds[$m-1] + $d;
				//不能变成下个月的，最多只能设置到月末
				if ( $this->taskInfo['DAY'] > $ds[$m] )
				{
					$this->taskInfo['DAY'] = $ds[$m];
				}
			}
			if ( 0 >= $this->taskInfo['DAY'])
			{
				$this->taskInfo['DAY'] = 1;
			}

			$this->taskInfo['DAYOFF'] = intval($time['hday']);
			if(!in_array( $this->taskInfo['DAYOFF'], 
					array(PLAN_HDAY_IGNORE,PLAN_HDAY_DELAY,PLAN_HDAY_CANCEL,PLAN_WORKDAY_CANCEL)))
			{
				$this->taskInfo['DAYOFF'] = PLAN_HDAY_IGNORE;
			}
			
			$this->taskInfo['AHEAD'] = intval($time['ahead']);
			
			$this->taskInfo['STARTTIME'] = 0;
			if ( isset($time['start']) )
			{
				$this->taskInfo['STARTTIME'] = strtotime($time['start']);
				$curTime = $this->taskInfo['STARTTIME']>time()?$this->taskInfo['STARTTIME']:time();
			}
			
			$this->taskInfo['ENDTIME'] = INFINITE_TIME;
			if ( isset($time['end']) )
			{
				$this->taskInfo['ENDTIME'] = strtotime($time['end']);
				//如果是YYYY-MM-DD这种形式，没指定H:i的，还要再加86400表示直接到24:00
				if( false === strpos( $time['end'], ':' ) )
				{
					$this->taskInfo['ENDTIME'] += SEC_EVERY_DAY;
				}
			}
			
			if ( isset($time['curtime']) )
			{
				$curTime = intval( $time['curtime'] );
			}
		}
		else //单字串，指定运行时间
		{
			$this->taskInfo['TYPE']    = PLAN_ONCE;
			$this->taskInfo['DAY']     = NULL;
			$this->taskInfo['RUNTIME'] = $time;
			$this->taskInfo['DAYOFF']  = PLAN_HDAY_IGNORE;
			//如果有需要提前的，必须自行转换为数组格式
			$this->taskInfo['AHEAD']   = 0;
		}
		return $this->calcExcTime($this->taskInfo,$curTime);
	}
	
	//处理用户调用请求，添加到任务库中
	function __call($func,$para)
	{
		$this->taskInfo['USERID'] = $GLOBALS['curUserID'];
		$this->taskInfo['METHOD'] = $func;
		$this->taskInfo['METHODARRAY'] = serialize($para);// $this->xml->genXmlByVar($para);
		
		$callTimer = false;
		//因为计划维护任务可能会有1分钟误差，在延时小于10分钟时1分钟是较大误差，所以需要用定时器唤醒
		if ( (PLAN_ONCE == $this->taskInfo['TYPE']) &&  ('c' != HIC_LOCAL) )
		{
			if( NULL == $this->taskInfo['RUNTIME'])
			{
				$callTimer = 0;
			}
			else
			{
				$diff = $this->taskInfo['EXCTIME']-time();
				if( $diff < 600 )
				{
					if( $diff < 1 ) $diff = 1;
					$callTimer = $diff;
				}
			}
		}
		
		if( false !== $callTimer )
		{
			//定时器调用任务通过设置STARTTIME值为一个特殊值来区分
			$this->taskInfo['STARTTIME'] = INFINITE_TIME - 10; 
			$planID =  $this->batch->add1($this->taskInfo);
		}
		else
		{
			$planID =  $this->batch->add($this->taskInfo);
		}

		
		if( false !== $callTimer )
		{
			//本地和外网的延迟要分离，以免互相阻塞
			$dp = getRealPort(HIC_SERVER_DELAY);//HIC_SERVER_DELAY;
			//发往服务器的处理有大量操作涉及到网络通讯，可能有很大时延
			//因此专门设置了一个进程只处理发往服务器的操作
			if( ( 'c' == $GLOBALS['dstpSoap']->getModuleAddr($this->taskInfo['DSTPMODULE']) ) 
				|| ( 'delay' == $this->taskInfo['DSTPMODULE'] ) )
			{
				$dp = getRealPort(HIC_SERVER_DELAY_E);//;
			}
			
			$info = "delay:$callTimer-$planID\n";
			
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendMsgBySocket($dp,$info,getRealPort('127.0.0.1'));
		}
		return $planID;
	}
	//更新任务计划
	function update($id,$para=NULL,$func=NULL)
	{
		if( NULL != $func )
		{
			$this->taskInfo['METHOD'] = $func;
		}
		if ( NULL != $para )
		{
			$this->taskInfo['METHODARRAY'] = serialize($para);
		}
		$this->taskInfo['ID'] = $id;
		return $this->batch->update($this->taskInfo);	
	}
	//删除任务计划
	function del($id)
	{
		return $this->batch->delByID($id);
	}
	
	//运行接口。由操作系统的计划调度任务不停的调度该函数。函数自动判断哪些任务需要执行
	//调用计划任务时，必须先设定两个全局变量curUserID和curUserName。可以不用设置值
	function runPlanTask()
	{
		//获得当前需要运行的任务，逐个取出，不要一次性全取出。避免因为运行时间太长，计划任务同时多次运行出错
		$cMaintence = new TableSql('sysmaintnceinfo');
		$this->orgUserID = $GLOBALS['curUserID'];
		
		//在目前的计划任务下，无论如何，每次维护任务执行，每个ID理论上都应该只执行一次 
		//但有次看到有个每月执行的连续插入了两万多条？在没找到原因前要先避免
		//把所有已经执行过的先保存，发现取到一样的就跳过
		$hasExec = array(); 
		//获得当前绝对时间
		$curTime = time();
		$preID = $cMaintence->queryValue('MTPLANID'); 
		if ( NULL == $preID  )
		{
			$preID = 0;
			$cMaintence->add(array('MTPLANID'=>0));							
		}
		//判断今日是否工作日
		include_once('workDays/workDays.class.php'); 
		$this->cWorkDays = new workDaysCls;
		$this->isWorkday = $this->cWorkDays->isWorkDay( UTIL::getCurTime() );
		
		//有些计划任务需要检测系统运行是否连续的。如果有中断，需要重新检测时间条件是否满足
		$GLOBALS['planIgnore'] = false;
		$preTime = Cache::get('plantasktime');
		if( $curTime > intval($preTime)+120 )
		{
			$GLOBALS['planIgnore'] = true;
		}
		
		Cache::set('plantasktime',$curTime);
		
		while ( 1 )
		{
			if ( (time()- $curTime) > ( MAX_EXEC_TIME - 8 )) 
			{
				//为了避免超时被强行中断运行，这儿在快超时时主动中断。尽量减少强行中断引起的意外
				break;
			}
			//sqlite好像不支持在查询语句里进行运算(EXCTIME-AHEAD)<=?，会导致出错。所以暂时注掉AHEAD的处理
			//经过试验'(EXCTIME-AHEAD)<=?'和'(EXCTIME)<=(AHEAD+?)'在逻辑上虽然等价。但后一种写法是可以正确处理的 
			//$this->w = $this->batch->query('*','(EXCTIME-AHEAD)<=? AND ID!=?',array($curTime,$preID));
			$this->w = $this->batch->query('*','(EXCTIME)<((AHEAD)+?) AND ID!=? AND (EXCTIME>=STARTTIME) AND (EXCTIME<=ENDTIME) ',array($curTime,$preID));
			if ( NULL == $this->w )
			{
				//正常退出
				$mi = array();
				$mi['MTPLANID'] = 0;
				$cMaintence->update1( $mi );
				break;
			}
			$mi = array();
			$mi['MTPLANID'] = $this->w['ID'];
			$cMaintence->update1( $mi );
			$curID = $this->w['ID'];
			
			//无论哪一种执行方式。在执行完finishTask后，都不应该在接后面的query能被查询到
			//但有次看到有个每月执行的连续插入了两万多条？在没找到原因前要先避免
			if ( in_array( $curID,$hasExec )  )
			{
				$this->w['EXCTIME'] = $curTime + 1 + intval($this->w['AHEAD']);
				$this->batch->update1($this->w);
				continue; //这要还能死循环就服了
			}
			
			$hasExec[] = $curID;
			//执行，并在执行后更新计划时间
			$this->finishTask($this->w);

			$preID = $cMaintence->queryValue('MTPLANID'); 
			if ( $curID != $preID )
			{
				break; //可能是别人已经进入
			}

			
		}
		return ;
	}
	
	//运行任务	
	private function finishTask(&$a)
	{
		$isWorkday = $this->isWorkday;
		if ( 0 != $a['AHEAD'] )
		{
			//$execday = intval($a['EXCTIME']/SEC_EVERY_DAY);
			//$isWorkday = $this->cWorkDays->isWorkDay( $execday );
			$isWorkday = $this->cWorkDays->isWorkDay( UTIL::getCurTime($a['EXCTIME']) );
		}


		//计算本次是否要执行
		$isExec = true;
		if ( (!$isWorkday 
				&& (PLAN_HDAY_CANCEL == $a['DAYOFF'] || PLAN_HDAY_DELAY == $a['DAYOFF'])) 
			|| ( $isWorkday && PLAN_WORKDAY_CANCEL == $a['DAYOFF'] )
			)
		{
			$isExec = false;
		}
		
		
		
		//这种任务要检查原设置任务是否已经被删除
		//如果不是工作日，PLAN_DELAYONCE必定会走入上一个判断，这儿也就无需再处理
		if ( $isWorkday && PLAN_DELAYONCE == $a['TYPE'] )
		{
			//如果原来是runonce,则执行。否则要检查原ID是否已经删除
			$r = preg_match("/^(\d{1,4})-(\d{1,2})-(\d{1,2})(\s+)(\d{1,2}):(\d{1,2})[:]*([\d{1,2}]*)/i",$a['RUNTIME'],$m);

			if ( 0 == $r ) //runonce的runtime必须满足上面的形式 
			{
				//检查$a['DAY']所代表的ID是否还存在
				if ( 0 == $this->batch->getRecordNum('ID=?',array($a['DAY'])) )
				{
					$this->batch->delByID($a['ID']);
					return;
				}
			}
		}
				
		//设置调用环境
		if ( NULL != $a['USERID'])
		{
			$GLOBALS['curUserID'] = $a['USERID'];
		}
		else
		{
			$GLOBALS['curUserID'] = $this->orgUserID;
		}
		
		
		$module  = $a['DSTPMODULE'];	
		$service = $a['SERVICE'];	

		$array = unserialize($a['METHODARRAY']); //$this->xml->getArrayFromXmlStr($a['METHODARRAY']);

		$this->client->setModule($module,$service);
		//$this->client->setDebug(false);
		
		//执行。没有因为假期要求而实际取消执行
		if ( $isExec )
		{
			$this->client->__call($a['METHOD'],($array));	
		}
		else
		{
			//如果是延时的，不影响原来的时间计算，但新插入一条只执行一次的特殊计划
			//这种类型的任务执行前，要先检查所属的原计划任务是否已经删除
			if ( PLAN_HDAY_DELAY == $a['DAYOFF'] )
			{
				$na = $a;
				//这种特殊类型的该字段特殊处理。用来记录原ID。以后执行时，查看该ID是否已经删除
				//如果ID已经删除，则该延迟执行的也不执行.如果是PLAN_ONCE的，因为ID已经被删除，通过RUNTIME来判断
				if ( PLAN_DELAYONCE != $a['TYPE'] )
				{
					$na['DAY']  = $a['ID'];
				}
				
				$na['TYPE'] = PLAN_DELAYONCE;
				$na['EXCTIME']  = $a['EXCTIME'] + SEC_EVERY_DAY; //延迟一天，每天检查一遍
				$this->batch->add1($na);
			}
		}

		//如果是一次执行任务，删除.如果是计划任务，做下次运行时间标记
		if ( PLAN_ONCE == $a['TYPE'] || PLAN_DELAYONCE == $a['TYPE'])
		{
			$this->batch->del1('ID=?',array($a['ID']));
		}
		else
		{
			$this->calcExcTime($a);
			if( PLAN_TIME == $a['TYPE'] )
			{
				$this->batch->update1($a);
			}
			else
			{
				$this->batch->update($a);
			}
		}
		return;
	}
	
	//计算下一时刻应该运行的时间,并把计算结果赋值到$taskInfo[EXCTIME]
	private function calcExcTime(&$taskInfo,$curTime = NULL )
	{
		if ( NULL == $curTime )
		{
			$curTime = time();
		}
		$ahead = 0;
		if ( isset($taskInfo['AHEAD']) )
		{
			$ahead = intval( $taskInfo['AHEAD'] );
		}
		
		$m = $this->parseRuntime( $taskInfo['RUNTIME'] );
		
		$timess = $this->getCurCycTime($taskInfo['TYPE'],$curTime,$taskInfo,$m);
		//如果当前时间已经超过本周期运行时间，则取下一周期的运行时间
		while( ( ($curTime > ($timess-$ahead)) || ($timess < ($taskInfo['STARTTIME'])) ) 
			&& (PLAN_ONCE!=$taskInfo['TYPE'])) 
		{
			$timess = $this->getNextCycTime($taskInfo['TYPE'],$timess,$taskInfo,$m);
		}
		if( isset($taskInfo['ENDTIME']) && $timess > $taskInfo['ENDTIME'] )
		{
			$timess = INFINITE_TIME;
		}
		$taskInfo['EXCTIME'] = $timess;
		return $taskInfo['EXCTIME'];
	}
	
	//获得指定时间所处周期中应该的运行时间
	private function getCurCycTime($type,$curTime,&$taskInfo,&$m)
	{
		if ( isset($taskInfo['EXCTIME']) && 0 != $taskInfo['EXCTIME'] )
		{
			return $taskInfo['EXCTIME'];
		}
		$timess = 0;
		switch( $type )
		{
			case PLAN_ONCE:
				//int mktime ( [int hour [, int minute [, int second [, int month [, int day [, int year [, int is_dst]]]]]]])
				//如果是一次运行时间，且time为空，则运行时间为0，表示立即执行。立即返回
				switch( count($m) )
				{
					case 3://hh:mm的写法，$m[0]是匹配整个字串
						$timess = mktime($m[1],$m[2]);
						break;
					case 4://hh:mm:ss的写法，$m[0]是匹配整个字串
						$timess = mktime($m[1],$m[2],$m[3]);
						break;
					case 7://$m[4]是匹配中间的一个空格串
						$timess = mktime($m[5],$m[6],0,$m[2],$m[3],$m[1]);
						break;
					case 8://$m[4]是匹配中间的一个空格串
						$timess = mktime($m[5],$m[6],$m[7],$m[2],$m[3],$m[1]);
						break;
					default://非法格式，直接返回0
						break;
				}
				break;
			case PLAN_DAY:
				//当天的指定时间
				//$timess = intval($curTime/SEC_EVERY_DAY)*SEC_EVERY_DAY + 3600*intval($m[1]) + 60*intval($m[2]);
				$timess = strtotime(date("Y-m-d $m[1]:$m[2]",$curTime));
				break;
			case PLAN_WEEK:
				$week = $this->weekDay[$taskInfo['DAY']];
				//$timess = strtotime ("$week", $curTime) + mktime($m[1],$m[2],0) - mktime(0,0,0); //查找本周应该运行相应的时刻
				$timess = strtotime ("$week", $curTime) + 3600*intval($m[1]) + 60*intval($m[2]);//mktime($m[1],$m[2],0) - mktime(0,0,0); //查找本周应该运行相应的时刻
				break;
			case PLAN_MONTH:
				//得到本月应该运行时间
				$tmp = getdate( $curTime );
				$t   = idate('t',$curTime);
				$tmp['mday'] = ($taskInfo['DAY']<$t) ? $taskInfo['DAY']:$t;
				$timess = mktime($m[1],$m[2],0,$tmp['mon'],$tmp['mday'],$tmp['year']);
				break;
			case PLAN_YEAR:
				$z = idate('z',$curTime);//得到指定时间是年份中的第几天，这个从0开始
				$L = idate('L',$curTime);//如果是闰年则返回 1，否则返回 0
				
				$d = $this->getDayOffset($taskInfo['DAY'],$L);
				$timess = strtotime(date("Y-m-d $m[1]:$m[2]",$curTime));
				$timess = $timess + ($d-$z-1)*SEC_EVERY_DAY;//$z从0开始，而$d从1开始，所以要再减1
				break;
			case PLAN_TIME:
				$timess = $curTime;
				break;
			default:
				$timess = $curTime + 3600;
				break;
		}
		return $timess;
	}
	
	//获得指定时间所处下一周期中应该的运行时间
	private function getNextCycTime($type,$timess,&$taskInfo,&$m)
	{
		$interval = intval($taskInfo['DAY']);
		if ( 0 >= $interval )
		{
			$interval = 1;//避免死循环
		}
		switch( $type )
		{
			case PLAN_ONCE:
				break;
			case PLAN_DAY:
				//$taskInfo['EXCTIME'] = strtotime ("+$interval day",mktime($m[1],$m[2],0));   //当前时间超过，放到下[DAY]天的指定时间执行
				$timess = $timess + $interval*SEC_EVERY_DAY;
				break;
			case PLAN_WEEK:
				//$taskInfo['EXCTIME'] = strtotime ("next week",$timess);   //当前时间超过本周应该运行时间，放到下[DAY]周的指定时间执行
				$timess = $timess+7*SEC_EVERY_DAY;   //当前时间超过本周应该运行时间，放到下[DAY]周的指定时间执行
				break;
			case PLAN_MONTH:
				//next month没判断是否超过下月日期数，可能直接就计算到了下下月。所以先获得本月1号再直接获取下月1号对应时间
				//$taskInfo['EXCTIME'] = strtotime ("next month",$timess); 
				$tmp = getdate( $timess );
				$timess = mktime($tmp['hours'],$tmp['minutes'],0,$tmp['mon'],1,$tmp['year']);
				$timess = strtotime ("next month",$timess); //先获得下月1号指定时间的年月和时分 
				$t   = idate('t',$timess);//获得下月最大的天数
				$mday = ($interval<$t) ? $interval:$t; //根据指定的日期和最大日期，得到一个最终日期
				$timess = $timess + ($mday-1)*SEC_EVERY_DAY;
				break;
			case PLAN_YEAR:
				$timess = strtotime ("next year",$timess); //先获得下月1号指定时间的年月和时分 
				$z = idate('z',$timess);//得到指定时间是年份中的第几天
				$L = idate('L',$timess);//如果是闰年则返回 1，否则返回 0
				$d = $this->getDayOffset($taskInfo['DAY'],$L);
				$timess = $timess + ($d-$z-1)*SEC_EVERY_DAY;
				break;
			case PLAN_TIME:
				$timess = $timess + $interval*60;
				break;
			default:
				$timess = $timess + 3600;//错误，简单处理避免死循环
				break;
		}
		return $timess;
	}

	//设置每年指定日期运行时，判断该日期在年份中第几天。L为1表示闰年
	//输入的指定日期是其在闰年年份中的天数，如果非闰年，需要处理
	private function getDayOffset($d,$L)
	{
		$M29 = 31+29;//2月29
		if( $d < $M29 )
		{
			return $d;
		}
		if ($L)
		{
			return $d;
		}
		return $d - 1;
	}
	
	//对YYYY-MM-DD HH:MM或者HH:MM三种形式进行解析
	private function parseRuntime(&$runtime)
	{
		$runtime = trim($runtime);
		if ( NULL == $runtime )
		{
			return NULL;
		}
		$m = NULL;
		$matchResult = 0;
		
		//RUNTIME只能有三种形式，yyyy-mm-dd hh:mm 或 yyyy-mm-dd hh:mm:ss 或 hh:mm
		if( false === strpos( $runtime,'-'))
		{
			$matchResult = preg_match("/^(\d{1,2}):(\d{1,2})/i",$runtime,$m);
		}
		else
		{
			//$matchResult = preg_match("/^(\d{1,4})-(\d{1,2})-(\d{1,2})(\s+)(\d{1,2}):(\d{1,2})[:]([\d{1,2}]*)/i",$runtime,$m);
			$matchResult = preg_match("/^(\d{1,4})-(\d{1,2})-(\d{1,2})(\s+)(\d{1,2}):(\d{1,2})[:]*([\d{1,2}]*)/i",$runtime,$m);
		}
		
		if ( 0 == $matchResult )
		{
			return NULL;
		}
		if( NULL == $m[7] )
		{
			unset($m[7]);
		}

		foreach($m as &$value)
		{
			if ( is_numeric($value)  ) $value = intVal($value);
		}
		return $m;
	}
}

class PlannedTaskSet
{
	var  $batch;
	function __construct()
	{
		$this->batch = new TableSql('batchproc','ID');
	}
	
	function delPlanSet($pid)
	{
		return $this->batch->delByID($pid);
	}
	
	function addPlanInfo($time)
	{
		return INVALID_ID;
	}
	
	function updatePlanTime($id,$time)
	{
		return true;
	}
	
	//获得定时配置信息
	function getPlanTime($pid)
	{
		$info = $this->batch->queryByID($pid);
		if ( NULL == $info )
		{
			return NULL;
		}
		$time = array();
		$time['cyc'] = $info['TYPE'];
		$time['ahead'] = $info['AHEAD'];
		$time['hday'] = $info['DAYOFF'];
		switch ( $time['cyc'] )
		{
			case PLAN_ONCE:
				list($time['other'], $time['time'] ) = explode(' ', $info['RUNTIME']);
				break;
			case PLAN_DAY:
			case PLAN_WEEK:
			case PLAN_MONTH:
				$time['other'] = $info['DAY'];
				$time['time']  = $info['RUNTIME'];
				break;
			case PLAN_YEAR:
				$ds = array(0,31,60,91,121,152,182,213,244,274,305,335,366);
				foreach($ds as $m=>$d)
				{
					if ( $info['DAY'] > $d )
					{
						continue;
					}
					//转换为mm-dd形式
					$time['other'] = $m.'-'.($info['DAY']-$ds[$m-1]);
					break;
				}
				$time['time'] = $info['RUNTIME'];
				break;
			case PLAN_TIME:
				break;
			default:
				break;
		}
		return $time;
	}
	
	//检察时间设置输入
	static function checkPlanTime($time)
	{
		include_once('a/commonLang.php');
		if ( isset($time['ahead']) && !is_numeric($time['ahead']) )
		{
			return PLANSET_ERR_AHEAD;
		}
		if ( isset($time['hday']) && !is_numeric($time['hday']) )//还应该检查是否在合理范围
		{
			return PLANSET_ERR_HDAY;
		}
		if ( isset($time['end']) ) //要嘛为空，要嘛需要yyyy-mm-dd
		{
			//return PLANSET_ERR_END;
		}
		//time必须是空或者HH:MM格式
		if ( NULL != $time['time'] && !preg_match("/^(\d{1,2}):(\d{1,2})/i",$time['time'],$m))
		{
			return PLANSET_ERR_TIME;
		}
			
		switch ( $time['cyc'] )
		{
			case PLAN_ONCE:
				//other必须为yyyy-mm-dd格式
				break;
			case PLAN_DAY:
				//必须大于数字
				break;
			case PLAN_WEEK:
				//必须在1-7之间
				break;
			case PLAN_MONTH:
				//必须在1-31之间
				break;
			case PLAN_YEAR:
				//必须为mm-dd格式
				break;
			case PLAN_TIME:
				//必须是数字
				break;
			default:
				return PLANSET_ERR_CYC;
				break;
		}
		return true;
	}
}
?>
