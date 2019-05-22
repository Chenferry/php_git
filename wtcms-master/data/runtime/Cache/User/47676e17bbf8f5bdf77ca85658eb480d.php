<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
<head>
	<title><?php echo ($site_name); ?></title>
	<meta name="keywords" content="<?php echo ($site_seo_keywords); ?>"/>
	<meta name="description" content="<?php echo ($site_seo_description); ?>">
	<meta name="author" content="taosir">
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

</head>
<body class="body-white">

<video id="video_background" preload="auto" autoplay="true" loop="loop" muted="muted" volume="0">
	<source src="/php_git/wtcms-master/themes/default/Public/assets/images/time.webm" type="video/webm">
	<source src="/php_git/wtcms-master/themes/default/Public/assets/images/time.mp4" type="video/mp4">
</video>
<div class="cover">
</div>
<div class="container tc-main" style="margin:100px auto 0;">
	<div class="row" style="overflow:hidden;">
		<div class="span4 offset4">
			<h2 class="text-center" style="color:#ffffff;font-weight:bold;margin-bottom:50px;">用户登录</h2>
			<form class="form-horizontal js-ajax-form" action="<?php echo U('user/login/dologin');?>" method="post">
				<div class="control-group">
					<input type="text" name="username" placeholder="手机号/邮箱/用户名" class="span4 input" required>
				</div>
				<div class="control-group">
					<input type="password" name="password" placeholder="密码" class="span4 input" required>
				</div>
				<div class="control-group">
					<div class="span4" style="margin-left: 0px;">
						<input class="span3 input" type="text" id="input_verify" name="verify" placeholder="验证码"required>
						<?php echo sp_verifycode_img('length=4&font_size=14&width=95&height=40&charset=12345678&use_noise=1&use_curve=0','style="cursor:pointer;border-radius:3px;float:right;" title="点击获取"');?>
					</div>
				</div>
				<div class="control-group">
					<button class="btn btn-warning js-ajax-submit span4" type="submit" style="margin-left: 0px">登 录</button>
				<!--	<a href="<?php echo U('api/oauth/login',array('type'=>'qq'));?>" class="btn btn-primary pull-right"><i class="fa fa-qq"></i> QQ登录</a>-->
				</div>
				<div class="control-group" style="text-align: center;">
					<ul class="inline">
						<li><a href="<?php echo leuu('user/register/index');?>">现在注册</a></li>
						<li><a href="<?php echo U('user/login/forgot_password');?>">忘记密码</a></li>
					</ul>
				</div>
			</form>
		</div>
	</div>
</div>
<p style="color:#ffffff;text-align:center;font-size:1.0em;font-weight:bold;">
	Copyright &copy; 2017 <a href="/php_git/wtcms-master/">TAOSIR.</a>
<p>
	<!-- /container -->
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

</body>
</html>