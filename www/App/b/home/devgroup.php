<?php
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

function getDevgroup($type,$id=INVALID_ID)
{
	//$devList = array(
	//	'devlist' = > array(
	//		array('ID'=>1,'SYSNAME'=>'color','NAME'=>'aaaa'),
	//		。。。
	//	),
	//	'select'=>array($id,$type,$name,$list),
	//);
	$ret = array();
	if( validID($id) )
	{
		$c = new TableSql('smartdevgroup');
		$info = $c->query('*','DGID=?',array($id));
		$c = new TableSql('smartdevgroupattr');
		$list = $c->queryAllList('ATTRID','DGID=?',array($id));
		
		$ret['select'] = array('ID'=>$info['DGID'],'NAME'=>$info['NAME'],'LIST'=>$list);
		
		$c = new TableSql('homeattr','ID');
		$type = $c->queryValue('SYSNAME',"ATTRINDEX=?",array($info['DGID']));
	}
	
	
	$c = new TableSql('homeattr','ID'); 
	$c->join('homedev','homeattr.DEVID=homedev.ID');
	$ret['devlist'] = $c->queryAll('homeattr.ID as ID,DEVID,SYSNAME,homeattr.NAME as NAME,ROOMID','DEVID!=? AND SYSNAME=? AND ATTRINDEX!=? ORDER BY ROOMID,ID',array(-2,$type,$id));
	foreach ($ret['devlist'] as $key => $value) 
	{
		if( $value['ROOMID'] == ROOM_SYSDEV_UNADDR )
		{
			$room = HOME_SYSDEV_UNADDR;			
		}
		else
		{
			$c = new TableSql('homeroom','ID'); 
			$room = $c->queryValue('NAME','ID=?',array($value['ROOMID']));
		}
		$ret['devlist'][$key]['NAME'] = $room.'—'.$ret['devlist'][$key]['NAME'];
	}

	return $ret;
}

//返回保存更新后的设备组ID，保存失败则返回-1
function saveDevgroup($type,$name,$idlist,$dgid=INVALID_ID)
{
	if( NULL == $name )
	{
		return -1;
	}
	
	if( !validID($dgid) )
	{
		//新建时，一定需要有正确的类型信息。如果类型不存在，也返回错误
		$typeList = getTypeList();
		if( !isset( $typeList[$type] ) )
		{
			return -1;
		}
	}
	
	$GLOBALS['dstpSoap']->setModule('smart','devgroup');
	$dgid = $GLOBALS['dstpSoap']->saveDevGroup($idlist,$name,$dgid);
	if( !validID($dgid) )
	{
		return -1;
	}
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return -1;
	}
	
	//查询指定的属性是否已经存在
	$c = new TableSql('homeattr','ID');
	$attr = $c->query('ID,NAME,SYSNAME','DEVID=? AND ATTRINDEX=?',array(-2,$dgid));
	if( NULL != $attr )
	{
		//更新名字
		if( trim($name) != $attr['NAME'] )
		{
			//修改设备组的名字时修改相对应的智能模式中设备组的名字
			$GLOBALS['dstpSoap']->setModule('smart','smart');
			$GLOBALS['dstpSoap']->changeSmartAttrName($attr['ID'],$attr['NAME'],$name);
			//清空用户首页收藏缓存
			$GLOBALS['dstpSoap']->setModule('setting','setting');
			$GLOBALS['dstpSoap']->cleanFavoriteCache();
			$attr['NAME'] = trim($name);
			$c->update($attr);
			noticeAttrModi($attr['ID']);
		}
	}
	else
	{
		//添加属性，同时属性必须更新为不报状态
		$info = array();
		$info['NAME']      = trim($name);
		$info['SYSNAME']   = $type;
		$info['ATTRINDEX'] = $dgid;
		$info['CANDEL']    = 1;
		$info['ISR']       = 0;
		
		//设备组的设备特殊指定为 -2
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->addDevAttList(-2,array($info));
	}
	statusNotice('dict');
	statusNotice('devgroup');
	return $dgid;
}

//删除指定的设备组
function delDevgroup($dgid)
{
	$r = checkAccess($GLOBALS['curUserID'],USER_TYPE_SYSTEM);
	if( true !== $r )
	{
		return false;
	}
	$GLOBALS['dstpSoap']->setModule('smart','devgroup');
	$GLOBALS['dstpSoap']->delDevGroup($dgid);
	return true;
}

function getDevGroupInfo()
{
	$info = array();
	$info['typeList']     = getTypeList();
	$info['devgroupList'] = getDevgroupList();
	return $info;
}

util::startSajax( array('getDevGroupInfo','getDevgroup','saveDevgroup','delDevgroup'));

//获取当前系统支持的所有设备类型
function getTypeList()
{
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],INVALID_ID,'devgroup');
	if( !$r )
	{
		return array();//没有执行情景模式的权限，直接返回空
	}
	$r = getAllTypeList();
	$c = new TableSql('homeattr','ID');
	$type = $c->queryAllList('SYSNAME','ISC=1 and DEVID!=-2');
	$type = array_unique($type);

	$result = array();
	foreach($type as $t)
	{
		if( !isset($r[$t]) )
		{
			continue;
		}
		$result[$t] = $r[$t];
	}
	return $result;

}
function getAllTypeList()
{
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],INVALID_ID,'devgroup');
	if( !$r )
	{
		return array();//没有执行情景模式的权限，直接返回空
	}
	//return array(
	//'kg'    => '开关',
	//'color' => '彩灯',
	//);
	$ret = Cache::get('sysnamelist');
	if( false !== $ret )
	{
		return $ret;
	}
	
	include_once('b/homeLang.php');
	$ret = array();
	//遍历属性目录，查找所有文件的相关配置
	$dir = dirname(dirname(__FILE__)).'/devattr/attrType/';
	$dh  = opendir($dir);
	while (false !== ($file = readdir($dh))) 
	{
		if( '.' == $file || '..' == $file ) continue;
		//根据file获取type，xxxAttr.php
		$type = substr($file,0,-8);
		$file = "$dir/$file";
		include_once($file);
		$class = $type.'AttrType';
		if ( !property_exists($class, 'name') )
		{
			continue;
		}
		if( NULL == $class::$name )
		{
			continue;
		}
		$ret[$type] = $class::$name;
	}
	closedir($dh);
	
	Cache::set('sysnamelist',$ret);
	
	return $ret;
}

//获取当前已设置的所有设备组信息
function getDevgroupList()
{
    $GLOBALS['dstpSoap']->setModule('setting','setting');
	$r = $GLOBALS['dstpSoap']->checkExecAccess($GLOBALS['curUserID'],INVALID_ID,'devgroup');
	if( !$r )
	{
		return array();//没有执行情景模式的权限，直接返回空
	}

	//return array(
	//array('ID'=>1,'SYSNAME'=>'color','NAME'=>'aaaa'),
	//);
	$c = new TableSql('smartdevgroup');
	return $c->queryALL('DGID as ID,NAME','NAME IS NOT NULL');
}


?>