<?php
include_once('a/yuyinLang.php');
//该文件实现语音控制管理接口
class yuyinInterFace
{
	///////////////语音解析//////////////////////

	//用户启动语音输入时，需要指示当前页面位置，方便语音控制定位操作
	static function start($pos)
	{
		$_SESSION['yuyinstart'] = $pos;
		if(isset($_SESSION['yuyinobj']))
		{
			$_SESSION['yuyinobj'] = NULL;
			unset($_SESSION['yuyinobj']);
		}
		return true;
	}

	//语音解析入口
	static function yuyin($yuyin,$gid=NULL,$userid=INVALID_ID)
	{
		include_once('fenci/fenci.class.php');

		//调用分词接口，进行分词。首先调用确定对象的分词接口
		$GLOBALS['dstpSoap']->setModule('yuyin','dict');
		$dict  = $GLOBALS['dstpSoap']->getDict();
		$fenci = fenciClass::fenci($yuyin,$dict);
		//if( NULL == $fenci )
		//{
		//	return YUYIN_FENCI_FAIL;//无法识别语音
		//}

		//$check = self::check($fenci);
		//if(!$check)
		//{
		//	return YUYIN_FENCI_FAIL;//
		//}
		
		//根据地点，名称或者当前位置等信息确定操作属性或者设备ID
		$objList = self::getObj($fenci,$gid);
		if( false == $objList )
		{
			if( isset($GLOBALS['yuyinexec']) ) 
			{
				$GLOBALS['yuyinexec'] = false;
			}
			return array(YUYIN_OBJ_FAIL);//无法识别操作对象
		}
		
		$info   = array();
		$result = array();
		$qjkey  = array();
		
		foreach($objList as $key=>&$obj)
		{
			$yy = $yuyin;
			//就第一次解析过的字符在第二次解析的时候要去除
			foreach( explode('-',$obj['name']) as $k => $v) 
			{
				$yy = str_replace($v,'',$yuyin);
			}
			$ret = false;
			switch($obj['attr'])
			{
				case 'attr'://属性
					//如果是从页面过来的，需要鉴权
					if( validID($userid) )
					{
						$GLOBALS['dstpSoap']->setModule('setting','setting');
						$r = $GLOBALS['dstpSoap']->checkExecAccess($userid,$obj['obj'],'attr');
						if( !$r )
						{
							continue;
						}				
					}

					$GLOBALS['dstpSoap']->setModule('devattr','attr');
					$ret = $GLOBALS['dstpSoap']->yuyin($obj['obj'],$yy);
					break;
				case 'qj'://情景模式
					if( validID($userid) )
					{
						$GLOBALS['dstpSoap']->setModule('setting','setting');
						$r = $GLOBALS['dstpSoap']->checkExecAccess($userid,$obj['obj'],'group');
						if( !$r )
						{
							continue;
						}				
					}
					$GLOBALS['dstpSoap']->setModule('smart','group');
					$r = $GLOBALS['dstpSoap']->execGroup($obj['obj']);
					$ret = array();
					$ret['ret'] = true;
					$ret['info'] = NULL;
					$qjkey[] = $key;
					break;
				case 'sys'://系统管理
					break;
				default://
					break;
			}
			if( false === $ret )
			{
				$info[$key]  = sprintf(YUYIN_YUYIN_FAIL,$obj['name']);
				$result[$key]= false; 
				continue;
			}

			if($ret['ret'])
			{
				$info[$key] = sprintf(YUYIN_OP_OK,$obj['name'],$ret['info']);
				$result[$key]= true; 
			}
			else
			{
				$info[$key] = sprintf(YUYIN_OP_FAIL2,$obj['name'],$ret['info']);
				$result[$key]= false; 
			}
		}
		
		//如果有执行正确也有执行错误的，则剔除执行错误的结果，只返回正确
		if( in_array(true,$result) )
		{
			foreach($result as $key=>$r)
			{
				//情景模式不能缓存做为下一次执行的对象
				if($r)
				{
					continue;
				}
				unset($info[$key]);
				unset($objList[$key]);
			}
		}
		else
		{
			if( isset($GLOBALS['yuyinexec']) ) 
			{
				$GLOBALS['yuyinexec'] = false;
			}
		}
		
		//情景模式对象不能做为下一次缓存执行对象
		foreach( $qjkey as $qk )
		{
			if( isset( $objList[$qk] ) )
			{
				unset($objList[$qk]);
			}
		}

		if( NULL != $gid )
		{
			Cache::set($gid,$objList,15);
		}
		else
		{
			$_SESSION['yuyinobj'] = $objList;
		}
		
		return $info;
	}
	
	////////////////////////////////////////////////
	//获取操作对象:array('attr'=>['dev','attr','sys'],'obj'=>id,'name'=>fullname)
	//根据地点，名称或者当前位置,上次操作对象等信息确定操作属性或者设备ID
	//$_SESSION['yuyinstart']
	private static function getObj(&$fenci,$gid)
	{
		//根据地点，名称,情景模式名称获取操作对象
		$list = self::getObjByInfo($fenci);

		//如果没有地点，名称，则根据当前操作页面，或者上一次操作获取
		if( NULL == $list )
		{
			$list = self::getDefaultObj($fenci,$gid);
		}

		foreach($list as &$l)
		{
			$name = NULL;
			switch($l['attr'])
			{
				case 'dev':
					$GLOBALS['dstpSoap']->setModule('home','end');
					$name = $GLOBALS['dstpSoap']->getAlarmAttrName($l['obj'],NULL);
					break;
				case 'attr':
					$GLOBALS['dstpSoap']->setModule('devattr','devattr');
					$name = $GLOBALS['dstpSoap']->getAlarmAttrName(NULL,$l['obj']);
					break;
				case 'qj':
					$c = new TableSql('smartgroup','ID');
					$name = $c->queryValue('NAME','ID=?',array($l['obj']));
					$name = sprintf(YUYIN_NAME_QJ,$name);
					break;
				case 'sys':
					break;
				default:
					break;
			}
			$l['name'] = $name;
		}
		return $list;
	}
	
	//根据分词结果中的地点/名称/情景名称获取操作对象
	private static function getObjByInfo(&$fenci)
	{
		//如果已经匹配到地点或者名称，则情景模式名称直接忽略
		//如果已经匹配到情景模式，则直接忽略其他，返回情景模式
		//如果匹配到了地点和名称，则开始获取对象
		$list = array();
		
		//存放临时的相关词组记录
		$sb  = array();
		$sx = array();
		$dd = array();
		foreach($fenci as &$ci)
		{
			foreach($ci['attr'] as &$attr)
			{
				switch( $attr )
				{
					case 'qj':
						if( NULL == $sb && NULL == $sx && NULL == $dd )
						{
							$list[] = self::getQJMSObj($ci['word']);
							return $list;
						}
						break;
					case 'sx':
						$sx[] =  $ci['word'];
						break;
					case 'sb':
						if( NULL != $sx )//如果已经有了属性，就不能再来一个新设备名
						{
							$dd1 = $dd;
							$r = self::getAttrObj($list,$sb,$sx,$dd);//但这儿应该不清除dd
							$dd = $dd1;
						}
						$sb[] =  $ci['word'];
						break;
					case 'dd':
						//如果已经有了地点，设备属性名，
						//再来一个新地点，则这个新地点应作为下一个的开始
						//否则理解为同时操作多地点的信息
						if( NULL != $dd && ( NULL != $sb || NULL != $sx ) )
						{
							$r = self::getAttrObj($list,$sb,$sx,$dd);
						}
						$dd[] =  $ci['word'];
						break;
					default:
						break;
				}
				//暂时没处理重名多属性的
				break;
			}
		}
		self::getAttrObj($list,$sb,$sx,$dd);
		
		return $list;
	}
	
	//根据地点，设备，属性信息获取对象。如果能获取到，则返回记录，同时清空缓存数据
	private static function getAttrObj(&$list,&$sb,&$sx,&$dd)
	{
		$addr = array();
		$c  = new TableSql('homeroom','ID'); 
		foreach($dd as &$d)
		{
			$id = $c->queryValue('ID','NAME=?',array($d));
			if( validID($id) )
			{
				$addr[] = $id;
			}
		}
		
		//获取设备，设备只能在指定的房间内
		$addrList = implode(',',$addr);
		$dev = array();
		$c  = new TableSql('homedev','ID'); 
		foreach($sb as &$b)
		{
			if( NULL != $addrList )
			{
				$idList = $c->queryAllList('ID',"NAME=? AND ROOMID IN ($addrList)",array($b));
			}
			else
			{
				$idList = $c->queryAllList('ID',"NAME=?",array($b));
			}
			$dev = array_merge($dev,$idList);
		}
		$dev = array_unique($dev);

		//如果没属性，就是只获取设备
		if( NULL == $sx )
		{
			foreach($dev as $d)
			{
				$list[] = array('attr'=>'dev','obj'=>$d);
			}
			$sb = array();
			$dd = array();
			return;
		}
		
		$c  = new TableSql('homedev','ID'); 
		if( NULL == $dev && NULL != $addrList )
		{
			$dev = $c->queryAllList('ID',"ROOMID IN ($addrList)");
		}
		$dev = implode(',',$dev);
		
		//如果有属性名，则返回属性名
		$attr = array();
		$c  = new TableSql('homeattr','ID'); 
		foreach($sx as &$x)
		{
			if( NULL != $dev )
			{
				$idList = $c->queryAllList('ID',"NAME=? AND DEVID IN ($dev)",array($x));
			}
			else
			{
				$idList = $c->queryAllList('ID',"NAME=?",array($x));
			}
			$attr = array_merge($attr,$idList);
		}
		
		foreach($attr as $d)
		{
			$list[] = array('attr'=>'attr','obj'=>$d);
		}
		$sb = array();
		$sx = array();
		$dd = array();
		return;		
	}
	
	//根据情景模式名称获取其ID
	private static function getDevObj($dev)
	{
		$c  = new TableSql('smartgroup','ID'); 
		$id = $c->queryValue('ID','NAME=?',array($qj));
		return array('attr'=>'qj','obj'=>$id);
	}
	
	//根据情景模式名称获取其ID
	private static function getQJMSObj($qj)
	{
		$c  = new TableSql('smartgroup','ID'); 
		$id = $c->queryValue('ID','NAME=?',array($qj));
		return array('attr'=>'qj','obj'=>$id);
	}
	
	
	
	//如果没有地点，名称，则根据当前操作页面，或者上一次操作获取
	private static function getDefaultObj(&$fenci,$gid)
	{
		if( NULL != $gid )
		{
			return Cache::get($gid);
		}
		if(isset($_SESSION['yuyinobj']))
		{
			return $_SESSION['yuyinobj'];
		}
		if(!isset($_SESSION['yuyinstart']))
		{
			return NULL;
		}
		$url = parse_url($_SESSION['yuyinstart']);
		if( FALSE == $url )
		{
			return NULL;
		}
		$id = INVALID_ID;
		$query = parse_str($url['query']);
		if(isset($query['id']))
		{
			list($id,$o) = explode('_',$query['id']);
		}
		if(!validID($id))
		{
			return NULL;
		}
		$ret = NULL;
		$file = basename($url['path']);
		switch($file)
		{
			case 'end.php':
				$ret = array(array('attr'=>'dev','obj'=>$id));
				break;
			case 'devattr.php':
				$ret = array(array('attr'=>'attr','obj'=>$id));
				break;
			case 'group.php':
				$ret = array(array('attr'=>'qj','obj'=>$id));
				break;
			default:
				break;
		}
		return $ret;
	}
	
	private static function check(&$fenci)
	{
		//必须检测到情景模式，动作，量词或者其他中至少一个

		//如果有地点，则需要有名称
		
		return true;
	}
	
}
?>