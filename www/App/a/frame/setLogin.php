<?php
	include_once('../../a/config/dstpCommonInfo.php');  
	
	function getdturl()
	{
		//这儿要根据当前cookie信息来初始化环境
		if( 'b' != HIC_LOCAL  )
		{
			include_once('../../a/config/dstpUserCheck.php');
		}
		$fromdev = 'mainapp';
		switch( HIC_LOCAL )
		{
			case 'b':
				$fromdev = 'mainapp';
				break;
			case 'i':
				$fromdev = 'single';
				break;
			case 'c':
				//判断当前的hicid是单品还是完全系统
				$hicid = HICInfo::getHICID();
				$c = new TableSql('hic_hicstatus');
				$rip = $c->queryValue('REALIP','HICID=?',array($hicid));
				if( is_numeric($rip) )
				{
					$fromdev = 'single';
				}
				else
				{
					$fromdev = 'mainapp';
				}
				break;
		}
		$r = array(
				'a'=>HICInfo::getAUrl(), 
				'b'=>HICInfo::getBUrl(), 
				'c'=>HICInfo::getCUrl(),
				's'=>HICInfo::getStatusHost(),
				'fromdev'=>$fromdev,
				'on' => true
		);
		if( 'offline' == $r['s'] )
		{
			$r['on'] = false;
		}
		return $r;
	}
	util::startSajax( array('getdturl') );

?>
