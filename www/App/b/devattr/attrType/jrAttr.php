<?php
//把所有家人作为一个整体
class jrAttrType
{
	static $cfg  = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'switch'; 
	
	static function getOtherInfo($value,$id)
	{
		$a = array(
			0   => ATTRCFG_JR_MEIREN,
			1   => ATTRCFG_JR_YOUREN,
		);
		return $a;
	}
}

 

?>