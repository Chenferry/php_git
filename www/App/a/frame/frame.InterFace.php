<?php
class frameInterFace
{
	//static function runHookService($hookName,$p1=NULL,$p2=NULL,$p3=NULL,$p4=NULL,$p5=NULL,$p6=NULL,$p7=NULL,$p8=NULL)
	//{
	//	return commonHook::runHookService($hookName,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8);
	//}

	//每天定期清理过期数据
	static function cleanDirtyData()
	{
		//cookie如果100天都没用，则删除
		$c = new TableSql('hic_frameautologin');
		$c->del('USETIME<?',array( (time()-86400*100 )));

		//清除过期10天的告警数据
		$c = new TableSql('commonalarm');
		$c->del('CTIME<?',array( (time()-86400*10 )));
	}
	
	private static function initDBEnvInner($dbid,$cacheid,$hicid=0,$itemid=0)
	{
		$cSql  = new TableSql('hic_db','ID');
		$dbCfg = $cSql->queryByID( $dbid, 'DBDSN,DBOPT,DBUSER,DBPSW,DBPRE' );
		if ( NULL == $dbCfg )
		{
			if( 'cli' != PHP_SAPI )
			{
				$_SESSION = array();
			}
			return false;
		}
		
		$GLOBALS['SYSDB'] = array();
		$GLOBALS['SYSDB']['SYSUID'] = $hicid;
		$GLOBALS['SYSDB']['ITEMID'] = $itemid;
		$GLOBALS['SYSDB']['DBPRE']  = $dbCfg['DBPRE'];
		if ( NULL != $dbCfg['DBDSN'] )
		{
			$dbCfg['DBOPT'] = unserialize($dbCfg['DBOPT']);
			$GLOBALS['SYSDB']['DBCFG']  = $dbCfg;
		}
		
		//获取对应的cache配置信息
		if( validID($cacheid) )
		{
			$cSql  = new TableSql('hic_cached','ID');
			$GLOBALS['SYSDB']['CACHE']  = $cSql->queryByID( $cacheid, 'PORT,SERVER,MEMUSER,MEMPWD' );
		}
		
		if( 'cli' != PHP_SAPI )
		{
			$_SESSION['SYSDB'] = $GLOBALS['SYSDB'];
		}
		return true;
	}
	
	static function initDBEnvByItem($itemid)
	{
		$cHIC  = new TableSql('hic_hicitem','ID');
		$info  = $cHIC->query('DBCFG,CACHEDID','ID=?',array($itemid ));
		if ( $info == NULL )
		{
			$_SESSION = array();
			return false; //系统还没初始化
		}
		return self::initDBEnvInner($info['DBCFG'],$info['CACHEDID'],0,$itemid);
	}

	static function initDBEnv($hicid)
	{
		if ( DSTP_CLU != CLU_CLOUD )
		{
			return true;
		}

		//云环境下继续初始化对应的数据访问信息
		$cHIC  = new TableSql('hic_hic','ID');
		$info  = $cHIC->query('DBCFG,CACHEDID,PHYID','ID=?',array($hicid ));
		if ( $info == NULL )
		{
			$_SESSION = array();
			return false; //系统还没初始化
		}
		$itemid = 0;
		if( is_numeric($info['PHYID']) )
		{
			$itemid = $info['PHYID'];
		}

		return self::initDBEnvInner($info['DBCFG'],$info['CACHEDID'],$hicid,$itemid);
	}

	static function isBindUser()
	{
		if ( 'c' == HIC_LOCAL )
		{
			return true;
		}
		$c = new TableSql('hic_user');
		if ( 0 == $c->getRecordNum() )
		{
			return false;
		}
		return true;
	}
	//生成设置自动登陆的cookie
	static function genLoginCookie($user,$hicid)
	{
		//优先获取一个已经存在的token，避免大量的同步或者生成
		$c = new TableSql('hic_frameautologin');
		$token = $c->queryValue('LOGINFLAG','HICID=? AND USERID=?',array($hicid,$user));
		if( NULL != $token )
		{
			return $token;
		}
		
		$randFlag  = substr(md5( mt_rand().mt_rand().mt_rand() ),5,10);
		$logflag   = $user.'-'.time().'-'.$hicid.'-'.$randFlag;
		$otherflag = substr(md5( $logflag ),5,10);
		$otherflag = substr(md5( $otherflag ),5,10);
		$logflag   = $logflag.'-'.$otherflag;

		self::setLoginCookie($logflag);
		return $logflag;
	}

	static function setLoginCookie($loginflag,$sync=true)
	{
		list($userid,$time,$hicid,$rand,$flag) = explode('-', $loginflag);
		//验证userid和hicid的绑定关系

		$info = array();
		$info['HICID']    = $hicid;
		$info['USERID']   = $userid;
		$info['LOGINFLAG']= $loginflag;
		$info['GENTIME'] = time();
		$info['USETIME'] = time();

		$c = new TableSql('hic_frameautologin');
		$c->add($info);

		//同步cookie给对端。
		if ( 'b' == HIC_LOCAL && $sync)
		{
			//如果断网情况下，这一步进入很慢。所以这儿使用延时任务
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('app','hic');
			$planTask->setLoginCookie($loginflag);	
		}

		return true;
	}
	
	//获取当前请求所来自的IP地址
	static function getLoginIP()
	{
		$ip = $_SERVER['REMOTE_ADDR'];
		if ( isset($_SERVER['HTTP_CLIENTIP']) )
		{
			$ip = $_SERVER['HTTP_CLIENTIP'];//BAE下remote_addr是内部地址
		}
		return intval(ip2long($ip));
	}
	
	//设置登录失败次数
	static function setCheckFail($reason)
	{
		$ip = self::getLoginIP();
	    $cachename = "$reason-$ip";
	    $d = Cache::get1($cachename);
	    $d = intval($d)+1;
	    Cache::set1($cachename, $d, 600);//10分钟后清除失败记录
	    return;
	}

	//检查本次是否需要验证码,返回值为空或者验证码链接
	static function checkNeedVCode($reseaon)
	{
	    $vcodeurl = '';

		$ip = self::getLoginIP();
	    $cachename = "$reseaon-$ip";

	    $d = Cache::get1($cachename);
	    $d = intval($d);//如果没有缓存则为0
	    if ( $d > 3 )
	    {
			//'http://'.$_SERVER['SERVER_NAME'].'/App/a/common/vcode.php'
			$vcodeurl = 'common/vcode.php';
	    }

	    return $vcodeurl;
	}
	//检查验证码输入是否正确
	static function checkVCode($reseaon,$vcode=NULL)
	{
		include_once('a/commonLang.php');
		// 判断是否要求输入注册码
	    $needVCode = self::checkNeedVCode($reseaon);
	    if ( '' == $needVCode )
	    {
	        return true;
	    }
		if( NULL == $vcode )
		{
			return LOGIN_ERR_VCODE;
		}

		if ( session_status() !== PHP_SESSION_ACTIVE) 
		{
			session_start();
		}		
	    //没检查到验证注册码，直接返回检查出错
	    if ( !isset( $_SESSION['VCODE'] ) )
	    {
	        return LOGIN_ERR_VCODE;
	    }
		
		//后面在判断到错误后就会清除cache，因此这儿暂时不需要判断验证码试验次数
		//$ip = self::getLoginIP();
	    //$cachename = "$reseaon-$ip";
	    //$d = Cache::get1($cachename);
	    //$d = intval($d);//如果没有缓存则为0
	    //if ( $d > 8 )
	    //{
	    //    return LOGIN_OVERMUCH_VCODE;
	    //}

	    if ( trim($vcode) != $_SESSION['VCODE'] )
	    {
			unset($_SESSION['VCODE']);
	        self::setCheckFail($reseaon);
	        return LOGIN_ERR_VCODE;
	    }

	    return true;
	}
	
	//检查本地是否有指定的token
	static function checkToken($token)
	{
		$c = new TableSql('hic_frameautologin');
		$r = $c->query('LOGINFLAG','LOGINFLAG=?',array($token));
		if ( NULL != $r )
		{
			return $token;			
		}		
		return false;
	}
	
	//根据token判断是否能登陆到指定的hicid系统中.如果可以，返回登陆信息
	//首先本地是否可以登陆，是否登陆本地，如果可以，则直接返回本地信息
	//否则发送给服务器确认
	static function loginByToken($token,$hicid=NULL)
	{
		if( NULL == $token )
		{
			return false;
		}

		if( 'b' == HIC_LOCAL )
		{
			return self::loginByTokenInB($token,$hicid);
		}
		return self::loginByTokenInC($token,$hicid);
	}

	private static function loginByTokenInB($token,$hicid)
	{
		if( (NULL != $hicid) && ($hicid != HICInfo::getHICID()) )
		{
			//不是要登陆本地的，直接向对端请求
			$GLOBALS['dstpSoap']->setModule('app','hic');
			return $GLOBALS['dstpSoap']->loginByToken($token,$hicid);
		}

		$c = new TableSql('hic_frameautologin');
		$r = $c->query('LOGINFLAG','LOGINFLAG=?',array($token));
		if ( NULL != $r )
		{
			//本地就有，登陆成功
			return $token;			
		}
		
		//向对端请求验证，如果可以，自动登陆并保存本地token
		$GLOBALS['dstpSoap']->setModule('app','hic');
		$token = $GLOBALS['dstpSoap']->loginByToken($token);
		if( false == $token )
		{
			return false;
		}

		self::setLoginCookie($token,false);

		return $token;
	}

	private static function loginByTokenInC($token,$hicid)
	{
		$c = new TableSql('hic_frameautologin');
		$r = $c->query('LOGINFLAG','LOGINFLAG=?',array($token));
		if ( NULL == $r )
		{
			//服务器如果没有该token，直接登陆失败
			return false;			
		}
		
		//检测用户和hicid的绑定关系
		list($userid,$time,$oldhic,$rand,$flag) = explode('-', $token);
		if( NULL == $hicid )
		{
			$hicid = $oldhic;
		}
		if( 0xFFFFFFFF == $hicid )
		{
			//查询当前是否有可绑定的hicid，如果有，直接使用该hicid重新生成
			//因为有可能是登陆后新建了一个，如果这时还返回原虚拟hic就很奇怪
			$GLOBALS['curUserID'] = $userid;
			$hicid = HICInfo::getNewHICID();
			if( validID($hicid) )
			{
				$token = self::genLoginCookie($userid, $hicid);
			}				
			return $token;//没有hic的token，直接返回不检查绑定关系
		}		
		
        $c = new TableSql('hic_hicbind');
        $userid = $c->queryValue('USERID','USERID=? AND HICID=?',array($userid,$hicid));
		if( !validID($userid) )
		{
			return false;
		}
		if( $hicid == $oldhic )
		{
			return $token;
		}
		
		//重新生成新登陆token
        return self::genLoginCookie($userid, $hicid);
	}

	static function loginByUserPsw($user,$psw,$hicid=NULL)
	{
		if( 'b' == HIC_LOCAL )
		{
			return self::loginByUserPswInB($user,$psw,$hicid);
		}
		return self::loginByUserPswInC($user,$psw,$hicid);
		
	}
	
	private static function checkUserPsw($user,$psw)
	{
		if( NULL == $user )
		{
			return NULL;
		}
		$userid = NULL;
		$isPhone = UTIL::isPhone($user);
		$isMail  = UTIL::isMail($user);
		if( $isPhone || $isMail )
		{
			$field = 'PHONE';
			if($isMail)
			{
				$field = 'EMAIL';
			}
			$c = new TableSql('hic_userinfo');
			$userid = $c->queryValue('USERID',"$field = ?",array($user));
			if( validID($userid) )
			{
				//兼容之前的用户名
				$c = new TableSql('hic_user');
				$userid = $c->queryValue('ID','ID=? AND PSW=?',array($userid,HICInfo::cryptPsw($psw)));
			}
		}
		if( NULL == $userid )
		{
			$c = new TableSql('hic_user');
			$userid = $c->queryValue('ID','NAME=? AND PSW=?',array($user,HICInfo::cryptPsw($psw)));
		}
		return $userid;
	}
	
	private static function loginByUserPswInB($user,$psw,$hicid)
	{
		if( (NULL != $hicid) && ($hicid != HICInfo::getHICID()) )
		{
			//不是要登陆本地的，直接向对端请求
			$GLOBALS['dstpSoap']->setModule('app','hic');
			return $GLOBALS['dstpSoap']->loginByUserPsw($user,$psw,$hicid);
		}
		
		$userid = self::checkUserPsw($user,$psw);
		if ( NULL != $userid )
		{
			if( NULL == $hicid )
			{
				$hicid = HICInfo::getHICID();
			}			
			//重新生成新登陆token
			return self::genLoginCookie($userid, $hicid);
		}
		
		//向对端请求验证，如果可以，自动登陆并保存本地token
		$GLOBALS['dstpSoap']->setModule('app','hic');
		$token = $GLOBALS['dstpSoap']->loginByUserPsw($user,$psw);
		if( false == $token )
		{
			return false;
		}
		
		//如果不是当前HICID，无需写本地
		list($userid,$time,$hicid,$rand,$flag) = explode('-', $token);
		if($hicid == HICInfo::getHICID())
		{
			//从对端获得，这儿不需要再同步给对端
			self::setLoginCookie($token,false);
		}
		

		return $token;		
	}

	private static function loginByUserPswInC($user,$psw,$hicid)
	{
		$userid = self::checkUserPsw($user,$psw);
		if ( NULL == $userid )
		{
			return false;
		}
		
		if( NULL == $hicid )
		{
			$GLOBALS['curUserID'] = $userid;
			$hicid = HICInfo::getNewHICID();
			if( !validID($hicid) )
			{
				//返回一个非法的hicid的token
				return self::genLoginCookie($userid, 0xFFFFFFFF);
			}
		}
		//检测用户和hicid的绑定关系
        $c = new TableSql('hic_hicbind');
        $userid = $c->queryValue('USERID','USERID=? AND HICID=?',array($userid,$hicid));
		if( !validID($userid) )
		{
			return false;
		}
		
		//重新生成新登陆token
        return self::genLoginCookie($userid, $hicid);
	}

	static function checkLoginCookie($value)
	{
		$c = new TableSql('hic_frameautologin');

		$r = $c->query('*','LOGINFLAG=?',array($value));
		if ( NULL != $r )
		{
			return true;
		}
		if ( 'b' == HIC_LOCAL )//向对端请求
		{
			$GLOBALS['dstpSoap']->setModule('app','hic');
			$r = $GLOBALS['dstpSoap']->checkLoginCookie($value);
			return $r;
		}
		return false;
	}

	static function checkLoginUser($user,$psw)
	{
		$c = new TableSql('hic_user');
		$r = $c->query('*','NAME=? AND PSW=?',array($user,HICInfo::cryptPsw($psw)));
		if ( NULL != $r )
		{
			return true;
		}
		if ( 'b' == HIC_LOCAL )//向对端请求
		{
			$GLOBALS['dstpSoap']->setModule('app','hic');
			$r = $GLOBALS['dstpSoap']->checkLoginUser($user,$psw);
			return $r;
		}
		return false;
	}

	/***************************************/
	static function getUserBindList($userid)
	{
		$GLOBALS['dstpSoap']->setModule('app','hic');
		$hicList = $GLOBALS['dstpSoap']->getUserBindList($GLOBALS['curUserID']);
		if ( false !== $hicList )
		{
			return $hicList;
		}
		if ( 'b' != HIC_LOCAL )
		{
			return array();
		}
		//如果从c那儿网络访问出错，则至少加入自身
		$c = new TableSql('hic_hic');
		$tmp = array();
		$tmp[] = $c->query('ID,NAME');
		return $tmp;
	}


}
?>