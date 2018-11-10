<?php
array(
	'page'  => 'xf',
	'open'   => array( 0=>ATTRCFG_XF_OPEN_GUAN, 1=>ATTRCFG_XF_OPEN_KAI ),
	'fun'   => array(
					'jdcc' => ATTRCFG_XF_FUN_JDCC,
					'flz'  => ATTRCFG_XF_FUN_FLZ,
					'uvsj' => ATTRCFG_XF_FUN_UVSJ,
					'fr'   => ATTRCFG_XF_FUN_FR,
					'cs'   => ATTRCFG_XF_FUN_CS,
					'js'   => ATTRCFG_XF_FUN_JS,
					'cysj' => ATTRCFG_XF_FUN_CYSJ,
				),
	'select'=> array(
					0=>array(  //选项折叠显示的
						0 => array(  //折叠的分区域显示，一个区域里包含多个，有个区域名
							'area' => ATTRCFG_XF_AREA_WIND, //区域名有可能为空
							'item' => array(
								'pf' => array(
									'name' => ATTRCFG_XF_SELECT_PF,
									'value' =>array( 0=>ATTRCFG_XF_SPF_GUAN,1=>ATTRCFG_XF_SPF_D,2=>ATTRCFG_XF_SPF_Z,3=>ATTRCFG_XF_SPF_G),
								),
								'sf' => array(
									'name' => ATTRCFG_XF_SELECT_SF,
									'value' =>array( 0=>ATTRCFG_XF_SPF_GUAN,1=>ATTRCFG_XF_SPF_D,2=>ATTRCFG_XF_SPF_Z,3=>ATTRCFG_XF_SPF_G),
								),
							),
						),
					),
			
					1=>array(  //选项展开显示的
						'ptf' => array(
							'name' => ATTRCFG_XF_SELECT_PTF,
							'value' =>array( 0=>ATTRCFG_XF_PTF_PTF,1=>ATTRCFG_XF_PTF_RJH),
						),
						'mode' => array(
							'name' => ATTRCFG_XF_SELECT_MODE,
							'value' =>array( 0=>ATTRCFG_XF_MODE_ZD, 1=>ATTRCFG_XF_MODE_SD, 2=>ATTRCFG_XF_MODE_DS),
						),
					),
				),
	'selectIndex' => array('pf','sf','ptf','mode'),			
	'set'   => array('wd'=>array(
						'name'  => '温度',
						'range' => array(20,60),				
					),
				),
	'yuyin' => array(
		'fun' => array(
			'js' => array(
				'bm'   => array('加水','加满水'),
			),
			'gai' => array(
				'bm'   => array('盖'),
			),
			'shao' => array(
				'bm'   => array('煮水','烧水','烧开'),
			),
		),
	),
);

?>