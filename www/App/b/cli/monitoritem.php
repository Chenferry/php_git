<?php
require_once(dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php');
include_once(dirname(dirname(dirname(__FILE__))).'/a/config/dstpCfgCustom.php');

//监控itemcommon相关进程是否在执行。一个itemid对应一个进程

$dir = dirname(__FILE__);

$pidMaxMem = 350000;
$pidMaxCpu = 15;
$procList = array('hicserver.php','hicdelay.php','hicstatusitem.php');
foreach( $procList as &$proc )
{
	foreach($GLOBALS['itemList'] as $itemid)
	{
		$procname = "$dir/$proc $itemid";
		$pidList = `pgrep -f '$procname'`;
		$pidList = explode("\n",trim($pidList));

		$pidList2 = `pgrep -f '$procname'`;
		$pidList2 = explode("\n",trim($pidList2));
		
		$pidList =  array_intersect($pidList,$pidList2);
		
		if( NULL == $pidList )
		{
			`/usr/bin/php $procname >/dev/null 2>&1 &`;
			continue;
		}
		
		//检测内存和CPU占用率
		foreach($pidList as $pid)
		{
			$info = `ps -aux | grep $pid | grep '$procname'| grep -v 'grep'`;
			$info = trim($info);
			$mem  = `echo $info | awk '{print $5}'`;
			$mem  = intval(trim($mem));
			$cpu  = `echo $info | awk '{print $3}'`;
			$cpu  = intval(trim($cpu));
			if( $mem > $pidMaxMem )
			{
				//重启
				`kill -9 $pid`;
				`/usr/bin/php $procname >/dev/null 2>&1 &`;
			}
			if( $cpu > $pidMaxCpu )
			{
				//重启
				`kill -9 $pid`;
				`/usr/bin/php $procname >/dev/null 2>&1 &`;
			}
		}
	}
	
}

?>
