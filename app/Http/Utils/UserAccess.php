<?php

namespace App\Http\Utils;
use Illuminate\Support\Facades\Auth;
use Search;
use DB;
use Elasticsearch\Client;
use Illuminate\Support\Facades\Redis;

/**
* fyj ES 工具类
* 20160624
*/
class UserAccess
{

   // 需要登录才可以访问的路由
    protected static $isAuthRoute = ["storeCheck","storeAdd","storeDel","downloadAdd","orderAdd","adsCheck","adsAudit"];

    // 需要VIP才可以访问的路由
    protected $isVipRoute = [];

    /**
     * 获取当前路由访问的方法
     * 20180910
     */
    public static  function routeInfo(){
        $action = app('request')->route()->getAction();
        $controller = '@';
        if (isset($action['controller'])) {
            $controller = class_basename($action['controller']);
        }

        list($routeControllerName, $routeActionName) = explode('@', $controller);
        return $routeActionName;

    }

    /**
     * 判断是否登录
     * 20180910
     */
    public  static function  isLogin($func){
        if(in_array($func,self::$isAuthRoute) && Auth::check() == false){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 获取当前用户信息
     * 20180910
     */
   public static function getUserInfo()
    {
        $user = Auth::user();
        if (!$user) return [];
        $data = $user->toArray();
        $referee_count = DB::connection('mysql_oc2018')->table('user_recommend_infor')->where('referee_id', '=', $data['id'])->count();
        $referee_count = empty($referee_count) ? "0" : $referee_count;
        $data["referee_count"] = $referee_count;
        $vreferee_count = DB::connection('mysql_oc2018')->table('user_recommend_infor')->where('referee_id', '=', $data['id'])->where("is_activation", "1")->count();
        $vreferee_count = empty($vreferee_count) ? "0" : $vreferee_count;
        $data["vreferee_count"] = $vreferee_count;
        $data["roleinfor"] = [];

        $role_infor = self::UserVipInfor($data["role"]);
        if($role_infor == null){
            $data["roleinfor"] = [];
        }else{
            $data_tmp = [];
            foreach (unserialize($role_infor) as $key =>$value){
                foreach ($value as $key1 =>$value1){
                    $data_tmp1[$key1] = $value1;
                }
                array_push($data_tmp, $data_tmp1);

            }
            $data["roleinfor"] = $data_tmp;
        }
        
        $data["phonenum"] = "";
        $phoners =  DB::connection('mysql_oc2018')->table('user_phone_list')->where('user_id', $data['id'])->first();
        if($phoners)$data["phonenum"] = $phoners->phone;
        
        $activity = DB::connection('mysql_oc2018')->table('activity')->where('user_id', '=', $data['id'])->first();
        $is_activity = empty($activity) ? "0" : "1";
        $data["is_activity"] = $is_activity;
        
        return $data;

    }


      public static function UserVipInfor($role)
    {
        if (empty(Redis::get($role . "_role11"))) {
            $data = DB::connection('mysql_oc2018')->table('function_ship')->where("role", $role)->first();
            $infor = isset($data) ? $data->function_detail : "";
            Redis::setex($role . "_role11", 3600, $infor);
        } else {
            $infor = Redis::get($role . "_role11");
        }
        return $infor;
    }


    public  static  function  isVip(){

    }










}