<?php
/**
 * 守护进程类
 *
 * @package default
 * @author  
 */
class service {
	/**
	 * 获取挂载硬盘路径
	 *
	 * @return void
	 * @author  
	 */
	static function MountPath() {
		
	}
	
	/**
	 * 启动停止代理服务
	 *
	 * @return void
	 * @author  
	 */
	static function ProxyStart() {
		return `/etc/init.d/proxy start`;
	}
	
	static function ProxyStop() {
		return `/etc/init.d/proxy stop`;
	}
	
	static function ProxyRestart(){
		return `/etc/init.d/proxy restart`;
	}
	
	static function dbBackup(){
	      return `database.sh`;
	}
    
    static function clearEmptySessionFile()
    {
      `find /tmp -name sess_* -size 0c | xargs rm -f`;//删除session文件
    }       
    static function clearSessionFile()
    {
      `find /tmp -name sess_*  | xargs rm -f`;//删除session文件
    }
} // END
?>