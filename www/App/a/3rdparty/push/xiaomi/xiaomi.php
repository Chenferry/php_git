<?php
use xmpush\Builder;
use xmpush\HttpBase;
use xmpush\Sender;
use xmpush\Constants;
use xmpush\Stats;
use xmpush\Tracer;
use xmpush\Feedback;
use xmpush\DevTools;
use xmpush\Subscription;
use xmpush\TargetedMessage;

include_once(dirname(__FILE__) . '/autoload.php');


class XIAOMI{

    static $aksk;
    static $package;
    function send($token,$title,$msg,$url=NULL,$notifyId=0){

        // 常量设置必须在new Sender()方法之前调用
        Constants::setPackage(self::$package);
        Constants::setSecret(self::$aksk['AppSecret']);
        $payload = '{"test":1,"ok":"It\'s a string"}';

        // message1 演示自定义的点击行为
        $message = new Builder();

        
        if($url){
            $passThrough=1;
        }else{
            $passThrough=0;
            $message->title($title);  // 通知栏的title
            $message->description($msg); // 通知栏的descption
        }
        $message->passThrough($passThrough);  // 这是一条通知栏消息，如果需要透传，把这个参数设置成1,同时去掉title和descption两个参数
        $message->payload($payload); // 携带的数据，点击后将会通过客户端的receiver中的onReceiveMessage方法传入。
        // $message1->extra(Builder::notifyEffect, 1);
        $message->extra(Builder::notifyForeground, 1); // 应用在前台是否展示通知，如果不希望应用在前台时候弹出通知，则设置这个参数为0
        $message->notifyId($notifyId); // 通知类型。最多支持0-4 5个取值范围，同样的类型的通知会互相覆盖，不同类型可以在通知栏并存
        $message->build();
        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($token, TargetedMessage::TARGET_TYPE_REGID); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        // // message2 演示预定义点击行为中的点击直接打开app行为
        // $message2 = new Builder();
        // $message2->title($title);
        // $message2->description($desc);
        // $message2->passThrough(1);
        // $message2->payload($payload); // 对于预定义点击行为，payload会通过点击进入的界面的intent中的extra字段获取，而不会调用到onReceiveMessage方法。
        // $message2->extra(Builder::notifyEffect, 1); // 此处设置预定义点击行为，1为打开app
        // $message2->extra(Builder::notifyForeground, 1);
        // $message2->notifyId(0);
        // $message2->build();
        // $targetMessage2 = new TargetedMessage();
        // $targetMessage2->setTarget($token, TargetedMessage::TARGET_TYPE_REGID);
        // $targetMessage2->setMessage($message2);

        // $targetMessageList = array($targetMessage, $targetMessage2);
        // //print_r($sender->multiSend($targetMessageList,TargetedMessage::TARGET_TYPE_ALIAS)->getRaw());

        // //print_r($sender->sendToAliases($message1, $aliasList)->getRaw());
        
        // //$stats = new Stats();
        // //$startDate = '20140301';
        // //$endDate = '20140312';
        // //print_r($stats->getStats($startDate,$endDate)->getData());
        // //$tracer = new Tracer();
        // //print_r($tracer->getMessageStatusById('t1000270409640393266xW')->getRaw());
        $sender = new Sender();
        $res=$sender->send($message,$token)->getRaw();
        
        if(0==$res['code']){
            return true;
        }
        return false;
    }
}
?>
