<?php

//该文件实现智能管理接口
class smartInterFace
{
	//UI设置相关
	static function getCondAttrList($roomList)
	{
		$c = new TableSql('homeattr','ID'); 
		$c->join('homedev','homeattr.DEVID=homedev.ID');
		
		$condListInfo = $c->queryAll('homeattr.ID as ID,DEVID,homeattr.NAME as NAME,ATTRINDEX,ICON,ROOMID,SYSNAME,PHYDEV,INUSE','ISR=1 ORDER BY SYSNAME,ROOMID');
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$havxjd = false;
		$condList = array();
		foreach( $condListInfo as &$attr )
		{
			if(-1 == $attr['INUSE'])
			{
				continue;
			}
			if( !isset( $roomList[ $attr['ROOMID'] ] ) )
			{
				$attr['ROOMID'] =  ROOM_SYSDEV_UNADDR;
			}
			if( PHYDEV_TYPE_SYS == $attr['PHYDEV'] )
			{
				$attr['ROOMID'] = ROOM_SYSDEV_SYS;
			}
			
			if( !isset($condList[$attr['ROOMID']]) )
			{
				$condList[$attr['ROOMID']] = array();
			}
			
			$GLOBALS['dstpSoap']->setAttrType($attr['SYSNAME']);
			$attr['page']  = $GLOBALS['dstpSoap']->getPage();
			//小家电的各个属性的条件设置需要合并显示
			if( 'xjd' == $attr['SYSNAME'] )
			{
				$havxjd = true;
				$condList[$attr['ROOMID']][] = array( 'NAME'=>$attr['NAME'],'ICON'=>$attr['ICON'],'DEVID'=>$attr['DEVID'],'subs'=>array($attr) );
			}
			else
			{
				$condList[$attr['ROOMID']][] = $attr;
			}
		}	
		
		if( $havxjd )
		{
			foreach( $condListInfo as &$attr )
			{
				if(-1 != $attr['INUSE'])
				{
					continue;
				}
				//遍历查找$condList，把attr加到其设备指定的subs中
				for( $i = count($condList[$attr['ROOMID']])-1; $i>=0; $i-- )
				{
					if( $condList[$attr['ROOMID']][$i]['DEVID'] != $attr['DEVID'] )
					{
						continue;
					}
					
					$condList[$attr['ROOMID']][$i]['subs'][] = $attr;
					break;
				}				
			}
		}
		
		return $condList;
	}
	
	/****************智能模式执行相关************************/
	//attrList中的属性有变化，检测是否有智能模式需要被触发
	static function checkAttrTriger($attrList)
	{
		$procList = array();
		$c  = new TableSql('smartdev');
		foreach( $attrList as $attr )
		{
			$sList = $c->queryAllList('SID','ATTRID=?',array($attr));
			foreach( $sList as $id )
			{
				//已经处理过的就不需再处理
				if ( in_array($id, $procList) )
				{
					continue;
				}
				$procList[] = $id;

				//检查是否满足触发条件
				$r = self::checkSmartStatus($id);
				if ( NULL === $r )//true,false,NULL三种返回值.NULL表示不判断处理
				{
					continue;
				}
				
				$inexec = 1;
				if ( !$r )
				{
					$inexec = 0;
				}
				self::execSmart($id,$inexec,$attr);
			}
		}
		return true;
	}
	
	private static function checkTimeCyc($id)
	{
		$c = new TableSql('smartsmart','ID'); 
		$plan = $c->queryValue('PLANCFG','ID=?',array($id));
		if ( !$plan )
		{
			return false;
		}
		$plan = unserialize($plan);
		if ( !$plan )
		{
			return false;
		}

		//判断计划时间，如果全天候的，则无需设置
		$plan = self::converSmartPlan($plan);
		if( NULL == $plan )
		{
			return true;	
		}

		return self::isInTimeCyc($plan);
	}

	static function isInTimeCyc($plan)
	{
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask();
		$sfirsttime = $planTask->getFirstExecTime($plan);
		
		//考虑节假日等因素可能跨天，结束时间用用开始时间为基准延后处理，所以ahead要设置负数
		$timediff = self::calcTimeDiff($plan);
		$plan['ahead'] = 0-$timediff;
		$planTask = new PlannedTask();
		$efirsttime = $planTask->getFirstExecTime($plan);
		
		//判断当前时间是否在计划周期内。如果在周期内，前面的计划时间设置将不包括，这儿要手动处理下
		//如果在周期内，就需要补上进入周期的处理
		if( ($sfirsttime > $efirsttime) 
			|| ( (PLAN_ONCE == $plan['cyc']) 
				&& ( ($sfirsttime<time()) 
					&& ( ( $efirsttime+$timediff ) > time() ))  )
			)
		//if( $sfirsttime > $efirsttime )
		{
			return true;
		}
		return false;		
	}
	
	static function trigerTimePlan($id)
	{
		$c = new TableSql('smartsmart','ID'); 
		$value = $c->query('GROUPID,INUSE','ID=?',array($id));
		if( !validID( $value['GROUPID'] ) )
		{
			return;
		}
		if($value['INUSE'] == '1')
		{
			$GLOBALS['dstpSoap']->setModule('smart','group');
			$GLOBALS['dstpSoap']->execGroup($value['GROUPID']);			
		}
	}

	//时间触发
	private static function trigerTimeCyc($id,$incyc=true)
	{
		//需要先检测下时间条件是否还满足
		if( $GLOBALS['planIgnore'] )
		{
			$cyc = self::checkTimeCyc($id);
			if( $cyc!=$incyc )
			{
				return;
			}
		}
		$c = new TableSql('smarttriger'); 
		$info = array();
		$info['INCYC'] = $incyc?1:0;
		$c->update($info,NULL,'SID=?',array($id));
		
		//进入计划周期就检查下是否可执行。时间是一个触发条件
		$exec = true;
		if( $incyc ) //如果是在时间周期内的，则还需要检测判断是否符合其他条件才执行
		{
			if ( !self::checkSmartStatus($id) )
			{
				$exec = false;
			}
		}
		
		if($exec)
		{
			self::execSmart($id,$incyc?1:0);	
		}
		return;
	}

	//智能模式进入开始执行时间的处理
	static function smartStartPlan($id)
	{
		return self::trigerTimeCyc($id);
	}

	//智能模式到达执行周期末尾的处理
	static function smartEndPlan($id)
	{
		return self::trigerTimeCyc($id,false);
	}	

	//检查智能模式当前是否符合执行要求
	static function checkSmartStatus($id)
	{
		//首先检查是否在执行周期中
		$c = new TableSql('smarttriger'); 
		$info = $c->query('*','SID=?',array($id));
		if ( NULL != $info && !$info['INCYC']) //如果有设置了执行周期，则要检查
		{
			return false;
		}
		
		//取出条件逐个检查
		$c = new TableSql('smartsmart','ID'); 
		$info = $c->query('COND,INUSE,SAVEFROM','ID=?',array($id));
		if ( NULL == $info )
		{
			return NULL;
		}
		//定时的只管时间，无需处理
		if( SMART_FROM_PLAN == $info['SAVEFROM'] || SMART_FROM_ATTRPLAN == $info['SAVEFROM'] )
		{
			return NULL;
		}
		if( !$info['INUSE'] )
		{
			return NULL;
		}
		
		$cond = unserialize($info['COND']);
		return self:: checkAttrStatus($cond);
	}

	//触发执行智能模式。记录执行历史；根据延时设置	
	//id:智能模式id
	//inexec:true:满足条件；false：从满足条件变为失去条件
	//attrList:触发本次执行的设备属性信息
	static function execSmart($id,$inexec=1,$attr=NULL)
	{
		$c = new TableSql('smartsmart','ID'); 
		$info = $c->query('ID,NAME,INUSE,DELAYS,DELAYS2,GROUPID,GROUPID2,INEXEC,ALARM,ALARM2','ID=?',array($id));
		if( NULL == $info )
		{
			return;
		}
		if( !$info['INUSE'] )
		{
			return;
		}
		//如果当前执行状态和新状态一致，则无需再执行
		if (  intval($info['INEXEC']) == intval( $inexec )	)
		{
			return;
		}
		
		$groupid = $info['GROUPID'];
		$delay   = $info['DELAYS'];
		if ( !$inexec )
		{
			$groupid = $info['GROUPID2'];
			$delay   = $info['DELAYS2'];
		}
		
		//执行情景模式.如果需要延时，则设置一个计划任务
		if ( $delay && !DSTP_DEBUG )
		{
			//$planTask = new PlannedTask('smart','group',$delay);
			//$planTask->execGroup($groupid);
			//系统维护任务的唤醒周期较长，以分钟为单位。而延时经常是以秒为单位
			//这儿应另起进程，休眠指定时间后就触发。否则就必须修改系统维护任务的执行间隔
			//$pid = pcntl_fork();
			//if ( 0 == $pid )
			//{
			//	sleep($delay);
			//	
			//	//再次检查条件是否符合，避免频繁抖动
			//	$r = self::checkSmartStatus($id);
			//	if ( intval($r) == intval($inexec) )
			//	{
			//		self::execSmartGroup($info,$groupid,$inexec,$attr);	
			//	}
			//	exit();
			//}
			
			//修改了计划任务，在延时较短时，启动定时器到时直接执行而不仅仅靠crond来触发
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('smart','smart', $delay);
			$planTask->delayExecSmart($id,$info,$groupid,$inexec,$attr);	
		}
		else
		{
			self::execSmartGroup($info,$groupid,$inexec,$attr);	
		}
		return true;
	}
	static function delayExecSmart($id,$info,$groupid,$inexec,$attr)
	{
		//再次检查条件是否符合，避免频繁抖动
		$r = self::checkSmartStatus($id);
		if ( intval($r) == intval($inexec) )
		{
			self::execSmartGroup($info,$groupid,$inexec,$attr);	
		}
		return;
	}
	
	//实际执行智能模式所要触发的动作	
	private static function execSmartGroup(&$sinfo,$groupid,$inexec,$attr)
	{
		$c = new TableSql('smartsmart','ID'); 
		$info = array();
		$info['ID']     = $sinfo['ID'];
		$info['INEXEC'] = intval( $inexec );
		$c->update($info);
		statusNotice('smart');

		//增添执行记录
		//$c = new TableSql('smartexecrecord'); 
		//$info = array();
		//$info['SID']    = $sinfo['ID'];
		//$info['ETIMES'] = time();
		//$info['EXECR']  = intval($inexec);
		//$info['ATTR']   = -1;//默认时间触发
		//if ( NULL != $attr )
		//{
		//	$info['ATTR']   = $attr;
		//}
		//$c->add($info);
		//$info = NULL;

		if( validID( $groupid ) )
		{
			$GLOBALS['dstpSoap']->setModule('smart','group');
			$GLOBALS['dstpSoap']->execGroup($groupid);
		}

		//告警
		if( ($sinfo['ALARM'] && $inexec) 
			|| ($sinfo['ALARM2'] && !$inexec))
		{
			include_once('b/homeLang.php');
			if( $inexec )
			{
				$alarmInfo = sprintf(HOME_SMART_EXEC,$sinfo['NAME']);
			}
			else
			{
				$alarmInfo = sprintf(HOME_SMART_NOEXEC,$sinfo['NAME']);
			}
			$info = array();
			$c = new TableSql('hic_hic','ID');
			$name = $c->queryValue('NAME');
			$info['TITLE']       = date('m-d H:i').'-'.trim($name).HOME_DEV_ALARM_TITLE;
			$info['DESCRIPTION'] = $alarmInfo;

			// $GLOBALS['dstpSoap']->setModule('delay','push');
			// $GLOBALS['dstpSoap']->sendNotice($info);
			// include_once('plannedTask/PlannedTask.php');
			// $planTask = new PlannedTask('delay','push');
			// $planTask->sendNotice($info);
		}
		return;
	}

	//检查设定的设备条件是否都已经满足
	static function checkAttrStatus($condList)
	{
		if ( !is_array($condList) || !isset($condList['filter']) || !isset($condList['cond']) )
		{
			return false;
		}
		$c = new TableSql('homeattr','ID'); 
		foreach( $condList['cond'] as &$cond )
		{
			if ( is_array($cond)  )
			{
				$r = self::checkAttrStatus($cond);
			}
			else
			{
				$r = $c->getRecordNum($cond);
			}
			if ( !$r )
			{
				if ( $condList['filter'] )
				{
					continue;
				}
				return false;
			}
			else
			{
				if ( $condList['filter'] )
				{
					return true;
				}
				continue;
			}
		}
		return $condList['filter']?false:true;
	}

	/******************智能模式设置修改相关*****************/
	static function saveSmart($id,$name,$attrList,$plan,$op1,$op2,$from)
	{
		$cond = array();
		//定时模式无需处理条件
		if( (SMART_FROM_PLAN!=$from) && (SMART_FROM_ATTRPLAN!=$from) )
		{
			if ( !self::genSmartCond($attrList,$cond) )
			{
				return false;
			}
		}

		$c = new TableSql('smartsmart','ID'); 
		$info = array();
		$info['NAME']    = $name;
		$info['COND']    = serialize($cond);
		$info['QCOND']   = serialize($attrList);
		$info['PLANCFG'] = serialize($plan);
		$info['DELAYS']  = intval($op1['delay']);
		$info['DELAYS2'] = intval($op2['delay']);
		$info['ALARM']   = intval($op1['alarm']);
		$info['ALARM2']  = intval($op2['alarm']);
		//$info['GROUPID'] = INVALID_ID; //后续会专门处理这两个
		//$info['GROUPID2']= INVALID_ID;//后续会专门处理这两个
		$info['INEXEC']  = 0;
		if ( validID($id) )
		{
			$info['ID'] = intval($id);
			$c->update($info);
		}
		else
		{
			$info['SAVEFROM']  = $from;
			$id = $c->add($info);
		}
		if ( !validID($id) )
		{
			return false;
		}

		//添加触发的情景模式。要删除原先设置的
		self::setSmartGroup($id,$op1['group'],$op2['group']);

		//查找相关的设备属性，辅助触发查询
		self::setSmartDev($id,$attrList);

		//设置触发时间
		self::setSmartPlan($id,$plan);
		
		if ( self::checkSmartStatus($id) )
		{
			self::execSmart($id);	
		}
		statusNotice('smart');
		return $id;
	}
	
	static function delSmart($id)
	{
		self::delSmartPlan($id);
		self::delSmartGroupSet($id);
		$c = new TableSql('smartexecrecord'); 
		$c->del('SID=?',array($id));
		$c = new TableSql('smartdev'); 
		$c->del('SID=?',array($id));
		$c = new TableSql('smartsmart','ID'); 
		$c->del('ID=?',array($id));
		statusNotice('smart');
		return true;
	}	
	/****************时间计划相关********************************/
	//根据传来的参数进行周期设置
	private static function setSmartPlan($id,&$plan)
	{
		self::delSmartPlan($id);
		
		//判断计划时间，如果全天候的，则无需设置
		$plan = self::converSmartPlan($plan);
		if( NULL == $plan )
		{
			return true;	
		}
		
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask('smart','smart',$plan);

		//定时模式不考虑结束时间周期，所以可以简单处理
		if('plan' == $plan['etime'] ) 
		{
			$pid = $planTask->trigerTimePlan($id);
			$info = array();
			$info['INCYC']   = 1;
			$info['SID']     = $id;
			$info['PLANID']  = $pid;
			$info['PLAN2ID'] = INVALID_ID;			
			$c = new TableSql('smarttriger'); 
			$c->add($info);
			return true;
		}
		
		$pid = $planTask->smartStartPlan($id);
		$sfirsttime = $planTask->getFirstExecTime($plan);

		//考虑节假日等因素可能跨天，结束时间用用开始时间为基准延后处理，所以ahead要设置负数
		$timediff = self::calcTimeDiff($plan);
		$plan['ahead'] = 0-$timediff;
		$planTask = new PlannedTask('smart','smart',$plan);
		$pid2 = $planTask->smartEndPlan($id);	
		$efirsttime = $planTask->getFirstExecTime($plan);
		
		$info = array();
		$info['INCYC']   = 0;
		$info['SID']     = $id;
		$info['PLANID']  = $pid;
		$info['PLAN2ID'] = $pid2;			
		$c = new TableSql('smarttriger'); 
		$c->add($info);
		
		//判断当前时间是否在计划周期内。如果在周期内，前面的计划时间设置将不包括，这儿要手动处理下
		//如果在周期内，就需要补上进入周期的处理
		if( ($sfirsttime > $efirsttime) 
			|| ( (PLAN_ONCE == $plan['cyc']) 
				&& ( ($sfirsttime<time()) 
					&& ( ( $efirsttime+$timediff ) > time() ))  )
			)
		{
			self::smartStartPlan($id);
		}
		return true;
	}

	//修改设备相关的智能模式
	static function changeSmartAttrName($attrid,$oldname,$name)
	{
		$c 	   = new TableSql('smartgroupattr','ID');
		$attr  = $c->queryAll('ID,NAME','ATTRID=?',array($attrid));
		foreach ($attr as $value) 
		{
			if( $value['NAME'] != null )
			{
				$info         = array();
				$info['ID']   = $value['ID'];
				$str 	 	  = explode("-",$value['NAME']);
				if( sizeof($str) < 2 )
					continue;
				if( $str[0] != $oldname )
					continue;
				$str[0] = $name;
				$info['NAME'] = implode('-',$str);
				$c->update($info);
			}
		}
		
		$c 	 = new TableSql('smartdev','ID');
		$sid = $c->queryAll('SID','ATTRID=?',array($attrid));
		foreach ($sid as $value) 
		{
			$c 	          = new TableSql('smartsmart','ID');
			$smart        = $c->queryValue('NAME','ID=?',array($value['SID']));
			$info 		  = array();
			$str1 	 = explode("&",$smart);
			$str2 	 = explode('-',end($str1));
			if( sizeof($str1) < 2  && sizeof($str2) < 2 )
				continue;
			if( $str2[1] != $oldname )
				continue;
			$str2[1] = $name;
			$str1[sizeof($str1)-1] = implode('-',$str2);
			$info['NAME'] =  implode('&',$str1);
			$info['ID']   = $value['SID'];
			$c->update($info);
			statusNotice('smart');	
		}		
	}

	//修改属性的房间或者删除房间，对应的定时模式和联动模式中的房间的名字也要做出对应的改变
	static function getSmartName($devid,$smartname,$roomid)
	{
		$c1 = new TableSql('homeattr','ID');
		$c1->join('smartdev','smartdev.ATTRID=homeattr.ID');
		$sid = $c1->queryAll('SID','DEVID=?',array($devid));
		if( sizeof($sid) )
		{
			foreach( $sid as $key => $value ) 
			{
				$c = new TableSql('smartsmart','ID');
				$smartname = $c->queryValue('NAME','ID=?',array($value['SID']));		
				$info         = array();
				$info['ID']   = $value['SID'];
				include_once('b/homeLang.php');
				$str1 	 = explode("&",$smartname);
				$str2 	 = explode('-',end($str1));
				if( sizeof($str1) < 2  && sizeof($str2) < 2 )
					continue;
				$c1 		 = new TableSql('homeroom','ID'); 
				$newname = $c1->queryValue('NAME','ID=?',array($roomid));
				if( $newname == NULL )
					$newname = HOME_SYSDEV_UNADDR;
				$str2[0] = $newname;
				$str1[sizeof($str1)-1] = implode('-',$str2);
				$info['NAME'] =  implode('&',$str1);
				$c->update($info);
			}
		}

	}

	//把前台的计划时间格式转为后台适用的格式
	static function converSmartPlan($plan)
	{
		if( NULL == $plan )
		{
			return NULL;	
		}

		//默认：Array ( [cyc] => [other] => [time] => 0:00 [etime] => 0:00 ) 
		//周期默认：Array ( [cyc] => [other] => [time] => 0:00 [etime] => 1:00 ) 
		//工作日：Array ( [cyc] => 1 [hday] => 2 [time] => 0:00 [endtime] => 0:00 ) 
		//节假日：Array ( [cyc] => 1 [hday] => 3 [time] => 0:00 [endtime] => 1:00 ) 
		//星期一：Array ( [cyc] => 2 [other] => 1 [time] => 0:00 [endtime] => 1:00 ) 
		//每年每月1日：Array ( [cyc] => 3 [other] => 1 [time] => 0:00 [etime] => 1:00 ) 
		//每年1月1日：Array ( [cyc] => 5 [other] => 1-1 [time] => 0:00 [etime] => 1:00 ) 
		//2015年1月1日：Array ( [cyc] => 0 [other] => 2015-1-1 [time] => 0:00 [etime] => 1:00 ) 
		//每年1月每日：Array ( [cyc] => [other] => [time] => 0:00 [etime] => 1:00 ) 
		
		//开始时间等于结束时间，等于没设置
		if( $plan['time'] == $plan['etime'] )
		{
			return NULL;
		}
			
		
		//( [cyc] => 1 [other] => [time] => [etime] => ) 
		//如果设置的是全部时间周期的，则也等于没设置，直接返回
		if(    ( !isset($plan['cyc'])   || (PLAN_DAY == $plan['cyc']) ) 
			&& ( !isset($plan['other']) || (1 == $plan['other']  ))
			&& ( !isset($plan['time'])  || ('00:00' == $plan['time']  ))
			&& ( !isset($plan['etime']) || ('24:00' == $plan['etime']  ))
			)
		{
			return NULL;
		}
		
		if( !array_key_exists('cyc',$plan))
		{
			$plan['cyc'] = PLAN_DAY; //默认周期是每日。如果是选定时间，则传来的是确定为0
		}

		//如果没设置etime，则表示到当天开始。
		if( !isset($plan['time']) ) $plan['time'] = '00:00';
		$plan['time'] = trim($plan['time']);
		if( !preg_match("/^(\d{1,2}):(\d{1,2})/i",$plan['time'],$m))
		{
			$plan['time'] = '00:00';
		}
		
		//自定义的统一使用plan_once，要根据定义的格式详细信息
		self::converUserDefineTime($plan);
	
		
		return $plan;
	}
	
	private static function converUserDefineTime(&$plan)
	{
		if( PLAN_ONCE != $plan['cyc'] )
		{
			return;
		}
		list($y,$m,$d) = explode('/',$plan['other']);
		
		//三个都是-1
		if( (-1==$y) && (-1==$m) && (-1==$d) )     //等价每日
		{
			$plan['cyc']   = PLAN_DAY;
			$plan['other'] = 1;
		}
		//两个-1
		else if( (-1!=$y) && (-1==$m) && (-1==$d) ) //每日，周期开始结束时间在某年里 
		{
			$plan['cyc']   = PLAN_DAY;
			$plan['other'] = 1;
			$plan['start'] = "$y-01-01";
			$plan['end']   = "$y-12-31";
		}
		else if( (-1==$y) && (-1!=$m) && (-1==$d) ) 
		{
		}
		else if( (-1==$y) && (-1==$m) && (-1!=$d) ) //每月
		{
			$plan['cyc']   = PLAN_MONTH;
			$plan['other'] = $d;
		}
		//一个-1
		else if( (-1==$y) && (-1!=$m) && (-1!=$d) ) //每年
		{
			$plan['cyc']   = PLAN_YEAR;
			$plan['other'] = "$m-$d";
		}
		else if( (-1!=$y) && (-1==$m) && (-1!=$d) ) //每月，周期开始结束时间在某年里
		{
			$plan['cyc']   = PLAN_MONTH;
			$plan['other'] = $d;
			$plan['start'] = "$y-01-01";
			$plan['end']   = "$y-12-31";
		}
		else if( (-1!=$y) && (-1!=$m) && (-1==$d) ) //每日，周期开始结束时间在某月里
		{
			$plan['cyc']   = PLAN_DAY;
			$plan['other'] = 1;
			$plan['start'] = "$y-$m-01";
			$ed = date('t',strtotime("$y-$m-10"));
			$plan['end']   = "$y-$m-$ed";
		}
		//没有-1
		else //完全指定的具体时间
		{
			$plan['cyc']   = PLAN_ONCE;
			$plan['other'] = "$y-$m-$d";
		}
	}
	
	//计算开始时间和结束时间的时间差
	private static function calcTimeDiff(&$plan)
	{
		if( !isset($plan['etime']) ) $plan['etime'] = '24:00';
		//如果没设置etime，则表示到当天结束
		$m = NULL;
		$plan['etime'] = trim($plan['etime']);
		if( !preg_match("/^(\d{1,2}):(\d{1,2})/i",$plan['etime'],$m))
		{
			$plan['etime'] = '24:00';
		}

		//如果etime小于time，则表示是第二天。
		//如果是跨天，考虑节假日等的计算，则结束通过设置一个计划周期会非常复杂
		//只设置开始周期的计划任务。当开始时，同时设置结束，以时间差计算
		$st = strtotime($plan['time'],0);
		$et = strtotime($plan['etime'],0);
		$timediff = $et-$st;
		if( $timediff < 0 ) //结束时间小于开始时间，则表示是第二天
		{
			$timediff = $timediff+86400;
		}
		return $timediff ;
	}
	
	//删除时间计划
	private static function delSmartPlan($id)
	{
		$c = new TableSql('smarttriger'); 
		$triger = $c->query('*','SID=?',array($id));
		if ( NULL != $triger )
		{
			//先查原来的planid，删除其相关计划任务
			if ( validID( $triger['PLANID'] ) )
			{
				include_once('plannedTask/PlannedTask.php');
				$cSet = new PlannedTaskSet;
				$cSet->delPlanSet( $triger['PLANID'] );
				$cSet->delPlanSet( $triger['PLAN2ID'] );
			}
			$c->del('SID=?',array($id));
		}
		return true;
	}
	

	
	/****************设备相关********************************/
	//设置相关的智能设备
	private static function setSmartDev($id,&$attrList)
	{
		//从attrList中获取所有的相关设备添加到触发检查表
		$idList = array();
		self::getSmartAttr($attrList,$idList);
		$idList = array_unique($idList);
		$c = new TableSql('smartdev'); 
		$c->del('SID=?',array($id));
		$info = array();
		$info['SID'] = $id;
		foreach( $idList as &$attrid )
		{
			$info['ATTRID'] = $attrid;
			$c->add($info);
		}
		return;
	}
	//遍历用户设置的条件，找出所有涉及的属性ID
	private static function getSmartAttr(&$attrList,&$idList)
	{
		if ( !isset( $attrList['sub'] ) )
		{
			return;
		}
		foreach( $attrList['sub'] as &$sub )
		{
			if ( isset($sub['filter']) )
			{
				self::getSmartAttr($sub,$idList);
				continue;
			}
			$idList[] = $sub['ID'];
		}
		return;
	}

	/****************涉及情景模式相关********************************/
	//删除智能模式对应的情景模式。如果是直接引用的，则无需删除，只要清除关联	
	private static function delSmartGroupSet($id,$del1=true,$del2=true)
	{
		//查询所使用的情景模式id，判断情景模式是直接使用还是引用
		//如果是直接引用，连该情景模式一并删除
		$c = new TableSql('smartsmart','ID');
		$info = $c->query('GROUPID,GROUPID2','ID=?',array($id));
		if ( NULL == $info )
		{
			return true;
		}
		$c = new TableSql('smartgroup','ID');
		if( validID($info['GROUPID']) && $del1 )
		{
			$isShow = $c->queryValue('ISSHOW','ID=?',array($info['GROUPID']));
			if( !$isShow )
			{
				$GLOBALS['dstpSoap']->setModule('smart','group');
				$GLOBALS['dstpSoap']->delGroup($info['GROUPID']);
			}
		}
		if( validID($info['GROUPID2']) && $del2)
		{
			$isShow = $c->queryValue('ISSHOW','ID=?',array($info['GROUPID2']));
			if( !$isShow )
			{
				$GLOBALS['dstpSoap']->setModule('smart','group');
				$GLOBALS['dstpSoap']->delGroup($info['GROUPID2']);
			}
		}
		return true;
	}
	//智能模式要触发的情景
	private static function setSmartGroup($id,&$groupList,&$groupList2)
	{
		$del1 = false;
		$del2 = false;
		if ( !is_numeric($groupList) )
		{
			$del1 = true;
			//先清除原来自定义设置的情景模式
			$GLOBALS['dstpSoap']->setModule('smart','group');
			$groupList = $GLOBALS['dstpSoap']->saveGroup(INVALID_ID,$name,$groupList,false);
		}
		if ( !is_numeric($groupList2) )
		{
			$del2 = true;
			$GLOBALS['dstpSoap']->setModule('smart','group');
			$groupList2 = $GLOBALS['dstpSoap']->saveGroup(INVALID_ID,$name,$groupList2,false);
		}
		
		if( $del1 || $del2 )
		{
			self::delSmartGroupSet($id,$del1,$del2);
		}

		//if ( !validID( $groupList ) )
		//{
		//	//
		//}
		$c = new TableSql('smartsmart','ID'); 
		$info = array();
		$info['ID'] = intval($id);
		$info['GROUPID']  = intval($groupList);
		$info['GROUPID2'] = intval($groupList2);
		$c->update($info);
		return;
	}

	
	/****************条件相关********************************/
	//根据ID检测设置的条件是否正确.
	private static function genSubCond(&$cond)
	{
		//至少要有一个比较值。智慧模式不支持IS NULL等操作
		if ( !isset($cond['VALUE1']) )
		{
			return false;
		}
		//根据ID，判断是否存在，并取出其sysname，存储信息的字段
		$id = $cond['ID'];
		$op = $cond['OP'];
		$v1 = $cond['VALUE1'];
		$v2 = $cond['VALUE2'];
		
		//枚举值因为多设置了一个''，有可能被选中，需要把该项剔除
		if ( isset( $v1[0] ) && is_array($v1))
		{
			$keyName = array_search('', $v1); 
			if ( false != $keyName )
			{
				unset($v1[$keyName]);
			}
		}
		if ( isset( $v2[0]) && is_array($v2))
		{
			$keyName = array_search('', $v2); 
			if ( false != $keyName )
			{
				unset($v2[$keyName]);
			}
		}

		$c = new TableSql('homeattr','ID'); 
		$attr = $c->query('ID,SYSNAME','ID=?',array($id));
		if ( NULL == $attr )
		{
			return false;
		}
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$dbField = $GLOBALS['dstpSoap']->getAttrStatusDBField($attr['SYSNAME']);
		$v1 = $GLOBALS['dstpSoap']->getDBInfo($v1,$id);
		if ( NULL == $v1 )
		{
			return false;
		}
		$v2 = $GLOBALS['dstpSoap']->getDBInfo($v2,$id);

		$query   = NULL;
		//创建比较条件。比较条件要加()
		switch ( $op )
		{
			case '='  :
			case '!=' :
			case '>'  :
			case '<'  :
			case '>=' :
			case '<=' :
				$query = "($dbField $op $v1)";
				break;
			case 'IN':
			case 'NOT IN':
				if(!is_array($v1)) $v1 = unserialize($v1);
				$v1 = implode(',',$v1);
				$query = "($dbField $op ($v1))";
				break;
			case 'LIKE':
			case 'NOT LIKE':
				//在转换时，字串的前后都已经被加上了\'，需要去掉
				$v1 = trim ($v1,"'");
				$query = "($dbField $op '%$v1%')";
				break;
			case 'BETWEEN':
				if ( NULL != $v2 )
				{
					$query = "($dbField $op $v1 AND $v2)";
				}
				break;
			default:
				break;
		}
		if ( NULL == $query )
		{
			return false;
		}
		return "((ID=$id) AND $query)";
	}
	
	//因为genSmartCond是引用参数，无法通过接口调用。所以封装一个壳
	static function genSmartCondArr($attrList)
	{
		$cond = NULL;
		$r = self::genSmartCond($attrList,$cond);
		if(!$r)
		{
			return false;
		}
		return $cond;
	}
	
	//检查用户输入的条件是否正确
	private static function genSmartCond(&$attrList,&$cond)
	{
		if( !isset( $attrList['sub'] ) )
		{
			return false;
		}
		$cond['filter'] =  ( 'AND' == $attrList['filter'])?0:1;
		$cond['cond'] = array();
		foreach( $attrList['sub'] as &$sub)
		{
			if ( isset($sub['filter']) )
			{
				$subCond = array();
				$r = self::genSmartCond($sub,$subCond);
				if ( !$r )
				{
					return false;
				}
				$cond['cond'][] = $subCond;
				continue;
			}
			$r = self::genSubCond($sub);
			if ( !$r )
			{
				return false;
			}
			$cond['cond'][] = $r;
		}
		return true;
	}
}
?>