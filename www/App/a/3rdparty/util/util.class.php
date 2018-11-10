<?php
//定义一堆公共函数

class UTIL  
{   
	//和时间值相关的基准年月日

	//返回当前日期的一个时间值
	static function  getCurTime($t=NULL)
	{  
		if( NULL==$t )
		{
			$t=time();
		}
		return intval((intval($t)+date('Z'))/SEC_EVERY_DAY);

		/*
		$curDate = getdate ();
		$from=mktime(0,0,0,$curDate["mon"],$curDate["mday"],$curDate["year"]);  
		$to=mktime(0,0,0,$month2,$day2,$year2);  
		return ($from - $to);
		*/
		//mktime得到的是一个和秒数相关的数值，转换为天
		//$timeArray = explode ("-", date("Y-m-d"));
		//return UTIL::getSpecTime($timeArray[0],$timeArray[1],$timeArray[2]);

		//$i =  gmmktime (0,0,0);
		//return $i/SEC_EVERY_DAY;
	}
	
	//计算到某个指定时间(秒)的月份数
	static function  getCurMonth($d)
	{  
		$y = idate('Y', $d);
		$m = idate('m', $d);
		return $y*12+$m;
	}

	//返回当前日期，格式"yyyy-mm-dd"
	static function getCurDate($fmt = "Y-m-d")
	{ 
		return date($fmt);
	}
	
	static function getSpecTime($year,$mon,$day) 
	{ 
		$i =   gmmktime(0,0,0,$mon,$day,$year); 
		
		return $i/SEC_EVERY_DAY;
	}
	
	//根据指定的时间值返回日期
	static function getSpecDate($timeValue,$fmt = "Y-m-d")
	{  
		$timeValue  = $timeValue * SEC_EVERY_DAY ;
		return  date($fmt, $timeValue);
	}

	static function getSpecTimeByStr($timeStr)
	{ 
		//判断是否符合yyyy-mm-dd格式
		if ( 0 == preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/",$timeStr) )
		{ 
			//return UTIL::getCurTime();
			return -1;
		}
		
		$timeArray = explode ("-",$timeStr);
		return UTIL::getSpecTime($timeArray[0],$timeArray[1],$timeArray[2]);
	}

	static function isPhone($phone) 
	{
		//移动：134、135、136、137、138、139、150、151、152、157、158、159、182、183、184、187、188、178(4G)、147(上网卡)；
		//联通：130、131、132、155、156、185、186、176(4G)、145(上网卡)；
		//电信：133、153、180、181、189 、177(4G)；
		//卫星通信：1349
		//虚拟运营商：170,171
		
		//1[]]
		if (!is_numeric($phone)) 
		{
			return false;
		}
		return preg_match('#^1[3,4,5,7,8][\d]{9}$#', $phone) ? true : false;
	}
	static function isMail($mail)
	{
		return preg_match("/([\w\-]+\@[\w\-]+\.[\w\-]+)/",$mail);
	}	
	
	//判断是否移动访问
	static function isMobile()
	{ 
		if ( isset($_SESSION['isMobile']) )
		{
			return $_SESSION['isMobile'];
		}
		// 如果有HTTP_X_WAP_PROFILE则一定是移动设备
		if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
		{
			$_SESSION['isMobile'] = true;
			return $_SESSION['isMobile'];
		} 

		// 脑残法，判断手机发送的客户端标志,兼容性有待提高
		if (isset ($_SERVER['HTTP_USER_AGENT']))
		{
			$clientkeywords = array (
				'android',
				'phone',
				'pad',
				'wap',
				'mobile',
				); 
			// 从HTTP_USER_AGENT中查找手机浏览器的关键字
			$agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			foreach( $clientkeywords as &$w )
			{
				if( false !== strpos($agent,$w) )
				{
					$_SESSION['isMobile'] = true;
					return $_SESSION['isMobile'];
				}
			}
		} 
		// 协议法，因为有可能不准确，放到最后判断
		if (isset ($_SERVER['HTTP_ACCEPT']))
		{ 
			// 如果只支持wml并且不支持html那一定是移动设备
			// 如果支持wml和html但是wml在html之前则是移动设备
			if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))))
			{
				$_SESSION['isMobile'] = true;
				return $_SESSION['isMobile'];
			} 
		} 
		$_SESSION['isMobile'] = false;
		return $_SESSION['isMobile'];
	} 

	static function sajaxDecodeArgs(&$args)
	{
		if ( !is_array( $args ) )
		{
			$args = urldecode($args);
			return;
		}
		foreach( $args as &$a )
		{
			if ( is_array( $a ) )
			{
				self::sajaxDecodeArgs($a);
				continue;
			}
			$a = urldecode($a);
		}
		return;
	}
	


	static function startSajax($export,$debug=0)
	{
		$mode = "";
		
		if (! empty($_GET["rs"])) 
			$mode = "get";
		
		if (!empty($_POST["rs"]))
			$mode = "post";
			
		if (empty($mode)) 
			return;

		if ($mode == "get") {
			//modi by bizi
			if (!$debug)
			{
				// Bust cache in the head
				header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");    // Date in the past
				header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
				// always modified
				header ("Cache-Control: no-cache, must-revalidate");  // HTTP/1.1
				header ("Pragma: no-cache");                          // HTTP/1.0
			}
			$rsargs = &$_GET;
		}
		else {
			$rsargs = &$_POST;
		}
		
		$func_name = $rsargs["rs"];
		if (! in_array($func_name, $export))
		{
			echo "-:$func_name not callable";
			exit;
		}

		$args = array();
		if (! empty($rsargs["rsargs"]))
		{
			$args = &$rsargs["rsargs"];
		}
		self::sajaxDecodeArgs($args);
		
		$result = call_user_func_array($func_name, $args);
		if (!$debug)
		{
			//echo "+:";
			//echo "var res = " . trim(sajax_get_js_repr($result)) . "; res;";
			$result = json_encode($result,JSON_PARTIAL_OUTPUT_ON_ERROR);
		}
		echo $result;
		exit;
	}


	//二维数组去重
	static function arrayUnique($array2d)
	{
		foreach ($array2d as $val) {
			//降维,也可以用implode,将一维数组转换为用逗号连接的字符串
			$val     = join(",", $val);
			$temp[]  = $val;
		}

		//去掉重复的字符串,也就是重复的一维数组
		$temp = array_unique($temp);
		$result  = array();
		foreach ($temp as $key => $value) {
			//再将拆开的数组重新组装
			$arr = explode(',', $value);
			$tempArr['SUBHOST'] = $arr[0];
			$tempArr['PHYDEV']  = $arr[1];

			$result[] = $tempArr;
		}

		return $result;
	}
		

	//通用二维数组去重
	static function arrayUniqueCommon($array2d)
	{
		foreach ($array2d as $val) {
			//降维,也可以用implode,将一维数组转换为用逗号连接的字符串
			$val     = join(",", $val);
			$temp[]  = $val;
		}

		//去掉重复的字符串,也就是重复的一维数组
		$temp = array_unique($temp);
		$result  = array();
		foreach ($temp as $key => $value) {
			//再将拆开的数组重新组装
			$arr = explode(',', $value);
			$result[] = $arr;
		}

		return $result;
	}
	
} 

?>
