<?php
//wifi设备连接情况
//mini页面显示在线情况。点击进去，应该包括：踢下线；当前网络流量
class clientAttrType
{
	static $cfg  = array('r'=>1,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'client'; 
	static $del = array('m'=>'home','s'=>'sysend','f'=>'delClientDev'); 

	
	//辅助前台显示的信息
	//比如pm2.5，得到的是数值，但需要根据数值转换为一个表示等级的枚举值
	//比如可能是一个多维数组，指示空调可选模式/温度列表等
	static function getOtherInfo($value,$id)
	{
		$a = array(
			0   => ATTRCFG_CLIENT_LIXIAN,
			1   => ATTRCFG_CLIENT_ZAIXIAN,
		);
		return $a;
	}
}

 

?>