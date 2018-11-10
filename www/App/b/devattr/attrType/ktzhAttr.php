<?php
//中弘中央空调对接
class ktzhAttrType
{
	static $cfg = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR);
	static $page = 'xjd'; 
	static $name = DEV_SYSNAME_KT;
	
	//获取得到所有内机地址
	private static function getKTInfo($zhktid,$ktList)
	{
		//物理添加设备。这些设备不能手动删除
		$dev = array();
		$dev['STATUS']  = DEV_STATUS_RUN;
		$dev['PHYDEV']  = PHYDEV_TYPE_SYS;
		$dev['VER']     = '123';
		$dev['PHYADDR'] = serialize(array('m'=>'home','s'=>'sysend','f'=>'zhktReceiveCMD'));
		$dev['ATIME']   = time();
		$dev['ETIME']   = INFINITE_TIME;
		//内机设置房间默认和外机一致。不过这时外机很可能还没开始设置位置
		
		$attr = array();
		$attr['ATTRINDEX'] = 0;
		$attr['NAME']      = DEV_SYSNAME_KT;
		$attr['SYSNAME']   = 'xjd';
		$attr['ICON']      = 'kt';
		$attr['INUSE']     = 1;
		$attr['CANDEL']    = 0;
		$attr['VF']  = TABLE_FIELD_INT;
		$attr['CF']  = TABLE_FIELD_INT;
		$attr['ISR'] = 1;
		$attr['ISC'] = 1;
		
		$index  = 1;
		$map    = array();
		$devList= array();
		$cdev   = new TableSql('homedev', 'ID');
		$cattr  = new TableSql('homeattr','ID');
		$addInfo['info'] = pack('CC',0,4).'zykt';
		$status          = pack('CnnCC',0,26,26,1,1);
		foreach( $ktList as $kt )
		{
			$index++;
			//直接添加物理设备
			$dev['NAME'] = DEV_SYSNAME_KT.$kt;
			$devid = $cdev->add($dev);
			if( !validID($devid) )
			{
				continue;
			}
			$devList[] = $devid;

			//添加小家电属性
			$attr['NAME']  = DEV_SYSNAME_KT.$kt;
			$attr['DEVID'] = $devid;
			$attr['ID'] = $cattr->add($attr);
			if( !validID($attr['ID']) )
			{
				continue;
			}			
			//生成小家电附加属性
			$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
			$GLOBALS['dstpSoap']->setAttrType('xjd');
			$v = xjdAttrType::parseAdditonInfo($addInfo,$attr['ID']);

			if ( false !== $v )
			{
				$v['zhktid']   = $zhktid;
				$v['njdz']     = $kt;
				$up = array();
				$up['ID']      = $attr['ID'];
				$up['CFGINFO'] = serialize($v);
				$cattr->update($up);
			}
			xjdAttrType::getStatusInfo($status,$attr['ID']);
			
			
			//内机地址和属性ID地址建立关联
			$map[$attr['ID']] = $kt;
		}
		
		//把自己也当温控器处理
		$addInfo['info'] = pack('CC',0,4).'zykt';
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$GLOBALS['dstpSoap']->setAttrType('xjd');
		$cfg = xjdAttrType::parseAdditonInfo($addInfo,$zhktid);
		$cfg['map'] = $map;
		$cfg['dev'] = $devList;

		$info = array();
		$info['ID']      = $zhktid;
		$info['CFGINFO'] = serialize( $cfg );
		$c = new TableSql('homeattr','ID');
		$c->update($info);

		xjdAttrType::getStatusInfo($status,$zhktid);
		return;
	}

	static function addAttrNotice($attrid)
	{
		//如果已经添加进来，就不要再重复添加
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?', array($attrid) );
		$cfg = unserialize($cfg);
		if( false != $cfg )
		{
			return;
		}
		
		
		//向空调控制器发送请求，获取所有内机地址		
		$cmd = array();
		$cmd['open'] = 0xFF;
		

		include_once('plannedTask/PlannedTask.php');
		$planTask 	  = new PlannedTask('devattr','attr',2);
		$planTask->execAttr($attrid,$cmd);
		return;
	}

	static function delAttrNotice($attrid,$devid,$attrindex)
	{
		//删除设备同时删除内机地址构造出的设备
		$c   = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( false == $cfg )
		{
			return;
		}
		$GLOBALS['dstpSoap']->setModule('home','end');
		foreach( $cfg['dev'] as $dev )
		{
			$GLOBALS['dstpSoap']->del($dev,true);
		}
	}
	
	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$info = unpack('Ccmd/Caddr/Cinfo',$value);
		
		switch($info['cmd'])
		{
			case 1://01关机、00开机
				break;
			case 2://当前温度，0-50
				break;
			case 3://03送风、01制冷、04制热   02除湿 00自动  
				break;
			case 4://01低速、02中速、03高速、00自动
				break;
			case 5://01风向1 02风向2 03风向3 04风向4  00自动
				break;
			case 6://N个（内机地址+开关机+当前温度+设定温度+当前模式+当前风速（各1字节））	一条消息最多25个空调状态，超过25个分开上报
				$cattr = new TableSql('homeattr','ID');
				$cfg   = $cattr->queryValue('CFGINFO','ID=?',array($attrid));
				$cfg   = unserialize($cfg);
				$map   = &$cfg['map'];
				$value = substr($value,2);
				$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
				$GLOBALS['dstpSoap']->setAttrType('xjd');
				for( $i=0; $i<$info['addr'];$i++ ) //第二个字节是数量
				{
					$addr   = unpack('C',$value);
					$addr   = $addr[1];
					$status = substr($value,1,7); //开关机+当前温度+设定温度+当前模式+当前风速
					$value  = substr($value,8);//继续下一个
					//根据地址获取属性ID
					$aid = $attrid;
					if( 0xFF != $addr ) //获取内机对应属性ID
					{
						foreach( $map as $ktid=>$ktaddr )
						{
							if( $ktaddr == $addr )
							{
								$aid = $ktid;
								break;
							}
						}
					}
					if( !validID($aid) )
					{
						continue;
					}
					xjdAttrType::getStatusInfo($status,$aid);
				}
				break;
			case 7://N个内机地址（1字节）
				$value = substr($value,2);
				$ktList = unpack('C*',$value);
				self::getKTInfo($attrid,$ktList);
				//清空所有设备属性缓存
				Cache::del('getAttrValue');
				return false;
				break;
		}
		//在本函数中直接调用小家电处理了。无需再返回后续处理
		return false;
	}

	//把数据库信息通过pack转化为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		if( !is_array( $value ) ) $value = unserialize($value);
		
		if( !array_key_exists('open',$value) )
		{
			$value['open'] = 1;
		}

		switch( $value['open'] )
		{
			case 0xFF: //查询内机地址
				return pack('CC',0x07,0xFF);
				break;
			case 0xFC: //中转对内机的控制
				return pack('CC',0x06,$value['njdz']).$value['cmd'];
				break;
			case 0x0:  //APP对外机的控制
				return pack('CCC',0x06,0xFF,0x00);
				break;
			case 0x1: 
				$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
				$GLOBALS['dstpSoap']->setAttrType('xjd');
				$cmd = xjdAttrType::getCMDInfo($value,$attrid);
				return pack('CC',0x06,0xFF).$cmd;
				break;
			default:   //非法
				break;	
		}
		return false;
	}

	static function getDetail($value,$attrid=NULL)
	{
		$GLOBALS['dstpSoap']->setModule('devattr','attrtype');
		$GLOBALS['dstpSoap']->setAttrType('xjd');
		return xjdAttrType::getDetail($value,$attrid);
	}
	
	//语音直接调用xjd的处理

}

 

?>