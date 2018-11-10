<?php
include_once('../../a/config/dstpCommonInfo.php');

function getItemAddrInB($hicid)
{
	$localid = HICInfo::getHICID();
	//如果hicid同本地，则取本地端口，否则往服务器获取端口
	if( $localid != $hicid )
	{
		$GLOBALS['dstpSoap']->setHost(r_jia_sx);
		$GLOBALS ['dstpSoap']->setModule ( 'app','init' );
		return $GLOBALS ['dstpSoap']->getItemAddr($hicid);	
	}

	$portList = array(HIC_SERVER_RWIFI);
	if ( defined('HIC_SYS_POWER') ) 
	{
		for( $i = 0; $i < HIC_SYS_POWER; $i++ )
		{
			$portList[] = HIC_SERVER_EXTRSTART+$i;
		}
	}
	$port = array_rand($portList);
	$port = $portList[$port];
	
	//b中这个端口是固定的
	return array('METHOD'   =>'item_addr',
				 'SERVER'   => 'jia.mn', 
				 'DEVRPORT' => intval($port), 
				 'HICID'    => intval($hicid), 
				 'ISSSL'    => 0,
				 'LOCAL'    => 1,
				);
	
}


function getItemAddr()
{
	if( 'b' == HIC_LOCAL )
	{
		return getItemAddrInB( $_REQUEST['hicid'] );
	}
	else
	{
		$GLOBALS ['dstpSoap']->setModule ( 'app','init' );
		return $GLOBALS ['dstpSoap']->getItemAddr($_REQUEST['hicid']);	
	}
}

$r = getItemAddr();

echo json_encode($r);

?>
