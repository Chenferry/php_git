<?php
/**
*路由器上电通知脚本，启动时执行
*/
if(PHP_SAPI!='cli') die('invalid opration');

$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);

//如果主机有4G模块，增加通讯（拨打电话，发送短信）设备
if( defined('HIC_SYS_HAVE4G') && (true==HIC_SYS_HAVE4G) )
{
	$c 	= new TableSql('homedev','ID');
	include_once('b/homeLang.php');
	$devid = $c->queryValue('ID','NAME=?',array(HOME_SYSDEV_COM));
	if( $devid==false )
	{
		$msg = array();
		$msg['STATUS']  = DEV_STATUS_RUN;
		$msg['PHYDEV']  = PHYDEV_TYPE_SYS;
		$msg['NAME']    = HOME_SYSDEV_COM;
		$msg['ISPOWER'] = DEV_POWER_POWER;	
		$devid = $c->add($msg);
		$c 	= new TableSql('homeattr','ID');
		$attr = array();
		$attr['DEVID']  = $devid;
		$attr['ISR']  = '0';
		$attr['ISC']  = '1';
		$attrid = $c->queryValue('ID','DEVID=? and NAME=?',array($devid,HOME_SYSDEV_SMS));
		if( $attrid==false )
		{
			$attr['ATTRINDEX']  = 0;
			$attr['SYSNAME'] = 'sms';
			$attr['ICON']  	 = 'sms';
			$attr['NAME'] 	 = HOME_SYSDEV_SMS;
			$c->add($attr);
		}
		$attrid1 = $c->queryValue('ID','DEVID=? and NAME=?',array($devid,HOME_SYSDEV_PHONE));
		if( $attrid1==false )
		{
			$attr['ATTRINDEX']  = 1;
			$attr['SYSNAME'] = 'phone';
			$attr['ICON']  	 = 'phone';
			$attr['NAME'] 	 = HOME_SYSDEV_PHONE;
			$c->add($attr);
		}
	}
}


//获取路由器序列号，否则不初始化设备
$GLOBALS['dstpSoap']->setModule('local','sn');
$sn = $GLOBALS['dstpSoap']->getSNInfo();
if( false != $sn )
{
	//恢复出厂设置后，需要重新更新HICCfg文件信息
	$GLOBALS['dstpSoap']->setModule('local','sn');
	$GLOBALS['dstpSoap']->setHICCfg($sn);

	`rm -f /etc/init.d/telnet`;
	//设置登录密码
	`echo "root:q#$%123hic" | chpasswd > /dev/null`;
}
else
{
	`/etc/init.d/telnet start`;
}

//设置路由器检测手机是否离线的时间间隔
//经过试验：一般安卓手机在60秒内都能处理
//iphone直到IOS9使用120是可以的
//一加手机需要设置到130
//这个值不能很长，否则当人离家时，系统迟迟不能发现手机已经离线，导致很多功能不好使
//设置的原则，保证主流手机是可以使用的，但如果一些小众手机需要的时间很长，则不予支持
`iwpriv ra0 set IdleTimeout=130`;

//
$GLOBALS['dstpSoap']->setModule('home','client');
$GLOBALS['dstpSoap']->initClientsACL();

//分机不执行initClientsACL，所以这儿调用置初始访问权限
if( APP_FJ == HIC_APP )
{
	$GLOBALS['dstpSoap']->setModule('local','firewall');
	$GLOBALS['dstpSoap']->rejectAll();
}

if( APP_FJ == HIC_APP )
{
	exit();
}

//给所有透传通道下发关闭添加命令
$c = new TableSql('sysinfo');
$r = $c->queryValue('DISNETJOIN');
$cmd = intval($r)?DEV_CMD_SYS_CLOSE_PERMITJOIN:DEV_CMD_SYS_OPEN_PERMITJOIN;
$GLOBALS['dstpSoap']->setModule('setting','setting');
$GLOBALS['dstpSoap']->openDevSearchMap($cmd);


//分机无需后续处理
//查询更新天气预报信息
$GLOBALS['dstpSoap']->setModule('home','envend');
$GLOBALS['dstpSoap']->reportDevInfo();
//设备状态初始化。以免智慧家庭的查询条件误触

?>