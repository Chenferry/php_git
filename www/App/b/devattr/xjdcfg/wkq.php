<?php
$GLOBALS['wkqJson'] = array(
	'page'  => 'xjd',
	'open'   => array( 0=>'关', 1=>'开' ),
	
	'select'=> array(
			'ms' => array(
				'name' => '模式',
				'icon'	=> 'ptf',
				'value' =>array( 1=>'冷',2=>'热'),
			),
			'fs' => array(
				'name' => '风速',
				'icon'	=> 'mode',
				'value' =>array( 0=>'自动', 1=>'低', 2=>'中', 3=>'高'),
			),
	),
	'set'   => array(
			'wd'=>array(
				'name'  => array('cur'=>'当前温度','set'=>'设定值'),
				'range' => array(18,30),	
				'unit'  => '℃',
				'icon'	=> 'wd',
			),
	),

	'selectIndex' => array('show'=>array('ms','fs')),			
);

?>