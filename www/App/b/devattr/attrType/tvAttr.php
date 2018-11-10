<?php
//电视
class tvAttrType
{
	static $cfg = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR);
	static $page    = 'tvctr'; 
	static $del  = array('m'=>'home','s'=>'remote','f'=>'delRemote'); 
	static $name = DEV_SYSNAME_TV;
	
	private static function getChannelIndex($channel)
	{
		if( 0 == $channel)
		{
			$channel = 16;//0按键索引是16
		}
		else
		{
			$channel = $channel + 5;//1按键从6开始，其它数字递增
		}
		return $channel;
	}


	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$id)
	{
		//情景模式下，大于100表示频道。如果是二位数，需要分三次下发
		if( $value > 109 )
		{
			$value -= 100;
			$shi = intval($value/10);
			$ge  = $value%10;
			
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($id,self::getChannelIndex($shi));
			$GLOBALS['dstpSoap']->execAttr($id,15);//--/-
			$GLOBALS['dstpSoap']->execAttr($id,self::getChannelIndex($ge));
	
			return false;
		}
		
		//
		if( $value >= 100 )
		{
			$value -= 100;
			$value = self::getChannelIndex($value);
		}
		
		$result = array();

		$c = new TableSQL('homeattr','ID');
		$attrindex = $c->queryValue('ATTRINDEX','ID=?',array($id));
		$c = new TableSQL('homeremote','ID');
		$irid = $c->queryValue('IRATTR','ID=?',array($attrindex));
		$c = new TableSQL('homeattr','ID');
		$result['index'] = $c->queryValue('ATTRINDEX','ID=?',array($irid));
		//根据设置值，获取数据
		$GLOBALS['dstpSoap']->setModule('home','remote');
		$result['value'] = $GLOBALS['dstpSoap']->getIRCode($value,$id);

		return $result;
	}

	static function getOtherInfo($value,$id)
	{
		return array();
	}

	//////////////////////语音控制相关////////////////////////////////////

	
}

 

?>