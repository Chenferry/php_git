<?php
	define('HIC_NAME',          'Smart Home Information Center'); 

	define('LOGIN_ERR_VCODE',   'incorrect verification code');    
	define('LOGIN_OVERMUCH_VCODE',  'incorrect verification code entered for too many times, please retry it later!'); 
	
	//HIC��ش���
	define('HIC_ERR_NULL',      'information center unregistered');  
	define('HIC_ERR_INIT',      'information center register failed:%s'); 
	define('HIC_ERR_INITNULL',  'information center reigster error');  
	define('HIC_ERR_SN',        'Device activation error, please contact your dealer');  
	define('HIC_ERR_RIGHT',     'No permission to restore the specified data');
	define('HIC_ERR_DELETE1',   'Delete failed��'); 
	define('HIC_ERR_ADMIN1',    'Currently you only have one administrator and there are other users, so you cannot delete the host.');
	//ע����ش���
	define('SN_ERR_CFG',        'Can��t find relevant platform batch information'); 
	define('SN_ERR_MANY',       'Too many activates');  
	define('SN_ERR_PHYID',      'Registered mac address');  
	define('SN_ERR_ERROR',      'Activation failed');  

	//Ȩ�޴���
	define('USER_ACCESS_ERR',   'You do not have permission to operate');

	define('HOME_SYSDEV_ALARM', 'notification alert'); 
	//ϵͳ��׼�澯��ʾSystem standard alert 
	define('HOME_DEV_ALARM_TITLE',      'alarm'); 
	define('HOME_DEV_CAMER_SCREEN',     'Your camera has screenshot'); 
	define('HOME_DEV_ALARM_DESCRIPT',   '%sthere is an alarm��');  
    define('ALARM_ROUTER_OFFLINE',      'Alarm:information center offline'); 
    define('ALARM_ROUTER_OFFLINE_DETAIL',      'information center %s offline');   
	$GLOBALS['gAlarmInfo'] = array(
		DEV_ALARM_IGNORE  =>   '',
		DEV_ALARM_CLEAN   =>   'clear',  
		DEV_ALARM_ALARM   =>   'alarm',  
		DEV_ALARM_POWER   =>   'low batery', 
		DEV_ALARM_OFFLINE =>   'offline', 
	);	
	//SOAP�����ڲ����� call internal error
	define('SOAP_ERR_CURL_COMMON_HIC',  'Failed to send request to information center'); 
	define('SOAP_ERR_CURL_COMMON_CLOUD','Failed to connect to server, please check your network'); 
	define('SOAP_ERR_HIC_MSF',          'information center program error(%s,%s,%s)'); 
	
	//�û�������ʾ user error reminder
	define('USER_PSW_ERR',      'Wrong user name or password!'); 
	define('USER_USER_NULL',    'User name not exists!'); 
	define('USER_USER_TOOMNAY', 'Max. 8 user bindable');  
	define('USER_USER_EXIST',   'Existed user name!');  
	define('USER_USER_FORMATR', 'User can not use phone number or email address'); 
	define('USER_REG_ERR',      'User register internal error, please retry it later!'); 
	define('USER_HASBIND_ERR',  'User is already binded!'); 
	define('USER_PSW_NULL',     'Please enter password!'); 
	define('USER_OLDPSW_ERR',   'Original password is wrong!'); 
	define('USER_BIND_HICNULL', 'You do not have an information center'); 
	define('USER_UNBIND_CUR',   'Unable to unbind current user (please go to management page to delete it if you need)!');  
   
	//����ƻ�������Ϣ   tasking planning error:
	define('PLANSET_ERR_CYC',   'wrong periodic type');  
	define('PLANSET_ERR_AHEAD', 'wrong advance notice time'); 
	define('PLANSET_ERR_HDAY',  'wrong holiday setting'); 
	define('PLANSET_ERR_TIME',  'Please enter the correct execution time in the format HH: MM');  

?>