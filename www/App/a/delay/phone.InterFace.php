<?php 
/**
* 手机相关接口
*/
class phoneInterFace
{
    /**
     * [sendSmsInfo 发送短信接口]
     * @param  [string] $phone [接收短信的手机号]
     * @param  [string] $msg   [短信内容]
     * @return [bool] [true 成功 false 失败]
     */
    static function sendSmsInfo($phone,$msg)
    {
        include_once('phone/phone.class.php');
        $res = Phone::smsSend($phone,$msg);
        return $res;
    }

    /**
     * [sendSmsToMultiPhone 群发短信接口]
     * @param  [array] $phoneArr [手机号数组]
     * @param  [string] $msg      [短信内容]
     * @return [array]           [发给对应手机短信状态，true 成功 false 失败]
     */
    static function sendSmsToMultiPhone($phoneArr,$msg)
    {
        include_once('phone/phone.class.php');
        $res = Phone::smsMultiPhoneSend($phoneArr,$msg);
        return $res;
    }

    /**
     * [dial 打电话接口]
     * @param  [string] $phone [电话号码]
     * @return [bool] [true 成功 false 失败]
     */
    static function dial($phone)
    {
        include_once('phone/phone.class.php');
        $res = Phone::phoneDial($phone);
        return $res;
    }
}

 ?>