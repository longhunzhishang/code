<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use DB;

class dataAdd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataAdd';

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
        $initid = 10156;
        while (DB::table('data5')->where('id', ">", $initid)->limit(100)->count() > 0) {
            $rs = DB::table('data5')->where('id', ">", $initid)->limit(100)->get();
            foreach ($rs as $key => $value) {
                if ($key == count($rs) - 1) {
                    $initid = $value->id;
                }
                $curl_param = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => $value->website_url_info]]
                            ]]
                        ],
                ];
                $rs1 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
                $data = DBUtil::get_parse_es_data($rs1);
                if (empty($data["data"])) {
                    if(empty($value->website_url_info)){
                        DB::table('data5_action_log')->insert(["domain" => "no", "type" => 0,"domain_id"=>$value->id]);
                    }else{
                        DB::table('data5_action_log')->insert(["domain" => $value->website_url_info, "type" => 0,"domain_id"=>$value->id]);
                    }
                } else {
                    $domain_id = $data["data"][0]->id;
                    $curl_param = [
                        "doc" => [
                            "parent_host" => $value->domain
                        ]
                    ];
                    $url = config('param.ES_URL') . config('param.DOMAIN_SEARCH') . '/' . $domain_id . '/_update';
                    $rs2 = HttpUtil::send_curl_methods($url, $curl_param, "POST");
                    $rs2 = json_decode($rs2,true);
                    if ($rs2["_shards"]["failed"] == 0) {
                        DB::table('data5_action_log')->insert(["domain" => $value->website_url_info, "type" => 1,"domain_id"=>$value->id]);
                    } else {
                        DB::table('data5_action_log')->insert(["domain" => $value->website_url_info, "type" => 3,"domain_id"=>$value->id]);
                    }
                }
            }

        }
        echo "success";
    }

}
