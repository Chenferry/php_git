<?php
//空调
class kt1AttrType
{
	static $cfg = array('r'=>0,'c'=>1,'s'=>1,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR,'rep'=>1);
	static $page = 'air'; 
	static $del  = array('m'=>'home','s'=>'remote','f'=>'delRemote'); 
	static $name = DEV_SYSNAME_KT;

	//把数据库信息通过pack转化为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		switch( $value['other'] )
		{
			case 'open'://开关机
				$cmd['m'] = 1;
				break;
			case 'temp'://设置温度
				$cmd['m'] = 2;
				break;
			case 'mode'://设置模式
				$cmd['m'] = 3;
				break;
			case 'wind'://风速控制
				$cmd['m'] = 4;
				break;
			case 'winddir'://风向控制
				$cmd['m'] = 5;
				break;
		}
		return pack('CC',$cmd['m'],$value[$value['other']]);
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		if( $value=='' ) return;
		$cfg = self::queryCfg($attrid); //查询数据库信息
		if ( !$cfg )
		{
			//传递默认值
			$cfg = array(
				'open' => 1,
				'mode' => 0,
				'temp' => 16,
				'wind' => 0,
				'winddir' => 0,
			);
		}
		$info = unpack("Cm/Cinfo",$value);

		switch($info['m'])
		{
			case 1://开关机上报
				$cfg['open'] = $info['info'];
				break;
			case 2://当前温度上报
				$cfg['temp'] = $info['info'];
				break;					
			case 3://当前模式上报
				$cfg['mode'] = $info['info'];
				break;
			case 4://当前风速上报
				$cfg['wind'] = $info['info'];
				break;
			case 5://当前风向上报
				$cfg['winddir'] = $info['info'];
				break;
			case 6://所有状态上报
				$info = unpack("Cm/Copen/Ctemp/Cmode/Cwind/Cwinddir",$value);
				$cfg['open'] 	= $info['open'];
				$cfg['temp'] 	= $info['temp'];
				$cfg['mode'] 	= $info['mode'];
				$cfg['wind'] 	= $info['wind'];
				$cfg['winddir'] = $info['winddir'];
				break;				
		}
		self::updateCfg($attrid,$cfg); //更新数据库信息	
		noticeAttrModi($attrid);
	}
	
	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$attrid)
	{
		$cfg = self::queryCfg($attrid); //查询数据库信息
		if ( !$cfg )
		{
			//传递默认值
			$cfg = array(
				'open' => 1,
				'mode' => 0,
				'temp' => 16,
				'wind' => 0,
				'winddir' => 0,
			);
		}
		return $cfg;
	}
	static function getOtherInfo($value,$id)
	{
		$a = array(
			'open' => array(				//开关机
				0 => ATTRCFG_KAI, 			//开机
				1 => ATTRCFG_GUAN			//关机
			),
			'temp' => array_combine(range(10,30),range(10,30)),	//设定温度
			'mode' => array(				//设置模式
				0 => ATTRCFG_ZIDONG,		//自动
				1 => ATTRCFG_KT_ZHILENG,	//制冷
				2 => ATTRCFG_KT_CHUSHI,		//除湿
				3 => ATTRCFG_KT_TONGFENG,	//通风
				4 => ATTRCFG_KT_ZHIRE		//制热
			),
			'wind' => array(				//风速控制
				0 => ATTRCFG_ZIDONG,		//自动
				1 => ATTRCFG_KT_DI,			//低速
				2 => ATTRCFG_KT_ZHONG,		//中速
				3 => ATTRCFG_KT_GAO			//高速
			),
			'winddir' => array(				//风向控制
				0 => ATTRCFG_ZIDONG,		//自动
				1 => ATTRCFG_KT_FENGXIANG1,	//风向1
				2 => ATTRCFG_KT_FENGXIANG2,	//风向2
				3 => ATTRCFG_KT_FENGXIANG3,	//风向3
				4 => ATTRCFG_KT_FENGXIANG4	//风向4
			),
		);
		return $a;
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
	//替换属性时，保存的状态也要相应替换
	static function replaceAttrNotice($oldid,$newid)
	{
		$c = new TableSql('homeattr','ID');
		$result = $c->queryAll('ID,ATTRSET',"homeattr.ID in ($oldid,$newid)");
		foreach($result as $key => $value)
		{
			$value['ID'] = $key==0 ? $result[1]['ID'] : $result[0]['ID'];
			$attr = array('ID'=>$value['ID'],'ATTRSET'=>$value['ATTRSET']);
			$c->update($attr);
		}
	}	
	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 
			'fy'=>array('有点','太','一点','一些'),
			'dz'=>array('开','关','高','低','热','冷','大','小','风速'),
			'lc'=>array('度','摄氏度'),
			'qt'=>array(
				'自动','制冷','除湿','通风','制热',
				'风速自动','风速低','风速中','风速高',
				'风向自动','风向1','风向2','风向3','风向4',
				'风向一','风向二','风向三','风向四',
				'上下扫风','左右扫风','双向扫风','固定风向',
				'上下少风','左右少风','双向少风',
				),
			);
	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		//先获取前一个动作
		$c = new TableSQL('homeattr','ID');
		$value = $c->queryValue('ATTRSTR','ID=?',array($attrid));
		$value = self::getViewInfo($value,$attrid);
		
		$ret  = false;
		$info = NULL;
		$adjust = 0;
		$adjustindex = 1;
		$adjustnum = 1;
		foreach($yuyin as &$word)
		{
			switch( $word['word'] )
			{
				case '关':
					$ret = true;
					$value['open'] = 1;
					unset($_SESSION['dz']);
					break;
				case '开':
					$ret = true;
					$value['open'] = 0;
					$value['mode'] = 1;
					break;
				case '高':	
				case '热':
				case '大':
					$ret = true;
					$value['open'] = 0;
					$adjust = 1;
					if($word['word']=='热') $_SESSION['dz'] = 'temp';
					break;
				case '低':	
				case '冷':
				case '小':
					$ret = true;
					$value['open'] = 0;
					$adjust = -1;
					if($word['word']=='冷') $_SESSION['dz'] = 'temp';
					break;
				case '风速':
					$_SESSION['dz'] = 'wind';							
					break;
				case '太':	
					$adjustindex = -2;
					break;
				case '有点':
					$adjustindex = -1;
					break;
				case '一点':
				case '一些':
					$adjustindex = 1;
					break;
				case '自动':
					$ret = true;
					$value['open'] = 0;
					$value['mode'] = 0;
					unset($_SESSION['dz']);
					break;
				case '制冷':
					$ret = true;
					$value['open'] = 0;
					$value['mode'] = 1;
					$_SESSION['dz'] = 'mode';
					break;
				case '除湿':
					$ret = true;
					$value['open'] = 0;
					$value['mode'] = 2;
					$_SESSION['dz'] = 'mode';
					break;
				case '通风':
					$ret = true;
					$value['open'] = 0;
					$value['mode'] = 3;
					$_SESSION['dz'] = 'mode';
					break;
				case '制热':
					$ret = true;
					$value['open'] = 0;
					$value['mode'] = 4;
					$_SESSION['dz'] = 'mode';
					break;
				case '风速自动':
					$ret = true;
					$value['open'] = 0;
					$value['wind'] = 0;
					unset($_SESSION['dz']);
					break;
				case '风速低':
					$ret = true;
					$value['open'] = 0;
					$value['wind'] = 1;
					$_SESSION['dz'] = 'wind';							
					break;
				case '风速中':
					$ret = true;
					$value['open'] = 0;
					$value['wind'] = 2;
					$_SESSION['dz'] = 'wind';							
					break;
				case '风速高':
					$ret = true;
					$value['open'] = 0;
					$value['wind'] = 3;
					$_SESSION['dz'] = 'wind';							
					break;
				case '风向自动':
					$ret = true;
					$value['open'] = 0;
					$value['winddir'] = 0;
					unset($_SESSION['dz']);
					break;
				case '风向1':
				case '风向一':
				case '上下扫风':
				case '上下少风':
					$ret = true;
					$value['open'] = 0;
					$value['winddir'] = 1;
					$_SESSION['dz'] = 'winddir';
					break;
				case '风向2':
				case '风向二':
				case '左右扫风':
				case '左右少风':
					$ret = true;
					$value['open'] = 0;
					$value['winddir'] = 2;
					$_SESSION['dz'] = 'winddir';
					break;
				case '风向3':
				case '风向三':
				case '双向扫风':
				case '双向少风':
					$ret = true;
					$value['open'] = 0;
					$value['winddir'] = 3;
					$_SESSION['dz'] = 'winddir';
					break;
				case '风向4':
				case '风向四':
				case '固定风向':
					$ret = true;
					$value['open'] = 0;
					$value['winddir'] = 4;
					$_SESSION['dz'] = 'winddir';
					break;
				default:
					//如果是数字,则需要直接处理
					if( !in_array('sz',$word['attr']) 
						&& !isset($GLOBALS['numberMap'][$word['word']]))
					{
						break;
					}
					
					//数字也需要当温度设置来处理。
					//如果数字在个位数，相当于在现在基础上调整
					//如果数字在超过10，则认为直接设置
					$ret = true;
					$value['open'] = 0;
					$t = $word['word'];
					if( isset($GLOBALS['numberMap'][$t]) )
					{
						$t = $GLOBALS['numberMap'][$t];
					}
					$t = intval($t);
					if( $t > 10 )
					{
						if( $value['mode'] == 0 )
						{
							return array('ret'=>false,'info'=>'自动模式下不能调节温度，请先修改当前空调模式！');
						}
						$value['temp'] = $t;
					}
					else
					{
						$adjustnum = $t;
					}
					$_SESSION['dz'] = 'temp';;
					break;
			}
		}
		if($ret)
		{
			//获取空调当前状态
			$other = self::getOtherInfo($value,$attrid);
			if( $_SESSION['dz'] == 'wind' )
			{
				if( 0 != $adjust )
				{
					$adjustnum = $adjustnum*$adjust*$adjustindex;
					$value['wind'] = $value['wind'] + $adjustnum;
				}
				if( $value['wind'] > 3 ) $value['wind'] = 3;
				if( $value['wind'] < 1 ) $value['wind'] = 1;
			}
			else
			{
				if( 0 != $adjust )
				{
					if( $value['mode'] == 0 )
					{
						return array('ret'=>false,'info'=>'自动模式下不能调节温度，请先修改当前空调模式！');
					}
					$adjustnum = $adjustnum*$adjust*$adjustindex;
					$value['temp'] = $value['temp'] + $adjustnum;
				}
				if( $value['temp'] > end($other['temp']) ) $value['temp'] = end($other['temp']);
				if( $value['temp'] < reset($other['temp']) ) $value['temp'] = reset($other['temp']);				
			}
			
			if( 1 == $value['open'] )
			{
				$info = $other['open'][$value['open']];
			}
			else
			{
				$temp = $value['temp']; 
				$mode = $other['mode'][ $value['mode'] ]; 
				$wind = $other['wind'][ $value['wind'] ]; 
				$winddir = $other['winddir'][ $value['winddir'] ]; 
				$info = "模式:$mode,温度:$temp,风速:$wind,风向:$winddir";
			}

			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,$value);
		}
		else
		{
			$info = YUYIN_OP_CMDFAIL;
		}
		return array('ret'=>$ret,'info'=>$info);
	}
}
?>