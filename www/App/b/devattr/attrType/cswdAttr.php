<?php
//城市温度
class cswdAttrType
{
	static $cfg = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_FLOAT,'cf'=>NULL);
	static $page = 'num'; 

	//返回单位值
	static function getOtherInfo($value,$id)
	{
		return array('unit'=>'℃','min'=>-10,'max'=>50);
	}


}

 

?>