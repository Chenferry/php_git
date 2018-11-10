<?php
class alarmInterFace
{
	//查询当前有效的告警通知列表
	private static function getAlarmList()
	{
		$c = new TableSql('commonalarm','ID');
		return $c->queryAll('*','(ALARMCODE=0) OR ((ALARMCODE!=0) AND (CTIME=?) )',array(INFINITE_TIME));
	}
	
	//查找当前告警或者通知
	private static function getAlarm($devid,$attrid,$code,&$source)
	{
		$c = new TableSql('commonalarm','ID');
		return $c->query('ID,ALARMCODE','ADEV=? AND AATTR=? AND ASOURCE=? AND CTIME=?',array($devid,$attrid,$source,INFINITE_TIME));
	}
	
	//虚拟构造一个告警通知设备。返回的结构需符合status.php的要求。
	//这儿要考虑下，在c中如何通过接口获取b的告警信息，b中通过接口获取c中的告警信息
	static function getAlarmDev()
	{
		$alarmDevList = Cache::get('alarmDevList');
		if( false !== $alarmDevList )
		{
			return $alarmDevList;
		}
		//查询当前是否还有告警，如果没有，直接返回NULL
		$aList = self::getAlarmList();
		if ( NULL == $aList )
		{
			Cache::set('alarmDevList',array());
			return NULL;
		}

		include_once('a/commonLang.php');
		$dev = array();
		$dev['ID']     = '0xfffffff';
		$dev['NAME']   = HOME_SYSDEV_ALARM;
		$dev['ROOMID'] = '';
		$dev['PHYDEV'] = PHYDEV_TYPE_SYS;
		$dev['STATUS'] = DEV_STATUS_RUN;
		$dev['sub']    = array();
		$calarm = new TableSql('commonalarm','ID');
		foreach( $aList as &$a)
		{
			$source = unserialize($a['ASOURCE']);
			$GLOBALS['dstpSoap']->setModule($source['m'],$source['s']);
			$devname  = $GLOBALS['dstpSoap']->getAlarmAttrName($a['ADEV'],$a['AATTR']);
			if( NULL == $devname )
			{
				$calarm->del('ID=?',array($a['ID']));
				continue;
			}

			$info = $a['ALARMINFO'];
			if( 0 != $a['ALARMCODE'] )	//告警
			{
				$info = $GLOBALS['gAlarmInfo'][$a['ALARMCODE']];
			}
			$attr = array('ID'=>$a['ID'],'ATTRID'=>$a['AATTR'],'DEVID'=>$a['ADEV'],'NAME'=>$devname,
							'INFO'=>$info,'CODE'=>$a['ALARMCODE'],'SOURCE'=>$source);

			$dev['sub'][] = $attr;
		}
		Cache::set('alarmDevList',$dev);
		return $dev;
	}
	
	
	/* devid：设备ID/APP ID
	 * attrid：属性ID
	 * info：告警码/告警信息。如果是0，表示清除告警
	 * source：告警来源
	 */
	static function alarm($devid,$attrid,$code=DEV_ALARM_CLEAN,$source=array('m'=>'devattr','s'=>'devattr'))
	{
		Cache::del('alarmDevList');
		$source = serialize($source);
		//查询当前的告警信息
		$info = self::getAlarm($devid,$attrid,$code,$source);

		if( DEV_ALARM_CLEAN === $code )
		{
			if( NULL != $info )
			{
				statusNotice('status');//状态有变化，通知客户端刷新数据
			}
			return self::clean($info);
		}
		
		//当前已经有告警，则不再处理直接返回
		if( NULL != $info )
		{
			return true;
		}

		$infos = array();
		$infos['ASOURCE'] = $source;
		$infos['ADEV']    = $devid;
		$infos['AATTR']   = $attrid;
		$infos['STIME']   = time();
		if( is_int($code) )	//告警
		{
			$infos['ALEVEL']    = 'warn';
			$infos['ALARMCODE'] = $code;
			$infos['ALARMINFO'] = '';
		}
		else
		{
			$infos['ALEVEL']    = 'info';
			$infos['ALARMCODE'] = 0;
			$infos['ALARMINFO'] = $code;

		}
		$c = new TableSql('commonalarm','ID');
		$id = $c->add($infos);
		
		self::sendNotice($devid,$attrid,$code,$source);
		statusNotice('status');//状态有变化，通知客户端刷新数据
		return validID($id);
	}
	
	static function cleanDevAlarm($devid)
	{
		Cache::del('alarmDevList');
		//清除指定设备或者属性的告警信息
		$c = new TableSql('commonalarm','ID');
		$c->del('ADEV=?',array($devid));
		return true;
	}

	
	//推送消息到手机
	static function sendNotice($devid,$attrid,$code,$source=NULL)
	{
		include_once('a/commonLang.php');
		$pushInfo = $code;
		if( is_int($code) )	//告警
		{
			$pushInfo = $GLOBALS['gAlarmInfo'][$code];
		}
		if( NULL == $source )
		{
			$source = array('m'=>'devattr','s'=>'devattr');
		}
		if( !is_array($source) )
		{
			$source = unserialize($source);
		}
		$GLOBALS['dstpSoap']->setModule($source['m'],$source['s']);
		$devname  = $GLOBALS['dstpSoap']->getAlarmAttrName($devid,$attrid);
		
		$info = array();
		$c = new TableSql('hic_hic','ID');
		$name = $c->queryValue('NAME');

		$info['TITLE']       = date('m-d H:i').'-'.trim($name).HOME_DEV_ALARM_TITLE;
		$info['DESCRIPTION'] = sprintf(HOME_DEV_ALARM_DESCRIPT,$devname,$pushInfo);

        include_once('plannedTask/PlannedTask.php');



        //如果推送内容是告警、且主机是4G主机，那么就对信息进行短信发送
        if( is_int($code) && defined('HIC_SYS_HAVE4G') && (true==HIC_SYS_HAVE4G))
        {
            $c = new TableSql('hic_userinfo');
            $phoneList=$c->queryAllList("PHONE");
            if($phoneList!==NULL)
            {
                $planTask = new PlannedTask('delay','phone');
                foreach ($phoneList as $phone)
                {
                    $msg=$info['TITLE']."-".$info['DESCRIPTION'];
                    $planTask->sendSmsInfo($phone,$msg);
                }
            }
        }

        // $planTask = new PlannedTask('delay','push');
        // $planTask->sendNotice($info);

		//$GLOBALS['dstpSoap']->setModule('app','push');
		//$RES=$GLOBALS['dstpSoap']->sendNotice($info);
		return ;
	}


	//清除告警
	private static function clean(&$info)
	{
		if( NULL == $info )
		{
			return true;
		}
		$c = new TableSql('commonalarm','ID');

		//如果是告警，则更新告警消除时间;如果是通知，则删除
		if( !validID($info['ALARMCODE']) ) //告警是发告警码，通知则是发信息
		{
			return $c->delByID($info['ID']);
		}
		//如果是通知，则删除通知
		$info['CTIME'] = time();
		$c->update($info);
		return true;
	}
}
?>