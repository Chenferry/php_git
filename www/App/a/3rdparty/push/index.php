<?php
date_default_timezone_set('PRC');
class push{
    static $package = 'com.test.smarthic.app';
    function send($token,$brand,$title,$msg,$url,$id){
        $key=array(
            'XIAOMI'=>array(
                'AppID'=>'2882303761517848211',
                'AppKey'=>'5431784886211',
                'AppSecret'=>'fW87sIBFsz7WbgNuXoD33w=='
            ),
            'huawei'=>array(
                'AppID'=>'100376939',
                'AppKey'=>'',
                'AppSecret'=>'32763126c52fdeb95165a95c22999e11'
            ),
            'oppo'=>array(),
            'vivo'=>array(),
            'meizu'=>array(
                'AppID'=>'2882303761517848211',
                'AppKey'=>'7d352aeb8b9548899d10eb8e30f8b03e',
                'AppSecret'=>'8f7093506f034a92a72d2331c1bdd1bd'
            )
        );

        include_once(dirname(__FILE__) . strtolower("/$brand/$brand.php"));
        $brand::$package=self::$package;
        $brand::$aksk=$key[$brand];
        $brand::send($token,$title,$msg,$url,$id);
    }
}
$title="测试";
$msg=date("Y-m-d H:i:s",time());
$brand='XIAOMI';
$id=$_GET['id'];
$token='V9UJjqyVx9g8CeWMt8SDDcjc/n+Z8oiNG5l+n+/sjc3EibHwu2ipPQZyu8mx2zKN';
push::send($token,$brand,$title,$msg,$url,$id);

$token='CX6afm2fIalmw4/kf4NOpV6aMDGAuQA9lGf42KH0OIvQG+Wt5/5F8usrxy/QZQ0n';
push::send($token,$brand,$title,$msg,$url,$id);

$token='3OB84t8KYrC6OLWZ9+PAmLY088ULHgYgPrq2uoEWl5mAv04V1zJWgU2B2HBmJmmR';
push::send($token,$brand,$title,$msg,$url,$id);

$token='4iQdMfqNgyqDFDA91RIAA5Ns4Ggx84dvQdII1AbhY0BfR1mCRqIcBMi/4B9ndwKo';
push::send($token,$brand,$title,$msg,$url,$id);

// $token='CSCBB18109202279300002238300CN01';
// $brand='huawei';
// push::send($token,$brand,$title,$msg);