<?php
/**
 * 本地可能访问网络或者被挂起的调用
 */

class syslocalInterFace
{
	static function reportHICStatus()
	{
		include_once('uci/uci.class.php');
		$ip=wan::getIP();

		$vercfg = dirname(dirname(dirname(__FILE__))).'/a/config/dstpHICVersion.cfg';
		$ver = file_get_contents($vercfg);  
		
		$GLOBALS['dstpSoap']->setModule('app','trigger');
		$info = $GLOBALS['dstpSoap']->reportHICStatus($ip,trim($ver));
		if ( !$info )
		{
			return;
		}
		Cache::set('statusinfo', $info);	
		Cache::set('connnetstatus', $info,600);	
		if ($info['sync'])
		{
			//如果需要同步，则马上同步一次
			$GLOBALS['dstpSoap']->setModule('local','dev');
			$r = $GLOBALS['dstpSoap']->syncHicInfo();
			if($r)
			{
				//如果同步成功了，则要求清除掉同步标记，避免频繁同步
				$GLOBALS['dstpSoap']->setModule('app','trigger');
				$r = $GLOBALS['dstpSoap']->setSyncFlag(false);
			}
		}
	}


}