<?php
//告警：红外，煤气......
class gjAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>1,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT,'rep'=>1);
	static $page = 'alarm'; 
	static $name = DEV_SYSNAME_GJ;

	//命令格式char alarm
	static $packfmt   = 'c';
	//static $unpackfmt = 'c';
	
	//解析附加信息
	//uchar alen
	//char* ainfo  //正常时显示信息
	//uchar blen
	//char* binfo //告警时显示信息
	static function parseAdditonInfo($value,$attrid)
	{
		if( NULL == $value )
		{
			return NULL;
		}
		$value = $value['info'];
		$len   = unpack('Clen',$value);
		$len   = $len['len'];	
		$info  = substr($value,1,$len);
		
		$value = substr($value,1+$len);
		$blen  = unpack('Cblen',$value);
		$blen  = $blen['blen'];	
		$binfo = substr($value,1,$blen);

		//如果参数$value只有1+$blen个字符，那么函数和将会返回结果false给$value
        $value = substr($value,1+$blen);
        if(!$value)
        {
            $action=0;
        }
        else
        {
            $alen  = unpack('Calen',$value);
            $alen  = $alen['alen'];
            $action = intval(substr($value,1,$alen));
        }
		return array(
		    'info'   => array(0=>$info,1=>$binfo),
		    'action' => $action
		 );
        //'action'：0/1/2,安防类型/联动类型/一直布防
        //'alarm':bu/ce布防或者撤防
	}

	static function getDetail($value,$attrid=NULL)
	{
		$info = array();
		$info['value'] = $value;
		$info['record'] = array();
		//获取告警信息
		$c = new TableSql('commonalarm','ID');
		$alarms = $c->queryAll('STIME,CTIME','AATTR=? ORDER BY STIME DESC',array($attrid));
		foreach($alarms as &$a)
		{
			$day = date('Y-m-d',$a['STIME']);
			if(!isset($info['record'][$day]))
			{
				$info['record'][$day] = array();
			}
			$tmp = array( 's'=>date('H:i',$a['STIME']) );
			if( INFINITE_TIME != $a['CTIME'] )
			{
				$tmp['e'] = gmdate('H时i分s秒',$a['CTIME']-$a['STIME']);
			}
			else
			{
				$tmp['e'] = '--';
			}
			$info['record'][$day][] = $tmp; 
		}
		return $info;		
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{	
		if( strlen($value) != 1 )
		{
			$info = unpack('Cindex/Cvalue',$value);
			$last = Cache::get('gj-'.$attrid) ? : array();
			foreach( $last as $k => $v ) 
			{
				if( time()-$v > 30 )	unset($last[$k]);
			}
			if( in_array($info['index'],array_keys($last)) ) return false;
			$last[$info['index']] = time();
			Cache::set('gj-'.$attrid,$last,30);			
		}
		else
		{
			$info = unpack('Cvalue',$value);			
		}
		$c = new TableSql('homeattr','ID');	
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid)); 
		$cfg = unserialize($cfg);
		if( $cfg['alarm'] == 'ce' )
		{
			return false;
		}
		$value = $info['value'] ? 1 : 0;
		return $value;
	}

	//把数据库信息通过pack转化为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = array();
		$cfg['ID'] = $attrid;
		$cfginfo = array();
		$cfginfo['alarm']  = 'bu';
		$cfginfo['action'] = 0;
		$cfg['ISC'] 	   = 1;
		switch( $value )
		{
			case 'bu'://布防
			case 'ce'://撤防
			case '0'://告警器
			case 1://探测器
			case 2://24h告警器
				if( 1 == $value || 2 == $value )
				{
					$cfg['ISC'] = 0;					
					$cfginfo['action'] = $value;
				}
				if( 'ce' == $value )
					$cfginfo['alarm']  = 'ce';
				//设置撤防或者触发时不报警状态时要清除该设备的告警通知
				if( 'ce' == $value ||  1 == $value )
				{
					$c = new TableSql('homeattr','ID');
					$id = $c->query('DEVID','ID=?',array($attrid));
					$GLOBALS['dstpSoap']->setModule('frame','alarm');
					$GLOBALS['dstpSoap']->alarm($id['DEVID'],$attrid,DEV_ALARM_CLEAN);
				}
				$cfg['ATTRSET'] = serialize($cfginfo);
				$c->update($cfg);
				//状态有变化，通知客户端刷新数据
				noticeAttrModi($attrid);
				$result = ( $cfginfo['alarm']=='ce' || $cfginfo['action']==1 )?0:1;
				return $result;
				break;
			case 'clean':			
				$c = new TableSql('commonalarm','ID');
				$c->del('AATTR=?',array($attrid));
				return false;
				break;
		}
		return false;
	}
	static function getAlarmInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		//作为活动监控，无需发光告警信息
		if( $cfg['alarm']=='ce' || $cfg['action']==1 )
		{
			return DEV_ALARM_IGNORE;
		}			
		return intval($value)?DEV_ALARM_ALARM:DEV_ALARM_CLEAN;
	}

	static function getOtherInfo($value,$id)
	{
		$c = new TableSql('homeattr','ID');
		$gjinfo = $c->query('CFGINFO,ATTRSET','ID=?',array($id));
		$cfg = unserialize($gjinfo['CFGINFO']);
		if( false == $cfg )
		{
			$cfg = array( );
		}
		if( !array_key_exists('info',$cfg) )
		{
			$cfg['info'] = array(0=>'正常',1=>'告警');
		}
		$setCfg = unserialize($gjinfo['ATTRSET']);
		$cfg['action'] 	= !array_key_exists('action',$setCfg)?$cfg['action']:$setCfg['action'];
		$cfg['alarm']	= !array_key_exists('alarm',$setCfg)?'bu':$setCfg['alarm'];
		return $cfg;
	}

	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'dz'=>array('布防','撤防'));
	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$ret = false;
		foreach( $yuyin as $word )
		{
			switch( $word['word'] )
			{
				case '布防':
					$ret = true;
					$info = '布防';
					$GLOBALS['dstpSoap']->setModule('devattr','attr');
					$GLOBALS['dstpSoap']->execAttr($attrid,1);
					break;
				case '撤防':
					$ret = true;
					$info = '撤防';
					$GLOBALS['dstpSoap']->setModule('devattr','attr');
					$GLOBALS['dstpSoap']->execAttr($attrid,0);
					break;
				default:
					break;
			}
			if($ret)
			{
				break;
			}
		}
		if(!$ret)
		{
			$info = YUYIN_OP_CMDFAIL;
		}
		return array('ret'=>$ret,'info'=>$info);
	}	
}
?>