<?php
//简单开关
class kgAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM,'rep'=>1);
	static $page = 'switch'; 
	static $name = DEV_SYSNAME_KG;

	//命令格式char switch
	static $packfmt   = 'c';
	static $unpackfmt = 'c';

	static function getOtherInfo($value,$id)
	{
		$a = array(
			0   => ATTRCFG_GUAN,
			1   => ATTRCFG_KAI,
		);
		return $a;
	}

	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'dz'=>array('开','关','反转','翻转'));
	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$ret  = false;
		$info = NULL;
		$dz = false;
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
			$ret = true;
			$cmd = -1;
			switch( $word['word'] )
			{
				case '开':
					$info = '打开';
					$cmd = 1;
					break;
				case '关':
					$info = '关闭';
					$cmd = 0;
					break;
				case '反转':
					$info = '反转';
					$cmd = 2;
					break;
				case '翻转':
					$info = '翻转';
					$cmd = 2;
					break;
				default:
					$ret = false;
					break;
			}
			if($ret)
			{
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($attrid,$cmd);
			}
			else
			{
				$info = YUYIN_OP_CMDFAIL;
			}
			break;
		}
		if(!$dz)
		{
			$info = YUYIN_OP_CMDFAIL;
		}
		return array('ret'=>$ret,'info'=>$info);
	}
		
}

 

?>