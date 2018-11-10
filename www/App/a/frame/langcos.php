<?php
include_once('../../a/config/dstpCommonInfo.php');  

function setLang($lang)
{
	if( NULL == $lang || 'sys'==$lang )
	{
		header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
		setcookie('lang','',time(),'/',a_jia_sx);
		return true;
	}
    header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
    setcookie('lang',$lang,time()+86400*3650,'/',a_jia_sx);
	return true;
}

util::startSajax( array('setLang'));
	
?>