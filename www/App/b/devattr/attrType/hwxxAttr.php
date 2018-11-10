<?php
//红外
class hwxxAttrType
{
	static $cfg = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR);
	static $page    = 'hwxx'; 

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$c = new TableSQL('homeattr','ID');
		$ainfo = $c->query('CFGINFO,ATTRSET','ID=?',array($attrid));
		$butinfo = unserialize($ainfo['ATTRSET']);
		$info = NULL;
		foreach( $butinfo as &$but )
		{
			if( $but['id'] != $value )
			{
				continue;
			}
			$info = $but['info'];
			foreach ($info as $k => $v) 
			{
				$info[$k] = base64_decode($v);
			}
			break;
		}
		if( NULL == $info )
		{
			return false;
		}

		$result = array();
		if( count($info) != 1 )
		{
			$i = Cache::get('hwxxpack') ? Cache::get('hwxxpack') : 0;
			$result['value'] = pack('C',REMOTE_CMD_TWO_PACKAGE).$info[$i];
			if( $i < count($info)-1)
			{
				$i++;
				Cache::set('hwxxpack',$i);				
				include_once('plannedTask/PlannedTask.php');
				$planTask = new PlannedTask('devattr','attr', date('Y-m-d H:i:s',time()+1));
				$planTask->execAttr($attrid,$value);
			}
			else
			{
				Cache::del('hwxxpack');
			}
		}
		else
		{
			$result['value'] = pack('C',REMOTE_CMD_CTRL2).$info[0];			
		}
		$cfginfo = unserialize($ainfo['CFGINFO']);
		$result['index'] = $c->queryValue('ATTRINDEX','ID=?',array($cfginfo['rid']));
		
		return $result;
	}
	
	static function getDetail($value,$attrid=NULL)
	{
		$c = new TableSQL('homeattr','ID');
		$butinfo = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$butinfo = unserialize($butinfo);
		$result = array();
		foreach( $butinfo as &$but )
		{
			$result[ $but['id'] ] = $but['name'];  
		}
		return $result;
	}
	
	//////////////////////语音控制相关////////////////////////////////////
	static function getYuyinDict($attrid)
	{
		$c = new TableSQL('homeattr','ID');
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$cfg = unserialize($cfg);

		foreach($cfg as &$mode)
		{
			if( NULL == $mode )
			{
				continue;
			}
			$ret[] = array('word'=>$mode['name'],'attr'=>'dz');
		}

		return $ret;
	}		
	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$c = new TableSQL('homeattr','ID');
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid));
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
			foreach( $cfg as &$but )
			{
				if( $word['word'] != $but['name'] )
				{
					continue;
				}
				$ret = $but['id'];
				break;
			}
			if( -1 != $ret )
			{
				$GLOBALS['dstpSoap']->setModule('devattr','attr');
				$GLOBALS['dstpSoap']->execAttr($attrid,$ret);
				$info = $word['word'];
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