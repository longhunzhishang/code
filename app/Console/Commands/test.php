<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Controller;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\ESUtil;
use App\Http\Utils\DSLUtil;
use App\Http\Utils\Tools;
use App\Http\Utils\Tools2;
use App\Http\Utils\UserAccess;
use App\Http\Utils\GetMac;
use Search;
use DB;
use Excel;
use Mail;
use App\Addata;
use App\AddataScore;
use App\AdTitle;
use App\AdHostRe;
use App\Domains;
use App\AddataRankRole;
use App\AddataRankList;
use App\AddataAttr;
use App\DomainTracker;
use App\Synonym;

class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test';

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
        $array = DB::table("title_input")->get();
        foreach ($array as $key => $value){
//            $curl_param = [
//                "from" => 0,
//                "size" => 600,
//                "query" => [
//                    "function_score" => [
//                        "filter" => [
//                            "bool" => [
//                                "must" => [
//                                    [
//                                        "multi_match" => [
//                                            "query" => $value->name,
//                                            "fields" => [
//                                                "title"
//                                            ]
//                                        ]
//                                    ]
//                                ]
//                            ]
//                        ],
//                        "functions" => [
//                            [
//                                "filter" => [
//                                    "match" => [
//                                        "title" => [
//                                            "query" => $value->name,
//                                            "minimum_should_match" => "100%"
//                                        ]
//                                    ]
//                                ],
//                                "weight" => "10"
//                            ]
//                        ],
//                        "score_mode" => "sum",
//                        "max_boost" => "10"
//                    ]
//                ]
//            ];
            $curl_param = [
                "from" => 0,
                "size" => 500,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "match" => [
                                    "title" => $value->name
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $data = DBUtil::get_parse_es_data($rs);
            foreach ($data["data"] as $key1 => $value1){
                $rs = DB::table("title_test")->where("title",$value1->title_no)->first();
                if(!$rs)DB::table("title_test")->insert(["title"=>$value1->title_no]);
            }
        }
        echo "success";

    }
}
