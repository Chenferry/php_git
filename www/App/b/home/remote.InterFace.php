<?php
//$cachename = 'ircache_1';  
//result:学习结果
//type:设备类型
//match:匹配结果

//红外学习和发射接口
class remoteInterFace
{
	//每日查询任务。定时查询本地的IR文件是否有更新；删除已经没被属性引用的数据文件
	static function checkIRCodeFile()
	{
		
	}
	
	private static function getIRCodeFileName($irid,$devid=DEV_REMOTE_AIR)
	{
		if( 'b' == HIC_LOCAL )
		{
			//现在默认都是空调的数据。如果后续添加了电视，需要相应修改
			return '/usr/db/IR/'.$irid;
		}
		//i服务器可以直接访问c目录，保存在c目录下的
		return dirname(dirname(dirname(__FILE__)))."/c/data/IR/IRData/$devid/$irid";

	}

	//获取IR数据保存到本地
	private static function getIRCodeAndSaveFile($remoteid)
	{
		//根据remoteid向服务器请求指令文件和详细信息
		$GLOBALS['dstpSoap']->setModule('app','cremote');
		$info = $GLOBALS['dstpSoap']->getIRCode($remoteid);
		if( NULL == $info )
		{
			return INVALID_ID;
		}

		$c = new TableSQL('homeir','ID');
		$fileid = $c->queryValue('ID','IRID=?',array($info['IRID']));
		if( validID($fileid) )
		{
			return $fileid;
		}

		$sinfo = array();
		$sinfo['IRID']  = $info['IRID'];
		$sinfo['C3RV']  = $info['C3RV'];
		$sinfo['CNUM']  = $info['CNUM'];
		$sinfo['CLEN']  = $info['CLEN'];
		$sinfo['DEV']   = $info['DEV'];
		$sinfo['MTIME'] = time();
		$sinfo['FORMATS']  = base64_decode($info['FORMATS']);
		$fileid = $c->add($sinfo);
		if( !validID($fileid) )
		{
			return INVALID_ID;
		}

		//服务器上已经保存所有文件，无需再写保存
		if( 'b' == HIC_LOCAL )
		{
			$codeInfo =  base64_decode($info['CODE']);
			$file = self::getIRCodeFileName($info['IRID']);
			$len = file_put_contents($file, $codeInfo);
			if( $len != strlen($codeInfo) )
			{
				return INVALID_ID;
			}
		}
		
		return 	$fileid;
	}

	//////////////////////////////////////////////
	//直接索引模式的计算
	private static function calcIndexCodeIndex(&$ir,&$v)
	{
		return $v-1;
	}
	private static function calcAirCodeIndex(&$ir,&$v)
	{
		//0=开关，1=运转模式，2=温度，3=风量，4=风向
		switch($v['key'])
		{
			case 'open':
				$k = 0;
				break;
			case 'mode':
				$k = 1;
				break;
			case 'temp':
				$k = 2;
				break;
			case 'wind':
				$k = 3;
				break;
			case 'winddir':
				$k = 4;
				break;
			default:
				$k = 0;	
		}
		$v['temp'] = $v['temp']-16;
		switch($ir['CNUM'])
		{
			case 15000:
				$index = $v['open']*7500 + $v['mode']*1500 + $v['temp']*100 + $v['wind']*25 + $v['winddir']*5 + $k + 1;
				break;
			case 3000:
				$index = $v['open']*1500 + $v['mode']*300 + $v['temp']*20 + $v['wind']*5 + $v['winddir'] + 1;
				break;
			case 15:
				$index = 0;
				break;
			default:
				$index = 0;
				break;
		}
		//码库算法从1开始，但本处代码都从0开始
		return $index-1;
	}
	private static function calcCodeIndex(&$irinfo,&$value)
	{
		//根据设备类型获取索引
		switch($irinfo['DEV'])
		{
			case DEV_REMOTE_AIR:
				return self::calcAirCodeIndex($irinfo,$value);
				break;
			case DEV_REMOTE_TV:
			case DEV_REMOTE_IPTV:
			case DEV_REMOTE_DVD:
			case DEV_REMOTE_FAN:
			case DEV_REMOTE_CLEANER:
			default:
				return self::calcIndexCodeIndex($irinfo,$value);
				break;
		}
		return 0;
	}
	
	//根据code和模板数据，替换规则计算最终红外码
	private static function calcIRCode( &$code,&$formats,$c3rv)
	{
		if( NULL == $c3rv || '0-0|'  == $c3rv )
		{
			return $code;//无需替换，直接返回
		}
		
		$j = 0;
		$c3rv = explode('|', $c3rv);
		foreach( $c3rv as &$c )
		{
			list($s,$l) = explode('-',$c);
			for($i=0;$i<$l;$i++)
			{
				$formats[$s+$i] = $code[$j+$i];
			}
			$j += $l;
		}

		return $formats;
	}
	
	private static function getCodeByIndex(&$codes,$index,&$irinfo)
	{
		$codes = gzinflate($codes);
		//如果是不定长的，则是文本文件直接压缩，需要先分割为数组
		if( 0 == $irinfo['CLEN'] )
		{
			$codes = explode("\n",$codes);
			$codes = trim($codes[$index]);
			//从字串转化为二进制串
			$len = strlen($codes);
			return base64_decode($codes);
		}
		$s = $index*$irinfo['CLEN'];
		return  substr($codes,$s,$irinfo['CLEN']);
	}
	
	//根据用户操作，获取红外码
	static function getIRCode($value,$attrid)
	{
		//根据attrid获取信息
		$c = new TableSQL('homeattr','ID');
		$index = $c->queryValue('ATTRINDEX','ID=?',array($attrid));
		if(!validID($index))
		{
			return NULL;
		}
		
		$c = new TableSQL('homeremote','ID');
		$irid = $c->queryValue('IRDATA','ID=?',array($index));
		if(!validID($irid))
		{
			return NULL;
		}
		
		//查找IR数据文件
		$c = new TableSQL('homeir','ID');
		$irinfo = $c->query('*','ID=?',array($irid));
		if( NULL == $irinfo )
		{
			return NULL;
		}		
		$file = self::getIRCodeFileName($irinfo['IRID']);
		$info = file_get_contents($file);
		if( false == $info )
		{
			return NULL;
		}
		
		//i服务器是直接取文件内容，需要先解码 
		if( 'b' != HIC_LOCAL )
		{
			$info = base64_decode($info);
		}

		//计算索引
		$codeindex = self::calcCodeIndex($irinfo,$value);
		
		//获取数据
		$code = self::getCodeByIndex($info,$codeindex,$irinfo);
		
		//获取的数据根据模板数据和替换规则得到最后数据
		return pack('C',REMOTE_CMD_CTRL).self::calcIRCode( $code,$irinfo['FORMATS'],$irinfo['C3RV']);
	}	
	
	/////////////////////////////////////////////
	//增加学习到的设备作为一个属性
	static function addRemote($id,$remoteid)
	{
		include_once('b/homeLang.php');
		//判断本地是否存在指定的IR数据文件。从服务器获取IR数据文件存储
		$codeid = self::getIRCodeAndSaveFile($remoteid);
		if( !validID($codeid) )
		{
			return soapFault(false,HOME_REMOTE_IRCODE_NULL); //返回失败信息
		}
		
		//从id获得设备ID
		$c = new TableSQL('homeattr','ID');
		$devid = $c->queryValue('DEVID','ID=?',array($id));
		$indexs = $c->queryAllList('ATTRINDEX','DEVID=?',array($devid));
		if( !validID($devid) )
		{
			return soapFault(false,'dev fail 1'); //返回失败信息
		}

		$ircache = $_SESSION['ircache1'];

		//到红外设备数据表中记录，得到id作为index
		$c = new TableSQL('homeremote','ID');
		$info = array();
		$info['DEVID']   = $devid;
		$info['IRATTR']  = $id;
		$info['IRDATA']  = $codeid;
		$info['DEVTYPE'] = $ircache['type'];
		//检查生成的红外设备的id是否存在，存在的话，删除重新生成
		do{
			$index = $c->add($info);
			if( in_array($index,$indexs) )	
				$c->del('ID=?',array($index));
		}while(in_array($index,$indexs));

		if( !validID($index) )
		{
			return soapFault(false,'dev fail 2'); //返回失败信息
		}
		
		//增添到设备属性表
		$sysname = 'air';
		switch( $ircache['type'] )
		{
			case DEV_REMOTE_AIR:
				$sysname = 'kt';
				break;
			case DEV_REMOTE_TV:
				$sysname = 'tv';
				break;
			case DEV_REMOTE_IPTV:
				$sysname = 'iptv';
				break;
			case DEV_REMOTE_DVD:
				$sysname = 'dvd';
				break;
			case DEV_REMOTE_FAN:
				$sysname = 'fan';
				break;
			case DEV_REMOTE_CLEANER:
			default:
				$sysname = 'cleaner';
				break;
		}

		$name = NULL;
		foreach( $ircache['match'] as &$n)
		{
			if ($n['id'] == $remoteid)
			{
				$name = $n['brand'];
			}
		}
		if ( NULL == $n )
		{
			return soapFault(false,'dev fail 3'); //返回失败信息
		}
		
		$info = array();
		$info['NAME']      = $name; //需要从homeclient中获取名字
		$info['CANDEL']    = 1;
		$info['SYSNAME']   = $sysname;
		$info['ATTRINDEX'] = $index;
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

	static function addUserRemoteBut($id,$name)
	{
		if( NULL == $_SESSION['ircache2'] )
		{
			return -1;
		}
		$info = $_SESSION['ircache2']['info'];
		$c = new TableSQL('homeattr','ID');
		$setinfo = $c->queryValue('ATTRSET','ID=?',array($id));
		$setinfo = unserialize($setinfo);
		if(false == $setinfo )
		{
			$setinfo = array();
		}
		$bid = 1;
		do{
			$find = false;
			foreach( $setinfo as &$s )
			{
				if( $s['id'] == $bid)
				{
					$find = true;
					break;
				}
			}
			if(!$find)
			{
				break;
			}
			$bid++;
		}while(true);
		foreach ($info as $k => $v) 
		{
			$info[$k] = base64_encode($v);
		}
		$setinfo[] = array( 'id'=>$bid, 'name'=>$name, 'info'=>$info );
		$info = array();
		$info['ID'] = $id;
		$info['ATTRSET'] = serialize($setinfo);
		$c->update($info);
		return $bid;
	}

	static function addUserRemote($id,$name)
	{
		include_once('b/homeLang.php');

		//从id获得设备ID
		$c = new TableSQL('homeattr','ID');
		$devid = $c->queryValue('DEVID','ID=?',array($id));
		if( !validID($devid) )
		{
			return soapFault(false,'dev fail 4'); //返回失败信息
		}

	
		//增添到设备属性表
		$sysname = 'hwxx';
		$index   = mt_rand(10,100);
		do{
			$r = $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($devid,$index));
			if(!validID($r))
			{
				break;
			}
			$index++;
		}while(true);

		$info = array();
		$info['NAME']      = $name;
		$info['CANDEL']    = 1;
		$info['SYSNAME']   = $sysname;
		$info['ATTRINDEX'] = $index;
		$info['CFGINFO']   = serialize(array( 'rid'=>$id ));
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
	
	//清楚remote表数据，同时查询指定的IR数据还有人用没，如果没人用了，直接删除
	static function delRemote($devid,$attrid)
	{
		$c = new TableSQL('homeremote','ID');
		$irdata = $c->queryValue('IRDATA','ID=?',array($attrid));
		$c->delByID($attrid);
		
		$num = $c->getRecordNum('IRDATA=?',array($irdata));
		if ( 0 == $num )
		{
			$c = new TableSQL('homeir','ID');
			$irid = $c->queryValue('IRID','ID=?',array($irdata));
			if ( NULL != $irid )
			{
				@unlink(self::getIRCodeFileName($irid));
			}
			$c->delByID($irdata);
		}
		return true;
	}

	//根据用户学习到的红外码，向服务器请求匹配的设备列表
	static function getIRMatch($value,$devtype)
	{
		//子进程执行匹配操作
		$GLOBALS['dstpSoap']->setModule('app','cremote');
		$r = $GLOBALS['dstpSoap']->IRMatch($value,$devtype);
		
		//返回的match是个数组，remoteid=>show。remoteid是服务器上数据库的ID，后面如果选择后，需要根据该ID到服务器上取详细信息
		$cachename = 'ircache_1';
		$ircache = Cache::get($cachename);
		$ircache['match'] = $r;
		Cache::set($cachename,$ircache,20);
	}
	
	//根据命令构造向设备发送的消息包
	static function sendRemoteCtrlMsg($id,$cmd,$info=NULL)
	{
		$msg = pack('C',$cmd).$info;
		$GLOBALS['dstpSoap']->setModule('home','if');
		return $GLOBALS['dstpSoap']->sendMsg($id,DEV_CMD_HIC_CTRL_DEV,$msg);
	}
	

	
}
?>