<?php
//wifi设备连接情况
//mini页面显示在线情况。点击进去，应该包括：踢下线；当前网络流量
class mobileAttrType
{
	static $cfg  = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'mobile'; 

	//把数据库信息转为前台显示信息
	//这个数据库中没存有什么信息，需要自己直接从client表中直接读取数据
	static function getDetail($value,$attrid=NULL)
	{
		$ip = NULL;
		include_once('uci/uci.class.php');	

		$r = array('pc'=>array(),'kr'=>array(),'hmd'=>array(),'init'=>array());

		$c	= new TableSql('homeclient','ID');
		$info = $c->queryAll('ID,PERIOD,NAME,MAC','SOURCES=?',array(DEV_CONNECTHIC_SSID));
		foreach($info as &$i)
		{
			switch($i['PERIOD'])
			{
				case DEV_CLIENT_PC:
					$r['pc'][$i['ID']] = $i['NAME'];
					break;
				case DEV_CLIENT_TEMP:
					$r['kr'][$i['ID']] = $i['NAME'];
					break;
				case DEV_CLIENT_REJECT:
					$r['hmd'][$i['ID']] = $i['NAME'];
					break;
				case DEV_CLIENT_INIT:
				case DEV_CLIENT_REQUEST:
					if( false !== strpos($i['NAME'],'?')   )
					{
						uci_base::getInfoByMAc($i['MAC'],$ip,$i['NAME']);
						if ( NULL != $i['NAME'] )
						{
							$c->update($i);
						}
						else
						{
							$i['NAME'] = '已离线';
						}
					}
					$r['init'][$i['ID']] = $i['NAME'];
					break;
				default:
					break;
			}
		}
		return $r;
	}	
}

 

?>