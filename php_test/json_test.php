<?php
	$json='{
		"name":"cxb",
		"year":26,
		"birth":"1018",
		"school":"hbmy"
	}';
	var_dump(json_decode($json));
	var_dump(json_decode($json, true));

	$a=array(
		"cc"=>"name",
		"1018"=>"birth",
		"year"=>26,
		"school"=>"hbmy"
	);
	var_dump(json_encode($a));