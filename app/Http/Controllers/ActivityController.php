<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use DB;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        if (time() >= strtotime("2018-11-12")) { //活动截止了
            $infor = 0;
            header('Location: http://www.adbug.cn/#/?infor=' . $infor.'&username='.$request->username);
            exit;
        }
        if (empty($request->id)) {
            $infor = 1;  //请注册用户
            header('Location: http://www.adbug.cn/#/?infor=' . $infor.'&username='.$request->username);
            exit;
        }
        $rs = DB::connection('mysql_oc2018')->table('users')
            ->where('id', $request->id)->first();
        if (!isset($rs)) {
            $infor = 1; //请注册用户
            header('Location: http://www.adbug.cn/#/?infor=' . $infor.'&username='.$request->username);
            exit;
        }
        $rs1 = DB::connection('mysql_oc2018')->table('activity')
            ->where("user_id", $request->id)
            ->first();
        if (isset($rs1)) {
            $infor = 2; //该用户已经领用过了
            header('Location: http://www.adbug.cn/#/?infor=' . $infor.'&username='.$request->username);
            exit;
        }
        if ($rs->vip_deadline == null) {
            $vip_start_time = date("Y-m-d H:i:s", time());
            $vip_deadline = date("Y-m-d H:i:s", strtotime("+7 day"));
            DB::connection('mysql_oc2018')->table('users')->where("id", $rs->id)->update(["role" => 1, "vip_start_time" => $vip_start_time, "vip_deadline" => $vip_deadline]);
        } else {
            $vip_deadline = date("Y-m-d H:i:s", strtotime("+7 day", strtotime($rs->vip_deadline)));
            DB::connection('mysql_oc2018')->table('users')->where("id", $rs->id)->update(["vip_deadline" => $vip_deadline]);
        }
        DB::connection('mysql_oc2018')->table('activity')->insert(["user_id" => $request->id, "created_at" => time()]);
        $infor = 3; //领取成功
        header('Location: http://www.adbug.cn/#/?infor=' . $infor.'&username='.$request->username);
        exit;

    }

}
