<?php 

class Cache_driver
{
	private static function getPath( $config=NULL)
	{
		$path = $GLOBALS['DstpDir']['tempDir'].'/hiccache/'; 
		if( !is_dir($path) )
		{
			mkdir($path);
		}
		return $path;
	}

	public function get($id)
	{
		if ( ! file_exists(self::getPath().$id))
		{
			return FALSE;
		}

		$data = file_get_contents(self::getPath().$id);
		$data = unserialize($data);

		if (time() >  $data['time'] + $data['ttl'])
		{
			unlink(self::getPath().$id);
			return FALSE;
		}

		return $data['data'];
	}

	public function set($id, $data, $ttl = 60)
	{
		$contents = array(
				'time'		=> time(),
				'ttl'		=> $ttl,
				'data'		=> $data
			);

		if (file_put_contents(self::getPath().$id, serialize($contents)))
		{
			@chmod(self::getPath().$id, 0777);
			return TRUE;
		}

		return FALSE;
	}
	
	public function del($id)
	{
		return unlink(self::getPath().$id);
	}

	public function clean()
	{
        $path   =  self::getPath();
        $files  =  scandir($path);
        if($files){
            foreach($files as $file){
                if ($file != '.' && $file != '..' && is_dir($path.$file) ){
                    array_map( 'unlink', glob( $path.$file.'/*.*' ) );
                }elseif(is_file($path.$file)){
                    unlink( $path . $file );
                }
            }
            return true;
        }
        return false;
	}
}
?>