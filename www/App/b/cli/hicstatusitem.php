<?php
//需要libevent扩展
///////////////////////////////////////////
if( 'cli'!=PHP_SAPI )
{
    exit(-1);
}


$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
require_once($dstpCommonInfo);
require_once( dirname(__FILE__).'/proxyserver.php' );

if( APP_ZJ != HIC_APP )
{
	exit(-1);
}

///////////////////////////////////////////////////


class status
{
	//以id为下标的连接
	static $phoneList = array();
	static $hicPhone  = array();

	static function init() //init
	{
		//定时清除无用连接
		server::startTimer(array('status', 'checkStatusConn'),1000000*20);
        //每秒检查下是否有状态变化，如果有，则通知
        server::startTimer(array('status', 'checkstatus'),1000000*1);
	}
	
	//websocket连接握手
	static function onRead($id,&$info) //read
	{
		//如果还没握手，则先握手
		if( !isset(self::$phoneList[$id]) )
		{
			$r = websocket::handshaking($info);
			if(false === $r)
			{
				//关闭连接
				server::closeEventConn($id);
			}
			
			if(!$r)//还没读取完整
			{
				return;
			}
			
			//发送握手消息
			$info = NULL;
			server::writeconn($id,$r);
			self::$phoneList[$id] = array();
			self::$phoneList[$id]['num'] = 0;
			return;
		}
		
		while( NULL != ($decode=trim(websocket::decode($info))) )
		{
			list($flag,$v) = explode(':',$decode);
			switch( $flag )
			{
				case 'cookie':
					if( NULL == $v )
					{
						return;
					}
					//如果对应的hicid不是在这个服务器上，直接关闭连接
					//$r = hic::addPhoneLink($v,$id);
					//if ( false === $r )
					//{
					//	//找不到对应的hic连接。关闭当前连接
					//	server::closeEventConn($id);
					//	return;
					//}
					self::$phoneList[$id]['hic'] = $v;
					if( !isset( self::$hicPhone[$v] ) )
					{
						self::$hicPhone[$v] = array();
					}
					self::$hicPhone[$v][] = $id;
					break;				
				case 'status':
				default:
					$hicid = self::$phoneList[$id]['hic'];
					if ( false == $hicid )
					{
						//找不到对应的hic连接。关闭当前连接
						server::closeEventConn($id);
						return;
					}

					setSysUid($hicid);
					self::hicNotice($v);
					break;
			}
			self::$phoneList[$id]['num'] = 0;
		}
	}
	
    static function hicNotice($pos)
    {
        $default = 1;
        setDevSleep($default);
    }	
		
	static function onClose($id)
	{
		//删除hicPhone中对应的信息
		$hicid = self::$phoneList[$id]['hic'];
		$key = array_search($id,self::$hicPhone[$hicid]);  
		if(isset($key))
		{  
			unset(self::$hicPhone[$hicid][$key]);  
		}
		if( NULL == self::$hicPhone[$hicid] )
		{
			unset(self::$hicPhone[$hicid]);
		}
		
		//删除连接信息
		unset(self::$phoneList[$id]);
	}

	static function checkStatusConn()
	{
		foreach(self::$phoneList as $id=>&$info)
		{
			//判断是否超时
			if( $info['num']++ > 3 )
			{
				server::closeEventConn($id);
			}
		}
	}	
 
    /////////////////////////////////////////////////////
    //检查状态是否有变化，如果有变化，则通知
    static function checkstatus()
    {
		foreach( self::$hicPhone as $hicid=>&$phoneList )
		{
			setSysUid($hicid);

			$status  = Cache::get('statuschange');
			if ( !$status )
			{
				continue;
			}

			Cache::del('statuschange');
			$GLOBALS['dstpSoap']->setModule('devattr','devattr');
			$GLOBALS['dstpSoap']->checkUpdatePageList();

			self::noticePhone($status,$phoneList);
		}
    }

    //给连接到hic的所有手机发通知，给hic发通知
    static function noticePhone($status,&$phoneList)
    {
        $status = array_unique($status);
		
		//如果包含dict，则重构语音识别词典
		if( in_array('dict',$status) )
		{
			$GLOBALS['dstpSoap']->setModule('yuyin','dict');
			$GLOBALS['dstpSoap']->buildDict(true);
		}
		if( in_array('roomAttrMap',$status) )
		{
			//清掉重新获取
			Cache::del('roomAttrMap');
		}		
		
        $status = implode('-',$status);
        
        $ni = websocket::encode($status);
        //直接通知连接到HIC的手机
        foreach( $phoneList as $phone)
        {
            $wi = $ni;
            server::writeconn( $phone, $wi);
        }
    }
}

server::regIf('status',  getRealPort(HIC_SERVER_STATUS),   false);
server::start();  

?> 