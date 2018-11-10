<?php
//红外
class hxdAttrType
{
	static $cfg = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR,'rep'=>1);
	static $page    = 'remote'; 
	static $packfmt   = 'c';
	
	//空调学习发送过来时是经过压缩，需要解压缩后才能发送给服务器匹配学习
	private function deCompress($data)
	{
		$unzip =  NULL;
		$offset = 0;
		
		$len = strlen($data);
		while( $offset < $len )//去除第一个字节00
		{
			$repeatNum = ord($data[$offset++]);
			$chr       = $data[$offset++];
			for($j=0; $j<$repeatNum; $j++)
			{
				$unzip .= $chr;
			}
		}
		return $unzip;
	}

	private static function procCatach1(&$ircache,&$value,$attrid)
	{
		$value = substr($value,1);//第一个字节是长度，去掉再解压
		//进行解压
		$value = self::deCompress($value);
		if( 230 != strlen($value) )
		{
			return false;
		}
		
		// 需要重新编码一下,因为有些数据库不能二进制流方式存储
		$value = base64_encode($value);
		//向服务器请求匹配学习。这个很可能比较耗时，需要延时处理
		include_once('plannedTask/PlannedTask.php');
		$planTask 	  = new PlannedTask('home','hxd');
		$planTask->getIRMatch($value,$ircache['type']);
		
		return false;		
	}

	private static function procCatach2(&$ircache,&$value,$attrid)
	{
		$cachename = 'ircache_2';
		$ircache['match'] = 1;	
		$ircache['info'][0] = $value;		
		Cache::set($cachename,$ircache,20);
		return false;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		if( $value == '')
		{
			return false;
		}
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
				DEV_REMOTE_AIR     => '空调',
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