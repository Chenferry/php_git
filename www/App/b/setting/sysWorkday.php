<?php
include_once('../../a/config/dstpCommonInclude.php');
include_once('workDays/workDays.class.php');

function setWorkDays($day)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$d = UTIL::getSpecTimeByStr($day);
	$cWorkDay  = new workDaysCls;
	$iswork = $cWorkDay->toggleWorkDay($d);

	return array('id'=>$d, 'day'=>$day, 'iswork'=>$iswork);
}

function getHoliDays($sd,$ed)
{
	$sd = UTIL::getSpecTimeByStr($sd);
	$ed = UTIL::getSpecTimeByStr($ed);

	$cWorkDay  = new workDaysCls;
	$dl = $cWorkDay->getHolidayList($sd,$ed);

	$es = array();
	$info = array();
	foreach($dl as $d)
	{
		$info[$d] = UTIL::getSpecDate($d);
	}
	return $info;
}

util::startSajax( array('setWorkDays','getHoliDays'));

	
?>
