<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redis;
use App\Http\Utils\UserAccess;
use DB;
use Illuminate\Http\Request;
use App\Http\Utils\Tools;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use App\AddataRankRole;
use App\Domains;
use App\AdRRoleList;
use App\RankTracker;
use App\Company;
use Excel;
use \Exception;

class UserController extends Controller
{

    //adbug 用户登录 加密 key
    protected $user_key = 'oS1wcdz9ysuxalNhH5AXkWVC4vbFE7ZDYOfnMQPq';

    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
    }



    /**
     * 登录
     * code  210  账号或者密码不可为空
     *       211 用户或者密码密码 212 未激活
     * account 邮箱
     * pwd 密码
     * @return [type] [description]
     */
    public function login(Request $request)
    {
        $account = UserAccess::getUserInfo();
        if (count($account) != 0) return HttpUtil::retrun_json(['code' => 214, 'data' =>[],'message' => "已经登录过了,不可再次登录"], 200);
        $account = empty($request->account) ? '' : $request->account;
        $pwd = empty($request->pwd) ? '' : $request->pwd;

        if (empty($account) || empty($pwd)) {
            return HttpUtil::retrun_json(['code' => 210, 'data' =>[],'message' => "缺少参数"], 200);
        }
        // if ($this->emailFree($account)) {
        //     return HttpUtil::retrun_json(HttpUtil::returnStatus(213), 200);
        // }
        if ($this->filterlogin($account)) {
            // return HttpUtil::retrun_json(HttpUtil::returnStatus(215), 200);
            return HttpUtil::retrun_json(['code' => 215, 'message' => "公司邮箱已被禁止登陆"], 200);
        }
        $pwd1 = $this->userKey($pwd);
        //mysql_oc_true
        $user = DB::connection('mysql_oc2018')->table('users')
            ->where(['email' => $account, 'password' => $pwd1])
            ->select('id', 'username', 'email', 'isonline', 'status', 'type', 'Referee')->first();

        if (empty($user)) {
            return HttpUtil::retrun_json(['code' => 211, 'data' =>[],'message' => "密码错误"], 200);
        }
        if ($user->status != 1) {
            $token = md5($user->id . $account);
            $referee = isset($user->Referee) ? $user->Referee : "";
            $url = 'http://testapi.adbug.cn/user/validEmailAlive?uid=' . $user->id . '&account=' . $account . '&token=' . $token . '&referee_id=' . $referee;
            $title = 'Adbug';
            $data['email'] = $account;
            $data['subject'] = "Adbug激活码";
            $data['url'] = $url;
            $data['title'] = $title;
            $data['account'] = $account;
            $data['uid'] = $user->id;
            $data['token'] = $token;
            $data['view'] = 'email';
            //Tools::sendMailWarn($data);
            Tools::sendMail($data);
            return HttpUtil::retrun_json(['code' => 212, 'data' =>[],'message' => "邮件发送成功"], 200);
        }
        Auth::attempt(array('email' => $account, 'password' => $pwd));
        $data1['code'] = 200;
        $data1['message'] = "登录成功";
        $data1['data'] = [];
        //更改用户状态
        $this->upUserStatus($account, 1);
        return HttpUtil::retrun_json($data1, 200);

    }

    public function filterlogin($email)
    {
        $email_free = ["bjyunrui.com","yunrui.net","yunrui.com","skytech.cn","teamtop.com"
        ];
        $email = strtolower($email);
        $email_is = explode('@', $email);

        if (empty($email_is[1])) {
            return true;
        }
        foreach ($email_free as $key => $value) {
            if ($email_is[1] == $value) {
                return true;
            }
        }
        return false;
    }


    public function sessionUpdate(Request $request)
    {
        $account = UserAccess::getUserInfo();
        if (count($account) == 0) return HttpUtil::retrun_json(['status' => 214, 'message' => "未登录用户无权限操作"], 200);
        $id = $request->session()->getId();
        DB::connection('mysql_oc2018')->table('users')->where('email',$account['email'])->update(["single_token"=>$id]);
        return HttpUtil::retrun_json(['status' => 200, 'message' => "session操作成功"], 200);
    }


    /**
     * 注册
     * code 201 请使用企业邮箱注册
     *      202 账号 密码不可为空
     *      203 两次输入密码不一致
     *      205 该邮箱已经被注册
     *      206 用户名已经存在
     * account 账号
     * pwd 密码
     * setpwd 重复密码
     * username 用户名
     * @return [type] [description]
     */
  public function register(Request $request)
    {  
        $field = ["account", "pwd", "username", "company", "phonenum", "code", "setpwd"];
        $condition = $request->all();
        $data1["code"] = "200";
        $data1["message"] = "注册成功";
        $notice = ["211" => "缺少参数", "212" => "前后密码不正确", "213" => "用户已经存在，请重新登录", "214" => "手机号码格式不正确，请重新输入", "215" => "该手机号码暂未发送验证码", "216" => "验证码不正确，请重新输入", "217" => "注册失败，联系客服","218" =>"该手机号已经注册过了"];
        try {
            $db = DB::connection('mysql_oc2018');
            foreach ($field as $key => $value) {
                if (empty($condition[$value])) throw new Exception("211");
            }
            $account = $request->account;
            $username = $request->username;
            $company = $request->company;
            $pwd = $request->pwd;
            $setpwd = $request->setpwd;
            $phonenum = $request->phonenum;
            $code = $request->code;
            if ($pwd != $setpwd) throw new Exception("212");
            $user = DB::connection('mysql_oc2018')->table('users')
                ->where(['email' => $account])->first();
            if (!empty($user)) throw new Exception("213");
            if (!preg_match("/^1[345678]{1}\d{9}$/", $phonenum)) throw new Exception("214");
            $key = $phonenum . "code";
            $kcode = Redis::get($key);
            if (empty($kcode)) throw new Exception("215");
            if ($kcode != $code) {
                throw new Exception("216");
            } else {
                $data['password'] = $this->userKey($pwd);
                $data['username'] = $username;
                $data['email'] = $account;
                $data['status'] = 1;//已激活
                $data['type'] = 2;
                $data['role'] = 3;
                $data['created_at'] = date('Y-m-d H:i:s', time());
                $db->beginTransaction();
                if ($db->table('user_phone_list')->where('phone', $phonenum)->select("id")->first()) throw new Exception("218");
                if (!$db->table('users')->insert($data)) throw new Exception("217");
                $userid = $db->table('users')->where('email', $account)->select("id")->first();
                if (!isset($userid)) throw new Exception("217");
                if (!$db->table('user_phone_list')->insert(["phone" => $phonenum, "company" => $company, "user_id" => $userid->id, "created_time" => $data['created_at']])) throw new Exception("217");
                $db->commit();
            }
        } catch (Exception $e) {
            $db->rollback();
            $data1["code"] = $e->getMessage();
            $data1["message"] = $notice[$e->getMessage()];
        }

        return HttpUtil::retrun_json($data1, 200);

    }

    //  private function User_benefit_operation($user_id,$email)
    // {
    //     $vip_start_time = date("Y-m-d H:i:s", time());
    //     $vip_deadline = date("Y-m-d H:i:s", strtotime("+7 day"));
    //     DB::connection('mysql_oc2018')->table('users')->where("id", $user_id)->update(["role" => 1, "vip_start_time" => $vip_start_time, "vip_deadline" => $vip_deadline]);
    //     $data['email'] = $email;
    //     $data['subject'] = "ADBUG注册用户7天会员发放成功";
    //     $data['view'] = 'notice';
    //     Tools::sendMailWarn($data);
    // }



    /**
     * 重置密码
     * code 210 账号密码不可为空
     *      301 用户不存在
     *      302 验证码不存在
     *      305 更新密码失败
     * active 激活码
     * pwd 密码
     * account 账号
     * type 1 获取激活码 2 提交 修改密码
     * @return [type] [description]
     */
    public function resetPwd(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $pwd = empty($request->pwd)?'':$request->pwd;
        $type = empty($request->type)?'1':$request->type;

        $active = empty($request->active)?'':$request->active;



        if(empty($account))return HttpUtil::retrun_json(HttpUtil::returnStatus(210),200);

        $user = DB::connection('mysql_oc2018')->table('users')
            ->where('email',$account)->first();

        if(empty($user))return HttpUtil::retrun_json(HttpUtil::returnStatus(301),200);

        //获取激活码
        if($type == 1)
        {
            $reset_user = DB::connection('mysql_oc2018')->table('resets')->where('email',$account)->first();

            $code = empty($reset_user)?rand(100000,999999):$reset_user->code;

            if(empty($reset_user)){
                DB::connection('mysql_oc2018')->table('resets')->insert(['email'=>$account,'code'=>$code]);
            }

            $data['email'] = $account;
            $data['view'] = 'reset';
            $data['subject'] = "Adbug 验证码";
            $data['title'] = 'Adbug';
            $data['code'] = $code;
            Tools::sendMailWarn($data);
            return HttpUtil::retrun_json(HttpUtil::returnStatus(200),200);
        }

        if($type==2)
        {
            if(empty($active) && empty($pwd))return HttpUtil::retrun_json(HttpUtil::returnStatus(210),200);


            $reset_user = DB::connection('mysql_oc2018')->table('resets')->where(['email'=>$account,'code'=>$active])->first();

            if(empty($reset_user))return HttpUtil::retrun_json(HttpUtil::returnStatus(302),200);

            $pwd = $this->userKey($pwd);

            if(!DB::connection('mysql_oc2018')->table('users')->where('email',$account)->update(['password'=>$pwd]))
            {
                return HttpUtil::retrun_json(HttpUtil::returnStatus(305),200);
            }
            return HttpUtil::retrun_json(HttpUtil::returnStatus(200),200);
        }
    }


    /**
     * 激活邮箱验证码
     * @return [type] [description]
     */
    public function validEmailAlive(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $uid = empty($request->uid)?'':$request->uid;
        $referee_id = empty($request->referee_id)?'':$request->referee_id;
        $token = empty($request->token)?'':$request->token;
        if(empty($account) || empty($uid) || empty($token))dd('valid error');

        if(md5($uid.$account) != $token)abort('403','token 验证失败, 请联系管理人员 1620683945@qq.com');

        $user = DB::connection('mysql_oc2018')->table('users')
            ->where('id',$uid)->first();

        if(empty($user))abort('403','该用户不存在, 请联系管理人员 1620683945@qq.com');

        if($user->email != $account)abort('403','邮箱和用户不符合, 请联系管理人员 1620683945@qq.com');
        if($user->status == 1)
        {
            header('Location: http://www.adbug.cn');
            exit;
        }
        if(!DB::connection('mysql_oc2018')->table('users')->where('id',$uid)->update(['status'=>1]))
        {
            abort('403','激活失败, 请联系管理人员 1620683945@qq.com');
        }
        if(!empty($referee_id))$this->RefereeDo($uid,$referee_id);
        header('Location: http://www.adbug.cn');
        exit;
    }


    /**
     * 推荐人推荐用户权限操作
     * @return [type] [description]
     */
    private function RefereeDo($user_id, $referee_id)
    {
        if (DB::connection('mysql_oc2018')->table('user_recommend_infor')->where("user_id", $user_id)->update(["is_activation" => 1])) {
            $count = DB::connection('mysql_oc2018')->table('user_recommend_infor')->where("referee_id", $referee_id)->where("is_valid", 0)->where("is_activation", 1)->count();
            if ($count >= 5) {
                $vip_deadline = date("Y-m-d H:i:s", strtotime("+1 month "));
                $vip_start_time = date("Y-m-d H:i:s", time());
                $rs = DB::connection('mysql_oc2018')->table('users')->where('id', $referee_id)->first();
                if ($rs->role == 0) {
                    DB::connection('mysql_oc2018')->table('users')->where('id', $referee_id)->update(['role' => 1,'vip_resource'=>1, 'vip_deadline' => $vip_deadline,'vip_start_time'=>$vip_start_time]);
                    DB::connection('mysql_oc2018')->table('user_recommend_infor')->where("referee_id", $referee_id)->where("is_activation", 1)->update(['is_valid' => 1]);
                }
            }
        }
    }


    /**
     * 判断是否登录
     * account 邮箱
     * @return boolean [description]
     */
    public function isOnline(Request $request)
    {
        $account = UserAccess::getUserInfo();
        if (empty($account)) return HttpUtil::retrun_json(HttpUtil::returnStatus(210), 200);
        $vfield = ["id","username", "email", "isonline", "type","role","downloads","vip_start_time","vip_deadline","referee_count","vreferee_count","search_times","landing"];
        $data["status"] = 200;
        $data["data"] = [];
        foreach ($account as $key => $value) {
            if (in_array($key, $vfield)) {
                $data["data"][$key] = $value;
            }
        }
        return HttpUtil::retrun_json($data, 200);
    }

    /**
     * 注销 登录状态更改
     * @return [type] [description]
     */
    private function upUserStatus($account,$alive)
    {
        DB::connection('mysql_oc2018')->table('users')
            ->where(['email'=>$account])->update(['isonline'=>$alive]);
    }

    /**
     * 注销用户
     * account 邮箱
     * @param  Request $request [description]
     * @return [type]           [description]
     */

    // public function logout(Request $request)
    //  {  
    //   if( Auth::check() == false)return HttpUtil::retrun_json(["status"=>211,"message"=>"用户未登录"],200);
    //      $rs = Auth::user()->toArray();
    //      $account = $rs["email"];
    //      $this->upUserStatus($account,0);
    //      $id = $request->session()->getId()
    //      Cookie::queue(Cookie::forget($id));
    //      Cookie::queue(Cookie::forget("adbug"));
    //      $data["cookie_id"] = $id;
    //      $data['status'] = 200;
    //      $data["message"] = "退出成功";
    //      return HttpUtil::retrun_json($data,200);
    //  }

    public function xlogout(Request $request)
    {
        $data['status'] = 200;
        $data["message"] = "退出成功";
        return HttpUtil::retrun_json($data,200);
    }


    public function userCheck(Request $request){
        $account = $request->account;
        $email = DB::connection('mysql_oc2018')->table('users')->where('email',$account)->first();
        return json_encode($email);
    }


    public function userUpdate(Request $request){
        $account = $request->account;
        $landing = $request->landing;
        $downloads = $request->downloads;
        if(!empty($downloads))$a["downloads"]=$downloads;
        if(!empty($landing))$a["landing"]=$landing;
        if(DB::connection('mysql_oc2018')->table('users')->where('email',$account)->update($a)){
            echo "success";
        }else{
            echo "fail";
        }
    }



    /**
     * 非企业邮箱过滤
     * finn
     * 2016-12-01
     */
    public function emailFree($email)
    {
        $email_free = [
            'sohu.com','163.com','google.com',
            'yahoo.com','sina.com.cn','outlook.com','tom.com',
            '21cn.com','10086.cn','alo.com','aliyun.com','icloud.com',
            '126.com','yeah.net','netease.com','sogou.com','chinaren.com',
            '17173.com','189.com','wo.cn','cctv.com',
            '173.com','sina.com','tom.com','21cn.com','yeah.com',
            '5460.net','ymail.com','xuite.net','zzn.com','hushmail.com',
            'csdn.net','ovi.com','heimail.com','shortmail.com','opera.com',
            'baihui.com','facebook.com','lycos.com',
            'zj.com','188.com','21cn.com','bxemail.com','eyou.com',
            'gmail.com','china.com','lantern.cn','foxmail.com','live.cn','qq.com','dingtalk.com','vip.qq.com','139.com','hotmail.com',
            'hotmail.com','vip.163.com'
        ];

        $r_a = $_SERVER['SERVER_ADDR'];

        // if(!in_array($r_a, C('WHITE_HOST')))
        // {
        //     return false;
        // }

        $email = strtolower($email);
        $email_is = explode('@', $email);

        if(empty($email_is[1]))
        {
            return true;
        }
        foreach ($email_free as $key => $value) {
            if($email_is[1]==$value)
            {
                return true;
            }
        }
        return false;
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


    public function reservation(Request $request)
    {
        $infor = $request->all();
        //$filed = ["username", "email", "telphone","film_name", "job", "product"];
        // dd(array_keys($infor));
        // foreach ($filed as $key => $value) {
        //     if (!array_key_exists($value, $infor))return HttpUtil::retrun_json(['status' => 211, 'message' => "缺少参数"], 200);
        // }
        DB::connection("mysql_oc2018")->table("reservation_infor")->insert(["user_name" => $infor["username"], "email" => $infor["email"], "telphone" => $infor["telphone"],"film_name" => $infor["film_name"], "job" => $infor["job"], "product" => $infor["product"]]);
        $data["user_name"] = $infor["username"] ;
        $data["user_email"] = $infor["email"];
        $data["telphone"] = $infor["telphone"];
        $data["film_name"] = $infor["film_name"];
        $data["job"] = $infor["job"];
        $notice["data"] = $data;
        $notice["view"] = "reservation_infor";
        $notice["subject"] = "!!你有新的销售信息,请及时查看";
        if($infor["product"] == 1){
        $notice["email"] = "sam@adbug.cn";
        }else{
        $notice["email"] = "katelu@adbug.cn";
        }
        Tools::sendTemplateMailNew($notice);
        return HttpUtil::retrun_json(['status' => 200, 'message' => "添加成功"], 200);

    }


    public function getClientIP(Request $request)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        return HttpUtil::retrun_json(['status' => 200, 'data' => $ip], 200);
    }

    public function sendMobileVerifyCode(Request $request)
    {
        $phonenum = $request->phonenum;
        $notice = ["211" => "手机号码不为空", "212" => "请不要有效时间内重复发送验证码", "213" => "手机号码格式不正确", "200" => "信息发送成功,请注意查看", "215" => "该手机号码当天发送验证码次数过多,超过5次"];
        try {
            if (empty($phonenum)) throw new Exception("211");
            if (!preg_match("/^1[345678]{1}\d{9}$/", $phonenum)) throw new Exception("213");
            $key = $phonenum . "code";
            $key1 = $phonenum . "num";
            //if (!empty(Redis::get($key1)) && Redis::get($key1) > 4) throw new Exception("215");
            if (empty(Redis::get($key1))) {
                $value1 = 1;
                $dtime = strtotime(date('Ymd')) + 86400 - time();
                Redis::setex($key1, $dtime, $value1);
            } else {
                Redis::incrby($key1, 1);
            }
            $code = rand(10000, 99999);
            Redis::setex($key, 600, $code);
            $result = Tools::sendSms($phonenum, $code);
            $result = json_decode($result, true);
            if ($result["result"] == true) {
                $data["message"] = $notice["200"];
                $data["code"] = 200;

            } else {
                $data["message"] = "发送失败,请联系客服人员";
                $data["code"] = 214;
            }
        } catch (Exception $e) {
            $data["code"] = (int)$e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);

    }


    public function mobileCodeVerify(Request $request)
    {
        $phonenum = $request->phonenum;
        $email = $request->email;
        $code = $request->code;
        $notice = ["211" => "手机号码或者邮箱不为空", "212" => "验证码不为空", "213" => "手机号码格式不正确", "214" => "验证码已经失效 ,请重新发送一次", "215" => "验证码验证错误", "216" => "手机号已经验证过了,无需验证","217"=>"用户不存在", "200" => "验证码验证成功"];
        try {
            if (empty($phonenum) || empty($email)) throw new Exception("211");
            if (empty($code)) throw new Exception("212");
            if(!DB::connection('mysql_oc2018')->table('users')->where('email', $email)->first())throw new Exception("217");
            if(DB::connection('mysql_oc2018')->table('user_phone_list')->where('phone', $phonenum)->first())throw new Exception("216");
            if (!preg_match("/^1[345678]{1}\d{9}$/", $phonenum)) throw new Exception("213");
            $key = $phonenum . "code";
            if (empty(Redis::get($key))) throw new Exception("214");
            if (Redis::get($key) != $code) {
                throw new Exception("215");
            } else {
                DB::connection('mysql_oc2018')->table('users')->where('email', $email)->update(["role"=>3]);
                $rs = DB::connection('mysql_oc2018')->table('users')->where('email', $email)->first();
                DB::connection('mysql_oc2018')->table('user_phone_list')->where('email', $email)->insert(["phone"=>$phonenum,"user_id"=>$rs->id,"created_time"=>date("Y-m-d H:i:s",time())]);
                $data["message"] = $notice["200"];
                $data["code"] = 200;
            }
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);

    }



    public function userOrderCheck(Request $request)
    {
        $account = $request->input("account", "");
        $page = $request->input("page", "1");
        $size = 10;
        $notice = ["211" => "缺少参数", "212" => "用户不存在"];
        $data["code"] = 200;
        $data["message"] = "创建成功";
        $data["data"] = [];
        $data["total"] = 0;
        try {
            if (empty($account)) throw new Exception("211");
            $user = DB::connection("mysql_oc2018")->table("users")->where("email", $account)->select("id")->first();
            if (!$user) throw new Exception("212");
            $rs = DB::connection('mysql_oc2018')->table('order_detail')->where("buyer_id", $user->id)->where("status", 1)->offset($size * ($page - 1))->limit($size)->get();
            $total = DB::connection('mysql_oc2018')->table('order_detail')->where("buyer_id", $user->id)->where("status", 1)->count();
            $data["total"] = $total;
            foreach ($rs as $key => $value) {
                $temp["out_trade_no"] = $value->out_trade_no;
                $temp["time_type"] = $value->vip_level == 1 ? "month" : "day";
                $temp["time_length"] = $value->time_length;
                $temp["created_time"] = $value->created_time;
                $temp["pay_time"] = $value->updated_time;
                $temp["amount"] = $value->amount;
                array_push($data["data"], $temp);

            }
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }

        return HttpUtil::retrun_json($data, 200);
    }



    public function userWechatCreate(Request $request)
    {
        $email = $request->email;
        $wechat_account = $request->wechat_account;
        if (empty($email) || empty($wechat_account)) return HttpUtil::retrun_json(["message" => "缺少参数", "code" => 211], 200);
        $rs = DB::connection("mysql_oc2018")->table("users")->where("email", $email)->select("id")->first();
        if (!isset($rs)) return HttpUtil::retrun_json(["message" => "用户信息不存在", "code" => 212], 200);
        if (DB::connection("mysql_oc2018")->table("integral_user_list")->where("user_id", $rs->id)->orWhere("wechat_account", $wechat_account)->first()) return HttpUtil::retrun_json(["message" => "用户信息或者微信号已经被绑定过了,不可重复绑定", "code" => 213], 200);
        DB::connection("mysql_oc2018")->table("integral_user_list")->insert(["user_id" => $rs->id, "wechat_account" => $wechat_account, "created_time" => date("Y-m-d H:i:s", time())]);
        return HttpUtil::retrun_json(["message" => "添加成功", "code" => 200], 200);
    }




    public function adsShareCreate(Request $request)
    {
        $md5 = $request->md5;
        $account = $request->account;
        $notice = ["211" => "缺少参数", "212" => "您的微信号未绑定账号,请及时绑定", "213" => "获取积分失败,请联系客服"];
        try {
            $db = DB::connection("mysql_oc2018");
            $db->beginTransaction();
            $data["code"] = 200;
            $data["message"] = "获取积分成功";
            if (empty($md5) || empty($account)) throw new Exception("211");
            $rs1 = $db->table("user_wechat_ship")->where("wechat_account", $account)->select("user_id")->first();
            if (empty($rs1)) throw new Exception("212");
            $rs = $db->table("integral_rule")->where("id", "2")->select("amount")->first();
            if (!$rs) throw new Exception("213");
            if (!$db->table("user_integral_log")->insert(["user_id" => $rs1->user_id, "amount" => $rs->amount, "md5" => $md5, "created_time" => date("Y-m-d H:i:s", time()), "rule_id" => "2"])
            ) throw new Exception("213");
            if (!$db->table("user_wechat_ship")->where("wechat_account", $account)->increment('integral_sum', $rs->amount)) throw new Exception("213");
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);
    }
    
    
    
    public function userIntegralCheck(Request $request)
    {
        $account = $request->account;
        $page = empty($request->page) ? "0" : $request->page;
        $size = empty($request->size) ? "10" : $request->size;
        $notice = ["211" => "缺少参数", "212" => "用户不存在"];
        try {
            $db = DB::connection("mysql_oc2018");
            $data["code"] = 200;
            $data["message"] = "查询成功";
            $data["data"] = [];
            if (empty($account)) throw new Exception("211");
            $rs = $db->table("users")->where("email", $account)->select("id")->first();
            if (!$rs) throw new Exception("212");
            $query = $db->table('user_integral_log')
                ->where('user_integral_log.user_id', $rs->id)->select("user_integral_log.amount", "user_integral_log.type", "user_integral_log.created_time", "integral_rule.name")
                ->leftJoin('integral_rule', function ($join) {
                    $join->on('integral_rule.id', '=', 'user_integral_log.rule_id');
                });
            $alltotal = $query->count();
            $data["alltotal"] = $alltotal;
            $rss = $query->orderBy('user_integral_log.id', 'desc')->offset($size * ($page - 1))->limit($size)->get();
            if (!empty($rss)) {
                foreach ($rss as $key => $value) {
                    $temp["amount"] = $value->type == 1 ? "-" . $value->amount : $value->amount;
                    $temp["name"] = $value->name;
                    $temp["created_time"] = $value->created_time;
                    array_push($data["data"], $temp);
                }
            }
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);
    }

    
    public function userIntegralTask(Request $request)
    {

        $account = $request->input("account", "");
        $page = $request->input("page", "1");
        $size = $request->input("size", "10");
        if ($size > 20) $size = 20;
        try {
            $notice = ["211" => "缺少参数", "212" => "用户不存在"];
            $data["code"] = 200;
            $data["message"] = "查看成功";
            $data["data"] = [];
            $data["total"] = 0;
            $db = DB::connection("mysql_oc2018");
            if (empty($account)) throw new Exception("211");
            $user = $db->table("users")->where("email", $account)->select("id")->first();
            if (!$user) throw new Exception("212");
            $query = $db->table('integral_task_user_list')
                ->where('integral_task_user_list.user_id', $user->id)->select("integral_task_list.name", "integral_task_list.type", "integral_task_user_list.created_time", "integral_task_user_list.status")
                ->leftJoin('integral_task_list', function ($join) {
                    $join->on('integral_task_list.id', '=', 'integral_task_user_list.task_id');
                });
            $total = $query->count();
            $data["total"] = $total;
            $rs = $query->orderBy('id', 'desc')->offset($size * ($page - 1))->limit($size)->get();
            foreach ($rs as $key => $value) {
                $temp["name"] = $value->name;
                $temp["type"] = $value->type;
                $temp["created_time"] = $value->created_time;
                $temp["status"] = $value->status;
                array_push($data["data"], $temp);
            }
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }

        return HttpUtil::retrun_json($data, 200);

    }


    

    public function userIntegralTaskCreate(Request $request)
    {
        $taskid = $request->input("taskid", "");
        $account = $request->input("account", "");
        $notice = ["211" => "缺少参数", "212" => "任务不存在或被禁用", "213" => "任务已存在,不可重复领取", "214" => "用户不存在"];
        try {
            $db = DB::connection("mysql_oc2018");
            if (empty($taskid) || empty($account)) throw new Exception("211");
            $rs = $db->table("integral_task_list")->where("status", 0)->where("id", $taskid)->first();
            if (!$rs) throw new Exception("212");
            $rs1 = $db->table("users")->where("email", $account)->select("id")->first();
            if (!$rs1) throw new Exception("214");
            $rs2 = $db->table("integral_task_user_list")->where("task_id", $taskid)->where("user_id", $rs1->id)->first();
            if (!$rs2) throw new Exception("213");
            if ($db->table('integral_task_user_list')->insert(["task_id" => $taskid, "user_id" => $rs1->id, "created_time" => date("Y-m-d H:i:s", time())])) {
                $data["code"] = 200;
                $data["message"] = "任务领取成功";
            }
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);
    }





     public function keywordsStoreCreate(Request $request)
    {

        $wd = $request->input("wd", "");
        $user_id = $request->input("user_id", "");
        $notice = ["211" => "缺少参数", "212" => "用户不存在", "213" => "关键词已经收藏过了,不必重复收藏", "214" => "信息创建失败,请联系客服"];
        $data["code"] = 200;
        $data["message"] = "创建成功";
        try {
            if (empty($wd) || empty($user_id)) throw new Exception("211");
            if (!DB::connection("mysql_oc2018")->table("users")->where("id", $user_id)->select("id")->first()) throw new Exception("212");
            if (DB::table("user_wd_store")->where("wd", $wd)->where("user_id", $user_id)->first()) throw new Exception("213");
            if (!DB::table("user_wd_store")->insert(["wd" => $wd, "user_id" => $user_id])) throw new Exception("214");
        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);

    }

    public function keywordsStoreCheck(Request $request)
    {
        $type = $request->input("type", "0");
        $user_id = $request->input("user_id", "");
        $notice = ["211" => "缺少参数"];
        try {
            if (empty($user_id)) throw new Exception("211");
            $data = [];
            $data["data"] = [];
            $data["code"] = "200";
            $data["message"] = "请求成功";
            $rs = DB::table("user_wd_store")->where("is_subscribe", $type)->where("user_id", $user_id)->select("wd")->get();
            if (!empty($rs)) {
                foreach ($rs as $key => $value) {
                    $data["data"][] = $value->wd;
                }
            }

        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["data"] = [];
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);

    }

    public function keywordsStoreUpdate(Request $request)
    {
        $type = $request->input("type", "0");
        $wd = $request->input("wd", "");
        $user_id = $request->input("user_id", "");
        $notice = ["211" => "缺少参数", "212" => "用户不存在", "213" => "用户收藏信息不存在", "214" => "取消收藏失败,请联系客服", "215" => "订阅失败,请联系客服", "216" => "取消订阅失败,请联系客服"];
        try {
            if (empty($wd) || empty($user_id)) throw new Exception("211");
            $rs = DB::connection("mysql_oc2018")->table("users")->where("id", $user_id)->select("id")->first();
            if (!isset($rs)) throw new Exception("212");
            if (!DB::table("user_wd_store")->where("wd", $wd)->where("user_id", $user_id)->first()) throw new Exception("213");
            if ($type == 0) {
                $data["code"] = 200;
                $data["message"] = "关键词取消收藏成功";
                if (!DB::table("user_wd_store")->where("wd", $wd)->where("user_id", $user_id)->delete()) throw new Exception("214");
            } elseif ($type == 1) {
                $data["code"] = 201;
                $data["message"] = "关键词订阅成功";
                if (!DB::table("user_wd_store")->where("wd", $wd)->where("user_id", $user_id)->update(["is_subscribe" => 1])) throw new Exception("215");
            } elseif ($type == 2) {
                $data["code"] = 202;
                $data["message"] = "关键词取消订阅成功";
                if (!DB::table("user_wd_store")->where("wd", $wd)->where("user_id", $user_id)->update(["is_subscribe" => 0])) throw new Exception("216");
            }

        } catch (Exception $e) {
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);

    }


     public function  adsShareCreateNew(Request $request)
    {
        $md5 = $request->md5;
        $account = $request->account;
        $notice = ["211" => "缺少参数","215" => "记录添加失败"];
        try {
            $db = DB::connection("mysql_oc2018");
            $db->beginTransaction();
            $data["code"] = 200;
            $data["message"] = "提交成功";
            if (empty($md5) || empty($account)) throw new Exception("211");
            if (!$db->table("wechat_share_log")->insert(["wechat_account" => $account, "md5" => $md5, "created_time" => date("Y-m-d H:i:s", time())])
            ) throw new Exception("215");
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $data["code"] = $e->getMessage();
            $data["message"] = $notice[$e->getMessage()];
        }
        return HttpUtil::retrun_json($data, 200);
    }


}
