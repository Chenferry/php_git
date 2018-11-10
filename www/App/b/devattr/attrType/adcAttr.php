<?php
//ADC电路通用处理......
class adcAttrType
{
	static $cfg  = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>NULL);
	static $page = 'enum'; 

	//命令格式char alarm
	static $packfmt   = 'n';
	static $unpackfmt = 'n';
	
	//解析附加信息
	//ushort 告警阈值 如果小数，需要乘以100
	//uchar  告警方向
	//uchar  附加告警信息的长度
	//uchar* 附加告警信息的内容
	//以下为分级显示内容
	//  uchar 分级显示的个数。每个的传输方式同以下结构
	//  	uchar  实际ADC值，区间上限
	//      uchar  对应显示信息的长度
	//      uchar* 对应的显示信息

	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$ret = array();
		//告警阈值，附加告警信息长度
		$ret = unpack('nyz/Cfx/Calarmlen',$value);
		$value = substr($value,4);
		if( 0 != intval($ret['alarmlen']))
		{
			//附加告警信息
			$ret['alarminfo'] = substr($value,0,$ret['alarmlen']);
			$value = substr($value,$ret['alarmlen']);
		}
		
		//分级显示个数
		$num = unpack('Cnum',$value);
		$num = $num['num'];
		$value = substr($value,1);

		$ret['map'] = array();
		for($i = 0; $i < $num; $i++)
		{
			// uchar  实际ADC值，区间上限
			// uchar  对应显示信息的长度
			// uchar* 对应的显示信息
			$temp = unpack('nadc/Clen',$value);
			$temp['show'] =  substr($value,3,$temp['len']);
			$value = substr($value,3+$temp['len']);
			$ret['map'][ $temp['adc'] ] =  $temp['show'];
		}
		
		return $ret;
	}
	
	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		foreach( $cfg['map'] as $v=>&$show )
		{
			if( $value < $v )
			{
				return $v;
			}
		}
		//如果超出了取值上限，直接返回最大值
		return $v;
	}

	//根据状态信息判断是否告警状态
	static function getAlarmInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( 0 == $cfg['yz'] )
		{
			return DEV_ALARM_IGNORE;
		}
		if( 0 == $cfg['fx'] )
		{
			if( $value <= $cfg['yz'] )
			{
				return DEV_ALARM_ALARM;
			}
			return DEV_ALARM_CLEAN;
		}
		else
		{
			if( $value <= $cfg['yz'] )
			{
				return DEV_ALARM_CLEAN;
			}
			return DEV_ALARM_ALARM;
		}
		
		return DEV_ALARM_IGNORE;
	}
	
	//辅助前台显示的信息
	//比如pm2.5，得到的是数值，但需要根据数值转换为一个表示等级的枚举值
	//比如可能是一个多维数组，指示空调可选模式/温度列表等
	static function getOtherInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( !isset($cfg['map']) || !is_array($cfg['map']))
		{
			$cfg['map'] = array();
		}
		return $cfg['map'];
	}

}
?>