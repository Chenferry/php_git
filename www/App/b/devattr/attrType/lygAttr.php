<?php
//24G专做的一个属性
class lygAttrType
{
	static $cfg  = array('r'=>0,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT,'rep'=>1);
	static $page = 'lyg'; 
	static $name = 'lyg'; 

	static $packfmt   = 'c';
	static $unpackfmt = 'c';

	static function getCMDInfo($value,$attrid=NULL)
	{
		switch($value)
		{
			case 'open':
				return 1;
			case 'close':
				return 2;
			case 'left':
				return 3;
			case 'right':
				return 4;
			case 'reset':
				return 5;
			case 'pause':
			default:
				return 6;
		}
	}
}

 

?>