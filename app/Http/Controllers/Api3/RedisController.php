<?php

namespace App\Http\Controllers\Api3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * finn
 * 20160727
 * redis api
 */
class RedisController extends Controller
{
   
   /**
    * 保存用户搜索内容 便于 后期 24小时查询
    */
    public function addRedis(Request $request)
    {
//        $wd = empty($request->wd)?'':$request->wd;
//
//        if(empty($wd))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
//
//
//
//    	$key = strtotime(date('YmdHis',time()));
//        Rediss::set($key,$wd);
//        $rs = DBUtil::findRedis();
//    	return HttpUtil::retrun_json($rs,200);
    }

    /**
     * 获取 24 小时所有 查询数据
     * 返回 搜索 次数 最多的 20条数据
     * @return [type] [description]
     */
    public function findRedis(Request $request)
    {
//    	$now_time = strtotime(date('YmdHis',time()-8400));
//
//    	$all_keys = $redis->KEYS('*');
//
//        $tem_da = [];
//    	$tmp_all_rs = [];
//    	foreach ($all_keys as $key => $value) {
//
//            $re = $redis->SMEMBERS($value);
//            // $tem_da[] = $re[0];
//    		if($value>=$now_time && $re[0])
//    		{
//    			$tmp_all_rs[] = $re[0];
//    		}
//    	}
//
//    	$all_rs = [];
//    	$tmp = [];
//    	foreach ($tmp_all_rs as $key => $value) {
//
//    		if(empty($tmp[$value]))
//    		{
//    			$tmp[$value] = 1;
//    		}else{
//    			$tmp[$value]+=1;
//    		}
//    	}
//
//    	$max = array_search(max($tmp), $tmp);
//		$max_rs = $tmp[$max];
//
//		$tmp_count = count($tmp);
//
//		arsort($tmp);
//		$tmp_count= count($tmp);
//		$data = [];
//		if($tmp_count > 30)
//		{
//			foreach ($tmp as $key => $value) {
//				# code..
//				if(count($data) < 30)
//				{
//					$data[] = $key;
//				}
//			}
//		}else{
//			foreach ($tmp as $key => $value) {
//				# code..
//				$data[] = $key;
//			}
//		}
//        dd($data);
//
//		return HttpUtil::retrun_json($data,200);

    }



    /**
     * 记录 用户 访问时的 cookies
     */
    public function addCookis(Request $request)
    {

        // $wd = empty($request->wd)?'':$request->wd;
        // $cookis = empty($request->cookis)?'':$request->cookis;



        // if(empty($wd) || empty($cookis))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        // $cookis = json_decode($cookis,true);

       
        // $h_u_a = $cookis['HTTP_USER_AGENT'];
        // $h_a = $cookis['HTTP_ACCEPT'];

        // $h_a_l = $cookis['HTTP_ACCEPT_LANGUAGE'];
        // $h_c = $cookis['HTTP_COOKIE'];

        // $key = $h_u_a.'_ADBUG_API_ES_'.$h_a.'_ADBUG_API_ES_'.$h_a_l.'_ADBUG_API_ES_'.$h_c;
        // $key = md5($key);

//        $redis = new Redis();
//        $redis->connect('127.0.0.1',6379);
//
//        $all = $request->all();
//
//        $rs = $redis->sAdd('ADBUG_API_ES_COOKIERS',json_encode($all));

        // return $all;
        // $re = $redis->SMEMBERS('ffff');

        // dd(json_decode($re[0]));
        
    }

    /**
     * 记录 用户 访问时的 cookies
     */
    public function getCookis(Request $request)
    {

//        $redis = new Redis();
//        $redis->connect('127.0.0.1',6379);
//
//       $all_keys = $redis->KEYS('COOKIS_KEYS*');
//
//        foreach ($all_keys as $key => $value) {
//
//            $re = $redis->GET($value);
//            print_r(json_decode($re,true));
//            echo '<br/>';
//
//        }

    }
}
