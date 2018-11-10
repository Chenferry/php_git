<?php
namespace app\index\controller;
use think\Db;
use think\Controller;
class Teacher extends Controller
{
    public function index()
    {
		$data=Db::name("school")->select();
		//var_dump($data);
		$result = Db::query('insert into school (name, year, class) values ("cxb", "19931011", "12")');
		//var_dump($result);
		//echo '</br>';
		$result = Db::query('update school set name = "cb" where class = 25 ');
		//var_dump($result);
		//echo '</br>';
		// 查询数据
		$result = Db::query('select * from school');
		//dump($result);
		$this->assign('result', $result);
		$htmls = $this->fetch();
		return $htmls;
	}
	public static function insert()
	{
		var_dump($_POST);
		$res = Db::name("school")->insert($_POST);
		if($res)
		{
			echo '<script>alert("add ok!")</script>';
		}
		else
		{
			echo '<script>alert("add fail!")</script>';
		}
	}
	public function add()
	{
		$htmls = $this->fetch();
		return $htmls;
	}
}
