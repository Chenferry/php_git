<?php

class inHIC
{
	private function getItemid($hicid)
	{
		$hicid = HICInfo::getHICID($hicid);
		if ( !validID($hicid) )
		{
			return INVALID_ID;
		}

		$itemid = $GLOBALS['SYSDB']['ITEMID'];
		if( !validID($itemid) )
		{
			$c = new TableSql('hic_hicstatus');
			$info = $c->query('REALIP','HICID=?',array($hicid));
			if( NULL == $info )
			{
				return 'offline';
			}
			$itemid = $info['REALIP'];
		}
		return $itemid; 
		
	}
	//返回连接websock的端口。如果在公网，则直接返回288
	static function  getStatusHost($hicid=NULL)
	{
		$itemid = self::getItemid($hicid);
		if( !validID($itemid) )
		{
			return b_jia_sx.':'.HIC_SERVER_STATUS;
		}

		
		$c = new TableSql('hic_hicitem','ID');
		$proxy = $c->query('SERVER,STATUSPORT','ID=?',array($itemid));
		if ( NULL != $proxy )
		{
			return $proxy['SERVER'].':'.$proxy['STATUSPORT'];
		}
		return b_jia_sx.':'.HIC_SERVER_STATUS;
	}
	static function  getBHost($hicid=NULL)
	{
		$itemid = self::getItemid($hicid);
		if( !validID($itemid) )
		{
			return b_jia_sx.':'.HIC_SERVER_WEB;
		}
		$c = new TableSql('hic_hicitem','ID');
		$proxy = $c->query('WEBSERVER,WEBPORT','ID=?',array($itemid));
		if ( NULL != $proxy )
		{
			return $proxy['WEBSERVER'];//默认都80了
			//return $proxy['WEBSERVER'].':'.$proxy['WEBPORT'];
		}
		return b_jia_sx.':'.HIC_SERVER_WEB;
	}
	static function getPeerHost($hicid=NULL)
	{
		return HICInfo::getCHost($hicid);
	}
	static function getPHYID($hicid=NULL)
	{
		$hicid = HICInfo::getHICID($hicid);
		$c  = new TableSql('hic_hic');
		return $c->queryValue('PHYID','ID=?',array($hicid));
	}
	
	static function getHICID($hicid=NULL)
	{
		if( NULL != $hicid )
		{
			return $hicid;
		}
		return getSysUid();
	}

	static function getSecure($hicid)
	{
		$c  = new TableSql('hic_hicinfo');
		return $c->queryValue('CHID','HICID=?',array($hicid));
	}
}
?>