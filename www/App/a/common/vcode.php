<?php
session_start();


if( isset($_SERVER['HTTP_ORIGIN']) )
{
	if( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] )
	{
		header('Access-Control-Allow-Methods:POST,OPTIONS,GET');
		header('Access-Control-Allow-Headers:Content-Type, Authorization, Accept,X-Requested-With');
		exit();
	}
	//login那要强制性的把www.jia.mn这类访问形式改为jia.mn。暂时还没处理，就改为任意来源都接受
	//header('Access-Control-Allow-Origin: http://'.a_jia_sx);
	header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
	header('XDomainRequestAllowed:1'); //for IE
	header('Access-Control-Allow-Credentials: true');
}




//生成验证码图片
Header("Content-type: image/PNG");
$im = imagecreate(44,18);
$back = ImageColorAllocate($im, 245,245,245);
imagefill($im,0,0,$back); //背景
$vcodes=NULL;
srand((double)microtime()*1000000);
//生成4位数字
for($i=0;$i<4;$i++){
$font = ImageColorAllocate($im, rand(100,255),rand(0,100),rand(100,255));
$authnum=rand(1,9);
$vcodes.=$authnum;
imagestring($im, 5, 2+$i*10, 1, $authnum, $font);
}

for($i=0;$i<100;$i++) //加入干扰象素
{ 
$randcolor = ImageColorallocate($im,rand(0,255),rand(0,255),rand(0,255));
imagesetpixel($im, rand()%70 , rand()%30 , $randcolor);
} 

//ob_start(); 
ImagePNG($im);
//$data = ob_get_contents (); 
//ob_end_clean(); 
//echo base64_encode($data);

ImageDestroy($im);

$_SESSION['VCODE'] = $vcodes;

?>