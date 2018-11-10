<?php
array(
	//页面布局方式
	'page'  => 'xjd',
	
	//前后台数据格式，按顺序如下
	//总开/功能按钮(1字节)/枚举按钮(1字节/设置值(4字节，前2字节当前值(大端)，后2字节设置值)/数值信息(2字节，大端)/故障代码(1字节)

	//总开关信息
	'open'   => array( 0=>ATTRCFG_XF_OPEN_GUAN, 1=>ATTRCFG_XF_OPEN_KAI ),
	
	//同时包含设定值和当前值的数据信息
	'set'   => array(
					'wd'=>array(
						'name'  => '温度',       //
						'range' => array(20,60), //设定值的温度范围				
					),
				),
	'num' => array( 'gz'=>array('name'=>'PM25','unit'=>'ug/m3','icon'=>'gz'),
			'wd'=>array('name'=>'温度','unit'=>'℃','icon'=>'wd'),
			'sd'=>array('name'=>'湿度','unit'=>'rH','icon'=>'sd'),
			'voc'=>array('name'=>'VOC','unit'=>'ppm','icon'=>'co'),
			'co2'=>array('name'=>'CO2','unit'=>'ug/m3','icon'=>'yg'),
			'main'=>array('name'=>'滤芯剩余','unit'=>'%','icon'=>'xiaodu'),
	),
	//开关类型的功能按钮。数组键值为图标名称
	'fun'   => array(
					'jdcc' => array('name'=>ATTRCFG_XF_FUN_JDCC,'icon'=>'jdcc'),
					'flz'  => array('name'=>ATTRCFG_XF_FUN_FLZ,'icon'=>'flz'),
					'uvsj' => array('name'=>ATTRCFG_XF_FUN_UVSJ,'icon'=>'uvsj'),
					'fr'   => array('name'=>ATTRCFG_XF_FUN_FR,'icon'=>'fr'),
					'cs'   => array('name'=>ATTRCFG_XF_FUN_CS,'icon'=>'cs'),
					'js'   => array('name'=>ATTRCFG_XF_FUN_JS,'icon'=>'js'),
					'cysj' => array('name'=>ATTRCFG_XF_FUN_CYSJ,'icon'=>'cysj'),
				),
	//			
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
	
	//设备告警码信息
	'alarm'   => array(
			'shao'=>array(
				'name' => '有水',       //
				'info' => array(0=>'有水',1=>'无水'), //设定值的温度范围				
			),
	),  
	

	//故障代码
	'code' => '',
	
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
			'js' => array(
				'bm'   => array('加水','加满水'),
				'kai'  => array('打开','开始'),  //打开的动作别名
				'guan' => array('关','停','不要'),  //关闭的动作别名
				'fan'  => array(),  //反向的动作别名
			),
			'gai' => array(
				'bm'   => array('盖'),
			),
			'shao' => array(
				'bm'   => array('煮水','烧水','烧开'),
			),
		),
		'select' => array(
			'mode'     => array(
				'bm'   => array(),
				
			), 
		),
	),
);

?>