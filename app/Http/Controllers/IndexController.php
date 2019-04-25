<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Support\Facades\Redis;
use DB;
use Illuminate\Http\Request;
use App\Http\Utils\Tools;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use \Exception;

class IndexController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    protected $page = 0;
    protected $size = 0;

    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
        $this->page = empty($request->page) ? 1 : $request->page;
        $this->size = empty($request->size) ? 10 : $request->size;
        if ($this->size > 30) $this->size = 20;
    }


    /** 
     * 积分任务列表
     * @return [type] [description]
     */
    public function integralTaskList(Request $request)
    {
        $data["data"] = [];
        $data["code"] = 200;
        $data["message"] = '请求成功';
        $data["total"] = 0;
        $query = DB::connection('mysql_oc2018')->table('integral_task_list');
        $alltotal = $query->count();
        $data["total"] = $alltotal;
        $rs = $query->orderBy("id", 'desc')->offset($this->size * ($this->page - 1))->limit($this->size)->get();
        foreach ($rs as $key => $value) {
            $temp["name"] = $value->name;
            $temp["type"] = $value->type;
            $temp["created_time"] = $value->created_time;
            $temp["remark"] = $value->remark;
            array_push($data["data"], $temp);
        }
        return HttpUtil::retrun_json($data, 200);
    }
}
