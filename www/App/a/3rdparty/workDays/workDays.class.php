<?php
class workDaysCls
{
	private static  $cSql = NULL;

	static function getDB()
	{
		if ( NULL == self::$cSql)
		{
			self::$cSql = new TableSql('sysworkday'); 
		}
		return self::$cSql;
	}
	
	//后续要改为把所有日期直接存数据库比较方便

   	/*********************************************** 
   	 * function:变更设置某日的工作日状态
   	 * input :$day:要设置的日期，整数
   	 * output:原来是工作日，变为休息日；否则相反			  
   	 * return:最新状态，true:工作日;false:休息日
   	 * other :
   	 ***********************************************/
	static function toggleWorkDay($day)
	{
		$isWork = self::isWorkDay($day);

		self::setWorkDay($day, !$isWork);

		return !$isWork;
	}

	
   	/*********************************************** 
   	 * function:设置某日为工作日或假日
   	 * input :$day:要设置的日期，整数
   	          $isWork:true设为工作日 false设为假期
   	 * output:			  
   	 * return:
   	 * other :
   	 ***********************************************/
	static function setWorkDay($day,$isWork=true)
	{
		self::getDB()->del('WORKDAY=?',array($day));
		
		//如果是默认假期，且现在为假期，则删除后无需再设置
		if ( !$isWork == self::isDefaultHoliday($day))
		{
			return true;
		}

		$info = array();
		$info['WORKDAY'] = $day;
		$info['ISWORK']  = $isWork ? 1:0;

		self::getDB()->add($info);

		return true;
	}

   	/*********************************************** 
   	 * function:判断指定日期是否为工作日
   	 * input :$day:要判断的日期
   	 * output:			  
   	 * return:true:工作日
   	 		  false:休息日
   	 * other :
   	 ***********************************************/
	static function isWorkDay($day)
	{
		$s = self::getDB()->query('ISWORK','WORKDAY=?',array($day));
		if ( NULL != $s )
		{
			return $s['ISWORK'] ? true:false;
		}
		//如果没有例外处理，则判断是否默认假期
		$s = self::isDefaultHoliday($day);
		return !$s;
	}

   	/*********************************************** 
   	 * function:获取指定日期内的工作日数量
   	 * input :$sd:开始日期，包含该日期
   	 		  $ed:结束日期，包含该日期
   	 * output:			  
   	 * return:实际工作日数量
   	 * other :
   	 ***********************************************/
	static function getWorkDays($sDay,$eDay)
	{
		$ws = self::getWorkDayList($sDay,$eDay);
		return count($ws);
	}

   	/*********************************************** 
   	 * function:获取下一个工作日
   	 * input :$day:指定的日期，不包含该日期
   	 * output:			  
   	 * return:下一个工作日的日期
   	 * other :
   	 ***********************************************/
	static function getNextWorkDay($day)
	{
		return self::getNextWHDay($day,true);
	}
   	/*********************************************** 
   	 * function:获取下一个工作日
   	 * input :$day:指定的日期，不包含该日期
   	 * output:			  
   	 * return:下一个工作日的日期
   	 * other :
   	 ***********************************************/
	static function getNextHoliday($day)
	{
		return self::getNextWHDay($day,false);
	}

   	/*********************************************** 
   	 * function:获取下一个指定类型(工作日/节假日)的日期
   	 * input :$day:指定的日期，不包含该日期
   	 * output:			  
   	 * return:下一个日期
   	 * other :
   	 ***********************************************/
	static function getNextWHDay($day,$isWork=true)
	{
		if ( INFINITE_TIME == $day )
		{
			return -1;
		}
		for( $i = $day+1; true; $i++ ) //
		{
			if ( $isWork == self::isWorkDay($i) )
			{
				return $i;
			}
		}
		
		return -1;
	}	
   	/*********************************************** 
   	 * function:根据计划开始日期和工作日数，计算计划结束日期
   	 * input :$day:计划开始时间，包含该日期
   	 		  $workDays:计划的工期
   	 * output:			  
   	 * return:计划结束时间
   	 * other :
   	 ***********************************************/
	static function getEndWorkDay($sDay,$days=0)
	{
		$i = 0;
		$j = 0;
		for( $i = 0; true; $i++ ) //
		{
			if ( INFINITE_TIME == ($sDay+$i) )
			{
				return INFINITE_TIME;
			}
			if ( self::isWorkDay($sDay+$i) )
			{
				$j++;
			}
			if ( $j >= $days )
			{
				break;
			}
		}
		return $sDay+$i;
	}

   	/*********************************************** 
   	 * function:获得指定日期段内的所有工作日
   	 * input :$sDay:开始时间，包含该日期
   	 		  $eDay:结束时间，包含该日期
   	 * output:			  
   	 * return:数组，包含sDay和eDay间的所有工作日
   	 * other :
   	 ***********************************************/
	static function getWorkDayList($sDay,$eDay)
	{
		$hs = self::getDB()->queryAllList('WORKDAY','ISWORK=0 AND WORKDAY>=? AND WORKDAY<=?', array($sDay,$eDay));
		$ws = self::getDB()->queryAllList('WORKDAY','ISWORK!=0 AND WORKDAY>=? AND WORKDAY<=?', array($sDay,$eDay));//例外工作日
		$d = array();
		for($i = $sDay; $i <= $eDay; $i++)
		{
			if ( !self::isDefaultHoliday($i) )
			{
				$d[] = $i;
			} 			
		}
		$d =  array_diff($d, $hs); 
		$d =  array_merge($d, $ws); 
		$d = array_unique($d);		

		return $d;


	}

   	/*********************************************** 
   	 * function:获得指定日期段内的所有休息日
   	 * input :$sDay:开始时间，包含该日期
   	 		  $eDay:结束时间，包含该日期
   	 * output:			  
   	 * return:数组，包含sDay和eDay间的所有工作日
   	 * other :
   	 ***********************************************/
	static function getHolidayList($sDay,$eDay)
	{
		$hs = self::getDB()->queryAllList('WORKDAY','ISWORK=0 AND WORKDAY>=? AND WORKDAY<=?', array($sDay,$eDay));
		$ws = self::getDB()->queryAllList('WORKDAY','ISWORK!=0 AND WORKDAY>=? AND WORKDAY<=?', array($sDay,$eDay));//例外工作日
		$d = array();
		for($i = $sDay; $i <= $eDay; $i++)
		{
			if ( self::isDefaultHoliday($i) )
			{
				$d[] = $i;
			} 			
		}
		$d =  array_diff($d, $ws); 
		$d =  array_merge($d, $hs); 
		$d = array_unique($d);		

		return $d;
	}
	


   	/*********************************************** 
   	 * function:判断指定日期是否为默认假期(周六周日)
   	 * input :$day:要判断的日期
   	 * output:			  
   	 * return:true:默认假期
   	 		  false:不是默认假期
   	 * other :
   	 ***********************************************/
	static function isDefaultHoliday($day)
	{
		// 默认假期为周六周日
		// 0是1970-01-01。15423和15424是某个周六周日
		$w = $day%7;
		if ( 2 == $w || 3 == $w )
		{
			return true;
		} 
		return false;
	}
}   


?>
