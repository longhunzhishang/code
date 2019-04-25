<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Http\Utils\UserAccess;
use DB;
use Illuminate\Http\Request;
use App\Http\Utils\Tools;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use App\Http\Utils\ESUtil;
use Excel;
use \Exception;

class AdminController extends Controller
{

    //adbug 用户登录 加密 key
    protected $user_key = 'oS1wcdz9ysuxalNhH5AXkWVC4vbFE7ZDYOfnMQPq';
    protected $page = 0;
    protected $size = 0;
    protected $from = 0;
    protected $aggs = [];
    protected $aggs_size = 0;
    protected $aggs_all = 0;
    protected $user = [];

    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
     //   $this->middleware('admintoken');
        $this->size = empty($request->size) ? config('param.SIZE') : $request->size;

        if ($this->size > 1200) $this->size = 1200;

        $this->page = empty($request->page) ? 1 : $request->page;

        $this->aggs_size = $this->size;
        $this->aggs_all = $this->page * $this->aggs_size;

        if ($this->aggs_all > 1200) $this->aggs_all = 1200;

        if ($this->page > 1) {
            $this->from = ($this->page - 1) * $this->size;
        }


    }

    /**author:zhujh
     * 更改广告创意的信息
     * id 广告创意id
     * host 广告主域名
     * title 广告标题
     * name 广告主名称
     * @return [type] [description]
     */
    public function adsinforupdate(Request $request)
    {
        $id = empty($request->id) ? "" : $request->id;
        $host = empty($request->advertiser_na) ? "" : $request->advertiser_na;
        $name = empty($request->advertiser_name_title) ? "" : $request->advertiser_name_title;
        $title = empty($request->title) ? "" : $request->title;
        try {
            if (empty($id) || empty($host) || empty($title)) throw new Exception("缺少参数");
            $rs = DB::table('domains')->where('host', $host)->first();
            if (!isset($rs)) {
                DB::table('domains')->insert(['host' => $host, 'md5' => md5($host)]);
                $rs = DB::table('domains')->where('host', $host)->first();
            }
            DB::beginTransaction();
            DB::table('addata_2018')->where('id', $id)->update(['title' => $title, 'advertiser' => $rs->host]);
            DB::table('main_index')->where('id', $id)->update(["advertiser" => $rs->id]);
            DB::commit();
            $curl_param = [
                "doc" => [
                    "advertiser_na" => $host,
                    "advertiser" => $host,
                    "domain_host" => $host,
                    "title_no" => $title,
                    "title" => $title,
                    "subject_title" => $title,
                    "advertiser_name" => $host,
                    "advertiser_name_title" => $name,
                    "advertiser_full" => $host . " " . $name
                ]
            ];
            $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . $id . '/_update';
            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $rs = json_decode($rs, true);
            if ($rs["_shards"]["failed"] == 0) {
                $data['message'] = "关联成功";
                $data['status'] = "200";
            } else {
                $data['message'] = "关联失败";
                $data['status'] = "211";
            }
        } catch (Exception $e) {
            DB::rollback();
            $data['message'] = $e->getMessage();
            $data['status'] = "211";
        }
        return HttpUtil::retrun_json($data, 200);
    }


    /**author:zhujh
     * 获取 ott 信息列表(新版本)
     * 满足首页需求
     * size 列表个数 默认 10 条 最大200
     * @return [type] [description]
     */
    public function getOttListNew(Request $request)
    {
        $size = empty($request->size) ? "10" : $request->size;
        $curl_param = [
            'size' => $size,
            'sort' => [
                'created_date' => [
                    'order' => 'desc'
                ]
            ],
            'query' => [
                'term' => [
                    "attribute06" => "ott"
                ]
            ],
        ];
        $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if (empty($data['data'])) {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data, 200);
        }
        $data = $this->getadvertiserDomain($data);
        $data['status'] = 200;
        $data['total'] = $size;
        return HttpUtil::retrun_json($data, 200);

    }

    /** author:zhujh
     * 统计最新注册人数以及注册人数详细信息
     * type 新增类型 0 所有用户 1今日新增用户  2本周新增用户 3 上周新增用户
     * @return [type] [description]
     */
    public function addnewcount(Request $request)
    {   
        $type = empty($request->type) ? "" : $request->type;
        $page = empty($request->page) ? "0" : $request->page;
        $wholeTotal = DB::connection('mysql_oc2018')->table('users')->count();
        $currentDayTotal = DB::connection('mysql_oc2018')->table('users')->where('created_at', '>=', date('Y-m-d', time()))->count();
        $currentWeekTotal = DB::connection('mysql_oc2018')->table('users')->where('created_at', '>=', date('Y-m-d', strtotime('this week')))->count();
        $lastWeekTotal = DB::connection('mysql_oc2018')->table('users')->whereBetween('created_at', [date('Y-m-d', strtotime("last week")), date('Y-m-d', strtotime('this week'))])->count();
        $data['currentDayTotal'] = $currentDayTotal;
        $data['currentWeekTotal'] = $currentWeekTotal;
        $data['lastWeekTotal'] = $lastWeekTotal;
        $data['allTotal'] = $wholeTotal;
        if (!empty($request->search_email)) {
            $query = DB::connection('mysql_oc2018')->table('users')->where('email', 'like', '%' . $request->search_email . '%');
        } else {
            if ($type == 1) {
                $query = DB::connection('mysql_oc2018')->table('users')->where('created_at', '>=', date('Y-m-d', time()));
            } elseif ($type == 2) {
                //$query = DB::connection('mysql_oc2018')->table('users')->where('created_at','>=',date('Y-m-d',strtotime('-1 week')));
                $query = DB::connection('mysql_oc2018')->table('users')->where('created_at', '>=', date('Y-m-d', strtotime('this week')));
            } elseif ($type == 3) {
                $query = DB::connection('mysql_oc2018')->table('users')->whereBetween('created_at', [date('Y-m-d', strtotime("last week")), date('Y-m-d', strtotime('this week'))]);
            } else {
                $query = DB::connection('mysql_oc2018')->table('users');
                if(!empty($request->start_time) && !empty($request->end_time)){
                $query = $query->where('created_at', '>=', date('Y-m-d', $request->start_time))->where('created_at', '<=', date('Y-m-d', $request->end_time));
                }
                if(!empty($request->character)){
                $query = $query->where('character',$request->character);
                }
            }
        }
        //dd($query->toSql());
        $alltotal = $query->count();
        $rs = $query->orderBy('created_at', 'desc')->offset($this->size * ($page - 1))->limit($this->size)->get();
        // $extra_query = DB::connection('mysql_oc2018')->table("user_phone_list")->select("phone","company","remark","is_will");
        $result = [];
        foreach ($rs as $v => $value) {
            $extra_infor = DB::connection('mysql_oc2018')->table("user_phone_list")->select("phone","company","remark","is_will")->where("user_id",$value->id)->first();
            if(isset($extra_infor)){
            $k["phone"] = $extra_infor->phone;
            $k["company"] = $extra_infor->company; 
            $k["is_will"] = $extra_infor->is_will; 
            $k["remark"] = $extra_infor->remark; 
            }else{
            $k["phone"] = null;
            $k["company"] = null; 
            $k["is_will"] = null; 
            $k["remark"] = null;  
            }
            $k["uid"] = $value->id;
            $k["username"] = $value->username;
            $k["email"] = $value->email;
            $k["nickname"] = $value->nickname;
            $k["role"] = $value->role;
            $k["character"] = $value->character;
            $k["vip_start_time"] = $value->vip_start_time;
            $k["vip_deadline"] = $value->vip_deadline;
            $k["status"] = $value->status;
            $k["downloads"] = $value->downloads;
            $k["landing"] = $value->landing;
            $k["search_times"] = $value->search_times;
            $k["created_at"] = $value->created_at;
            array_push($result, $k);
        }

        $data['total'] = $alltotal;
        $data['size'] = $this->size;
        $data['all_page'] = ceil($alltotal / $this->size);
        $data["result"] = $result;
        return HttpUtil::retrun_json($data, 200);

    }

    public function vipUpdate(Request $request)
    {
        if (empty($request->id)) return HttpUtil::retrun_json(["code" => 214, "message" => "缺少参数"], 200);
        $condition = $request->all();
        $field = ["role", "vip_deadline", "downloads", "search_times", "vip_resource", "landing","character"];
        $vfield = ["remark","is_will"];
        $update = [];
        foreach ($field as $key => $value) {
            if (isset($condition[$value])) {
                $update[$value] = $condition[$value];
            }
        }
        $update_v = [];
        foreach ($vfield as $key => $value) {
            if (isset($condition[$value])) {
                $update_v[$value] = $condition[$value];
            }
        }
        if(empty(array_diff(array_keys($condition), $field )))return HttpUtil::retrun_json(["code" => 214, "message" => "请填写要修改的字段名称"], 200);
        if (!empty($update_v) && DB::connection('mysql_oc2018')->table('user_phone_list')->where(["user_id" => $request->id])->update($update_v)) {
            return HttpUtil::retrun_json(["code" => 211, "message" => "修改成功"], 200);
        }
        if (!empty($update) && DB::connection('mysql_oc2018')->table('users')->where(["id" => $request->id])->update($update)) {
            return HttpUtil::retrun_json(["code" => 200, "message" => "修改成功"], 200);
        }
        return HttpUtil::retrun_json(["code" => 211, "message" => "修改失败"], 200);
    }

    /**author:zhujh
     * 注册用户处理
     * type 处理 0 启用 1 禁用 2 删除 3 重置密码
     * uid  用户id
     * pwd  重置密码
     * @return [type] [description]
     */
    public function userdeal(Request $request)
    {
        $type = empty($request->type) ? "" : $request->type;
        $uid = empty($request->uid) ? "" : $request->uid;
        $pwd = empty($request->pwd) ? "" : $request->pwd;

        if (strstr($uid, ',')) {
            $condition = explode(",", $uid);
        } else {
            $condition[0] = $uid;
        }
        if ($type == 1) {
            foreach ($condition as $key => $value) {
                DB::connection('mysql_oc2018')->table('users')->where(["id" => $value])->update(["status" => 3]);
            }
        } elseif ($type == 0) {
            foreach ($condition as $key => $value) {
                DB::connection('mysql_oc2018')->table('users')->where(["id" => $value])->update(["status" => 1]);
            }
        } elseif ($type == 2) {
            foreach ($condition as $key => $value) {
                DB::connection('mysql_oc2018')->table('users')->where(["id" => $value])->delete();
            }
        } elseif ($type == 3) {
            $pwd = $this->userKey($pwd);
            foreach ($condition as $key => $value) {
                DB::connection('mysql_oc2018')->table('users')->where(["id" => $value])->update(["password" => $pwd]);
            }
        }
        return HttpUtil::retrun_json("修改成功", 200);
    }

    /**
     * key rxJOYsbo04R6vpK13NViAmIhWS7T5PFdyq8ujEHk
     * @param  [type] $str [description]
     * @param  string $key [description]
     * @return [type]      [description]
     */
    private function userKey($str)
    {
        return '' === $str ? '' : md5(sha1($str) . $this->user_key);
    }


    public function cnameUpdate(Request $request)
    {
        $host = $request->host;
        $cname = $request->cname;
        if (empty($host) || empty($cname)) return HttpUtil::retrun_json(["status" => 211, "message" => "缺少参数"], 200);
        if(!DB::table("domains")->where("host",$host)->update(["cname"=>$cname]))return HttpUtil::retrun_json(["status" => 211, "message" => "修改失败"], 200);
        $curl_param = [
            "_source" =>["id"],
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["term" => ["host_no" => $host]]
                    ]]
                ],
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        $id = $data["data"][0]->id;
        $curl_param = [
            "doc" => [
                "cname" => $cname
            ]
        ];
        $url = config('param.ES_URL') . config('param.DOMAIN_SEARCH') . '/' . $id . '/_update';
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $rs = json_decode($rs, true);
        if ($rs["_shards"]["failed"] == 0) {
            $data["status"] = 200;
            $data["message"] = "修改成功";
            return HttpUtil::retrun_json($data, 200);
        }
        $data["status"] = 211;
        $data["message"] = "修改失败";
        return HttpUtil::retrun_json($data, 200);
    }

    /**
     * 获取一个邮箱的后缀
     */
    private function getPostfix($email)
    {
        $data = explode("@", $email);
        return $data[1];
    }

    private function filmEmailDo(Request $request)
    {
        $account = $request->account;
        $account = $this->getPostfix($account);
    }


    public function roleInforPost(Request $request)
    {
        $infor = $request->data;
        $infor = json_decode($infor, true);
        $role_name = $infor["role_name"];
        $array_filter = ["size", "page", "role", "role_name", "token"];
        foreach ($infor as $key => $value) {
            if (in_array($key, $array_filter)) $infor = self::array_remove($infor, $key);
        }
        $data["message"] = "创建成功";
        $data["status"] = "200";
        try {
            if (empty($role_name)) throw new Exception("标题不能为空");
            if (DB::connection('mysql_oc2018')->table('function_ship')->where("role_name", $role_name)->first()) throw new Exception("数据已存在");
            $rs_tmp = DB::connection('mysql_oc2018')->table('function_ship')->orderBy("id", "asc")->first();
            if (isset($rs_tmp)) {
                $add_tmp = [];
                foreach ($infor as $key => $value) {
                    if (!array_key_exists($key, unserialize($rs_tmp->function_detail))) {
                        $value["status"] = false;
                        $add_tmp[$key] = $value;
                    }
                }
                if (!empty($add_tmp)) {
                    $wholeRs = DB::connection('mysql_oc2018')->table('function_ship')->get();
                    foreach ($wholeRs as $key => $value) {
                        $data_tmp = unserialize($value->function_detail);
                        foreach ($add_tmp as $key1 => $value1) {
                            $data_tmp[$key1] = $value1;
                        }
                        DB::connection('mysql_oc2018')->table('function_ship')->where("role", $value->role)->update(["function_detail" => serialize($data_tmp)]);
                    }
                }

            }
            $role = DB::connection('mysql_oc2018')->table('function_ship')->max("role");
            if (!isset($role)) $role = -1;
            if (!DB::connection('mysql_oc2018')->table('function_ship')->insert(["role" => ++$role, "role_name" => $role_name, "function_detail" => serialize($infor), "created_time" => time()])) throw new Exception("保存失败");
        } catch (Exception $e) {
            $data['message'] = $e->getMessage();
            $data['status'] = "211";
        }
        return HttpUtil::retrun_json($data, 200);
    }

    public function roleInforGet(Request $request)
    {
        $query = DB::connection('mysql_oc2018')->table('function_ship');
        $alltotal = $query->count();
        $rs = $query->orderBy('created_time', 'desc')->offset($this->size * ($this->page - 1))->limit($this->size)->get();
        $result = [];
        foreach ($rs as $v => $value) {
            $k["uid"] = $value->id;
            $k["role"] = $value->role;
            $k["role_name"] = $value->role_name;
            $k["created_time"] = strtotime("Y-m-d", $value->created_time);
            $k["roleinfor"] = unserialize($value->function_detail);
            array_push($result, $k);
        }

        $data['total'] = $alltotal;
        $data['size'] = $this->size;
        $data['all_page'] = ceil($alltotal / $this->size);
        $data["data"] = $result;
        return HttpUtil::retrun_json($data, 200);
    }

    public function roleInforPut(Request $request)
    {   

        $infor = $request->data;
        $infor = json_decode($infor, true);
        $role_name = $infor["role_name"];
        $role = $infor["role"];
        $array_filter = ["size", "page", "role", "role_name", "token"];
        foreach ($infor as $key => $value) {
            if (in_array($key, $array_filter)) $infor = self::array_remove($infor, $key);
        }
        $data["message"] = "更新成功";
        $data["status"] = "200";
        try {
            if (!isset($role_name)) throw new Exception("缺少参数");
            $rs_tmp = DB::connection('mysql_oc2018')->table('function_ship')->where("role", "<", $role)->first();
            if (!isset($rs_tmp)) $rs_tmp = DB::connection('mysql_oc2018')->table('function_ship')->where("role", ">", $role)->first();
            if (isset($rs_tmp)) {
                $add_tmp = [];
                foreach ($infor as $key => $value) {
                    if (!array_key_exists($key, unserialize($rs_tmp->function_detail))) {
                        $value["status"] = false;
                        $add_tmp[$key] = $value;
                    }
                }
                if (!empty($add_tmp)) {
                    $wholeRs = DB::connection('mysql_oc2018')->table('function_ship')->where("role", "!=", $role)->get();
                    foreach ($wholeRs as $key => $value) {
                        $data_tmp = unserialize($value->function_detail);
                        foreach ($add_tmp as $key1 => $value1) {
                            $data_tmp[$key1] = $value1;
                        }
                        DB::connection('mysql_oc2018')->table('function_ship')->where("role", $value->role)->update(["function_detail" => serialize($data_tmp)]);
                    }
                }

            }
            if (!DB::connection('mysql_oc2018')->table('function_ship')->where("role", $role)->update(["role_name" => $role_name, "function_detail" => serialize($infor)])) throw new Exception("更新失败");
        } catch (Exception $e) {
            $data['message'] = $e->getMessage();
            $data['status'] = "211";
        }
        return HttpUtil::retrun_json($data, 200);
    }

     public function roleInforDelete(Request $request)
    {
        $id = $request->id;
        $data['message'] = "删除成功";
        $data['status'] = "200";
        try {
            if (!isset($id)) throw new Exception("id不能为空");
            if ($id == 33) throw new Exception("普通会员权限不可删除");
            $rs = DB::connection('mysql_oc2018')->table('function_ship')->where("id", $id)->first();
            if (!DB::connection('mysql_oc2018')->table('function_ship')->where("id", $id)->delete()) throw new Exception("删除失败");
            if ( DB::connection('mysql_oc2018')->table('users')->where("role", $rs->role)->first() && !DB::connection('mysql_oc2018')->table('users')->where("role", $rs->role)->update(["role" => 0, "vip_start_time" => null, "vip_deadline" => null])
            ) throw new Exception("用户权限更新失败");
        } catch (Exception $e) {
            $data['message'] = $e->getMessage();
            $data['status'] = "211";
        }
        return HttpUtil::retrun_json($data, 200);
    }


    public static function array_remove($data, $key)
    {
        if (!array_key_exists($key, $data)) {
            return $data;
        }
        $keys = array_keys($data);
        $index = array_search($key, $keys);
        if ($index !== FALSE) {
            array_splice($data, $index, 1);
        }
        return $data;

    }


    public function getFunctionList(Request $request)
    {
        $rs = DB::connection('mysql_oc2018')->table('function_list')->get();
        $data = [];
        foreach ($rs as $key => $value) {
            $data_tmp["type"] = $value->type;
            $data_tmp["name"] = $value->name;
            if ($value->status == "true") $data_tmp["status"] = true;
            if ($value->status == "false") $data_tmp["status"] = false;
            $data_tmp["limit"] = empty($value->limit) ? [] : unserialize($value->limit);
            array_push($data, $data_tmp);

        }
        return HttpUtil::retrun_json(["status" => 200, "data" => $data], 200);
    }


    public function integralTaskCreate(Request $request)
    {
        $name = $request->input("name", "");
        $type = $request->input("type", "");
        $md5 = $request->input("md5", "");
        $remark = $request->input("remark", "");
        $notice = ["211" => "缺少参数"];
        try {
            $db = DB::connection("mysql_oc2018");
            if (empty($name) || empty($type)) throw new Exception("211");
            $insertdata["name"] = $name;
            $insertdata["type"] = $type;
            $insertdata["md5"] = $md5;
            $insertdata["remark"] = $remark;
            $insertdata["created_time"] = date("Y-m-d H:i:s", time());
            if ($db->table('integral_task_list')->insert($insertdata)) {
                $data["code"] = 200;
                $data["message"] = "创建成功";
            }
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);
    }

     public function getBrandList(Request $request)
    {   

        $wd = $request->wd;
        $page = $request->input("page", "1");
        $size = 20;
        $brand_infor = [];
        $total = DB::table('brand_detail_new')->where("Chinese_brand_name", "like", "%$wd%")->orWhere("brand_name", "like", "%$wd%")->count();
        $result = DB::table('brand_detail_new')->where("Chinese_brand_name", "like", "%$wd%")->orWhere("brand_name", "like", "%$wd%")->offset($size * ($page - 1))->limit($size)->get();
        foreach ($result as $key => $value) {
            array_push($brand_infor, (array)$value);
        }
        $data = $brand_infor;
        return HttpUtil::retrun_json(["status" => 200, "data" => $data,"total" => $total], 200);
    }
    
    //http://dev-api.adbug.cn/admin/brand/infor/add?brand=1&area=2&industry=2&company=3&cname=4&ename=5
    public function brandInforAdd(Request $request)
    {
        $request = $request->all();
        // $fields =["brand"=>"brand","area"=>"parent_company_headquarters","industry"=>"industry","company"=>"parent_company","cname"=>"Chinese_brand_name","ename"=>"brand_name"];
        $fields =["brand"=>"brand","parent_company_headquarters"=>"parent_company_headquarters","industry"=>"industry","parent_company"=>"parent_company","Chinese_brand_name"=>"Chinese_brand_name","brand_name"=>"brand_name"];
        foreach ($fields as $key => $value) {
              if(in_array($value,array_keys($request))){
                $insert["$value"] = $request["$value"];
              }
        }
        if(empty($insert))return HttpUtil::retrun_json(["status"=>212,"message"=>"缺少参数"], 200);
        $data["status"] = 200;
        $data["message"] = "添加成功";
        if(!DB::table('brand_detail_new')->insert($insert))return HttpUtil::retrun_json(["status" => 211, "message"=>"数据添加失败"], 200);
        return HttpUtil::retrun_json($data, 200);
    }

    public function brandInforUpdate(Request $request)
    {
     
        $request = $request->all();
        if(!isset($request["id"]))return HttpUtil::retrun_json(["status" => 211, "message"=>"缺少参数"], 200);
        $fields =["brand"=>"brand","parent_company_headquarters"=>"parent_company_headquarters","industry"=>"industry","parent_company"=>"parent_company","Chinese_brand_name"=>"Chinese_brand_name","brand_name"=>"brand_name","status"=>"status"];
        foreach ($fields as $key => $value) {
              if(in_array($value,array_keys($request))){
                $update["$value"] = $request["$value"];
              }
        }
        $data["status"] = 200;
        $data["message"] = "更改成功";
        if(!DB::table('brand_detail_new')->where("id",$request["id"])->update($update))return HttpUtil::retrun_json(["status" => 212, "message"=>"数据更改失败"], 200);
        return HttpUtil::retrun_json($data, 200);
    }
    

     public function brandDelete(Request $request)
    {
        $request = $request->all();
        $data["status"] = 200;
        $data["message"] = "删除成功";
        $notice = ["211" => "缺少参数", "212" => "删除失败"];
        try {
            if (empty($request["id"])) throw new Exception("211");
            DB::beginTransaction();
            if (!DB::table('brand_detail_new')->where("id", $request["id"])->delete()) throw new Exception("212");
            $db = DB::table("brand_domain_4");
            if ($db->where("brand_id", $request["id"])->first() && !$db->where("brand_id", $request["id"])->update(["brand_id" => null])) throw new Exception("212");
            $curl_param = [];
            $url = config('param.ES_URL') . "brand_infor_v4/brand_domain/" . $request["id"];
            self::curl_method($url, $curl_param, "DELETE");
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $data['message'] = $notice[$e->getMessage()];
            $data['status'] = (int)$e->getMessage();
        }
        return HttpUtil::retrun_json($data, 200);
    }



     public static function curl_method($url,$data,$method){

        if(!ESUtil::getEsStatus())$url = str_replace('192.168.1.47', '192.168.1.58', $url);
        $ch = curl_init(); //初始化CURL句柄
        curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); 
        curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
        $rs = curl_exec($ch);//执行预定义的CURL
        curl_close($ch);

        return $rs;
    }


    public function accountAdd(Request $request){
        $request = $request->all();
        $fields =["username","email","password"];
        foreach ($fields as $key => $value) {
              if(!in_array($value,array_keys($request)))return HttpUtil::retrun_json(["status" => 211, "message"=>"缺少参数"], 200);
        }
        $data['password'] = $this->userKey($request["password"]);
        $data['username'] = $request["username"];
        $data['email'] = $request["email"];
        $data['status'] = 1;//已激活
        $data['type'] = 2;
        $data['role'] = 3;
        $data['created_at'] = date('Y-m-d H:i:s', time());
        $db = DB::connection('mysql_oc2018');
        if($db->table('users')->where("email",$request["email"])->first())return HttpUtil::retrun_json(["status" => 213, "message"=>"账号已经存在,请重新命名"], 200);
        if(!$db->table('users')->insert($data))return HttpUtil::retrun_json(["status" => 212, "message"=>"添加失败"], 200);
        return HttpUtil::retrun_json(["status" => 200, "message"=>"添加成功"], 200);
    }
  
}
