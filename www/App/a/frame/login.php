<?php
if ( !isset($_SESSION) )
{
	session_start();
}

require_once('../../a/config/dstpCommonInfo.php');
include_once('a/commonLang.php');
if( 'b' == HIC_LOCAL )
{
    $c = new TableSql('hic_user');
    $c->queryAll();
    if( $c->getSqlError() && $c->getSqlError() == 'SQLSTATE[HY000]: General error: 11 database disk image is malformed' )
    {
        `echo '.dump' | sqlite3 /tmp/hdang.db | sqlite3 /tmp/new.db`;
        `cp  /tmp/new.db  /tmp/hdang.db `;
        `rm  /tmp/new.db`;
    }   
}
if( 'b' == HIC_LOCAL && !DSTP_DEBUG)
{
	//如果是复位初始化，可能HICCFG需要重新更新获得
	$ip = $_SERVER['REMOTE_ADDR'];
	if ( isset($_SERVER['HTTP_CLIENTIP']) )
	{
		$ip = $_SERVER['HTTP_CLIENTIP'];//BAE下remote_addr是内部地址
	}	
	
	$c	= new TableSql('homeclient','ID');
	$info = $c->query('ID,PERIOD','IP=?',array($ip));
	if( NULL == $info )
	{
		$mac = NULL;
		$name = NULL;
		include_once('uci/uci.class.php');	
		uci_base::getInfoByIP($ip,$mac,$name);
		$info = $c->query('ID,PERIOD','MAC=?',array($mac));
		if( NULL != $info )
		{
			$info['IP'] = $ip;
			$c->update($info);
		}
	}
	$period = $info['PERIOD'];

	switch( $period )
	{
		case DEV_CLIENT_INIT:
            $GLOBALS['dstpSoap']->setModule('frame');
            if($GLOBALS['dstpSoap']->isBindUser())
            {
				if( !isset($_REQUEST['rs']) )
				{
					$url = 'http://'.a_jia_sx.'/UI/indexnew.html';
					header("Location:$url");
				}
            }
            break;
		case DEV_CLIENT_REQUEST:
			include_once('uci/uci.class.php');	
			$wifiInfo = SSID::getSSID();
			$enc = trim($wifiInfo['encryption']);
            if (empty($enc) || ('none' == $enc) || !isset($_REQUEST['rs']) )
			{
				$url = 'http://'.a_jia_sx.'/UI/indexnew.html';
                header("Location:$url");
                exit();
            }
			break;
		case DEV_CLIENT_REJECT:
			die();
			break;
		default:
			break;
	}
}


function changeHIC($hicid)
{
	$hicToken = getHICToken();
    $GLOBALS['dstpSoap']->setModule('frame');
    $hicToken = $GLOBALS['dstpSoap']->loginByToken($hicToken,$hicid);
	return $hicToken;
}

function logout()
{
	setcookie('hicautologin','',time(),'/',a_jia_sx);
	session_unset();
	session_destroy();
	return true;
}


function getLoginKey(&$loginUser,&$loginPsw)
{
	//在其它家庭网络登陆时，其跳转是使用get方式传递参数的
	if( isset($_GET['LoginName']) )
	{
		$loginUser = urldecode($_GET['LoginName']);
		$loginPsw  = urldecode($_GET['LoginPsw']);
	}
	//但如果有post数据，用户密码优先使用post数据
	if ( !empty($_POST['LoginName']) )
	{
		$loginUser = $_POST['LoginName'];
		$loginPsw  = $_POST['Psw'];
	}
    $loginUser = strtolower(trim($loginUser));
    $loginPsw  = trim($loginPsw);
	return;
}


function login($user,$psw,$vcode=NULL)
{
    $user = strtolower(trim($user));
    $psw  = trim($psw);
	
	//定义验证码请求原因
	$vReason = 'logincheck';

    $GLOBALS['dstpSoap']->setModule('frame');
    $result = $GLOBALS['dstpSoap']->checkVCode($vReason,$vcode);
    if ( true === $result )
    {
        //继续检查用户密码。如果检测成功，则在该函数会跳转
        $result = checkUserLogin($user, $psw);
    }	
	if( !is_array( $result ) )
	{
		$err    = $result;
		$result = array();
		$result['login'] = false;
		$result['info']  = $err;
		$GLOBALS['dstpSoap']->setModule('frame');
		$result['vcode'] = $GLOBALS['dstpSoap']->checkNeedVCode($vReason);
	}
	return $result;
}

//判断HIC是否已经初始化
function isInitHIC()
{
	$r = array();
	$r['init']    =  true;
	$r['active']  =  true;
	$r['connect'] =  true;
	$r['isname']  =  true;
	$GLOBALS['dstpSoap']->setModule('frame');
	$r['vcode'] = $GLOBALS['dstpSoap']->checkNeedVCode('logincheck');

	
	//b需要判断是否已经登陆
	if( 'b'==HIC_LOCAL )
	{
		$GLOBALS['dstpSoap']->setModule('frame');
		$r['init'] = $GLOBALS['dstpSoap']->isBindUser();
		if( !$r['init'] ) //如果还没初始化，则可能还需要知道是否已经联网
		{
			//判断是否已经激活，如果还没激活，要显示错误信息
			$GLOBALS['dstpSoap']->setModule('local','sn');
			if( false == $GLOBALS['dstpSoap']->getSN() )
			{
				$r['active']  =  false;
			}
				
			$GLOBALS['dstpSoap']->setModule('local','local');
			$r['connect'] = $GLOBALS['dstpSoap']->isConnectToCloud();
		}
	}
	if( !$r['init'] )
	{
		return $r;
	}
	
	//继续判断是否能自动登陆
	$result = userAutoLogin( );
	if( false == $result)
	{
		$r['login']   = false;
		$r['info']	  = '';
	}
	else
	{
		$r['login']   = true;
		$r['info']	  = $result['info'];
		$r['nohic']	  = $result['nohic'];
		$r['domain']  = $result['domain'];
	}

	return $r;
}
util::startSajax( array('login','changeHIC','isInitHIC','logout'));


$GLOBALS['logindomain'] = NULL;
$checkResult = NULL;


/////////////////////////////////////////////////////////
$loginUser = NULL;
$loginPsw  = NULL;
getLoginKey($loginUser,$loginPsw);


//如果输入了用户名登陆
$result = false;
if ( NULL != $loginUser )
{
    //先判断是否要求验证码，如果要求验证码则进行检查。
    //检查验证码输入是否正确
    $GLOBALS['dstpSoap']->setModule('frame');
    $result = $GLOBALS['dstpSoap']->checkVCode('logincheck',$_POST['vcode']);
    if ( true === $result )
    {
        //继续检查用户密码。如果检测成功，则在该函数会跳转
        $result = checkUserLogin($loginUser, $loginPsw);
    }
}

//检测自动登陆
if( !is_array($result) )
{
	//这时result保存了登陆错误信息，但userAutoLogin不会有其它错误信息
	//为了避免自动登陆破坏了保存的错误信息，用$r临时存储userAutoLogin的返回值
	$r = userAutoLogin( );
	if( false != $r )
	{
		$result = $r;
	}		
}

// 如果可以自动登陆，则在这儿就会自动登陆不会再继续往下走
if( !is_array($result) )
{
	$result = array();
	$result['login'] = false;
	$GLOBALS['dstpSoap']->setModule('frame');
	$result['vcode'] = $GLOBALS['dstpSoap']->checkNeedVCode('logincheck');
	$result['info']  = $result;
}

//如果当前访问域名和主域名不一致，则重定向到主域名继续访问
if ( NULL != $loginUser )
{
	echo json_encode($result);
}
//else
//{
//	//直接导向登陆页面即可
//	$url = 'http://'.a_jia_sx.'/UI/index.html';
//    header("Location:$url");	
//}
die();

////////////////////////////////////////////////////////

//根据链接信息，返回指定的需要登陆的系统用户名
function getLoginName()
{
    $name = NULL;
    if ( isset($_GET['username']) )
    {
        $name = $_GET['username'];
    }
    return $name;
}

// 如果可以自动登陆，则在这儿就会自动登陆
//a.该路由器已绑定云，则忽略其对路由器的访问，导向外网c.jia.sx自动登陆；
function userAutoLogin( )
{
	$hicToken = getHICToken();
    $GLOBALS['dstpSoap']->setModule('frame');
    $hicToken = $GLOBALS['dstpSoap']->loginByToken($hicToken);	
	if( false == $hicToken )
	{
		return false;
	}

    //通过判断，则直接登陆
    return checkUserOKProc($hicToken);
}

//先检测是否本地用户。如果是本地用户，进行密码验证
//如果本地用户检测错误，则需要定向到云服务器上进行处理。
//返回值：array,登陆结果
//        string,错误信息
function checkUserLogin($user, $psw)
{
	//先检查用户密码，而不是先检查本地用户是否存在
	//因为检查密码会同时检测c。在别人家庭登陆。如果是直接检测本地用户，发现不存在，直接跳转到c
	//当网络没通时，不是给错误提示，而是会产生连接错误用户友好性不好
    $GLOBALS['dstpSoap']->setModule('frame');
    $hicToken = $GLOBALS['dstpSoap']->loginByUserPsw($user,$psw);
    if ( $hicToken )
    {
        return checkUserOKProc($hicToken);
    }
    else
    {
        //验证码错误标记
        $GLOBALS['dstpSoap']->setCheckFail('logincheck');
        setLoginLog($user,false);
		return USER_PSW_ERR;
    }
	return USER_PSW_ERR;
}

//登陆成功后的SESSION初始化
function initLoginSession($userid,$hicid)
{
	if( 'b' == HIC_LOCAL && ($hicid != HICInfo::getHICID()) )
	{
		//如果不是登陆本机的，则本机无需初始化处理
		return true;
	}
    //全部清空一遍
    $_SESSION = array();

    $_SESSION['loginUserID']   = $userid;
    $_SESSION['loginUserName'] = getUserName($userid);
	$_SESSION['loginHICID']    = $hicid;
	
    //初始化数据库访问所需的session变量
    $GLOBALS['dstpSoap']->setModule('frame');
    return $GLOBALS['dstpSoap']->initDBEnv($_SESSION['loginHICID']);
}

//用户登录检查OK后的处理
//这个函数重点要处理下，当在别人家连接，但被导向了c时的处理是否正确
function checkUserOKProc($hicToken=NULL)
{
	list($userid,$time,$hicid,$rand,$flag) = explode('-', $hicToken);
    //登陆后的session信息准备
    initLoginSession($userid,$hicid);
    
    //登陆时通知
    if ( 'b' == HIC_LOCAL )
    {
        setDevSleep(2);
    }
    else
    {
		//如果断网时，这一步会很慢，就等websocket的通知了
        //$GLOBALS['dstpSoap']->setModule('local','local');
        //$GLOBALS['dstpSoap']->loginNotice(2);
    }
	
	//IE下好像不能添加最后一个a_jia_sx？
    header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
    setcookie('hicautologin',$hicToken,time()+86400*3650,'/',a_jia_sx);
	

    setLoginLog($userid,true);

	$result = array();
	$result['login'] = true;
	$result['info']  = $hicToken;
	$result['nohic'] = false;
	if( 0xFFFFFFFF == $hicid )
	{
		$result['nohic'] = true;
	}
	
	//判断token是否使用本地或者使用对端
	$result['domain'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['HTTP_HOST'] : a_jia_sx;
	if( 'b' == HIC_LOCAL && ($hicid != HICInfo::getHICID()) )
	{
		//如果不是登陆本机的，则返回服务器地址重新处理
		$result['domain'] = c_jia_sx;
	}

	return $result;
}

function setLoginLog($userid,$r)
{
    return;
    if ( 'b' == HIC_LOCAL )
    {
        return true;//信息中心就没必要保存这些信息了
    }

    $log = array();
    $log['DATETIME'] = time();
    $log['IP']       = $GLOBALS['ip'];
    $log['USERID']   = $userid;
    $log['RESULT']   = $r?1:0;

    $c = new TableSql('frameLoginLog');
    return $c->add($log);
}


?>
