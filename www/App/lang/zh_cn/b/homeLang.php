<?php
	define('HOME_NAME_NULL',      '请输入名称');

	define('HOME_DEV_OFFLINE',    '有设备失去联系');
	define('HOME_DEV_OFFLINEINFO','设备%s与信息中心失去联系，有可能没电或者其它原因请查看解决');

	define('HOME_DEV_NEW',        '有新设备请求加入信息中心');
	define('HOME_DEV_NEWINFo',    '有新设备%s要加入信息中心，请确认是否您的设备');
	define('HOME_DEV_WIFICONNECT','有设备请求要连接上网');

	define('HOME_SMART_PUSH',     '会当智能模式消息');
	define('HOME_SMART_EXEC',     '您设置的智能模式%s已触发，请查看');
	define('HOME_SMART_NOEXEC',   '您设置的智能模式%s已经解除触发，请查看');
	
	//系统属性名称 
	define('DEV_SYSNAME_TV',      '电视');
	define('DEV_SYSNAME_KT',      '空调');
	define('DEV_SYSNAME_COLOR',   '彩灯');
	define('DEV_SYSNAME_CL',      '窗帘');
	define('DEV_SYSNAME_GJ',      '告警设备');
	define('DEV_SYSNAME_KG',      '开关插座');
	define('DEV_SYSNAME_QJ',      '情景开关');
	define('DEV_SYSNAME_TCQ',     '推窗器');
	define('DEV_SYSNAME_BJYY',    '背景音乐');	
	define('DEV_SYSNAME_MS',      '门锁');
	define('DEV_SYSNAME_CFG485',  '485配置');
	define('DEV_SYSNAME_XJD',     '家电');
	define('DEV_SYSNAME_XF',      '新风');

	
	//属性配置的显示信息
	define('ATTRCFG_KAI',       '开');
	define('ATTRCFG_GUAN',      '关');
	define('ATTRCFG_ZIDONG',    '自动');
	
	//摄像头
	define('ATTRCFG_CAMER_CALLING',      '视频呼叫');
	

	//门锁
	define('ATTRCFG_MS_USER_MIMA',       '密码');
	define('ATTRCFG_MS_USER_ZHIWEN',     '指纹');
	define('ATTRCFG_MS_USER_RENLIAN',    '人脸');
	define('ATTRCFG_MS_USER_MENKA',      '门卡');
	define('ATTRCFG_MS_USER_SHENWEN',    '声纹');
	define('ATTRCFG_MS_USER_YAOKONG',    '遥控');
	define('ATTRCFG_MS_USER_WUXIAN',     '无线');
	define('ATTRCFG_MS_USER_LOCAL',      '无线');
	
	define('ATTRCFG_MS_STATUS_SHANGSUO',   '上锁');
	define('ATTRCFG_MS_STATUS_GUANLIYUAN', '管理员');
	
	define('ATTRCFG_MS_YONGHU',     '用户');
	
	define('ATTRCFG_MS_ALARM_YICHANGKS',  '异常开锁');
	define('ATTRCFG_MS_ALARM_SCBJ',       '试错报警');
	define('ATTRCFG_MS_ALARM_XSBJ',       '斜舌报警');
	define('ATTRCFG_MS_ALARM_FSBJ',       '机械钥匙开锁报警');
	define('ATTRCFG_MS_ALARM_FCBJ',       '防拆报警');
	define('ATTRCFG_MS_ALARM_MCBJ',       '门磁报警');
	define('ATTRCFG_MS_ALARM_ZDBJ',       '震动报警');
	define('ATTRCFG_MS_ALARM_XTBJ',       '心跳报警');
	define('ATTRCFG_MS_ALARM_DCBJ',       '电池电量低');
	define('ATTRCFG_MS_ALARM_CSGMBJ',     '超时关门报警');

	define('ATTRCFG_MS_MS',     	'门锁');
	define('ATTRCFG_MS_ADD',     	'添加');
	define('ATTRCFG_MS_DEL',     	'删除');
	define('ATTRCFG_MS_MODIPSW',    '%s修改管理员密码');
	define('ATTRCFG_MS_USERINFO',   '%s%s%s%s');  //门锁 本地 添加 用户 密码用户1
	define('ATTRCFG_MS_RENAMEUSER', '%s将%s改名为%s');  //会当 将 密码用户1 改名为 xxx
	define('ATTRCFG_MS_MODIUSER',   '%s修改用户(%s)信息');  //会当 修改用户(密码用户1)信息
	define('ATTRCFG_MS_DELFAIL',    '删除用户(%s)失败');  //删除用户xxx失败
	define('ATTRCFG_MS_LOCAL',     	'本地');
	define('ATTRCFG_MS_REMOTER',    '远程');
	define('ATTRCFG_MS_GQ',     	'过期');
	define('ATTRCFG_MS_SYNC',     	'同步');
	define('ATTRCFG_MS_CSYJ',     	'次数用尽');
	define('ATTRCFG_MS_RESET',     	'恢复出厂设置');
	define('ATTRCFG_MS_DB',     	'门铃通知');
	define('ATTRCFG_MS_F',     		'失败');

	
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
	define('ATTRCFG_BJYY_SUOYOU',   '所有');
	define('ATTRCFG_BJYY_BENDI',    '本地');
	define('ATTRCFG_BJYY_SD',       'SD');
	define('ATTRCFG_BJYY_USB',      'USB');
	define('ATTRCFG_BJYY_SHOUCANNG','我的收藏');
	define('ATTRCFG_BJYY_PUTONG',   '普通');
	define('ATTRCFG_BJYY_GUDIAN',   '古典');
	define('ATTRCFG_BJYY_JUESHI',   '爵士');
	define('ATTRCFG_BJYY_YAOGUN',   '摇滚');
	define('ATTRCFG_BJYY_LIUXING',  '流行');
	define('ATTRCFG_BJYY_ZANTING',  '暂停');
	define('ATTRCFG_BJYY_BOFANG',   '播放');

	define('ATTRCFG_KT_ZHILENG',    '制冷');
	define('ATTRCFG_KT_CHUSHI',     '除湿');
	define('ATTRCFG_KT_TONGFENG',   '通风');
	define('ATTRCFG_KT_ZHIRE',      '制热');
	define('ATTRCFG_KT_DI',         '低');
	define('ATTRCFG_KT_ZHONG',      '中');
	define('ATTRCFG_KT_GAO',        '高');
	define('ATTRCFG_KT_FENGXIANG1', '上下扫风');
	define('ATTRCFG_KT_FENGXIANG2', '左右扫风');
	define('ATTRCFG_KT_FENGXIANG3', '双向扫风');
	define('ATTRCFG_KT_FENGXIANG4', '固定风向');

	define('ATTRCFG_JR_YOUREN', '有人');
	define('ATTRCFG_JR_MEIREN', '没人');

	define('ATTRCFG_REMOTE_AIR', '空调');
	
	define('ATTRCFG_WL_DUANKAI', '断开');
	define('ATTRCFG_WL_LIANJIE', '连接');

	define('ATTRCFG_CLIENT_LIXIAN', '离线');
	define('ATTRCFG_CLIENT_ZAIXIAN','在线');
	
	//系统虚拟设备名称
	define('HOME_SYSDEV_SMARTGROUP', '情景模式');
	define('HOME_SYSDEV_DEVGROUP', '设备组');
	define('HOME_SYSDEV_SYS',      '系统');
	define('HOME_SYSDEV_ENV',      '天气');
	define('HOME_SYSDEV_TMP',      '气温');
	define('HOME_SYSDEV_COM',      '通讯');
	define('HOME_SYSDEV_SMS',      '发送短信');
	define('HOME_SYSDEV_PHONE',    '拨打电话');
	define('HOME_SYSDEV_JR',       '家人');
	define('HOME_SYSDEV_QTSJ',     '其他手机');
	define('HOME_SYSDEV_GROUP',    '分组设备');
	define('HOME_SYSDEV_UNADDR',   '默认位置');
	//安装设备的特殊说明
	define('HOME_INITUSER_NAME',   '安装人员');

	/**********万能遥控相关**********************************/
	define('HOME_REMOTE_IRCODE_NULL',   '下载遥控器指令出错');

	/*************智能模式配置相关信息******************************/
	//周期性任务计划的相关信息
    $GLOBALS['filterArray'] = array(
	'AND'  =>   '并且',
	'OR'   =>   '或者'
	);

	//根据不同类型有不同的显示
	$GLOBALS['opTypeArray'] = array(
		'value'  => array(
		    '='         =>  '等于',
		    '!='        =>  '不等于',
		    '>'         =>  '大于',
		    '>='        =>  '大于等于',
		    '<'         =>  '小于',
		    '<='        =>  '小于等于',
		    'BETWEEN'   =>  '位于',
		),

		'select'   => array(
		    'IN'        =>  '包含于',
		    'NOT IN'    =>  '不包含于',
		),

		'switch'   => array(
		    '='         =>  '等于',
		),

		'char'     => array(
		    'LIKE'      =>  '包含',
		    'NOT LIKE'  =>  '不包含',
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
