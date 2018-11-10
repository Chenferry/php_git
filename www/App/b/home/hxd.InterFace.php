<?php
//$cachename = 'ircache_1';  
//result:学习结果
//type:设备类型
//match:匹配结果

//红外学习和发射接口
class hxdInterFace
{
	//每日查询任务。定时查询本地的IR文件是否有更新；删除已经没被属性引用的数据文件
	static function checkIRCodeFile()
	{
		
	}
	
	//根据用户学习到的红外码，向服务器请求匹配的设备列表
	static function getIRMatch($value,$devtype)
	{
		//子进程执行匹配操作
		$GLOBALS['dstpSoap']->setModule('app','chxd');
		$r = $GLOBALS['dstpSoap']->IRMatch($value,$devtype);
		
		foreach( $r as &$code )
		{
			$code['id'] = 'hxd_'.$code['id'];
		}
		
		
		//返回的match是个数组，remoteid=>show。remoteid是服务器上数据库的ID，后面如果选择后，需要根据该ID到服务器上取详细信息
		$cachename = 'ircache_1';
		$ircache = Cache::get($cachename);
		$ircache['match'] = $r;
		Cache::set($cachename,$ircache,20);
	}
	
	//增加学习到的设备作为一个属性
	static function addRemote($id,$remoteid)
	{
		include_once('b/homeLang.php');
		//从id获得设备ID
		$c = new TableSQL('homeattr','ID');
		$devid = $c->queryValue('DEVID','ID=?',array($id));
		if( !validID($devid) )
		{
			return soapFault(false,'dev fail 1'); //返回失败信息
		}

		$ircache = $_SESSION['ircache1'];
		
		//增添到设备属性表
		$sysname = 'kt';

		$name = NULL;
		$code = NULL;
		foreach( $ircache['match'] as &$n)
		{
			if ($n['id'] == $remoteid)
			{
				$name = $n['brand'];
				$code = $n['code'];
			}
		}
		if ( NULL == $n )
		{
			return soapFault(false,'dev fail 3'); //返回失败信息
		}
		
		//选择一个index
		$index  = 1;
		$attrid = INVALID_ID;
		$c = new TableSQL('homeattr','ID');
		do{
			$index++;
			$attrid = $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($devid,$index));
		}while(validID($attrid));
		
		$info = array();
		$info['NAME']      = $name; //需要从homeclient中获取名字
		$info['CANDEL']    = 1;
		$info['SYSNAME']   = $sysname;
		$info['ATTRINDEX'] = $index;
		$info['CFGINFO']   = serialize(array('type'=>'hxd','code'=>$code,'remoteid'=>$id));
		$attrList[] = $info;
		
		$GLOBALS['dstpSoap']->setModule('home','end');
		$r = $GLOBALS['dstpSoap']->addDevAttList($devid,$attrList);
		if(!$r)
		{
			return soapFault(false,'add fail'); //返回失败信息
		}
		$c = new TableSQL('homeattr','ID');
		return $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($devid,$index));
	}
	
	private static function getKeyCode(&$value)
	{
		//必须限制在16-30度之间
		$value['temp'] = intval($value['temp']);
		if ( 16 > $value['temp'] ) $value['temp'] = 16;
		if ( 30 < $value['temp'] ) $value['temp'] = 30;	
		$info  = chr( $value['temp'] ); 

		//风量  自动：01,低：02,中：03,高：04
		//(0=>ATTRCFG_ZIDONG,1=>ATTRCFG_KT_DI,2=>ATTRCFG_KT_ZHONG,3=>ATTRCFG_KT_GAO)
		$value['wind'] = intval($value['wind'])+1; //前台数据和数据发送要求的差异
		$info .= chr( $value['wind'] ); 
		
		//手动风向：向下：03,中：02,向上：01
		//(0=>ATTRCFG_ZIDONG,1=>ATTRCFG_KT_FENGXIANG1,2=>ATTRCFG_KT_FENGXIANG2,3=>ATTRCFG_KT_FENGXIANG3,4=>ATTRCFG_KT_FENGXIANG4)
		$info .= chr( $value['widdir'] ); 
		//自动风向：01,打开,00，关,
		$value['widdirauto'] = 0x00;
		if( 0 == $value['widdir'] )
		{
			$value['widdirauto'] = 0x01;
		}
		$info .= chr( $value['widdirauto'] ); 
		
		//开关数据：开机时：0x01,关机时：0x00
		//(1=>ATTRCFG_GUAN,0=>ATTRCFG_KAI)
		$value['open'] = $value['open']?0:1;
		$info .= chr( $value['open'] ); 
		
		//键名对应数据,电源：0x01,模式：0x02,风量：0x03,手动风向：0x04,
		//自动风向：0x05,温度加：0x06,  温度减：0x07
		//array(open,mode,temp,wind,winddir)
		$key = 0x01;
		switch( $value['other'] )
		{
			case 'mode':
				$key = 0x02;
				break;
			case 'wind':
				$key = 0x03;
				break;
			case 'winddir':
				$key = $value['widdirauto']+0x04;
				break;
			case 'temp':
			case 'open':
			default:	
				$key = 0x01;
				break;
		}
		$info .= chr( $key ); 

		//模式：自动（默认）：0x01,制冷：0X02,抽湿：0X03,送风：0x04;制热：0x05
		//(0=>ATTRCFG_ZIDONG,1=>ATTRCFG_KT_ZHILENG,2=>ATTRCFG_KT_CHUSHI,3=>ATTRCFG_KT_TONGFENG,4=>ATTRCFG_KT_ZHIRE)
		$value['mode'] = intval($value['mode'])+1;
		$info .= chr( $value['mode'] ); 
		
		return $info;
	}

	static function getIRCode($value,$code)
	{
		$code  = base64_decode($code);
		$group = substr($code,0,2);
		//表中的code实际上是一个完整的按键数据，所以包含到了FF数据
		//去掉组号，按键组合等信息
		$code  = substr($code,2+7);

		$info  = chr(0x30).chr(0x01);
		$info .= $group;
		$info .= self::getKeyCode($value);
		//$info .= chr( strlen($code)+1 );
		$info .= $code;  //数据表中code已经包含了长度和最后的FF这些信息
		//$info .= chr(0xFF);
		
		//最后一个字节为前面所有数据之和的低8位,（第0x30到0xFF数据之和取低8位
		$len = strlen($info);
		$sum = 0;
		for($i=0; $i<$len; $i++)
		{
			$sum += ord($info[$i]);
		}
		$info  .= chr( $sum&0xFF );

		return pack('CC',REMOTE_CMD_CTRL,strlen($info)).$info;
	}

}
?>