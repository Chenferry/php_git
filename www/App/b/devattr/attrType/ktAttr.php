<?php
//空调
class ktAttrType
{
	static $cfg = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR);
	static $page = 'air'; 
	static $del  = array('m'=>'home','s'=>'remote','f'=>'delRemote'); 
	static $name = DEV_SYSNAME_KT;


	
	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$id)
	{
		$value = unserialize($value);
		if ( !$value )
		{
			//传递默认值
			$value = array(
				'open' => 1,
				'mode' => 0,
				'temp' => 26,
				'wind' => 0,
				'winddir' => 0,
			);
		}
		return $value;
	}
	
	private static function hxdgetCMDInfo($value,$id,$cfg)
	{
		$result = array();
		$c = new TableSQL('homeattr','ID');
		$result['index'] = $c->queryValue('ATTRINDEX','ID=?',array($cfg['remoteid']));
		$GLOBALS['dstpSoap']->setModule('home','hxd');
		$result['value'] = $GLOBALS['dstpSoap']->getIRCode($value,$cfg['code']);
		
		return $result;
	}
	private static function norgetCMDInfo($value,$id,$attrindex)
	{
		//默认为按了开关键
		$value['key'] = 'open';

		$result = array();
		$c = new TableSQL('homeremote','ID');
		$irid = $c->queryValue('IRATTR','ID=?',array($attrindex));
		$c = new TableSQL('homeattr','ID');
		$result['index'] = $c->queryValue('ATTRINDEX','ID=?',array($irid));
		//根据设置值，获取数据
		$GLOBALS['dstpSoap']->setModule('home','remote');
		$result['value'] = $GLOBALS['dstpSoap']->getIRCode($value,$id);

		return $result;
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$id)
	{
        $value = unserialize($value);
        //下发命令时候，要同时把该命令缓存到状态中.作为下次用户操作显示
        $c = new TableSQL('homeattr','ID');
        $attrstr=$c->queryValue('ATTRSTR','ID = ?',array($id));
        $attrstr = unserialize($attrstr);

        //打开空调时，如果是自动模式则改为制冷模式
        if($attrstr['mode'] == 0 && $attrstr['open'] == 1 && $value['open'] == 0)
        {
            $value['mode']=1;
        }

        $info = array();
        $info['ID']      = $id;
        $info['ATTRSTR'] = serialize($value);
        $c->update1($info);
        noticeAttrModi($id);

        $attrinfo  = $c->query('CFGINFO,ATTRINDEX','ID=?',array($id));

        $cfg       = $attrinfo['CFGINFO'];
        $attrindex = $attrinfo['ATTRINDEX'];

        $result = array();
        $cfg = unserialize($cfg);

        switch( $cfg['type'] )
        {
            case 'hxd':
                $result = self::hxdgetCMDInfo($value,$id,$cfg);
                break;
            default:
                $result = self::norgetCMDInfo($value,$id,$attrindex);
                break;
        }
        return $result;
	}

	static function getOtherInfo($value,$id)
	{
		$a = array(
			'open' => array(1=>ATTRCFG_GUAN,0=>ATTRCFG_KAI),
			'mode' => array(0=>ATTRCFG_ZIDONG,1=>ATTRCFG_KT_ZHILENG,2=>ATTRCFG_KT_CHUSHI,3=>ATTRCFG_KT_TONGFENG,4=>ATTRCFG_KT_ZHIRE),
			'temp' => array(),
			'wind' => array(0=>ATTRCFG_ZIDONG,1=>ATTRCFG_KT_DI,2=>ATTRCFG_KT_ZHONG,3=>ATTRCFG_KT_GAO),
			'winddir' => array(0=>ATTRCFG_ZIDONG,1=>ATTRCFG_KT_FENGXIANG1,2=>ATTRCFG_KT_FENGXIANG2,3=>ATTRCFG_KT_FENGXIANG3,4=>ATTRCFG_KT_FENGXIANG4),
		);
		for( $i=16;$i<30;$i++ )
		{
			$a['temp'][$i] = $i;
		}
		return $a;
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