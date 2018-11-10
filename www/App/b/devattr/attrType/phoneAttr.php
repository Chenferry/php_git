<?php
//拨号
class phoneAttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>NULL,'cf'=>TABLE_FIELD_ENUM_CHAR);
	static $page = 'phone';
	
	static function getCMDInfo($value,$attrid=NULL)
	{
		if( strlen($value) != 11 ) return false;
		$c    = new TableSql('homedev','ID');
		$c->join('homeattr','homeattr.DEVID=homedev.ID');
		$phydev  = $c->queryValue('PHYDEV','homeattr.ID=?',array($attrid));
		if( !$phydev )
		{
			include_once('plannedTask/PlannedTask.php');
			$planTask = new PlannedTask('delay','phone');		
			$planTask->dial($value);
			return false;			
		}
		else
		{
			return pack('C1a11',6,$value);
		}
	}

}
?>