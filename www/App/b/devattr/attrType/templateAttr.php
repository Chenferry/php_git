<?php
/*本文件是模版文件。新加属性种类则增加一个文件*/
/* 开关/行程开关(调色)/颜色拾取器/枚举/数字/浮点/字符/日期/时间/红外遥控器/流 */
/* 每种设备属性，都必须定义一个类来实现该设备属性的相关特性信息以及用户接口 */
/* 前缀名称和home_attrtype中信息一致 */
class templateAttrType
{
	/* 设备属性的标准定义
	 * r: 是否会上报状态。还是只接受控制。默认可上报 
	 * s: 上报状态是否同步服务器长期保存。0:不上报;1:上报状态；2：上报并统计 
	 * c: 是否可控制，还是只上报信息。默认只上报信息 
	 * vf:上报的属性值的存储信息。TABLE_FIELD_INT... 
	 * cf:下发控制命令的存储信息。TABLE_FIELD_INT... 
	 * cf:下发控制命令的存储信息。TABLE_FIELD_INT... 
	 */
	static $cfg = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT);

	/* 家庭状态的可能显示方式 */
	/* 开关:switch
			value:0/1
			other:option数组
	 * 滑动杆:adjust
			value:int
			array(min,max)
	 * 告警:alarm
			value:0/1
			other:
	 * 枚举/文字:enum
			value:枚举值
			other:可选的枚举数组
	 * 数字+单位:num
			value:数字
			other:单位
	 * 文字+单位:char
			value:文字
			other:单位
	 * 调色:color
			value:array(r,g,b)
			other:
	 * 家人手机:client
			value:0/1
			other:
			note:
	 * 非家人手机:mobile
			value:array('kr'=>array(),'hmd'=>array())
			other:
			note:
	 * 情景开关:group
			value:
			other:
			note:
	 * 摄像头:rtsp
			value:url
			other:
			note:如果进了详细页面，直接点击链接打开
	 * 待接入:dev
			value:
			other:
			note:
	 * 中继路由器:wds
			value:
			other:
			note:
	 * 调色:color
			value:array('m'=>0,'r'=>,'g'=>,'b'=>)
				m=0:表示用户自定义rgb。如果关灯，m=0，rgb全0
			value:array('m'=>,'s'=>)
				m=x:模式值，非0。rate以0.5为间隔，最小0.5，最大10
			other:
	 ******下面这些在状态简单显示中不显示，只能进入详细页面******
	 * 红外学习:remote
			value:
			other:array(dev) //可学习的设备列表
	 * 空调遥控:air
			value:array(key,open mode,temp,wind,winddir)//按键,开关，模式(制冷/制热/通风)，温度，风速，风向
			other:
	 * 电视遥控:tv/iptv/dvd/fan/cleaner
			value:
			other:
	 * 机顶盒遥控:iptv
	 * 自定义遥控:remote
	 * 摄像头:camer
	 * 窗帘开关/煤气开关/插座开关
	 */
	static $page = ''; 

	//命令格式char switch
	static $packfmt   = 'c';
	static $unpackfmt = 'c';

	//需要根据值直接判断是否告警;false表示状态值为假时告警，true表示状态值为真时告警 
	static $alarm = false;

	//把前台值转为数据库信息
	static function getDBInfo($value,$attrid=NULL)
	{
		return NULL;
	}
	
	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$attrid=NULL)
	{
		return array();
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		return NULL;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		return NULL;
	}

	//根据状态信息判断是否告警状态
	static function getAlarmInfo($value,$attrid=NULL)
	{
		return NULL;
	}
	
	//辅助前台显示的信息
	//比如pm2.5，得到的是数值，但需要根据数值转换为一个表示等级的枚举值
	//比如可能是一个多维数组，指示空调可选模式/温度列表等
	static function getOtherInfo($value,$attrid=NULL)
	{
	}
	
	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'dz'=>array(), 'lc'=>array(), 'qt'=>array() );
	
	//语音识别输入处理函数
	static function yuyin($value,$attrid=NULL)
	{
		//
	}
	
	//接受语音查询，返回设备当前状态信息
	static function yuyinStatus($yuyin,$attrid=NULL)
	{
		
	}

}

 

?>