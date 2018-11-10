<?php 

/**
 * memcached 方式存储缓存
 */
class Cache_driver
{
    private $_mem;
    private $_sers = array(
        array('127.0.0.1', 11211),
    );

    function __construct($serverCfg)
    {
        if( !isset( $GLOBALS['SYSDB']['CACHE'] ) )
        {
            if(  'cli' == PHP_SAPI )
            {
                $itemid = intval($_SERVER['argv'][1]);
                $GLOBALS['dstpSoap']->setModule('frame');
                $GLOBALS['dstpSoap']->initDBEnvByItem($itemid);
            }
            else
            {
                $hicToken = getHICToken();
                list($userid,$time,$hicid,$rand,$flag) = explode('-', $hicToken); 
                $GLOBALS['dstpSoap']->setModule('frame');
                $GLOBALS['dstpSoap']->initDBEnv($hicid);				
            }
        }
        $this->_mem = new Memcached;
        // 开启使用二进制协议,因为SASL只能在这种模式下使用
        $this->_mem->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        // 开启或关闭已连接socket的无延迟特性 在某些幻境可能会带来速度上的提升
        $this->_mem->setOption(Memcached::OPT_TCP_NODELAY, true);
        //每台上面都有memcache，直接设置本地
        //否则memcache启动参数就需要设置不能是127.0.0.1
        if(  'cli' == PHP_SAPI )
        {
            $this->_mem->addServer( '127.0.0.1',$GLOBALS['SYSDB']['CACHE']['PORT']);
        }
        else
        {
            $this->_mem->addServer( $GLOBALS['SYSDB']['CACHE']['SERVER'],$GLOBALS['SYSDB']['CACHE']['PORT']);
        }

        // 注意如果memcached未开启SASL功能 则不需要此步骤
        // $this->_mem->setSaslAuthData('memuser', 'asdf1234~');
        if (isset($GLOBALS['SYSDB']['CACHE']['MEMUSER']) && isset($GLOBALS['SYSDB']['CACHE']['MEMPWD'])) {
            $this->_mem->setSaslAuthData($GLOBALS['SYSDB']['CACHE']['MEMUSER'],
                $GLOBALS['SYSDB']['CACHE']['MEMPWD']);
        }
    }

    public function get($id)
    {
        return $this->_mem->get($id);
    }

    public function set($id, $data, $ttl = 0)
    {
        return $this->_mem->set($id,$data,$ttl);
    }

    public function del($id)
    {
        return $this->_mem->delete($id);
    }

    public function clean()
    {
        return false;
    }

}
?>
