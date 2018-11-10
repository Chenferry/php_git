<?php
include_once("bosSts/sts.php");

class TSDB{
    static $aksk;
    static $url = "huidang1.tsdb.iot.gz.baidubce.com/v1/datapoint?";

    function curl($url,$data, $headers,$method)
    {
        //把关联数组转换为一维数组
        foreach ($headers as $key => $val) {
            $indexHeaders[] = $key . ": " . $val;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if($method=="PUT"){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }elseif ($method=="POST"){
            curl_setopt($ch, CURLOPT_POST, true);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $indexHeaders); //设置header
        $res = curl_exec($ch);

        // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // if (200 != $httpCode || 204 != $httpCode) {
        //     echo (var_dump($res));
        // }
        // return 123;
        return $res;

    }

    function common(&$signer,&$headers,$httpMethod,$data,$params=array()){

        $timezone=date_default_timezone_get();
        HttpUtil::__init();
        SampleSigner::__init();
        $credentials = self::$aksk;

        $path = "/v1/datapoint";
        date_default_timezone_set("UTC");
        $stamp = time();

        $x_bce_date = date("Y-m-d\TH:i:s\Z", $stamp);
        $signer = new SampleSigner();
        $timestamp = new \DateTime();
        $timestamp->setTimestamp($stamp);

        $headers = array(
            "x-bce-date" => $x_bce_date,
            "Host" => "huidang1.tsdb.iot.gz.baidubce.com",
            "Content-Type" => "application/json; charset=utf-8",
            "Content-Length" => strlen($data)
        );

        $options = array(SignOption::TIMESTAMP => $timestamp);

        $Authorization = $signer->sign($credentials, $httpMethod, $path, $headers, $params, $options);
        $headers["Authorization"] = $Authorization;
        date_default_timezone_set($timezone);
    }


    //输入数据array(
    //    array('metric'=>$metric,'value'=$value,'tags'=$tags),
    //    array('metric'=>$metric1,'value'=$value1,'tags'=$tags1)
    //      )
    //$tags也是一个array('key'=>val,'key1'=>val1)
    public function insert($data){
        $datapoints=array(
            "datapoints"=>array()
        );
        $hicid=HICInfo::getHICID();
        
        foreach($data as $item){
            $itme['tags']['hicid']=$hicid;
            $datapoints['datapoints'][]=array(
                "tags"=>$itme['tags'],
                "timestamp"=>time(),
                "metric"=>$item['metric'],
                "value"=>round(doubleval($item['$value']),2),
//                    "field"=>"direction",
                "type"=>"Double"
            );
        }

        $datapoints=json_encode($datapoints);

        $httpMethod = "POST";
        $this->common($signer,$headers,$httpMethod,$datapoints);
        $this->curl(self::$url,$datapoints,$headers,$httpMethod);
    }

    //tags是一个array
    public function query($start,$end,$sampling,$metric,$tags=array(),$func='Avg'){
        $hicid=HICInfo::getHICID();
        $data =array(
            "queries"=>array(
                array(
                    "aggregators"=>array(
                        array(
                            "name"=>ucfirst(strtolower($func)),
                            "sampling"=>$sampling
                        )
                    ),
                    "metric"=>$metric,
                    //    "field"=>"direction",
                    "limit"=>1000,
                    "filters"=>array(
                        "start"=>$start,
                        "end"=>$end,
                        // "value"=>">= 0",
                        "tags"=>array(
                            "hicid"=>array(
                                $hicid
                            )
                        )
                    ),
//                    "groupBy"=>array(
//                        array(
//                            "name"=>"Tag",
//                            "tags"=>array(
//                                "hicid"
//                            )
//                        )
//                    )
                )
            )
        );


        foreach($tags as $key=>$value){
            $data['queries'][0]['filters']['tags'][$key]=array($value);
        }
        $data=json_encode($data);
        $httpMethod = "PUT";
        $params = array('query' => '');
        $this->common($signer,$headers,$httpMethod,$data,$params);
        foreach ($params as $key =>$val){
            $url=self::$url.$key."=".$val."&";
        }

        $res=$this->curl($url,$data,$headers,$httpMethod);
        return $res;
    }


}

?>