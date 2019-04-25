<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/16
 * Time: 14:43
 */
namespace App\Models;
use DB;
use Illuminate\Database\Eloquent\Model;

class IntegrationModel extends Model
{

    /**
     * 积分添加
     * @param Request $request
     * @return mixed
     */
     public function igCount($request)
     {
         $arr=DB::connection('mysql_oc2018')->table('wechat_share_log')
             ->where("wechat_account",$request->wechat_account)
             ->where("md5",$request->md5)
             ->first();

         if(empty($arr)){
             $arr1=DB::connection('mysql_oc2018')->table('integral_user_list')
                  ->where("wechat_account",$request->wechat_account)
                  ->first();
             $amount=$this->getTask("微信转发",1);
             if(!empty($arr1)){
                    DB::connection('mysql_oc2018')->table('integral_user_list')
                     ->where('wechat_account',$request->wechat_account)
                     ->increment('integral_sum',$amount);
             }else{
                     DB::connection('mysql_oc2018')->table("integral_user_list")->insert([
                         'user_id' => 0,
                         'wechat_account' => $request->wechat_account,
                         'integral_sum' => $amount,
                         'created_time'=>date('Y-m-d H:i:s')
                     ]);
             }
         }

         return true;
     }

    /**
     * 根据类型来获取积分
     * @param $name  广告分享方式（微信转发）
     * @param $type   广告分享类型  （转发）
     * @return mixed
     */
     public function getTask($name,$type){
         return DB::connection('mysql_oc2018')->table("integral_task_list")
             ->where("name",$name)
             ->where("type",$type)
             ->value('amount');
     }

    /**
     * object转化二维array
     * @param $obj  object
     * @return array
     */

    public function obj_array($obj)
    {
        $array=[];
        foreach ($obj as $key => &$value) {
            $array[] = (array)$value;
        };
        return $array;
    }
}