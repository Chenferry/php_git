<?php
namespace app\index\controller;
use think\Controller;
use think\Request;
use app\index\model\School;
class TeacherController extends Controller
{
	public function index()
	{
		$Teacher =new School();
		$teachers = $Teacher->select();
		//var_dump($teachers);
		$this->assign('result',$teachers);
		$htmls = $this->fetch();
		return $htmls;
	}
	public function add()
	{
		$htmls = $this ->fetch();
		return $htmls;
	}
	public function insert()
	{
		//var_dump($_POST);
		//下方不知为何拿不到post数据
		//$postData = Request::instance()->post();
		$postData = Request::instance()->get();
		//$postData = $this ->request->post();	
		var_dump($postData);
	}
}
