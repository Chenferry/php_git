<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
<head>
	<title><?php echo ($site_seo_title); ?> | <?php echo ($site_name); ?></title>
	<meta name="keywords" content="<?php echo ($site_seo_keywords); ?>" />
	<meta name="description" content="<?php echo ($site_seo_description); ?>">
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

	<link href="/php_git/wtcms-master/themes/default/Public/assets/css/slippry/slippry.css" rel="stylesheet">
	<style type="text/css">
		.caption-wraper{position: absolute;left:50%;bottom:2em;}
		.caption-wraper .caption{
			position: relative;left:-50%;
			background-color: rgba(0, 0, 0, 0.54);
			padding: 0.4em 1em;
			color:#fff;
			-webkit-border-radius: 1.2em;
			-moz-border-radius: 1.2em;
			-ms-border-radius: 1.2em;
			-o-border-radius: 1.2em;
			border-radius: 1.2em;
		}
		@media (max-width: 767px){
			.sy-box{margin: 12px -20px 0 -20px;}
			.caption-wraper{left:0;bottom: 0.4em;}
			.caption-wraper .caption{
				left: 0;
				padding: 0.2em 0.4em;
				font-size: 0.92em;
				-webkit-border-radius: 0;
				-moz-border-radius: 0;
				-ms-border-radius: 0;
				-o-border-radius: 0;
				border-radius: 0;}
		}
	</style>
</head>
<body>

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


<div class="container" style="background-color:#fff;">
	<div class="row">
		<div class="span8">
			<div class="tc-box  article-box">
				<?php $home_slides=sp_getslide("home_slide"); $home_slides=empty($home_slides)?$default_home_slides:$home_slides; ?>
				<ul id="homeslider" class="unstyled" >
					<?php if(is_array($home_slides)): foreach($home_slides as $key=>$vo): ?><li>
							<div class="caption-wraper">
								<div class="caption"><?php echo ($vo["slide_name"]); ?></div>
							</div>
							<a href="<?php echo ($vo["slide_url"]); ?>"><img src="<?php echo sp_get_asset_upload_path($vo['slide_pic']);?>" alt="<?php echo ($vo["slide_name"]); ?>"></a>
						</li><?php endforeach; endif; ?>
				</ul>
			</div>
			<div class="tc-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>新闻消息 </h2>
					<span class="sub-header">News</span>
					<a href="/index.php?m=list&a=index&id=1"><i class="fa fa-hand-o-right" title="查看更多"></i></a>
				</div>
				<div class="ranking">
					<?php $hot_articles=sp_sql_posts("cid:$portal_news;field:post_title,post_excerpt,object_id,smeta,term_id;order:post_date desc;limit:3;"); ?>
					<ul class="unstyled">
						<?php if(is_array($hot_articles)): foreach($hot_articles as $key=>$vo): $top=$key<3?"top3":""; ?>
							<li class="<?php echo ($top); ?>"><i><?php echo ($key+1); ?></i><a title="<?php echo ($vo["post_title"]); ?>" href="<?php echo leuu('article/index',array('id'=>$vo['object_id'],'cid'=>$vo['term_id']));?>"><?php echo ($vo["post_title"]); ?></a></li><?php endforeach; endif; ?>
					</ul>
				</div>
			</div>
			<div class="tc-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>通知公告 </h2>
					<span class="sub-header">Notice</span>
					<a href="/index.php?m=list&a=index&id=2"><i class="fa fa-hand-o-right" title="查看更多"></i></a>
				</div>
				<div class="ranking">
					<?php $hot_articles=sp_sql_posts("cid:$portal_notice;field:post_title,post_excerpt,object_id,smeta,term_id;order:post_date desc;limit:3;"); ?>
					<ul class="unstyled">
						<?php if(is_array($hot_articles)): foreach($hot_articles as $key=>$vo): $top=$key<3?"top3":""; ?>
							<li class="<?php echo ($top); ?>"><i><?php echo ($key+1); ?></i><a title="<?php echo ($vo["post_title"]); ?>" href="<?php echo leuu('article/index',array('id'=>$vo['object_id'],'cid'=>$vo['term_id']));?>"><?php echo ($vo["post_title"]); ?></a></li><?php endforeach; endif; ?>
					</ul>
				</div>
			</div>

		</div>
		<div class="span4">
			<div class="tc-box first-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>公司简介</h2>
					<span class="sub-header">Introduction</span>
				</div>
				<div class="ranking" style="text-indent:1.0em;padding:4px;font-weight:400">
					PHP是一种通用开源脚本语言。语法吸收了C语言、Java和Perl的特点，利于学习，使用广泛，主要适用于Web开发领域。PHP 独特的语法混合了C、Java、Perl以及PHP自创的语法。
					它可以比CGI或者Perl更快速地执行动态网页。用PHP做出的动态页面与其他的编程语言相比，PHP是将程序嵌入到HTML文档中去执行，执行效率比完全生成HTML标记的CGI要高许多；
					PHP还可以执行编译后代码，编译可以达到加密和优化代码运行，使代码运行更快。<br/>&nbsp;&nbsp;
					ThinkPHP是一个快速、兼容而且简单的轻量级国产PHP开发框架，诞生于2006年初，原名FCS，2007年元旦正式更名为ThinkPHP，
					遵循Apache2开源协议发布，从Struts结构移植过来并做了改进和完善，同时也借鉴了国外很多优秀的框架和模式，使用面向对象的开发结构和MVC模式，
					融合了Struts的思想和TagLib（标签库）、RoR的ORM映射和ActiveRecord模式。
				</div>
			</div>
			<div class="tc-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>公司动态</h2>
					<span class="sub-header">Trends</span>
					<a href="/index.php?m=list&a=index&id=3"><i class="fa fa-hand-o-right" title="查看更多"></i></a>
				</div>
				<div class="ranking">
					<?php $hot_articles=sp_sql_posts("cid:$portal_academic;field:post_title,post_excerpt,object_id,smeta,term_id;order:post_date desc;limit:5;"); ?>
					<ul class="unstyled">
						<?php if(is_array($hot_articles)): foreach($hot_articles as $key=>$vo): $top=$key<3?"top3":""; ?>
							<li class="<?php echo ($top); ?>"><i><?php echo ($key+1); ?></i><a title="<?php echo ($vo["post_title"]); ?>" href="<?php echo leuu('article/index',array('id'=>$vo['object_id'],'cid'=>$vo['term_id']));?>"><?php echo ($vo["post_title"]); ?></a></li><?php endforeach; endif; ?>
					</ul>
				</div>
			</div>
		</div>

	</div>
</div>
<!-- Footer ================================================== -->
<?php echo hook('footer');?>
<div class="container">
	<div id="footer">
		<div class="row" >
			<div class="text-center">
				友情链接:
				<?php $links=sp_getlinks(); ?>
				<?php if(is_array($links)): foreach($links as $key=>$vo): if(!empty($vo["link_image"])): ?><a href="<?php echo ($vo["link_url"]); ?>" target="<?php echo ($vo["link_target"]); ?>">
							<?php echo ($vo["link_name"]); ?>
							<!--<img src="<?php echo sp_get_image_url($vo['link_image']);?>" style="width:140px;height:30px;padding:0 10px 10px 0;">-->
						</a> |<?php endif; endforeach; endif; ?>
			</div>
		</div>
		<div class="row">
			<div class="text-center">
				<br/>
				&copy; 2017 TAOSIR. All Rights Reserved.
			</div>
		</div>
	</div>
</div>
<div id="backtotop">
	<i class="fa fa-chevron-circle-up" title="返回顶部"></i>
</div>
<!--web analyse script-->
<?php echo ($site_tongji); ?>

<script type="text/javascript">
   //全局变量
   var GV = {
      ROOT: "/php_git/wtcms-master/",
      WEB_ROOT: "/php_git/wtcms-master/",
      JS_ROOT: "public/js/"
   };
</script>
<!-- Placed at the end of the document so the pages load faster -->
<script src="/php_git/wtcms-master/public/js/jquery.js"></script>
<script src="/php_git/wtcms-master/public/js/wind.js"></script>
<script src="/php_git/wtcms-master/themes/default/Public/assets/userTheme/bootstrap/js/bootstrap.min.js"></script>
<script src="/php_git/wtcms-master/public/js/frontend.js"></script>
<script>
   $(function(){
      $('body').on('touchstart.dropdown', '.dropdown-menu', function (e) { e.stopPropagation(); });
      $("#main-menu li.dropdown").hover(function(){
         $(this).addClass("open");
      },function(){
         $(this).removeClass("open");
      });
      $.post("<?php echo U('user/index/is_login');?>",{},function(data){
         if(data.status==1){
            if(data.user.avatar){
               $("#main-menu-user .headicon").attr("src",data.user.avatar.indexOf("http")==0?data.user.avatar:"<?php echo sp_get_image_url('[AVATAR]','!avatar');?>".replace('[AVATAR]',data.user.avatar));
            }
            $("#main-menu-user .user-nicename").text(data.user.user_nicename!=""?data.user.user_nicename:data.user.user_login);
            $("#main-menu-user li.login").show();
         }
         if(data.status==0){
            $("#main-menu-user li.offline").show();
         }
         /* $.post("<?php echo U('user/notification/getLastNotifications');?>",{},function(data){
          $(".nav .notifactions .count").text(data.list.length);
          }); */
      });
      ;(function($){
         $.fn.totop=function(opt){
            var scrolling=false;
            return this.each(function(){
               var $this=$(this);
               $(window).scroll(function(){
                  if(!scrolling){
                     var sd=$(window).scrollTop();
                     if(sd>100){
                        $this.fadeIn();
                     }else{
                        $this.fadeOut();
                     }
                  }
               });
               $this.click(function(){
                  scrolling=true;
                  $('html, body').animate({
                     scrollTop : 0
                  }, 500,function(){
                     scrolling=false;
                     $this.fadeOut();
                  });
               });
            });
         };
      })(jQuery);
      $("#backtotop").totop();
   });
</script>

<script src="/php_git/wtcms-master/themes/default/Public/assets/js/slippry.min.js"></script>
<script>
	$(function() {
		var demo1 = $("#homeslider").slippry({
			transition: 'fade',
			useCSS: true,
			captions: false,
			speed: 1000,
			pause: 3000,
			auto: true,
			preload: 'visible'
		});
	});
</script>
<?php echo hook('footer_end');?>
</body>
</html>