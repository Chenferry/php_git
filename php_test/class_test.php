<?php
	class people
	{
		public $nn;
		function __construct($name)
		{
			echo "i am people \r\n";
			$this->nn = $name;
		}
		function __destruct()
		{
			echo "销毁".$this->nn."\r\n";
		}
	}

	class student extends people
	{
		function __construct()
		{
			parent::__construct();
			echo "i am student\r\n";
		}
		public $class;
		private $score;
	}

	$man = new people("cxb");
	$stu = new student();