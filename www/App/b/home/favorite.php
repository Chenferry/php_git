<?php
//情景模式设置
include_once('../../a/config/dstpCommonInclude.php');  
include_once('b/homeLang.php');

function saveFavorite($favoriteList)
{
	$cachename = 'favorite_'.$GLOBALS['curUserID'];
	Cache::del($cachename);
	
	statusNotice('status');

	$c 	= new TableSql('homefavorite');
	$c->del('USERID=?',array($GLOBALS['curUserID']));
	if( NULL == $favoriteList )
	{
		return true;
	}
	
	$c    = new TableSql('homeattr','ID');
	foreach( $favoriteList as &$f )
	{
		if( 0 == $f['type'] )
		{
			$f['ICON'] = $c->queryValue('ICON','ID=?',array($f['id']));
		}
	}

	$info = array();
	$info['USERID'] = $GLOBALS['curUserID'];
	$info['FAVORITE'] = serialize($favoriteList);
	$c 	= new TableSql('homefavorite');
	$c->add($info);
	
	$GLOBALS['dstpSoap']->setModule('setting','setting');
	$favoriteList = $GLOBALS['dstpSoap']->setFavoriteName($favoriteList);
	Cache::set($cachename,$favoriteList);

	return true;
}
util::startSajax( array('saveFavorite'));



?>