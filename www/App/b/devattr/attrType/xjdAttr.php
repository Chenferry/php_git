<?php
//新风属性：包含
class xjdAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_INT,'cf'=>TABLE_FIELD_INT);
	static $page = 'xjd'; 
	static $name = DEV_SYSNAME_XJD;
	
	private static function addXJDAttr($name,$sys,$icon,$index,$cfg=NULL)
	{
		if( NULL != $cfg )
		{
			$cfg=serialize($cfg);
		}
		$attr = array();
		$attr['NAME']      = $name;
		$attr['SYSNAME']   = $sys;	
		$attr['ATTRINDEX'] = $index;			
		$attr['CFGINFO']   = $cfg;			
		$attr['INUSE']	   = -1; //这个表示只是用来做条件，不在其它地方显示
		$attr['ICON']	   = $icon;	 	
		$attr['ISR']	   = 1;		
		$attr['ISC']	   = 0;	 	
		$attr['ISS']	   = 0;
		return $attr;
	}
	
	//附加信息
	static function parseAdditonInfo($value,$attrid)
	{
		//页面布局方式，ver版本为0
		$value = $value['info'];
		$info  = unpack('Cver/Clen',$value);
		$layout= substr($value,2,$info['len']);
		
		//根据上传的家电类型，获取得到该家电的详细配置信息
		include_once( dirname(dirname(__FILE__))."/xjdcfg/$layout.php");
		$varname = $layout.'Json';
		$cfg = &$GLOBALS[$varname];

		//自动更新添加属性
		//开关+设置(当前/设置)+数字+功能按钮+选择按钮+告警列表
		//设置的只需要获取当前值
		$attrList = array();
		$i = 100;
		if( isset( $cfg['set'] ) )
		{
			foreach( $cfg['set'] as &$set )
			{
				$ainfo = array( 'type'=>0,'min'=>$set['range'][0],'max'=>$set['range'][1],'calc'=>'X','unit'=>$set['unit'] );
				$attrList[] = self::addXJDAttr($set['name']['cur'],'num',$set['icon'],$i++,$ainfo);
				$attrList[] = self::addXJDAttr($set['name']['set'],'num',$set['icon'],$i++,$ainfo);
			}
		}
		if( isset( $cfg['num'] ) )
		{
			foreach( $cfg['num'] as &$num )
			{
				$ainfo = array( 'type'=>0,'min'=>-65535,'max'=>65535,'calc'=>'X','unit'=>$num['unit'] );
				$attrList[] = self::addXJDAttr($num['name'],'num',$num['icon'],$i++,$ainfo);
			}
		}
		if( isset( $cfg['fun'] ) )
		{
			foreach( $cfg['fun'] as &$fun )
			{
				$attrList[] = self::addXJDAttr($fun['name'],'kg',$fun['icon'],$i++,NULL);
			}
		}
		if( isset( $cfg['select'] ) )
		{
			foreach( $cfg['select'] as &$sel )
			{
				$ainfo = $sel['value'];
				$attrList[] = self::addXJDAttr($sel['name'],'tcq',$sel['icon'],$i++,$ainfo);
			}
		}

		if( isset( $cfg['alarm'] ) )
		{
			foreach( $cfg['alarm'] as &$alarm )
			{
				$ainfo = array( 'info'=>$alarm['info'] );
				$attrList[] = self::addXJDAttr($alarm['name'],'gj',$alarm['icon'],$i++,$ainfo);
			}
		}
		
		//查找当前设备的ID
		$c = new TableSql('homeattr','ID');
		$devid = $c->queryValue('DEVID','ID=?',array($attrid));
		$GLOBALS['dstpSoap']->setModule('home','end');
		$GLOBALS['dstpSoap']->addDevAttList($devid,$attrList);
		
		//更新语音命令
		
		
		return array('layout'=>$layout,'cfg'=>$cfg);
	}
	
	static function getPage(&$attr,$attrid)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		$page = $cfg['cfg']['page'];
		return $page;
	}
	
	//把数据库信息通过pack转化为下发控制命令信息
	//array(
	//  'open'  =>0/1
	//  'set'=>array('wd'=>Value )     //数值设置功能    
	//	'fun'   =>array('jdcc'=>,'flz'=>,'uvsj'=>)//开关功能
	//  'select'=>array(                          //多选一功能
	//		'mode'=>0/1/2
	//		'ptf'=>0/1..
	//		'pf'=>0/1..
	//		'sf'=>
	//  ), 
	//)
	//开关+设置值+功能按钮+选择按钮
	//开关（1字节）+排风（1字节）+送风（1字节）+旁通（1字节）+模式（1字节）+功能（7字节）
	//当开关为00即关时，后面数据都无
	//每个字节位置为FF时表示该位置功能不处理
	static function getCMDInfo($value,$attrid=NULL)
	{
		if( !is_array( $value ) ) $value = unserialize($value);
		
		//设置关状态
		if( array_key_exists('open',$value) && 0 == $value['open'] )
		{
			return pack('C',0);
		}
		
		$cmd = pack('C',0x01);
		
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		$cfg = unserialize($cfg);
		$cfg = $cfg['cfg'];
		
		$cmdList = array('set','fun','select');
		foreach( $cmdList as &$cmdkey )
		{
			if( !isset( $cfg[$cmdkey] )  )
			{
				continue;
			}
			if( !array_key_exists($cmdkey,$value) )
			{
				$value[$cmdkey] = array();
			}

			foreach( $cfg[$cmdkey] as $key=>$v )
			{
				if( 'set' == $cmdkey )
				{
					if( !array_key_exists($key,$value[$cmdkey]) )
					{
						$value[$cmdkey][$key]['set'] = 0xFFFF;
					}
					$cmd .= pack('n',$value[$cmdkey][$key]['set']);
				}
				else
				{
					if( !array_key_exists($key,$value[$cmdkey]) )
					{
						$value[$cmdkey][$key] = 0xFF;
					}
					$cmd .= pack('C',$value[$cmdkey][$key]);
				}
			}
		}
		return $cmd;
	}

	//把设备上报的状态信息转为数据库信息
	//开关+设置(当前/设置)+数字+功能按钮+选择按钮+告警列表+故障代码长度+故障代码
	//开关（1字节）+排风（1字节）+送风（1字节）+旁通（1字节）+模式（1字节）+功能（7字节）
	static function getStatusInfo($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$attrcfg = $c->query('CFGINFO,ATTRSET,ATTRINDEX,DEVID','ID=?',array($attrid));
		$funCfg  = unserialize( $attrcfg['CFGINFO'] );
		$funCfg  = $funCfg['cfg'];

		$old = unserialize($attrcfg['ATTRSET']);

		//开关+设置(当前/设置)+数字+功能按钮+选择按钮+告警列表+故障代码长度+故障代码
		$open = substr($value,0,1);
		$cfg  = unpack('Copen',$open);
		
		$start = 1;
		$cmdList = array('set','num','fun','select','alarm','code');
		$attrIndex = 100;
		$upList  = array(); //记录所有要更新的属性的index和值
		foreach( $cmdList as $cmdkey )
		{
			if( !isset( $funCfg[$cmdkey] )  )
			{
				continue;
			}
			$cfg[$cmdkey] = array();
			switch( $cmdkey )
			{
				case 'set':
					$subs  = substr($value,$start,count( $funCfg[$cmdkey] )*4);
					$start = $start + count( $funCfg[$cmdkey] )*4;
					$subs  = unpack("n*",$subs);
					$i = 0;
					foreach( $funCfg[$cmdkey] as $funindex=>$fun )
					{
						$cfg[$cmdkey][$funindex] = array( 'cur'=>$subs[$i*2+1],'set'=>$subs[$i*2+2]);
						$i++;

						if( $cfg[$cmdkey][$funindex]['cur'] != $old[$cmdkey][$funindex]['cur'] )
						{
							$upList[] = array('ATTRINDEX'=>$attrIndex,'ATTRINT'=>$cfg[$cmdkey][$funindex]['cur']);
						}
						$attrIndex++;
						
						if( $cfg[$cmdkey][$funindex]['set'] != $old[$cmdkey][$funindex]['set'] )
						{
							$upList[] = array('ATTRINDEX'=>$attrIndex,'ATTRINT'=>$cfg[$cmdkey][$funindex]['set']);
						}
						$attrIndex++;
					}
					break;
				case 'num':
				case 'fun':	
				case 'select':	
				case 'alram':
					$clen = 1;
					if( 'num' == $cmdkey )
					{
						$clen = 2;
					}
					$subs  = substr($value,$start,count( $funCfg[$cmdkey] )*$clen );
					$start = $start + count( $funCfg[$cmdkey] )*$clen;
					if( 'num' == $cmdkey )
					{
						$subs  = unpack("n*",$subs);
					}
					else
					{
						$subs  = unpack("C*",$subs);
					}
					$i = 0;
					foreach( $funCfg[$cmdkey] as $funindex=>$fun )
					{
						$cfg[$cmdkey][$funindex] = $subs[$i+1];
						$i++;
						if( $cfg[$cmdkey][$funindex] != $old[$cmdkey][$funindex] )
						{
							$upList[] = array('ATTRINDEX'=>$attrIndex,'ATTRINT'=>$cfg[$cmdkey][$funindex]);
						}
						$attrIndex++;
					}
					
					break;
				case 'code':
					break;
			}
		}
		
		if( $cfg['open']!=$old['open'] )
		{
			$upList[] = array('ATTRINDEX'=>$attrcfg['ATTRINDEX'],'ATTRINT'=>$cfg['open']);
		}

		//判断是否相等
		if( NULL != $upList || false == $old )
		{
			$info = array();
			$info['ID']      = $attrid;
			$info['ATTRSET'] = serialize($cfg);
			$c->update($info);	
			
			$devid = $attrcfg['DEVID'];
			//要针对逐个属性的是否有变化去决定更新，以便触发联动
			$smartTriger = array();
			foreach( $upList as &$up )
			{
				$upid = $c->queryValue('ID','DEVID=? AND ATTRINDEX=?',array($devid,$up['ATTRINDEX']));
				$c->update($up,NULL,'ID=?',array($upid));
				$smartTriger[] = $upid;
			}
			
			//检测智能模式是否触发
			$GLOBALS['dstpSoap']->setModule('smart','smart');
			$GLOBALS['dstpSoap']->checkAttrTriger($smartTriger);

			noticeAttrModi($attrid);
		}
		
		//open已经在本函数自己处理了，无需后续处理
		return false;
		return $cfg['open'];
	}

	static function getDetail($value,$attrid=NULL)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->query('ATTRSET,CFGINFO','ID=?',array($attrid));

		$a = array();
		$a['info'] = unserialize($cfg['ATTRSET']); 
		$a['cfg']  = unserialize($cfg['CFGINFO']); 
		$a['cfg']  = $a['cfg']['cfg'];
		if( isset($a['cfg']['yuyin']) )
		{
			unset( $a['cfg']['yuyin'] );
		}
		return $a;
	}
	

	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array( 'open'=>array('开','关','停'));
	static function getYuyinDict($id)
	{
		$c = new TableSql('homeattr','ID');
		$cfg = $c->queryValue('CFGINFO','ID=?',array($attrid));
		if( NULL == $cfg )
		{
			return array();
		}
		$cfg = unserialize($cfg);
		$cfg = $cfg['cfg'];

		$ret = array();
		$cmdList = array('set','fun','select');
		foreach( $cmdList as &$cmdkey )
		{
			if( !isset( $cfg[$cmdkey] ) )
			{
				continue;
			}
			foreach( $cfg[$cmdkey] as &$f )
			{
				$ret[] = array('word'=>$f['name'],'attr'=>$cmdkey,'id'=>$id);
			}
			if( !isset( $cfg['yuyin'][$cmdkey] ) )
			{
				continue;
			}

			foreach( $cfg['yuyin'][$cmdkey] as &$yy )
			{
				foreach( $yy as $attr=>$wordList )
				{
					if( 'bm' == $attr )
					{
						$attr = $cmdkey;//这个是指功能别名
					}
					foreach( $wordList as &$word )
					{
						$ret[] = array('word'=>$word,'attr'=>$attr,'id'=>$id);
					}
				}
			}
		}
		return $ret;
	}	

	//语音识别输入处理函数
	//static function yuyin($yuyin,$attrid)
	//{
	//}	

}

 

?>