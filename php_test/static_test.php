<?php
	class test
	{
		public static $data = array('name'=> 'cxb');
		public $name = '';
		public static function get_data()
		{
			return self::$data;
		}
		public  function get_name()
		{
			return $this->name;
		}
	}

	$obj = new test;
	echo implode(test::get_data());
	echo $obj->get_name();