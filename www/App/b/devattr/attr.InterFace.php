<?php
include_once(dirname(__FILE__).'/class.attr.inc.php');

//该文件进行系统属性管理接口
class attrInterFace
{
	static $attrType;
	//根据ID，设置相应的attrType类别信息
	static function setAttrClass($id)
	{
		$c = new TableSql('homeattr','ID');
		self::$attrType = $c->queryValue('SYSNAME','ID=?',array($id));
		if ( NULL == self::$attrType )
		{
			return false;
		}
		return attrType::setAttrType(self::$attrType);
	}

	//向设备属性下发执行命令
	static function execAttr($id,$cmd,$get=false)
	{
		$c = new TableSql('homeattr','ID');
		$attr = $c->query('DEVID,SYSNAME,ATTRINDEX','ID=?',array($id));
		if ( !$attr )
		{
			return false;
		}
		
		//设备组的设备ID为特殊值
		if( -2 == $attr['DEVID'] )
		{
			$GLOBALS['dstpSoap']->setModule('smart','devgroup');
			$GLOBALS['dstpSoap']->execDevGroup($attr['ATTRINDEX'],$cmd);
			return true;
		}
		
		attrType::setAttrType($attr['SYSNAME']);
		$cmd = attrType::getDBInfo($cmd,$id);
		if( false === $cmd )
		{
			return true;
		}
		
		if( $get )
		{
			$info = array();
			$info['DEVID'] = $attr['DEVID'];
			$info['ATTR']  = array('ID'=>$id, 'ATTR'=>$cmd);
			return $info;
		}
		
		$attrList = array();
		$attrList[] = array('ID'=>$id, 'ATTR'=>$cmd);

		$GLOBALS['dstpSoap']->setModule('home','end');
		return $GLOBALS['dstpSoap']->sendMsg($attr['DEVID'], $attrList);
	}
	
	//把页面传来的设备控制字转换为数据库保存格式
	static function getDBInfo($id,$info)
	{
		if ( !self::setAttrClass($id) )
		{
			return NULL;
		}
		return attrType::getDBInfo($info,$id);
	}
	
	//把数据库读出的控制字转为页面所需的信息
	static function getViewInfo($id,$info)
	{
		if ( !self::setAttrClass($id) )
		{
			return NULL;
		}
		return attrType::getViewInfo($info,$id);
	}

	//属性的语音解析
	static function yuyin($id,$info)
	{
		if ( !self::setAttrClass($id) )
		{
			return false;
		}

		$GLOBALS['dstpSoap']->setModule('yuyin','dict');
		$dict = $GLOBALS['dstpSoap']->getAttrDict(self::$attrType);
		if ( method_exists(attrType::$attrClass, 'getYuyinDict') )
		{
			$class = attrType::$attrClass;
			$attrDict = $class::getYuyinDict($id);
			include_once('fenci/dict.class.php');
			dictClass::buildDict($attrDict,$dict);
		}
		$fenci = fenciClass::fenci($info,$dict);
		if(!$fenci) return;
		//
		return attrType::yuyin($fenci,$id);
	}


}
?>