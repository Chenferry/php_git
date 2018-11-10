<?php
	setcookie("name","cxb",time()+3600);
	echo $_COOKIE["name"];
	if(isset($_COOKIE["name"]))
	{
		echo $_COOKIE["name"];
	}
	else
	{
		echo "there is no cookie";
	}
