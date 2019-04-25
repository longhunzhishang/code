<?php

namespace App\Models;
use DB;
use Illuminate\Database\Eloquent\Model;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use Excel;
use phpDocumentor\Reflection\Element;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/3
 * Time: 14:02
 */

class OrderModel extends Model{

    /**
     * 查询推荐人信息
     * @param $userId   用户id
     * @return mixed
     */

    public function getUser($userId)
    {

            $arr=DB::connection('mysql_oc2018')->table('users')
                ->where("users.id",$userId)
                ->first();
            return $arr;
    }

    /**
     * 查询订单
     * @param $request
     * @return array
     */

    public function getOrderlist($request,$type){
        $code_id=DB::connection('mysql_oc2018')->table('pay_code_copy')->where("user_id",$request->userId)->value('id');
         if(empty($request->where)){
                 if(!empty($code_id) && $type==2){
                     $arr=DB::connection('mysql_oc2018')->table('order_detail')
                         ->join('users', 'users.id', '=', 'order_detail.buyer_id')
                         ->select('users.id','users.email', 'order_detail.updated_time','order_detail.code_id', 'order_detail.sno','users.role','order_detail.amount','users.vip_start_time','users.vip_deadline',"order_detail.id as oid")
                         ->where('order_detail.code_id',$code_id)
                         ->where('order_detail.status',1)
                         ->where('order_detail.is_del',1)
                         ->orderBy('order_detail.updated_time','desc')
                         ->get();
                 }else{
                     $arr=DB::connection('mysql_oc2018')->table('order_detail')
                         ->join('users', 'users.id', '=', 'order_detail.buyer_id')
                         ->select('users.id','users.email', 'order_detail.updated_time','order_detail.code_id', 'order_detail.sno','users.role','order_detail.amount','users.vip_start_time','users.vip_deadline',"order_detail.id as oid")
                         ->where('order_detail.status',1)
                         ->where('order_detail.is_del',1)
                         ->orderBy('order_detail.updated_time','desc')
                         ->get();
                 }

                  $arr=$this->obj_array($arr);
                  if(!empty($arr)){
                      $arr2=$this->getCode($arr);
                      $arr=$this->getPhone($arr2);
                  }

         }else{
                 $where6=$request->where;
                 $where = json_decode($where6[0],true);
                 $where1="`order_detail`.`status` = 1 and `order_detail`.`is_del`=1";
                 if(!empty($code_id) && $type==2){
                      $where1.=" and `order_detail`.`code_id`="."'".$code_id."'";
                 }
                 $where2="";
                 if(!empty($where['user_Id'])){$where2 .=' or order_detail.buyer_id=\''.$where['user_Id'].'\'';}
                 if(!empty($where['email'])){$where2 .=' or users.email=\''.$where['email'].'\'';}
                 if(!empty($where['begin_time']) && !empty($where['end_time'])){$where2 .=' or order_detail.updated_time>=\''.strtotime($where['begin_time']).'\'';$where2 .=' and order_detail.updated_time<=\''.strtotime($where['end_time']).'\'';}
                 if(!empty($where['sno'])){$where2 .=' or order_detail.sno=\''.$where['sno'].'\'';}
                 if(!empty($where['role'])){$where2 .=' or users.role=\''.$where['role'].'\'';}
                 if(!empty($where['amount'])){$where2 .=' or order_detail.amount=\''.$where['amount'].'\'';}
                 if(!empty($where2)){$where3=substr($where2 , 3);$where4=$where1." and (".$where3.")";}else{$where4=$where1;}
                 $arr= DB::connection('mysql_oc2018')->select("select `users`.`id`, `users`.`email`, `order_detail`.`code_id`,`order_detail`.`updated_time`, `order_detail`.`sno`, `users`.`role`, `order_detail`.`amount`, `users`.`vip_start_time`, `users`.`vip_deadline`,`order_detail`.`id` as oid from `users` inner join `order_detail` on `users`.`id` = `order_detail`.`buyer_id` where ".$where4." ORDER BY `order_detail`.`updated_time` desc");
                 $arr=$this->obj_array($arr);
                 if(!empty($arr)){
                     $arr2=$this->getCode($arr);
                     $arr=$this->getPhone($arr2);
                 }
                     if(!empty($where['vip_effective_time']) && $where['vip_effective_time']=="一月"){
                         foreach($arr as $key=>$val){
                             $day = floor((strtotime($val['vip_deadline']) - strtotime($val['vip_start_time'])) / 86400);
                             if ($day >364 || $day==0) {unset($arr[$key]);

                             }
                         }
                     }
                     if(!empty($where['vip_effective_time']) && $where['vip_effective_time']=="一年"){
                         foreach($arr as $key=>$val){
                             if(empty($val['vip_deadline']) || empty($val['vip_start_time'])){
                                 unset($arr[$key]);
                             }else{
                                 $day=floor((strtotime($val['vip_deadline'])-strtotime($val['vip_start_time']))/86400);
                                 if($day<365){unset($arr[$key]);}
                             }
                         }
                     }
               if(!empty($where['channel_code'])){
                 foreach($arr as $key=>$val){
                     if($val['channel_code']!=$where['channel_code']){unset($arr[$key]);}
                 }
               }
             if(!empty($where['channel_name'])){
                 foreach($arr as $key=>$val){
                     if($val['channel_name']!=$where['channel_name']){unset($arr[$key]);}
                 }
             }
             if(!empty($where['phone'])){
                 foreach($arr as $key=>$val){
                     if($val['phone']!=$where['phone']){unset($arr[$key]);}
                 }
             }

         }
        $sum_amount=0;
         if(!empty($arr)){
                 foreach($arr as $k=>$v){
                      $arr[$k]['updated_time']=date('Y-m-d H:i:s',$v['updated_time']);
                      if(empty($v['vip_deadline']) || empty($v['vip_start_time'])){
                          $arr[$k]['vip_effective_time']="";
                      }else{
                          $day=floor((strtotime($v['vip_deadline'])-strtotime($v['vip_start_time']))/86400);
                          if($day==30 || $day==31 ||$day==28 || $day==29){
                              $arr[$k]['vip_effective_time']="一月";
                          }else{
                              $arr[$k]['vip_effective_time']="一年";
                          }
                      }
                      unset($arr[$k]['vip_start_time']);
                      unset($arr[$k]['vip_deadline']);
                      $sum_amount+=$v['amount']-$v['price'];
                 }
         }
        if($request->userId==4627){
            foreach ($arr as $key=>$value){
                if(empty($value['channel_name']) || empty($value['channel_code']) || empty($value['coupon_code'])){
                    unset($arr[$key]);
                }
            }
        }
        $arr1=[];
        foreach ($arr as $k=>$v){
            $arr1[]=$v;
        }
        $info= DB::connection('mysql_oc2018')->table('pay_code_copy')->select("token","price","channel_name","channel_code")->where("user_id",$request->userId)->get();
        if(!empty($info)){
            $info=$this->obj_array($info);
        }else{
            $info=[];
        }
         $data=[
             'arr'=>$arr1,
             'sum_amount'=>$sum_amount,
             'info'=>$info
         ];
         return $data;
    }

    /**
     *  查询订单的邀请码
     * @param $array
     * @return mixed
     */

    public function getCode($array)
    {

        foreach($array as $k=>$v){
            if($v['code_id']==0){
               $array[$k]['price']=0;
               $array[$k]['channel_code']=0;
               $array[$k]['coupon_code']=0;
               $array[$k]['channel_name']=0;
            }else{
               $arr= DB::connection('mysql_oc2018')->table("pay_code_copy")
                //   ->join('pay_channel_code', 'pay_channel_code.channel_code', '=', 'pay_code_copy.channel_code')
                 //  ->select("pay_channel_code.price","pay_code_copy.channel_code","pay_code_copy.channel_name")
                   ->where("pay_code_copy.id",$v['code_id'])
                   ->first();
                $info=$this->obj_array1($arr);
                $array[$k]['price']=$info['price'][0];
                $array[$k]['channel_code']=$info['channel_code'][0];
                $array[$k]['coupon_code']=$info['coupon_code'][0];
                $array[$k]['channel_name']=$info['channel_name'][0];
            }
        }
         return $array;

    }


    /**
     * 查询用户手机号
     */

    public function getPhone($array)
    {
        foreach($array as $k=>$v){
                $phone= DB::connection('mysql_oc2018')->table("user_phone_list")
                    ->where("user_id",$v['id'])
                    ->value('phone');
                 if($phone)
                 {
                     $array[$k]['phone']=$phone;

                 }else{
                     $array[$k]['phone']="";
                 }
            }
        return $array;
    }
    /**
     * 删除用户订单（批删，单删）
     * @param $oid 订单id
     */

    public function orderDelect($oid)
    {
        $orderId = implode(',', $oid);
        $res = DB::connection('mysql_oc2018')->update("update order_detail set is_del=2 where id in (" . $orderId . ")");
        if ($res) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * 推荐人信息查询
     * @param $order_email
     * @return mixed
     */
    public function getOrder($order_email)
    {
        $arr=DB::connection('mysql_oc2018')->table('users')
            ->join("pay_code_copy",'users.id', '=', 'pay_code_copy.user_id')
            ->where("users.email",$order_email)
            ->where("users.type",1)
            ->select('users.*','pay_code_copy.id as code_id','pay_code_copy.channel_code')
            ->first();
        return $arr;
    }

    /**
     *添加订单
     * @param $request
     * @return bool
     */

    public function addNeworder($request)
    {
        $id=DB::connection('mysql_oc2018')->table('users')
            ->where("users.email",$request->email)
            ->value("id");
        $data=[
            'sno'=>$request->sno,
            'out_trade_no'=>$request->sno.'a'.rand(100000,999999),
            'vip_level'=>$request->vip_level,
            'time_length'=>$request->vip_effective_time,
            'amount'=>$request->amount,
            'pay_type'=>$request->pay_type,
            'status'=>1,
            'created_time'=>$request->created_time,
            'updated_time'=>$request->updated_time,
            'buyer_id'=>$id,
            'code_id'=>$request->code_id,
            'code'=>0
        ];
        $res=DB::connection('mysql_oc2018')->table('order_detail')->insert($data);
        if($res){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取要下载数据
     * @param $oid
     * @return array|mixed
     */

    public function getDownload($oid)
    {
        $arr=DB::connection('mysql_oc2018')->table('order_detail')
            ->join('users', 'users.id', '=', 'order_detail.buyer_id')
            ->join('user_phone_list', 'users.id', '=', 'user_phone_list.user_id')
            ->select('users.id','users.email','user_phone_list.phone', 'order_detail.updated_time','order_detail.code_id', 'order_detail.sno','users.role','order_detail.amount','users.vip_start_time','users.vip_deadline',"order_detail.id as oid")
            ->whereIn('order_detail.id',$oid)
            ->get();
        $arr=$this->obj_array($arr);
        $arr=$this->getCode($arr);
        foreach($arr as $k=>$v){
            $arr[$k]['updated_time']=date('Y-m-d H:i:s',$v['updated_time']);
            $day=floor((strtotime($v['vip_start_time'])-strtotime($v['vip_deadline']))/86400);
            if($day==30 || $day==31 ||$day==28 || $day==29){
                $arr[$k]['vip_effective_time']="一月";
            }else{
                $arr[$k]['vip_effective_time']="一年";
            }
            unset($arr[$k]['vip_start_time']);
            unset($arr[$k]['vip_deadline']);
            unset($arr[$k]['code_id']);
            unset($arr[$k]['oid']);
        }
        return $arr;
    }

    /**
     * 订单信息写入excel中
     * @param $cellData
     */

    public function orderDownload($Data)
    {
        $cellData=[
            ['用户id','邮箱','手机号','时间','订单号','会员等级','充值金额','优惠金额','渠道码','兑换码','渠道名称','有效期']
        ];
        foreach($Data as $k=>$v){
            $item['id']=$v['id'];
            $item['email']=$v['email'];
            $item['phone']=$v['phone'];
            $item['updated_time']=$v['updated_time'];
            $item['sno']=$v['sno'];
            $item['role']=$v['role'];
            $item['amount']=$v['amount'];
            $item['price']=$v['price'];
            $item['channel_code']=$v['channel_code'];
            $item['coupon_code']=$v['coupon_code'];
            $item['channel_name']=$v['channel_name'];
            $item['vip_effective_time']=$v['vip_effective_time'];
            array_push($cellData, $item);
        }
        Excel::create("订单信息",function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('xls');
    }
    /**
     * object转化array
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

    /**
     * object转化array
     * @param $obj  object
     * @return array
     */

    public function obj_array1($obj)
    {
        if(empty($obj)){$obj=[];}
        foreach ($obj as $key => &$value) {
            $array[$key] = (array)$value;
        };
        return $array;
    }

}