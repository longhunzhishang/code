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
class pushSubscribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pushSubscribe';

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
     DB::table('subscribe_log')->insert(["user_id" =>1, "store_id" => 2, "type" => 1, "infor" =>4, "created_time" => time()]);
        dd(1111);
        $id = 0;
        while (DB::table('user_store')->where('id', ">", $id)->limit(100)->count() > 0) {
            $record = DB::table('user_store')->where('id', ">", $id)->limit(100)->get();
            foreach ($record as $key => $value) {
                if ($key == count($record) - 1) {
                    $id = $value->id;
                }
                if ($value->type == 1) {
                    $curl_param = [
                        "query" =>
                            ["bool" =>
                                ["must" => [
                                    ["term" => ["id" => $value->store_id]]
                                ]]
                            ],
                        "size" => 1,
                    ];
                    $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
                    $data_temp = DBUtil::get_parse_es_data($rs2);
                    $data1 = $this->getHostMessage($data_temp["data"][0]->host);
                    $data2 = $data1["ads"];
                    $data3 = $data_temp["data"][0]->role == 0 ? '3' : $data_temp["data"][0]->role;
                    $role = $data3 == 1 ? "媒体" : ($data3 == 2 ? "广告主" : "广告科技");
                    $cname = $data_temp["data"][0]->cname;
                    $infor = ["role" => $role, "cname" => $cname, "amount" => $data2];
                    $rs = DB::table('subscribe_log')->where("user_id", $value->user_id)->where("store_id", $value->store_id)->first();
                    if (!isset($rs)) {
                        DB::table('subscribe_log')->insert(["user_id" => $value->user_id, "store_id" => $value->store_id, "type" => 1, "infor" => serialize($infor), "created_time" => time()]);
                    } else {
                        $infor = unserialize($rs->infor);
                        if ($data2 > $infor["amount"]) {
                            $rs2 = DB::connection('mysql_oc2018')->table('users')->where("id", $value->user_id)->first();
                            $rest = $data2 - $infor["amount"];
                            $infor["amount"] = $data2;
                            DB::table('subscribe_log')->where("id", $rs->id)->update(["infor" => serialize($infor), "created_time" => time()]);
                           $data["data"] = ["role" => $infor["role"], "cname" => $infor["cname"], "rest" => $rest];
                           $data['subject'] = "ADBUG广告档案数据更新提醒";
                           $data['email'] = $rs2->email;
                           $data["view"] = "adbug_subs_notify";
                           Tools::sendTemplateMailNew($data);
                        }


                    }


                } elseif ($value->type == 0) {

                }


            }
        }


    }

    public function getHostMessage($host)
    {
        $curl_param_domain = [
            'size' => 1,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'host_no' => $host
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
        $data_domain = DBUtil::get_parse_es_data($rs);
        $vrole = $data_domain["data"][0]->role;
        if ($vrole == 0) {
            $vrole = 3;
        }
        $data = empty($this->getAdsList($data_domain["data"][0]->host_no, $vrole)) ? "" : $this->getAdsList($data_domain["data"][0]->host_no, $vrole);
        return $data["aggs"];
    }

    private function getAdsList($rv, $role)
    {
        $role_val = $rv;
        $url = config('param.ES_URL') . config('param.DOMAIN_SEARCH') . '/' . config('param.ES_SEARCH');
        //营销活动
        if ($role == 4) {
            $curl_param = [
                'query' => [
                    'term' => [
                        'md5' => $role_val
                    ]
                ]
            ];
            $url = config('param.ES_URL') . config('param.SUBJECT_SEARCH6') . '/' . config('param.ES_SEARCH');

        } else {
            $curl_param = [
                'query' => [
                    'term' => [
                        'host_no' => $role_val
                    ]
                ]
            ];
        }
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");

        $data = DBUtil::get_parse_es_data3($rs);

        if (empty($data['data'])) {
            $array = [];
            return $array;
            return false;
        }

        $ad_id = $role == 4 ? $data['data'][0]->ad_id : 0;


        $field = 'advertiser_na';

        switch ($role) {
            case '2':
                $field = 'advertiser_na';
                break;
            case '1':
                $field = 'publisher_na';
                break;
            case '3':
                $field = 'tracker_list';
                break;
            case '4':
                $field = 'subject_md5';
                break;
            case '6':
                $field = 'tags_list';
                break;
        }


        $curl_param = [
            'size' => 0,
            'query' => [
                'term' => [
                    $field => $role_val
                ]
            ],
            'aggs' => [
                'ads' => [
                    'cardinality' => [
                        'field' => 'id'
                    ]
                ]
                ,
                'subjects' => [
                    'cardinality' => [
                        'field' => 'subject_md5'
                    ]
                ]
                ,
                'publishers' => [
                    'cardinality' => [
                        'field' => 'publisher_na'
                    ]
                ]
                ,
                'trackers' => [
                    'cardinality' => [
                        'field' => 'tracker_list'
                    ]
                ],
                'advertisers' => [
                    'cardinality' => [
                        'field' => 'advertiser_na'
                    ]
                ],
                'first_detected' => [
                    'min' => [
                        'field' => 'created_date'
                    ]
                ],
                'last_detected' => [
                    'max' => [
                        'field' => 'created_date'
                    ]
                ]

            ]
        ];
        $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");

        $aggs = $this->getAggs($rs);
        if ($role == 3 && $aggs["ads"] == 0 && $aggs["advertisers"] == 0 && $aggs["publishers"] == 0 && $aggs["subjects"] == 0) {
            $curl_param = [
                'size' => 0,
                'query' => [
                    'term' => [
                        'tags_list' => $role_val
                    ]
                ],
                'aggs' => [
                    'ads' => [
                        'cardinality' => [
                            'field' => 'id'
                        ]
                    ]
                    ,
                    'subjects' => [
                        'cardinality' => [
                            'field' => 'subject_md5'
                        ]
                    ]
                    ,
                    'publishers' => [
                        'cardinality' => [
                            'field' => 'publisher_na'
                        ]
                    ]
                    ,
                    'trackers' => [
                        'cardinality' => [
                            'field' => 'tracker_list'
                        ]
                    ],
                    'advertisers' => [
                        'cardinality' => [
                            'field' => 'advertiser_na'
                        ]
                    ],
                    'first_detected' => [
                        'min' => [
                            'field' => 'created_date'
                        ]
                    ],
                    'last_detected' => [
                        'max' => [
                            'field' => 'created_date'
                        ]
                    ]

                ]
            ];
            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $aggs = $this->getAggs($rs);
        }
        if ($role == 4) {
            if (!empty($ad_id)) {
                $ad_info = $this->getAdDetail($ad_id);
                $aggs['advertiser_host'] = empty($ad_info['data'][0]) ? '' : $ad_info['data'][0]->advertiser_na;
                $aggs['advertiser_cname'] = empty($ad_info['data'][0]) ? '' : $ad_info['data'][0]->advertiser_name_title;
            }
            if (!empty($aggs['first_detected'])) {
                $aggs['first_detected'] = date('Y-m-d H:i:s', $aggs['first_detected'] / 1000);
            }

            if (!empty($aggs['last_detected'])) {
                $aggs['last_detected'] = date('Y-m-d H:i:s', $aggs['last_detected'] / 1000);
            }
        }
        $data['aggs'] = $aggs;
        $data['status'] = 200;
        return $data;
    }

    /**
     * 返回统计数据
     * @param  [type] $rs [description]
     * @return [type]     [description]
     */
    private function getAggs($rs)
    {
        $ads_json = json_decode($rs, true);
        $aggs['ads'] = empty($ads_json['aggregations']['ads']['value']) ? 0 : $ads_json['aggregations']['ads']['value'];
        $aggs['trackers'] = empty($ads_json['aggregations']['trackers']['value']) ? 0 : $ads_json['aggregations']['trackers']['value'];
        $aggs['advertisers'] = empty($ads_json['aggregations']['advertisers']['value']) ? 0 : $ads_json['aggregations']['advertisers']['value'];

        $aggs['publishers'] = empty($ads_json['aggregations']['publishers']['value']) ? 0 : $ads_json['aggregations']['publishers']['value'];

        $aggs['subjects'] = empty($ads_json['aggregations']['subjects']['value']) ? 0 : $ads_json['aggregations']['subjects']['value'];

        $aggs['first_detected'] = empty($ads_json['aggregations']['first_detected']['value']) ? 0 : $ads_json['aggregations']['first_detected']['value'];

        $aggs['last_detected'] = empty($ads_json['aggregations']['last_detected']['value']) ? 0 : $ads_json['aggregations']['last_detected']['value'];

        return $aggs;
    }


}
