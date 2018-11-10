<?php 

class Cache_driver
{
	static private $c;
	private static function getDBH( $config=NULL)
	{
		if( NULL == self::$c )
		{
			self::$c = new TableSql('syscache');
		}
		return self::$c;
	}

	public function get($id)
	{
		$t = time();
		$info = self::getDBH()->query('CVALUE','CNAME=? AND DELTIME>?',array($id, $t));
		if ( NULL == $info )
		{
			return false;
		}
		return unserialize($info['CVALUE']);
	}

	public function set($id, $data, $ttl = 60)
	{
		$info = array();
		$info['CNAME']   = $id;
		$info['CVALUE']  = serialize($data);
		$info['DELTIME'] = time()+ $ttl;
		self::getDBH()->del('CNAME=?',array($id));
		self::getDBH()->add($info);
		return true;
	}
	
	public function del($id)
	{
		return self::getDBH()->del('CNAME=?',array($id));
	}

	public function clean()
	{
		return self::getDBH()->del();
	}
	
}
?>