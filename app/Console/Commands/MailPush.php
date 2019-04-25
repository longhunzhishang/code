<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Utils\Tools;
use DB;

class MailPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailpush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
//    public function handle()
//    {
//        $id = 0;
//        while (DB::connection('mysql_oc2018')->table('users')->where('id', ">", $id)->limit(1000)->count() > 0) {
//            $rs = DB::connection('mysql_oc2018')->table('users')->where('id', ">", $id)->limit(1000)->get();
//            foreach ($rs as $key => $value) {
//                if ($this->emailFree($value->email))continue;
//                $data['email'] = $value->email;
//                $data['view'] = 'reset';
//                $data['subject'] = "Adbug 双十一优惠";
//                $data['title'] = 'Adbug';
//               // $data['code'] = $code;
//                Tools::sendTemplateMail($data);
//                if ($key == count($rs) - 1) {
//                    $id = $value->id;
//                }
//            }
//        }
//    }

    public function handle()
    {
        $id = 0;
        while (DB::table('email_data')->where('id', ">", $id)->limit(100)->count() > 0) {
            $rs = DB::table('email_data')->where('id', ">", $id)->limit(100)->get();
            foreach ($rs as $key => $value) {
                if ($key == count($rs) - 1) {
                    $id = $value->id;
                }
                $data['email'] = $value->email;
                $name = explode("@",$value->email);
                $username = $name[0];
                $data['subject'] = "ADBUG广告查查 限时赠送VIP特权";
                $data["url"] = "http://www.adbug.cn/#/" ;
                $data["view"] = "adbug_sale_template";
                $data["username"] = "尊敬的用户" ;
                DB::table('email_send_log')->insert(["user_id"=>$value->id]);
                Tools::sendTemplateMail($data);
            }
        }
        echo "success";
    }

   
    // public function handle()
    // {   
    //     $rs = DB::connection('mysql_oc2018')->table('users')->where('email', "z@adbug.cn")->get();
    //     foreach ($rs as $key => $value) {
    //         if ($this->emailFree($value->email)) continue;
    //         $data['email'] = $value->email;
    //         $name = explode("@",$value->email);
    //         $username = $name[0];
    //         $data['subject'] = "ADBUG广告查查 限时赠送VIP福利";
    //         $data["url"] = "http://testapi.adbug.cn/activity/index?token=b04b86c026eb5a81f846b0f4af168b5b&id=" . $value->id."&username=". $username ;
    //         $data["view"] = "adbug_sale_template";
    //         $data["username"] = $username ;
    //         DB::table('email_send_log')->insert(["user_id"=>$value->id]);
    //         Tools::sendTemplateMail($data);
    //     }
    // }



    private function emailFree($email)
    {
        $email_free = [
            'sohu.com', '163.com', 'google.com',
            'yahoo.com', 'sina.com.cn', 'outlook.com', 'tom.com',
            '21cn.com', '10086.cn', 'alo.com', 'aliyun.com', 'icloud.com',
            '126.com', 'yeah.net', 'netease.com', 'sogou.com', 'chinaren.com',
            '17173.com', '189.com', 'wo.cn', 'cctv.com',
            '173.com', 'sina.com', 'tom.com', '21cn.com', 'yeah.com',
            '5460.net', 'ymail.com', 'xuite.net', 'zzn.com', 'hushmail.com',
            'csdn.net', 'ovi.com', 'heimail.com', 'shortmail.com', 'opera.com',
            'baihui.com', 'facebook.com', 'lycos.com',
            'zj.com', '188.com', '21cn.com', 'bxemail.com', 'eyou.com',
            'gmail.com', 'china.com', 'lantern.cn', 'foxmail.com', 'live.cn', 'qq.com', 'dingtalk.com', 'vip.qq.com', '139.com', 'hotmail.com',
            'hotmail.com', 'vip.163.com'
        ];

       // $r_a = $_SERVER['SERVER_ADDR'];

        // if(!in_array($r_a, C('WHITE_HOST')))
        // {
        //     return false;
        // }

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




}
