<?php
//该文件实现组合操作管理接口
class groupInterFace
{
	//删除情景模式
	static function delGroup($id)
	{
		//如果是一个组设备，则需要先删除该组设备
		$c = new TableSql('smartgroupattr','ID'); 
		$devGroup = $c->queryAllList('ATTRID','ATTRID>=? AND GROUPID=?',
						array(MAX_SEP_VALUE,$id));
		foreach( $devGroup as $dgid )
		{
			$GLOBALS['dstpSoap']->setModule('smart','devgroup');
			$GLOBALS['dstpSoap']->delDevGroup($dgid - MAX_SEP_VALUE);
		}
		
		//删除情景模式，相对于主页的信息也要修改
		$GLOBALS['dstpSoap']->setModule('setting','setting');
		$GLOBALS['dstpSoap']->resetFavorite($id);

		//如果情景模式引用了怎处理？
		$c->del('GROUPID=?',array($id));
		$c = new TableSql('smartgroupexec'); 
		$c->del('GID=?',array($id));
		$c = new TableSql('smartgroup','ID'); 
		$result = $c->delByID($id);
		statusNotice('list');
		statusNotice('dict');
		return $result;
	}
	//保存设置一个group
	static function saveGroup($id,$name,$attrList,$isShow=true)
	{
		if( NULL == $attrList )
		{
			return INVALID_ID;
		}
		
		$info = array();
		$info['NAME']   = $name;
		$info['ISSHOW'] = 1;
		if ( !$isShow )
		{
			$info['ISSHOW'] = 0;
		}
		$c = new TableSql('smartgroup','ID'); 
		if ( validID($id) )
		{
			$info['ID'] = $id;
			$c->update($info);
		}
		else
		{
			$id = $c->add($info);
		}
		if ( !validID($id) )
		{
			return false;
		}
		
		$preID = INVALID_ID;
		$c = new TableSql('smartgroupattr','ID'); 
		$org = $c->queryAll('ID,ATTRID','GROUPID=?',array($id));
		$c->del('GROUPID=?',array($id));
		
		$updateList = array();

		foreach( $attrList as &$attr )
		{
			$dgid = INVALID_ID;
			foreach($org as &$o)
			{
				if( $o['ID'] == $attr['ID'] )
				{
					$updateList[] = $attr['ID'];
					$dgid = $o['ATTRID'];
					break;
				}
			}
			unset($attr['ID']);
			$GLOBALS['dstpSoap']->setModule('smart','devgroup');
			$attr['ATTRID']  = $GLOBALS['dstpSoap']->saveDevGroupFromGroup($attr['ATTRID'],'',$dgid);

			$attr['GROUPID'] = $id;
			$attr['PREID']   = $preID;
			$attr['DELAYMS'] = intval($attr['DELAYMS']);
			$GLOBALS['dstpSoap']->setModule('smart','smart');
			$attr['COND'] = $GLOBALS['dstpSoap']->genSmartCondArr($attr['CONARR']);
			if( !$attr['COND'] )
			{
				$attr['COND'] = true; //如果没设置，则认为条件始终为真
			}
			$attr['ATTR'] = serialize($attr['ATTR']);
			$attr['COND'] = serialize($attr['COND']);
			$attr['CONARR'] = serialize($attr['CONARR']);

			$GLOBALS['dstpSoap']->setModule('smart','smart');
			$attr['PLANCFG'] = $GLOBALS['dstpSoap']->converSmartPlan($attr['PLAN']);
			$attr['PLANCFG'] = serialize($attr['PLANCFG']);
			$attr['PLAN']    = serialize($attr['PLAN']);
			
			$preID = $c->add($attr);
		}
		//删除已经被删掉的dgid
		$delDgList = array();
		foreach($org as &$o)
		{
			if( !in_array($o['ID'],$updateList) && ($o['ATTRID']>MAX_SEP_VALUE))
			{
				$delDgList[] = $o['ATTRID'];
			}
		}
		if( NULL != $delDgList )
		{
			$GLOBALS['dstpSoap']->setModule('smart','devgroup');
			$GLOBALS['dstpSoap']->delDevGroup($id);
		}
		statusNotice('list');
		statusNotice('dict');

		return $id;
	}
	//触发执行组合操作
	//返回值：true，模式执行完成，false，等待延迟执行
	static function execGroup($id,$gList=array(),$curid=NULL)
	{
		if( in_array($id,$gList) )
		{
			return true;
		}
		$gList[] = $id;

		$c = new TableSql('smartgroupattr');
		$attr = $c->query('*','GROUPID=? ORDER BY ID',array($id));
		//执行第一个
		return self::execAttrIndex($attr,$gList,$curid);
	}
	
	//从指定ID开始执行。执行时需要考虑延迟问题
	static function execAttrIndex($attr,$gList,$curid)
	{
		if( is_numeric($attr) ) //传的参数是id
		{
			$c = new TableSql('smartgroupattr');
			$attr = $c->query('*','ID=?',array($attr));
		}
		if ( NULL == $attr )
		{
			//表示该情景模式已经执行完成
			//判断是否由其它情景模式调用。如果是，则需要继续调用上级情景模式的下一个
			if( validID($curid) )
			{
				$c = new TableSql('smartgroupattr');
				$attr = $c->query('*','PREID=?',array($curid));
			}
			if( NULL == $attr )
			{
				return true;
			}
			$curid = NULL;
		}
		//如果没有延迟的，则直接执行
		if( 0 == $attr['DELAYMS'] )
		{
			return self::execAttr($attr,$gList,$curid);
		}
		
		include_once('plannedTask/PlannedTask.php');
		$planTask = new PlannedTask('smart','group', $attr['DELAYMS']);
		$attr['DELAYMS'] = 0; //
		$planTask->execAttrIndex($attr,$gList,$curid);	
		return false;
	}
	
	static function execAttr($attr,$gList,$curid)
	{
		$c = new TableSql('smartgroupattr');
		if( is_numeric($attr) ) //传的参数是id
		{
			$attr = $c->query('*','ID=?',array($attr));
		}
		if ( NULL == $attr )
		{
			return true;
		}
		
		$devAttr = array();
		do{
			//判断是否有执行前置条件，如果有。判断是否满足。满足才执行
			$cond = unserialize($attr['COND']);
			$plan = unserialize($attr['PLANCFG']);
			if( is_array($cond) && isset($cond['cond']) ) //这个表明了其有设置条件，需要判断
			{
				//为了发送效率，同时发送的多条指令，可能会组合到最后判断是否同一设备统一发送
				//但如果有条件判断，需要把前面的先执行了。
				//因为该判断条件可能依赖于前面的执行结果。比如数字设备的加减
				$GLOBALS['dstpSoap']->setModule('home','end');
				foreach( $devAttr as $devid=>&$attrList )
				{
					$GLOBALS['dstpSoap']->sendMsg($devid, $attrList);
				}
				$devAttr = array();
				
				$GLOBALS['dstpSoap']->setModule('smart','smart');
				$cond = $GLOBALS['dstpSoap']->checkAttrStatus($cond);
			}
			if( $cond && $plan ) //判断时间是否满足
			{
				$GLOBALS['dstpSoap']->setModule('smart','smart');
				$cond = $GLOBALS['dstpSoap']->isInTimeCyc($plan);
			}
			$continue = true;
			
			if( $cond )
			{
				$attr['ATTR'] = unserialize($attr['ATTR']);

				if( $attr['ATTRID'] < MAX_SEP_VALUE )
				{
					//情景模式把所有相同设备的属性集中在一条消息发送，execAttr不直接发送，而是把信息返回
					$GLOBALS['dstpSoap']->setModule('devattr','attr');
					$r = $GLOBALS['dstpSoap']->execAttr($attr['ATTRID'], $attr['ATTR'], true);
					if(is_array($r)) 
					{
						if( !isset($devAttr[$r['DEVID']]) )
						{
							$devAttr[$r['DEVID']] = array();
						}
						$devAttr[$r['DEVID']][]	= $r['ATTR'];
					}
				}
				else if( $attr['ATTRID'] < MAX_INT_VALUE ) //
				{
					$GLOBALS['dstpSoap']->setModule('smart','devgroup');
					$GLOBALS['dstpSoap']->execDevGroup($attr['ATTRID'], $attr['ATTR'] );
				}
				else
				{
					$groupid = $attr['ATTRID'] - MAX_INT_VALUE;
					$continue = self::execGroup($groupid,$gList,$attr['ID']);
					//如果没执行完毕，则这儿不应该继续下去，而应该等该模式来触发继续
				}
			}
			if( $continue )
			{
				$attr = $c->query('*','PREID=?',array($attr['ID']));
			}
			else
			{
				$attr = NULL;//如果有调用情景模式没执行，则这儿先暂停执行
			}

		}while( (NULL != $attr) && ( 0 == $attr['DELAYMS'] ) );//如果没有延迟的，则直接循环执行，不递归调用
		

		$GLOBALS['dstpSoap']->setModule('home','end');
		foreach( $devAttr as $devid=>&$attrList )
		{
			$GLOBALS['dstpSoap']->sendMsg($devid, $attrList);
		}
		
		return self::execAttrIndex($attr,$gList,$curid);
	}
}
?>