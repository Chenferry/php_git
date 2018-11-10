<?php
require_once('../../a/config/dstpCommonInfo.php');

/*
 * $time是日期,格式为2018-08-08或者2018-08
 * $unit是时间单位，day、days、month、months、year、years
*/
function getChartData($time, $unit, $attrid,$tags=array())
{
    return ;
    $hicid=HICInfo::getHICID();

    $unit=strtoupper($unit);
    if ('c' == HIC_LOCAL)
    {
        $c = new TableSql('hic_frameautologin');
        $hicid = $c->queryValue('HICID','LOGINFLAG=?',array(getHICToken()));
        $aksk = BAEConf::$tsdb;

        $GLOBALS['dstpSoap']->setModule('app', 'hic');
        $info = $GLOBALS['dstpSoap']->getChartInfo($hicid,$attrid);

    }
    elseif ('b' == HIC_LOCAL)
    {
        $r=Cache::get('TSDB_AKSK');
        if(false === $r || 'false' == $r)
        {
            $GLOBALS['dstpSoap']->setModule('app', 'server');
            $aksk = $GLOBALS['dstpSoap']->getTSDBAkSk();
            Cache::set('TSDB_AKSK',json_encode($aksk));
        }else{
            $aksk=json_decode($r,true);
        }

        $c = new TableSql('hic_frameautologin');
        $hicid = $c->queryValue('HICID','LOGINFLAG=?',array(getHICToken()));

        $c = new TableSql('homeattr','ID');
        $attr = $c->query('*','ID=?',array($attrid));
        $info['CHARTTYPE']=$attr['type'];
        $info['UNIT']=$attr['unit'];
    }

    if(!is_numeric($time))
    {
        $time=strtotime($time);
    }

    include_once('tsdb/tsdb.php');
    $t = new TSDB();
    $t::$aksk = $aksk;
    $start=$time;

    if("DAY" == $unit ||"DAYS" == $unit)
    {
        $key="H";
        //获取当天24点的时间戳
        $end=$time+3600*24;
        //每小时一个数据
        $sampling="1 hour";
    }
    elseif ("MONTH" == $unit || "MONTHS" == $unit)
    {
        $key="d";
        //获取1号0点时间戳
        $start=strtotime(date('Y-m',$time));
        //获取月尾时间
        $end=strtotime(date('Y-m',$time)." +1 month");
        //每天一个数据
        $sampling="1 day";

    }else if("YEAR" == $unit || "YEARS" == $unit) {

        $key = "m";
        //获取年初时间
        $start = strtotime(date('Y-01-01 00:00:00', time()));
        //获取下一年年初时间
        $end = strtotime(date('Y-01-01 00:00:00', time()) . " +1 year");
        //每月一个数据
        $sampling = "1 month";
    }

    $attrid="attr$attrid";
    $res=$t->query($start, $end, $sampling, $attrid ,$tags);
    $res=json_decode($res,true);
    if(empty($res['results'][0]['groups'][0]['values']))
    {
        return "nodata";

    }

    $value=$res['results'][0]['groups'][0]['values'];
    foreach($value as $point)
    {
        //如果是按year取数据，key就是月份
        //如果是按month取数据，key就是几号
        //如果是按day，key就是几点
        $day=intval(date($key,$point[0]/1000));
        $tmp[]=array(
            'time'=>$day,
            'value'=>$point[1]
        );
    }
    return $tmp;
    return array(
        'type'=>$info['CHARTTYPE'],
        'unit'=>$info['UNIT'],
        'data'=>$tmp
    );
}

util::startSajax(array('getChartData'));
