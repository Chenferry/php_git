<?php
//485配置页面
class cfg485AttrType
{
	static $cfg  = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'cfg485'; 
	static $name = DEV_SYSNAME_CFG485;
	
	static function getViewInfo($value,$id)
	{
		$c = new TableSQL('homeattr','ID');
		$info = $c->queryValue('ATTRSET','ID=?',array($id));
		$info = unserialize($info);
		return $info;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		$value = unpack('Cid/Cset',$value);
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		if( !isset($cfg['id']) || $cfg['id'] != intval($value['id']) || !isset($cfg['set']) || $cfg['set'] != intval($value['set']) )
		{
			$info['ID'] = $attrid;
			$cfg['id'] = intval($value['id']);
			$cfg['set'] = intval($value['set']);
			$info['ATTRSET'] = serialize($cfg);
			$c->update($info);	
			noticeAttrModi($attrid);			
		}
	}

	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		$type = $value['type']=='id' ? 0 : 1;
		return pack('CC',$type,intval($value['value']));
	}

}
?>