<?php
	namespace test;
	include_once "func_test.php";
	$test_array=array("name" =>"cxb",
		"year" => "25",
		"birth" => "19931018",
		"home" => "湖北",
		'other' => array(
			'math' => '111',
			'chem' => '121',
			'eng' => '113'
		)
		);
		var_dump($test_array);
		echo "\r\n";
		echo $test_array['name'];
		echo $test_array['other']['math'];
		foreach ($test_array as $key => $value) {
			echo "$key";
			echo " ";
		}
		echo "\r\n";
		$GLOBAL['name']='cxxxxxxb';
		echo $GLOBAL['name'];
		echo $_SERVER["PHP_SELF"];

