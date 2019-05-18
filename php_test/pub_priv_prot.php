<?php
	class test
	{
		public static $a="aa";
		private static $b="bb";
		protected static $c="cc";
		public static function geta()
		{
			return self::$a.self::getb().self::getc();
		}
		private function getb()
		{
			return self::$b;
		}
		protected function getc()
		{
			return self::$c;
		}
	}
	class testson extends test
	{
		public function getc()
		{
			return parent::getc()."dd";
		}
		public function getason()//不能是geta(),继承
		{
			return "aa"."dd";
		}		
	}
	echo test::geta();
	$a=new test();
	echo "\r\n";
	echo $a->geta();
	echo "\r\n";
	$b=new testson();
	echo $b->getc();
	echo "\r\n";
	echo $b->geta();
	echo "\r\n";
	echo $b->getason();