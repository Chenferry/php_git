<?php
	define('HOME_NAME_NULL',      'Please enter the name!');  

	define('HOME_DEV_OFFLINE',    'There is device lost contact!'); 
	define('HOME_DEV_OFFLINEINFO','the device %s lost contact with the center maybe due to power off or other reasons, please check it'); 

	define('HOME_DEV_NEW',        'New device requests to add!');
	define('HOME_DEV_NEWINFo',    'new device requests to add, please check it is your device!'); 
	define('HOME_DEV_WIFICONNECT','Device requests to connect to network'); 

	define('HOME_SMART_PUSH',     'Hdang smart mode notification'); 
	define('HOME_SMART_EXEC',     'the smart mode %s is triggered, please check it!'); 
	define('HOME_SMART_NOEXEC',   'the smart mode %s is	cancelled the trigger, please check it!');
	
	//系统属性名称 system property name
	define('DEV_SYSNAME_TV',      'TV');  
	define('DEV_SYSNAME_KT',      'air-conditioning');  
	define('DEV_SYSNAME_COLOR',   'color light'); 
	define('DEV_SYSNAME_CL',      'curtain');  
	define('DEV_SYSNAME_GJ',      'alarm device');   
	define('DEV_SYSNAME_KG',      'switch/socket');  
	define('DEV_SYSNAME_QJ',      'scene switch');  
	define('DEV_SYSNAME_TCQ',     'window pusher');  
	define('DEV_SYSNAME_BJYY',    'background music player');	 
	define('DEV_SYSNAME_MS',      'lock');    
	define('DEV_SYSNAME_CFG485',  '485 configuration'); 
	define('DEV_SYSNAME_XJD',     'household');
 	define('DEV_SYSNAME_XF',      'ventilation');

	
	//属性配置的显示信息  the attribute configuration info.
	define('ATTRCFG_KAI',       'open');  
	define('ATTRCFG_GUAN',      'close');  
	define('ATTRCFG_ZIDONG',    'auto');  

	//摄像头
	define('ATTRCFG_CAMER_CALLING',      'video calling');
	
	
	//门锁
	define('ATTRCFG_MS_USER_MIMA',       'password'); 
	define('ATTRCFG_MS_USER_ZHIWEN',     'fingerprint');  
	define('ATTRCFG_MS_USER_RENLIAN',    'face');  
	define('ATTRCFG_MS_USER_MENKA',      'card');  
	define('ATTRCFG_MS_USER_SHENWEN',    'voiceprint');   
	define('ATTRCFG_MS_USER_YAOKONG',    'remote control');  
	define('ATTRCFG_MS_USER_WUXIAN',     'wireless');   
	define('ATTRCFG_MS_USER_LOCAL',      'local');

	define('ATTRCFG_MS_STATUS_SHANGSUO',   'lock'); 
	define('ATTRCFG_MS_STATUS_GUANLIYUAN', 'admin.'); 
	
	define('ATTRCFG_MS_YONGHU',     'user');   

	define('ATTRCFG_MS_ALARM_YICHANGKS',  'Abnormal unlock');  
	define('ATTRCFG_MS_ALARM_SCBJ',       'Trial error alarm'); 
	define('ATTRCFG_MS_ALARM_XSBJ',       'Oblique tongue alarm');  
	define('ATTRCFG_MS_ALARM_FSBJ',       'Mechanical key unlock alarm'); 
	define('ATTRCFG_MS_ALARM_FCBJ',       'Tamper alarm');  
	define('ATTRCFG_MS_ALARM_MCBJ',       'Door sensor alarm'); 
	define('ATTRCFG_MS_ALARM_ZDBJ',       'Shock alarm');  
	define('ATTRCFG_MS_ALARM_XTBJ',       'heartbeat alarm');  
	define('ATTRCFG_MS_ALARM_DCBJ',       'Low voltage alarm');  
	define('ATTRCFG_MS_ALARM_CSGMBJ',     'Overtime close door alarm');  

	define('ATTRCFG_MS_MS',     	'doorlock');
	define('ATTRCFG_MS_ADD',     	'add'); 
	define('ATTRCFG_MS_DEL',     	'cancel'); 
	define('ATTRCFG_MS_MODIPSW',    '%s change admin password');
	define('ATTRCFG_MS_USERINFO',   '%s%s%s%s'); //门锁 本地 添加 密码用户1
	define('ATTRCFG_MS_RENAMEUSER', '%s change name %s to %s');  //会当 将 密码用户1 改名为 xxx
	define('ATTRCFG_MS_MODIUSER',   '%s  modifies user %s information');  //会当 修改用户(密码用户1)信息
	define('ATTRCFG_MS_DELFAIL',    'Delete user (%s) failure');  //删除用户xxx失败
	define('ATTRCFG_MS_LOCAL',     	'local');  
	define('ATTRCFG_MS_REMOTER',    'remote'); 
	define('ATTRCFG_MS_GQ',     	'expire'); 
	define('ATTRCFG_MS_SYNC',     	'Synchronize');    
	define('ATTRCFG_MS_CSYJ',     	'count runs out'); 
	define('ATTRCFG_MS_RESET',     	'return to factory set'); 
	define('ATTRCFG_MS_DB',     	'doorbell notification'); 
	define('ATTRCFG_MS_F',     		'fail'); 

	
	//新风系统
	define('ATTRCFG_XF_AREA_WIND',  '风速设置');

	define('ATTRCFG_XF_FUN_JDCC',   '静电除尘');
	define('ATTRCFG_XF_FUN_FLZ',    '负离子');
	define('ATTRCFG_XF_FUN_UVSJ',   'UV杀菌');
	define('ATTRCFG_XF_FUN_FR',     '辅热');
	define('ATTRCFG_XF_FUN_CS',     '除湿');
	define('ATTRCFG_XF_FUN_JS',     '加湿');
	define('ATTRCFG_XF_FUN_CYSJ',   '臭氧杀菌');
	
	define('ATTRCFG_XF_SELECT_MODE','模式');
	define('ATTRCFG_XF_SELECT_PTF', '旁通阀');
	define('ATTRCFG_XF_SELECT_PF',  '排风');
	define('ATTRCFG_XF_SELECT_SF',  '送风');

	define('ATTRCFG_XF_OPEN_GUAN',   '关');
	define('ATTRCFG_XF_OPEN_KAI',    '开');
	define('ATTRCFG_XF_MODE_ZD',     '自动');
	define('ATTRCFG_XF_MODE_SD',     '手动');
	define('ATTRCFG_XF_MODE_DS',     '定时');
	define('ATTRCFG_XF_PTF_PTF',     '旁通阀');
	define('ATTRCFG_XF_PTF_RJH',     '热交换');
	define('ATTRCFG_XF_SPF_D',       '低');
	define('ATTRCFG_XF_SPF_Z',       '中');
	define('ATTRCFG_XF_SPF_G',       '高');
	define('ATTRCFG_XF_SPF_GUAN',    '关闭');
	
	//背景音乐
	define('ATTRCFG_BJYY_SUOYOU',   'all');   
	define('ATTRCFG_BJYY_BENDI',    'local');
	define('ATTRCFG_BJYY_SD',       'SD');
	define('ATTRCFG_BJYY_USB',      'USB');
	define('ATTRCFG_BJYY_SHOUCANNG','my collection');  
	define('ATTRCFG_BJYY_PUTONG',   'common'); 
	define('ATTRCFG_BJYY_GUDIAN',   'classic');  
	define('ATTRCFG_BJYY_JUESHI',   'jazz');  
	define('ATTRCFG_BJYY_YAOGUN',   'rock');  
	define('ATTRCFG_BJYY_LIUXING',  'popular');  
	define('ATTRCFG_BJYY_ZANTING',  'pause');  
	define('ATTRCFG_BJYY_BOFANG',   'play');  

	define('ATTRCFG_KT_ZHILENG',    'refrigeration');   
	define('ATTRCFG_KT_CHUSHI',     'dehumidification');  
	define('ATTRCFG_KT_TONGFENG',   'ventilation');  
	define('ATTRCFG_KT_ZHIRE',      'heating'); 
	define('ATTRCFG_KT_DI',         'low');   
	define('ATTRCFG_KT_ZHONG',      'middle');  
	define('ATTRCFG_KT_GAO',        'high');  
	define('ATTRCFG_KT_FENGXIANG1', 'sweep up and down'); 
	define('ATTRCFG_KT_FENGXIANG2', 'Sweep around the wind');  
	define('ATTRCFG_KT_FENGXIANG3', 'two-way sweep');  
	define('ATTRCFG_KT_FENGXIANG4', 'fixed wind direction');  

	define('ATTRCFG_JR_YOUREN', 'someone');                                                                                                                                                                                                       
	define('ATTRCFG_JR_MEIREN', 'no one');

	define('ATTRCFG_REMOTE_AIR', 'air-conditioner');
	define('ATTRCFG_WL_DUANKAI', 'disconnect'); 
	define('ATTRCFG_WL_LIANJIE', 'connect'); 

	define('ATTRCFG_CLIENT_LIXIAN', 'offline'); 
	define('ATTRCFG_CLIENT_ZAIXIAN','online'); 
	
	//系统虚拟设备名称
	define('HOME_SYSDEV_SMARTGROUP', 'scene mode');  
	define('HOME_SYSDEV_DEVGROUP', 'device group'); 
	define('HOME_SYSDEV_SYS',      'system'); 
	define('HOME_SYSDEV_ENV',      'weather'); 
	define('HOME_SYSDEV_TMP',      'air temperature'); 
	define('HOME_SYSDEV_COM',      'communication'); 
	define('HOME_SYSDEV_SMS',      'SMS');
	define('HOME_SYSDEV_PHONE',    'call');  
	define('HOME_SYSDEV_JR',       'family'); 
	define('HOME_SYSDEV_QTSJ',     'other phone'); 
	define('HOME_SYSDEV_GROUP',    'grouping device'); 
	define('HOME_SYSDEV_UNADDR',   'default area'); 
	//安装设备的特殊说明Special instructions for installation 
	define('HOME_INITUSER_NAME',   'installer'); 

	/**********万能遥控相关universal remote info.**********************************/ 
	define('HOME_REMOTE_IRCODE_NULL',   'remote command downloading error');  

	/*************智能模式配置相关信息intelligent mode configuration******************************/ 
	//周期性任务计划的相关信息Periodic task planning info.
    $GLOBALS['filterArray'] = array(
	'AND'  =>   'and',  
	'OR'   =>   'or'  
	);

	//根据不同类型有不同的显示
	$GLOBALS['opTypeArray'] = array(
		'value'  => array(
		    '='         =>  'equal', 
		    '!='        =>  'not equal to',   
		    '>'         =>  'greater', 
		    '>='        =>  'greater or equal to',  
		    '<'         =>  'less than', 
		    '<='        =>  'less than or equal to',  
		    'BETWEEN'   =>  'between',  
		),

		'select'   => array(
		    'IN'        =>  'included in', 
		    'NOT IN'    =>  'not included in', 
		),

		'switch'   => array(
		    '='         =>  'equal',  
		),

		'char'     => array(
		    'LIKE'      =>  'included in',  
		    'NOT LIKE'  =>  'not included in', 
		),
	);
	$GLOBALS['opArray'] = array(
		'switch' => &$GLOBALS['opTypeArray']['switch' ],
		'cl'     => &$GLOBALS['opTypeArray']['value' ],
		'cl1'    => &$GLOBALS['opTypeArray']['value' ],
		'adjust' => &$GLOBALS['opTypeArray']['value'],
		'alarm'  => &$GLOBALS['opTypeArray']['switch' ],
		'enum'   => &$GLOBALS['opTypeArray']['select'],
		'num'    => &$GLOBALS['opTypeArray']['value'],
		'char'   => &$GLOBALS['opTypeArray']['char'],
		'time'   => &$GLOBALS['opTypeArray']['value'],
		'client' => &$GLOBALS['opTypeArray']['switch'],
		'color'  => &$GLOBALS['opTypeArray']['switch'],
		'tcq'    => &$GLOBALS['opTypeArray']['select'],
		'ms'     => &$GLOBALS['opTypeArray']['select'],
		'bjyy'   => &$GLOBALS['opTypeArray']['switch'],
		'rsq'    => &$GLOBALS['opTypeArray']['value'],
		'xjd'    => &$GLOBALS['opTypeArray']['switch'],
	);
?>