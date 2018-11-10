<?php
	/*
	这是系统维护接口文件，供系统调度任务通过定时调用。每次调用时，都自动检查DSTP中的计划任务并进行执行
	*/
	//暂时不考虑云主机的更新维护。先考虑item和本地主机
	// 云模式下控制只能通过自动程序调用，不允许使用手工调用
	if( 'cli'!=PHP_SAPI )
	{
		exit(-1);
	}
	
	//这个要使用绝对路径。因为有可能用命令行方式从其他地方进行调用
	$dstpCommonInfo = dirname(dirname(__FILE__)).'/config/dstpCommonInfo.php';
	require_once($dstpCommonInfo);

	//执行计划任务可能很耗时，设置允许运行时长2小时
	@ini_set('max_execution_time', MAX_EXEC_TIME );
	@ignore_user_abort(true);
	
	include_once('plannedTask/PlannedTask.php');
	
	//以系统用户身份进行调用
	$GLOBALS['curUserID']   = INVALID_ID;
	$GLOBALS['curUserName'] = 'system';
	
	if( 'i' == HIC_LOCAL )
	{
		//如果当前sysmaintence还在执行，则直接退出，避免多个同时执行
		
		//读取指定配置的itemid列表，每个进行处理
		foreach( $GLOBALS['itemList'] as $itemid )
		{
			//初始化数据库环境
			$GLOBALS['dstpSoap']->setModule('frame');
			$GLOBALS['dstpSoap']->initDBEnvByItem($itemid);
			
			//查找当前有需要超时执行的hicid，构造环境执行
			$c = new TableSql('batchproc','ID');
			$w = $c->query('*','(EXCTIME)<((AHEAD)+?) AND (EXCTIME>=STARTTIME) AND (EXCTIME<=ENDTIME) ',array(time()));
			while( NULL != $w )
			{
				setSysUid($w['CLOUDID']);
				$client = new PlannedTask();
				//在这里，一次性就把该hicid里所有超时任务执行完成
				$client->runPlanTask();
				//继续获取下一个hicid进行处理
				setSysUid( 0 );
				$w = $c->query('*','(EXCTIME)<((AHEAD)+?)  AND (EXCTIME>=STARTTIME) AND (EXCTIME<=ENDTIME) ',array(time()));
			}
			
			//统一查找离线设备
			$GLOBALS['dstpSoap']->setModule('home','end');
			$GLOBALS['dstpSoap']->scanEndOnlineInI();
		}

		die('ok');
	}

	//避免sysmaintence重入
	if( 'b' == HIC_LOCAL )
	{
		$exec = Cache::get('sysmaintence');
		if ( false != $exec )
		{
			exit();
		}
		Cache::set('sysmaintence',time(),120);
	}	

	$client = new PlannedTask();
	//检测前一个任务是否还在执行，不要多个在同时运行.由runPlanTask自己保证
	$client->runPlanTask();
	
	if( 'b' == HIC_LOCAL )
	{
		Cache::del('sysmaintence');
	}
	echo 'ok';
?>
