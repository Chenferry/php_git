<?php  
//分机配置信息
//hicid,chid,hcid,logicid,
const DEV_STATUS_INIT   = 0; //初始
const DEV_STATUS_JOIN   = 1; //收到加入确认，正在发送属性
const DEV_STATUS_APPEND = 2; //收到属性列表，发送附加信息
const DEV_STATUS_WORK   = 3; //加入完成，进入工作状态


$GLOBALS['DevInfo'] = array(
	'name' => '分机',
	'sn'   => '123',
	'ver'  => '1.0',
	'power'  => 255,
);

$GLOBALS['devAttrDef'] = array(
	0 => array('index'=>0,'name'=>'分机',  'attr'=>'fj'),
	1 => array('index'=>1,'name'=>'Zigbee','attr'=>'tran'),
);
if (defined('HIC_SYS_HAVE4G') && (true == HIC_SYS_HAVE4G))
{
	$GLOBALS['devAttrDef'][2] = array('index'=>2,'name'=>'电话','attr'=>'phone');
	$GLOBALS['devAttrDef'][3] = array('index'=>3,'name'=>'短信','attr'=>'sms');
}


$GLOBALS['devAppendInfo'] = array(
	0 => pack('CC',0,1), //ver/wifi
	1 => pack('CC',0,1), //ver/zigbee
	14=> pack('CCCCCCCC',0X00,0X01,0X01,0X01,0X00,0X00,0X00,0X00), //属性布局信息
);

class fjAttrClass
{
	static function devCtrl($msg)
	{
		$cmd = unpack('Ccmd',$msg);
		$cmd = $cmd['cmd'];
		switch ($cmd) 
		{
			case 0://心跳包回复
				$ssid = trim(substr($msg, 1, 32));
				$encryption = trim(substr($msg, 1+32, 10));
				$key = trim(substr($msg, 1+32+10, 32));

				if (($encryption != "none") && (strlen($key) < 8)) {
					debug(__FUNCTION__ . ' ' . __LINE__ . ' ' . " password too short");
					continue;
				}
				
				//判断SSID是否有一样，不一样则修改
				$ossid    = SSID::getSSID();
				if ( $ssid != $ossid['name'] || $encryption != $ossid['encryption'] || $key != $ossid['password'])
				{
					$ret = SSID::setSSID($ssid);
					$ret = SSID::setEcrypt($encryption, $key);
					$ret = network::restart();
				}
				break;
			case 5:
				$info = unpack('a17mac/C1period/C1add',substr($msg, 1, 17+1+1));
				$GLOBALS['dstpSoap']->setModule('local','firewall');
				$GLOBALS['dstpSoap']->setMacPeriod($info['mac'],$info['period'],$info['add']);
				break;
		}
	}
	
}
class tranAttrClass
{
	static function devCtrl($msg){}
	
}
class smsAttrClass
{
	static function devCtrl($msg)
	{
		$cmd = unpack('Ccmd',$msg);
		$cmd = $cmd['cmd'];
		switch ($cmd) 
		{
			case 7://发送短信
				$info = unpack('n1phoneLens/n1msgLens',substr($msg, 1, 2+2));
				$phoneLens = $info['phoneLens'];
				$msgLens = $info['msgLens'];
				$mode = 'a' . $phoneLens . 'phones/a' . $msgLens . 'sms';
				$info = unpack($mode,substr($msg, 1+4, $phoneLens+$msgLens));
				$phones = unserialize($info['phones']);
				$sms = $info['sms'];
				include_once('plannedTask/PlannedTask.php');
				$planTask = new PlannedTask('delay','phone');
				$planTask->sendSmsToMultiPhone($phones,$sms);
				break;
		}
	}
	
}
class phoneAttrClass
{
	static function devCtrl($msg)
	{
		$cmd = unpack('Ccmd',$msg);
		$cmd = $cmd['cmd'];
		switch ($cmd) 
		{
			case 6://拨打电话
				$info = unpack('a11phone',substr($msg, 1, 11));
				include_once('plannedTask/PlannedTask.php');
				$planTask = new PlannedTask('delay','phone');		
				$planTask->dial($info['phone']);
		}
	}
}


class extProtoClass
{

	static function checkSecuryCode($key)
	{
		$checkkey = file_get_contents('/usr/db/key');
		if( false == $checkkey )
		{
			return false;
		}
		//根据HCID和CHID分别计算出IMSI，比较是否相同
		$logicId = substr($checkkey, 0, 4);
		$key1  = substr($key, 0, 4);  
		$key2  = substr($key, 4, 4);
		$rand = $key1 ^ $logicId;
		$rand1 = $key1 ^ $logicId;
		$rand2 = $key2 ^ $logicId;

		$randNum = $rand;
		
		$randomCode = Cache::get('randomCode');
		if( false == $randomCode )
		{
			$randomCode = array(0,0,0);
		}
		
		for ($i=0; $i < 3; $i++) { 
			if ($randNum == $randomCode[$i]) {
				break;
			}
		}

		if ($i >= 3) {
			return false;
		}
		
		return true;
	}	
	static function genRandomCode()
	{
		$randomCode = Cache::get('randomCode');
		if( false == $randomCode )
		{
			$randomCode = array(0,0,0);
		}
		$randomCode[2] = $randomCode[1];
		$randomCode[1] = $randomCode[0];
		$randomCode[0] = pack('l',mt_rand());
		Cache::set('randomCode',$randomCode);
		return $randomCode;
	}
	//生成密钥
	static function genSecury()
	{
		$checkkey = file_get_contents('/usr/db/key');
		
		$chid = substr($checkkey, 4, 4);//self::$extSys['cHid'];
		$hcid = substr($checkkey, 8, 4);//self::$extSys['hCid'];
		
		$randomCode = self::genRandomCode();
		$randNum = $randomCode[0];
		$sec1 = ($randNum) ^ ($chid);
		$sec2 = ($randNum) ^ ($hcid);

	    // $securyCode = $sec2 . $sec1;
	    $securyCode = $sec1 . $sec2;

		return $securyCode;
	}
	
	//
	static function genProtoHeader(&$msg,$len=NULL)
	{
		if( NULL === $len )
		{
			$len = strlen($msg);
		}

		$newlen = $len;
		if( $len > 235 )
		{
			//如果消息长度超过一定长度，则消息第二个字节为0，在HIC头后跟4个字节表示实际长度
			//HIC头前面的字节有H16n1c1和C1a8C1，共21字节
			$newlen  = $len+4;
			$len     = 0; 
			$msg     = substr($msg,0,21).pack("N",$newlen).substr($msg,21);
		}
		$info = pack('C',$len ).$msg; 
		$crc  = HICProto::calcCRC($info);
		$msg  = chr(0xFC).$info.$crc;
		
		return true;
	}
	
	static function genHICHeader($cmd,$seq)
	{
		$key  = self::genSecury();
		
		$header = pack('n1c1', 0, 1);
		return $header.pack('C1a8C1',$seq,$key,$cmd);

	}
	
	static function sendMsg($id,$cmd,&$msg,$seq=0)
	{
		//生成HIC消息头部
		$info  = self::genHICHeader($cmd,$seq);

		//加入消息体
		$info .= $msg;
		
		self::genProtoHeader($info);

		server::writeconn( $id, $info );
	}
	
}






?>