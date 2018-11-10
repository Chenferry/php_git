<?php
namespace app\index\controller;
use think\Db;
use think\Controller;
class Index extends Controller
{
    public function index()
    {
		var_dump(Db::name("school")->find());
		$this->assign('data', Db::name("school")->find());
		$htmls = $this->fetch();
		return $htmls;
	}
}
