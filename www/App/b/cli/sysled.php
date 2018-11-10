<?php 
if( 'cli'!=PHP_SAPI )
{
    exit(-1);
}
$dstpCommonInfo = dirname(dirname(dirname(__FILE__))).'/a/config/dstpCommonInfo.php';
include_once($dstpCommonInfo);
include_once( dirname(__FILE__).'/proxyserver.php' );
include_once('util/hic.proto.php');
include_once( dirname(__FILE__).'/hicproc.php' );

/**
* system status which indicated by leds
* 一、未激活：
* 1.1 检测串口通讯是否正常，如果不正常，快闪红灯
* 1.2 检测设备加入是否正常，如果不正常，快闪绿灯
* 1.3 检测是否已经激活，如果还没激活，红绿灯常亮
* 二、已经激活
* 2.1 检测是否已经注册，如果没注册，红灯，绿灯慢闪
* 2.2 检测zigbee是否正常，如果不正常，闪绿灯
* 2.3 检测网络是否正常，如果不正常，红灯常亮
* 2.4 恢复出厂设置，慢闪红灯
* 2.5 正常工作状态，慢闪绿灯
* 三、分机灯指示
* 3.1 串口通讯不正常 快闪红灯
* 3.2 分机是否已经加入主机，未加入，红绿灯慢闪
* 3.3 ZigBee是否正常，异常，闪绿灯
* 3.4 检测网络是否正常，如果不正常，红灯常亮
* 3.5 恢复出厂设置，慢闪红灯
* 3.6 正常工作状态，慢闪绿灯
*/
class SysLed
{
    //根据高低电平有效进行反转,默认高电平有效
    static $ON  = 1;
    static $OFF = 0;

    static $LED_GPIO_RED = 0;
    static $LED_GPIO_GREEN = 1;

    const LED_INIT             = 0;
    const LED_ON               = 1;
    const LED_OFF              = 2;
    const LED_BLINK_SLOW       = 3;
    const LED_BLINK_NORMAL     = 4;
    const LED_BLINK_FAST       = 5;

    const LED_BLINK_SLOW_TIME     = 20;
    const LED_BLINK_NORMAL_TIME   = 5;
    const LED_BLINK_FAST_TIME     = 1;

    const SYS_STATUS_INIT           = 0;
    const SYS_STATUS_NET_OK         = 1;
    const SYS_STATUS_NET_NOT_OK     = 2;
    const SYS_STATUS_WIFI_OK        = 3;
    const SYS_STATUS_WIFI_NOT_OK    = 4;
    const SYS_STATUS_ZIGBEE_OK      = 5;
    const SYS_STATUS_ZIGBEE_NOT_OK  = 6;
    const SYS_STATUS_RESET_FACTORY  = 7;
    const SYS_STATUS_REG_AND_ACTIVE = 8;
    const SYS_STATUS_SERIAL_NOT_OK  = 9;
    const SYS_STATUS_DEV_DETECT_NOT_OK  = 10;
    const SYS_STATUS_USER_NOT_REG   = 11;
    const SYS_STATUS_NORMAL_WORK   = 12;

    static $status = 0;

    static function init()
    {
        if(defined('HIC_SYS_LED_ACTIVE'))
        {
            if (HIC_SYS_LED_ACTIVE == 'low') {
                self::$ON = 0;
                self::$OFF = 1;
            }
        }

        if(defined('HIC_SYS_LED_RED_GPIO'))
        {
            self::$LED_GPIO_RED = HIC_SYS_LED_RED_GPIO;
        }

        if(defined('HIC_SYS_LED_GREEN_GPIO'))
        {
            self::$LED_GPIO_GREEN = HIC_SYS_LED_GREEN_GPIO;
        }

        self::$status = self::SYS_STATUS_INIT;
        self::resetAllLed();
        self::run();
    }

    static function run()
    {
        server::startTimer(array(__CLASS__, 'sysStatusCheck'), 1000000*5, array(), false);
    }

    static function resetAllLed()
    {
        //目前就只有0、1这两个gpio用来控制led
        $ledGpio = [self::$LED_GPIO_RED,self::$LED_GPIO_GREEN];
        foreach ($ledGpio as $key => $value) {
            //reset led
            `gpio l $value 0 0 0 0 0`;
            self::sysLedOff($value);
        }
    }

    static function sysLedOn($gpio)
    {
        $on = self::$ON;
        //reset first
        `gpio l $gpio 0 0 0 0 0`;
        //turn on
        `gpio w $gpio $on`;
    }

    static function sysLedOff($gpio)
    {
        $off = self::$OFF;
        //reset first
        `gpio l $gpio 0 0 0 0 0`;
        //turn off
        `gpio w $gpio $off`;
    }

    static function sysLedBlinkSlow($gpio)
    {
        $time = self::LED_BLINK_SLOW_TIME;
        //reset first
        `gpio l $gpio 0 0 0 0 0`;
        //blink slow
        `gpio l $gpio $time $time 4000 0 0`;
    }

    static function sysLedBlinkNormal($gpio)
    {
        $time = self::LED_BLINK_NORMAL_TIME;
        //reset first
        `gpio l $gpio 0 0 0 0 0`;
        //blink normal
        `gpio l $gpio $time $time 4000 0 0`;
    }

    static function sysLedBlinkFast($gpio)
    {
        $time = self::LED_BLINK_FAST_TIME;
        //reset first
        `gpio l $gpio 0 0 0 0 0`;
        //blink normal
        `gpio l $gpio $time $time 4000 0 0`;
    }

    static function sysLedSet($status)
    {
        if (APP_FJ == HIC_APP) {
            if ($status == self::SYS_STATUS_ZIGBEE_NOT_OK
                || $status == self::SYS_STATUS_DEV_DETECT_NOT_OK) {
                //分机不需要检测SYS_STATUS_ZIGBEE_NOT_OK,SYS_STATUS_DEV_DETECT_NOT_OK
                return;
            }
        }
        if (self::$status != $status) {
            self::resetAllLed();
            switch ($status) {
                case self::SYS_STATUS_INIT:
                    // self::resetAllLed();
                    break;
                case self::SYS_STATUS_NET_OK:
                    // self::sysLedBlinkNormal(self::$LED_GPIO_GREEN);
                    break;
                case self::SYS_STATUS_NET_NOT_OK:
                    self::sysLedOn(self::$LED_GPIO_RED);
                    break;
                case self::SYS_STATUS_WIFI_OK:
                    break;
                case self::SYS_STATUS_WIFI_NOT_OK:
                    // self::sysLedBlinkNormal(self::$LED_GPIO_RED);
                    // self::sysLedBlinkNormal(self::$LED_GPIO_GREEN);
                    break;
                case self::SYS_STATUS_ZIGBEE_OK:
                    break;
                case self::SYS_STATUS_ZIGBEE_NOT_OK:
                    self::sysLedBlinkNormal(self::$LED_GPIO_GREEN);
                    break;
                case self::SYS_STATUS_RESET_FACTORY:
                    self::sysLedBlinkSlow(self::$LED_GPIO_RED);
                    break;
                case self::SYS_STATUS_REG_AND_ACTIVE:
                    self::sysLedOn(self::$LED_GPIO_RED);
                    self::sysLedOn(self::$LED_GPIO_GREEN);
                    break;
                case self::SYS_STATUS_SERIAL_NOT_OK:
                    self::sysLedBlinkFast(self::$LED_GPIO_RED);
                    break;
                case self::SYS_STATUS_DEV_DETECT_NOT_OK:
                    self::sysLedBlinkFast(self::$LED_GPIO_GREEN);
                    break;
                case self::SYS_STATUS_USER_NOT_REG:
                    // self::sysLedBlinkNormal(self::$LED_GPIO_RED);
                    self::sysLedBlinkSlow(self::$LED_GPIO_RED);
					sleep(1);
                    self::sysLedBlinkSlow(self::$LED_GPIO_GREEN);
                    break;
                case self::SYS_STATUS_NORMAL_WORK:
                    self::sysLedBlinkSlow(self::$LED_GPIO_GREEN);
                    break;
                
                default:
                    break;
            }
            self::$status = $status;
        }
    }

    static function sysStatusCheck()
    {
        $status = self::SYS_STATUS_INIT;
        $isFactory  = file_exists('/tmp/restorefactory');
        $isnetOk    = Cache::get('connnetstatus');

        if (APP_ZJ == HIC_APP) {
            //主机则获取是否已经注册用户
            $GLOBALS['dstpSoap']->setModule('frame');
            $isReg  = $GLOBALS['dstpSoap']->isBindUser();
            // $isHicSerOK = Cache::get('hicserverlive');
            $isHicSerOK = true;
        } else {
            //分机则判断是否已经加入主机
            $isReg  = file_exists('/usr/db/addFlag');
            //分机默认假设ZigBee通讯正常
            $isHicSerOK = true;
        }

        if ($isFactory) {
            //恢复出厂状态
            $status = self::SYS_STATUS_RESET_FACTORY;
        } else if ($isReg == false) {
            $GLOBALS['dstpSoap']->setModule('local','sn');
            $isActive =$GLOBALS['dstpSoap']->getSN();
            if ($isActive == false) {
                if (APP_ZJ == HIC_APP) {
                    //判断是否具备激活条件
                    if(!defined('HIC_SYS_NOZIGBEE'))
                    {
                        $isSerialOK = file_exists('/tmp/testHICOK');
                        //串口需要能连接通讯
                        if(!$isSerialOK)
                        {
                            //未激活且串口通讯异常
                            $status = self::SYS_STATUS_SERIAL_NOT_OK;
                            debug("串口通讯还未成功");
                        } else {
                            //要有设备加入，保证无线收发正常
                            $c = new TableSql('homedev','ID');
                            $dev = $c->queryValue('ID','PHYDEV=?',array(PHYDEV_TYPE_ZIGBEE));
                            if( !validID($dev) )
                            {
                                //未激活且ZigBee通讯异常
                                debug("系统未收到设备请求，请检查是否有设备能正常加入");
                                $status = self::SYS_STATUS_DEV_DETECT_NOT_OK;
                            } else {
                                //未激活等待激活
                                $status = self::SYS_STATUS_REG_AND_ACTIVE;
                            }
                        }
                    }
                } else {
                    //未激活等待激活
                    $status = self::SYS_STATUS_REG_AND_ACTIVE;
                }
            } else {
                //已经激活但主机未注册或者分机未加入
                $status = self::SYS_STATUS_USER_NOT_REG;
            }
        } else {
            if (false == $isHicSerOK) {
                //已经激活但ZigBee异常
                $status = self::SYS_STATUS_ZIGBEE_NOT_OK;
            } else {
                if ($isnetOk) {
                    $status = self::SYS_STATUS_NORMAL_WORK;
                } else {
                    $status = self::SYS_STATUS_NET_NOT_OK;
                }
            }
        }

        self::sysLedSet($status);
        server::startTimer(array(__CLASS__, 'sysStatusCheck'), 1000000*5, array(), false);
    }

    static function checkNetConnOK($url,$timeout)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        $content = curl_exec($ch);
        if (false == $content) {
            return false;
        } else {
            return true;
        }

        // curl_getinfo($ch, CURLOPT_HTTP_CODE);
    }

}


server::regIf('SysLed', 0, false); 

server::start();
 ?>