<?php
namespace App\Http\Controllers;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;

use Illuminate\Http\Request;
use App\WxPayConfig;

class WXpayController extends Controller
{
    /**
     * 解决跨域请问问题
     */
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
    }

    /**
     *
     * 微信订单支付
     * 400,订单支付成功 411 订单已经支付过了  412,缺少参数 ,413,订单不存在 414,订单号不符规则 415,订单未支付 416 订单异常
     */
    public function index(Request $request)
    {
        if (empty($request->sno)) return HttpUtil::retrun_json(['status' => 412, 'message' => "缺少参数"], 200);
        $out_trade_no = $request->sno . "a" . date("His");//订单编号
        $rs = DB::connection('mysql_oc2018')->table('order_detail')->where("sno", $request->sno)->first();
        if (!$rs) return HttpUtil::retrun_json(['status' => 413, 'message' => "订单不存在"], 200);
        if ($rs->status == 1) HttpUtil::retrun_json(['status' => 411, 'message' => "订单已经支付过了,请勿生成二维码"], 200);
        if (DB::connection('mysql_oc2018')->table('order_detail')->where("sno", $request->sno)->update(["out_trade_no" => $out_trade_no])) {
            $rs = DB::connection('mysql_oc2018')->table('order_detail')->where("sno", $request->sno)->first();
        }
        $define = "adbug企业会员收费";
        $free = $rs->amount * 100;//订单价格
        $input = new \WxPayUnifiedOrder();
        $input->SetBody($define);
        $input->SetAttach($define);
        $input->SetOut_trade_no($out_trade_no);
        $input->SetTotal_fee($free);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag($define);
        $input->SetNotify_url("http://testapi.adbug.cn/wx/notify");
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id("123456789");
        $result = $this->GetPayUrl($input);
        $data["data"] = "http://testapi.adbug.cn/qrcode.php?data=" . urlencode($result["code_url"]);
        $data["status"] = 200;
        return HttpUtil::retrun_json($data, 200);
    }

    /**
     *
     * 生成直接支付url，支付url有效期为2小时,模式二
     * @param UnifiedOrderInput $input
     */
    public function GetPayUrl($input)
    {
        if ($input->GetTrade_type() == "NATIVE") {
            try {
                $config = new WxPayConfig();
                $result = \WxPayApi::unifiedOrder($config, $input);
                return $result;
            } catch (Exception $e) {
                Log::ERROR(json_encode($e));
            }
        }
        return false;
    }


    /**
     * 备注:js需要定时器执行该接口,直到支付成功之后,停止执行
     * 微信订单查询以及处理支付之后的逻辑
     * @param UnifiedOrderInput $input
     * 400,订单支付成功 411 订单已经支付过了  412,缺少参数 ,413,订单不存在 414,订单号不符规则 415,订单未支付 416 订单异常 417 微信提示
     */
    public function OrderQuery(Request $request)
    {
        if (empty($request->sno)) return HttpUtil::retrun_json(["status" => 412, "message" => "缺少参数"], 200);
        $rs = DB::connection('mysql_oc2018')->table('order_detail')->where("sno", $request->sno)->first();
        if (!$rs) return HttpUtil::retrun_json(["status" => 413, "message" => "订单号不存在"], 200);
        if ($rs->status == 1) return HttpUtil::retrun_json(['status' => 411, 'message' => "订单已经支付过了,请勿重复支付"], 200);
        $input = new \WxPayOrderQuery();
        $input->SetOut_trade_no($rs->out_trade_no);
        $config = new WxPayConfig();
        $result = \WxPayApi::orderQuery($config, $input);
        if ($result["result_code"] == "FAIL" || $result["return_code"] == "FAIL") {
            $data["status"] = 417;
            $data["message"] = $result["err_code_des"];
        } elseif ($result["trade_state"] == "NOTPAY") {
            $data["status"] = 415;
            $data["message"] = $result["trade_state_desc"];
        } elseif ($result["trade_state"] == "SUCCESS" && DB::connection('mysql_oc2018')->table('order_detail')->where("sno", $request->sno)->update(["pay_type" => "2", "status" => "1", "updated_time" => time()])) {
            $user_id = $rs->buyer_id;
            $role = $rs->vip_level;
            $time_length = $rs->time_length;
            $lownloads = 0;
            $rs1 = DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->first();
            if ($rs1->vip_start_time == null) {
                $vip_start_time = date("Y-m-d H:i:s", time());
                $vip_deadline = date("Y-m-d H:i:s", strtotime("+" . $time_length . "month "));
                DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->update(["role" => $role, "vip_start_time" => $vip_start_time, "vip_deadline" => $vip_deadline, "downloads" => $lownloads,'character'=>1]);
            } else {
                 $vip_deadline = date("Y-m-d H:i:s", strtotime("+" . $time_length . "month ", strtotime($rs1->vip_deadline)));
                DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->update(["role" => $role, "vip_deadline" => $vip_deadline, "downloads" => $lownloads,'character'=>1]);
            }

            $data['message'] = "订单支付成功";
            $data['status'] = "400";
        }
        return HttpUtil::retrun_json($data, 200);

    }


    /**
     * 遍历订单是否支付
     */
    public function OrderUpdate(Request $request)
    {
        $data = DB::connection('mysql_oc2018')->table('order_detail')->where("status", 0)->orderBy("id", "desc")->limit(80)->get();
        foreach ($data as $key => $value) {
            $input = new \WxPayOrderQuery();
            $input->SetOut_trade_no($value->out_trade_no);
            $config = new WxPayConfig();
            $result = \WxPayApi::orderQuery($config, $input);
            if ($result["result_code"] == "FAIL" || $result["return_code"] == "FAIL" || $result["trade_state"] == "NOTPAY") continue;
            if ($result["trade_state"] == "SUCCESS" && DB::connection('mysql_oc2018')->table('order_detail')->where("id", $value->id)->update(["pay_type" => "2", "status" => "1", "updated_time" => time()])) {
                $user_id = $value->buyer_id;
                $role = $value->vip_level;
                $time_length = $value->time_length;
                $lownloads = 0;
                $rs1 = DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->first();
                if ($rs1->vip_start_time == null) {
                    $vip_start_time = date("Y-m-d H:i:s", time());
                    $vip_deadline = date("Y-m-d H:i:s", strtotime("+" . $time_length . "month "));
                    DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->update(["role" => $role, "vip_start_time" => $vip_start_time, "vip_deadline" => $vip_deadline, "downloads" => $lownloads,'character'=>1]);
                } else {
                    $vip_deadline = date("Y-m-d H:i:s", strtotime("+" . $time_length . "month ", strtotime($rs1->vip_deadline)));
                    DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->update(["role" => $role, "vip_deadline" => $vip_deadline, "downloads" => $lownloads,'character'=>1]);
                }
            }
        }
        echo "结束";
    }

    /**
     * 删除未支付的订单
     */
    public  function  OrderDelete(Request $request){
        DB::connection('mysql_oc2018')->table('order_detail')->where("status", 0)->orderBy("id", "asc")->limit(100)->delete();
        echo "成功";
    }


}



























































