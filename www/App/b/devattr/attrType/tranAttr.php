<?php
//透传通道
class tranAttrType
{
	static $cfg  = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>NULL);
	static $page = 'char'; 
	
	//ver:附加信息版本
	//phydev:透传通道类型。主要是替换属性时用
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$ret = unpack('Cver/Cphydev',$value);
		return $ret;
	}
}
?>