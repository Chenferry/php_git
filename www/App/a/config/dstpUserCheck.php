<?php
    function redireToLogin()
    {
		//从摄像头打开的，如果不能打开，也就不打开了。直接停止运行即可
		if( isset($_GET['rtsptoken']) )
		{
			die();
		}
		if( isset($_GET['fromrtsp']) )
		{
			die();
		}
		
		echo 'logout';
        exit();
		
        //转向主页面
        //header('Location:'.HICInfo::getAUrl().'/frame/logout.php');
        //exit();
    }

	//检查token的正确性
    $GLOBALS['dstpSoap']->setModule('frame');
    $hicToken = $GLOBALS['dstpSoap']->loginByToken( getHICToken() );
	if( false == $hicToken )
	{
		//在断网情况下。有可能本地登陆保存了token，当联网时，其请求C时，会找不到token
		//如果这时直接返回logout，会因为返回到登陆页面本地又检测通过导致不停退出登陆
		//所以如果是访问c的，需要再判断下该token是否在本地是否存在
		if ( 'c' != HIC_LOCAL )
		{
			//访问b则直接返回。如果是访问服务器中的a，这个不是前面所述错误，也直接退出
			redireToLogin();
		}
		//如果是item的，也无需处理
		
		list($userid,$time,$hicid,$rand,$flag) = explode('-', $hicToken); 
		$GLOBALS['dstpSoap']->setModule('local','local',$hicid);
		$hicToken = $GLOBALS['dstpSoap']->checkToken(getHICToken());
		if( false == $hicToken )
		{
			die();//为了避免登陆一直被弹出，这儿不进行很强制退出
			redireToLogin();
		}
		
		$GLOBALS['dstpSoap']->setModule('frame');
        $GLOBALS['dstpSoap']->setLoginCookie($hicToken);
	}
	
	list($userid,$time,$hicid,$rand,$flag) = explode('-', $hicToken); 
	if( 'b' == HIC_LOCAL )
	{
		//不是本机token不检查，直接导向login去处理
		if( $hicid != HICInfo::getHICID() )
		{
			redireToLogin();
		}
	}
	else
	{
		//直接登陆token所指示的主机
		$GLOBALS['curHICID'] = $hicid;
	}


    //用户登陆判断
    if (!isset($_SESSION['loginUserID']) || $_SESSION['loginUserID']!=$userid)
    {
        //先清空session，预防前一个登陆用户的残留信息
        $_SESSION = array();
        $_SESSION['loginUserID']   = $userid;
        $_SESSION['loginUserName'] = getUserName($userid);
		$_SESSION['loginHICID']    = $hicid;
    }

    //这儿要判断，当前用户和所绑定的phyid是否有关联。如果没有，则需要强制退出重登录
    $GLOBALS['curUserID']    = $_SESSION['loginUserID'];
    $GLOBALS['curUserName']  = $_SESSION['loginUserName'];
	
	//云环境需要进行初始化
	if( DSTP_CLU == CLU_CLOUD )
	{
		if( isset($_SESSION['SYSDB']) && ($_SESSION['SYSDB']['SYSUID'] == $GLOBALS['curHICID'] ) )
		{
			$GLOBALS['SYSDB'] = $_SESSION['SYSDB'];
		}
		else
		{
			$GLOBALS['dstpSoap']->setModule('frame');
			$GLOBALS['dstpSoap']->initDBEnv($GLOBALS['curHICID']);
		}
	}
    
?>