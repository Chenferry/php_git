<?php
//加载设备页面。get可能参数
//id:attr id

include_once('../../a/config/dstpCommonInclude.php');  
include_once('class.attr.inc.php');  

function execAttr($id,$cmd)
{
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],$id,'attr');
	if( !$r )
	{
		return false;
	}	

	$c 	 = new TableSql('homeattr','ID');
	$cfg = $c->queryValue('ATTRSET','ID=?',array($id));
	$cfg = unserialize($cfg);
	//cfg有可能没设置，这儿会有错误信息，需要抑制
	@$password = $cfg['password'];
	$password  = trim($password);
	
	if( (6 == $cmd['m']) && ( NULL != $password ) ) 
	{
		//设置超级管理员，需要检验当前还没设置。否则需要走修改流程
		return 0;
	}
	//如果是m=7。需要检查密码只能输入6-10位的数字，不能是其它信息
	if( (7 == $cmd['m']) && (!preg_match('/^\d{6,8}$/',$cmd['password'])))
	{
		return 0;
	}

	if( (6 != $cmd['m']) && (11 != $cmd['m']) )
	{
		//检验密码正确性。如果密码不正确，一段时间内不准太多次错误
		$ksname = 'ks_'.$GLOBALS['curUserID'];
		$d = Cache::get($ksname);
		if( intval($d)>4 )
		{
			return -1;
		}
		if( $password != $cmd['pw'] )
		{
		    $d = intval($d)+1;
		    Cache::set($ksname, $d, 120);		
		    return 0;
		}	
	}

	$GLOBALS['dstpSoap']->setModule('devattr','attr');
	return $GLOBALS['dstpSoap']->execAttr($id,$cmd);
}

//获取指定门锁相同指纹器型号的所有门锁
function getRecord($attrid,$ut)
{
	$result = array();
	$c = new TableSql('homeattr','ID');
	$msInfo = $c->queryValue('CFGINFO','ID=?',array($attrid));
	$cfg = unserialize($msInfo);
	if( !isset($cfg['recordtype'][$ut]) )
	{
		//return $result;
	}
	$recordtype = $cfg['recordtype'][$ut];
	
	
	$msList = $c->queryAll('ID,NAME,CFGINFO','SYSNAME=?',array('ms'));
	foreach( $msList as &$ms )
	{
		$cfg = unserialize($ms['CFGINFO']);
		if( !isset($cfg['recordtype'][$ut]) )
		{
			//continue;
		}
		if( $cfg['recordtype'][$ut] != $recordtype )
		{
			//continue;
		}
		if( $ms['ID'] == $attrid )
		{
			//continue;
		}
		$result[] = $ms;
	}
	return $result;
}

//在锁的页面状态中，会定时调用本函数。用来通知唤醒锁进行准备操作
function msNotice($id)
{
	Cache::set1("msnotice_$id",$id,60);
	$ms = Cache::get1('msnotice');//不同hicid共享同一个缓存。
	if( isset($ms[$id]) ) 
	{
		//已经发送过通知，无需再处理
		return;
	}
	$c = new TableSql('homeattr','ID');
	$dev = $c->queryValue('DEVID','ID=?',array($id));
	Cache::set('mssleep',1);
	$GLOBALS['dstpSoap']->setModule('home','if');
	$GLOBALS['dstpSoap']->sendMsg($dev,DEV_CMD_HIC_CTRL_DEV,array());
	$sysuid = getSysUid();

	$ms[$id] = array( 'dev'=>$dev,'sys'=>$sysuid );
	Cache::set1('msnotice',$ms);
}

//用户开锁后，会定时读取是否有开锁回报状态
function getLockstatus($id)
{
	$lockstatus = Cache::get("doorlock_$id");
	if( false != $lockstatus )
	{
		Cache::del("doorlock_$id");
		return true;
	}
}

//删除门锁用户操作后，会定时读取是否有删除用户操作的回应
function delStatus($attrid,$type,$id)
{
	$status = Cache::get("deluser_$type_$id_$attrid");
	if( false != $status )
	{
		Cache::del("deluser_$type_$id_$attrid");
		return true;
	}
}

//添加门锁用户操作后，会定时读取是否有删除用户操作的回应
function addStatus($attrid)
{
	$status = Cache::get("adduser_$attrid");
	if( false != $status )
	{
		Cache::del("adduser_$attrid");
		return true;
	}
}

function ycAddStatus($attrid,$type,$target)
{
	$status = Cache::get("mscjfinish-$attrid-$type-$target");
	if( false != $status )
	{
		Cache::del("adduser_$attrid");
		return true;
	}
}


util::startSajax( array('execAttr','getLockstatus','delStatus','addStatus',
			'msNotice','ycAddStatus','getRecord') );

?>