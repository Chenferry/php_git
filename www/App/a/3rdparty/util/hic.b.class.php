<?php
//
class inHIC
{
	//返回连接websock的端口。如果在公网，则直接返回288
	static function  getStatusHost()
	{
		return b_jia_sx.':'.HIC_SERVER_STATUS;
	}
	static function  getBHost()
	{
		//如果从外网调用hic的链接。在该链接中直接返回b_jia_sx则是不可访问的
		//这个处理还有个问题：如果从内网走到外网，如果没及时刷新bhost信息，会导致不可访问
		if ( isset($_SERVER['REQUEST_URI']) )
		{
			return $_SERVER['HTTP_HOST'];
		}
		return b_jia_sx.':'.HIC_SERVER_WEB;
	}
	//如果是在b，不允许调用其它路由器，所以这个函数和服务器上不同，不接受phyid参数
	static function getPeerHost()
	{
		return HICInfo::getCHost();
	}
	static function getPHYID($hicid=NULL)
	{
		include('uci/uci.class.php');
		return network::getMac();
	}
	static function getHICID()
	{
		$c  = new TableSql('hic_hic');
		return $c->queryValue('ID');
	}
	static function getSecure($hicid)
	{
		$c  = new TableSql('hic_hicinfo');
		return $c->queryValue('CHID');
	}
}



?>