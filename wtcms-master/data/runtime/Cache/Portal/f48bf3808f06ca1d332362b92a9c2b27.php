<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html lang="en">
<head>
	<title>跳转提示</title>
	<?php  function _sp_helloworld(){ echo "hello!"; } function _sp_helloworld2(){ echo "hello2!"; } function _sp_helloworld3(){ echo "hello3!"; } function _sp_sql_keywords_bypostcatid($explodeChar,$topCount){ $catIDS=array(); $terms=M("Terms")->field("term_id")->where("status=1")->order('term_id asc')->select(); foreach($terms as $item){ $catIDS[]=$item['term_id']; } if(!empty($catIDS)){ $catIDS=implode(",", $catIDS); $catIDS="cid:$catIDS;"; $catIDS="$catIDS;limit:1000;"; } else{ $catIDS=""; } $posts= sp_sql_posts($catIDS); $keywords=array(); foreach($posts as $post) { $tags=explode($explodeChar,$post['post_keywords']); $tagarrlength=count($tags); $keywordsarrlength=count($keywords); for($x=0 ; $x < $tagarrlength ;$x++ ) { $haskeywords=false; foreach($keywords as $w=>$w_value) { if($tags[$x]==$w) { $haskeywords=true; $keywords[$w]=$w_value+1; break; } } if($haskeywords==false and trim($tags[$x])!="") { $keywords[trim($tags[$x])]=1; } } } arsort($keywords); $keywordsarrlength=count($keywords); if($keywordsarrlength > $topCount){ $keywords=array_slice($keywords,0,$topCount); } ksort($keywords); return array($keywords); } ?>
<?php $portal_index_recommend_article="1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20"; $portal_hot_articles="1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20"; $portal_last_post="1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20"; $portal_news = "1"; $portal_notice = "2"; $portal_academic = "3"; $tmpl=sp_get_theme_path(); $default_home_slides=array( array( "slide_name"=>"slide1", "slide_pic"=>$tmpl."Public/assets/images/home_slide/slide1.jpg", "slide_url"=>"", ), array( "slide_name"=>"slide2", "slide_pic"=>$tmpl."Public/assets/images/home_slide/slide2.jpg", "slide_url"=>"", ), array( "slide_name"=>"slide3", "slide_pic"=>$tmpl."Public/assets/images/home_slide/slide3.jpg", "slide_url"=>"", ), ); ?>

<meta name="author" content="taosir">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<!-- Set render engine for 360 browser -->
<meta name="renderer" content="webkit">
<!-- No Baidu Siteapp-->
<meta http-equiv="Cache-Control" content="no-siteapp"/>
<!-- HTML5 shim for IE8 support of HTML5 elements -->
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
<![endif]-->
<link rel="icon" href="/php_git/wtcms-master/themes/default/Public/assets/images/hust.ico" type="image/x-icon">
<link rel="shortcut icon" href="/php_git/wtcms-master/themes/default/Public/assets/images/favicon.png" type="image/x-icon">
<link href="/php_git/wtcms-master/themes/default/Public/assets/userTheme/theme.min.css" rel="stylesheet">
<link href="/php_git/wtcms-master/themes/default/Public/assets/userTheme/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
<link href="/php_git/wtcms-master/themes/default/Public/assets/userTheme/font-awesome/4.4.0/css/font-awesome.min.css" rel="stylesheet" type="text/css">
<!--[if IE 7]>
<link rel="stylesheet" href="/php_git/wtcms-master/themes/default/Public/assets/userTheme/font-awesome/4.4.0/css/font-awesome-ie7.min.css">
<![endif]-->
<link href="/php_git/wtcms-master/themes/default/Public/assets/css/style.min.css" rel="stylesheet">

	<style type="text/css">
		*{ padding: 0; margin: 0; }
		body{ background: #fff; font-family: '微软雅黑'; color: #333; font-size: 16px; }
		.system-message{ padding: 24px 48px;text-align: center; }
		.system-message h1{ font-size: 100px; font-weight: normal; line-height: 120px; margin-bottom: 12px; text-align: center;}
		.system-message .jump{ padding-top: 10px}
		.system-message .success,.system-message .error{ line-height: 1.8em; font-size: 36px }
		.system-message .detail{ font-size: 12px; line-height: 20px; margin-top: 12px; display:none}
	</style>
</head>
<body class="body-white">
<?php echo hook('body_start');?>
<div class="container">
    <div class="header">
        <img alt="信息存储系统教育部重点实验室" src="/php_git/wtcms-master/themes/default/Public/assets/images/banner.png" width="100%"/>
    </div>
    <div class="navbar">
        <div class="navbar-inner">
            <div class="container">
                <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </a>
                <div class="nav-collapse collapse" id="main-menu" >
                    <?php
 $effected_id="main-menu"; $filetpl="<a href='\$href' target='\$target'>\$label</a>"; $foldertpl="<a href='\$href' target='\$target' class='dropdown-toggle' data-toggle='dropdown'>\$label<b class='caret'></b></a>"; $ul_class="dropdown-menu" ; $li_class="li-class"; $style="nav"; $showlevel=6; $dropdown='dropdown'; echo sp_get_menu("main",$effected_id,$filetpl,$foldertpl,$ul_class,$li_class,$style,$showlevel,$dropdown); ?>
                    <div class="pull-right">
                        <form method="post" class="form-inline" action="<?php echo U('portal/search/index');?>">
                            <input type="text" id="search-field" placeholder="输入关键字查询" name="keyword" value="<?php echo I('get.keyword');?>"/>
                            <input type="submit" id="search-btn" class="btn btn-primary" value="搜索"/>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="system-message">
	<?php if(isset($message)): ?><h1>^_^</h1>
		<p class="success">
			<?php echo($message); ?>
		</p>
		<?php else: ?>
		<h1>&gt;_&lt;</h1>
		<p class="error">
			<?php echo($error); ?>
		</p><?php endif; ?>
	<p class="detail">
	</p>
	<p class="jump">
		页面自动
		<a id="href" href="<?php echo($jumpUrl); ?>">跳转</a> 等待时间： <b id="wait"><?php echo($waitSecond); ?>
	</b>
	</p>
</div>
<script type="text/javascript">
	(function(){
		var wait = document.getElementById('wait'),href = document.getElementById('href').href;
		var interval = setInterval(function(){
			var time = --wait.innerHTML;
			if(time <= 0) {
				location.href = href;
				clearInterval(interval);
			};
		}, 1000);
	})();
</script>
</body>
</html>