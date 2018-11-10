<?php
	define('HIC_NAME',          '智能信息中心'); 

	define('LOGIN_ERR_VCODE',   '验证码输入错误'); 
	define('LOGIN_OVERMUCH_VCODE',  '验证码输入错误次数过多，请稍后再试！'); 
	
	//HIC相关错误
	define('HIC_ERR_NULL',      '信息中心未注册'); 
	define('HIC_ERR_INIT',      '信息中心注册失败:%s'); 
	define('HIC_ERR_INITNULL',  '信息中心注册参数错误'); 
	define('HIC_ERR_SN',        '设备激活信息错误，请联系经销商'); 
	define('HIC_ERR_RIGHT',     '无权恢复指定信息中心数据'); 
	define('HIC_ERR_DELETE1',   '删除失败！');
	define('HIC_ERR_ADMIN1',    '当前只有您一名管理员，且有其他用户，所以您不能删除主机！');
	
	//注册相关错误
	define('SN_ERR_CFG',        '找不到相关平台批次信息'); 
	define('SN_ERR_MANY',       '激活数量过多'); 
	define('SN_ERR_PHYID',      'MAC地址已经注册过'); 
	define('SN_ERR_ERROR',      '激活失败'); 

	define('HOME_SYSDEV_ALARM', '通知告警'); 
	
	//权限错误
	define('USER_ACCESS_ERR',   '您没有权限操作');
	
	//系统标准告警提示
	define('HOME_DEV_ALARM_TITLE',      '告警');
	define('HOME_DEV_CAMER_SCREEN',     '你的摄像头有截图');
	define('HOME_DEV_ALARM_DESCRIPT',   '%s有告警：%s');
    define('ALARM_ROUTER_OFFLINE',      '告警:信息中心离线');
    define('ALARM_ROUTER_OFFLINE_DETAIL',      '信息中心%s离线');   
	$GLOBALS['gAlarmInfo'] = array(
		DEV_ALARM_IGNORE  =>   '',
		DEV_ALARM_CLEAN   =>   '清除',
		DEV_ALARM_ALARM   =>   '告警',
		DEV_ALARM_POWER   =>   '电量不足',
		DEV_ALARM_OFFLINE =>   '离线',
	);	
	
	//SOAP调用内部错误
	define('SOAP_ERR_CURL_COMMON_HIC',  '向信息中心发送请求失败');
	define('SOAP_ERR_CURL_COMMON_CLOUD','连接服务器失败，请检查能否上网');
	define('SOAP_ERR_HIC_MSF',          '信息中心程序错误(%s,%s,%s)');
	
	//用户错误提示
	define('USER_PSW_ERR',      '用户名或密码错'); 
	define('USER_USER_NULL',    '用户名不存在'); 
	define('USER_USER_TOOMNAY', '信息中心可绑定用户数最多8个'); 
	define('USER_USER_EXIST',   '用户名已经存在'); 
	define('USER_USER_FORMATR', '用户名不能使用手机号码或者邮件地址'); 
	define('USER_REG_ERR',      '用户注册内部错误，请稍候再试'); 
	define('USER_HASBIND_ERR',  '用户已经绑定'); 
	define('USER_PSW_NULL',     '请输入密码'); 
	define('USER_OLDPSW_ERR',   '原密码错误'); 
	define('USER_BIND_HICNULL', '没有已绑定的信息中心'); 
	define('USER_UNBIND_CUR',   '不能解绑当前用户(如需解绑请到信息中心管理页面删除)');

	//任务计划错误信息
	define('PLANSET_ERR_CYC',   '错误的周期类型');
	define('PLANSET_ERR_AHEAD', '错误的提前通知时间');
	define('PLANSET_ERR_HDAY',  '错误的假期处理方式');
	define('PLANSET_ERR_TIME',  '请输入正确的执行时间，格式为HH:MM');


?>
