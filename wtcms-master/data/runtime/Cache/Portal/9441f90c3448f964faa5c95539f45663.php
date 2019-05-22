<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
<head>
	<title>搜索 |<?php echo ($site_name); ?> </title>
	<meta name="keywords" content=""/>
	<meta name="description" content="">
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
<body class="">
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
		<div class="span9">
			<div class="tc-box first-box">
				<?php $result=sp_sql_posts_paged_bykeyword($keyword,"",10); $result_pages= sp_sql_pages($tag,$where=array()); ?>
				<h3>'<?php echo ($keyword); ?>' 搜索结果 </h3>
				<p>
					<span class="badge btn-danger"> <?php echo ($result['count']); ?> 条结果</span>
				</p>
			</div>
			<?php if(is_array($result['posts'])): $i = 0; $__LIST__ = $result['posts'];if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i; $smeta=json_decode($vo['smeta'], true); ?>
				<div class="list-boxes">
					<h2><a href="<?php echo leuu('article/index',array('id'=>$vo['object_id'],'cid'=>$vo['term_id']));?>"><?php echo ($vo["post_title"]); ?></a></h2>
					<p>
						<?php echo ($vo["post_excerpt"]); ?>
					</p>
					<div>
						<div class="pull-left">
							<div class="list-actions">
								<a href="javascript:;"><i class="fa fa-eye"></i><span>阅读量<?php echo ($vo["post_hits"]); ?></span></a>
							</div>
						</div>
						<a class="btn btn-warning pull-right" href="<?php echo leuu('article/index',array('id'=>$vo['object_id'],'cid'=>$vo['term_id']));?>">查看更多</a>
					</div>
				</div><?php endforeach; endif; else: echo "" ;endif; ?>
			<div class="pagination">
				<ul>
					<?php echo ($result['page']); ?>
				</ul>
			</div>
		</div>
		<div class="span3">
			<div class="tc-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>分享文章 </h2>
					<span class="sub-header">Share</span>
				</div>
				<div class="bdsharebuttonbox">
					<a href="#" class="bds_tsina" data-cmd="tsina" title="分享到新浪微博"></a><a href="#" class="bds_qzone" data-cmd="qzone" title="分享到QQ空间"></a>
					<a href="#" class="bds_renren" data-cmd="renren" title="分享到人人网"></a>
					<a href="#" class="bds_weixin" data-cmd="weixin" title="分享到微信"></a>
				</div>
				<script>
					window._bd_share_config={
						"common":{"bdSnsKey":{},"bdText":"","bdMini":"2","bdMiniList":false,"bdPic":"","bdStyle":"1","bdSize":"24"},
						"share":{}
					};
					with(document)0[(getElementsByTagName('head')[0]||body).appendChild(createElement('script')).src='http://bdimg.share.baidu.com/static/api/js/share.js?v=89860593.js?cdnversion='+~(-new Date()/36e5)];
				</script>
			</div>
			<div class="tc-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>最新发布 </h2>
					<span class="sub-header">Latest</span>
				</div>
				<div class="ranking">
					<?php $hot_articles=sp_sql_posts("cid:$portal_last_post;field:post_title,post_excerpt,object_id,smeta,term_id;order:post_date desc;limit:5;"); ?>
					<ul class="unstyled">
						<?php if(is_array($hot_articles)): foreach($hot_articles as $key=>$vo): $top=$key<3?"top3":""; ?>
							<li class="<?php echo ($top); ?>"><i><?php echo ($key+1); ?></i><a title="<?php echo ($vo["post_title"]); ?>" href="<?php echo leuu('article/index',array('id'=>$vo['object_id'],'cid'=>$vo['term_id']));?>"><?php echo ($vo["post_title"]); ?></a></li><?php endforeach; endif; ?>
					</ul>
				</div>
			</div>
			<div class="tc-box">
				<div class="headtitle">
					<h2><img src="/php_git/wtcms-master/themes/default/Public/assets/images/bar.png"/>友情链接 </h2>
					<span class="sub-header">Link</span>
				</div>
				<div class="ranking">
					<?php $links=sp_getlinks(); ?>
					<ul class="unstyled">
						<?php if(is_array($links)): foreach($links as $key=>$vo): ?><li class="top3">
								<a href="<?php echo ($vo["link_url"]); ?>" target="<?php echo ($vo["link_target"]); ?>"><i class="fa fa-share-alt"></i><?php echo ($vo["link_name"]); ?></a>
							</li><?php endforeach; endif; ?>
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

</body>
</html>