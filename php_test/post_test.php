<?php
	function http_post_data($url, $data) {  
       
       //将数组转成json格式
       $data = json_encode($data);
       
       //1.初始化curl句柄
        $ch = curl_init(); 
        
        //2.设置curl
        //设置访问url
        curl_setopt($ch, CURLOPT_URL, $url);  
        
        //捕获内容，但不输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        //模拟发送POST请求
        curl_setopt($ch, CURLOPT_POST, 1);  
        
        //发送POST请求时传递的参数
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        
        //设置头信息
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(  
            //'Content-Type: application/x-www-form-urlencoded', //file_get_contents("php://input"); 
            'Content-Type: application/json;charset=utf-8',  
            //$_POST
            'Content-Length: ' . strlen($data))  
        );  
 
        //3.执行curl_exec($ch)
        $return_content = curl_exec($ch);  
        
        //如果获取失败返回错误信息
        if($return_content === FALSE){ 
            $return_content = curl_errno($ch);
        }
        
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
        
        //4.关闭curl
        curl_close($ch);
        
        return array($return_code, $return_content);  
    }  
  
//$url  = "http://www.tuling123.com/openapi/api";  
$url  = "http://39.108.188.13/post_test.php";    
$data = array("key"=>"KEY",
     "info"=>"明天天气怎么样？",
     "loc"=>"满洲里市");   

list($return_code, $return_content) = http_post_data($url, $data);

var_dump(json_decode($return_content,true));  
