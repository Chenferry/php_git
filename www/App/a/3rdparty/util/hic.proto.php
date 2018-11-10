<?php  
//HIC协议处理，只负责各层消息包拆解和组装
class HICProto
{
	//$info:待计算校验码的信息
	//start:从第几个字节开始参与计算，
	static function calcCRC(&$info,$start=0,$len=NULL)
	{
		if( NULL === $len )
		{
			$end = strlen($info)-$start;
		}
		else
		{
			$end = $len+$start;
		}
		$code = $info[$start];
		for($i=($start+1); $i<$end; $i++)
		{
			$code = $code^$info[$i];
		}
		return $code;
	}
	
	//根据指定的设备ID计算指定的HIC密钥是否正确，并刷新相关信息
	static function checkHICHeader($devid,$key)
	{
		if ( !validID($devid) )
		{
			return true; //未添加设备，无需校验
		}
		$c   = new TableSql('homedev','ID');
		$dev = $c->query('ID,STATUS,PHYDEV,CHID,HCID','ID=?',array($devid));
		if ( PHYDEV_TYPE_SYS == $dev['PHYDEV'] || DEV_STATUS_WAITACK == $dev['STATUS'] )
		{
			return true;
		}
		
		//根据HCID和CHID分别计算出IMSI，比较是否相同
		$chid  = pack('l',intval($dev['CHID']));
		$hcid  = pack('l',intval($dev['HCID']));
		$key1  = substr($key, 0, 4);  
		$key2  = substr($key, 4, 4);  
		$tmsi1 = $chid^$key1;
		$tmsi2 = $hcid^$key2;
		$chid1 = $tmsi1^$key1;
		
		if( ($tmsi1 != $tmsi2) || ($chid != $chid1) )
		{
			//24G设备不做加密处理，其它都需要
			if( PHYDEV_TYPE_24G != $dev['PHYDEV'] )
			{
				return false;
			}
		}

		//保存IMSI到数据表中。数据表中不能保存二进制数值，所以要先转为文本
		$tmsi = unpack('lTMSI',$tmsi1);
		$info = array();
		$info['ID']    = $devid;		
		$info['TMSI']  = $tmsi['TMSI'];
		$info['ETIME'] = time();		
		$c->update1($info);
		
		return true;
	}

	//////////协议读取和解包过程/////////////////////////////////////////
	//读取一个完整协议包，包括标记码，但扣除了校验码
	static function onRead(&$info,&$msg)
	{
		while( NULL != $info )
		{
			$i = 0;
			$l = strlen($info);
			while( ($i<$l) && ((chr(0xFE) != $info[$i]) && (chr(0xFC) != $info[$i]) ) )
			{
				$i++;
			}
			if( $i >= $l )
			{
				$info =  NULL;
				break;
			}
			
			//剩余内容至少要有4个字节(3个固定+1个内容)，否则肯定不满足，需要继续等待数据后读取
			if( ($l - $i) < 4 )
			{
				break;
			}
			
			if ( $i )
			{
				$info = substr($info,$i);
				if ( NULL == $info )
				{
					break;
				}
			}
			
			$flag = $info[0]; //取第一个标记字段，用来判断协调器软件版本

			$len = unpack('Clen',$info[1]);
			$len = $len['len'];
			//第二个字节如果为0，表示需要在后面
			//if( 0 == $len )
			//{
			//	if( strlen($info) < (23+4) )
			//	{
			//		break;
			//	}
			//	$len = unpack('Nlen',substr($info,23,4));
			//	$len = $len['len'];
			//	//这个一定要加上长度合法性判断
			//}
			
			
			if ( ($len + 3) > strlen($info) )//3:标记头，长度，校验码
			{
				//还没接受完全，跳出等待继续接受
				break;
			}

			//第一个字节flag是不参与校验计算，第二个字节长度信息要参与
			//len表示的是实际消息长度，实际参与校验的，需要在len上加长度那个字节
			$crc = self::calcCRC($info,1,$len+1);
			//计算最后一个校验码
			if( $crc != $info[2+$len] )
			{
				//如果校验不对，则可能是丢失数据。跳过本次头部，再继续查找下一个头部
				$info = substr($info,1);
				continue;
			}
			
			//返回的内容去除校验码
			$msg = substr($info,0,$len+2);
			
			$info = substr($info, 3+$len);
			
			return true;
			break;
		}
		return false;		
	}	
	
	
	//协议包头处理
	static function getProtoHeader(&$info,&$header)
	{
		$flag = $info[0];
		$len  = $info[1];
		$msg  = substr($info,2);
		switch($flag)
		{
			case chr(0xFC):	//新版本的协调器，不带mac地址
				$header = unpack('nLOGICADDR/cRSSI',$msg);
				break;
			case chr(0xFE):
				$header = unpack('H16PHYADDR/nLOGICADDR/cRSSI',$msg);
				break;
			default:
				return;	
		}	
		if( isset($header['PHYADDR']) )
		{
			$info = substr($msg,11);
		}
		else
		{
			$info = substr($msg,3);
		}
		return;
	}

	//命令包头处理
	//从原始码流中获得协议头部，hic头，消息
	static function getHICHeader(&$msg,&$hic)
	{
		//获取hic头部
		$hic = unpack('Cseq/a8key/Ccmd',$msg);

		//获取消息体
		$msg = substr($msg,10);
		return;
	}
	
	////////////协议组包过程////////////////////////
	static function genProtoHeader(&$msg,$len=NULL)
	{
		if( NULL === $len )
		{
			$len = strlen($msg);
		}

		$newlen = $len;
		if( $len > 235 )
		{
			//如果消息长度超过一定长度，则消息第二个字节为0，在HIC头后跟4个字节表示实际长度
			//HIC头前面的字节有H16n1c1和C1a8C1，共21字节
			$newlen  = $len+4;
			$len     = 0; 
			$msg     = substr($msg,0,21).pack("N",$newlen).substr($msg,21);
		}
		$info = pack('C',$len ).$msg; 
		$crc  = self::calcCRC($info);
		$msg  = chr(0xFE).$info.$crc;
		
		return true;
	}
	
	static function genHICHeader(&$dev,$cmd,$seq)
	{
		$scid = pack('l',$dev['LOGICID']);
		$tmsi = pack('l',$dev['TMSI']);
		$key  = $scid^$tmsi;
		
		$header = pack('H16n1c1', $dev['PHYADDR'], $dev['LOGICADDR'],1 );
		return $header.pack('C1a8C1',$seq,$key,$cmd);

	}

}

?>