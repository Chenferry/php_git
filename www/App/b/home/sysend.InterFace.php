<?php
include_once('plannedTask/PlannedTask.php'); 
//该文件进行系统默认设备的管理
//系统：WIFI/网络
class sysendInterFace
{
	static $addr = array('m'=>'home','s'=>'sysend','f'=>'receiveHICCmd');
	
	
	//////////////////////////////////////////////
	
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
		$msg['NAME']    = HOME_SYSDEV_SYS;
		$msg['SN']      = '123';
		$msg['VER']     = '1.0';
		$msg['ISPOWER'] = DEV_POWER_POWER;

		//发送设备信息
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_ADD, $msg);
		
		//系统属性都是事件触发，无需定时上报。不过为了调试方便还是定时触发
		//$time = array();
		//$time['cyc']   = PLAN_TIME; 
		//$time['other'] = 30; 
		//$planTask = new PlannedTask('home','sysend', $time);
		//$planTask->reportDevInfo();
	}

	//确认上报设备属性信息
	static function reportDevAttr()
	{
		//接入的家庭设备也做为该设备的一个子属性，且其attrindex以自己数据表中的ID为信息。
		//所以这儿设的attrindex都要保证不会与正常ID冲突
		$msg  = array(
			//array('ATTRINDEX'=>0,             'NAME'=>'网络状况', 'SYSNAME'=>'wl'),
			//暂时屏蔽无线信号处理
			//array('ATTRINDEX'=>MAX_INT_VALUE, 'NAME'=>'无线信号', 'SYSNAME'=>'tg'),
			array('ATTRINDEX'=>-3,            'NAME'=>'消息推送', 'SYSNAME'=>'push'),
			array('ATTRINDEX'=>-2,            'NAME'=>'家人',     'SYSNAME'=>'jr'),
			array('ATTRINDEX'=>-1,            'NAME'=>'其他手机', 'SYSNAME'=>'mobile'),
		);
		
		//发送设备信息
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_JOIN, $msg);

		$GLOBALS['dstpSoap']->setModule('home','end');
		$devid = $GLOBALS['dstpSoap']->getDevidFromAddr(serialize(self::$addr), NULL);

		$iconList = array();
		//$iconList[] = array('ICON'=>'wl','ATTRINDEX'=>0);
		$iconList[] = array('ICON'=>'jr','ATTRINDEX'=>-2);
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->setAttrIcon($devid,$iconList);
	}

	// 定时更新系统设备的状态
	static function reportDevInfo()
	{
		//if(DSTP_DEBUG)
		//{
		//	$wifi = Cache::get('wifirssi'); //SSID::getWiFiPower();//获取wifi信号强度			
		//}
		//else
		//{
		//	include_once('uci/uci.class.php');
		//	$wifi = SSID::getWiFiPower();//获取wifi信号强度			
		//}

		//$GLOBALS['dstpSoap']->setModule('local','local');
		//$isConnect   = $GLOBALS['dstpSoap']->isConnect();
        //
		//$msg  = array(
		//	array('ATTRINDEX'=>0, 			  'STATUS'=>$isConnect),
		//	//array('ATTRINDEX'=>MAX_INT_VALUE, 'STATUS'=>$wifi)
		//);
		//
		////网络通断由脚本触发，而不是定时触发
		//$GLOBALS['dstpSoap']->setModule('home','if');
		//$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_STATUS, $msg);
	}
	
	//更新客户端的在线状态
	static function reportClientStatus($id,$status=1)
	{
		$msg  = array(
			array('ATTRINDEX'=>$id, 'STATUS'=>$status),
		);
		
		if( $status )//只要有一个人在线，那么家人就是在线
		{
			$msg[] = array('ATTRINDEX'=>-2, 'STATUS'=>1);
		}
		else
		{
			//判断是否所有家人都已离线
			$c = new TableSql('homeattr','ID');	
			$num = $c->getRecordNUM('SYSNAME=? AND ATTRINDEX!=? AND ATTRINT=1 AND INUSE=1',
										array('client',$id));
			if( $num <= 0 ) //如果只有一个人在线。这个人离线后，所有家人离线
			{
				$msg[] = array('ATTRINDEX'=>-2, 'STATUS'=>0);
			}
		}
		
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendSysEndMsgToHIC(self::$addr, DEV_CMD_DEV_STATUS, $msg);
	}

	
	static function setDevInfo($cmdList)
	{
		foreach($cmdList as &$cmd)
		{
			switch($cmd['ATTRINDEX'])
			{
				case MAX_INT_VALUE:
					if(DSTP_DEBUG)
					{
						Cache::set('wifirssi', $cmd['SENDATTR']);
					}
					else
					{
						include_once('uci/uci.class.php');
						SSID::setWiFiPower($cmd['SENDATTR']);
					}
					self::reportDevInfo();
					break;
				case 0:  //不允许断网
					break;
				default: //接入网络的的终端ID
					break;
				
			}
		}
	}
	
	//如果设置了主人设备，则该上网设备必须做为系统设备的一个属性加以管理
	static function addClientDev($id,$name)
	{
		$info = array();
		$info['NAME']      = $name; //需要从homeclient中获取名字
		$info['CANDEL']    = 1;
		$info['SYSNAME']   = 'client';
		$info['ATTRINDEX'] = $id;
		$attrList[] = $info;

		$GLOBALS['dstpSoap']->setModule('home','end');
		$devid= $GLOBALS['dstpSoap']->getDevidFromAddr(serialize(self::$addr), NULL);
		
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->addDevAttList($devid,$attrList);

		$iconList = array();
		$iconList[] = array('ICON'=>'msj','ATTRINDEX'=>$id);
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->setAttrIcon($devid,$iconList);
	}
	static function delClientDev($devid,$id)
	{
		//判断是否所有家人都已离线
		
		$GLOBALS['dstpSoap']->setModule('home','client');
		return $GLOBALS['dstpSoap']->allowClient($id);
	}
	
	////////////////////////////////////////////////////////

	//中央空调内机是虚拟为一个虚拟设备，其接受消息由这儿处理
	static function zhktReceiveCMD($cmd,$msg,$devid)
	{
		if( DEV_CMD_HIC_CTRL_DEV != $cmd )
		{
			return;
		}
		$c = new TableSql('homeattr','ID');
		$GLOBALS['dstpSoap']->setModule('devattr','attr');
		//从msg中获取属性信息，根据属性信息获取完整的
		foreach( $msg as &$m )
		{
			//获取当前属性ID，发给其对应的中央空调属性处理
			$cfg = $c->queryValue('CFGINFO','DEVID=? AND ATTRINDEX=?',array($devid,$m['ATTRINDEX']));
			$cfg = unserialize($cfg);
			$GLOBALS['dstpSoap']->execAttr($cfg['zhktid'], array('open'=>0xFC,'njdz'=>$cfg['njdz'],'cmd'=>$m['SENDATTR'] ) );			
		}
	}

	

}
?>