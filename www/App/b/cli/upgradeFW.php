<?php

@ini_set('max_execution_time', 0 );
@ignore_user_abort(true);
	
include_once('/www/App/a/config/dstpCommonInfo.php');

//检测是否已经在升级中
$upgradeing = Cache::get('startupgradeing');
if( false != $upgradeing )
{
	exit();
}
Cache::set('startupgradeing','startupgradeing',60*10);

//开始下载升级程序和md5
$GLOBALS['dstpSoap']->setModule('local','upgrade');
$ver = $GLOBALS['dstpSoap']->getHICVersion();
$fw  = $ver['fw'];
$hic = $ver['hic'];

$fwfile  = "http://upgrade.jia.mn/$fw/firmware/$hic/fwhic";
$md5file = "http://upgrade.jia.mn/$fw/firmware/$hic/fwmd5";

//进入/tmp目录下，获取hic和md5文件
`cd /tmp && rm -rf fwmd5`;
`cd /tmp && rm -rf fwhic`;
`cd /tmp && wget -O fwmd5 $md5file &&  wget -O fwhic $fwfile `;
$md5info = file_get_contents('/tmp/fwmd5');
if( NULL == $md5info )
{
	exit();
}
$md5file = md5_file('/tmp/fwhic');
if( strtoupper(trim($md5info)) != strtoupper(trim($md5file)) )
{
	exit();
}

//开始升级
`cd /tmp && sysupgrade fwhic`;

?>