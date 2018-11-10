<?php
//消息推送
class pushAttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>NULL,'cf'=>TABLE_FIELD_CHAR);
	static $page = 'push';
	
	static function getCMDInfo($value,$attrid=NULL)
	{
		$GLOBALS['dstpSoap']->setModule('frame','alarm');
		$GLOBALS['dstpSoap']->sendNotice(NULL,$attrid,$value);		
	}

}
?>