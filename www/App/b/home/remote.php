<?php
//加载设备页面。get可能参数
include_once('../../a/config/dstpCommonInclude.php');  

//////////////公共函数///////////////////
//改变设备类型
function setDevType($id,$devtype)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$cachename = 'ircache_1';
	$ircache = Cache::get($cachename);
	$ircache['type'] = $devtype;
	Cache::set($cachename,$ircache,20);
	return true;
}

//关闭学习
function closeCatch($id)
{
	$cachename = 'ircache_1';
	Cache::del($cachename);
	//不需要发送该命令。学习中无法接受命令，反而经常导致进入未知状态，等超时后红外自动关闭
	//$GLOBALS['dstpSoap']->setModule('home','remote');
	//$GLOBALS['dstpSoap']->sendRemoteCtrlMsg($id, REMOTE_CMD_CLOSE_CATCH);
}

//开始学习
function innerCatch($id,$cachename,$cacheinfo)
{
	//$ircache =  Cache::get('ircache_2');
	//if ( false != $ircache )
	//{
	//	//如果cache存在的，则避免重复发出，直接返回
	//	//现在重复发出学习指令，红外设备那会进入不可预期状态。由后台避免
	//	//return true;
	//}
	//$ircache =  Cache::get('ircache_1');
	//if ( false != $ircache )
	//{
	//	//return true;
	//}

	Cache::set($cachename,$cacheinfo,22);
	
	$cmd = REMOTE_CMD_START_CATCH;
	if( 'ircache_2' == $cachename )
	{
		$cmd = REMOTE_CMD_START_CATCH2;
	}
	
	//向设备发送开始学习命令
	$attrList = array();
	$attrList[] = array('ID'=>$id, 'ATTR'=>$cmd);

	$c = new TableSql('homeattr','ID');
	$devid = $c->queryValue('DEVID','ID=?',array($id));

	$GLOBALS['dstpSoap']->setModule('home','end');
	$r = $GLOBALS['dstpSoap']->sendMsg($devid, $attrList);
	if ( $r )
	{
		//如果成功发了学习命令下去，红外学习设备20秒内不再做处理
		//因此要把报状态时间人为往后加20秒，避免超时检查
		$info = array();
		$info['ID'] = $devid;
		$info['ETIME'] = time()+20;
		$c = new TableSql('homedev','ID');
		$c->update($info);
	}
	return true;
}


//////////////空调设备学习相关///////////////////////
//开始学习
function startCatch($id,$devtype)
{
	//开始学习前，清空学习缓存
	$cacheinfo   = array(
		'id'     => $id,
		'type'   => $devtype,
		'result' => array(),
		'match'  => 0,
	);
	
	return innerCatch($id,'ircache_1',$cacheinfo);
}

//检查空调学习匹配结果
function getCatchResult($id,$devtype=DEV_REMOTE_AIR)
{
	$cachename = 'ircache_1';
	$ircache = Cache::get($cachename);
	if( false == $ircache )
	{
		//已经超时没学习，直接返回失败
		return -1;
	}

	if ( 0 != $ircache['match'] 
		&& 1 != $ircache['match'])
	{
		$_SESSION['ircache1'] = $ircache;
		Cache::del($cachename);
	}
	return $ircache['match'];
}

//把用户确认的空调设备添加到数据库中
//根据用户选择的空调型号，到服务器获取空调码库信息
function addRemote($id,$devid)
{
	//根据id判断处理接口
	$h = substr($devid,0,4);
	switch($h)
	{
		case 'hxd_':
			$GLOBALS['dstpSoap']->setModule('home','hxd');
			break;
		default:
			$GLOBALS['dstpSoap']->setModule('home','remote');
			break;
	}
	
	$r = $GLOBALS['dstpSoap']->addRemote($id,$devid);
	if ( false == $r )
	{
		return $GLOBALS['dstpSoap']->getErr();
	}
	statusNotice('dev');
	return $r;
}

//////////////用户设备学习相关/////////////////////////////////////
//新建一个用户设备
function startUserdev($id,$name)
{
	if( NULL == trim($name) )
	{
		return INVALID_ID;
	}
	
	//往给remote下增加一个hwxx属性
	$GLOBALS['dstpSoap']->setModule('home','remote');
	$r = $GLOBALS['dstpSoap']->addUserRemote($id,$name);
	if( !validID($r) )
	{
		return INVALID_ID;
	}
	return $r;
}

//开始学习按键
function startUserCatch($id)
{
	//开始学习前，清空学习缓存
	$cacheinfo   = array(
		'id'     => $id,
		'result' => array(),
		'match'  => 0,
	);
	
	//根据id获取红外学习的id
	$c = new TableSQL('homeattr','ID');
	$cfg = $c->queryValue('CFGINFO','ID=?',array($id));
	$cfg = unserialize($cfg);
	if(false == $cfg)
	{
		return false;
	}

	$rid = $cfg['rid'];
	
	return innerCatch($cfg['rid'],'ircache_2',$cacheinfo);
}


//检查用户按键学习结果
function getUserCatchResult()
{
	$cachename = 'ircache_2';
	$ircache = Cache::get($cachename);
	if( false == $ircache )
	{
		//已经超时没学习，直接返回失败
		return -1;
	}
	if ( 0 == $ircache['match'] )
	{
		return 0;
	}
	$_SESSION['ircache2'] = $ircache;
	Cache::del($cachename);
	return 1;
}

//根据用户确认添加学习按键
function addUserRemote($id,$name)
{
	//向服务器请求下载IR数据
	$GLOBALS['dstpSoap']->setModule('home','remote');
	$r = $GLOBALS['dstpSoap']->addUserRemoteBut($id,$name);
	if ( false == $r )
	{
		return $GLOBALS['dstpSoap']->getErr();
	}
	return $r;
}

//删除用户学习按键
function delUserRemoteBut($id,$bid)
{
	//向服务器请求下载IR数据
	$c = new TableSQL('homeattr','ID');
	$setinfo = $c->queryValue('ATTRSET','ID=?',array($id));
	$setinfo = unserialize($setinfo);
	if(false == $setinfo )
	{
		return true;
	}
	$delkey = -1;
	foreach($setinfo as $key=>&$set)
	{
		if( $set['id'] == $bid)
		{
			$delkey = $key;
			break;
		}
	}
	if( -1 != $delkey )
	{
		unset($setinfo[$delkey]);
	}
	$info = array();
	$info['ID'] = $id;
	$info['ATTRSET'] = serialize($setinfo);
	$c->update($info);
	return true;
}

//给用户学习按键改名
function changeUserRemoteBut($id,$bid,$name)
{
	if( NULL == $name )
	{
		return false;
	}
	$c = new TableSQL('homeattr','ID');
	$setinfo = $c->queryValue('ATTRSET','ID=?',array($id));
	$setinfo = unserialize($setinfo);
	if(false == $setinfo )
	{
		return true;
	}
	$delkey = -1;
	foreach($setinfo as $key=>&$set)
	{
		if( $set['id'] == $bid)
		{
			$delkey = $key;
			break;
		}
	}
	if( -1 != $delkey )
	{
		$setinfo[$delkey]['name'] = $name;
	}
	$info = array();
	$info['ID'] = $id;
	$info['ATTRSET'] = serialize($setinfo);
	$c->update($info);
	return true;
	
}

///////////////////////////////////


util::startSajax( array('startUserdev','startUserCatch','startCatch',
					'setDevType','closeCatch',
					'getUserCatchResult','getCatchResult',
					'addUserRemote','addRemote',
					'delUserRemoteBut','changeUserRemoteBut'
					));

?>