<?php
$GLOBALS['lyjJson'] = array(
	'page'  => 'lyj',
	'open'  => array( 0=>ATTRCFG_XF_OPEN_GUAN, 1=>ATTRCFG_XF_OPEN_KAI ),
	'fun'   => array(
			'hg'     => array('name'=>'烘干','icon'=>'hg'),
			'fg'     => array('name'=>'风干','icon'=>'fg'),
			'xiaodu' => array('name'=>'消毒','icon'=>'xiaodu'),
			'gz'     => array('name'=>'照明','icon'=>'gz'),
	),

	'select' => array(
			'lyj' => array(
				'name' => '晾衣架',
				'icon' => 'tcq',
				'value' =>array( 0=>'向上',1=>'停止',2=>'向下'),
			),
	),
	'selectIndex'=> array(
			//选项展开显示的
			'show'=>array( 'lyj'),
	),
);

?>