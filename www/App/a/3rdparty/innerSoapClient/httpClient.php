<?php
//通过后台，直接调用指定主机的sajax函数请求
class innerHttp
{
	private $token;
	private $service;
	private $location;

	private $errMsg;
	function getErr()
	{
		return $this->errMsg;
	}

	function setToken($token)
	{
		$this->token = $token;
		list($userid,$time,$hicid,$rand,$flag) = explode('-', $token); 
		$this->location = HICInfo::getPeerHost($hicid);
		return;
	}

	function setFunc($service,$token=NULL)
	{
		$this->service = $service;
		if( NULL != $token )
		{
			$this->setToken($token);
		}
		return;
	}

	//提交post请求
	private function callSoapByCurl(&$url,&$data)
	{	
		if( NULL == $url )
		{
			return NULL;
		}
		
		$ch = curl_init();
		//设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,30);  //定义超时5秒钟  
		curl_setopt($ch, CURLOPT_COOKIE, 'hicautologin='.$this->token);
		// POST数据
		curl_setopt($ch, CURLOPT_POST, 1);
		// 把post的变量加上
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));    //所需传的数组用http_bulid_query()函数处理一下，就ok了
		
		//执行并获取url地址的内容
		$out = curl_exec($ch);
		$errorCode = curl_errno($ch);
		//释放curl句柄
		curl_close($ch);
		if(0 !== $errorCode)
		{
			return NULL;
		}
		if ( NULL == $out )
		{
			return NULL;
		}
		return json_decode($out,true);
	}	
	
	function __call($func,$para)
	{
		if( 'offline' == $this->location )
		{
			debug("offline");
			return NULL;
		}

		$arr = array();
		$arr['rs']    = $func;
		$arr['rsargs']= $para;

		$url = 'http://'.$this->location.'/App/'.$this->service;
		//通过POST远程调用获得返回	
		return $this->callSoapByCurl($url,$arr);
	}
}

?>
