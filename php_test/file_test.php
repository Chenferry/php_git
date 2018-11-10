<?php
	echo readfile("./func_test.php");
	echo "\r\n";
	$mfile=fopen("./func_test.php","a");
	$content="\r\n"."#this is add by".$_SERVER["PHP_SELF"]."!";
	//fwrite($mfile, $content);
	fclose($mfile);