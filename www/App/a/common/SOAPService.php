<?php
ob_start();
include_once('../config/dstpCommonInfo.php');

if( !isset($_POST['moduleName']) || !isset( $_POST['methodName'] ))
{
	$r = soapFault(false,"parm fail");
	ob_end_clean(); 
	echo serialize($r);
	exit(-1);
}

$GLOBALS['curHICID']    = isset($_POST['curHICID'])?$_POST['curHICID']:NULL;
if( isset($_POST['curUserID']) )
{
	$GLOBALS['curUserID']   = $_POST['curUserID'];
	$GLOBALS['curUserName'] = $_POST['curUserName'];
}
else
{
	$GLOBALS['curUserID']   = NULL;
	$GLOBALS['curUserName'] = NULL;
}

//取出物理ID和密钥进行鉴权。
$valid = NULL;
$c  = new TableSql('hic_hicinfo');
if( isset( $_POST['curSecure'] ) )
{
	if( 'b' == HIC_LOCAL )
	{	
		$valid = $c->queryValue('CHID','HICID=? AND HCID=?',array($GLOBALS['curHICID'], $_POST['curSecure']));
	}
	else
	{	
		$valid = $c->queryValue('HCID','HICID=? AND CHID=?',array($GLOBALS['curHICID'], $_POST['curSecure']));
	}
}

if ( NULL == $valid )
{
	//(app,init)的调用时无需鉴权的，所以需要特殊判断。如果该条件也不满足，就是鉴权失败返回
	if( 'c' != HIC_LOCAL || 'app' != $_POST['moduleName'] || 'init'!= $_POST['serviceName'])
	{
		$_POST['curSecure'] = isset( $_POST['curSecure'] )?$_POST['curSecure']:NULL;
		$r = soapFault(false,"soap check fail:$GLOBALS[curHICID], $_POST[curSecure]");
		ob_end_clean(); 
		echo serialize($r);
		exit(-1);
	}
}
else
{
	$GLOBALS['dstpSoap']->setModule('frame');
	$GLOBALS['dstpSoap']->initDBEnv($GLOBALS['curHICID']);
}

//有些虽然做成接口，但只允许本地调用。这些要处理

//检查用户和信息中心的的绑定关系是否正确，防止伪造用户.
//信息中心一般不会处理userid，所以暂时不检查
//有些是系统接口调用，没有有效用户名，这种也允许通过
if( 'c' == HIC_LOCAL && validID($GLOBALS['curUserID']) )
{
	$c = new TableSql('hic_hicbind'); 
	if ( 0 == $c->getRecordNum('HICID=? AND USERID=?', 
								array(HICInfo::getHICID(),$GLOBALS['curUserID'] )) )
	{
		//在init的changeLogo这些函数，有可能是跨数据库服务器请求的，所以在SN服务器不定能查找到hicid
		if( 'c' != HIC_LOCAL || 'app' != $_POST['moduleName'] || 'init'!= $_POST['serviceName'])
		{
			$r = soapFault(false,'soap bind fail');
			ob_end_clean(); 
			echo serialize($r);
			exit(-1);
		}
	}
}

if ( !isset( $_POST['methodArray'] ) )
{
	$_POST['methodArray'] = array();
}

//有些接口只能从本地调用。这个值用来让这些接口作校验
$GLOBALS['callFromRemote'] = true;


//根据参数设置环境，开始调用
$r = $GLOBALS['dstpSoap']->useMethod($_POST['moduleName'],$_POST['serviceName'], 
										$_POST['methodName'], $_POST['methodArray'] );
ob_end_clean(); 								

echo serialize($r);	

?>