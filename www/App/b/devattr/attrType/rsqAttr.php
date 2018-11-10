<?php
//热水器
class rsqAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM,'rep'=>1);
	static $page = 'rsq'; 
	static $name = DEV_SYSNAME_KG;
	
	static function getViewInfo($value,$id)
	{
		//{ isopen:true,curTemp:'50',setTemp:'40' }
		$c = new TableSql('homeattr','ID');
		$value = $c->queryValue('SENDATTR','ID=?',array($id));
		return unserialize($value);
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		
		//把设定温度保存进数据库中
		return pack('CC', $value['isopen'], $value['setTemp']);
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$ret = unpack("Cisopen/CsetTemp/CcurTemp",$value);
		
		//详细信息更新到SENDATTR中
		$c = new TableSql('homeattr','ID');
		$attr  = $c->queryValue('SENDATTR','ID=?',array($attrid));
		$value = serialize($ret);
		if( $attr != $value )
		{
			$info = array();
			$info['SENDATTR'] = $value;
			$info['ID']       = $attrid;
			$c->update1($info);
			noticeAttrModi($attrid);
		}

		return $ret['curTemp'];
	}



	static function getOtherInfo($value,$id)
	{
		$a = array(
			'min'   => 40,
			'max'   => 60,
		);
		return $a;
	}

	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'dz'=>array('开','关'));
	
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$cmd  = self::getViewInfo(NULL,$attrid);
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
			switch( $word['word'] )
			{
				case '开':
					$info = '打开';
					$cmd['isopen'] = 1;
					break;
				case '关':
					$info = '关闭';
					$cmd['isopen'] = 0;
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