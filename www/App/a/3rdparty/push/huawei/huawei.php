<?php
include_once "huaweiPush/Http/Http.php";
include_once "huaweiPush/Http/Request.php";
include_once "huaweiPush/Http/Response.php";
include_once "huaweiPush/huaweiPush.php";
//include "vendor/autoload.php";


class HUAWEI {
    static $aksk;
    static $package;
    function send($token,$title,$msg,$url=NULL,$notifyId=0){
        $push=new \huaweiPush(self::$aksk['AppID'],self::$aksk['AppSecret']);
        $AccessToken=$push->getAccessToken();//获取AccessToken 可以保存起来


        $push->setTitle($title)
            ->setMessage($msg)
            ->setAccessToken($AccessToken)
            ->setAppPkgName(self::$package) //设置包名称
            ->setCustomize(["你好"]) //设置自定义参数 （点击app后可以应用可获取的参数）
           ->addDeviceToken($token);
        $push->sendMessage(); // 执行推送消息。


        return $push->isSendSuccess(); //是否推送成功
        // var_dump($push->isSendFail()); //是否推送失败
        // var_dump($push->getAccessTokenExpiresTime()); //获取AccessToken 过期时间
        // var_dump($push->getSendSuccessRequestId()); //获取推送成功后接口返回的请求id

    }
}
