<?php
//需要libevent扩展
///////////////////////////////////////////
if( 'cli'!=PHP_SAPI )
{
    exit(-1);
}

//避免重入

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
	//与proxystub的心跳保持连接
	static $client;
	
	//手机直接连接的链接
    static $phoneList = array();

    //代理转发过来的指示当前位置信息.flag表示当前页面
    static function hicNotice($pos)
    {
        $default = 1;
        setDevSleep($default);
    }	
	
	
    static function init() //init
    {
        //每秒检查下是否有状态变化，如果有，则通知
        server::startTimer(array('status', 'checkstatus'),1000000*1);
    }
	
    //websocket连接握手
    static function onRead($id,&$info) //read
    {
		if( $id == self::$client )//与proxy的心跳
		{
			$wi = "live\n";
			server::writeconn( self::$client, $wi);
			return;
		}
		
        //如果还没握手，则先握手
        if( !isset(self::$phoneList[$id]) )
        {
            $r = websocket::handshaking($info);
            if(false == $r)
            {
				//检查是不是hicstub连接上的
				$pos = strpos($info,"stubconnect");
				if( false !== $pos )
				{
					if( NULL != self::$client )
					{
						//先关闭以前的再重新赋值
						server::closeEventConn(self::$client);
					}
					self::$client = $id;
					$wi = "live\n";
					server::writeconn( self::$client, $wi);
					return;
				}
				
				if( false === $r )
				{
					//关闭连接
					server::closeEventConn($id);
				}

            }
            
            if(!$r)//还没读取完整
            {
                return;
            }
            
            //发送握手消息
            $info = NULL;
            server::writeconn($id,$r);
            self::$phoneList[$id] = 1;
            return;
        }
        while( NULL != ($decode=trim(websocket::decode($info))) )
        {
            list($flag,$v) = explode(':',$decode);
            //如果是cookie，需要判断是否当前设备
            self::hicNotice($v);
            self::$phoneList[$id] = 1;
        }
    }

    static function onClose($id)
    {
        unset(self::$phoneList[$id]);
    }
    
    /////////////////////////////////////////////////////
    //检查状态是否有变化，如果有变化，则通知
    static function checkstatus()
    {

		Cache::set('hicstatuslive','live',20);
		
        foreach(self::$phoneList as $phone=>&$num)
        {
            //判断是否超时
            if( $num++ > 60 )
            {
                server::closeEventConn($phone);
            }
        }

        $status  = Cache::get('statuschange');
        if ( !$status )
        {
			return;
        }

        Cache::del('statuschange');
		$GLOBALS['dstpSoap']->setModule('devattr','devattr');
		$GLOBALS['dstpSoap']->checkUpdatePageList();
		
        return self::noticePhone($status);
    }
    //给连接到hic的所有手机发通知，给hic发通知
    static function noticePhone($status)
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
		
		//向proxystub发送通知，由其转发给未直接相连的手机
		if(self::$client)
		{
			$wi = "$status\n";
			server::writeconn( self::$client, $wi);
		}
        
        $ni = websocket::encode($status);
        //直接通知连接到HIC的手机
        foreach(self::$phoneList as $phone=>&$num)
        {
            $wi = $ni;
            server::writeconn( $phone, $wi);
        }
    }
}

server::regIf('status',  getRealPort(HIC_SERVER_STATUS),   false);
server::start();  

?> 