<?php
class localInterFace
{
	private static function sysMaintenceInB()
	{
		//重启相关服务
		`killall php-cgi`;
		`/etc/init.d/lighttpd restart`;
		
		//hicserver等相关服务重启。删除除sysmaintence外的所有php进程
		//当前进程正由于sysmaintence执行，所以不能删除
		$mypid = getmypid();
		$proc = 'php-cli';
		$pidList = `pgrep -f '$proc'`;
		$pidList = explode("\n",$pidList);
		foreach( $pidList as $pid )
		{
			if($pid == $mypid)
			{
				continue;
			}
			`kill -9 $pid`;	
		}
		`php-cli /www/App/b/cli/monitor.php`;	
		
		//定时和服务器交互的，但为了避免所有信息中心同时请求服务器瞬间压力太大
		//启动交互时这儿设置随机时间后再启动，分散处理时间
		//备份时间尽量放在晚上，该任务00：30启动，5小时内处理完
		include_once('plannedTask/PlannedTask.php');
		$time = mt_rand(1001,5*3600);
		$planTask = new PlannedTask('local','dev', $time);
		$planTask->backupHICCfg();
		
		//备份完成3分钟后再启动升级。要考虑升级过程是否有可能被monitor打断
		$time     = $time + 500;
		$planTask = new PlannedTask('local','upgrade', $time);
		$planTask->upgradeRouter();
		
		//升级检查两次提高成功率
		$time     = $time + 1500;
		$planTask = new PlannedTask('local','upgrade', $time);
		$planTask->upgradeRouter();
		
	}
	//定时维护任务，清理较少的数据
	static function planMaintence()
	{
		$GLOBALS['dstpSoap']->setModule('setting','setting');
		$GLOBALS['dstpSoap']->checkUpdateRoomInfo();

		//重新更新一次页面缓存
		$GLOBALS['dstpSoap']->setModule('devattr','devattr');
		$GLOBALS['dstpSoap']->checkUpdatePageList();
	}
	//每天凌晨的系统维护处理
	static function sysMaintence()
	{
		//清理脏数据
		$GLOBALS['dstpSoap']->setModule('local','local');
		$GLOBALS['dstpSoap']->cleanDirtyData();
		
		//重新更新一次页面缓存
		$GLOBALS['dstpSoap']->setModule('devattr','devattr');
		$GLOBALS['dstpSoap']->maintencePageList();
		
		$GLOBALS['dstpSoap']->setModule('setting','setting');
		$GLOBALS['dstpSoap']->maintenceRoomInfo();
	
		//查询更新本地保存的IR数据文件
		$time = mt_rand(0,5*3600);
		$planTask = new PlannedTask('home','remote', $time);
		$planTask->checkIRCodeFile();
		
		//和服务器同步数据，包括用户密码,备份历史告警信息
		//随机决定是否要重新交换密钥
		if( 'b' == HIC_LOCAL )
		{
			self::sysMaintenceInB();
		}
	}
	
	private static function cleanDirtyDataInB()
	{
		//每天清理一遍内存
		`sync && echo 1 > /proc/sys/vm/drop_caches`;

		//VACUUM数据库文件
		`sqlite3 /tmp/hdang.db "VACUUM;"`;
		
		//每天至少保存一次数据
		file_put_contents('/tmp/dbchange','');

		//每天清除失效的缓存文件		
		$path = $GLOBALS['DstpDir']['tempDir'].'/hiccache/'; 
		if( is_dir($path) )
		{
			$handle = opendir($path);
			$array_file = array();
			while (false !== ($file = readdir($handle)))
			{
				if ($file != "." && $file != "..") 
				{
					Cache::get($file);
				}
			}
			closedir($handle);
		}			
		
        include_once('procd/service.class.php');
        service::clearEmptySessionFile();
	}
	
	//每天定期清理过期数据
	//该函数需要修改，查找每个设备类型的sysMaintence函数。清除各设备类型的过期数据
	//
	static function cleanDirtyData()
	{
		//清除b独有数据
		if( 'b' == HIC_LOCAL )
		{
			self::cleanDirtyDataInB();
		}
		
		self::cleanAttrTypeData();
		
		//每天清除未加入设备
		$c	= new TableSql('homedev','ID');
		$initList = $c->queryAllList('ID','STATUS=? AND ATIME<?'
									,array(DEV_STATUS_INIT,(time()-3600*2)));
		foreach( $initList as $initid )
		{
			$GLOBALS['dstpSoap']->setModule('home','end');
			$GLOBALS['dstpSoap']->del($initid);
		}
									

		//每天清除过期客户端数据
		$c	= new TableSql('homeclient','ID');
		$cList = $c->queryAllList('ID','( PERIOD=? OR PERIOD=? ) AND CTIME < ? AND IP IS NULL',
									array(DEV_CLIENT_INIT,DEV_CLIENT_REQUEST,(time()-36000)));
		foreach($cList as $cid)
		{
			$GLOBALS['dstpSoap']->setModule('home','client');
			$GLOBALS['dstpSoap']->allowClient($cid, DEV_CLIENT_INIT);
		}


		//每天清除下误加入的第三方zigbee设备
		$c   = new TableSql('homeexceptdev');
		$list = $c->queryAll('*','JOINTIME<?',array( time()-86400 ));
		$c->del( 'JOINTIME<?',array( time()-86400 ) );

		$c   = new TableSql('homedev');
		foreach( $list as $dev )
		{
			$info = $c->query('ID','PHYADDR=? AND SUBHOST=?',
								array($dev['PHYADDR'],$dev['SUBHOST']));
			if( NULL != $info )
			{
				continue;
			}
			$GLOBALS['dstpSoap']->setModule('home','if');
			$GLOBALS['dstpSoap']->sendDevSySMsg($dev['SUBHOST'],DEV_CMD_SYS_RM_DEV_ASSOC,$dev['PHYADDR']);
		}
		
		//设备组信息清理维护
		$GLOBALS['dstpSoap']->setModule('smart','devgroup');
		$GLOBALS['dstpSoap']->sysMaintence();
		
		//清除a中的数据
		$GLOBALS['dstpSoap']->setModule('frame','frame');
		return $GLOBALS['dstpSoap']->cleanDirtyData();
	}
	
	static function cleanAttrTypeData()
	{
		$dir = dirname(dirname(__FILE__)).'/devattr/attrType/';
		$dh  = opendir($dir);
		while (false !== ($file = readdir($dh))) 
		{
			if( '.' == $file || '..' == $file ) continue;

			//根据file获取type，xxxAttr.php
			$type = substr($file,0,-8);
			$class = $type.'AttrType';
			$file = "$dir/$file";
			include_once($file);
			if ( !property_exists($class, 'sysMaintence') )
			{
				continue;
			}
			$class::sysMaintence();
		}
		closedir($dh);		
	}
	
	//登陆时通知
	static function loginNotice($t)
	{
		setDevSleep(2);
	}
	
	//检测是否有外网
	static function isConnect()
	{
		include_once('uci/uci.class.php');
		$ip=wan::getIP();
		$validip=ip2long($ip);
		if (empty($validip) || ($validip == -1) || ($validip === FALSE)) {
			return false;
		}
		return  true;
	}
	//检测是否能和c.jia.sx正常通讯
	static function isConnectToCloud()
	{
		$conn = stream_socket_client('tcp://'.c_jia_sx.':80', $errno, $errstr,3,STREAM_CLIENT_CONNECT );
		if(!$conn)
		{
			return false;
		}
		fclose($conn);
		return true;
	}
	/*设置设备名称，修改SSID.$restart，是否立即重启wifi网络生效*/
	static function setHICName($ssid,$restart=true)
	{
		if ( NULL == $ssid )
		{
			return false;
		}
		$c = new TableSql('hic_hic');
		$name = $c->queryValue('NAME','ID=?',array(HICInfo::getHICID()));
		if ( $name == $ssid )
		{
			return true;
		}
		
		include_once('uci/uci.class.php');
		SSID::setSSID($ssid);
		
		//修改设备名称
		$c = new TableSql('hic_hic');
		$info = array();
		$info['NAME'] = $ssid;
		$c->update($info);
		
		//向服务器同步SSID
		$GLOBALS['dstpSoap']->setModule('app','hic');
		$GLOBALS['dstpSoap']->setHICName($ssid);
		if ( $restart )
		{
			network::restart();
		}
		return true;
	}
	
	static function checkToken($token)
	{
		$GLOBALS['dstpSoap']->setModule('frame');
		return $GLOBALS['dstpSoap']->checkToken($token);
	}
	
	static function unbindUser($userid)
	{
		//清空该用户自动登陆缓存
		$c = new TableSql('hic_user','ID');
		$c->delByID($userid);
		$c = new TableSql('hic_frameautologin');
		$c->del('USERID=?',array($userid));
		
		//清空相关的session
        include_once('procd/service.class.php');
        service::clearSessionFile();		
		return true;
	}

	//向路由器定时上报自己的状态进行保活
	static function reportHICStatus()
	{
		//这个运行不需要很频繁，所以只要运行，就无需重复
		$r = Cache::get('reportHICStatus');
		if( false != $r )
		{
			return false;
		}
		Cache::set('reportHICStatus','a',50);
		
		//当由crontab触发了该命令时，在设备时间比较同步情况下，实际上这任务几乎是同时执行
		//这导致服务器会在同时受到大量的心跳消息，超出负荷，导致服务器大量拒绝
		//为了避免同时触发，这儿使用随机时间错开请求
		include_once('plannedTask/PlannedTask.php');
		$time = mt_rand(0,120);
		$planTask = new PlannedTask('delay','syslocal', $time);
		$planTask->reportHICStatus();
	}
}
?>