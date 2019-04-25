<?php

		$curl_param = [
            'aggs'=>[
                'max_id'=>[
                    'max'=>[
                        'field'=>'id'
                    ]
                ]
            ]
        ];
        $url = 'http://localhost:9200/mains/main_index/_search';


        $ch = curl_init(); //初始化CURL句柄 
	    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
	    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); //设置请求方式
	     
	    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_param));//设置提交的字符串
	    $rs = curl_exec($ch);//执行预定义的CURL 
	    curl_close($ch);

        $es = json_decode($rs,true);


        if(empty($es['aggregations']['max_id']['value']))exit();
        $max_id = $es['aggregations']['max_id']['value'];

        $max_id = intval($max_id);

        if($max_id)
        {
        	system('/home/elasticsearch-jdbc-2.3.2.0/bin/import_mains_index.sh '.$max_id);
        }