<?php
	$deviceApp = 'E:\workbase\newSource\devCommon\PlatForm\Zstack\DeviceApp\CC2530DB';
	$tool = 'D:\Program Files (x86)\IAR Systems\Embedded Workbench 6.0\common\bin\IarBuild.exe';
    if (!file_exists($tool)) {
        echo("您已配置IAR进行自动编译，但路径有误，请检查iarDir的路径配置");
        die();
    }
    $type = 'RouterEB';

    //删除编译目录，强制全部重编，以免错误


    $proj = "$deviceApp\\DeviceApp.ewp ";
    $cmd = "\"$tool\" $proj $type";
    $output = NULL;
    $return = NULL;
    exec($cmd, $output, $return);
    if (0 != $return) {
        debug("编译有错，请检查信息");
        debug($output);
        die();
    } else {
        $out = $deviceApp.'\Exe\DeviceApp.hex';
        echo("编译完成，生成文件:");
        echo($out);
    }   