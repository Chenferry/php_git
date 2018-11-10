<?php
$GLOBALS['xfJson'] = array(
	'page'  => 'xjd',
	'open'  => array( 0=>ATTRCFG_XF_OPEN_GUAN, 1=>ATTRCFG_XF_OPEN_KAI ),
	'fun'   => array(
			'jdcc' => array('name'=>ATTRCFG_XF_FUN_JDCC,'icon'=>'jdcc'),
			'flz'  => array('name'=>ATTRCFG_XF_FUN_FLZ,'icon'=>'flz'),
			'uvsj' => array('name'=>ATTRCFG_XF_FUN_UVSJ,'icon'=>'uvsj'),
			'fr'   => array('name'=>ATTRCFG_XF_FUN_FR,'icon'=>'fr'),
			'cs'   => array('name'=>ATTRCFG_XF_FUN_CS,'icon'=>'cs'),
			'js'   => array('name'=>ATTRCFG_XF_FUN_JS,'icon'=>'js'),
			'cysj' => array('name'=>ATTRCFG_XF_FUN_CYSJ,'icon'=>'cysj'),
	),
	'num' => array( 'gz'=>array('name'=>'PM25','unit'=>'ug/m3','icon'=>'gz'),
			'wd'=>array('name'=>'温度','unit'=>'℃','icon'=>'wd'),
			'sd'=>array('name'=>'湿度','unit'=>'rH','icon'=>'sd'),
			'voc'=>array('name'=>'VOC','unit'=>'ppm','icon'=>'co'),
			'co2'=>array('name'=>'CO2','unit'=>'ug/m3','icon'=>'yg'),
			'main'=>array('name'=>'滤芯剩余','unit'=>'%','icon'=>'xiaodu'),
	),

	'select' => array(
			'pf' => array(
				'name' => ATTRCFG_XF_SELECT_PF,
				'icon' => 'pf',
				'value' =>array( 0=>ATTRCFG_XF_SPF_GUAN,1=>ATTRCFG_XF_SPF_D,2=>ATTRCFG_XF_SPF_Z,3=>ATTRCFG_XF_SPF_G),
			),
			'sf' => array(
				'name' => ATTRCFG_XF_SELECT_SF,
				'icon' => 'sf',
				'value' =>array( 0=>ATTRCFG_XF_SPF_GUAN,1=>ATTRCFG_XF_SPF_D,2=>ATTRCFG_XF_SPF_Z,3=>ATTRCFG_XF_SPF_G),
			),
			'ptf' => array(
				'name' => ATTRCFG_XF_SELECT_PTF,
				'icon' => 'ptf',
				'value' =>array( 0=>ATTRCFG_XF_PTF_PTF,1=>ATTRCFG_XF_PTF_RJH),
			),
			'mode' => array(
				'name' => ATTRCFG_XF_SELECT_MODE,
				'icon' => 'mode',
				'value' =>array( 0=>ATTRCFG_XF_MODE_ZD, 1=>ATTRCFG_XF_MODE_SD, 2=>ATTRCFG_XF_MODE_DS),
			),
	),
	'selectIndex'=> array(
			'hide'=>array(  //选项折叠显示的
				0 => array(  //折叠的分区域显示，一个区域里包含多个，有个区域名
					'area' => ATTRCFG_XF_AREA_WIND, //区域名有可能为空
					'item' => array('pf','sf',),
				),
			),
			//选项展开显示的
			'show'=>array( 'ptf','mode'),
	),
);

?>