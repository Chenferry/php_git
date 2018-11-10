<?php
//烧水壶
$GLOBALS['sshJson'] = array(
	'page'  => 'xjd',
	'open'   => array( 0=>'关', 1=>'开' ),
	'fun'   => array(
			'zhu'  => array('name'=>'烧水','icon'=>'kg'),
			'jia'  => array('name'=>'加水','icon'=>'kg'),
			'gai'  => array('name'=>'盖子','icon'=>'kg'),
			'ting' => array('name'=>'停止','icon'=>'kg'),
	),
	
	'select'=> array(
			'mode' => array(
				'name' => '模式',
				'icon'	=> 'mode',
				'value' =>array( 0=>'温开水',1=>'红茶',2=>'绿茶',3=>'铁观音',4=>'普洱茶',5=>'咖啡',6=>'泡奶',7=>'晨起用水'),
			),
	),
	'set'   => array(
			'wd'=>array(
				'name'  => array('cur'=>'水温','set'=>'设定值'),
				'range' => array(30,100),	
				'unit'  => '℃',
				'icon'	=> 'wd',
			),
	),

	'selectIndex' => array('show'=>array('mode')),		

	'yuyin' => array(
		'set' => array(
			'wd'=>array(
				'bm'   => '温度',
				'keep' => array('保温'), //设置指定数值的动作别名
				'add'  => array('太冷了','有点冷','调高'),       //增加数值的动作别名
				'sub'  => array(),		 //减少数值的动作别名
			),
		),
		'fun' => array(
			'zhu' => array(
				'bm'   => array('煮水','开水','烧开'),
				'kai'  => array(),  //打开的动作别名
				'guan' => array(),      //关闭的动作别名
				'fan'  => array(),      //反向的动作别名
			),
			'jia' => array(
				'bm'   => array('满水','加点水'),
			),
			'gai' => array(
				'bm'   => array('盖','开盖'),
			),
		),

	),

	
);

?>