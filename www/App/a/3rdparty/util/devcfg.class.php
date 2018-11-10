<?php
/*HIC下的服务端口定义*/

//define('HIC_SERVER_SDIO',     2889); //接收发送给串口数据的进程，只接受127.0.0.1连接。发到该端口的命令不缓存
define('HIC_SERVER_RDIO',     2888); //ser2net.conf中用。只接受127.0.0.1连接
define('HIC_SERVER_RWIFI',    2887); //默认内网设备连接端口，只接受内网连接
define('HIC_SERVER_SWIFI',    2886); //转发给默认内网设备处理的进程，只接受127.0.0.1连接
define('HIC_SERVER_DELAY',    2885); //用来设置定时器后启动维护任务，只接受127.0.0.1连接
define('HIC_SERVER_SCREEN',   2884); //接受摄像头的截图数据，只接受内网连接
define('HIC_SERVER_CLIENT',   2883); //未认证客户端的访问请求处理端口
//define('HIC_SERVER_SDIO1',    2882); //接收发送给串口数据的进程，只接受127.0.0.1连接
//define('HIC_SERVER_EXTHOST',  2881); //接收分机系统透传消息的端口
//define('HIC_SERVER_RWIFI1',   2880); //内网wifi设备连接端口，只接受内网连接。且第一条报MAC地址
//define('HIC_SERVER_YUYIN',    2879); //内网语音处理接口。这个接口后续需要取消
define('HIC_SERVER_YOUZHUAN', 2879); //右转连接的特殊处理
define('HIC_SERVER_PROXY',    2878); //状态检测进程连接proxystub的端口
define('HIC_SERVER_DELAY_E',    2877); //用来设置定时器后访问外网的维护任务，只接受127.0.0.1连接

//代码重构后，系统允许多个进程用来接送分机的透传消息。
define('HIC_SERVER_EXTRSTART', 2988); //接收分机系统透传消息的起始端口，多个进程使用端口由该端口按序递增
define('HIC_SERVER_EXTSSTART', 3988); //

/*下面这些接口，需要对外提供服务，防火期中要打开端口******/
define('HIC_SERVER_WEB',      2890);   //在wan口的web服务端口。因为80端口经常被封杀，所以重定义个新端口
                                     //这个端口同时需要做端口转发到80

define('HIC_SERVER_STATUS',   2891);   //websock连接状态通知的端口
define('HIC_SERVER_RTSPCTRL', 2892);   //摄像头转动控制连接的端口
define('HIC_SERVER_RTSP',     2893);   //rtsp协议连接的端口
define('HIC_SERVER_BCAST',    2894);   //广播监听端口


/*智能模式下的一些相关定义*/
//智能模式从哪里保存的
define('SMART_FROM_NOR',     0); //用户自定义的智能模式
define('SMART_FROM_ATTRPLAN',1); //属性定时任务
define('SMART_FROM_REL',     2); //联动任务
define('SMART_FROM_GROUP',   3); //情景模式判断依据
define('SMART_FROM_PLAN',    4); //普通定时任务

/***********设备物理类型定义********/
define('PHYDEV_TYPE_SYS',       0); //系统虚拟设备
define('PHYDEV_TYPE_ZIGBEE',    1); //zigbee设备
define('PHYDEV_TYPE_IP',        2); //ip通讯设备
define('PHYDEV_TYPE_485',       3); //485总线设备
define('PHYDEV_TYPE_24G',       4); //普通24G设备

/**********设备的供电情况*************************/
define('DEV_POWER_BATTERY',     0); //电池设备
define('DEV_POWER_POWER',       0xFF); //电源设备

/**********设备的受控情况*************************/
define('POWER_DC',              0); //电源设备
define('POWER_BAT_CTRL',        1); //电池，且受控时间敏感
define('POWER_BAT_CTRL_RPT',    2); //电池，受控时间不敏感
define('POWER_BAT_RPT',         3); //电池，不受控


/***********设备状态定义***********/
define('DEV_STATUS_STOP',      -1);  //停用
define('DEV_STATUS_INIT',       0);  //未加入
define('DEV_STATUS_WAITACK',    1);  //加入确认中.确认成功后，校验密码才生效
//本行之上的状态，都是在家庭状态中不显示的。其定义值都要小于DEV_STATUS_RUN
define('DEV_STATUS_RUN',        10); //正常
//本行之下的状态，都是在家庭状态中会显示的。其定义值都要大于DEV_STATUS_RUN
define('DEV_STATUS_OFFLINE',    11); //离线
define('DEV_STATUS_POWER',      50); //电池告警

/*********告警标准码*************/
/***所有的告警码要在homeLang.php的gAlarmInfo变量中设置文字提示信息******/
define('DEV_ALARM_IGNORE',     -1); //无需告警
define('DEV_ALARM_CLEAN',       0); //告警消除
define('DEV_ALARM_ALARM',       1); //通用告警
define('DEV_ALARM_POWER',       2); //电池告警
define('DEV_ALARM_OFFLINE',     3); //离线告警


/*********上网设备的状态定义*************/
define('DEV_CLIENT_REJECT',    -1); //拉黑
define('DEV_CLIENT_INIT',       0); //未处理
define('DEV_CLIENT_LONG',       1); //主人手机。永久允许
define('DEV_CLIENT_TEMP',       2); //客人。临时允许
define('DEV_CLIENT_DEV',        3); //设备
define('DEV_CLIENT_PC',         4); //主人电脑。永久允许
define('DEV_CLIENT_REQUEST',    5); //已经发出了请求

/********连入HIC的途径************************/
define('DEV_CONNECTHIC_WAN',    0); //WAN
define('DEV_CONNECTHIC_LAN',    1); //网口
define('DEV_CONNECTHIC_SSID',   2); //用户设置的SSID
define('DEV_CONNECTHIC_DEVSSID',3); //隐藏的SSID

/*******红外设备类型定义*******************/
define('DEV_REMOTE_AIR',        1); //空调
define('DEV_REMOTE_TV',         2); //电视
define('DEV_REMOTE_BOX',        3); //机顶盒
define('DEV_REMOTE_DVD',        4); //DVD
define('DEV_REMOTE_FAN',        5); //电风扇
define('DEV_REMOTE_CLEANER',    6); //空气净化器
define('DEV_REMOTE_IPTV',       7); //IPTV

/*****************用户类型定义*****************/
define('USER_TYPE_ADMIN',		10); //管理员
define('USER_TYPE_SYSTEM',		20); //系统设置员
define('USER_TYPE_COMMON',		30); //普通用户

/*****************特殊房间号定义*****************/
define('ROOM_SYSDEV_UNADDR',	-1); //未分区房间
define('ROOM_SYSDEV_DEVGROUP',	-2); //设备组房间
define('ROOM_SYSDEV_SYS',		-3); //系统房间
define('ROOM_SYSDEV_SMARTGROUP',-5); //情景模式房间
define('ROOM_SYSDEV_WHITE',		 1); //白名单
define('ROOM_SYSDEV_BLACK',		 2); //黑名单


/*******红外设备控制命令字*******************/
define('REMOTE_CMD_START_CATCH',   1); //开始学习，info为空
define('REMOTE_CMD_CLOSE_CATCH',   2); //停止学习，info为空
//define('REMOTE_CMD_CATCH_RESULT',  3); //学习返回。Info为学习结果
define('REMOTE_CMD_CATCH_CONFIRM', 4); //确认。Info为确认包序号
define('REMOTE_CMD_CTRL',          5); //发射红外码。Info为红外码
define('REMOTE_CMD_START_CATCH2',  6); //用户自定义学习解码
define('REMOTE_CMD_CTRL2',         7); //发射用户自学习红外编码
define('REMOTE_CMD_TWO_PACKAGE',   8); //按键拆包下发
/***********设备命令字********************/
	/*******系统通知信息*******
	 设备连接通知 DEV_CMD_SYS_ONLINE
		char phyaddr[8]
		char logicaddr[2]

	 设备断开通知 DEV_CMD_SYS_OFFLINE
		char phyaddr[8]
		char logicaddr[2]
	 HICID变化通知 DEV_CMD_SYS_HICID
		int hicid
	 HICID变化确认通知 DEV_CMD_SYS_HICIDCONFIRM
		null
	 通知协调器删除关联设备 DEV_CMD_SYS_RM_DEV_ASSOC
		char phyaddr[8]
	 删除关联设备确认 DEV_CMD_SYS_RM_DEV_ASSOC_CONFIRM
		null
	 zigbee设备入网通知 DEV_CMD_SYS_DEV_JOIN
		null.老协调器在包头的物理地址就是新加设备地址。该命令中的逻辑地址无效
		char mac[8] 新协调器消息体中带mac
	 广播消息 DEV_CMD_HIC_GROUP_CTRL_DEV
		short groupid 组播ID
		char  len     消息长度
		char* cmd     控制字 
	********************************/
define('DEV_CMD_SYS_ONLINE',       200); //
define('DEV_CMD_SYS_OFFLINE',      201); //
define('DEV_CMD_SYS_HICID',        202); //
define('DEV_CMD_SYS_HICIDCONFIRM', 203); //
define('DEV_CMD_SYS_RM_DEV_ASSOC', 204); //
define('DEV_CMD_SYS_RM_DEV_ASSOC_CONFIRM', 205); //
define('DEV_CMD_SYS_DEV_JOIN',     206); //
define('DEV_CMD_HIC_GROUP_CTRL_DEV',    207);  //
define('DEV_CMD_HIC_RESET',             208);  // HIC要求协调器复位
define('DEV_CMD_HIC_RESET_CONFIRM',     209);  //复位回应
define('DEV_CMD_SYS_DEV_JOIN1',         210);  //协调器-->HIC, 有设备加入时报告物理地址。
											   //和DEV_CMD_SYS_DEV_JOIN区别在于，该命令的逻辑地址和物理地址都准确
define('DEV_CMD_MAC_REPORT',            211); //
define('DEV_CMD_SYS_CLOSE_PERMITJOIN',  212); //允许设备加入
define('DEV_CMD_SYS_OPEN_PERMITJOIN',   213); //禁止设备加入
define('DEV_CMD_SYS_PERMITJOIN_CONFIRM',214); //协调器回复HIC确认允许禁止操作
define('DEV_CMD_SYS_KEEPLIVE',          215); //心跳保活

 
  

/*******设备报告的可能信息*******
	 加入请求    DEV_CMD_DEV_ADD
		char name[30]  => NAME
		char sn[30]    => SN
		char ver[3]    => VER
		char power[1]  => ISPOWER
		char mac[8]    => //新终端才需要该字节

	 加入成功确认，同时报告属性列表。安全密钥这时起作用。DEV_CMD_DEV_JOIN
		//报告attrlist
		uchar num
		attr* attr
			uchar index     => ATTRINDEX
			char  name[30]  => NAME
			char  attr[10]  => SYSNAME

	 加入成功确认，同时报告属性列表。安全密钥这时起作用。DEV_CMD_DEV_JOIN2
		//报告attrlist
		uchar sysnamenamelen
		attr* sysname
		uchar attrnum
			uchar attrnamenamelen
			attr* attrname

	 离开请求 DEV_CMD_DEV_LEAVE
		null

	 请求控制 DEV_CMD_DEV_GETCMD
		null

	 状态报告 DEV_CMD_DEV_STATUS
		uchar num
		attr* attr
			uchar   index
			uchar   len
			char   *status
	 附加属性 DEV_CMD_DEV_ATTR_CONF
		uchar index
		uchar len
		attr* attr

	 图标信息 DEV_CMD_DEV_ATTR_ICON
		uchar num
		attr* attr
			uchar   index
			uchar   len
			char   *iconname

	 组设置确认 DEV_CMD_DEV_GROUP_CONF
		ushort groupid

	 MAC请求回应 DEV_CMD_DEV_MAC_CONF
		char mac[8]
	 检查当前HICID DEV_CMD_DEV_CHECKHICID
		char key[8]
	 报告SN信息DEV_CMD_DEV_REPORT_SN
		char key[30]
		*********************************/
define('DEV_CMD_DEV_ADD',        1); //
define('DEV_CMD_DEV_JOIN',       2); //
define('DEV_CMD_DEV_LEAVE',      3); //
define('DEV_CMD_DEV_GETCMD',     4); //
define('DEV_CMD_DEV_STATUS',     5); //
define('DEV_CMD_DEV_ATTR_CONF',  6); //
define('DEV_CMD_DEV_GROUP_CONF', 7); //
define('DEV_CMD_DEV_MAC_CONF',   8); //
define('DEV_CMD_DEV_JOIN1',      9); //
define('DEV_CMD_DEV_ATTR_ICON',  10); //
define('DEV_CMD_DEV_CHECKHICID', 11); //
define('DEV_CMD_DEV_REPORT_NEW_MAC_ACT', 12); //
define('DEV_CMD_DEV_JOIN2',              15); //
define('DEV_CMD_DEV_JOIN3',              16); //
define('DEV_CMD_DEV_REPORT_SN',          17); //


 	/*******向设备下发的可能信息*******
	获取属性列表  DEV_CMD_HIC_GET_ATTRLIST
	加入确认      DEV_CMD_HIC_CONFIRM
		char logicid[4]  => LOGICID
		char chid[4]     => CHID
		char hcid[4]     => HCID
	获取状态      DEV_CMD_HIC_GET_STATUS
		null
	控制下发	  DEV_CMD_HIC_CTRL_DEV
		ushort sleeptime
		uchar num
		attr* attr
			uchar index
			uchar len
			char  *cmd
	确认加入完成  DEV_CMD_HIC_JOIN_CONFIRM
	附加属性确认  DEV_CMD_DEV_ATTR_CONF_RSP
			uchar index
	设置组信息  DEV_CMD_HIC_GROUP_DEV
			ushort group_id   //组ID
			uchar index       //具体要控制的属性索引位域，如0x03表示控制index0,1
	请求MAC地址  DEV_CMD_HIC_GET_MAC
		null
	图标信息确认 DEV_CMD_DEV_ATTR_ICON_RSP
		null
	图标信息确认 DEV_CMD_DEV_CHECKHICID_RSP
		int     hicid
	*************************************/
define('DEV_CMD_HIC_GET_ATTRLIST',   100); //
define('DEV_CMD_HIC_CONFIRM',        101); //
define('DEV_CMD_HIC_GET_STATUS',     102); //
define('DEV_CMD_HIC_CTRL_DEV',       103); //
define('DEV_CMD_HIC_JOIN_CONFIRM',   104); //
define('DEV_CMD_DEV_ATTR_CONF_RSP',  105); //
define('DEV_CMD_HIC_GROUP_DEV',      106); //
define('DEV_CMD_HIC_GET_MAC',        107); //
define('DEV_CMD_DEV_ATTR_ICON_RSP',  108); //
define('DEV_CMD_DEV_CHECKHICID_RSP', 109); //
define('DEV_CMD_DEV_NEW_MAC_ACT_RSP', 110); //

?>