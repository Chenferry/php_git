<?php

class inHIC
{
	//返回连接websock的端口。如果在公网，则直接返回288
	static function  getStatusHost($hicid=NULL)
	{
		if ( DSTP_DEBUG )
		{
			return b_jia_sx.':'.HIC_SERVER_STATUS;
		}
		$hicid = HICInfo::getHICID($hicid);
		if ( !validID($hicid) )
		{
			return b_jia_sx.':'.HIC_SERVER_STATUS;
		}
		$c = new TableSql('hic_hicstatus');
		$info = $c->query('*','HICID=? AND STIME>?',array($hicid, time()-b_offline_time));
		if( NULL == $info )
		{
			return 'offline';
		}
		//首先判断是否在防火墙后面。如果不在防火墙后，直接返回其真实IP
		//if ( $info['WANIP']==$info['REALIP'])
		//{
		//	return $info['WANIP'].':'.HIC_SERVER_STATUS;
		//}
		
		//如果REWALIP是一个整数，表示这是一个单品HIC，获取其配置的STATUS端口
		if( is_numeric($info['REALIP']) )
		{
			$c = new TableSql('hic_hicitem','ID');
			$proxy = $c->query('SERVER,STATUSPORT','ID=?',array($info['REALIP']));
			if ( NULL != $proxy )
			{
				return $proxy['SERVER'].':'.$proxy['STATUSPORT'];
			}
		}
		else
		{
			//对于防火墙后的信息中心，获取其代理服务器
			$GLOBALS['dstpSoap']->setModule('app','hic');
			$proxy = $GLOBALS['dstpSoap']->getProxyServer($hicid);
			if ( NULL != $proxy )
			{
				return $proxy['PROXY'].':'.$proxy['STATUSPORT'];
			}
		}
		return b_jia_sx.':'.HIC_SERVER_STATUS;
	}
	static function  getBHost($hicid=NULL)
	{
		if ( DSTP_DEBUG )
		{
			return b_jia_sx;
		}
		$hicid = HICInfo::getHICID($hicid);
		if ( !validID($hicid) )
		{
			return b_jia_sx.':'.HIC_SERVER_WEB;
		}
		
		//如果是hicid是item，则直接取item中所配置信息
		
		$c = new TableSql('hic_hicstatus');
		$info = $c->query('*','HICID=? AND STIME>?',array($hicid, time()-b_offline_time));
		if( NULL == $info )
		{
			return 'offline';
		}
		//首先判断是否在防火墙后面。如果不在防火墙后，直接返回其真实IP
		//if ( (NULL != $info) && ($info['WANIP']==$info['REALIP']))
		//{
		//	return $info['WANIP'].':'.HIC_SERVER_WEB;
		//}
		//if( is_numeric($info['REALIP']) )
		//{
		//	return c_jia_sx;
		//}


		//如果REWALIP是一个整数，表示这是一个单品HIC，获取其配置的STATUS端口
		if( is_numeric($info['REALIP']) )
		{
			$c = new TableSql('hic_hicitem','ID');
			$proxy = $c->query('WEBSERVER','ID=?',array($info['REALIP']));
			if ( NULL != $proxy )
			{
				return $proxy['WEBSERVER'];
			}
		}
		else
		{
			//对于防火墙后的信息中心，获取其代理服务器
			$GLOBALS['dstpSoap']->setModule('app','hic');
			$proxy = $GLOBALS['dstpSoap']->getProxyServer($hicid);
			if ( NULL != $proxy )
			{
				return $proxy['PROXY'].':'.$proxy['WEBPORT'];
			}
		}
		return b_jia_sx.':'.HIC_SERVER_WEB;
	}
	static function getPeerHost($hicid=NULL)
	{
		if ( 'i' == HIC_LOCAL )
		{
			return HICInfo::getCHost($hicid);
		}
		return HICInfo::getBHost($hicid);
	}
	static function getPHYID($hicid=NULL)
	{
		$hicid = HICInfo::getHICID($hicid);
		$c  = new TableSql('hic_hic');
		return $c->queryValue('PHYID','ID=?',array($hicid));
	}
	
	static function getHICID($hicid=NULL)
	{
		$user = NULL;
		if(isset($_SESSION['loginUserID']))
		{
			$user = $_SESSION['loginUserID'];
		}
		if(isset($GLOBALS['curUserID']))
		{
			$user = $GLOBALS['curUserID'];
		}
		if(NULL==$user)
		{
			return NULL;
		}
		
		//返回用户绑定的第一个信息中心。应该优先获取有phyid的
		$c  = new TableSql('hic_hicbind');

		if( isset($_GET['newui']) )
		{
			$idList = $c->queryAllList('HICID','USERID=?',array($user));
		}
		else
		{
			//原来的暂时先屏蔽单品信息
			$isitem = 0;
			$idList = $c->queryAllList('HICID','USERID=? AND ISITEM=?',array($user,$isitem));
		}
		if ( NULL == $idList )
		{
			return NULL;
		}

		if( isset($_GET['HICID']) && in_array( $_GET['HICID'], $idList))
		{
			return $_GET['HICID'];
		}
		
		//默认访问上一次访问的
		$hicToken = getHICToken();
		if ( NULL != $hicToken )
		{
			list($userid,$time,$hicid,$rand,$flag) = explode('-', $hicToken);
			if( in_array( $hicid, $idList) )
			{
				return $hicid;
			}
		}
		
		//访问当前在线使用
		$idList = implode(',', $idList);
		$c  = new TableSql('hic_hic');
		return $c->queryValue('ID',"ID IN ($idList) AND PHYID IS NOT NULL");
	}
	static function getSecure($hicid)
	{
		$c  = new TableSql('hic_hicinfo');
		if ( 'i' == HIC_LOCAL )
		{
			return $c->queryValue('CHID','HICID=?',array($hicid));
		}
		else
		{
			return $c->queryValue('HCID','HICID=?',array($hicid));
		}
	}
}
?>