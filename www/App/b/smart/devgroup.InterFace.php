<?php
//该文件实现组合操作管理接口
class devgroupInterFace
{
	private static $groupNum = 5;

	//生成一个设备组ID.这个ID，需要不超过65535，不重复
	private function getDevGroupID()
	{
		$c = new TableSql('smartdevgroup');
		$dgList = $c->queryAllList('DGID');
		
		$num = 0;
		$dgid = mt_rand(1,65500);
		while( in_array($dgid,$dgList) )
		{
			if( $num++ > 65500 )//避免死循环
			{
				return INVALID_ID;
			}
			$dgid = ($dgid+1)%65500;
			if( 0 == $dgid ) $dgid =1;
			if( 65500<$dgid ) $dgid =1;
		}
		return $dgid;
	}
	
	//更新指定设备组的信息。必须确保一个设备的记录不超过20条
	private static function updateDevGroupAttr($id,$attrList)
	{
		$c = new TableSql('smartdevgroupattr');
		$org = $c->queryAllList('ATTRID','DGID=?',array($id));

		$delList = array_diff($org,$attrList);
		$addList = array_diff($attrList,$org);
		self::cleanDevGroup($id,$delList);

		if( NULL == $attrList || true === $attrList )
		{
			return true;
		}

		$attrList1 = array();
		$attrList2 = array();
		foreach ($attrList as $value) 
		{
			if( $value > MAX_INT_VALUE )
			{
				$attrList1[] = $value-MAX_INT_VALUE;
			}
			else
			{
				$attrList2[] = $value;
			}
		}
		$attrStr = implode(',',$attrList2);

		//2.4G和zigbee的电源设备才能设置组播组。
		//分组设备(非zigbee)也不能设置组播组
		$c1 = new TableSql('homeattr','ID');
		//设备组
		$group  = $c1->queryAll('ID as ATTRID,DEVID',"ID IN ($attrStr) and DEVID = ?",array(-2));
		$c1->join('homedev','homeattr.DEVID=homedev.ID');
		//设备
		$aInfo = $c1->queryAll('homeattr.ID as ATTRID,DEVID,ISPOWER,PHYDEV',"homeattr.ID IN ($attrStr)");
		$aInfo = array_merge($aInfo,$group);
		//情景模式，设定情景模式的DEVID=-1
		if( sizeof($attrList1) != 0 )
		{
			$attrStr1 = implode(',',$attrList1);
			$c1    = new TableSql('smartgroup','ID');
			$list = $c1->queryAll('ID+'.MAX_INT_VALUE.' as ATTRID,-1 as DEVID',"ISSHOW=1 and ID IN ($attrStr1)"); 
			$aInfo = array_merge($aInfo,$list);
		}

		$info = array();
		$info['DGID'] = $id;
		$info['DGSTATUS'] = 0;
		
		$devGroup = array(); //可以做设备组的数据
		foreach( $aInfo as &$a )
		{
			if( ( ( PHYDEV_TYPE_ZIGBEE==$a['PHYDEV'] ) || (PHYDEV_TYPE_24G==$a['PHYDEV']) ) 
				&& (DEV_POWER_POWER==$a['ISPOWER']) )
			{
				$devGroup[] = $a;//后面判断是否加入组播组
				continue;
			}
			if( !in_array($a['ATTRID'],$addList) )
			{
				continue; //直接点播的，如果不是在新增加信息中，一定是已经设置且无需修改状态
			}
			//直接作为
			$info['DEVID']  = $a['DEVID'];
			$info['ATTRID'] = $a['ATTRID'];
			$c->add($info);
		}
		
		$status = 1;
		//如果$attrList数目太少，直接不设置组播组
		if( self::$groupNum > count($devGroup) )
		{
			$status = 0;
		}
		foreach( $devGroup as &$a )
		{
			if( in_array($a['ATTRID'],$addList) )
			{
				$info['DGSTATUS'] = $status;
				$info['DEVID']    = $a['DEVID'];
				$info['ATTRID']   = $a['ATTRID'];
				$c->add($info);
				continue;
			}
			//原来已经存在，先查询状态
			$orgStatus = $c->queryValue('DGSTATUS','DGID=?  AND ATTRID=?',
							array($id,$a['ATTRID']));

		
			$newStatus = 0;
			if( 0 == $status )
			{
				switch($orgStatus)
				{
					case 0:
						$newStatus = 0;
						break;
					case 1:
					case 2:
					case 3:
					case 4:
					default:
						$newStatus = 4;
						break;
				}
			}
			else
			{
				switch($orgStatus)
				{
					case 2:
						$newStatus = 2;
						break;
					case 0:
					case 1:
					case 3:
					case 4:
					default:
						$newStatus = 1;
						break;
				}
			}
			if( $newStatus!=$orgStatus )
			{
				$tmp = array();
				$tmp['DGSTATUS'] = $newStatus;
				$c->update($tmp,NULL,'DGID=?  AND ATTRID=?',array($id,$a['ATTRID']));				
			}
		}
		
		self::multiSendupdate();

		return;
	}
	
	private static function multiSendupdate()
	{
		//发现设置情景模式时，如果有比较多的开关插座被选择，通常无法一次性设置群组成功
		//所以这儿干脆多启动几个定时器处理
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask('smart','devgroup', 1);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的
		$planTask = new PlannedTask('smart','devgroup', 4);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的
		$planTask = new PlannedTask('smart','devgroup', 7);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的
		$planTask = new PlannedTask('smart','devgroup', 10);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的
		$planTask = new PlannedTask('smart','devgroup', 13);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的
		$planTask = new PlannedTask('smart','devgroup', 16);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的
		$planTask = new PlannedTask('smart','devgroup', 19);
		$planTask->updateDevGroupDevAttr(); //直接查找全部处理，方便补足原来没处理完整的

		return;
	}

	//更新维护设备组信息。
	static function sysMaintence()
	{
		$c = new TableSql('smartdevgroup');
		$dgList = $c->queryAllList('DGID');

		//数目太少的组，直接删除设备协议组
		$c = new TableSql('smartdevgroupattr');
		foreach( $dgList as $dg )
		{
			$num = $c->getRecordNum('DGID=? AND ( DGSTATUS=? OR DGSTATUS=? )',array($dg,1,2));
			if( self::$groupNum <= $num )
			{
				continue;
			}
			$info = array();
			$info['DGSTATUS'] = 4;
			$c->update($info,NULL,'DGID=? AND ( DGSTATUS=? OR DGSTATUS=? )',array($dg,1,2));
		}
		//如果还有未响应的协议组设置，则重新下发
		self::multiSendupdate();
	}

	//设置设备组
	static function saveDevGroup($attrList,$name='',$id=INVALID_ID)
	{
		//如果数目不多，直接删除协议		
		//if( self::$groupNum > count($attrList))
		//{
		//	self::delDevGroup($id);
		//	$id = INVALID_ID;
		//}

		$info = array();
		$info['NAME'] = $name;
		$c = new TableSql('smartdevgroup');
		if( validID($id) )
		{
			$c->update($info,NULL,'DGID=?',array($id));
		}
		else
		{
			$id = self::getDevGroupID();
			if( !validID($id) )
			{
				return $id;
			}
			$info['DGID'] = $id;
			$c->add($info);
		}
		
		self::updateDevGroupAttr($id,$attrList);
		
		return $id;
	}
	static function saveDevGroupFromGroup($attrList,$name='',$id=INVALID_ID)
	{
		if( $id > MAX_SEP_VALUE && (INVALID_ID!=$id))
		{
			$id = $id-MAX_SEP_VALUE;
		}
		else
		{
			$id = INVALID_ID;
		}
		//如果只有一个，则无需设置
		if( NULL == $attrList )
		{
			self::delDevGroup($id);
			return INVALID_ID;
		}
		if( is_numeric($attrList) )
		{
			self::delDevGroup($id);
			return $attrList;
		}

		if( 1 == count($attrList))
		{
			self::delDevGroup($id);
			return $attrList[0];
		}
		
		
		$dgid = self::saveDevGroup($attrList,$name,$id);
		if( !validID($dgid) )
		{
			return $dgid;
		}
		return $dgid+MAX_SEP_VALUE;
	}
	
	
	//指定设备指定设备组如果还有未响应的组设置消息，则重新发一条
	static function updateDevGroupDevAttr($devid=INVALID_ID,$dgid=INVALID_ID)
	{
		$where = '( (DGSTATUS=?) OR (DGSTATUS=?) OR (DGSTATUS=?))';
		$wArr  = array(1,3,4);
		if( validID($devid) )
		{
			$where .= ' AND DEVID=?';
			$wArr[] = $devid;
		}
		if( validID($dgid) )
		{
			$where .= ' AND DGID=?';
			$wArr[] = $dgid;
		}
		$cdg = new TableSql('smartdevgroupattr');
		$dgList = $cdg->queryAll('*',$where,$wArr);
		$set = array();
		$c = new TableSql('homeattr','ID');
		foreach( $dgList as &$dg )
		{
			if(!isset( $set[$dg['DEVID']] ))
			{
				$set[$dg['DEVID']] = array();
			}
			if(!isset( $set[$dg['DEVID']][$dg['DGID']] ))
			{
				$set[$dg['DEVID']][$dg['DGID']] = array(0,0,0,0);
			}
			
			$index = $set[$dg['DEVID']][$dg['DGID']];
			
			$attrIndex = $c->query('DEVID,ATTRINDEX','ID=?',array($dg['ATTRID']));
			if( NULL == $attrIndex )
			{
				//该属性可能不存在了，直接删除
				$cdg->del('ATTRID=?',array($dg['ATTRID']));
				continue;
			}
			$attrIndex = $attrIndex['ATTRINDEX'];
			$num = intval($attrIndex/8);
			$attrIndex = $attrIndex%8;

			if( 1 == $dg['DGSTATUS'] )//表示该属性要设置到组播组中
			{
				$index[$num] |= 1<<$attrIndex;
			}
			else //表示该属性从组播组中删除
			{
				$bit = 1 << $attrIndex;  
				$nMark = 0;  
				$nMark = (~$nMark) ^ $bit;  
				$index[$num] &= $nMark;  
			}
			$set[$dg['DEVID']][$dg['DGID']] = $index;
		}
		
		if( NULL == $set )
		{
			return true;
		}
		//向指定设备发送消息。这儿应该控制一次性下发的数量
		$sendArray = array();
		$c = new TableSql('homedev');
		foreach($set as $devid=>$dgList)
		{
			//设备当前离线的，则无需处理
			$status = $c->queryValue('STATUS','ID=?',array($devid));
			if( DEV_STATUS_RUN != $status )
			{
				continue;
			}
			foreach( $dgList as $dgid=>$index )
			{
				$sendArray[] = array('devid'=>$devid,'dgid'=>$dgid,'index'=>$index);
			}
		}
		if( NULL == $sendArray )
		{
			return true;
		}		
		//控制一次性下发的数量。但为了避免有些一直无法返回的占住导致无法处理。这儿数组要乱序
		$num = 0;
		shuffle($sendArray);
		$GLOBALS['dstpSoap']->setModule('home','if');
		foreach($sendArray as &$send)
		{
			$msg          = array();
			$msg['gid']   = $send['dgid'];
			$msg['index'] = $send['index'];
			$GLOBALS['dstpSoap']->sendMsg($send['devid'],DEV_CMD_HIC_GROUP_DEV,$msg);
			if( $num++ > 5 )
			{
				break;
			}
		}
		return true;
	}

	//下发设备组时设备响应消息
	static function devGroupRsp($devid,$dgid)
	{
		$info = array();
		$c = new TableSql('smartdevgroupattr');
		
		$info['DGSTATUS'] = 2;
		$c->update($info,NULL,'DEVID=? AND DGSTATUS=? AND DGID=?',array($devid,1,$dgid));
		$info['DGSTATUS'] = 0;
		$c->update($info,NULL,'DEVID=? AND DGSTATUS=? AND DGID=?',array($devid,4,$dgid));
		
		//待删除的属性直接删除
		$c->del('DGID=? AND DEVID=? AND DGSTATUS=? AND DGID=?',array($dgid,$devid,3,$dgid));
		return;
	}	

	//把指定的设备ID的属性全部改为未回应已加入组
	static function resetDevGroup($devid)
	{
		$info = array();
		$info['DGSTATUS'] = 1;
		$c = new TableSql('smartdevgroupattr');
		$c->update($info,NULL,'DEVID=? AND DGSTATUS=?',array($devid,2));
		
		//初始化时，相当于已经确认了修改为不设置组播组
		$info['DGSTATUS'] = 0;
		$c->update($info,NULL,'DEVID=? AND DGSTATUS=?',array($devid,4));
		
		self::updateDevGroupDevAttr($devid);
	}
	
	//删除设备时，删除该设备中的所有属性在设备组中的信息
	//因为设备已经删除，所以系统中无需保留任何组播组设置信息，等待其重新初始化
	static function delDevGroupDev($devid)
	{
		$c = new TableSql('smartdevgroupattr');
		return $c->del('DEVID=?',array($devid));
	}


	//删除设备组
	static function delDevGroup($dgid)
	{
		//删除设备组前，先看下是否有设置了属性，如果有需要先删除
		$c = new TableSql('homeattr','ID');
		$attrid = $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',array(-2,$dgid));
		if( validID($attrid) )
		{
			$GLOBALS['dstpSoap']->setModule('home','end');
			$GLOBALS['dstpSoap']->delAttr($attrid);
		}
		
		self::cleanDevGroup($dgid,true);

		$c = new TableSql('smartdevgroup');
		$c->del('DGID=?',array($dgid));
		statusNotice('devgroup');
		return true;
	}
	
	//清除某个分组中的设备属性信息
	private function cleanDevGroup($dgid,$attrList=NULL)
	{
		if( NULL == $attrList )
		{
			return;
		}
		$addWhere = NULL;
		if( true !== $attrList ) //true表示全删
		{
			$attrList = implode(',',$attrList);
			$addWhere = " AND ATTRID IN ($attrList)";
		}
		$c = new TableSql('smartdevgroupattr');
		$c->del('DGID=? AND  DGSTATUS=?'.$addWhere, array($dgid,0)); //没有设置组播组的直接删除

		$info = array();
		$info['DGSTATUS'] = 3;//修改为删除等待回应状态
		$c->update($info,NULL,'DGID=?'.$addWhere,array($dgid));

		//向设备更新组播组状态
		self::updateDevGroupDevAttr(INVALID_ID,$dgid);
	}
	
	//查询设备组中的所有设备属性
	static function queryDev($dgid)
	{
		$c = new TableSql('smartdevgroupattr');
		$list = $c->queryAllList('ATTRID','DGID=?',array($dgid));
		if( NULL == $list )
		{
			$list = array(INVALID_ID);
		}
		return $list;
	}
	
	//向指定设备组发送控制命令
	//指定设备组中，有可能是有的已经在协议栈中作为一个组，可以同时发出，但有的则需要单独发出
	static function execDevGroup($id, $cmd)
	{
		if( $id > MAX_SEP_VALUE )
		{
			$id -= MAX_SEP_VALUE;
		}

		$c = new TableSql('smartdevgroupattr');
		$group = $c->queryAllList('ATTRID','DGID=? AND DGSTATUS=?',array($id,2));
		$allList = $c->queryAllList('ATTRID','DGID=? AND DGSTATUS!=?',array($id,3));
		//当设备组的组员是情景模式时的处理
		foreach ($allList as $key=>$value) 
		{
			if( $value > MAX_INT_VALUE )
			{
				$GLOBALS['dstpSoap']->setModule('smart','group');
				$GLOBALS['dstpSoap']->execGroup($value-MAX_INT_VALUE,$gList=array(),$curid=NULL);
			}
		}
		$c = new TableSql('smartdevgroup');
		$dgname = $c->queryValue('NAME','DGID=?',array($id));
		if( NULL == $dgname )
		{
			$allStr  = implode(',',$allList);
			$c = new TableSql('homeattr','ID');
			$attr = $c->query('ID,SYSNAME',"ID IN ($allStr)");
		}
		else
		{
			$c = new TableSql('homeattr','ID');
			$attr = $c->query('ID,SYSNAME',"DEVID=? AND ATTRINDEX=?",array(-2,$id));
			noticeAttrModi($attr['ID']);
		}
		
		if ( !$attr )
		{
			return false;
		}
		include_once(dirname(dirname(__FILE__)).'/devattr/class.attr.inc.php');

		attrType::setAttrType($attr['SYSNAME']);
		$dbcmd = attrType::getDBInfo($cmd,$attr['ID']);
		if( false === $dbcmd )
		{
			return true;
		}

		//有已经加入协议栈组播组的，直接发组播
		if( NULL != $group )
		{
			attrType::setAttrType($attr['SYSNAME']);
			$groupcmd = attrType::getCMDInfo($dbcmd,$attr['ID']);
			if( false !== $groupcmd )
			{
				//根据group找出所有subhost,向每个分机都发送组播消息
				$attrStr = implode(',',$group);
				$c1 = new TableSql('homeattr','ID');
				$c1->join('homedev','homeattr.DEVID=homedev.ID');
				$hostList = $c1->queryAll('SUBHOST,PHYDEV',"homeattr.ID IN ($attrStr)");
				$hostList =  UTIL::arrayUnique($hostList); 

				$GLOBALS['dstpSoap']->setModule('home','if');
				foreach( $hostList as $host )
				{
					$GLOBALS['dstpSoap']->sendMsgToGroup($host['SUBHOST'],$id,$groupcmd,$host['PHYDEV']);
				}
			}
		}
		
		if( count($allList) == count($group))
		{
			return true;
		}
		$idList = array_diff($allList,$group);
		
		//没有加入协议栈的组播组的，按设备发送
		$idList = implode(',',$idList);
		$c = new TableSql('homeattr','ID');
		$attrInfo = $c->queryAll('ID,DEVID,ATTRINDEX',"ID IN ($idList)");
		if( NULL == $attrInfo )
		{
			return true;
		}
		$realid = $attr['ID'];
		$devAttr = array();
		foreach( $attrInfo as &$attr ) //这儿要优化下，同一个设备应该一起发出
		{
			if( -2 == $attr['DEVID']  )
			{
				//这个也是个设备组，不能直接发出，递归调用
				$GLOBALS['dstpSoap']->setModule('smart','devgroup');
				$GLOBALS['dstpSoap']->execDevGroup($attr['ATTRINDEX'],$cmd);
				continue;
			}
			if( !isset($devAttr[$attr['DEVID']]) )
			{
				$devAttr[$attr['DEVID']] = array();
			}
			$devAttr[$attr['DEVID']][] = array('ID'=>$attr['ID'], 'ATTR'=>$dbcmd);
		}
		
		$GLOBALS['devgrouprealattr'] = $realid;
		$GLOBALS['dstpSoap']->setModule('home','end');
		foreach( $devAttr as $devid=>&$attrList )
		{
			$GLOBALS['dstpSoap']->sendMsg($devid, $attrList);
		}
		unset($GLOBALS['devgrouprealattr']);
		return true;
	}
}
?>