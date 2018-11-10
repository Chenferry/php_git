<?php
include_once('a/commonLang.php');

class snRWInfo
{
	static function set($info)
	{
		if ('RASPBERRY' == snInterFace::getSystemFirmwareInfo()) 
		{
			self::writeSNCFG($info);
		} 
		else 
		{
			self::writeSNMTD($info);
		}
		return true;
	}
	static function get()
	{
		if ('RASPBERRY' == snInterFace::getSystemFirmwareInfo()) 
		{
			$sn = self::getSNCFG();
		} 
		else 
		{
			$sn = self::getSNMTD();
		}
		
		return $sn;		
	}
	/////////////////////////flash读写分区相关函数
	private static function writeSNMTD($info)
	{
		file_put_contents('/tmp/sn',$info);
		`cd /tmp && mtd write /tmp/sn sn > /dev/null`;
		`rm -rf /tmp/sn > /dev/null`;
		return;
	}

	private static function getSNMTD()
	{
		$mtd = self::getMTDByName('sn');
		`dd if=/dev/$mtd of=/tmp/sn > /dev/null`;
		$sn = file_get_contents('/tmp/sn');
		`rm -rf /tmp/sn > /dev/null`;
		return $sn;
	}

	private static function getMTDByName($name)
	{
		$info = `cat /proc/mtd | grep "$name"`;
		$info = trim($info);
		list($mtd,$info) = explode(':',$info);
		return trim($mtd);
	}
	
	/////////////////////////树莓派读取分区相关函数
	
	private static function writeSNCFG($info)
	{
		file_put_contents('/boot/serialnumber', $info);
		return;
	}

	private static function getSNCFG()
	{
		$sn = file_get_contents('/boot/serialnumber');
		return $sn;
	}
}


class snInterFace
{
	static $sn = NULL;
	static $hiccfg = array(
		'HICDOMAIN' => 'jia.mn',
		'IDFLAG'    => 'huidang',
		'NAME'      => HIC_NAME,
		'LANG'      => 'zh_cn',
		'TIMEZONE'  => 8,
		'HICHELP'   => 'http://help.jia.mn/index.htm',
	);
	static function getSystemFirmwareInfo()
	{
		$info = trim(`uci get system.@version[0].firmware`);
		list($firmware,$version) = explode('-',$info);
		return trim($firmware);
	}
	
	//////////////////////////////////////////////////////
	static function setHICCfg($info)
	{
		//补充默认值
		foreach(self::$hiccfg as $key=>&$value)
		{
			if( !isset($info[$key]) )
			{
				$info[$key] = $value;
			}
		}

		//修改系统时区信息
		
		//修改hosts文件
		$host = file_get_contents('/rom/etc/hosts');
		if( self::$hiccfg['HICDOMAIN'] != $info['HICDOMAIN'])
		{
			$ym = $info['HICDOMAIN'];
			$host  .= "\n192.168.93.1 $ym\n192.168.93.1 www.$ym\n192.168.93.1 b.$ym";
		}
		file_put_contents('/etc/hosts',$host);
		
		//写hiccfg文件
		$cfg = $info;
		unset($cfg['SN']);
		unset($cfg['CHECKKEY']);
		$hiccfg = '$GLOBALS["hicCfg"] = '.var_export($cfg, TRUE).";\n" ;
		$file = dirname(dirname(dirname(__FILE__))).'/a/config/dstpHICCfg.php';
		file_put_contents($file, "<?php \n".$hiccfg."\n?>");
		
		return;
	}
	
	static function getSNInfo()
	{
		if( DSTP_DEBUG )
		{
			return array('SN'=>'aaaaa','IDFLAG'=>'', 'NAME'=>'smarthic');
		}
		$sn = snRWInfo::get();

		//兼容之前的保存方式
		@list($sn,$flag,$other) = explode('###',$sn);
		if( 'aaa' != $flag )
		{
			//还没被激活
			return false;
		}

		$pos = strpos($other,'@#$*&');
		if( false != $pos )
		{
			$sninfo = substr($other,0,$pos);
			$sninfo = unserialize($sninfo);
		}
		if( !is_array($sninfo) )
		{
			$sninfo = array();
		}
		$sninfo['SN'] = $sn;
		foreach(self::$hiccfg as $key=>&$value)
		{
			if( !isset($sninfo[$key]) )
			{
				$sninfo[$key] = $value;
			}
		}

		return $sninfo;
	}

	static function setSNInfo($info)
	{
		self::setHICCfg($info);
		
		if( !isset($info['SN']) )
		{
			//服务器不会再传来sn信息，所以这儿需要从分区中先读出再写进去
			$sn = self::getSNInfo();
			if( false != $sn )
			{
				$info['SN'] = $sn['SN'];
			}
		}
		$sn = $info['SN'];
		$info = $sn.'###aaa###'.serialize($info).'@#$*&';
		return snRWInfo::set($info);
	}

    static function getSN()
    {
		if( NULL == self::$sn )
		{
			$cfg = self::getSNInfo();
			self::$sn = $cfg['SN'];
		}
		if( NULL == self::$sn )
		{
			return false;
		}
		return self::$sn;
    }
	
	//后期动态修改更新品牌信息
	static function changeLogo($idflag,$checkKey)
	{
		if( NULL == $idflag )
		{
			return soapFault(false,'idflag is null');
		}
		
		$sn = self::getSN();
		if(false == $sn)
		{
			return soapFault(false,'sn is null');
		}

		$phyid = HICInfo::getPHYID();
		$GLOBALS['dstpSoap']->setHost(r_jia_sx);
		$GLOBALS['dstpSoap']->setModule('app','init');
		$info = $GLOBALS['dstpSoap']->changeLogo($phyid,$idflag,$checkKey);
		if(false==$info)
		{
			return soapFault(false,'change fail');
		}
		$info['SN'] = $sn;
		self::setSNInfo($info);
		
		return $info['NAME'];
	}
    
    static function activeSN($idflag=NULL)
    {
		if(false != self::getSN())
		{
			return true;
		}

		$GLOBALS['dstpSoap']->setModule('local','upgrade');
		$ver = $GLOBALS['dstpSoap']->getHICVersion();
		
		$phyid = HICInfo::getPHYID();

		$GLOBALS['dstpSoap']->setHost(r_jia_sx);
		$GLOBALS['dstpSoap']->setModule('app','init');
		$sn = $GLOBALS['dstpSoap']->requestSN($ver,$phyid,$idflag);
		if( false == $sn )
		{
			return soapFault(false,$GLOBALS['dstpSoap']->getErr());
		}
		
		$info = array();
		$info['SN']     = $sn['sn'];
		$info['NAME']   = $sn['name'];
		$info['IDFLAG'] = $sn['idflag'];

		self::setSNInfo($info);
		
		//写入马上读出，如果正确写入，则向服务器回确认消息
		$sn = self::getSN();
		if( $sn != $info['SN'] )
		{
			self::$sn = NULL;
			return false;
		}
		
		//向服务器回确认消息
		$GLOBALS['dstpSoap']->setHost(r_jia_sx);
		$GLOBALS['dstpSoap']->setModule('app','init');
		$GLOBALS['dstpSoap']->confirmSN($phyid,$sn);

		//设置登录密码
		`echo "root:q#$%123hic" | chpasswd > /dev/null`;
		
		//激活后，所有进程重启下，刷新部分缓存信息
		`killall php-cli`;

		return true;
    }

}
?>