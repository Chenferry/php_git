<?php
//wifi设备连接情况
//mini页面显示在线情况。点击进去，应该包括：踢下线；当前网络流量
class fjAttrType
{
	static $cfg  = array('r'=>0,'c'=>0,'s'=>0,'vf'=>TABLE_FIELD_ENUM,'cf'=>TABLE_FIELD_ENUM);
	static $page = 'char'; 
	
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		return unpack('Cver/Cwifi',$value);
	}

	private static function getSendPort()
	{
		//请求连接的转发端口
		$portList = array(HIC_SERVER_RWIFI);
		if ( defined('HIC_SYS_POWER') ) 
		{
			for( $i = 0; $i < HIC_SYS_POWER; $i++ )
			{
				$portList[] = HIC_SERVER_EXTRSTART+$i;
			}
		}
		$port = array_rand($portList);
		return $portList[$port];
	}

    static function getStatusInfo($value,$attrid=NULL)
    {
        $cmd = unpack('Ccmd',$value);
        $cmd = $cmd['cmd'];
		$value = substr($value,1);

		$exec = array();
        switch($cmd)
        {
            case 6: //不带WIFI的分机
                return false;
                break;
            case 0: //分机正常的心跳状态上报
				$exec['action'] = 'wifi';
                $GLOBALS['dstpSoap']->setModule('devattr','attr');
                $GLOBALS['dstpSoap']->execAttr($attrid,$exec);       
                return false;
                break;
			case 1: //分机初始化当前在线WIFI信息。每次连上都报告一次
					//发给主机的消息长度最多只能200多个字节。这儿要考虑MAC太多导致超长
				//$value = unserialize($value);
				$value = array();
                $GLOBALS['dstpSoap']->setModule('home','client');
                $GLOBALS['dstpSoap']->initOnlineList($value,$attrid);
				
				//这儿不break返回。直接继续执行2的分支。
                //return false;
				//break;
            case 2:
				//每次连上，都重新获取一次该分机所需的连接端口
				$exec['action'] = 'fjport';
				$exec['port']   = self::getSendPort();
				$exec['token']  = md5('hicfjtoken');//先暂时写死

                $GLOBALS['dstpSoap']->setModule('devattr','attr');
                $GLOBALS['dstpSoap']->execAttr($attrid,$exec);       
                return false;
				break;
            case 5:
                $info = unpack('a3action/a17mac/a15ip/a30name/C1source',$value);
                $GLOBALS['dstpSoap']->setModule('home','client');
                if ('add' == $info['action']) 
				{
                    $GLOBALS['dstpSoap']->clientConnect($info['mac'],$info['ip'],$info['name'],$info['source'],$attrid);
                } 
				else 
				{
                    $GLOBALS['dstpSoap']->clientOffline($info['mac'],$attrid);
                }
                return false;
                break;
        }
        return false;
    }

    //如果控制字为空时，表示报告状态时的处理，需要把index改为0
    static function getCMDInfo($value,$id)
    {
        if( !is_array($value) )
        {
            $value = unserialize($value);
        }
		
        if( !is_array($value) )
		{
			return false;
		}
        
        switch($value['action'])
        {
			case 'wifi':
				//char ssid[32]   //HIC的SSID
				//char encryption[10] //加密方式
				//char key[32]      //密码        
				include_once('uci/uci.class.php');  
				$ssid = SSID::getSSID();
				return pack("C1Z32Z10Z33",0,trim($ssid['name']),trim($ssid['encryption']),trim($ssid['password']));
				break;
            //允许放行新接入的wifi设备
            case 'wifimac':
				return pack('C1a17C1C1',5,$value['mac'],$value['period'],$value['add']);
                break;
            //传回分机连接所需的端口和token
            case 'fjport':
				$len = strlen($value['token']);
				return pack("C1n1C1Z$len",2,$value['port'],$len,$value['token']);
                break;                  
            default:
				return false;
                break;
        }
        return false;
    }
	
}

 

?>