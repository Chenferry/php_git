<?php 

class Cache_driver
{
	private $_cached;	
	public function __construct( $config=NULL)
	{
		include_once 'BAE/BaeMemcache.class.php';
		$this->_cached = new BaeMemcache( BAEConf::$cache['id'], 
										  BAEConf::$cache['host'].':'.BAEConf::$cache['port'], 
										  BAEConf::$app['ak'], 
										  BAEConf::$app['sk']
										  );
		return;
	}
	public function get($id)
	{
		return $this->_cached->get($id);
	}

	public function set($id, $data, $ttl = 0)
	{
		return $this->_cached->set($id,$data,0,$ttl);
	}
	
	public function del($id)
	{
		return $this->_cached->delete($id);
	}

	public function clean()
	{
		return false;
	}
	
}
?>