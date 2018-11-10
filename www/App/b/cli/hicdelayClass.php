<?php

////////////////////////////////////////////////////
//引用文件中如果定义了$GLOBALS['psysAllowBlock']
//则表示该进程中psyse中处理的都是发往外网的请求，可能被阻塞
//否则都是内部进程通讯，不允许被阻塞
class psys
{
	static $batch;
	
	//进程刚起来时，数据表中有很多初始延迟处理需要执行。但这些延迟处理可能会被阻塞
	//为了避免阻塞其它业务进程，在专门的delay中处理这些被阻塞的函数
	static function procInitDelay()
	{
		$t = time();
		$starttime = INFINITE_TIME - 10;
		$all = self::$batch->queryAll('ID,EXCTIME','STARTTIME=?',array($starttime));
		foreach( $all as &$a )
		{
			if( $a['EXCTIME'] > $t)
			{
				$times = $a['EXCTIME'] - $t;
				server::startTimer(array('psys', 'procDelay'),$times*1000000,array($a['ID']),false);
			}
			else
			{
				self::procDelay($a['ID']);
			}
		}		
	}
	
	static function init()
	{
		self::$batch = new TableSql('batchproc','ID');
		
		if( isset($GLOBALS['psysAllowBlock']) )
		{
			//似乎应该启动循环定时器，定时调用下这个函数
			//以免业务进程那边挂了，有些延时函数不能被调用。否则在这个进程不挂其它进程挂了情况下，有可能丢包
			self::procInitDelay();
		}
		
	}
	//为了避免大量下发sysMaintence，需要对其数量进行控制。
	static function procDelay($planid)
	{
		if( 'i' == HIC_LOCAL )
		{
			setSysUid(0);
		}
		$a = self::$batch->query('*','ID=?',array($planid));
		if( NULL == $a )
		{
			return;
		}
		self::$batch->del1('ID=?',array($planid));
		$array = unserialize($a['METHODARRAY']);

		if( 'i' == HIC_LOCAL )
		{
			setSysUid($a['CLOUDID']);
		}
		$GLOBALS['dstpSoap']->setModule($a['DSTPMODULE'],$a['SERVICE']);
		$GLOBALS['dstpSoap']->__call($a['METHOD'],($array));	
		return;
	}
    static function onRead($id,&$info)
    {
        $cmd = server::getInfo($info,"\n");
        while(  NULL !== $cmd )
        {
            $cmd = trim($cmd);
            list($cmd,$parm) = explode(':',$cmd,2);
			switch($cmd)
			{
				case 'delay':
					list($times,$planid) = explode('-',$parm);
					if( 0 == $times )
					{
						self::procDelay($planid);
					}
					else
					{
						server::startTimer(array('psys', 'procDelay'),$times*1000000,array($planid),false);
					}
					break;
				case 'yzbj':
					list($attrid,$value) = explode('@',$parm);
					if ( method_exists('yzbjConn', 'sendPlayctrl') )
					{
						yzbjConn::sendPlayctrl( unserialize($value),$attrid );
					}

					break;
				case 'yzbjstatus':
					yzbjConn::onStatus($parm);
					break;
				default:
					break;
			}
            $cmd = server::getInfo($info,"\n");         
        }
        return true;        
    }
}

?> 