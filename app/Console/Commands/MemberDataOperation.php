<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api9\ApiController;
use App\Http\Utils\Tools;
use Illuminate\Http\Request;
use App\Http\Utils\EsSelect;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\ESUtil;
use App\Http\Utils\DSLUtil;
use DB;

class MemberDataOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'MemberDataOperation';

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
    public function handle()
    {
        $id = 0;
        while (DB::connection('mysql_oc2018')->table('users')->where('id', ">", $id)->where("character", 1)->limit(1000)->count() > 0) {
            $record = DB::connection('mysql_oc2018')->table('users')->where('id', ">", $id)->where("character", 1)->limit(1000)->get();
            foreach ($record as $key => $value) {
                if (!empty($value->vip_deadline) && strtotime($value->vip_deadline) - time() <= 24 * 60 * 60 && strtotime($value->vip_deadline) - time() > 0) {
//                    $data["data"] = ["vip_deadline" => $value->vip_deadline];
//                    $data['subject'] = "ADBUG广告查查会员即将到期提醒通知";
//                    $data['email'] = $value->email;
//                    $data["view"] = "adbug_subs_notify";
//                     Tools::sendTemplateMailNew($data);
                    $rrecord = DB::connection('mysql_oc2018')->table('user_recommend_infor')->where('referee_id',$value->id)->where('is_valid',1)->where('is_activation',1)->get();
                    if(!empty($rrecord)){
                        DB::connection('mysql_oc2018')->table('user_recommend_infor')->where('referee_id',$value->id)->where('is_valid',1)->where('is_activation',1)->delete();
                    }
                    $data['email'] = $value->email;
                    $data['subject'] = "ADBUG广告查查会员即将到期提醒通知";
                    $data['date'] = $value->vip_deadline;
                    $data['view'] = 'renew';
                    Tools::sendMailWarn($data);
                    sleep(10);
                }
                if ($key == count($record) - 1) {
                    $id = $value->id;
                }
            }
        }


    }
}
