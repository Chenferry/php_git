<?php
//灯具遥控

/*灯具遥控数据保存：homeattr中的ATTRSET字段
array(
	'button'=>array(
		1=>array(
			'AID'	=>'属性ID',	//没有设置则为'-1'
			'MID'	=>'上次执行的模式ID',
		),
		2=>array(……),
		3=>array(……),
		4=>array(……),
	),
	'slider' = '滑动条最大值',	
);*/

class djykAttrType
{
	static $cfg  = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'djyk'; 
	static $name = 'djyk';

	//解析附加信息
	//第一个字节，表示该遥控器能选几组灯
	//第二个字节，表示该遥控器的滑动条最大值
	static function parseAdditonInfo($value,$attrid)
	{
		$cfg = self::queryCfg($attrid); //查询数据库信息
		if(!$cfg)
		{
			$value = unpack('Cver/Cnum/Crange',$value['info']);
			$cfg = array();
			$cfg['button'] = array_fill(1,$value['num'],array('AID'=>'-1','MID'=>NULL));
			$cfg['slider'] = $value['range'];
			self::updateCfg($attrid,$cfg); //更新数据库信息	
			noticeAttrModi($attrid);			
		}
	}
	

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		$cfg = self::queryCfg($attrid); //查询数据库信息

		if( isset($cfg['button'][$value['btnid']]) )
		{
			$cfg['button'][$value['btnid']]['AID'] = $value['setid'];
			self::updateCfg($attrid,$cfg); //更新数据库信息	
		}
		noticeAttrModi($attrid);
		return false;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$value = unpack('Cindex/Ccmd/Cbtnid/Cinfo',$value);
		if( $value['index'] == 0 ) return false;
		$last = Cache::get('djyk-'.$attrid) ? : array();
		foreach( $last as $k => $v ) 
		{
			if( time()-$v > 30 )	unset($last[$k]);
		}
		if( in_array($value['index'],array_keys($last)) ) return false;
		$last[$value['index']] = time();
		Cache::set('djyk-'.$attrid,$last,30);

		$cfg = self::queryCfg($attrid); //查询数据库信息

		if( !isset($cfg['button'][$value['btnid']]) || $cfg['button'][$value['btnid']]['AID']=='-1')
		{
			return false;
		}
		
		//ATTRSET每个指定两个值:AID,关联的属性ID，MID，上次执行的模式ID(-2表示暖光，-1表示白光)
		$button  = $cfg['button'][$value['btnid']];
		$colorid = $button['AID'];
		$modeid  = $button['MID'];
		$max 	 = $cfg['slider'];
		$cmd = array();
		
		$c = new TableSql('homeattr','ID');
		$sysname = $c->queryValue('SYSNAME','ID=?',array($colorid));
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$GLOBALS['dstpSoap']->setAttrType($sysname);
		$last = $GLOBALS['dstpSoap']->getViewInfo(NULL,$colorid);

		switch($value['cmd'])
		{
			case 0: //亮度 
				//亮度计算要根据附加信息中的滑动条最大值来计算
				$cmd['m'] = 1;
				$cmd['l'] = $value['info']*100/$max;
				$cmd['w'] = isset($last['w']) ? $last['w'] : 0;
				$cmd['s'] = 0;
				break;
			case 1: //色温
				//色温计算要根据附加信息中的滑动条最大值来计算
				$cmd['m'] = 1;
				$cmd['l'] = isset($last['l']) ? $last['l'] : 100;
				$cmd['w'] = $value['info']*100/$max;
				$cmd['s'] = 0;
				break;
			case 2: //颜色
				//颜色计算要根据附加信息中的滑动条最大值来计算
				//颜色值相当于H值，0-360
				$h = $value['info']*360/$max;
				$cmd['m'] = 2;
				$cmd['w'] = 0;
				$cmd['s'] = 0;
				$cmd['r'] = self::HLS2RGBvalue($h+120)*255;
				$cmd['g'] = self::HLS2RGBvalue($h)*255;
				$cmd['b'] = self::HLS2RGBvalue($h-120)*255;
				break;
			case 3: //模式
				//查找当前指定灯组有设置了哪些模式，直接按顺序执行
				//执行后，要把当前执行的顺序写进ATTRSET，下次从该次基础继续
				$mode = self::queryCfg($colorid); //查询彩灯当前幻彩模式
				if( !$mode )
				{
					if( $modeid == '-1' )
					{
						$cmd = array('m'=>1,'l'=>100,'w'=>100,'s'=>0); //暖光
						$modeid = -2;
					}
					else
					{
						$cmd = array('m'=>1,'l'=>100,'w'=>0,'s'=>0); //白光
						$modeid = -1;
					}
				}
				elseif( count($mode)==1 )
				{
					switch( $modeid )
					{
						case $mode[0]['id']:
							$modeid = -1;
							$cmd 	= array('m'=>1,'l'=>100,'w'=>0,'s'=>0); //白光
							break;
						case -1:
							$modeid = -2;
							$cmd 	= array('m'=>1,'l'=>100,'w'=>100,'s'=>0); //暖光
							break;
						default:
							$modeid = $mode[0]['id'];
							$cmd 	= array('m'=>3,'id'=>$modeid);
							break;
					}
				}
				else
				{
					foreach( $mode as $key => $v )
					{
						if( $v['id'] == $modeid )
						{
							$modeid = ($key+1)==count($mode) ? $mode[0]['id'] : $mode[$key+1]['id'];
							break;
						}
					}
					if( $modeid == $button['MID'] )
					{
						$modeid = $mode[0]['id'];
					}				
					$cmd = array('m'=>3,'id'=>$modeid);
				}
				$cfg['button'][$value['btnid']]['MID'] = $modeid;
				self::updateCfg($attrid,$cfg); //更新当前执行的幻彩模式id	
				break;
			case 4: //开
				//直接下发开灯指令
				$cmd['m'] = 0;
				break;
			case 5: //关 
				//直接下发关灯指令
				$cmd['m'] = -1;
				break;
			case 6: //反转
				$cmd['m'] = ( (NULL == $last) || (-1 == $last['m']) ) ? 0 : -1;
				break;
			default:
				//获取当前灯的开或者关状态，自己计算出反转信息
				//设备组使用上一次执行状态
				return false;
				break;
		}
		if(	$cmd )
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($colorid,$cmd);			
		}
		return false;
	}
	static function getOtherInfo($value,$id)
	{
		return self::queryCfg($id); //查询数据库信息
	}
	//彩灯RGB与HSL的转换
	static function HLS2RGBvalue($hue)
	{
		if($hue > 360)
			$hue -= 360;
		else if($hue < 0)
			$hue += 360;
		
		
		if($hue < 60)
			return $hue/60;
		else if($hue < 180)
			return 1;
		else if($hue < 240)
			return (240-$hue)/60;
		else
			return 0;
	}
	//查询数据库信息
	static function queryCfg($attrid)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid));
		return unserialize($cfg);
	}
	
	//更新数据库信息
	static function updateCfg($attrid,$cfg)
	{
		$c = new TableSql('homeattr','ID');
		$info = array();
		$info['ID']      = $attrid;
		$info['ATTRSET'] = serialize($cfg);
		$c->update($info);
	}
	//////////////////////语音控制相关////////////////////////////////////
	//////////////////////////无//////////////////////////////////////////
	
}

 

?>