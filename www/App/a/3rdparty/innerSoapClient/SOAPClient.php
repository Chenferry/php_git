<?php
class innerSOAP
{
	private $errMsg;
	private $hicid;
	private $callUrl;
	private $totalArray;
	private $host;
	private $calltime = 30;

	function __construct($moduleName = NULL,$serviceName=NULL)
	{
		$this->totalArray = array();
		$this->totalArray['curUserID']   = @$GLOBALS['curUserID'];
		$this->totalArray['curUserName'] = @$GLOBALS['curUserName'];//系统在调用计划任务的时候，用户名传不过来，暂时这样处理
		
		$this->setModule($moduleName, $serviceName);	
	}
	
	function getErr()
	{
		return $this->errMsg;
	}

	//废弃不用。但源代码可能有留存。所以函数不删除
	function setDebug($isTrue = true)
	{
		return $this;
	}
	
	//强行指定请求所用的域名.指定后，默认只使用一次
	function setHost($host=NULL)
	{
		$this->host = $host;
	}
	
	function setCallTime($time=30)
	{
		$this->calltime = $time;
	}
	
	//根据模块名确定其所在的abc位置
	//现在要求所有模块目录名不能重复。根据是否存在文件夹来判断
	function getModuleAddr($m)
	{
		if ( is_dir($GLOBALS['DstpDir']['DocDir'].'/a/'.$m) )
		{
			return 'a';
		}
		switch( HIC_LOCAL )
		{
			case 'b':
			case 'i':
				if ( is_dir($GLOBALS['DstpDir']['DocDir'].'/b/'.$m) )
				{
					return 'b';
				}
				return 'c';
			case 'c':
			default:
				if ( is_dir($GLOBALS['DstpDir']['DocDir'].'/c/'.$m) )
				{
					return 'c';
				}
				return 'b';
		}
		//??die
		return 'a';

		
		//这儿使用cache现在会有问题
		//在多个服务器情况下，cache配置可能需要读取数据库
		//而初始化数据库之前，就需要调用这个函数
		$mList = Cache::get1('moduleAddr');
		if ( !isset( $mList[$m] ) )
		{
			if ( false === $mList ) 
			{
				$mList = array();
			}
			if ( is_dir($GLOBALS['DstpDir']['DocDir'].'/a/'.$m) )
			{
				$mList[$m] = 'a';
			}
			else if ( is_dir($GLOBALS['DstpDir']['DocDir'].'/'.HIC_LOCAL.'/'.$m) )
			{
				$mList[$m] = HIC_LOCAL;
			}
			else
			{
				$mList[$m] = ('b'==HIC_LOCAL) ?  'c':'b';
			}
			Cache::set1('moduleAddr', $mList);	
		}
		return $mList[$m];
	}

   	/*********************************************** 
   	 * function:修改SOAP客户端所要请求的服务位置
   	 * input :$moduleName:服务所在的目录名
   	          $serviceName:服务名字，如果没设置则默认和目录同名。对服务的要求，在$moduleName目录下，必须存在一个文件$serviceName.InterFace.php
   	          文件中必须定义一个类$serviceNameInterface
   	 
   	 * output:
   	 * return:
   	 * other :
   	 ***********************************************/
	function setModule($moduleName=NULL, $serviceName=NULL,$hicid=NULL)
	{
		if ( NULL == $moduleName )
		{
			return ;
		}

		if ( NULL == $serviceName )
		{
			$serviceName = strtolower($moduleName);
		}
		$this->totalArray['moduleName']  = $moduleName;
		$this->totalArray['serviceName'] = $serviceName;
		if( NULL != $this->host )
		{
			$this->callUrl = 'http://'.$this->host.'/App/a/common/SOAPService.php';
			$this->hicid   = $hicid;
			$this->host    = NULL; //指定域名最多只使用一次
			return $this;
		}
		
		$this->callUrl = NULL;

		//在单品访问服务器中，直接返回，无需远程调用
		if( 'i' == HIC_LOCAL )
		{
			return $this;
		}
		
		//根据module位置判断是否本地。
		$ma = self::getModuleAddr($moduleName);
		if ( 'a' == $ma || HIC_LOCAL == $ma )
		{
			return $this;
		}
		if( NULL != $hicid )
		{
			//先鉴权。确保当前用户是可操作指定的设备
			$c      = new TableSql('hic_hicbind');
			$idList = $c->queryValue('HICID','HICID=? AND USERID=?',array($hicid,$_SESSION['loginUserID']));
			if ( NULL == $idList )
			{
				return $this;
			}
		}
		$this->hicid = $hicid;
		$location = HICInfo::getPeerHost($hicid);
		//上一行调用了setModule方法，需要恢复模块和服务名。
		if( 'b' == HIC_LOCAL )
		{
			$this->callUrl = 'https://'.$location.'/App/a/common/SOAPService.php';
		}
		else
		{
			$this->callUrl = 'http://'.$location.'/App/a/common/SOAPService.php';
		}
		$this->totalArray['moduleName']  = $moduleName;
		$this->totalArray['serviceName'] = $serviceName;
		return $this;
	}

	//提交post请求
	private function callSoapByCurl(&$url,&$data)
	{	
		$ch = curl_init();
		//设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,30);  //定义超时5秒钟  
		// POST数据
		curl_setopt($ch, CURLOPT_POST, 1);
		// 把post的变量加上
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));    //所需传的数组用http_bulid_query()函数处理一下，就ok了
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //这个是重点,规避ssl的证书检查。
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 跳过host验证
		
		//c发往信息中心的调用有可能需要通过中转代理。这儿头部设置路由器目的信息
		//方便中转服务器能迅速获得对应的路由器目标连接	
		if( 'c' == HIC_LOCAL ) 
		{
			$hicid = $data['curHICID'];
			$header = array("smarthic:$hicid");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
		}
		
		//执行并获取url地址的内容
		$out = curl_exec($ch);
		$errorCode = curl_errno($ch);
		//释放curl句柄
		curl_close($ch);
		if(0 !== $errorCode)
		{
			include_once('a/commonLang.php');
			if( 'c' == HIC_LOCAL ) 
			{
				return $this->soapFault(false, SOAP_ERR_CURL_COMMON_CLOUD);
			}
			return $this->soapFault(false, SOAP_ERR_CURL_COMMON_HIC);
		}
		if ( NULL == $out )
		{
			return NULL;
		}
		$r = unserialize($out);
		//无法反系列化和返回值false无法分开，取消这个判断
		//if ( FALSE === $r )
		//{
		//	return $this->soapFault(false, $out);
		//}
		if( is_a($r,'hicFault'))
		{
			return $this->soapFault($r->faultcode, $r->faultstring); 
		}
		return $r;
	}	
	
	function __call($func,$para)
	{
		$this->errMsg = NULL;
		$this->totalArray['curUserID']   = @$GLOBALS['curUserID'];
		$this->totalArray['curUserName'] = @$GLOBALS['curUserName'];

		$this->totalArray['methodName']  = $func;
		$this->totalArray['methodArray'] = $para;

		if ( NULL == $this->callUrl )
		{
			//保存调用模块名，避免几次调用后，这个模块名就被改了。以前是SOAP，改了不影响，现在不行
			$mn = $this->totalArray['moduleName']; 
			$sn = $this->totalArray['serviceName'];
			
			$r  = $this->useMethod($mn,$sn, $func, $para );
			$this->totalArray['moduleName']  = $mn; 
			$this->totalArray['serviceName'] = $sn;
			$this->callUrl = NULL;
			if( is_a($r,'hicFault'))
			{
				return $this->soapFault($r->faultcode, $r->faultstring); 
			}
			return $r;
		}
		
		$this->totalArray['curHICID']    = HICInfo::getHICID($this->hicid);//查找当前设备
		$this->totalArray['curSecure']   = HICInfo::getSecure($this->hicid);//查找对端交换密钥	
		//通过POST远程调用获得返回	
		return $this->callSoapByCurl($this->callUrl,$this->totalArray);
	}
	
	/////////////////////////////////调试函数////////////////////////////////////////
	
	//调试模式下，直接调用，不通过SOAP处理
	function useMethod($module,$service, $m, &$p )
	{
		$ma = self::getModuleAddr($module);
		$serveFunFile = $GLOBALS['DstpDir']['DocDir']."/$ma/$module/$service.InterFace.php";
		include_once($serveFunFile);	
		$c = strtolower($service) ."InterFace";
		$result = method_exists($c, $m);		
		if ( true != $result )
		{
			include_once('a/commonLang.php');
			return soapFault(false, sprintf(SOAP_ERR_HIC_MSF,$module,$service,$m));
		}

		//在call_user_func_array中直接传类名，会自动以静态类方法处理，不能使用$this。
		//call_user_func_array调用的函数中，参数不能写为引用。所以这儿直接写调用
		switch( count($p) )
		{
			case 0:
				return $c::$m();
				break;
			case 1:
				return $c::$m($p[0]);
			case 2:
				return $c::$m($p[0],$p[1]);
			case 3:
				return $c::$m($p[0],$p[1],$p[2]);
			case 4:
				return $c::$m($p[0],$p[1],$p[2],$p[3]);
			case 5:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4]);
			case 6:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4],$p[5]);
			case 7:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6]);
			case 8:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7]);
			case 9:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8]);
			case 10:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8],$p[9]);
			case 11:
				return $c::$m($p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8],$p[9],$p[11]);
			default:
				return call_user_func_array (array($c, $m), $p);
		}
	}	
	
	function soapFault($returnValue=NULL, $errMsg=NULL)
	{
		//debug($errMsg);
		$this->errMsg = $errMsg;
		return $returnValue;
	}

}

?>
