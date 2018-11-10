<?php
	include_once('../../a/config/dstpCommonInfo.php');  
	
	function logout()
	{
		//清除自动登陆标记，同时数据表中删除相关数据
		//这儿不清除，留着系统维护任务定时清理长期不用的即可
		//if( isset($_COOKIE['hicautologin']) )
		//{
		//	$c = new TableSql('hic_frameautologin');
		//	$c->del('LOGINFLAG=?',array( $_COOKIE['hicautologin'] ))
		//}
		
		//直接置空，在chrome中无法删除。写的时候chrome好像自动设置为.a_jia_sx，删除却是删a_jia_sx
		//所以这儿需要和写cookie时一样的处理
		//setcookie('hicautologin');
		setcookie('hicautologin','',time(),'/',a_jia_sx);

		
		//$loginPage = NULL;
		//if ( isset($_SESSION['loginPage'])) 
		//{
		//	$loginPage = $_SESSION['loginPage'];
		//}

		session_unset();
		session_destroy();
		return true;
	}

	util::startSajax( array('logout'));
?>
