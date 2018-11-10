<?php
//这个是由系统crontab调用，避免原来的升级任务有问题导致无法升级
//使用一个完全独立的代码路径，不和其它任何系统代码相关联

@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);

//系统通过crontab设置了一个独立的升级代码路径，避免原来的升级路径出错了导致无法再升级
//为了避免所有板子都在同一个时间启动，这儿每次启动后，就随机修改这个crontab的启用时间
//避免和local,sysmaintence里设定的正常升级通道时间重叠
sleep(mt_rand(0,3600));

//为了避免更新时出错导致所有更新挂掉。这儿使用ROM目录下的文件
require_once('/rom/www/App/a/config/dstpCommonInfo.php'); 
upgradeInterFace::upgradeRouter();
		
class upgradeInterFace
{
	//获取当前版本
	static function getHICVersion()
	{
		$vercfg = dirname(dirname(dirname(__FILE__))).'/a/config/dstpHICVersion.cfg';
		$hic = file_get_contents($vercfg);  
		
		//发送最原始的oldhic
		$vercfg = '/rom/www/App/a/config/dstpHICVersion.cfg';
		$old = file_get_contents($vercfg);  
		

		//获取固件版本号
		$fw	= `uci get system.@version[0].firmware`;
		$bn	= `uci get system.@version[0].batchno`;

		//获取数据库结构版本
		$c  = new TableSql('hic_hicver');
		$db = $c->queryValue('VER');
		
		return array('fw'=>trim($fw),'bn'=>trim($bn),'db'=>trim($db),'hic'=>trim($hic),'old'=>trim($old));	
	}

	//检查当前是否有待升级的版本
	private static function getUpVerison(&$ver)
	{
		$fw  = $ver['fw'];
		$bn  = $ver['bn'];
		$db  = $ver['db'];
		$hic = $ver['hic'];
		$old = $ver['old'];
		$host = u_jia_sx;

		$url = "http://$host/upgrade/App/app/verService.php?fw=$fw&bn=$bn&db=$db&hic=$hic&old=$old&way=backup";
		$ch = curl_init(); 
		curl_setopt ($ch, CURLOPT_URL, $url); 
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,30); 
		//$json = curl_exec($ch); 
		$json = false;

     	if(false==$json)
		{
			//通过安全连接试验下，防止被插入流氓信息
			$url = "https://$host/upgrade/App/app/verService.php?fw=$fw&bn=$bn&db=$db&hic=$hic&old=$old&way=backup";
			$ch = curl_init(); 
			curl_setopt ($ch, CURLOPT_URL, $url); 
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,30); 
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 2);
			$json = curl_exec($ch); 
		}
     	if(false==$json)
		{
			return false;
			
		}
		return json_decode($json,TRUE);
	}
	
	private static function setDBVer($v)
	{
		$c = new TableSql('hic_hicver');
		$c->del();
		$info = array();
		$info['VER'] = $v;
		$c->add($info);
	}

	private static function setFWVer($v)
	{
		`uci set system.@version[0].firmware="$v" && uci commit system`;
	}

	private static function setHICVer($v)
	{
		$vercfg = dirname(dirname(dirname(__FILE__))).'/a/config/dstpHICVersion.cfg';
		file_put_contents($vercfg,$v);
	}

    static function unzip($file,$path)
	{
		if (!file_exists($path))
		{
			@$result = mkdir($path,0777,true);
			if (true != $result )
			{
				echo "make fail\n";
				return false;
			}
		}

		$zip = zip_open($file);
		if ( !$zip )
		{
				echo "open fail\n";
			return false;
		}
		
	    while ($zip_entry = zip_read($zip)) 
	    {
	    	$name = zip_entry_name($zip_entry);
			$isdir = substr($name,-1);			
	        if (zip_entry_open($zip, $zip_entry, "r")) 
	        {
	            $file = $path . '/' . $name;
				if ( '/' == $isdir )
				{				
					if (!file_exists($file)) mkdir($file,0777,true);
				}
				else
				{
					$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
					file_put_contents($file,$buf);
				}
					
	            zip_entry_close($zip_entry);
	        }	
	    }
	    zip_close($zip);
		
		return true;
	}

	private static function getUpgradeDir($up)
	{
		$file = $up['file'];
		`cd /tmp && wget -O upgrade.zip $file`;
		$check = strtoupper(md5_file('/tmp/upgrade.zip'));
		if( $check!= $up['check'] )
		{
			return false;
		}

		//解压
		$r = self::unzip('/tmp/upgrade.zip','/tmp/upgrade');
		`rm -rf /tmp/upgrade.zip`;
		if(!$r)
		{
			return false;
		}
		
		//判断压缩包内容的正确性。根据压缩包的ver.php进行版本比较，以免升级服务器的操作失误
		$ckInfo = file_get_contents('/tmp/upgrade/ver.cfg');
		if( NULL == $ckInfo )
		{
			return false;
		}
		$ck = array();
		$ckInfo = explode("\n",$ckInfo);
		foreach($ckInfo as &$cfg)
		{
			if ( NULL == $cfg )
			{
				continue;
			}
			list($key,$info) = explode(':',$cfg);
			$ck[$key] = trim($info);
		}
		
		//考虑应用多平台支持，这个在系统管理上设置。暂不写配置里判断
		//if( ($up['fw'] != $ck['fw']) || ($up['hic'] != $ck['hic']) )
		if( $up['ver']['hic'] != $ck['hic'] )
		{
			return false;
		}
		
		return '/tmp/upgrade';
	}
	
	//根据升级包中的信息升级SQL
	private static function updateSQL($dir,$newver)
	{
		//判断是否需要升级
		$sqlstr = file_get_contents("$dir/upgrade.sql");
		if( NULL == $sqlstr )
		{
			return true;
		}

		$sqls = explode(';', $sqlstr);
		$cSql = new SQL( getCloudDBH() );
		$cSql->beginTransaction1();
		foreach($sqls as &$sql)
		{
			$sql = trim($sql);
			if ($sql == '') { continue; }
			$r = $cSql->exec($sql);
			//if( !$r )
			//{
			//	$cSql->rollBack1();
			//	return false;
			//}
		}

		self::setDBVer($newver);

		$cSql->commit1();

		return true;		
	}

	//根据升级包中的信息升级SQL
	private static function updateWWW($dir)
	{
		`cp -rf $dir/www /`;
		//要保证smarty模板文件能得到重新编译生成
	}

	//根据升级包中的信息升级IPK
	private static function updateIPK($dir)
	{
		//检查待删除的opkg列表
		$remove = file_get_contents("$dir/removelist");
		$remove = explode("\n",$remove);
		foreach($remove as $r)
		{
			if ( NULL == $r )
			{
				continue;
			}
			`opkg remove $r`;
		}
		
		//检查有没待安装或者升级的ipk
		$handle = opendir("$dir/ipk");
		if ( false == $handle )
		{
			return true;
		}
		while (false !== ($ipk = readdir($handle))) 
		{ 
			if ($ipk == "." || $ipk == "..") { continue; }
			
			`cd $dir/ipk && opkg install $ipk`;
		}
		return true;
	}

	//执行PHP代码
	private static function execPHP($dir)
	{
		$codepath="$dir/upgrade.php";
		`php-cli -q $codepath`;
	}	

	//升级程序入口
	static function upgradeRouter() 
	{
		//升级之前先备份。备份应该要有多重备份，否则一直只覆盖也容易出错
        //$GLOBALS['dstpSoap']->setModule('local','dev');
        //$GLOBALS['dstpSoap']->backupHICCfg();

		//获取自己的版本信息，传递自己的版本信息给升级服务器，检查是否可升级
		$ver = self::getHICVersion();
       	$up  = self::getUpVerison($ver);
		if( false == $up )
		{
			return;
		}

		/*
		$up = array(
			'ver'   => array('hic'=>,'db'=>,'fw'=>)
			'file'  => url,
			'check' => filemd5
		)
		filedir
			ver.cfg
			www
			ipk
				xxx.ipk
			upgrade.php
			upgrade.sql
			removelist
		*/
		while( false != $up )
		{
			//判断升级程序的正确性。要同一平台的才可以升级
			if( $up['ver']['fw'] != $ver['fw'] )
			{
				break;
			}

			//获取升级包并校验，解压
			$upgradedir = self::getUpgradeDir($up);
			if( false == $upgradedir )
			{
				break;
			}

			//进行升级处理
			//如果当前版本大于待升级版本，则跳过不处理
			if( 1 == version_compare($up['ver']['hic'], $ver['db']) )
			{
				self::updateSQL($upgradedir, $up['ver']['hic']);
			}
			if( 1 == version_compare($up['ver']['hic'], $ver['hic']) )
			{
				self::updateIPK($upgradedir);
				self::updateWWW($upgradedir);
			}

			//执行收尾的PHP处理代码
			self::execPHP($upgradedir);

			//更新HIC版本号
			self::setHICVer($up['ver']['hic']);
			
			$continueup= true;
			if(file_exists("$upgradedir/www/App/b/local/upgrade.InterFace.php"))
			{
				//如果有修改了文件自己，则不能再继续检查升级，应该先退出等下次
				$continueup= false;
			}

			//删除临时文件
			`rm -rf $upgradedir`;

			//数据库保存到flash
			`database.sh`;
			
			if( !$continueup )
			{
				break;
			}

			//继续检查是否还有需要升级的。有些如果涉及到升级程序自己的，不能继续检查，需要退出重启后再检查
			$ver = self::getHICVersion();
			$up  = self::getUpVerison($ver);
		}

		//升级后进程重启
		`/etc/init.d/lighttpd restart`;
		
		//直接结束所有PHP进程，等下次monitor重启
		`killall php-cli`;
		//`/etc/init.d/proxy stop`;
		//`/etc/init.d/hicserver stop`;
		//`/etc/init.d/hicstatus stop`;
		//`/etc/init.d/proxy start`;
		//`/etc/init.d/hicserver start`;
		//`/etc/init.d/hicstatus start`;

		exit();//直接结束本进程，等待维护任务再重启，以免后续接口调用版本不匹配
	}
}


?>