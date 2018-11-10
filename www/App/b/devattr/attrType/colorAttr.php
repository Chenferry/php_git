<?php
//彩灯
class colorAttrType
{
	static $cfg  = array('r'=>1,'c'=>1,'s'=>0,'vf'=>TABLE_FIELD_CHAR,'cf'=>TABLE_FIELD_CHAR,'rep'=>1);
	static $page = 'color'; 
	static $name = DEV_SYSNAME_COLOR;
	
 
	private static function HLS2RGB( &$r, &$g, &$b, $h, $l, $s)
	{
		$l /= 254;
		$s /= 254;
	 
		if($l <= 0.5)
			$cmax = $l*(1+$s);
		else
			$cmax = $l*(1-$s)+$s;
		$cmin = 2*$l-$cmax;
	 
		if($s == 0){
			$r = $g = $b = $l*255;
		}else{
			$r = self::HLS2RGBvalue($cmin,$cmax,$h+120)*255;
			$g = self::HLS2RGBvalue($cmin,$cmax,$h)*255;
			$b = self::HLS2RGBvalue($cmin,$cmax,$h-120)*255;
		}
	}
	 
	private static function HLS2RGBvalue($n1,$n2,$hue)
	{
		if($hue > 360)
			$hue -= 360;
		else if($hue < 0)
			$hue += 360;
		if($hue < 60)
			return $n1+($n2-$n1)*$hue/60;
		else if($hue < 180)
			return $n2;
		else if($hue < 240)
			return $n1+($n2-$n1)*(240-$hue)/60;
		else
			return $n1;
	}
	
	private static function RGB2HLS( &$h, &$l, &$s, $r, $g, $b)
	{
		$dr = $r/255;
		$dg = $g/255;
		$db = $b/255;
		$cmax = max($dr, $dg, $db);
		$cmin = min($dr, $dg, $db);
		$cdes = $cmax - $cmin;
	 
		$ll = ($cmax+$cmin)/2;
		if($cdes){
			if($ll <= 0.5)
				$ss = ($cmax-$cmin)/($cmax+$cmin);
			else
				$ss = ($cmax-$cmin)/(2-$cmax-$cmin);
	 
			if($cmax == $dr)
				$hh = (0+($dg-$db)/$cdes)*60;
			else if($cmax == $dg)
				$hh = (2+($db-$dr)/$cdes)*60;
			else// if(cmax == b)
				$hh = (4+($dr-$dg)/$cdes)*60;
			if($hh<0)
				$hh+=360;
		}else
			$hh = $ss = 0;
		
		
	 
		$h = $hh;
		//后台计算使用254为最大值
		$l = intval($ll*254);
		$s = intval($ss*254);
		
		return;
	}
	
	
	//
	private static function converToView($value)
	{
		if( NULL == $value ) //默认关灯状态
		{
			return array('m'=>-1);
		}
		$mode = unpack("cm",$value);
		$mode = $mode['m'];
		switch($mode)
		{
			case -1://关灯
				$ret  = array('m'=>-1);
				break;
			case 0: //打开
			case 1: //白光
				$ret  = array('m'=>1);
				$info = unpack('cm/Cw/Cww',$value);
				$ret['l'] = intval(($info['w']+$info['ww'])/2.5) ? : 1;
				$ret['w'] = intval(($info['ww']/($info['w']+$info['ww']))*100);
				$ret['s'] = 0;
				break;
			case 2: //白光呼吸
				$ret  = array('m'=>1);
				$info = unpack('cm/Cw/Cww/Ccyc',$value);
				$ret['l'] = intval(($info['w']+$info['ww'])/2.5);
				$ret['w'] = intval(($info['ww']/($info['w']+$info['ww']))*100);
				$ret['s'] = $info['cyc']/10;
				break;
			case 3: //冷暖交替
				$ret  = array('m'=>1);
				$info = unpack('cm/Cw/Ccyc',$value);
				$ret['l'] = intval($info['w']);
				$ret['w'] = 101;
				$ret['s'] = $info['cyc']/10;
				break;
			case 10: //彩色
			case 11: //彩色呼吸
				$ret  = array('m'=>2);
				$s = 0;
				if( 11 == $mode )
				{
					$info = unpack('cm/nh/Cs/Cl/Cw/Ccyc',$value);
					$s = $info['cyc']/10;
				}
				else
				{
					$info = unpack('cm/nh/Cs/Cl/Cw',$value);
				}
				$r = $g = $b = NULL;
				self::HLS2RGB( $r, $g, $b, $info['h'], $info['l'], $info['s']);
				$ret['r'] = $r;
				$ret['g'] = $g;
				$ret['b'] = $b;
				$ret['w'] = $info['w'];
				$ret['s'] = $s;
				break;
			default:
				$ret  = array('m'=>3,'id'=>($mode-50));
				break;
		}
		return $ret;
	}
	
	//这个函数和colormode文件中的完全一样，后面两个函数要合并
	private static function setColorMode($value,$attrid)
	{
		//value['id'] 待修改的模式ID，为-1表示新增加
		//value['name']模式名称
		//value['set'] => array( array(r,g,b,f,s) )
						//其中r=-1，表示白光
						//r=-2,表示随机颜色
		
		$c = new TableSql('homeattr','ID');
		$modeList = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$modeList = unserialize($modeList);
		if( NULL == $modeList )
		{
			$modeList = array();
		}
		
		if( NULL == $value['name'] )
		{
			$value['name'] = '***';
		}
		
		$id = $value['id'];
		if( -1 == $value['id'] ) //新增
		{
			$id = 10;
			do{
				$find = false;
				$id++;
				foreach($modeList as $key=>&$m)
				{
					if( $id == $m['id'] )
					{
						$find = true;
						break;
					}
				}
			}while($find);
			if( $id > 60 )
			{
				return false;
			}
			$value['id'] = $id;
			$modeList[] = $value;
		}
		else
		{
			foreach($modeList as $key=>&$m)
			{
				if( $value['id'] == $m['id'] )
				{
					$m = $value;
					break;
				}
			}
		}
		$curid = -1;
		foreach($modeList as $key=>&$m)
		{
			if( $value['id'] == $m['id'] )
			{
				$curid = $key;
				break;
			}
		}
		
		$c = new TableSql('homeattr','ID');
		$devid  = $c->queryValue('DEVID','ID=?',array($attrid));
		$c = new TableSql('homedev','ID');
		$phydev = $c->queryValue('PHYDEV','ID=?',array($devid));
		//最多只能设置8个颜色,但24G由于资源限制，最多只能设置三个
		$maxMode = 8;
		if( PHYDEV_TYPE_24G == $phydev )
		{
			$maxMode = 3;
		}
		
		$modeList[$curid]['set'] = array_slice($modeList[$curid]['set'], 0, $maxMode);

		//如果是白光，则不能使用渐变,强制改为呼吸
		$num = count($modeList[$curid]['set']);
		for( $i=0; $i<$num; $i++ )
		{
			$m = &$modeList[$curid]['set'][$i];
			if( 3 != $m['f'] )
			{
				continue;
			}
			if( -1 == $m['r'] || -3 == $m['r'])
			{
				$m['f'] = 2; //白光不能渐变，强制修改为呼吸
				continue;
			}
			//如果是渐变的，下一个也不能是白光
			$next  = ($i+1)%$num;
			$nextm = &$modeList[$curid]['set'][$next];
			if( -1 == $nextm['r'] || -3 == $nextm['r'])
			{
				$m['f'] = 2; 
				continue;
			}
		}
		

		$info = array();
		$info['ID'] = $attrid;
		$info['ATTRSET'] = serialize($modeList);
		$c->update($info);
		noticeAttrModi($attrid);

		return $id;
	}
	
	private static function delColorMode(&$value,$attrid)
	{
		//value['id'] 待修改的模式ID，为-1表示新增加
		$c = new TableSql('homeattr','ID');
		$modeList = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$modeList = unserialize($modeList);
		if( NULL == $modeList )
		{
			return false;
		}
		$index = false;
		foreach($modeList as $key=>&$m)
		{
			if( $value['id'] == $m['id'] )
			{
				$index = $key;
				break;
			}
		}
		if( false === $index )
		{
			return false;
		}
		unset($modeList[$index]);
		$info = array();
		$info['ID'] = $attrid;
		$info['ATTRSET'] = serialize($modeList);
		$c->update($info);
		noticeAttrModi($attrid);
		return true;
	}


	
	//处理白光照明
	private static function procLight(&$value)
	{
		//l   =  x  //亮度
		//w  =  x  //色温，0表示正白，100表示暖白
		//s  =  x  //呼吸速度，单位为秒。0表示不呼吸
		$w  = intval( ($value['l']*(100-$value['w'])/100)*2.5 );
		$ww = intval( ($value['l']*($value['w'])/100)*2.5 );
		if( 0 == $value['s'] && 101 != $value['w']) //白光
		{
			//Mode	W	WW
			$ret = pack('cCC',1,$w,$ww);
		}
		else //白光呼吸
		{
			if( 100 >= $value['w'] ) 
			{
				//Mode	W	WW	Cyc
				$ret = pack('cCCC',2,$w,$ww,intval($value['s']*10));
			}
			else //这个表示冷暖交替
			{
				//Mode	L	Cyc
				if( 0 == intval($value['s'])  )
				{
					$value['s'] = 3;
				}
				$ret = pack('cCC',3,$value['l'],intval($value['s']*10));
			}
		}
		return $ret;
	}

	//处理彩色照明
	private static function procColor(&$value)
	{
		//r   =  x /
		//g  =  x  //
		//b  =  x  //
		//w  =  x  //饱和度,0-100
		//s  =  x  //呼吸速度，单位为秒。0表示不呼吸
		self::RGB2HLS($h,$l,$s,$value['r'],$value['g'],$value['b']);
		if( 0 == $value['s'] ) //单色
		{
			//Mode	H	S	L	W
			$ret = pack('cnCCC',10, $h,$s,$l,$value['w']);
		}
		else
		{
			//Mode	H	S	L	W  Cyc
			$ret = pack('cnCCCC',11, $h,$s,$l,$value['w'],intval($value['s']*10));
		}
		return $ret;
	}

	//处理幻彩模式
	private static function procMode(&$value,$attrid)
	{
		//id  =  x  //模式的ID
		$id = $value['id'];
		$c = new TableSql('homeattr','ID');
		$modeList = $c->queryValue('ATTRSET','ID=?',array($attrid));
		$modeList = unserialize($modeList);
		if( NULL == $modeList )
		{
			return false;
		}
		foreach( $modeList as &$m)
		{
			if( $id != $m['id'] )
			{
				continue;
			}
			if( 0 == count($m['set']) )
			{
				return false;
			}
			$id += 50; //id从50开始
			//Mode	Count	[Fun	H	Cyc]
			//当H＝512时，表示当前颜色为白光
			//当H＝1024时，表示当前颜色随机
			//set  =  [  [r,g,b,f,s] , ......  ]
			//其中r 等于 -1，表示白光；r等于-2，表示随机颜色
			$ret = pack('cC',$id,count($m['set']));
			foreach( $m['set'] as $set )
			{
				$h = 0;
				switch( $set['r'] )
				{
					case -1: //白光
						$h = 512;
						break;
					case -2: //随机
						$h = 1024;
						break;
					case -3: //暖白
						$h = 2048;
						break;
					default:
						$h = $l = $s = NULL;
						self::RGB2HLS($h,$l,$s,$set['r'],$set['g'],$set['b']);
						break;
				}
				$info  = (intval($set['f'])<<12)|$h;
				$ret  .= pack('nC',$info,intval($set['s']*10));
			}
			return $ret;
			break;
		}
		return false;
	}
	
	//彩灯添加进来后，默认就设置几个模式
	private static function setDefaultMode($attrid)
	{
		//随机跳变、随机呼吸、红绿蓝渐变、随机闪烁
		$cmd = array();
		$cmd['m']    = -3;
		$cmd['id']   = -1;
		$cmd['name'] = '跳舞';
		$cmd['set'][0]['f'] = 1;
		$cmd['set'][0]['s'] = 0.2;
		$cmd['set'][0]['r'] = -2;
		self::setColorMode($cmd,$attrid);

		$cmd = array();
		$cmd['m']    = -3;
		$cmd['id']   = -1;
		$cmd['name'] = '彩色呼吸';
		$cmd['set'][0]['f'] = 2;
		$cmd['set'][0]['s'] = 3;
		$cmd['set'][0]['r'] = -2;
		self::setColorMode($cmd,$attrid);

		$cmd = array();
		$cmd['m']    = -3;
		$cmd['id']   = -1;
		$cmd['name'] = '彩色跳变';
		$cmd['set'][0]['f'] = 1;
		$cmd['set'][0]['s'] = 2;
		$cmd['set'][0]['r'] = -2;
		self::setColorMode($cmd,$attrid);

		$cmd = array();
		$cmd['m']    = -3;
		$cmd['id']   = -1;
		$cmd['name'] = '七彩变幻';
		$cmd['set'][0]['f'] = 3;
		$cmd['set'][0]['s'] = 2;
		$cmd['set'][0]['r'] = 255;
		$cmd['set'][0]['g'] = 0;
		$cmd['set'][0]['b'] = 0;
		$cmd['set'][1]['f'] = 3;
		$cmd['set'][1]['s'] = 2;
		$cmd['set'][1]['r'] = 0;
		$cmd['set'][1]['g'] = 255;
		$cmd['set'][1]['b'] = 0;
		$cmd['set'][2]['f'] = 3;
		$cmd['set'][2]['s'] = 2;
		$cmd['set'][2]['r'] = 0;
		$cmd['set'][2]['g'] = 0;
		$cmd['set'][2]['b'] = 255;
		self::setColorMode($cmd,$attrid);

		$cmd = array();
		$cmd['m']    = -3;
		$cmd['id']   = -1;
		$cmd['name'] = '七彩跳变';
		$cmd['set'][0]['f'] = 1;
		$cmd['set'][0]['s'] = 2;
		$cmd['set'][0]['r'] = 255;
		$cmd['set'][0]['g'] = 0;
		$cmd['set'][0]['b'] = 0;
		$cmd['set'][1]['f'] = 1;
		$cmd['set'][1]['s'] = 2;
		$cmd['set'][1]['r'] = 255;
		$cmd['set'][1]['g'] = 0x7f;
		$cmd['set'][1]['b'] = 0;
		$cmd['set'][2]['f'] = 1;
		$cmd['set'][2]['s'] = 2;
		$cmd['set'][2]['r'] = 255;
		$cmd['set'][2]['g'] = 255;
		$cmd['set'][2]['b'] = 0;
		$cmd['set'][3]['f'] = 1;
		$cmd['set'][3]['s'] = 2;
		$cmd['set'][3]['r'] = 0;
		$cmd['set'][3]['g'] = 255;
		$cmd['set'][3]['b'] = 0;
		$cmd['set'][4]['f'] = 1;
		$cmd['set'][4]['s'] = 2;
		$cmd['set'][4]['r'] = 0;
		$cmd['set'][4]['g'] = 255;
		$cmd['set'][4]['b'] = 255;
		$cmd['set'][5]['f'] = 1;
		$cmd['set'][5]['s'] = 2;
		$cmd['set'][5]['r'] = 0;
		$cmd['set'][5]['g'] = 0;
		$cmd['set'][5]['b'] = 255;
		$cmd['set'][6]['f'] = 1;
		$cmd['set'][6]['s'] = 2;
		$cmd['set'][6]['r'] = 0x8b;
		$cmd['set'][6]['g'] = 0;
		$cmd['set'][6]['b'] = 255;		
		self::setColorMode($cmd,$attrid);
	}

	static function addAttrNotice($attrid)
	{
		//设备组的设备ID是-2，设备组不会有附加信息过来，直接在这儿添加默认模式
		//如果是正常彩灯，等附加信息上来，报告了灯类型确认有彩灯时再添加
		$c = new TableSql('homeattr','ID');
		$devid = $c->queryValue('DEVID','ID=?',array($attrid));
		if( -2 == $devid )
		{
			self::setDefaultMode($attrid);
		}
	}

	/*************************************/
	//解析附加信息
	//uchar type 灯类型，表示1路，二路，三路，四路，五路
	static function parseAdditonInfo($value,$attrid)
	{
		$value = $value['info'];
		$type = 1;
		if( NULL != $value )
		{
			$type   = unpack('Ctype',$value);
			$type   = $type['type'];	
		}
		if( $type > 2 )
		{
			self::setDefaultMode($attrid);
		}
		return array('type'=>$type);
	}
	
	//把数据库信息转为前台显示信息
	static function getViewInfo($value,$id)
	{
		$a = unserialize($value);
		if( is_array($a) )
		{
			//彩灯在情景模式中，已经保存了完整的信息
			return $a;
		}
		//详细信息更新到SENDATTR中，返回的是一个可以作为联动设置的信息
		//这儿需要把SENDATTR的二进制数据进行解析
		$c = new TableSql('homeattr','ID');
		$value = $c->queryValue('SENDATTR','ID=?',array($id));
		return self::converToView($value);
	}

	//把数据库信息通过pack转发为下发控制命令信息
	static function getCMDInfo($value,$attrid=NULL)
	{
		$value = unserialize($value);
		
		if( isset($GLOBALS['devgrouprealattr']) )
		{
			$attrid = $GLOBALS['devgrouprealattr'];
		}

		$return = false;
		switch($value['m'])
		{
			case -4: //删除模式
				//value['id'] 待删除的模式ID
				self::delColorMode($value,$attrid);
				return false;
				break;
			//设置的转到colorMode文件中处理	
			//case -3: //设置模式
			//	//value['id'] 待修改的模式ID，为-1表示新增加
			//	//value['name']模式名称
			//	//value['set'] => array( array(r,g,b,f,s) )
			//					//其中r=-1，表示白光
			//					//r=-2,表示随机颜色
			//	self::setColorMode($value,$attrid);
			//	return false;
			//	break;
			case -2:
				//open  =  x //设置信息，0表示关闭时状态，1表示当前状态
				$return = pack('cc',-2,$value['s']);
				break;
			case -1: //关闭
			case 0:  //打开
			case 4:  //反转
				$return = pack('c',$value['m']);
				break;
			case 1: //照明
				$return = self::procLight($value);
				break;
			case 2: //彩色
				$return = self::procColor($value);
				break;
			case 3: 
			default: //幻彩
				$return = self::procMode($value,$attrid);
				break;
		}
		

		$c = new TableSql('homeattr','ID');
		$info = array();
		$info['SENDATTR'] = $return;
		$info['ID']       = $attrid;
		$c->update1($info);
		noticeAttrModi($attrid);

		return $return;
	}

	//把设备上报的状态信息转为数据库信息
	static function getStatusInfo($value,$attrid=NULL)
	{
		//$ret = self::converToView($value);
		$mode = unpack("cm",$value);
		$mode = $mode['m'];
		
		//详细信息更新到SENDATTR中，返回的是一个可以作为联动设置的信息
		$status = 1;
		switch($mode)
		{
			case -1:
				$status = 0;
				break;
			default:
				$status = 1;
				break;
		}
		
		//详细信息更新到SENDATTR中
		$c = new TableSql('homeattr','ID');
		$sendattr = $c->queryValue('SENDATTR','ID=?',array($attrid));
		if( $sendattr != $value )
		{
			$info = array();
			$info['SENDATTR'] = $value;
			$info['ID']       = $attrid;
			//这儿的更新数据无需保存到flash，所以最后一个参数置为false
			$c->update1($info);
			
			noticeAttrModi($attrid);
		}

		return $status;
	}


	static function getDetail($value,$id)
	{
		$c = new TableSql('homeattr','ID');
		$info = $c->query('DEVID,CFGINFO,ATTRSET','ID=?',array($id));
		if( -2 == $info['DEVID'] )
		{
			$cfg = array();
			$cfg['type'] = 5;
		}
		else
		{
			$cfg = unserialize($info['CFGINFO']);
		}

		//灯模式
		$cfg['mode'] = array();
		$modeList = unserialize($info['ATTRSET']);
		foreach( $modeList as &$m )
		{
			$cfg['mode'][$m['id']] = $m['name'];
		}
		
		return $cfg;
	}


	static function isDz($yuyin)
	{
		$attr = array();
		foreach($yuyin as &$ci)
		{
			$attr[] = $ci['attr'][0];
			if( in_array('dz',$ci['attr']) || in_array('color',$ci['attr']) )
			{
				return 1;				
			}
		}
		if( !in_array('dz',$attr) && !in_array('color',$attr) && in_array('fy',$attr) )
		{
			return -1;
		}
		return 0;		
	}


	//////////////////////语音控制相关////////////////////////////////////
	//语音识别辅助词典：动作dz，量词lc，其它qt
	static $sysDict = array(
			'fy' => array('有点','太','一点','一些'),
			'dz'=>array('开','关','熄灭','熄灯','灭灯','卵白','暖白','卵色','暖色','暖光','自然光','白','红','粉红','粉','橙','黄','绿','蓝','靛','青','紫','随机','随便','冷暖交替','亮度','最小','最大','最快','最慢','亮','暗','最亮','最暗','罪案','小夜灯','速度','色温','快','慢','冷','暖','变','换','开始呼吸','呼吸','停','停止','停止呼吸'),
	);
	static function getYuyinDict($id)
	{
		$ret = array();
		$c = new TableSql('homeattr','ID');
		$modes = $c->queryValue('ATTRSET','ID=?',array($id));
		$modes = unserialize($modes);
		if( NULL == $modes )
		{
			return $ret;
		}
		foreach($modes as &$mode)
		{
			if( NULL == $mode )
			{
				continue;
			}
			$ret[] = array('word'=>$mode['name'],'attr'=>'color');
		}
		return $ret;
	}

	//语音识别输入处理函数
	static function yuyin($yuyin,$attrid)
	{
		$c = new TableSql('homeattr','ID');
		$attrinfo = $c->query('DEVID,CFGINFO,ATTRSET','ID=?',array($attrid));
		$cfg = unserialize($attrinfo['CFGINFO']);

		$value 	 =  array();
		
		$ret  	 =  true;
		$sudu    =  0;//呼吸，停止
		$sewen 	 =  0;
		$liangdu =  0;
		$adjust  =  0;
		$huxi    =  0;
		$change  =  false;
		$light 	 =  false;
		$speed 	 =  false;
		$ct 	 =  false;	
		$session =  false;
		$dz 	 =  array();
		$number  =  array();
		$isDz = self::isDz($yuyin);
		if( $isDz == 0 )
		{
			switch( $_SESSION['dz'] ) 
			{			
				case '亮度':
					$light = true;
					$dz[] = 'light';
					$session =  true;
					break;				
				case '色温':
					$ct = true;
					$dz[] = 'ct';
					$session =  true;
					break;				
				case '速度':
					$speed = true;
					$dz[] = 'speed';
					$session =  true;
					break;
			}
		}	
		foreach($yuyin as &$word)
		{
			if( $cfg['type']=='1' || $cfg['type']=='2' ) 
			{
				$action=array('红','粉红','粉','橙','黄','绿','蓝','靛','青','紫','随机','随便','换');
				if( $cfg['type']=='1' )
				{
					$action=array_merge($action,array('卵白','暖白','卵色','暖色','暖光','自然光','冷暖交替','色温','冷','暖'));
				}
				if( in_array($word['word'],$action) )
				{
					$ret = false;
					$info = '当前设备不支持该操作';
					return array('ret'=>$ret,'info'=>$info);
				}
			}

			switch( $word['word'] )
			{
				case '关':
				case '熄灭':
				case '熄灯':
				case '灭灯':
					$value['m'] = -1;
					$info = '关闭。';
					unset($_SESSION['dz']);
					break;
				case '开':
					$value['m'] = 0;
					$info = '打开。';
					break;
				case '红':
					$value['m'] = 2;
					$value['r'] = 255;
					$value['g'] = 0;
					$value['b'] = 0;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '红色。';
					break;
				case '粉':
				case '粉红':
					$value['m'] = 2;
					$value['r'] = 255;
					$value['g'] = 140;
					$value['b'] = 162;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = $word['word'].'色。';
					break;				
				case '橙':
					$value['m'] = 2;
					$value['r'] = 255;
					$value['g'] = 125;
					$value['b'] = 0;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '橙色。';
					break;
				case '黄':
					$value['m'] = 2;
					$value['r'] = 255;
					$value['g'] = 255;
					$value['b'] = 0;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '黄色。';
					break;
				case '绿':
					$value['m'] = 2;
					$value['r'] = 0;
					$value['g'] = 255;
					$value['b'] = 0;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '绿色。';
					break;
				case '蓝':
					$value['m'] = 2;
					$value['r'] = 0;
					$value['g'] = 0;
					$value['b'] = 255;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '蓝色。';
					break;
				case '靛':
				case '青':
					$value['m'] = 2;
					$value['r'] = 0;
					$value['g'] = 255;
					$value['b'] = 255;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = $word['word'].'色。';
					break;
				case '紫':
					$value['m'] = 2;
					$value['r'] = 255;
					$value['g'] = 0;
					$value['b'] = 255;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '紫色。';
					break;
				case '随机':
				case '随便':
					$value['m'] = 2;
					$value['r'] = $value['g'] = $value['b'] =0;
					$h = mt_rand(0,360);
					self::HLS2RGB( $value['r'], $value['g'], $value['b'], $h, 127, 254);
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '随机颜色。';
					break;
				case '白':
					$value['m'] = 1;
					$value['l'] = 100;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '白光。';
					break;
				case '自然光':
					$value['m'] = 1;
					$value['l'] = 100;
					$value['w'] = 50;
					$value['s'] = 0;
					$info = '自然光。';
					break;
				case '卵白':
				case '暖白':
				case '卵色':
				case '暖色':
				case '暖光':
					$value['m'] = 1;
					$value['l'] = 100;
					$value['w'] = 100;
					$value['s'] = 0;
					$info = ($word['word']=='卵白' ? '暖白' : $word['word']).'。';
					break;
				case '冷暖交替':
					$value['m'] = 1;
					$value['l'] = 100;
					$value['w'] = 101;
					$value['s'] = 3;
					$info = '冷暖交替。';
					break;
				case '亮':
					$liangdu = 1;
					$_SESSION['dz'] = '亮度';							
					break;
				case '暗':
					$liangdu = -1;
					$_SESSION['dz'] = '亮度';							
					break;
				case '暖':
					$sewen = 1;
					$_SESSION['dz'] = '色温';							
					break;
				case '冷':
					$sewen = -1;
					$_SESSION['dz'] = '色温';							
					break;
				case '快':
					$sudu = -1;
					$_SESSION['dz'] = '速度';							
					break;
				case '慢':	
					$sudu = 1;
					$_SESSION['dz'] = '速度';							
					break;
				case '一点':
				case '一些':
					$adjust =  1;
					break;				
				case '太':
					$adjust = -2;
					break;					
				case '有点':
					$adjust = -1;
					break;
				case '呼吸':
				case '开始呼吸':
					$huxi =  1;
					$_SESSION['dz'] = '速度';							
					break;
				case '停':
				case '停止':
				case '停止呼吸':
					$huxi = -1;
					break;
				case '变':
				case '换':
					$change = true;
					break;
				case '小夜灯':
					$value['m'] = 1;
					$value['l'] = 3;
					$value['w'] = 0;
					$value['s'] = 0;
					$info = '小夜灯。';
					break;				
				case '最大':
				case '最小':
					while( sizeof($dz)-sizeof($number)!=1 )  $number[] = NULL;
					switch( end($dz) ) 
					{			
						case 'light':
							$number[] = $word['word']=='最大' ?  1 : 0.05;
							$_SESSION['dz'] = '亮度';
							break;		
						case 'speed':
							$number[] = $word['word']=='最大' ?  0.1 : 10;
							$_SESSION['dz'] = '速度';	
							break;
					}	
					break;
				case '最亮':
					$light = true;
					if( !in_array('light',$dz) ) $dz[] = 'light';
					while( sizeof($dz)-sizeof($number)!=1 )  $number[] = NULL;
					$number[] = 1;
					$_SESSION['dz'] = '亮度';					
					break;				
				case '最暗':
				case '罪案':
					$light = true;
					if( !in_array('light',$dz) ) $dz[] = 'light';
					while( sizeof($dz)-sizeof($number)!=1 )  $number[] = NULL;
					$number[] = 0.05;
					$_SESSION['dz'] = '亮度';
					break;								
				case '最快':
					$speed = true;
					if( !in_array('speed',$dz) ) $dz[] = 'speed';
					while( sizeof($dz)-sizeof($number)!=1 )  $number[] = NULL;
					$number[] = 0.1;
					$_SESSION['dz'] = '速度';					
					break;				
				case '最慢':
					$speed = true;
					if( !in_array('speed',$dz) ) $dz[] = 'speed';
					while( sizeof($dz)-sizeof($number)!=1 )  $number[] = NULL;
					$number[] = 10;
					$_SESSION['dz'] = '速度';					
					break;				
				case '亮度':
					$light = true;
					$dz[] = 'light';
					$_SESSION['dz'] = '亮度';
					break;				
				case '色温':
					$ct = true;
					$dz[] = 'ct';
					$_SESSION['dz'] = '色温';
					break;				
				case '速度':
					$speed = true;
					$dz[] = 'speed';
					$_SESSION['dz'] = '速度';
					break;								
				default:
					if( !in_array('sz',$word['attr'])  && !in_array('color',$word['attr']) )
					{
						break;
					}													
					$num = $word['word'];
					if( in_array('sz',$word['attr']) )
					{
						if( $speed && array_flip($dz)['speed'] == sizeof($dz)-1 )
						{
							$num = $num > 10 ? 10 : $num;
							$number[] = $num;
							break;
						}
						$num = $num >= 1 ? ( $num > 100 ? 1 : $num/100 ) : $num;
						if( sizeof($dz) - sizeof($number) != 1)
						{
							$number[] = NULL;
						}
						$number[] = $num;
						break;
					}					
					$find = false;
					foreach( $word['attr'] as &$wattr )
					{
						if( 'color' == $wattr )
						{
							$find = true;
							break;
						}
					}
					if(!$find)
					{
						continue;
					}

					$value['m'] = 3;

					//灯模式
					$cfg['mode'] = array();
					$modeList = unserialize($attrinfo['ATTRSET']);
					foreach( $modeList as &$m )
					{
						if($m['name'] == $word['word'])
						{
							$value['id'] = $m['id'];
							$info = $m['name'];
							break;
						}
					}
					break;
			}
		}

		if( sizeof($dz) - sizeof($number) == 1)
		{
			if( $session )
			{
				$number[] = 'null';							
			}
			else
			{
				$number[] = NULL;				
			}
		}

		$ddz = array_combine($dz,$number);

		if( 0 != $sewen || 0 != $sudu || 0 != $liangdu || 0 != $huxi || $light || $ct || $speed || $change ) //需要在原来颜色上更新
		{	
			if( $value == array() )
			{
				$value = self::getViewInfo(NULL,$attrid);		
			}
			if( NULL == $value || (-1 == $value['m']) )
			{
				$value = array();
				$value['m'] = 1;
				$value['l'] = 100;
				$value['w'] = 0;
				$value['s'] = 0;
			}
		}
		if( 0 != $sudu )
		{
			$sudu *= $adjust;
			if( 0 != $value['s'] )
			{
				$value['s'] += $sudu;
				if( 0 >= $value['s'] ) $value['s'] = 0.5;
				if( 10 < $value['s'] ) $value['s'] = 10;
				$info .= "当前变化周期:$value[s]秒。";			
			}
			else
			{
				$ret = false;
				$info = '请先设置彩灯处于呼吸状态！';				
			}
			if( $value['m'] == 3 )
			{
				$value['m'] = 1;
				$value['l'] = 100;
				$value['w'] = 100;
				$value['s'] = 0;
				$info = '变成暖色';
			}			
		}
		if( 0 != $liangdu )
		{
			if( 1 == $value['m'] )
			{
				if( $adjust !=  0 )
				{
					$liangdu = $adjust*$value['l']*0.3*$liangdu;
					$value['l'] += $liangdu;
				}
				if( $value['l'] > 100)  $value['l'] = 100;
				if( $value['l'] <  1)   $value['l'] = 1;				
			}
			else
			{
				$h = $l = $s = NULL;
				self::RGB2HLS($h,$l,$s,$value['r'],$value['g'],$value['b']);
				if(  $adjust !=  0  )
				{
					$liangdu = ($adjust*0.3)*$liangdu*$l;
					$l += $liangdu;					
				}
				if( $l >= 127) $l = 127;
				if( $l <  0)   $l = 0;
				self::HLS2RGB($value['r'],$value['g'],$value['b'],$h,$l,$s);
			}
			$ld = isset($value['l']) ? $value['l'] : intval($l*100/127);
			if( $liangdu > 0 )
			{
				$info = '增加亮度，当前亮度为：'.$ld;
			}
			else
			{
				$info = '降低亮度，当前亮度为：'.$ld;
			}
		}
		if( 0 != $sewen )
		{
			if( $value['m'] != 1 )
			{
				$value['m'] = 1;
				$value['l'] = 100;
				$value['w'] = 100;
				$value['s'] = 0;
				$info = '变成暖色';
			}
			else
			{
				$sewen = $adjust*$sewen*$value['w']*0.2;
				$value['w'] += $sewen;
				$info .= "当前色温:$value[w]。";
			}				
		}
		if( $light && $adjust == 0 )
		{
			$num = $ddz['light'];
			if( $value['m'] == 3 )
			{
				$ret = false;
				$info = '炫彩模式下不能调整亮度，你可以说关键字“换”或者“变”来改变当前炫彩模式';
			}				
			elseif( $num === NULL )
			{
				$info = '请语音输入亮度！';
			}
			elseif( $num === 'null' )
			{
				$ret = false;
				$info = YUYIN_OP_CMDFAIL.'<br>你可以直接输入亮度值（1-100），或者其他动作！';
			}
			else
			{
				if( $value['m'] == 2 )
				{
					$h = $l = $s = NULL;
					self::RGB2HLS($h,$l,$s,$value['r'],$value['g'],$value['b']);
					$l = 127*$num;
					self::HLS2RGB($value['r'],$value['g'],$value['b'],$h,$l,$s);	
				}
				elseif( $value['m'] == 1 )
				{
					$value['l'] = $num*100;
				}
				$ld = $num*100;
				$info .= "当前亮度:".$ld."。";				
			}
	
		}
		if( $ct && $adjust == 0 )
		{
			$num = $ddz['ct'];
			if( $value['m'] == 2 || $value['m'] == 3 )
			{
				$value['m'] = 1;
				$value['l'] = 100;
				$value['w'] = 100;
				$value['s'] = 0;
				$info = '变成暖色';
			}
			elseif( $num === NULL )
			{
				$info = '请语音输入色温值（0~100）！';
			}
			elseif( $num === 'null' )
			{
				$ret = false;
				$info = YUYIN_OP_CMDFAIL.'<br>你可以直接输入色温值（1-100），或者其他动作！';
			}			
			elseif( $value['m'] == 1 )
			{
				$value['w'] = $num*100;
				$info .= "当前色温:$value[w]。";
			}			

		}
		if( $speed && $adjust == 0 )
		{
			$num = $ddz['speed'];
			if( $value['m'] == 3 )
			{
				$ret = false;
				$info = '炫彩模式下不能调整速度，你可以说关键字“换”或者“变”来改变当前炫彩模式';
			}			
			elseif( $num === NULL )
			{
				$info = '请语音输入呼吸速度（0~10）！';
			}
			elseif( $num === 'null' )
			{
				$ret = false;
				$info = YUYIN_OP_CMDFAIL.'<br>你可以直接输入速度值（1-10），支持小数，或者其他动作！';
			}			
			else
			{
				$value['s'] = $num;
				$info .= "当前变化周期:$value[s]秒。";					
			}
		
		}		
		if( $change && $value['m'] == 3 )
		{
			$modeList = unserialize($attrinfo['ATTRSET']);
			if( sizeof($modeList) == 1 )
			{
				$ret = false;
				$info = '亲爱的，不好意思，当前只一种幻彩模式，无法进行变换！';						
			}
			else
			{
				$modename = array();
				$modeid = array();
				foreach( $modeList as &$m )
				{
					$modename[] = $m['name'];
					$modeid[] = $m['id'];
				}
				unset($modeid[array_search($value['id'], $modeid)]);
				if( sizeof($modeid) == 1 )
				{
					$value['id'] = array_values($modeid)[0];
				}
				else
				{
					$value['id'] = $modeid[array_rand($modeid)];
				}
				$name = $modename[array_search($value['id'], $modeid)];
				$info = '幻彩模式切换成功，当前模式为：'.$name;
			}
		}
		if( 0 != $huxi )
		{
			if( $value['m'] == 3 )
			{
				$ret = false;
				$info = '炫彩模式下不能调整速度，你可以说关键字“换”或者“变”来改变当前炫彩模式';
			}
			else 
			{
				if( ( 1 == $huxi ) && ( 0 == $value['s'] ) ) 
				{
					$info = '开始呼吸。';
					$value['s'] = 3;
				}
				if( ( -1 == $huxi ) && ( 0 != $value['s'] ) ) 
				{
					$info = '停止呼吸。';
					$value['s'] = 0;
				}				
			}	
		}
		
		if( $value )
		{
			$GLOBALS['dstpSoap']->setModule('devattr','attr');
			$GLOBALS['dstpSoap']->execAttr($attrid,$value);
		}
		elseif( $isDz == -1 )
		{
			$ret = false;
			$info = YUYIN_OP_CMDFAIL.'<br>彩灯的使用说明：<br>改变色温:太暖(冷),有点暖(冷),暖(冷)一点<br>改变速度:太快(慢),有点快(慢),快(慢)一点<br>改变亮度:太亮(暗),有点亮(暗),亮(暗)一点';			
		}
		else
		{
			$ret = false;
			$info = YUYIN_OP_CMDFAIL.'  彩灯的使用说明：'.'<br>彩灯 开<br>彩灯 红<br>彩灯 最暗<br>彩灯 有点亮<br>彩灯 亮度 50<br>彩灯 色温 50<br>彩灯 速度 0.1';
		}
		return array('ret'=>$ret,'info'=>$info);
	}	
}

 

?>