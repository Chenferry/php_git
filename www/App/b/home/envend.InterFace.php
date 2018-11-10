<?php
include_once('plannedTask/PlannedTask.php'); 
//该文件进行宏观环境设备的管理
//城市：城市天气/温度
class envendInterFace
{
	static $addr = array('m'=>'home','s'=>'envend','f'=>'receiveHICCmd');

	
	//接收HIC发来的命令
	static function receiveHICCmd($cmd,$msg)
	{
		switch( $cmd )
		{
			case DEV_CMD_HIC_CONFIRM:
				self::reportDevAttr();
				break;
			case DEV_CMD_HIC_GET_STATUS:
				self::reportDevInfo();
				break;
			case DEV_CMD_HIC_CTRL_DEV:
				self::setDevInfo($msg);
				break;
		}
		return;
	}
	
	
	//添加默认的系统设备
	static function addSysEnd()
	{
		include_once('b/homeLang.php');
		$msg = array();
		$msg['NAME']    = HOME_SYSDEV_ENV;
		$msg['SN']      = '123';
		$msg['VER']     = '1.0';
		$msg['ISPOWER'] = DEV_POWER_POWER;

		//发送设备信息
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_ADD, $msg);
		
		//系统属性都是事件触发，无需定时上报。不过为了调试方便还是定时触发
		$time = array();
		$time['cyc']   = PLAN_TIME; 
		$time['other'] = 30; 
		$planTask = new PlannedTask('home','envend', $time);
		$planTask->reportDevInfo();
	}

	//确认上报设备属性信息
	static function reportDevAttr()
	{
		$msg  = array(
			array('ATTRINDEX'=>0, 'NAME'=>'天气', 'SYSNAME'=>'tq'),
			array('ATTRINDEX'=>1, 'NAME'=>'气温', 'SYSNAME'=>'cswd')
		);
		
		//发送设备信息
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_JOIN, $msg);
		
		$GLOBALS['dstpSoap']->setModule('home','end');
		$devid = $GLOBALS['dstpSoap']->getDevidFromAddr(serialize(self::$addr), NULL);

		$iconList = array();
		$iconList[] = array('ICON'=>'tq','ATTRINDEX'=>0);
		$iconList[] = array('ICON'=>'wd','ATTRINDEX'=>1);
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->setAttrIcon($devid,$iconList);	}

	// 定时更新系统设备的状态
	static function reportDevInfo()
	{
		$GLOBALS['dstpSoap']->setModule('app','server');
		$weather	=$GLOBALS['dstpSoap']->getWeather();
		if(false==$weather)
			return;
		//网上获取天气预报
		$tq = $weather['weather'];
		$wd = $weather['tempture']; //城市温度
		
		Cache::set('tqxx',$weather,60*120);//天气120分钟如果没更新就失效

		statusNotice('status');
		
		$msg  = array(
			array('ATTRINDEX'=>0, 'STATUS'=>$tq),
			array('ATTRINDEX'=>1, 'STATUS'=>$wd)
		);
		
		//网络通断由脚本触发，而不是定时触发
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_STATUS, $msg);
	}
	
	static function setDevInfo($cmdList)
	{
		return;
	}
	
}
?>