<?php
/* 开关/行程开关(调光)/颜色拾取器/枚举/数字/浮点/字符/日期/时间/红外遥控器/流 */

//sysname指示的是生活中的属性，比如温度，湿度等
//但像温度，湿度这类，对用户来说可能是不同的属性。但它们的显示方式可能是一样的。只在某些细节有区分
include_once('b/homeLang.php');
class attrType
{
	/* 支持的sysname */
	/* time:时间信息
	 * dc:电池
	 * kg:开关
	 * //cz:插座。无需这个，和开关一致
	 * dy:计量芯片的电压
	 * dl:计量芯片的电流
	 * wd:温度
	 * sd:湿度
	 * gd:光度
	 * tq:天气
	 * rq:红外入侵探测
	 * mq:煤气告警
	 * pm:pm2.5检测
	 * jq:甲醛检测
	 * cl:窗帘
	 * mc:门磁
	 * ms:门锁
	 * ml:门铃
	 * kt:空调遥控器
	 * ds:电视遥控器
	 * sx:摄像头
	 */
	static $attrClass;
	static function setAttrType($attrType)
	{
		//这儿要判断是否有存在指定的class，如果没有，直接返回
		$attrType = trim($attrType);
		include_once(dirname(__FILE__).'/attrType/'.$attrType.'Attr.php');
		self::$attrClass = $attrType.'AttrType';
		return true;
	}
	
	//把页面传来的设备控制字转换为数据库保存格式
	static function getPage()
	{
		$class = self::$attrClass;
		if ( method_exists(self::$attrClass, 'getPage') )
		{
			return $class::getPage($attr,$attrid);
		}
		if ( property_exists($class, 'page') )
		{
			return $class::$page;
		}
		return NULL;	
	}
	static function getCfg()
	{
		$class = self::$attrClass;
		if ( property_exists($class, 'cfg') )
		{
			return $class::$cfg;
		}
		return array();		
	}
	static function getDel()
	{
		$class = self::$attrClass;
		if ( property_exists($class, 'del') )
		{
			return $class::$del;
		}
		return NULL;
	}
	//获取attrtype的配置信息
	static function getAttrTypeCfg($sysname)
	{
		self::setAttrType($sysname);
		return self::getCfg();
	}	
	//获取attr状态信息的存储字段
	static function getAttrStatusDBField($sysname)
	{
		self::setAttrType($sysname);
		$cfg = self::getCfg();
		switch( $cfg['vf'] )
		{
			case TABLE_FIELD_INT:
			case TABLE_FIELD_ENUM:
			case TABLE_FIELD_DATE:
			case TABLE_FIELD_TIME:
				$db = 'ATTRINT';
				break;
			case TABLE_FIELD_FLOAT:
				$db = 'ATTRFLOAT';
				break;
			case TABLE_FIELD_ENUM_CHAR:
			case TABLE_FIELD_CHAR:
			case TABLE_FIELD_TEXT:
			default:	
				$db = 'ATTRSTR';
				break;
		}
		return $db;
	}
	
	/**********************************************/
	//新增属性通知
	static function addAttrNotice($attrid=NULL)
	{
		if ( method_exists(self::$attrClass, 'addAttrNotice') )
		{
			$class = self::$attrClass;
			return $class::addAttrNotice($attrid);
		}
		return true;
	}

	//删除属性通知
	static function delAttrNotice($attrid,$devid,$attrindex)
	{
		if ( method_exists(self::$attrClass, 'delAttrNotice') )
		{
			$class = self::$attrClass;
			return $class::delAttrNotice($attrid,$devid,$attrindex);
		}
		return true;
	}

	//替换属性通知
	static function replaceAttrNotice($oldid,$newid)
	{
		if ( method_exists(self::$attrClass, 'replaceAttrNotice') )
		{
			$class = self::$attrClass;
			return $class::replaceAttrNotice($oldid,$newid);
		}
		return true;
	}	
	
	
	static function getDBInfo($value,$attrid=NULL)
	{
		if ( method_exists(self::$attrClass, 'getDBInfo') )
		{
			$class = self::$attrClass;
			return $class::getDBInfo($value,$attrid);
		}
		if( is_array($value) )
		{
			$value = serialize($value);
		}
		return $value;
	}
	
	//把数据库读出的控制字转为页面所需的信息
	static function getViewInfo($value,$attrid=NULL)
	{
		if ( method_exists(self::$attrClass, 'getViewInfo') )
		{
			$class = self::$attrClass;
			return $class::getViewInfo($value,$attrid);
		}
		return $value;
	}
	//
	static function getOtherInfo($value,$attrid=NULL)
	{
		if ( method_exists(self::$attrClass, 'getOtherInfo') )
		{
			$class = self::$attrClass;
			return $class::getOtherInfo($value,$attrid);
		}
		return $value;
	}

	//把数据库读出的控制字转为页面所需的信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$class = self::$attrClass;
		if ( method_exists($class, 'getCMDInfo') )
		{
			$value = $class::getCMDInfo($value,$attrid);
		}
		if ( property_exists($class, 'packfmt') )
		{
			//字串的使用a30这样的格式串，a带字串长度
			//单字节的使用c
			//浮点数的使用f
			//short的使用n
			//int的使用N
			return pack($class::$packfmt, $value);
		}

		return $value;
	}
	static function getStatusInfo($value,$attrid=NULL)
	{
		$class = self::$attrClass;
		if ( property_exists($class, 'unpackfmt') )
		{
			//字串的使用a30这样的格式串，a带字串长度
			//单字节的使用c
			//浮点数的使用f
			//short的使用n
			//int的使用N
			$v = unpack($class::$unpackfmt, $value);
			$value = $v[1];//这儿不能直接返回，可能需要后续函数继续处理
		}
		if ( method_exists($class, 'getStatusInfo') )
		{
			return $class::getStatusInfo($value,$attrid);
		}
		return $value;
	}
	static function getDetail($attr,$attrid=NULL)
	{
		$class = self::$attrClass;
		if ( method_exists($class, 'getDetail') )
		{
			return $class::getDetail($attr,$attrid);
		}
		return array();
	}
	//解析属性附加信息
	static function parseAdditonInfo($value,$attrid)
	{
		if( NULL == $value )
		{
			return false;
		}
		if ( method_exists(self::$attrClass, 'parseAdditonInfo') )
		{
			$class = self::$attrClass;
			return $class::parseAdditonInfo($value,$attrid);
		}
		return false;
	}
	
	//根据状态信息判断是否告警状态
	static function getAlarmInfo($value,$attrid=NULL)
	{
		$class = self::$attrClass;
		if ( property_exists($class, 'alarm') )
		{
			if( $class::$alarm ) 
				return intval($value)?DEV_ALARM_ALARM:DEV_ALARM_CLEAN;
			return intval($value)?DEV_ALARM_CLEAN:DEV_ALARM_ALARM;
		}
		if ( method_exists($class, 'getAlarmInfo') )
		{
			return $class::getAlarmInfo($value,$attrid);
		}
		return DEV_ALARM_IGNORE; //无需判断告警状态
	}	

	//传递给前台的相关信息值
	static function getAttrShow(&$attr,$attrid=NULL,$getDetail=false)
	{
		if( NULL == $attr['SYSNAME'] )
		{
			return array('type'=>'group', 'value'=>0, 'other'=>NULL,'detail'=>false);
		}
		self::setAttrType( $attr['SYSNAME'] );
		$class = &self::$attrClass;
		$cfg  = &$class::$cfg;
		if ( method_exists(self::$attrClass, 'getPage') )
		{
			$page = $class::getPage($attr,$attrid);
		}
		else
		{
			$page = &$class::$page;
		}

		//根据值类型取得相应的信息
		$value = NULL;
		$other = NULL;
		switch( $attr['VF'] )
		{
			case TABLE_FIELD_INT:
			case TABLE_FIELD_ENUM:
			case TABLE_FIELD_DATE:
			case TABLE_FIELD_NAME:
			case TABLE_FIELD_TIME:
				$value = intval($attr['ATTRINT']);
				break;
			case TABLE_FIELD_FLOAT:
				$value = floatval($attr['ATTRFLOAT']);
				break;
			case TABLE_FIELD_CHAR:
			case TABLE_FIELD_TEXT:
			case TABLE_FIELD_ENUM_CHAR:
			case TABLE_FIELD_NAMELIST:
			case TABLE_FIELD_NAMESTR:
			case TABLE_FIELD_IDLIST:
				$value = $attr['ATTRSTR'];
				break;
			case TABLE_FIELD_USERDEF:
			case TABLE_FIELD_VIDEO:
			case TABLE_FIELD_AUDIO:
			case TABLE_FIELD_STREAM:
			default:
				break;
		}
		
		//如果需要对信息进行显示转换，则需要调用
		if ( method_exists(self::$attrClass, 'getViewInfo') )
		{
			$value = $class::getViewInfo($value,$attrid);
		}
		if ( method_exists(self::$attrClass, 'getOtherInfo') )
		{
			$other = $class::getOtherInfo($value,$attrid);
		}

		$detail = false;
		if ( method_exists(self::$attrClass, 'getDetail') )
		{
			$detail = true;
			if($getDetail)
			{
				$detail = $class::getDetail($value,$attrid);
			}
		}
		
		//page:显示页面，other：附加信息，如单位，或者报警等级。由各页面解释
		return array('type'=>$page, 'value'=>$value, 'other'=>$other,'detail'=>$detail);
	}

	//
	static function yuyin($value,$attrid=NULL)
	{
		if ( method_exists(self::$attrClass, 'yuyin') )
		{
			$class = self::$attrClass;
			return $class::yuyin($value,$attrid);
		}
		return false;
	}
	
}

 

?>