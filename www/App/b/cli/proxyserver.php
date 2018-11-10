<?php

class server  
{  
	private static $base;
	private static $info=array();
	private static $fdid=1;
	
	private static $map=array();
	
	private static $event;
	private static $proto;
	
	//按照指定的界定符逐条读取数据
	static function getInfo(&$info,$delimiter)
	{
		$pos = strpos($info,$delimiter);
		if( false === $pos )
		{
			return NULL;
		}
		$len = strlen($delimiter);
		$ret = substr($info,0,$pos+$len);
		$info = substr($info,$pos+$len);

		return $ret;
	}
	
	function mapConn($id1,$id2)
	{
		if( !isset( self::$info[$id1] ) 
			|| !isset(self::$info[$id2]))
		{
			return false;	
		}
		self::$map[$id1] = $id2;
		self::$map[$id2] = $id1;
		self::trigerread($id1);
		self::trigerread($id2);
		return true;
	}
	
	function regIf($proto,$port,$flag=false)
	{
		self::$proto[$proto] = array();
		self::$proto[$proto]['port']   = $port;
		self::$proto[$proto]['isflag'] = $flag;
		self::$proto[$proto]['flag']   = array();
	}

	function startTimer($fun,$times,$arg=array(),$cyc=true)
	{
		$timerevent = event_timer_new();
		event_timer_set($timerevent, array(__CLASS__, 'procTimer'), 
				array('fd'=>$timerevent,'cyc'=>$cyc,'arg'=>$arg,'fun'=>$fun,'times'=>$times));
		event_base_set($timerevent, self::$base);  
		event_timer_add($timerevent,$times);
		return;
	}


	//写入指定id的连接
	function writeconn($id,&$info)
	{
		if(!isset(self::$info[$id]))
		{
			return false;
		}
		if( NULL == $info && NULL == self::$info[$id]['winfo'] )
		{
			return true;
		}
		$wInfo = NULL;
		if( NULL == self::$info[$id]['winfo'] )
		{
			$wInfo = &$info;
		}
		else
		{
			self::$info[$id]['winfo'] .= $info;
			$wInfo = &self::$info[$id]['winfo'];
		}
		$r = event_buffer_write( self::$info[$id]['buf'], $wInfo );
		if ($r)
		{
			self::$info[$id]['winfo']  = NULL;
		}
		else
		{
			if( NULL == self::$info[$id]['winfo'] )
			{
				self::$info[$id]['winfo'] = $info;
			}
			server::startTimer(array(__CLASS__, 'proxy_write_ok'),100000,array($id),false);
		}
		$info = NULL;
		return $r;
	}
	//触发指定id再读一遍
	function trigerread($id)
	{
		if(NULL == $id)
		{
			return;
		}
		if ( !isset(self::$info[$id]) )
		{
			return false;
		}
		return self::proxy_read(self::$info[$id]['buf'], $id);
	}
	
	function closeEventConn($id)
	{
		if( NULL == $id )
		{
			return;
		}
		if ( !isset( self::$info[$id] ) )
		{
			return;
		}

		if ( isset( self::$info[$id]['del'] ) )
		{
			return;
		}
		self::$info[$id]['del'] = true;
		
		$If = self::$info[$id]['if'];
		if( method_exists($If,'onClose') )
		{
			$If::onClose($id);
		}			
		
		event_buffer_disable(self::$info[$id]['buf'], EV_READ);  
		event_buffer_free(self::$info[$id]['buf']);  
		fclose(self::$info[$id]['conn']);  

		if( isset( self::$map[$id] ) )
		{
			self::closeEventConn( self::$map[$id] );
			unset( self::$map[$id] );
		}
		
		if(isset(self::$proto[$If]['flag']) && isset(self::$info[$id]['flag']) )
		{
			if(isset(self::$proto[$If]['flag'][ self::$info[$id]['flag'] ]))
			{
				unset( self::$proto[$If]['flag'][ self::$info[$id]['flag'] ] );
			}
		}
		
		unset(self::$info[$id]);  

		return;
	}
	
	function setupConn($If,$addr)
	{
		$conn = stream_socket_client($addr, $errno, $errstr,30,
						STREAM_CLIENT_CONNECT );
		if(!$conn)
		{
			return -1;
		}
		stream_set_blocking ($conn,0);

		return self::setupClientConn($If,$conn);
	}
	
	function setupClientConn($If,$conn)
	{
		$id = self::$fdid++;
		$buffer = self::newProxyEventBuf($conn,'proxy_read','proxy_write','proxy_error',$id);
		
		self::$info[$id] = array();
		self::$info[$id]['if']    = $If;
		self::$info[$id]['conn']  = $conn;
		self::$info[$id]['buf']   = $buffer;  
		self::$info[$id]['info']  = NULL; //该连接当前已读取数据 
		self::$info[$id]['winfo'] = NULL; //该连接当前已读取数据 
		
		if( !self::$proto[$If]['isflag'] && method_exists($If,'onAccept'))
		{
			$If::onAccept($id);
		}
		return $id;
	}
	

	private function initIf($port,$If)
	{
		if( 0 != $port )
		{
			$sweb = stream_socket_server("tcp://0.0.0.0:$port", $errno, $errstr);  
			if (!$sweb) die("$errstr ($errno)");  
			stream_set_blocking($sweb,0); // 非阻塞  

			self::$event[$If] = event_new();  
			event_set(self::$event[$If], $sweb, EV_READ | EV_PERSIST, array(__CLASS__, 'proxy_accept'), $If);  
			event_base_set(self::$event[$If], self::$base);  
			event_add(self::$event[$If]);
		}
		
		if( method_exists($If,'init') )
		{
			$If::init();
		}	
	}

	private function newProxyEventBuf($conn,$r,$w,$e,$id)
	{	
		//libevent的写事件如果设置了就一直触发。
		//去掉写事件触发，改为写失败后设置个定时器延时100ms后再尝试触发重写
		//$buffer = event_buffer_new($conn, array(__CLASS__, $r), array(__CLASS__, $w), array(__CLASS__, $e), $id);  
		$buffer = event_buffer_new($conn, array(__CLASS__, $r), NULL, array(__CLASS__, $e), $id);  		
        event_buffer_base_set($buffer, self::$base);  
        event_buffer_timeout_set($buffer, 0,0);  
        event_buffer_watermark_set($buffer, EV_READ | EV_WRITE, 1, 0xffffff);  
        event_buffer_priority_set($buffer, 10);  
        event_buffer_enable($buffer, EV_READ | EV_WRITE | EV_PERSIST ); 

		return $buffer;
	}	

    private function proxy_accept($socket, $flag, $If)   
    {  
		
        $conn = stream_socket_accept($socket);  
        stream_set_blocking($conn,0); 
		
		return self::setupClientConn($If,$conn);
		
		//self::proxy_read($buffer, $id);
    }  
	
    private function proxy_error($buffer, $error, $id)   
    {  
		if ($error == (EVBUFFER_READ | EVBUFFER_TIMEOUT)) 
		{
			event_buffer_enable($buffer, EV_READ|EV_WRITE);
			return;
		}
		if ($error == (EVBUFFER_WRITE | EVBUFFER_TIMEOUT)) 
		{
			event_buffer_enable($buffer, EV_READ|EV_WRITE);
			return;
		}
		
		if ($error == (EVBUFFER_READ | EVBUFFER_EOF)) 
		{
			return self::closeEventConn($id);
		}
		if ($error == (EVBUFFER_READ | EVBUFFER_ERROR)) 
		{
			return self::closeEventConn($id);
		}		
		return self::closeEventConn($id);
    }  
      
    private function proxy_read($buffer, $id)   
    {  
		if ( !isset(self::$info[$id]) )
		{
			self::closeEventConn($id);
			return false;
		}
		$info = &self::$info[$id]['info'];
		$If   =  self::$info[$id]['if'];
        while ($read = event_buffer_read($buffer, 4096)) 
		{  
            $info .= $read;  
        } 
		if( NULL == $info )
		{
			return true;
		}
		//判断是否需要读取第一行的标记
		if( self::$proto[$If]['isflag'] && !isset(self::$info[$id]['flag']) )
		{
			$pos = strpos($info, "\n");
			if( false === $pos )
			{
				if( true !== self::$proto[$If]['isflag'] && strlen($info) > self::$proto[$If]['isflag'] )
				{
					self::$info[$id]['flag'] = true;
					if( method_exists($If,'onAccept'))
					{
						$If::onAccept($id,NULL);
					}					
				}
				else
				{
					if ( strlen($info) > 50 )
					{
						self::closeEventConn($id);
						return false;
					}
					return true;//
				}
			}
			else
			{
				$len = strlen("\n");
				$flag = substr($info,0,$pos+$len);
				$info = substr($info,$pos+$len);

				//list($flag,$info) = explode("\n",$info);
				$flag = trim($flag);
				self::$info[$id]['flag'] = $flag;
				if( isset(self::$proto[$If]['flag'][$flag]) )
				{
					self::closeEventConn(self::$proto[$If]['flag'][$flag]);
				}
				self::$proto[$If]['flag'][$flag] = $id;
				if( method_exists($If,'onAccept'))
				{
					$If::onAccept($id,$flag);
				}
			}
		}
		if ( NULL != $info &&  method_exists($If,'onRead'))
		{
			$r = $If::onRead($id,$info);
			if( false === $r )
			{
				return true;//返回false，表示暂停后面处理
			}
		}
		if( isset(self::$map[$id]) )
		{
			self::writeconn( self::$map[$id], $info );
		}
		if( strlen($info) > 2048 )
		{
			$info = NULL;
		}
		return true;
 	}  
    
	//libevent的write事件会被不停触发，所以不用该接口，在写失败后使用定时器调用下面的函数重写	
    private function proxy_write($buffer, $id)   
    {  
		$If = self::$info[$id]['if'];
		$If::ew($id);
    }

	private function proxy_write_ok($id)
	{
		$info = NULL;
		self::writeconn( $id, $info );
	}	
    

	private function procTimer($socket, $flag, $info)  
	{
		call_user_func_array ($info['fun'], $info['arg']);
		if( $info['cyc'] )
		{
			event_timer_add($info['fd'], $info['times']);
		}
		else
		{
			event_del($info['fd']); 
			event_free($info['fd']); 
		}
	}
	
	function getIF($id)
	{
		return self::$info[$id]['if'];
	}

	function getflag($id)
	{
		return self::$info[$id]['flag'];
	}
	function getid($If,$flag)
	{
		if( isset(self::$proto[$If]['flag'][$flag]) )
		{
			return self::$proto[$If]['flag'][$flag]; 
		}
		return false;
	}
	function getconn($id)
	{
		if( isset(self::$info[$id]) )
		{
			return self::$info[$id]['conn']; 
		}
		return false;
	}
	function getrinfo($id)
	{
		if( isset(self::$info[$id]) )
		{
			return self::$info[$id]['info']; 
		}
		return false;
	}
	function getIFConns($If)
	{
		return self::$proto[$If]['flag'];
	}
	function getIFPort($If)
	{
		return self::$proto[$If]['port'];
	}
	/////////////////////////////////////////////////////////
      
	//pweb：作为web访问的端口
	//phic：信息中心与中转服务器控制信息连接的端口
	//ppxy：信息中心与中转服务器数据转发连接的端口
	function start()  
	{  
		self::$base = event_base_new();  
		
		foreach(self::$proto as $If=>&$ifInfo)
		{
			echo "start $If on $ifInfo[port]\n";
			self::initIf($ifInfo['port'],$If,$ifInfo['flag']);
		}
		
		$r = event_base_loop(self::$base);  
		
		echo "$r:exit loop\n";
	} 	
}
////////需要保活的客户端的统一处理////////////////////////////////
class clientMgt
{
	//继承类必须定义如下变量
	//static $client;
	//static $addr     = 'tcp://192.168.93.1:'.HIC_SERVER_RWIFI1;
	//static $livetime = 5;//保活时间，秒为单位
    //static $timeout  = false;
	
	//多个地方可能会启动定时器来重连，包括重连函数自己也会启动定时器来重连
	//这个变量就是在重连函数自己可能启动定时器不断重连时，其它地方不要来重连
	static $hasConnTimer=array();

	static function init($class)
	{
        //设定定时器，1秒后就开始启动连接
        server::startTimer(array($class, 'getCtrlConn'),1000000,array($class),false);
        //指定时间内如果无心跳，则认为连接已断，重连。两次检查才认为离线，所以循环时间为livetime/2
        server::startTimer(array($class, 'timeoutcheck'),500000*$class::$livetime,array($class));
	}

	//必须要能连上，否则就循环重连
	static function getCtrlConn($class)
	{
		self::$hasConnTimer[$class] = true;
		if ( method_exists($class,'getConnAddr'))
		{
			$r = $class::getConnAddr();
			if(!$r)
			{
				$run=false; 
				server::startTimer(array($class, 'getCtrlConn'),1000000*15,array($class),false);;
				return;
			}
		}
		
		if ( $class::$client > 0 ) 
		{
			self::$hasConnTimer[$class] = false;
			return $class::$client;
		}

		$class::$client = server::setupConn($class,$class::$addr);
		if( 0 >= $class::$client )						
		{
			//30秒后再重试
			server::startTimer(array($class, 'getCtrlConn'),1000000*15,array($class),false);;
			return ;
		}

        $class::$timeout = false;

		if ( method_exists($class,'onConn'))
		{
			$class::onConn($class::$client);
		}		

		self::$hasConnTimer[$class] = false;

		return $class::$client;
	}

	static function timeoutcheck($class)
	{
        if(!$class::$timeout)
        {
            $class::$timeout = true;
            return;
        }
		if ( method_exists($class,'hasTimeout'))
		{
			$class::hasTimeout();
		}
        //关闭当前连接，重连
        if( validID($class::$client) )
        {
            server::closeEventConn($class::$client);
            $class::$client = NULL;
			//如果主动关闭指定的连接，则会在onClose中重启连接
			//注释掉下面这句
			//$class::getCtrlConn($class);		
        }
		else
		{
			if( !self::$hasConnTimer[$class] )
			{
				server::startTimer(array(__CLASS__, 'getCtrlConn'),1000000*2,array($class),false);;
			}
		}
	}
	
	static function onClose($id)
	{
		$class = server::getIF($id);
		$class::$client = NULL;
		if( !self::$hasConnTimer[$class] )
		{
			server::startTimer(array(__CLASS__, 'getCtrlConn'),1000000*2,array($class),false);;
		}
	}

	static function onRead($id,&$info)
	{
		$class = server::getIF($id);
        $class::$timeout = false;
		return true;
	}
	
}


/////////////////////////////////////////////////////////

class websocket
{
	private function handshakingInfo(&$info)
	{
        //处理Sec-WebSocket-Key
        $Sec_WebSocket_Key = '';
        if(!preg_match("/Sec-WebSocket-Key: *(.*?)\r\n/", $info, $match))
        {
			return false;
        }		
        $Sec_WebSocket_Key = $match[1];

		$origin = NULL;
	    if(preg_match("/Origin: (.*)\r\n/", $info, $match))
		{
			$origin=$match[1]; 
		}  

		$new_key = base64_encode(sha1($Sec_WebSocket_Key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));
        // 握手返回的数据
        $new_message = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n";
		if($origin)
		{
			//$new_message = "Sec-WebSocket-Origin: $origin\r\n";
		}
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
		return $new_message;
		
	}

	function handshaking(&$info)
	{
		$pos = strpos($info,"\r\n\r\n");
		if( false === $pos )
		{
			if ( 4096 < strlen($info) )
			{
				return false;
			}
			return NULL;
		}
		
		$response = self::handshakingInfo($info);
		if( false ==  $response)
		{
			return false;
		}
		return $response;
	}
	
	//解析websocke数据的协议,获取其中一个包
	static function decode( &$info )
	{
		//头部最少需要2个字节
		$headLen = 2;
		$infoLen = strlen($info);
		//ws头部最少需要6个字节
		if( $infoLen < $headLen )
		{
			return NULL;
		}
        $b1 = ord($info[0]);
        $isFinframe = $b1>>7;
        $opcode = $b1 & 0xf;

        $b2 = ord($info[1]);
		$isMask = $b2>>7;
        $datalen = ord($info[1]) & 127;

        switch($opcode)
        {
            case 0x0://附加
                break;
            case 0x1://文本
                break;
            case 0x2://二进制
                break;
            case 0x8://指示关闭
                break;
            case 0x9://ping
                break;
            case 0xa: //pong 
                break;
            default ://error
				break;
                return 0;
        }
        
		if( $isMask )
		{
			$headLen += 4;//有掩码存在
		}
		
		
		switch($datalen)
		{
			case 126://后面两个字节是payload长度
				$headLen += 2;
				if( $infoLen < $headLen )
				{
					return NULL;
				}
				$conver  = unpack('ndatalen', substr($buffer, 2, 2));
				$datalen = $conver['datalen'];
				break;
			case 127://后面四个字节是payload长度
				$headLen += 4;
				if( $infoLen < $headLen )
				{
					return NULL;
				}
				$conver  = unpack('N2', substr($buffer, 2, 4));
				$datalen = $conver[1]*4294967296 + $conver[2];
				break;
			default:
				break;
		}
		
		if( $infoLen < ($headLen+$datalen) )
		{
			return NULL;
		}

		$frame = substr($info,$headLen,$datalen);
		if( !$isMask )
		{
			$info  = substr($info,$headLen+$datalen);
			return $frame;
		}
		$mask = substr($info,$headLen-4,4);
		$info  = substr($info,$headLen+$datalen);

		$ret = NULL;
        for ($i = 0; $i < $datalen; $i++) 
		{
            $ret .= $frame[$i] ^ $mask[$i % 4];
        }
		return $ret;
	}
	

	//打包websocket数据
	static function encode($info)
	{
        $len = strlen($info);

        $b1 = "\x81";
        if($len<=125)
        {
            return $b1.chr($len).$info;
        }
        else if($len<=65535)
        {
            return $b1.chr(126).pack("n", $len).$info;
        }
        else
        {
            return $b1.chr(127).pack("xxxxN", $len).$info;
        }
	}

}
?> 