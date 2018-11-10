<?php
//短信
class smsAttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>NULL,'cf'=>TABLE_FIELD_ENUM_CHAR);
	static $page = 'sms';

	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		if( count($value) != 2 )  return false;
		$c    = new TableSql('homedev','ID');
		$c->join('homeattr','homeattr.DEVID=homedev.ID');
		$phydev  = $c->queryValue('PHYDEV','homeattr.ID=?',array($attrid));
		if( !$phydev )
		{
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('delay','phone');
			$planTask->sendSmsToMultiPhone($value['nums'],$value['messages']);
			return false;
		}
		else
		{
			$nums = serialize($value['nums']);
			$phoneLen = strlen($nums);
			$msgLen = strlen($value['messages']);
			$mode = 'C1n1n1a'.$phoneLen.'a'.$msgLen;
			return pack($mode,7,$phoneLen,$msgLen,$nums,$value['messages']);
		}

	}
}
?>