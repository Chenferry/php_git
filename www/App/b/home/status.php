<?php
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

function getDevListNewFromRtsp($rtspid)
{
	$curRoom = -10;
	if( validID($rtspid) )
	{
		//直接查找相关摄像头所处的房间
		$c  = new TableSql('homeattr','ID');
		$rtspdev = $c->queryValue('DEVID','ID=?',array($$rtspid));	
		$c  = new TableSql('homedev','ID');
		$curRoom = $c->queryValue('ROOMID','ID=?',array($rtspdev));
	}	

	$r = getDevListJson(true);
	$c = new TableSql('smartgroup','ID'); 
	$r['group']   = $c->queryAll('ID,NAME', 'ISSHOW=1 ORDER BY ID');
	$r['curRoom'] = $curRoom;
	
	return $r;
}

function getAlarmInfo()
{
	$GLOBALS['dstpSoap']->setModule('frame','alarm');
	$alarmInfo = $GLOBALS['dstpSoap']->getAlarmDev();
	return $alarmInfo['sub'];
}


function getDevListJson($onlyCtrl=false)
{
	$result = array();

    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$access = $GLOBALS['dstpSoap']->getUserAccess($GLOBALS['curUserID']);
	
	$result['usertype'] = $access['type'];

	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$result['addr'] = $GLOBALS['dstpSoap']->getRoomListShow();

	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$result['map'] = $GLOBALS['dstpSoap']->getRoomAttrMap($onlyCtrl);

	$GLOBALS['dstpSoap']->setModule('devattr','devattr');
	$result['offline'] = $GLOBALS['dstpSoap']->getOffline();

	$GLOBALS['dstpSoap']->setModule('devattr','devattr');
	$result['page'] = $GLOBALS['dstpSoap']->getAttrValue();
	
	$GLOBALS['dstpSoap']->setModule('devattr','devattr');
	$result['layout'] = $GLOBALS['dstpSoap']->getAttrLayout();

	//天气，温度
	$result['tq'] = Cache::get('tqxx');
	if( $result['tq'] == false )
	{
		//断网时，不停的查询天气会把hicserver给挂死导致操作无法响应
		//include_once('plannedTask/PlannedTask.php');
		//$planTask 	  = new PlannedTask('home','envend',3);
		//$planTask->reportDevInfo();	
	}	

	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$result['favorite'] = $GLOBALS['dstpSoap']->getUserFavorite($GLOBALS['curUserID']);

	//房间顺序
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$result['placelist'] = $GLOBALS['dstpSoap']->getRoomOrder();
	
	if( USER_TYPE_SYSTEM >= $access['type'] )
	{
		$result['alarm'] = getAlarmInfo();

		
		//在线家人
		$c     = new TableSql('homeattr','ID');
		$result['jr'] = $c->queryAllList('NAME','ATTRINT=1 AND SYSNAME=?',array('client'));
	}
	else 
	{
		$result['jr']        = array();
		$result['alarm']     = array();
		
		//根据可访问权限过滤掉部分数据
		if(ROOM_SYSDEV_WHITE == $access['info']['type'])
		{
			$roomList = array_keys($result['addr']);
			$diffRoom = array_diff($roomList,$access['info']['room']);
			foreach( $diffRoom as $room ) //不在白名单中的都删除
			{
				//删除不相关的属性数据
				foreach( $result['map'][$room] as $delid )
				{
					unset( $result['page'][$delid] );
				}
				unset( $result['addr'][$room] );
				unset( $result['map'][$room] );
			}
		}
		else
		{
			//删除黑名单的数据
			foreach( $access['info']['room'] as $room )
			{
				//删除不相关的属性数据
				foreach( $result['map'][$room] as $delid )
				{
					unset( $result['page'][$delid] );
				}
				unset( $result['addr'][$room] );
				unset( $result['map'][$room] );
			}
		}
	}

	return $result;	
}

//获取摄像头的所有UID和用户密码设置，打开APP就自动去连入摄像头
//这个地方要考虑权限，只能传回可见的
function getHXCamerList()
{
	$result = array();
	$c = new TableSql('homeattr','ID');
	$cfgList = $c->queryAllList('CFGINFO','SYSNAME=?',array('rtsp'));
	foreach( $cfgList as &$cfg )
	{
		$cfg = unserialize($cfg);
		if(false == $cfg)
		{
			continue;
		}
		if( !isset($cfg['ver']) )
		{
			continue;
		}
		$tmp  = array();
		$tmp['uid']  = $cfg['uid'];
		$tmp['user'] = $cfg['user'];
		$tmp['psw']  = $cfg['psw'];
		$result[]    = $tmp;
	}
	return $result;
}

//当前台得到设备变化通知时，就调用这个接口，得到新加入的设备
function getNewDevList()
{
	$r = Cache::get("openDevSearch");
	if( false == $r )
	{
		return array();
	}
	$c = new TableSql('homedev','ID');
	return $c->queryAll('ID,NAME,ROOMID','ATIME>?',array($r));
}
//当用户每次进入设备添加页面时，就需要定时调用本接口
//只有在接口打开后，系统才会允许设备自动加入
function openDevSearch($subhost=INVALID_ID)
{
	$r = Cache::get("openDevSearch");
	if( false == $r )
	{
		$r = time();
	}
	Cache::set("openDevSearch",$r,22);

	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$GLOBALS['dstpSoap']->openDevSearch(DEV_CMD_SYS_OPEN_PERMITJOIN);
	
	//延迟30秒时间后就发送关闭命令
	include_once('plannedTask/PlannedTask.php');
	$planTask 	  = new PlannedTask('setting','setting',30);
	$planTask->openDevSearchMap(DEV_CMD_SYS_CLOSE_PERMITJOIN);
		
}
function stopDevSearch()
{
	Cache::del("openDevSearch");
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$GLOBALS['dstpSoap']->openDevSearch(DEV_CMD_SYS_CLOSE_PERMITJOIN);
	return;
}

function setNetPermitjoin($isAllow)
{
	$info = array();
	$info['DISNETJOIN'] = intval($isAllow)?0:1; //数据库的出于默认考虑，存储含义和参数是相反的

	$c = new TableSql('sysinfo');
	$c->del();
	$c->add($info);
	
	//如果改为默认打开，则直接打开；否则先发送关闭指令
	$cmd = intval($isAllow)?DEV_CMD_SYS_OPEN_PERMITJOIN:DEV_CMD_SYS_CLOSE_PERMITJOIN;
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$GLOBALS['dstpSoap']->openDevSearch($cmd);
	
	return true;
}

function getNetPermitjoin()
{
	$c = new TableSql('sysinfo');
	$r = $c->queryValue('DISNETJOIN');
	return intval($r)?0:1;
}

//收到推送消息后，app会调用此接口，删除服务器上的记录，视为此条推送成功
function pushed($id)
{
    if(isset($id['id'])){
        // $GLOBALS['dstpSoap']->setModule('app','push');
		// $GLOBALS['dstpSoap']->pushed($id['id']);
		// include_once('plannedTask/PlannedTask.php');
        // $planTask = new PlannedTask('delay','push');
        // $planTask->pushed($id['id']);
    }

}
util::startSajax( array('pushed','getNewDevList','getAlarmInfo',
							'getDevListJson','getDevListNewFromRtsp',
							'getHXCamerList','openDevSearch','stopDevSearch',
							'setNetPermitjoin','getNetPermitjoin'));

?>