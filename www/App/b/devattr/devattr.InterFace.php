<?php

//该文件进行系统属性管理接口
class devattrInterFace
{
	static function checkUpdatePageList()
	{
		//如果属性信息有更新，则需要重新生成属性信息的缓存页
        $attrchangelist  = Cache::get('attrchangelist');
		Cache::del('attrchangelist');
		
        if ( !$attrchangelist ) //如果属性信息有变化
		{
			return;
		}
		self::updatePageList($attrchangelist);
	}
	
	/***********每天重新生成一次缓存，避免可能的错误累积**********/
	static function maintencePageList()
	{
		$cacheName = 'getAttrValue';
		Cache::del($cacheName);
		self::getAttrValue();
	}
	
	static function updatePageList($idList)
	{
		if(!$idList)
		{
			return;
		}
		$idList = array_unique($idList);
		
		$cacheName = 'getAttrValue';
		$pageList  = Cache::get($cacheName);
		if( false == $pageList )
		{
			$pageList    = self::genPageList();
			Cache::set($cacheName,$pageList);
			return;
		}
		$unpdateList = self::genPageList($idList);
		foreach( $unpdateList as $id=>&$update )
		{
			$pageList[$id] = $update;
		}
		
		Cache::set($cacheName,$pageList);
	}
	
	//获取指定id的页面信息,如果id为空，则全部
	private static function genPageList($idList = NULL)
	{
		include_once('class.attr.inc.php');
		//生成当前所有属性信息的缓存
		if( NULL == $idList )
		{
			$c = new TableSql('homeattr','ID');
			$idList = $c->queryAllList('ID');
		}
		
		$c = new TableSql('homeattr','ID');
		$result = array();
		foreach($idList as $id)
		{
			$attr = $c->query('*','ID=?',array($id));
			if( NULL == $attr )
			{
				//现在经常出现，明明数据在数据库里有，但不知为什么查询出来为空
				//这儿做个规避处理，一旦数据为空且该ID在数据库中确实存在，就重新再获取下
				$checkList = $c->queryAllList('ID');
				if( in_array($id,$checkList) )
				{
					noticeAttrModi($id);
				}
				continue;
			}

			$info = attrType::getAttrShow( $attr, $id);
			
			//删除无用字段，减少前后台数据传输
			unset($attr['ATTRINDEX']);
			unset($attr['CFGINFO']);
			unset($attr['VF']);
			unset($attr['CF']);
			unset($attr['ATTRINT']);
			unset($attr['ATTRFLOAT']);
			unset($attr['ATTRSTR']);
			unset($attr['ATTRSET']);
			unset($attr['SENDATTR']);

			//if( !isset($attr['ICON']) )
			//{
			//	$attr['ICON'] = $attr['SYSNAME'];
			//}
			
			$result[$id] = array('type'=>$info['type'],
								 'attr'=>$attr,
								 'value'=>$info['value'],
								 'other'=>$info['other'],
								 'detail'=>$info['detail'],
								);
		}
		return $result;
	}

	/***********属性状态页缓存信息读取页面**************************/
	static function getAttrValue()
	{
		$cacheName = 'getAttrValue';
		$result = Cache::get($cacheName);	
		if( false == $result )
		{
			$result = self::genPageList();
			Cache::set($cacheName,$result);
		}
		return $result;
	}
	
	//获取属性的layout布局方式
	static function getAttrLayout()
	{
		$result = Cache::get('attrlayout');
		if( $result )	return $result;
		$c   = new TableSql('homeattrlayout','ID');
		$c1  = new TableSql('homeattr','ID');
		$primList = $c->queryAll('ATTRID,PAGE,NAME,TIPS','MAINID=0');
		$result   = array();
		foreach( $primList as &$priminfo )
		{
			$prim = $priminfo['ATTRID'];
			$theSub   = $c->queryAll('ATTRID,TIPS,LAYOUT','MAINID=?',array($prim));
			if( NULL == $theSub )
			{
				continue;
			}
			
			$result[$prim] = array();
			$name = $priminfo['NAME'];
			if( NULL == $name )
			{
				$name = $c1->queryValue('NAME','ID=?',array($prim));				
			}			
			$result[$prim]['name'] = $name;
			
			if( NULL != $priminfo['PAGE'] )
			{
				$result[$prim]['page'] = $priminfo['PAGE'];
			}

			$result[$prim]['showlist'] =  array();
			if( NULL != $priminfo['TIPS'] )
			{
				$result[$prim]['showlist'][$priminfo['TIPS']] = array($prim);
			}
			foreach( $theSub  as &$sub )
			{
				$result[$sub['ATTRID']] = 0;
				
				$tips = $sub['TIPS'];				
				if( NULL == $tips )
				{
					if( 0 == $sub['LAYOUT'] )
					{
						$tips = 'main';
					}
					else
					{
						$tips = 'row';
					}	
				}
				if( !isset( $result[$prim]['showlist'][$tips] ) )
				{
					$result[$prim]['showlist'][$tips] = array();
				}
				$result[$prim]['showlist'][$tips][] = $sub['ATTRID'];
			}
		}

		Cache::set('attrlayout',$result);
		return $result;
	}

	static function getOffline()
	{
		$list = array();
		$c  = new TableSql('homeattr','ID');
		$c->join('homedev','homedev.ID=homeattr.DEVID');
		$idList = $c->queryAll('homeattr.ID as ID','STATUS=?',array(DEV_STATUS_OFFLINE));
   		foreach ($idList as $item)
   		{
   			$list[] = $item['ID'];
   		}
		return $list;
	}
	
	/***********其他模块要求的接口**********************************/
	static function getAlarmAttrName($devid,$attrid)
	{
		$attrinfo = NULL;
		if ( validID($attrid) )
		{
			$c = new TableSql('homeattr','ID');
			$attrinfo  = $c->query('NAME,DEVID','ID=?',array($attrid));
			if( NULL != $attrinfo )
			{
				$devid = $attrinfo['DEVID'];
			}
		}

		//推送消息到手机。获得房间，设备，属性名
		$c = new TableSql('homedev','ID');
		$dname = $c->query('NAME,ROOMID','ID=?',array($devid));
		$devname  = $dname['NAME'];
		$roomname = NULL;
		if( $dname['ROOMID'] == '-1' )
		{
			include_once('b/homeLang.php');
			$devname = HOME_SYSDEV_UNADDR;
		}
		if ( validID($dname['ROOMID']) )
		{
			$c = new TableSql('homeroom','ID');
			$name  = $c->queryValue('NAME','ID=?',array($dname['ROOMID']));
			$devname  = $name;
		}
		if( NULL != $attrinfo )
		{
			$devname  = $devname.'-'.$attrinfo['NAME'];
		}

		return $devname ;
	}

}
?>