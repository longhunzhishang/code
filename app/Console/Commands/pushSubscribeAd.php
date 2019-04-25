<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Utils\Tools;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use DB;

class pushSubscribeAd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pushSubscribeAd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户订阅创意提醒';

    /**=
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
        $user_id = 0;
        while (DB::table('user_store')->select("user_id")->distinct()->where('user_id', ">", $user_id)->where('type', "0")->orderBy("user_id", "ASC")->limit(100)->count() > 0) {
            $record = DB::table('user_store')->select("user_id")->distinct()->where('user_id', ">", $user_id)->where('type', "0")->orderBy("user_id", "ASC")->limit(100)->get();
            foreach ($record as $key => $value) {
                if ($key == count($record) - 1) {
                    $user_id = $value->user_id;
                }
                $rs1 = DB::table('user_store')->where("user_id", $value->user_id)->where("type", "0")->get();
                $rs4 = DB::connection('mysql_oc2018')->table('users')->where("id", $value->user_id)->first();
                $output_data = [];
                foreach ($rs1 as $key1 => $value1) {
                    $curl_param = [
                        "query" =>
                            ["bool" =>
                                ["must" => [
                                    ["term" => ["id" => $value1->store_id]]
                                ]]
                            ],
                        "size" => 1,
                    ];
                    $rs3 = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $data_temp = DBUtil::get_parse_es_data($rs3);
                    $am_url = $data_temp["data"][0]->am_url;
                    $curl_param = [
                        "query" =>
                            ["bool" =>
                                ["must" => [
                                    ["term" => ["fingerprint" => $data_temp["data"][0]->fingerprint]],
                                    ["range" =>
                                        ["created_date" => [
                                            "gt" => $value1->created_time * 1000,
                                            "lt" => time() * 1000
                                        ]
                                        ]
                                    ]
                                ],
                                ]],
                        "size" => 1
                    ];
                    $rs3 = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $data_temp = DBUtil::get_parse_es_data($rs3);
                    if (!empty($data_temp["data"])) {
                        foreach ($data_temp["data"] as $key2 => $value2) {
                            $output_data_tmp["init_image"] = "https://file.adbug.cn/m/image/".$am_url;
                            $output_data_tmp["ads_id"] = "http://www.adbug.cn/#/ad/detail/".$value2->id;
                            array_push($output_data,$output_data_tmp);
                        }
                    }
                    DB::table('user_store')->where("user_id", $value->user_id)->where("type", "0")->where("store_id",$value1->store_id)->update(["created_time"=>time()]);
                }
                if(!empty($output_data)){
                    $data['subject'] = "ADBUG广告创意收藏 监测提醒";
                    $data['email'] = $rs4->email;
                    $data['outdata'] = $output_data;
                    $data["view"] = "adnotice";
                    Tools::sendMailWarn($data);
                }
            }

        }
        echo "success";
    }




}