<?php
	set_time_limit(0);
	$res = mkdir(iconv('utf-8', 'gbk', 'E:/workbase/php_mkdir'));
	$cc='cxb';
	if($res)
	{
		echo 'make dir is ok $cc\r\n'.\r\n;
	}
	else
	{
		echo 'make dir fail $cc\r\n';
	}