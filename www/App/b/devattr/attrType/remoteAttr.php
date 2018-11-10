<?php
//红外
class remoteAttrType
{
	static $cfg = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR,'rep'=>1);
	static $page    = 'remote'; 
	static $packfmt   = 'c';
	
	private static function procCatach1(&$ircache,&$value,$attrid)
	{
		//uchar total  //学习结果总拆包数
		//uchar index  //包序号，从0开始
		//uchar len    //本包长度
		//char* info   //学习结果拆包内容
		//发来的消息是学习结果，已经拆包。学习结果暂时保存在cache中
		$cachename = 'ircache_1';
		$vi    = unpack('Ctotal/Cindex',$value);
		if ( 0 == $vi['total'] )
		{
			//超时，没学到
			//$ircache['match'] = -1;
			Cache::del($cachename);
			return false;
		}
		if( $vi['total'] > 1 && $vi['index'] == 0 )
		{
			$value = substr($value,4);
		}
		else
		{
			$value = substr($value,2);
		}
		
		//向设备回应收包应答
		$GLOBALS['dstpSoap']->setModule('home','remote');
		$GLOBALS['dstpSoap']->sendRemoteCtrlMsg($attrid, REMOTE_CMD_CATCH_CONFIRM, pack('C',$vi['index']));
		
		$ircache['match'] = 1;

		$result = &$ircache['result'];
		
		$result[$vi['index']] = $value;
		Cache::set($cachename,$ircache,20);

		//学习还没完成，直接返回
		if (  count($result)!=$vi['total'] )
		{
			return false;
		}

		$r = NULL;
		for( $i=0; $i<$vi['total']; $i++ )
		{
			$r .= $result[$i];
		}

		// 需要重新编码一下,因为有些数据库不能二进制流方式存储
		$r = base64_encode($r);
		//向服务器请求匹配学习。这个很可能比较耗时，需要延时处理
		include_once('plannedTask/PlannedTask.php');
		$planTask 	  = new PlannedTask('home','remote');
		$planTask->getIRMatch($r,$ircache['type']);
		
		//remote的上报数据是学习结果，在这儿全部处理，无需保存数据库和后续处理
		return false;		
	}

	private static function procCatach2(&$ircache,&$value,$attrid)
	{
		$cachename = 'ircache_2';
		$vi    = unpack('Ctotal/Cindex',$value);
		$ircache['match'] = 1;		
		$result = &$ircache['info'];
		if( $vi['total']!=2  && $vi['index']>=$vi['total'])
		{
			$vi['total'] = 1;
			$vi['index'] = 0;
		} 
		$result[$vi['index']] = $value;
		if( count($result)!=$vi['total'] ) $ircache['match'] = 0;

		Cache::set($cachename,$ircache,20);
		return false;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		if( $value == '') return false;
		$cachename = 'ircache_1';
		$ircache = Cache::get($cachename);
		if ( false !== $ircache )
		{
			return self::procCatach1($ircache,$value,$attrid);
			return false;
		}
		$cachename = 'ircache_2';
		$ircache = Cache::get($cachename);
		if ( false !== $ircache )
		{
			return self::procCatach2($ircache,$value,$attrid);
			return false; 
		}
		//开始学习时，就必须设置cache。如果cache不存在，很可能是已经停止学习
		return false;
	}

	static function getOtherInfo($value,$id)
	{
		return array(
			'dev' => array( 
				DEV_REMOTE_AIR     => DEV_SYSNAME_KT,
				//DEV_REMOTE_TV      => '电视',
				//DEV_REMOTE_BOX     => '机顶盒',
				//DEV_REMOTE_DVD     => 'DVD',
				//DEV_REMOTE_FAN     => '电风扇',
				//DEV_REMOTE_CLEANER => '空气净化器',
				//DEV_REMOTE_IPTV    => 'IPTV',
			),
		);
	}
	//替换红外遥控属性时，同时其他学习的设备都要跟着替换（相当于替换设备）
	static function replaceAttrNotice($oldid,$newid)
	{
		$c = new TableSql('homeattr','ID');
		$oldDevid = $c->queryValue('DEVID',"ID=?",array($newid));
		$oldAttrlist = $c->queryAllList('ID',"DEVID=?",array($oldDevid));
		$newDevid = $c->queryValue('DEVID',"ID=?",array($oldid));
		$newAttrlist = $c->queryAllList('ID',"DEVID=?",array($newDevid));
		$info = array();
		foreach ($oldAttrlist as $key => $value) 
		{
			if($value == $newid)	continue;
			$info['ID'] = $value;
			$info['DEVID'] = $newDevid;
			$c->update($info);
		}
		foreach ($newAttrlist as $key => $value) 
		{
			if($value == $oldid)	continue;
			$info['ID'] = $value;
			$info['DEVID'] = $oldDevid;
			$c->update($info);
		}
		$c = new TableSql('homeremote','ID');
		$result = $c->queryAll('ID,DEVID,IRATTR',"IRATTR in ($oldid,$newid)");
		foreach ($result as $key => $value) 
		{
			$info = $value;
			$info['DEVID'] = $value['IRATTR'] == $newid ? $oldDevid : $newDevid;
			$c->update($info);
		}
	}
}

 

?>