<?php
class devInterFace
{
	//重启设备
	static function rebootDev()
	{
		//判断
		return `reboot`;
	}
	
	//检查设备是否在运行。简单一个消息调用如果能回复表示在运行
	static function checkDevRun()
	{
		return true;
	}

	static function freeDev($userid)
	{
		$c	= new TableSql('hic_frameautologin');
		if( NULL == $userid )
		{
			$c->del('1=1');
		}
		else
		{
			$c->del('USERID=?',array($userid));
		}
	    include_once('procd/service.class.php');
		service::clearSessionFile();
		return true;
	}
	
	//生产第一次启动后，向服务器注册SN序列号
	static function initDevSN()
	{
		//SN唯一生成，必须是从服务器获取登记。刚生产下来后，第一步获取SN完成出厂
		//SN是从指定内网服务器获取，该内网服务器再凭密钥从云服务器获取
	}
	//重置设备
	static function resetDev()
	{ 
		//恢复etc/config
		//恢复db
		return self::restoreHICCfg();
		
	}
	//向服务器注册并获得交换密钥
	static function initNewHic()
	{
		$phyid   = HICInfo::getPHYID();
		$ssid    = SSID::getSSID();
		$GLOBALS['dstpSoap']->setModule('local','sn');
		$sn      = $GLOBALS['dstpSoap']->getSN();
		//检查自己是否已经注册但未绑定用户。如果是，判断当前注册信息和服务器上的信息是否一致
		$c = new TableSql('hic_hic');
		$info = $c->query();
		if ( NULL != $info )
		{
			//如果已经绑定用户就不处理。真要重新开始也要复位后再来
			$c = new TableSql('hic_user'); 
			if ( 0 != $c->getRecordNum() )
			{
				return false;
			}
			//如果没绑定用户，就探测下的发个请求，看现在的注册和交换密钥信息是否正确。
			$GLOBALS['dstpSoap']->setModule('app','hic');
			$r = $GLOBALS['dstpSoap']->checkRegister($info);
			if ( true == $r )
			{
				return $info['ID'];
			}
			//清空重注册
			$c = new TableSql('hic_hic');
			$c->del();
			$c = new TableSql('hic_hicinfo');
			$c->del();
		}
		
		//如果还没注册或者注册信息和服务器的不一致，则重新初始化
		$GLOBALS['dstpSoap']->setModule('app','init');
		$r = $GLOBALS['dstpSoap']->initNewHic($phyid,$ssid['name'],$sn);
		if ( $r == false )
		{
			return soapFault(false, $GLOBALS['dstpSoap']->getErr());
		}
		
		//注册时，理论上应该所有设备为空。但由于现在默认允许接入设备
		//有可能现在这时已经有很多测试时留下的设备加入信息。直接删除
		$c = new TableSql('homedev','ID');
		$c->del('PHYDEV=?',array(PHYDEV_TYPE_ZIGBEE));
		
		$c = new TableSql('hic_hic');
		$c->add($r['info']);
		$c = new TableSql('hic_hicinfo');
		$c->add($r['sinfo']);
		
		//添加默认用户权限
		$c = new TableSql('homeaccess');
		$access = array();
		$access['USERID']   = -1;
		$access['USERTYPE'] = USER_TYPE_ADMIN;
		$c->add($access);
		

		if( !DSTP_DEBUG )
		{
			`killall php-cli`;//重新启动代理进程
		}
		return $r['info']['ID'];
	}

	static function syncHicInfo()
	{
		//随机决定是否发起请求更新密钥
		
		//同步本设备的云用户
		$GLOBALS['dstpSoap']->setModule('app','reg');
		$uListInfo = $GLOBALS['dstpSoap']->getUserList(true);
		if ( false == $uListInfo )
		{
			return false;
		}
		
		$uList = &$uListInfo['user'];
		$c = new TableSql('hic_user');
		$cAccess = new TableSql('homeaccess');

		//获取默认用户类型
		$existList = $c->queryAllList('ID');
		$newList   = array();
		$c->del();
		foreach($uList as &$u)
		{
			$newList[] = $u['ID'];
			$c->add($u);
		}

		//删除该用户在本地的其它属性信息
		$c       = new TableSql('homefavorite');
		$delList = array_diff($existList,$newList);
		foreach( $delList as $del )
		{
			$cAccess->del('USERID=?',array($del));
			$c->del('USERID=?',array($del));
		}
		
		//获取用户默认权限
		$access  = $cAccess->query('*','USERID=?',array(-1));
		$addList = array_diff($newList,$existList);
		foreach( $addList as $add )
		{
			$access['USERID'] = $add;
			$cAccess->add($access);
		}
		
		//更新用户绑定信息
		$uInfo = &$uListInfo['info'];
		$c = new TableSql('hic_userinfo');
		$c->del();
		foreach($uInfo as &$ui)
		{
			$c->add($ui);
		}
		
		//查询SSID名称进行同步
		$GLOBALS['dstpSoap']->setModule('app','hic');
		$name = $GLOBALS['dstpSoap']->getHICName();
		if ( $name )
		{
			$GLOBALS['dstpSoap']->setModule('local','local');
			$GLOBALS['dstpSoap']->setHICName($name);
		}
        include_once('procd/service.class.php');
        service::dbBackup();

		return true;

		//同步本设备的自动登陆信息。无需同步，每次登陆时再检查
		//$GLOBALS['dstpSoap']->setModule('app','hic');
		//$r = $GLOBALS['dstpSoap']->syncDevAutoLogin();
		
	}
	
	/* 备份本地信息到云服务器 */
	static function backupHICCfg()
	{
		//读取网络配置和ssid，保存到/user/db/network
		include_once('uci/uci.class.php');
		$netCfg = array();
		$netCfg['ssid']    = SSID::getSSID();
		$netCfg['wantype'] = trim(wan::getType());
		$netCfg['pppoe']   = PPPoE::getAccount();
		$netCfg = serialize($netCfg);
		file_put_contents('/usr/db/network',$netCfg);		

		//配置文件打包成tar.gz
		$cfg_path='/tmp/files';
		`database.sh`;
		`mkdir -p $cfg_path`;
		`mkdir -p $cfg_path/usr/db`;
		`cp -rf /usr/db/ $cfg_path/usr`;
		$file = `tar -zcf - $cfg_path`;
		$file_md5 = md5($file);
		$GLOBALS['dstpSoap']->setModule('app','hic');
		$r = $GLOBALS['dstpSoap']->uploadCfg($file,$file_md5);//上传配置文件

		`rm -rf $cfg_path`;

		return $r;
	}
	
	private static function restoreLocalDB($dbfile,&$cfg)
	{
		$newphyid = HICInfo::getPHYID();

		$dsn    = 'sqlite:'.$dbfile;
		$db = connectToDB($dsn, NULL, NULL, NULL);
		$db->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

		// 更新本地环境的phyid
		$info = array ();
		$info ['PHYID'] = $newphyid;
		$c = new TableSql ( 'hic_hic' );
		$c->setDBH($db);		
		$c->update ( $info );

        $aa     = $cfg['s'];
        $hcid   = $aa['HCID'];
        $chid   = $aa['CHID'];
		
		$data   = array();
		$data['HCID']   = $hcid;
		$data['CHID']   = $chid;
		$c  = new TableSql('hic_hicinfo');
		$c->setDBH($db);		
		$c->update($data);		

		$db = NULL;
	}
	
	/* 更新恢复本地的设备文件 */
	static function restoreHICCfg($cfg=NULL)
	{
		if ( NULL == $cfg )
		{
			$GLOBALS['dstpSoap']->setModule('app','hic');
			$cfg = $GLOBALS['dstpSoap']->downloadCfg();
		}
		if($cfg==false)
			return false;
		if( $cfg['m'] && md5($cfg['c']) != $cfg['m'] )
		{
			return false;
		}
		//还需要先删除原来的/etc/config文件 mark by bizi
		$tmp_path='/tmp/config.tar.gz';
		file_put_contents($tmp_path,$cfg['c']);//把下载文件写入临时目录
		
		//$shell =`tar -zxf $tmp_path -C /tmp &&  cp -rf /tmp/tmp/files/IR/* /usr/db && sqlite3 /tmp/hdang.db ".restore /tmp/tmp/files/hdang.db"`;/*shell命令解压缩文件 */
		//解压
		`tar -zxf $tmp_path -C /tmp`;
		
		self::restoreLocalDB('/tmp/tmp/files/usr/db/hdang.db',$cfg);
		
		//备份文件直接覆盖.数据库同步保存一份到/tmp/hdang.db
		`cp /tmp/tmp/files/usr/db/hdang.db /tmp/hdang.db`;
		`cp -rf /tmp/tmp/files/usr /`;
		
		//恢复网络拨号设置和SSID设置
		include_once('uci/uci.class.php');
		$netCfg = file_get_contents('/usr/db/network');	
		$netCfg = unserialize($netCfg);
		if( false != $netCfg )
		{
			PPPoE::setAccount($netCfg['pppoe']['username'],$netCfg['pppoe']['password']);
			SSID::setSSID($netCfg['ssid']['name']);
			SSID::setEcrypt($netCfg['ssid']['encryption'],$netCfg['ssid']['password']);
			wan::setProto($netCfg['wantype']);
		}

        //$info   = $cfg['s'];
        //$hcid   = $info['HCID'];
        //$chid   = $info['CHID'];
		$res=file_get_contents("http://127.0.0.1/App/b/local/restoreDb.php");
		
		//清除缓存重新生成
		`rm -rf /tmp/hiccache/*`;
		
		//强制协调器重新初始化，让所有设备重上网更新相关逻辑物理地址
		//这儿要暂时注释掉，需要考虑分机的情况
		$GLOBALS['dstpSoap']->setModule('home','if');
		$GLOBALS['dstpSoap']->sendDevSySMsg(0,DEV_CMD_SYS_HICID,0);
		//这儿要等待确保协调器有回应
		
		//配置恢复后的信息初始化
		if( !DSTP_DEBUG )
		{
			//wifi和防火墙重启				
			`/etc/init.d/network restart`;

			//代理进程重启
			//直接结束所有PHP进程，等下次monitor重启
			//如果是命令行运行，则本进程也会被杀掉。需要注意该处后面没有其它有用的处理
			`killall php-cli`;
			//`/etc/init.d/hicserver stop`;
			//`/etc/init.d/hicserver start`;
			//`/etc/init.d/proxy stop`;
			//`/etc/init.d/proxy start`;
			//`/etc/init.d/hicstatus stop`;
			//`/etc/init.d/hicstatus start`;

		}
	
		return ($res=='1') ? true:false;
	}
}
?>