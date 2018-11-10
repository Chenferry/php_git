<?php
//窗帘
class clAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT,'rep'=>1);
	static $page = 'cl'; 
	static $name = DEV_SYSNAME_CL;

	static $packfmt   = 'c';
	static $unpackfmt = 'c';

	static function getOtherInfo($value,$id)
	{
		if( isset($_GET['from']) 
			&& ('smart' == $_GET['from'] || 'smartmobi' == $_GET['from'] || 'between' == $_GET['from']))
		{
			return array('min'=>0,'max'=>10);
		}

		$a = array(
			0  => ATTRCFG_GUAN,
			10 => ATTRCFG_KAI,
		);
		return $a;
	}
	//保存当前命令作为下次执行的当前状态
	static function updateStatus($attrid,$value)
	{
		$c = new TableSQL('homeattr','ID');
		$info = array();
		$info['ID']      = $attrid;
		$info['ATTRINT'] = $value;
		$c->update($info);		
	}
	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 
			'dz'=>array('开','关','合','停','上','下','再','多','少','升','降','高','低','关上','合上'),
			'qt'=>array('点','一点','一半','一般'),
			);
	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$action = -2;
		$num    = -1;
		$change	= 0;
		$again 	= 0;
		$up 	= false;
		$down 	= false;
		$isPer 	= false;
		$info 	= NULL;
		foreach($yuyin as &$word)
		{
			switch( $word['word'] )
			{
				case '关上':
				case '关':
				case '合上':
				case '合':
					$action = 0;
					$change	= -1;						
					break;
				case '开':
					$action = 1;
					$change	=  1;
					if( $again != 0 )
					{
						$change = $again;
					}					
					break;
				case '停':
					$action = 2;
					break;
				case '点':
				case '一点':
					if( $again == 0 )
					{
						$again = $change;						
					}
					$num = 0.1;	
					break;
				case '再':
				case '多':
					$again = 1;
					break;				
				case '少':
					$again = -1;
					break;						
				case '一半':
				case '一般':
					$num = 0.5;	
					break;		
				case '高':
				case '上':
				case '升':
					$change	=  1;
					break;
				case '低' :
				case '下':
				case '降':
					$change	= -1;
					break;
				default:
					//如果是数字,则需要直接处理
					if( !in_array('sz',$word['attr']) )
					{
						break;
					}
					
					//数字也需要当温度设置来处理。
					//如果数字在个位数，相当于在现在基础上调整
					//如果数字在超过10，则认为直接设置
					$ret = true;
					$num = $word['word'];

					break;
			}			
		}
		if( -1 != $num )
		{
			$num = $num > 10 ? ( $num > 100 ? 10 : intval($num/10) ) : ( $num > 1 ? intval($num) : intval($num*10) );
			if( $again != 0 )
			{
				$c = new TableSql('homeattr','ID');
				$info = $c->query('ATTRINT','ID=?',array($attrid));	
				$num = $info['ATTRINT'] + $change*$num; 
				$num = $num>10 ? 10 : ($num<0 ? 0 : $num);
			}
			$ret = true;
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,intval($num));
			self::updateStatus($attrid,$num);
			return array('ret'=>$ret,'info'=>'OK');
		}
		$ret = true;
		switch($action)
		{
			case 0:
				$num = 0;
				break;
			case 1:
				$num = 10;
				break;
			case 2:
				$num = 101;
				break;
			default:
				$ret = false;
				break;
		}
		
		if($ret)
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,intval($num));
			self::updateStatus($attrid,intval($num));
			$info = "OK";
		}
		else
		{
			$info = YUYIN_OP_CMDFAIL;
		}
		return array('ret'=>$ret,'info'=>$info);
	}	
}

 

?>