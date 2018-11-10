<?php 
/**
* 手机类，目前包括发送短信和拨打电话功能
*/
class Phone 
{

    /**
     * [smsSend 发送短信信息接口函数]
     * @param  [string] $phone  [接收短信的手机号码]
     * @param  [string] $sms [短信内容]
     * @return none
     */
    static function smsSend($phone,$sms)
    {
        static $isLock = false;
        static $smsTimes = 1;

        if ($isLock == true) {
            echo "上一条命令尚未执行完成，请稍等……\r\n";
            return false;
        }

        $isLock = true;
        `killall dial.sh`;
        `killall cat`;
        if (strlen($phone) != 11 || empty($sms) || !preg_match("/^1[34578]{1}\d{9}$/",$phone)) {
            echo "手机号码有误或者发送消息内容为空\r\n";
            $isLock = false;
            return false;
        }

        $recvPhone = '86' . $phone;
        //手机号码长度为奇数则最后一位补F
        if (strlen($recvPhone) % 2 == 1) {
            $recvPhone = $recvPhone.'F';
        }
        $phoneNum = "";
        //奇数和偶数位互换
        for ($i=0; $i < (strlen($recvPhone) - 1); $i+=2) { 
            $phoneNum .= $recvPhone[$i+1].$recvPhone[$i];
        }
        $phoneNum = '0D91' . $phoneNum;
        echo "phoneNum:$phoneNum\r\n";

        /*
        $smsCenter = "+8613800210500";
        $smsCenter = "8613800210500";
        //手机号码长度为奇数则最后一位补F
        if (strlen($smsCenter) % 2 == 1) {
            $smsCenter = $smsCenter.'F';
        }
        $centerNum = "";
        //奇数和偶数位互换
        for ($i=0; $i < (strlen($smsCenter) - 1); $i+=2) { 
            $centerNum .= $smsCenter[$i+1].$smsCenter[$i];
        }
        $centerNum = '91'.$centerNum;
        $centerNum = '0' . dechex(strlen($centerNum)/2) . $centerNum;
        echo "centerNum:$centerNum\r\n";
        */

        $smsContent = mb_convert_encoding($sms, "UCS-2", "auto");
        $smsContent = bin2hex($smsContent);
        echo "smsContent:$smsContent\r\n";
        $cententLen = strlen($smsContent);
        echo "cententLen:$cententLen\r\n";

        if ($cententLen > 0xf) {
            $cententLen = dechex($cententLen);
            $smsContent = $cententLen . $smsContent;
        } else {
            $cententLen = dechex($cententLen);
            $smsContent = '0' . $cententLen . $smsContent;
        }
        
        $msg = '1100' . $phoneNum . '000801' . $smsContent;
        $msgLen = ceil(strlen($msg) / 2);
        $msg = '00' . $msg;
        // $ctrlZ = mb_convert_encoding(0x1A, "UCS-2", "ASCII");
        // $ctrlZ = chr(0x1A);
        // $msg = $msg . $ctrlZ;
        // echo "msgLen:$msgLen\r\n";
        // echo "msg:$msg\r\n";
        $res = `sms.sh $msgLen $msg`;
        $isLock = false;
        $errStr = 'ERROR';
        $pos = strpos($res, $errStr);
        if ($pos !== false) {
            //如果发送短信失败则尝试发送3次
            if ($smsTimes > 3) {
                $smsTimes = 1;
                return false;
            }
            $smsTimes++;
            self::smsSend($phone,$sms);
        } else {
            $smsTimes = 1;
            return true;
        }
    }


    /**
     * [smsMultiPhoneSend 群发短信接口]
     * @param  [array] $phoneArr [手机号数组]
     * @param  [string] $sms      [短信内容]
     * @return [array]           [返回的发送给对应手机短信状态，true 成功 false 失败]
     */
    static function smsMultiPhoneSend($phoneArr,$sms)
    {
        $res = array();
        foreach ($phoneArr as $key => $phone) {
            $isOK = self::smsSend($phone,$sms);
            $res[$phone] = $isOK;
        }
        return $res;
    }

    /**
     * [phoneDial 拨打电话接口函数]
     * @param  [string] $phoneNum [要拨打的电话号码]
     * @return none
     */
    static function phoneDial($phoneNum)
    {
        static $isLock = false;
        static $dialTimes = 1;

        if ($isLock) {
            echo "上一条命令尚未执行完成，请稍等……\r\n";
            return false;
        }

        $isLock = true;
        `killall dial.sh`;
        `killall cat`;
        if (strlen($phoneNum) != 11 || !preg_match("/^1[34578]{1}\d{9}$/",$phoneNum)) {
            echo "手机号码有误\r\n";
            $isLock = false;
            return false;
        }

        $res = `dial.sh $phoneNum`;
        $isLock = false;
        $errStr = 'ERROR';
        $pos = strpos($res, $errStr);
        if ($pos !== false) {
            //如果拨打电话失败则尝试2次
            if ($dialTimes >= 2) {
                $dialTimes = 1;
                return false;
            }
            $dialTimes++;
            self::phoneDial($phoneNum);
        } else {
            $dialTimes = 1;
            return true;
        }
    }
}

 ?>