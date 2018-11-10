<?php
include_once('../../a/config/dstpCommonInclude.php');  

function setColorMode($attrid,$value)
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
	
	//最多只能设置8个颜色
	$modeList[$curid]['set'] = array_slice($modeList[$curid]['set'], 0, 8);

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

function getColorModeValue($attrid,$mid)
{
	$value = array();
	$c = new TableSql('homeattr','ID');
	$modeList = $c->queryValue('ATTRSET','ID=?',array($attrid));
	$modeList = unserialize($modeList);
	foreach($modeList as &$m)
	{
		if( $mid == $m['id'] )
		{
			$value = $m;
			break;
		}
	}	
	return $value;
}
util::startSajax( array('setColorMode','getColorModeValue'));

?>