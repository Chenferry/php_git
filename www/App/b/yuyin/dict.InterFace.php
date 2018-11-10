<?php
//该文件实现用户词典管理接口
//数据库中获取：情景模式qj，地点dd，设备名sb，属性名sx
//属性相关的配置信息：动作dz，量词lc，其它qt
//设备管理相关配置信息:
//系统管理相关：
//定时管理相关：    
//系统中的：数字sz，字母zm。这些无需添加用户词典

//sysdict:系统属性相关的配置信息
//userdict:数据库中读取的配置信息
//yuyindict:语音识别所需的用户词典

class dictInterFace
{
	//获取分词所需的字典,包含情景模式，属性名称，地点名称
	static function getDict($dictname)
	{
		$info = Cache::get('dict');
		if( NULL != $info )
		{
			return $info;
		}
		
		$info = self::buildDict();
		return $info;
	}

	static function getAttrDict($attr)
	{
		$info = Cache::get('attrdict');
		if( NULL != $info )
		{
			return $info[$attr];
		}
		
		$info = self::buildAttrDict();
		return $info[$attr];
	}

	//构建系统词典
	static function buildDict($rebuild=false)
	{
		$wordList  = self::genDictFromDB();
		
		////生成语音识别所需词典
		//$words = array();
		//foreach( $wordList as &$w )
		//{
		//	$words[] = $w['word'];
		//}
		//file_put_contents('/usr/db/yuyindict',serialize($words));
		
		//生成分词所需的词典
		$dict = NULL;
		include_once('fenci/dict.class.php');
		dictClass::buildDict($wordList,$dict);

		Cache::set('dict',$dict);
		return $dict;
	}

	//生成系统属性中的词典信息.这个可以事先生成后放指定文件中
	static function buildAttrDict( )
	{
		$ret = array();
		//遍历属性目录，查找所有文件的相关配置
		$dir = dirname(dirname(__FILE__)).'/devattr/attrType/';
		$dh  = opendir($dir);
		include_once('fenci/dict.class.php');
		while (false !== ($file = readdir($dh))) 
		{
			if( '.' == $file || '..' == $file ) continue;
			//根据file获取type，xxxAttr.php
			$type = substr($file,0,-8);
			$file = "$dir/$file";
			include_once($file);
			$class = $type.'AttrType';
			if ( !property_exists($class, 'sysDict') )
			{
				continue;
			}
			
			$wordList = array();
			foreach( $class::$sysDict as $attr=>&$words )
			{
				foreach($words as &$w)
				{
					$wordList[] = array('word'=>$w,'attr'=>$attr);
				}
			}
			$dict = NULL;
			dictClass::buildDict($wordList,$dict);
			$ret[$type] = $dict;
		}
		closedir($dh);

		Cache::set('attrdict',$ret);

		return $ret;
	}


	//生成语音识别所需的用户词典，这个是获取所有词组。暂时无需
	static function genYuyinDict()
	{
		if( !file_exists('/usr/db/yuyindict') )
		{
			self::buildDict();
		}
		$info = file_get_contents('/usr/db/yuyindict');
		return unserialize($info);
	}


	////////////////////////////////////////////////////////////	

	
	//生成用户配置的所有相关字典信息
	//情景模式qj，地点dd，设备名sb，属性名sx，摄像头定位点dwd
	private static function genDictFromDB()
	{
		$cfg = array(
			array('n'=>'qj', 'tb'=>'smartgroup',    'w'=>'ISSHOW=1'),
			array('n'=>'dd', 'tb'=>'homeroom',      'w'=>'ID>0'),
			array('n'=>'sx', 'tb'=>'homeattr',      'w'=>'ISC=1'),
			//array('n'=>'sb', 'tb'=>'homedev',       'w'=>'ID>0'),
			//array('n'=>'dwd','tb'=>'homecammerdwd', 'w'=>'NAME IS NOT NULL'),
		);
		
		$ret = array();
		foreach($cfg as &$cf)
		{
			$c = new TableSql($cf['tb'],'ID');
			$list = $c->queryAllList('NAME',$cf['w']);
			foreach($list as &$l)
			{
				$ret[] = array('word'=>$l,'attr'=>$cf['n']);
			}
		}
		return $ret;
	}
	
}
?>