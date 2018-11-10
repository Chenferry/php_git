<?php
class firewall extends uci_base{

/**
 * 开放指定端口
 *  @param port
 *  @param proto
 * @return boolean
 * @author
 */
static function openPort($proto,$port) {
	$res = `iptables -I INPUT -p $proto --dport $port -j ACCEPT`;
    return $res ? false:true;
}

static function closePort($proto,$port)
{
	$res = `iptables -D INPUT -p $proto --dport $port -j ACCEPT`;
    return $res ? false:true;
}




  static function snat($act,$proto,$interface,$outport,$inport,$outip=null,$inip=null)
  {
        if($outip==null)
            $source     =" -j SNAT --to-port $outport";
        else
            $source     ="-j SNAT --to-source $outip:$outport";
//        if($inip!=null)
//            $condition  ="-s $inip --sport $inport";
//        else
            $condition  ="--sport $inport";
        if($outip==null && $inip==null)
        {
            $snat   = "--sport $inport -j REDIRECT --to-port $outport";
        }else{
            $snat   = "$condition $source";
        }
        return `iptables -t nat -$act POSTROUTING -o $interface -p $proto $snat`;


  }

  static function dnat($act,$proto,$interface,$outport,$inport,$outip=null,$inip=null)
  {
   //     if($outip!=null)
     //       $condition      ="-d $outip --dport $outport";
       // else
            $condition      ="--dport $outport";
        if($inip==null)
            $destination    ="-j DNAT --to-port $inport";
        else
            $destination    ="-j DNAT --to-destination $inip:$inport";
        if($outip==null && $inip==null)
        {
            $dnat   = "--dport $inport -j REDIRECT --to-port $outport";
        }else{
            $dnat   = "$condition $destination";
        }

        return `iptables -t nat -$act PREROUTING -i $interface -p $proto $dnat`;
  }

/**
 * 重定向
 * @param proto 协议类型
 * @param outip 外网IP
 * @param inip 内网IP如摄像头
 * @param outport 外网端口
 * @param wanport WAN端口
 * @param lanport LAN端口
 * @return void
 * @author ldh
 */
    static function map($proto,$port,$ip) {
        include_once('uci/wan.class.php');
        $interface  = wan::getInterface();
        $lanip      = network::getLanip();
        $wanip      = wan::getIP();
        $outip      = $ip['out'];
        $inip       = $ip['in'];
        $outport    = $port['out'];
        $inport     = $port['in'];
        $wanport    = $port['wan'];
        $lanport    = $port['lan'];
        firewall::dnat('A',$proto,$interface,$wanport,$inport,$wanip,$inip);
        firewall::dnat('A',$proto,'br-lan',$lanport,$outport,$lanip,$outip);
        firewall::snat('I',$proto,$interface,$wanport,$inport,$wanip,$inip);
        firewall::snat('I',$proto,'br-lan',$lanport,$outport,$lanip,$outip);
    }

/**
 * 删除重定向
 * @param proto 协议类型
 * @param outip 外网IP
 * @param inip 内网IP如摄像头
 * @param outport 外网端口
 * @param wanport WAN端口
 * @param lanport LAN端口
 * @return void
 * @author ldh
 */
    static function unmap($proto,$port,$ip) {
        include_once('uci/wan.class.php');
        $interface  = wan::getInterface();
        $lanip      = network::getLanip();
        $wanip      = wan::getIP();
        $outip      = $ip['out'];
        $inip       = $ip['in'];
        $outport    = $port['out'];
        $inport     = $port['in'];
        $wanport    = $port['wan'];
        $lanport    = $port['lan'];
        firewall::dnat('D',$proto,$interface,$wanport,$inport,$wanip,$inip);
        firewall::dnat('D',$proto,'br-lan',$lanport,$outport,$lanip,$outip);
        firewall::snat('D',$proto,$interface,$wanport,$inport,$wanip,$inip);
        firewall::snat('D',$proto,'br-lan',$lanport,$outport,$lanip,$outip);
    }



    /**
     * 解除对mac的访问限制。
     * lan：true，只去除对内网的访问禁止。false，解除内外网访问禁止。
     * @author ldh
     */
    static function allow_mac($mac,$lan=false)
    {
		if($lan)
		{
			`iptables -t mangle -D PREROUTING  -m mac --mac-source $mac -d 192.0.0.0/8 -j DROP`;
		}
		else
		{
			//`iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p udp --dport 53 -j DNAT --to 192.168.93.1:3000`;
			`iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp --dport 80  -j DNAT --to 192.168.93.1:5000`;
			`iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp --dport 443 -j DNAT --to 192.168.93.1:5001`;
			`iptables -t mangle -D PREROUTING  -m mac --mac-source $mac -j DROP`;
		}
        return true;
    }

    /**
     * 禁止指定的MAC地址上网
     * lan,如果为true，表示只禁止lan访问但允许外网访问。如果为false，表示内网外网全部禁止访问
     * @return boolean
     * @author ldh
     */
    static function deny_mac($mac,$lan=false)
    {
		//使用插入最后，所以先插入允许内容
		self::allow_mac($mac,$lan);
		if($lan)
		{
			//需要允许DNS端口和80端口。因为可能客人需要用HIC访问自家HIC.初始化这几个端口全部同时打开了
			`iptables -t mangle -A PREROUTING  -m mac --mac-source $mac -d 192.0.0.0/8 -j DROP`;
		}
		else
		{
			//但把所有web访问全部导向到本地80端口
			`iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p tcp --dport 80  -j DNAT --to 192.168.93.1:5000`;
			`iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p tcp --dport 443 -j DNAT --to 192.168.93.1:5001`;
			`iptables -t mangle -A PREROUTING  -m mac --mac-source $mac -j DROP`;
		}
        return true;
    }
     /**
      * 初始化防火墙，拦截所有的流量
      * @return false
      * @author
      */
     static function lanReject() {
		//防火墙使用黑名单机制，当连线上时再添加进黑名单，所以无需处理.
		//当一个未经允许的设备连上后，会设置禁止访问。但需要它能访问如下两个端口
		//所以在表最前头插入这两条规则，后面的禁止规则追加在表后头
		//`iptables -t mangle -I PREROUTING 1 -p udp --dport 3000  -j ACCEPT`;
		`iptables -t mangle -I PREROUTING 1 -p tcp --dport 2887  -j ACCEPT`;
		`iptables -t mangle -I PREROUTING 1 -p udp --dport 67  -j ACCEPT`;
		`iptables -t mangle -I PREROUTING 1 -p udp --dport 53  -j ACCEPT`;
		`iptables -t mangle -I PREROUTING 1 -p tcp --dport 80  -j ACCEPT`;
		`iptables -t mangle -I PREROUTING 1 -p tcp --dport 443 -j ACCEPT`;

		//查找当前已经连上的所有无线mac，先禁止

		return true;
     }

     static function addRedirectToWebPort($mac)
     {
          //所有通过有wifi密码进来的设备首先都重定向到web 80端口，审核通过后方能正常上网
          //先删除原先的配置，避免相同的mac不断增加
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp -d 192.168.93.1 -j ACCEPT`;
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p udp --dport 53 -j DNAT --to 192.168.93.1:3000`;
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp --dport 80 -j DNAT --to 192.168.93.1:5000`;
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp --dport 443 -j DNAT --to 192.168.93.1:5001`;

          `iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p tcp -d 192.168.93.1 -j ACCEPT`;
          `iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p tcp --dport 80 -j DNAT --to 192.168.93.1:5000`;
          `iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p tcp --dport 443 -j DNAT --to 192.168.93.1:5001`;
          // `iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p tcp --dport 80 -j DNAT --to 192.168.93.1:80`;
		  //加密的，允许访问。但访问网页时需要输入个名字，只把80端口端口导向自己就可以，无需修改dns
		  //因为修改dns后，用户随后的访问经常会有一段时间因为缓存而错误
          //`iptables -t nat -A PREROUTING  -m mac --mac-source $mac -p udp --dport 53 -j DNAT --to 192.168.93.1:3000`;

          return true;
     }

     static function cancelRedirectToWebPort($mac)
     {
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp -d 192.168.93.1 -j ACCEPT`;
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp --dport 80 -j DNAT --to 192.168.93.1:5000`;
          `iptables -t nat -D PREROUTING  -m mac --mac-source $mac -p tcp --dport 443 -j DNAT --to 192.168.93.1:5001`;

          return true;
     }
}

?>