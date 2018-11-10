<?php
//推窗器......
class tcqAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>NULL,'rep'=>1);
	static $page = 'tcq'; 
	//static $packfmt   = 'C';
	//static $unpackfmt = 'C';
	static $name = DEV_SYSNAME_TCQ;
	
	
	//按钮个数/按钮1名称长度/按钮1名称/......
	private static function getButInfo($value)
	{
		$ret = array();
		//按键个数
		$num = unpack('Cnum',$value);
		$num = $num['num'];
		$value = substr($value,1);

		if( 0xFF == $num ) //如果这个，表示每个按键就只有三个字节的名字
		{
			$i = 0;
			while( NULL != $value )
			{
				$ret[$i] = substr($value,0,3);
				$value   = substr($value,3);
				$i++;
			}	
			return $ret;
		}		
		
		for($i=0; $i<$num; $i++)
		{
			$len = unpack('Clen',$value);
			$len = $len['len'];
			
			$ret[$i] = substr($value,1,$len);
			$value   = substr($value,$len+1);
		}
		
		return $ret;		
	}

	//解析附加信息
	//按钮个数/按钮1名称长度/按钮1名称/......
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];

		$ret = array();
		//按键个数
		$info = unpack('Cnum/Cver/Cmode',$value);
		$num = $info['num'];
		if( 0 != $num )
		{
			return self::getButInfo($value);
		}
		
		$value = substr($value,3);
		$ret   = self::getButInfo($value);
		if( 0!=$info['mode'] )
		{
			$ret[0xFF] = $info['mode']; //表示是否支持摇摆
		}
		
		return $ret;
	}
	
	//把前台值转为数据库信息
	static function getDBInfo($value,$attrid=NULL)
	{
		if( is_array($value) )
		{
			return serialize($value);
		}
		if( -1 == $value)
		{
			return 0;
		}
		$value = intval($value);
		return 1<<$value;
	}
	
	
	static function getCMDInfo($value,$attrid=NULL)
	{
		if( is_int($value) )
		{
			return pack('C',$value);
		}
		if( !is_array($value) )
		{
			$value = unserialize($value);
		}
		if( 0xFF == $value['m'] )
		{
			return pack('CCC',0xFF,0,$value['t']);
		}
		return false;
	}

	static function getStatusInfo($value,$id)
	{
		for( $i=0; $i<8; $i++)
		{
			if( $value & (1<<$i) )
			{
				return $i;
			}
		}
		return -1;
	}

	static function getOtherInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( false == $cfg )
		{
			$cfg = array();
		}
		return $cfg;
	}
	//////////////////////语音控制相关////////////////////////////////////
	static function getYuyinDict($id)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($id));
		$cfg = unserialize($cfg);

		foreach($cfg as &$mode)
		{
			if( NULL == $mode )
			{
				continue;
			}
			$ret[] = array('word'=>$mode,'attr'=>'dz');
		}

		return $ret;
	}		
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);


		$ret = -1;
		$dz  = false;
		foreach($yuyin as &$word)
		{
			foreach( $word['attr'] as &$a )
			{
				if( 'dz' != $a )
				{
					continue;
				}
				$dz = true;
				break;
			}
			if(!$dz)
			{
				continue;
			}
			foreach( $cfg as $index=>$name )
			{
				if( $word['word'] != $name )
				{
					continue;
				}
				$ret = $index;
				break;
			}
			if( -1 != $ret )
			{
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($attrid,$ret);
				$info = $cfg[$ret];
			}
			else
			{
				$info = YUYIN_OP_CMDFAIL;
			}
			break;
		}
		if( -1 == $ret)
		{
			$info = YUYIN_OP_CMDFAIL;
			return array('ret'=>false,'info'=>$info);
		}
		else
		{
			return array('ret'=>true,'info'=>$info);
		}
	}
}
?>