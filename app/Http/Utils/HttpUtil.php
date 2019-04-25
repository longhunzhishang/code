<?php

namespace App\Http\Utils;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

/**
* fyj
* 20160629
* 公共函数类
*/
class HttpUtil
{
	/**
	 * 返回 json
	 * fyj
	 * @param  [type] $data   数据
	 * @param  [type] $msg    提示信息
	 * @param  [type] $status  成功 或者 失败
	 */
	public static function retrun_json($data,$status)
	{
		 return (new Response($data, $status))
              ->header('Content-Type', 'application/json');
	}

	public static function retrun_javascript($data,$status)
	{
		 return (new Response($data, $status))
		 	->header('Content-Type', 'application/javascript');
	}

	/**
	 * 返回 数据格式
	 * @return [type] [description]
	 */
	public static function returnStatus($status)
	{
		return [
			'data'=>[],
			'status'=>$status
		];
	}
	
    /**
	 * 返回 数据格式
	 * @return [type] [description]
	 */
	public static function returnCode($code,$message,$data =[])
	{
		return [
			'data'=>$data,
			'message'=>$message,
			'code'=>$code
		];
	}
	


	/**
	 * 发送 curl get 请求
	 * @param  [type] $url   [description]
	 * @param  [type] $param [description]
	 * @return [type]        [description]
	 */
	public static function send_curl_get($url,$param)
	{
		$ch = curl_init($url);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true) ; // 获取数据返回  
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true) ; // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回  
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($param));  
        $es_rs = curl_exec($ch) ;  
        curl_close($ch);
        return $es_rs;
	}
	public static function send_orc_curl_get($url)
	{
		$ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true) ; // 获取数据返回  
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true) ;
        $es_rs = curl_exec($ch) ;  
        curl_close($ch);
        return $es_rs;
	}

	public static function send_curl_post($url,$param)
	{
		if(!ESUtil::getEsStatus())$url = str_replace('192.168.1.47', '192.168.1.58', $url);
		
		$ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//1 输出 html   
        curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
        curl_setopt($ch, CURLOPT_POST, true);  
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($param));  
        $result = curl_exec($ch);
        curl_close($ch);

        // if(empty($result))
        // {
        // 	$url = self::reSendUrl($url);
        // 	return self::re_send_curl_methods($url,$param,"POST");
        // 	// return self::re_send_curl_post($url,$param);
        // 	exit;
        // }
        return $result;
	}
	/**
	 * sql 查询
	 * @param  [type] $url [description]
	 * @param  [type] $sql [description]
	 * @return [type]      [description]
	 */
	public static function send_curl_post_sql($url,$sql)
	{
		if(!ESUtil::getEsStatus())$url = str_replace('192.168.1.47', '192.168.1.58', $url);

		$ch = curl_init(); //初始化CURL句柄 
        curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
        curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); //设置请求方式
        // curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sql);//设置提交的字符串
        $rs = curl_exec($ch);//执行预定义的CURL 
        curl_close($ch);
        return $rs;
	}
	
	/**
	 * 
	 * @param  [type] $url    [description]
	 * @param  [type] $data   [json 格式 数据]
	 * @param  [type] $method [description]
	 * @return [type]         [description]
	 */
	public static function send_curl_methods($url,$data,$method){
		
         $re_key = serialize($data);
		 $re_key = md5($re_key.$url);
		 $data1 = Redis::get($re_key);
         if (!empty($data1)) {
         	return $data1 ;
        }
		if(!ESUtil::getEsStatus())$url = str_replace('192.168.1.47', '192.168.1.58', $url);


		// echo $url;
		// exit;
	    $ch = curl_init(); //初始化CURL句柄 
	    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
	    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
	    // curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
	    $rs = curl_exec($ch);//执行预定义的CURL 
	    curl_close($ch);
        Redis::setex($re_key, 100, $rs);
	    return $rs;
	}

	public static function send_curl_methods_v1($url,$data,$method){
		if(!ESUtil::getEsStatus())$url = str_replace('192.168.1.47', '192.168.1.58', $url);
		// echo $url;
		// exit;
	    $ch = curl_init(); //初始化CURL句柄 
	    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
	    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
	    // curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
	    $rs = curl_exec($ch);//执行预定义的CURL 
	    curl_close($ch);
	    return $rs;
	}
	/**
	 * 交换 IP 地址 重新请求数据
	 * @param  [type] $url    [description]
	 * @param  [type] $data   [description]
	 * @param  [type] $method [description]
	 * @return [type]         [description]
	 */
	public static function re_send_curl_methods($url,$data,$method)
	{
		// echo $url;exit;
		$ch = curl_init(); //初始化CURL句柄 
	    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
	    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
	    // curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
	    $rs = curl_exec($ch);//执行预定义的CURL 
	    curl_close($ch);
	    return $rs;
	}
	/**
	 * pull data to es
	 * @param  [type] $url    [description]
	 * @param  [type] $data   [description]
	 * @param  [type] $method [description]
	 * @return [type]         [description]
	 */
	public static function send_put_methods($url,$data)
	{
		$ch = curl_init(); //初始化CURL句柄 
	    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
	    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); //设置请求方式
	    // curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
	    $rs = curl_exec($ch);//执行预定义的CURL 
	    curl_close($ch);
	    return $rs;
	}
	/**
	 * 处理请求数据
	 * @param  [type] $index      [索引]
	 * @param  [type] $curl_param [请求体]
	 */
	public static function parse_curl_search($index,$curl_param)
	{
		$url = config('param.ES_URL');
		if(empty($index))
		{
			$url = $url.'_analyze?analyzer=ik';
		}else{
			$url = $url.$index.'/'.config('param.ES_SEARCH');
		}
        return self::send_curl_methods($url,$curl_param,"POST");
	}


	/**
	 * type 1 特殊字段 0 所有字段
	 * @param  [type] $index      [description]
	 * @param  [type] $curl_param [description]
	 * @param  [type] $type       [description]
	 * @return [type]             [description]
	 */
	public static function parse_curl_search_type($index,$curl_param,$type)
	{
		$url = config('param.ES_URL');
		if(empty($type))
		{
			$url = $url.$index.'/'.config('param.ES_SEARCH');
		}else{
			$url = $url.$index.'/'.config('param.ES_SEARCH').'?_source=id,host,cname,ename,role,md5,platform';
		}
        return self::send_curl_methods($url,$curl_param,"POST");
	}
	/**
	 * 替换 url
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	public static function reSendUrl($url)
	{
		if(in_array($_SERVER['SERVER_ADDR'], config('param.ES_HOST_IN')))
		{
			if(stristr($url,'192.168.1.47')!==FALSE)
			{
				$url = str_replace("192.168.1.47","192.168.1.58",$url);
			}else{
				$url = str_replace("192.168.1.58","192.168.1.47",$url);
			}
		}
		return $url;
	}

	//=================暂时没有使用 下个版本可以丢弃=========================
	
	public static function re_send_curl_post($url,$param)
	{
		$ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//1 输出 html   
        curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
        curl_setopt($ch, CURLOPT_POST, true);  
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($param));  
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
	}
	public function send_curl_methods2($url,$data,$method)
	{
		$ch = curl_init(); //初始化CURL句柄 
	    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
	    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
	    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
	    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
	    $rs = curl_exec($ch);//执行预定义的CURL 
	    curl_close($ch);
	    return true;
	}

}