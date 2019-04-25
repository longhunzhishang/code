<?php

namespace App\Http\Controllers\Api9;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
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

/**
 *  1 获取 所有 域名
 *  2 domians sort 排序
 *  3 getPubTacAdsIndex 广告主 媒体 追踪者 index  接口
 */
class ApiController extends Controller
{

    protected $page = 0;
    protected $size = 0;

    protected $from = 0;
    protected $aggs = [];
    protected $aggs_size = 0;
    protected $aggs_all = 0;
    protected $user = [];

    //adbug 用户登录 加密 key
    protected $user_key = 'oS1wcdz9ysuxalNhH5AXkWVC4vbFE7ZDYOfnMQPq';

    /**
     * 初始化一些数据
     * @param Request $request [description]
     */
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
        $this->middleware('token');
        $this->size = empty($request->size) ? config('param.SIZE') : $request->size;

        if ($this->size > 80) $this->size = 80;

        $this->page = empty($request->page) ? 1 : $request->page;

        $this->aggs_size = $this->size;
        $this->aggs_all = $this->page * $this->aggs_size;

        if ($this->aggs_all > 1200) $this->aggs_all = 1200;

        if ($this->page > 1) {
            $this->from = ($this->page - 1) * $this->size;
        }
        $this->user = UserAccess::getUserInfo();

    }

    /**
     * 获取 中英文对比
     * @param  [type] $synonym [description]
     * @return [type]          [description]
     */
    private function getSynonym($en_ch, $param)
    {
        $synonym = '';

        if ($en_ch == 1) {
            $synonym = Synonym::where('ename', $param)->get();

            $synonym = !count($synonym) ? Synonym::where('ename', 'like', '%' . $param . '%')->orwhere('host', 'like', '%' . $param . '%')->limit(3)->get() : $synonym;

        } else if ($en_ch == 2 || $en_ch == 3) {
            $synonym = Synonym::where('cname', $param)->get();
            $synonym = !count($synonym) ? Synonym::where('cname', 'like', '%' . $param . '%')->orwhere('ename', 'like', '%' . $param . '%')->limit(3)->get() : $synonym;
        }
        $c_param = '';
        $c_host = '';
        if ($synonym) {
            foreach ($synonym as $key => $value) {
                $c_param = $c_param . ' ' . $value['cname'] . ' ' . $value['ename'];
                $c_host = $c_host . ' ' . $value['host'];
            }
        }

        return ['c_host' => $c_host, 'c_param' => $c_param];
    }


    private function getSynonymt($en_ch, $param)
    {
        $synonym = '';

        if ($en_ch == 1) {
            $synonym = Synonym::where('ename', $param)->get();

            $synonym = !count($synonym) ? Synonym::where('ename', 'like', '%' . $param . '%')->orwhere('host', 'like', '%' . $param . '%')->limit(3)->get() : $synonym;

        } else if ($en_ch == 2 || $en_ch == 3) {
            $synonym = Synonym::where('cname', $param)->get();
            $synonym = !count($synonym) ? Synonym::where('cname', 'like', '%' . $param . '%')->orwhere('ename', 'like', '%' . $param . '%')->limit(3)->get() : $synonym;
        }
        $c_param = '';
        $c_host = '';
        if ($synonym) {
            foreach ($synonym as $key => $value) {
                $c_param = $value['cname'] ;
                $c_host =  $value['host'];
            }
        }

        return ['c_host' => $c_host, 'c_param' => $c_param];
    }


    /**
     * key rxJOYsbo04R6vpK13NViAmIhWS7T5PFdyq8ujEHk
     * @param  [type] $str [description]
     * @param  string $key [description]
     * @return [type]      [description]
     */


    public function getadvertiserDomain($data)
    {
        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            $value = is_array($value) ? $value : (array)$value;

            if (!empty($value['advertiser']) && !in_array($value['advertiser'], $domain_tmp)) {
                $domain_tmp[] = $value['advertiser'];
            }
        }
        $domain_arr = [];
        foreach ($domain_tmp as $key => $value) {
            $curl_param = [
                'query' => [
                    'term' => [
                        'host' => $value
                    ]
                ]
            ];
            $url = config('param.ES_URL') . config('param.DOMAIN_SEARCH_4') . '/' . config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $data_d = DBUtil::get_parse_es_data3($rs);
            if (!empty($data_d['data'])) {
                $domain_arr[] = (array)$data_d['data'][0];
            }
        }
        $return_data = [];
        foreach ($data['data'] as $key => $value) {
            $return_data[] = (array)$value;
        }
        foreach ($return_data as $key => &$value) {
            if (!empty($value['advertiser'])) {
                foreach ($domain_arr as $k => $v) {
                    if ($v['host'] == $value['advertiser']) {
                        $value['advertiser'] = $v;
                    }
                }
            }

        }
        $data['data'] = $return_data;
        return $data;
    }

    /**
     * 根据 iscampaign 添加 参数
     * @return boolean [description]
     */
    private function isCurlCampaignMerge($curl_param, $iscampaign, $c_host, $oldparam, $param)
    {
        // dd($c_host);
        if (!$iscampaign) {
            $filter_range_es = ESUtil::filterESParam4($param, $c_host, $oldparam);
            foreach ($filter_range_es as $key => $value) {
                array_push($curl_param['query']['function_score']['functions'], $value);
            }
            $curl_param['min_score'] = 1;
        } else if ($iscampaign == 1) {

            $campain_title = [
                'filter' => [
                    'match' => [
                        'title' => [
                            'query' => $param,
                            'minimum_should_match' => '100%'
                        ]
                    ]
                ],
                "weight" => '2'
            ];
            array_push($curl_param['query']['function_score']['functions'], $campain_title);
        } else if ($iscampaign == 2) {
            $curl_param = $this->compainCurl($curl_param, config('param.DOMAIN_SEARCH_HOST'), empty($c_host) ? $oldparam : $c_host);

        }
        return $curl_param;
    }

    /**
     * 获取对应的curl_param
     * @param  [type] $oldparam [description]
     * @param  [type] $size     [description]
     * @return [type]           [description]
     */
    private function getCurlParam($oldparam, $size, $last_id, $from)
    {
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $oldparam) : '';
        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $oldparam : $c_param . ' ' . $c_host;
        // 默认
        $curl_param = [
            'from' => $from,
            'size' => $size,
            'query' => [
                'function_score' => [
                    'filter' => [
                        'bool' => [
                            'must' => [
                                0 => ["bool" => [
                                    "should" => []
                                ]],
                                1 => ["bool" => [
                                    "should" => []
                                ]],
                                2=> [
                                    'multi_match' => [
                                        'query' => $param,
                                        'fields' => [
                                            'title',
                                            'advertiser_name',
                                            'advertiser_name_title',
                                            'keywords_v1.advertiser',
                                            'keywords_v1.title'
                                        ]
                                    ]
                                ]
                            ],
                            'must_not'=>[],
                            'should'=>[]
                        ]
                    ],
                    'functions' => [

                    ],
                    'score_mode' => 'sum',
                    'max_boost' => '10'
                ],
            ],
            'sort' => [
                [
                    '_score'=>[
                        'order'=>'desc'
                    ]
                ],
                [
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ]

            ]
        ];

        $curl_param = $this->isCurlCampaignMerge($curl_param, '', $c_host, $oldparam, $param);

        if (in_array($oldparam, config('param.WHITE_MIN_SCORE'))) {
            $curl_param['min_score'] = 0;
        }
        return $curl_param;
    }


    private function getCurlParam2($oldparam, $size, $last_id, $from)
    {
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $oldparam) : '';
        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $oldparam : $c_param . ' ' . $c_host;
        // 默认
        $curl_param = [
            'from' => $from,
            'size' => $size,
            'query' => [
                'function_score' => [
                    'filter' => [
                        'bool' => [
                            'must' => [
                                0 => ["bool" => [
                                    "should" => []
                                ]],
                                1 => ["bool" => [
                                    "should" => []
                                ]],
                                2 => ["bool" => [
                                    "should" => [
                                        ['match_phrase'=>[
                                            'title'=>[
                                                'query'=>$oldparam
                                            ]
                                        ]],
                                        ['match_phrase'=>[
                                            'advertiser_name'=>[
                                                'query'=>$oldparam
                                            ]
                                        ]],
                                        ['match_phrase'=>[
                                            'advertiser_name_title'=>[
                                                'query'=>$oldparam
                                            ]
                                        ]],
                                        ['match_phrase'=>[
                                            'keywords_v1.advertiser'=>[
                                                'query'=>$oldparam
                                            ]
                                        ]],
                                        ['match_phrase'=>[
                                            'keywords_v1.title'=>[
                                                'query'=>$oldparam
                                            ]
                                        ]]
                                    ]
                                ]],
                            ],
                            'must_not'=>[],
                            'should'=>[]
                        ]
                    ],
                    'functions' => [

                    ],
                    'score_mode' => 'sum',
                    'max_boost' => '10'
                ],
            ],
            'sort' => [
                [
                    '_score'=>[
                        'order'=>'desc'
                    ]
                ],
                [
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ]

            ]
        ];

        $curl_param = $this->isCurlCampaignMerge($curl_param, '', $c_host, $oldparam, $param);

        if (in_array($oldparam, config('param.WHITE_MIN_SCORE'))) {
            $curl_param['min_score'] = 0;
        }
        return $curl_param;
    }


    private function NewGetCurlParam($oldparam, $size, $from, $fields)
    {
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $oldparam) : '';
        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $oldparam : $c_param . ' ' . $c_host;
        // 默认

        $curl_param = [
            'from' => $from,
            'size' => $size,
            'min_score' => 2,
            'query' => [
                'function_score' => [
                    'filter' => [
                        'bool' => [
                            'should' => [
                                'multi_match' => [
                                    'query' => $param,
                                    'fields' => $fields
                                ]
                            ],
                            'must' => []
                        ]
                    ],
                    'functions' => [

                    ],
                    'score_mode' => 'sum',
                    'max_boost' => '10'
                ],
            ],
            'sort' => [
                [
                    '_score'=>[
                        'order'=>'desc'
                    ]
                ],
                [
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ]

            ]
        ];


        $curl_param = $this->isCurlCampaignMerge($curl_param, '', $c_host, $oldparam, $param);

        if (in_array($oldparam, config('param.WHITE_MIN_SCORE'))) {
            $curl_param['min_score'] = 0;
        }
        return $curl_param;
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


    private function wdSplit($wd)
    {
        $oldparam = urldecode($wd);
        $data["text"] = $oldparam;
        $rs = HttpUtil::parse_curl_search("", $data);
        $rs = json_decode($rs, true);
        $re = [];
        foreach ($rs["tokens"] as $key => $value) {
            array_push($re, $value["token"]);
        }
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch == 1 ? $this->getSynonymt($en_ch, $oldparam) : "";
        if(!empty($synonym['c_param'])){
            array_push($re, $synonym['c_param']);
        }

        return $re;

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
            '_source' => ['id'],
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
        $rs_tmp = json_decode($rs, true);
        $aggs['ads'] = $rs_tmp["hits"]["total"];
        if($role == 3 && $aggs["ads"] == 0 && $aggs["advertisers"] == 0 && $aggs["publishers"] == 0 && $aggs["subjects"] == 0){
            $curl_param = [
                'size' => 0,
                '_source' => ['id'],
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
            $rs_tmp = json_decode($rs, true);
            $aggs['ads'] = $rs_tmp["hits"]["total"];
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

     public function getAdsSearch(Request $request)
    {  
       // Log::info($request->header());
       // Log::info($request->wd);
        if (empty($this->user)) {
            $ip = $request->getClientIp();
            $key1 = md5($ip);
            $aa = Redis::get($key1);
            if (empty($aa)) {
                Redis::setex($key1, 300, 0);
            } else {
                Redis::incrby($key1, 1);
            }
            if ($aa > 5) return false;
        }
        $search_time = Cookie::get('search_time2');
        if (!isset($search_time)) {
            $search_time = ["time" => time(), "lock" => 0];
            Cookie::queue('search_time2', $search_time);
        }
        if (date("Ymd", $search_time["time"]) != date("Ymd")) $search_time = ["time" => time(), "lock" => 0];
        if (empty($this->user) && $this->page > 1) return HttpUtil::retrun_json(['message' => "未注册用户只能查看前50个创意", 'status' => 410], 200);//未注册用户
        if (!empty($this->user)) {
            if (empty($this->user["roleinfor"])) return HttpUtil::retrun_json(['message' => "用户数据错误.请联系管理员", 'status' => 212], 200);
            foreach ($this->user["roleinfor"] as $key => $value) {
                if ($value["type"] == "search") {
                    $searchinfor = $value;
                    break;
                }
            }
              foreach ($this->user["roleinfor"] as $key => $value) {
                if ($value["type"] == "selectSize") {
                    $selectSize = $value;
                    break;
                }
            }
            if ($searchinfor["status"] == false) return HttpUtil::retrun_json(['message' => "用户搜索权限已被禁用,请联系客服", 'status' => 211], 200);
            foreach ($searchinfor["limit"] as $key => $value) {
                if ($value["type"] == "page") {
                    $page_max = $value["max"];
                } elseif ($value["type"] == "times") {
                    $times_max = $value["max"];
                }
            }
             if (isset($selectSize) && $selectSize["status"] == false) return HttpUtil::retrun_json(['message' => "用户搜索权限已被禁用,请联系客服", 'status' => 211], 200);
            if(isset($selectSize)){
            foreach ($selectSize["limit"] as $key => $value) {
                if ($value["type"] == "times") {
                    $this->size = $value["max"];
                } 
            }
            }
            $user_code = ["0"=>"100","1"=>"101","2"=>"202"];
            $u_code = $user_code[$this->user["character"]] ;
            if (isset($page_max) && $this->page > $page_max){
                if(isset($u_code)) {
                    return HttpUtil::retrun_json(['message' => "用户只能查看前" . $page_max * 50 . "个创意", 'status' => 411, "code" => $u_code], 200);//用户查看创意权限
                }else{
                    return HttpUtil::retrun_json(['message' => "用户只能查看前" . $page_max * 50 . "个创意", 'status' => 411, "code" => "000"], 200);//用户查看创意权限

                }
            }
            // if (isset($page_max) && $this->page > $page_max) return HttpUtil::retrun_json(['message' => "用户只能查看前" . $page_max * 50 . "个创意", 'status' => 411], 200);//用户查看创意权限
        }
        $width = empty($request->width) ? 0 : $request->width;
        $min_width = empty($request->min_width) ? 0 : $request->min_width;
        $min_height = empty($request->min_height) ? 0 : $request->min_height;
        $height = empty($request->height) ? 0 : $request->height;
        $order_type = empty($request->order_type) ? "0" : "1"; //0代表时间,1代表相关性
        $advertiser = empty($request->advertiser) ? "" : $request->advertiser;
        $publisher = empty($request->publisher) ? "" : $request->publisher;
        $tracker = empty($request->tracker) ? "" : $request->tracker;
        $max_width = max($width, $min_width);
        $min_width = min($width, $min_width);
        $accurate = empty($request->accurate) ? "0" : $request->accurate;//模糊查询,准确查询
        $max_height = max($height, $min_height);
        $min_height = min($height, $min_height);
        $subject = empty($request->subject_title) ? "" : $request->subject_title;
        $negative = empty($request->negative) ? "" : $request->negative;
        $categories = empty($request->categories) ? "" : $request->categories;
        $area = empty($request->area) ? "" : $request->area;

        $is_width = ($max_width === $min_width ? 1 : 0);
        $is_height = ($max_height === $min_height ? 1 : 0);

        $iscampaign = empty($request->iscampaign) ? '' : $request->iscampaign;

        if (($this->size && $this->size > 10000) || !is_numeric($this->size)) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $sort = empty($request->sort) ? 'desc' : $request->sort;

        if (!in_array($sort, ['desc', 'asc', 'DESC', 'ASC'])) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $param = empty($request->wd) ? '' : $request->wd;
        $param2 = empty($request->wd2) ? '' : $request->wd2;
        $param1 = $param;
        if (!empty($param) && empty($this->user) && date("Ymd", $search_time["time"]) == date("Ymd") && $search_time["lock"] >= 5) return HttpUtil::retrun_json(['message' => "非注册会员每日搜索上限5次", 'status' => 412], 200);//非注册用户
        if (!empty($param) && !empty($this->user) && isset($times_max) && $this->user["search_times"] > $times_max) {
            $u_code = $user_code[$this->user["character"]] ;
            if(isset($u_code)) {
                return HttpUtil::retrun_json(['message' => "用户每日搜索上限" . $times_max . "次", 'status' => 412,"code"=>$u_code], 200);
            }else{
                return HttpUtil::retrun_json(['message' => "用户每日搜索上限" . $times_max . "次", 'status' => 412,"code"=>"000"], 200);
            }
        }
        // if (!empty($param) && !empty($this->user) && isset($times_max) && $this->user["search_times"] > $times_max) return HttpUtil::retrun_json(['message' => "用户每日搜索上限" . $times_max . "次", 'status' => 412], 200);
        $oldparam = urldecode($param);
        $oldparam1 = urldecode($param);
        $param = urldecode($param);
        $oldparam2 = urldecode($param2);
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        if (!empty($request->platform) && strpos($request->platform, ",") == true) {
            $platform1 = explode(",", $request->platform);
            $attribute04 = [];
            $platform = [];
            foreach ($platform1 as $key => $value) {
                if ($value == 1) {
                    array_push($platform, "1");
                    continue;
                }
                if ($value == 2) {
                    array_push($platform, "2");
                    continue;
                }
                if ($value == 3) {
                    array_push($attribute04, "android");
                    continue;
                }
                if ($value == 4) {
                    array_push($attribute04, "ios");
                    continue;
                }
                if ($value == 5) {
                    array_push($platform, "4");
                    continue;
                    // $value = 4;
                }
            }


        } else if (!empty($request->platform) && strpos($request->platform, ",") == false) {
            $attribute04 = [];
            $platform = [];
            if ($request->platform == 3) {
                array_push($attribute04, "android");
            } elseif ($request->platform == 4) {
                array_push($attribute04, "ios");
            } elseif ($request->platform == 5) {
                array_push($platform, "4");
            } else {
                array_push($platform, $request->platform);
            }
        } else {
            $attribute04 = [];
            $platform = [];
        }
        $startTime = empty($request->start_time) ? '' : $request->start_time;
        $endTime = empty($request->end_time) ? '' : $request->end_time;
        $shape = empty($request->shape) ? [] : explode(",", $request->shape);  // 0  所有尺寸  1  ， 2 ，3 ...
        if (!empty($request->type) && strpos($request->type, ",") == true) {
            $type = explode(",", $request->type);
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];
        } else if (!empty($request->type) && strpos($request->type, ",") == false) {
            $type[0] = $request->type;
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];

        } else {
            $type = [];
            $material = [];

        }
        // 相关性 查询
        $sortmode = empty($request->sortmode) ? '' : $request->sortmode;

        if ($startTime) {
            if ($endTime < $startTime) {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data, 200);
            }
        }

        $is_time = ($startTime === $endTime ? 1 : 0);

        // 验证参数格式
        $valiedParam = [
            'shape' => $shape
        ];
        if ($is_width) $valiedParam['width'] = $max_width;
        if ($is_height) $valiedParam['height'] = $max_height;
        $valiedParam['platform'] = $platform;
        $valiedParam['attribute04'] = $attribute04;
        $valiedParam['material'] = $material;
        $valiedParam['type'] = $type;

        if (!empty($advertiser) && $advertiser != "null") {
            $vfield = 'advertiser_na';
            $advertiser_t = $advertiser;
            if (strpos($advertiser, ",") == true) {
                $advertiser_t = explode(",", $advertiser);
            }
            $valiedParam[$vfield] = $advertiser_t;
        }
        if (!empty($publisher)) {
            $vfield = 'publisher_na';
            $publisher_t = $publisher;
            if (strpos($publisher, ",") == true) {
                $publisher_t = explode(",", $publisher);
            }
            $valiedParam[$vfield] = $publisher_t;
        }
        if (!empty($tracker) && $tracker != 'null') {
            $vfield = 'tracker_list';
            $tracker_t = $tracker;
            if (strpos($tracker, ",") == true) {
                $tracker_t = explode(",", $tracker);
            }
            $valiedParam[$vfield] = $tracker_t;
        }

        if (!empty($area) && $area != 'null') {
            $vfield = 'location_v1.id';
            $area_t = $area;
            if (strpos($area, ",") == true) {
                $area_t = explode(",", $area);
            }
            $valiedParam[$vfield] = $area_t;
        }

        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $param) : '';

        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $param : $c_param;
        // 默认
        if ($accurate == 2 || $accurate == 3) {
            $curl_param = $this->getCurlParam2($oldparam, $this->size, 0, $this->from);
        } else {
            $curl_param = $this->getCurlParam($oldparam, $this->size, 0, $this->from);
        }
        //dd($curl_param);
        //dd($valiedParam);
        $curl_param = Tools::valiedParamt($valiedParam, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime, $endTime, $is_time, $max_width, $min_width, $is_width, $max_height, $min_height, $is_height);
        $curl_param = Tools::rangeParamt($range, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        if ($order_type == 1) {
            unset($curl_param["sort"]);
            $curl_param["sort"][0] = [
                "_score" => [
                    "order" => "desc"
                ]
            ];

        }

        if (empty($oldparam1)) {
            array_splice($curl_param["query"]["function_score"]["filter"]["bool"]["must"], 2, 1);
        }
        if (!empty($tracker) && $tracker == 'null') {
            $item = [
                'exists' => [
                    "field" => "tracker_list"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if (!empty($advertiser) && $advertiser == 'null') {
            $item = [
                'term' => [
                    "advertiser_na" => "*"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if ($en_ch != 1 && $accurate == 1) {
            $item = [
                'filter' => [
                    'match_phrase' => [
                        'title' => [
                            'query' => $oldparam
                        ]
                    ]
                ],
                "weight" => '4'
            ];
            array_push($curl_param['query']['function_score']['functions'], $item);
            $curl_param["query"]["function_score"]["max_boost"] = 14;
        }
        if (!empty($subject)) {
            $subject_term = [
                "match" => ["title" => ["query" => $subject, "operator" => "and", "minimum_should_match" => "100%"]]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $subject_term);
        }
        $mustno = [
            "term" => [
                "fingerprint" => "0000000000000000"
            ]
        ];
        array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must_not"], $mustno);

        $mustno = [
            "terms" => [
                "category03" => array_keys(config('param.NAGATIVE_CATEGORY'))
            ]
        ];
        array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must_not"], $mustno);

        // $mustno = [
        //     "term" => [
        //         "publisher_na" => "wechat"
        //     ]
        // ];
        // array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must_not"], $mustno);


        if (!empty($request->platform) && ($request->platform == 3 || $request->platform == 4)) {
            $must = [
                "term" => [
                    "platform" => 2
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);
        }
        // dd($curl_param);
        //echo config('param.ADDATA_SEARCH6');
        //dd(json_encode($curl_param));
        if ($accurate == 2) {
            $score_temp = [
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name_title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],

            ];
            $curl_param["query"]["function_score"]["functions"] = $score_temp;
        }
        if ($accurate == 3) {
            $score_temp = [
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name_title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],

            ];
            $curl_param["query"]["function_score"]["functions"] = $score_temp;
            $curl_param["sort"] = [
                [
                    "v1_trend_times" => [
                        "order" => "desc"
                    ]
                ],
                [
                    "_score" => [
                        "order" => "desc"
                    ]
                ],
                [
                    "date5" => [
                        "order" => "desc"
                    ]
                ]
            ];
        }
        if (!empty($negative)) {
            $array_negative["nested"] = [
                "path" => "risk_cats_v1",
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "risk_cats_v1.cat" => $negative
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            if ($negative == "98") {
                array_push($array_negative["nested"]["query"]["bool"]["must"], [
                    "range" => [
                        "risk_cats_v1.score" => [
                            "gt" => "6"
                        ]
                    ]
                ]);
            }
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $array_negative);
        }
        if (!empty($categories)) {
            $categories_arr = [$categories];
            if (strpos($categories, ",") == true) {
                $categories_arr = explode(",", $categories);
            }
            $array_categories["nested"] = [
                "path" => "risk_cats_v1",
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "terms" => [
                                    "risk_cats_v1.cat" => $categories_arr
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $array_categories);
        }
        if(!empty($oldparam2)){
            $wd_array = [
                "multi_match" => [
                    "query" => $oldparam2,
                    "fields" => [
                        "title",
                        "advertiser_name",
                        "advertiser_name_title"
                    ],
                    "type"=>"phrase"
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"],$wd_array);
        }
        $curl_param_domain = [
            "_source" => ["id"],
            "size" => 2000,
            "query" => [
                "bool"=>[
                    "must"=>[
                    ["term"=>["status"=>1]
                    ]
                    ]
                ]

            ]
        ];
        if (!empty($request->brand)) {
            if (strpos($request->brand, ",")) {
                $brand_arr = explode(",", $request->brand);
            } else {
                $brand_arr = [$request->brand];
            }

            $curl_temp = [
                "bool" => [
                    "should" => []
                ]
            ];
            foreach ($brand_arr as $key => $value) {
                array_push($curl_temp["bool"]["should"], ["multi_match" => [
                    "query" => $value,
                    "fields" => ["brand","brand_name","brand_cname"],
                    "type" => "phrase"
                ]
                ]);
            }
            array_push($curl_param_domain["query"]["bool"]["must"], $curl_temp);
        }
        if (!empty($request->industry)) {
            if (strpos($request->industry, ",")) {
                $industry_arr = explode(",", $request->industry);
            } else {
                $industry_arr = [$request->industry];
            }
            $curl_temp = [
                "bool" => [
                    "should" => []
                ]
            ];
            foreach ($industry_arr as $key => $value) {
                array_push($curl_temp["bool"]["should"], [
                    "term" => [
                        "industry_no" => "$value"
                    ]
                ]);
            }
            array_push($curl_param_domain["query"]["bool"]["must"], $curl_temp);

        }
        if (!empty($request->company)) {
            if (strpos($request->company, ",")) {
                $company_arr = explode(",", $request->company);
            } else {
                $company_arr = [$request->company];
            }
            $curl_temp = [
                "bool" => [
                    "should" => []
                ]
            ];
            foreach ($company_arr as $key => $value) {
                array_push($curl_temp["bool"]["should"], ["multi_match" => [
                    "query" => $value,
                    "fields" => ["company"],
                    "type" => "phrase"
                ]
                ]);
            }
            array_push($curl_param_domain["query"]["bool"]["must"], $curl_temp);
        }
        if(!empty($request->brand) || !empty($request->industry) || !empty($request->company)){
            $rs = HttpUtil::parse_curl_search("brand_infor_v4/brand_domain", $curl_param_domain);
            $rs = DBUtil::get_es_data($rs);
            $brand_ids = [];
            foreach ($rs["data"] as $key => $value) {
                $brand_ids[]= $value->id;
            }
            if(empty($brand_ids))$brand_ids[]=123456;
            $curl_param_domain_1 = [
                "_source" => ["host_no"],
                "size" => 3000,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "terms" => [
                                    "brand_resource" => $brand_ids
                                ]
                            ]
                        ]
                    ]

                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain_1);
            $rs = DBUtil::get_es_data($rs);
            $host_no = [];
            foreach ($rs["data"] as $key => $value) {
                $host_no[]= $value->host_no;
            }
            if(empty($host_no))$host_no[]= 123456;//不存在的广告主
            $must = [
                "terms" => [
                    "advertiser_na" => $host_no
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);
        }
        if(!empty($request->mainpublisher)){
             if (strpos($request->mainpublisher, ",")) {
                $mainpublisher_arr = explode(",", $request->mainpublisher);
            } else {
                $mainpublisher_arr = [$request->mainpublisher];
            }
            $mainpublisher_str = "";
            foreach($mainpublisher_arr as $key => $value){
              $mainpublisher_str =  $mainpublisher_str."'".$value."',";
            }
            $mainpublisher_str = rtrim($mainpublisher_str,",");
            $sql = 'SELECT host_no FROM domain where brand_publisher_cname  in(' . $request->mainpublisher . ')';
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'), $sql, "POST");
            $rs = DBUtil::get_es_data($rs);
            $main_arr = [];
            foreach ($rs["data"] as $key => $value) {
                array_push($main_arr,$value->host_no);
            }
            if(empty($main_arr))$main_arr[]= 123456;//不存在的媒体
            //$valiedParam["publisher_na"] = $main_arr;
            $must = [
                "terms" => [
                    "publisher_na" => $main_arr
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);

        }
        $curl_param["_source"] = [
            "id", "title", "platform", "type", "shape", "am_url", "am_source_url", "times", "date5","created_date","advertiser_na", "advertiser_full",
            "publisher_na", "publisher_full", "tags_list", "days", "risk_cats_v1", "role","advertiser_name","publisher_name","attribute04","sub_domain","keywords_v1","advertiser_name_title","target_url","v1_trend_times","v1_trend_last","v1_trend_days"
        ];
        //dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if (isset($tracker) && strpos($tracker, ",") == false && empty($data["data"])) {
            foreach ($curl_param["query"]["function_score"]["filter"]["bool"]["must"] as $key => $value) {
                if (array_key_exists("term", $value) && array_key_exists("tracker_list", $value["term"])) {
                    $array_temp = $curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tracker_list"];
                    unset($curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tracker_list"]);
                    $curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tags_list"] = $array_temp;
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $data = DBUtil::get_parse_es_data($rs);
                }
            }
        }
        if (empty($data)) $data['data'] = [];
        
        $data = $this->getadvertiserDomain($data);
        $data['wd'] = $this->wdSplit($oldparam1);
        $data['status'] = empty($data['data']) ? 203 : 200;
        // foreach($data['data'] as $key => $value){
        // if(strpos($value["target_url"],"itunes.apple.com") && $value["advertiser_na"] == "apple.com")unset($data['data'][$key]);
        // }
        if (!empty($param1)) {
            $search_time = ["time" => time(), "lock" => $search_time["lock"] + 1];
            Cookie::queue('search_time2', $search_time);
        }
        if (count($this->user) != 0 && !empty($data) && $this->searchnum($request)) DB::connection('mysql_oc2018')->table('users')->where('id', "=", $this->user["id"])->increment('search_times');
        return HttpUtil::retrun_json($data, 200);
    }


    private function searchnum($object)
    {
        $data = $object->all();
        if(isset($data["page"]) || isset($data["platform"]) || isset($data["type"]) || isset($data["shape"]) || isset($data["start_time"]) || isset($data["end_time"]))
        {
            return false;
        }else{
            return true;
        }
    }

    private function srecord($userid, $word)
    {
        $rs = DB::table('wordsearch')->where("user_id", $userid)->first();
        if (isset($rs) && ($rs->words != $word)) {
            DB::table('wordsearch')->where("user_id", $userid)->update(["words" => $word]);
        }
        if (!isset($rs)) {
            DB::table('wordsearch')->insert(["user_id" => $userid, "words" => $word]);
        }
    }


    public function getHostMessage(Request $request)
    {
        $host = empty($request->host) ? "" : $request->host;
        $role = empty($request->role) ? "" : $request->role;
        if (empty($host)) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
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
        if(empty($data_domain["data"]))return HttpUtil::retrun_json(["code"=>200,"data"=>[]], 200);
        if (!empty($role)) {
            $vrole = $role;
        } else {
            $vrole = $data_domain["data"][0]->role;
            if($vrole == 0){
                $vrole = 3;
            }

        }
        $data["top"] = empty($this->getAdsList($data_domain["data"][0]->host_no, $vrole))? "" : $this->getAdsList($data_domain["data"][0]->host_no, $vrole);
        $data['status'] = empty($data['data']) ? 203 : 200;
        return HttpUtil::retrun_json($data, 200);
    }

    public function getBrandMessage(Request $request){
        $brand = empty($request->brand) ? "" : $request->brand;
        $brand_role = empty($request->brand_role) ? "" : $request->brand_role;
        if ($brand_role == "advertiser") {
            $curl_param = [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "wildcard" => [
                                    "brand" => "*$brand*"
                                ]
                            ]
                           
                        ]
                    ]
                ]
            ];
        } elseif ($brand_role == "publisher") {
            $curl_param = [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "wildcard" => [
                                    "brand_publisher_cname" => "*$brand*"
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs = DBUtil::get_es_data($rs);
        $data["top"] = [];
        if($brand_role == "publisher"){
            $data["top"]["brand"] = $rs["data"][0]->brand_publisher_cname;
            $data["top"]["company"] = $rs["data"][0]->brand_publisher_company;
            $data["top"]["logo"] = $rs["data"][0]->brand_publisher_logo;
            $data["top"]["category"] = $rs["data"][0]->brand_publisher_category;
        }elseif($brand_role == "advertiser"){
            $data["top"]["brand"] = $rs["data"][0]->brand;
            $brand = $rs["data"][0]->brand;
             $curl_param = [
                "size" => 1,
                "query" => [
                    "bool" => [
                        "must" => [
                        ["term"=>["status"=>1]],
                        [
                             "match" => [
                             "brand"=>[
                                    "query" => $brand,
                                    "type" => "phrase"
                             ]
                                    
                                ]]
                            ]

                        ]
                    ]
                ];
            $rs3 = HttpUtil::parse_curl_search("brand_infor_v4/brand_domain", $curl_param);
            $rs3= DBUtil::get_es_data($rs3);
            $data["top"]["company"] = $rs["data"][0]->company_no;
            $data["top"]["logo"] = $rs3["data"][0]->logo;
            $data["top"]["category"] = $rs["data"][0]->industry_no;
        }
        $host_no = [];
        foreach ($rs["data"] as $key => $value) {
            array_push($host_no,$value->host_no);
        }
        //dd($host_no);
        $temp_data = $this->getBrandAdsList($host_no,$brand_role);
        $data["data"] = $temp_data["aggs"];
        $data["status"] = 200;
        return HttpUtil::retrun_json($data, 200);
    }
   

    private function getBrandAdsList($rv,$role){
        $field = 'advertiser_na';
        switch ($role) {
            case 'advertiser':
                $field = 'advertiser_na';
                break;
            case 'publisher':
                $field = 'publisher_na';
                break;
            case 'tracker':
                $field = 'tracker_list';
                break;
        }
        $curl_param = [
            'size' => 0,
            'query' => [
                'terms' => [
                    $field => $rv
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
        $rs_tmp = json_decode($rs, true);
        $aggs['ads'] = $rs_tmp["hits"]["total"];
        $data['aggs'] = $aggs;
        return $data;
    }


    private function getHostMessageNew($host)
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

    public function getTopHostByWd(Request $request)
    {   
        $param = empty($request->wd) ? '' : $request->wd;
        $param = urldecode($param);
        $oldparam = urldecode($param);
        if (empty($param)) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
        $is_en_ch = DBUtil::check_str_cn_en($param);
        $orderBy = empty($request->orderBy) ? 'brand_ads' : $request->orderBy;
        if ($is_en_ch == 1) {
            $sql = 'SELECT * FROM domain where ( host_no like "' . $param . '%" or ename like "%' . $param . '%" or cname like "%' . $param . '%")';
            if ($orderBy) $sql = $sql . 'order by ' . $orderBy . ' desc';
            $sql = $sql . ' limit ' . $this->from . ', ' . $this->size;
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'), $sql, "POST");
            $data = DBUtil::get_parse_es_data($rs);
            if (empty($data['data'])) {
                $param = strtolower($param);
                $sql = 'SELECT * FROM domain where ( host_no like "' . $param . '%" or ename like "%' . $param . '%" or cname like "%' . $param . '%")';
                if ($orderBy) $sql = $sql . 'order by ' . $orderBy . ' desc';
                $sql = $sql . ' limit '  . $this->from . ', '  . $this->size;
                $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'), $sql, "POST");
                $data = DBUtil::get_parse_es_data($rs);

            }
        } else {
            $curl_param = [
                "query" => [
                    "bool" => [
                        "should" => [
                            [
                                "multi_match" => [
                                    "query" => $oldparam,
                                    "fields" => ["host", "cname"],
                                    "type"=>"phrase"
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "host" => "*" . $oldparam."*",
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "cname_no" => "*" . $oldparam."*",
                                ]
                            ]
                            // [
                            //     "range" => [
                            //         "brand_ads" => ["gte" => 20],
                            //     ]
                            // ],
                            // [
                            //     "range" => [
                            //         "tracker_ads" => ["gte" => 20],
                            //     ]
                            // ],
                            // [
                            //     "range" => [
                            //         "publisher_ads" => ["gte" => 20],
                            //     ]
                            // ]
                        ]
                    ]
                ],
                "from" =>$this->from,
                "size" =>$this->size,
                "sort" => [
                    [
                        "_score" => "desc"
                    ],
                    [
                        "_script" => [
                            "type" => "number",
                            "script" => [
                                "inline" => "doc['brand_ads'].value + doc['publisher_ads'].value + doc['tracker_ads'].value",
                                "params" => ["factor" => 1.1]
                            ],
                            "order" => "desc"
                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
            $data = DBUtil::get_parse_es_data($rs);

        }

        // 暂无数据 2002
        if (empty($data['data'])) return HttpUtil::retrun_json(config('param.ES_DATA_NULL'), 200);
        $data['wd'] = $this->wdSplit($oldparam);
        $data["count"] = [];
        foreach ($data["data"] as $key => $value) {
            if(isset($value->brand_resource))continue;
            if(isset($value->brand_publisher_cname))continue;
            if($value->role == 0){
                $rrolve = 3;
            }else{
                $rrolve = $value->role;
            }
            $rsd = $this->getAdsList($value->host_no, $rrolve);
            if($rsd["aggs"]["ads"] == 0 && $rsd["aggs"]["advertisers"] == 0 && $rsd["aggs"]["publishers"] == 0)continue;
            $curl_param = [
                "query" =>
                    ["bool" =>
                        ["must" => [
                            ["term" => ["host" => $value->host_no]]
                        ]]
                    ],
                "size" => 50,
            ];
            $rs = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param);
            $rs = json_decode($rs, true);
            $rsd["risk"] = [];
            foreach ($rs["hits"]["hits"] as $key => $value){
                $rsd["risk"] = $value["_source"]["riskCategory"];
            }
            array_push($data["count"], $rsd);
        }
        $data['status'] = 200;
        unset($data['data']);
        return HttpUtil::retrun_json($data, 200);

    }


    public function rightRoleList(Request $request)
    {
        $param = empty($request->wd) ? '' : $request->wd;
        $role = empty($request->role) ? '' : $request->role;
        switch ($role) {
            case '1':
                $field = 'ispublisher';
                break;
            case '2':
                $field = 'isbrand';
                break;
            case '3':
                $field = 'istracker';
                break;
        }
        if($role == 1 || $role == 2){
            $curl_param = [
                "size" => 50,
                "_source"=>["id","host","cname"],
                "query" => [
                    "bool" => [
                        "should" => [
                            [
                                "multi_match" => [
                                    "query" => $param,
                                    "fields" => ["host", "cname","ename"]
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "host" => "*".$param."*"
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "cname" => "*".$param."*"
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "ename" => "*".$param."*"
                                ]
                            ]

                        ],
                        "must" => [
                            "term" => [
                                $field=> "1"
                            ]
                        ]
                    ]
                ],
                "sort" => [
                    [
                        "_score" => "desc"
                    ]
                ]
            ];
        }elseif($role == 3){
            $curl_param = [
                "size" => 50,
                "_source" => ["id", "host", "cname"],
                "query" => [
                    "bool" => [
                        "must" => [
                            "bool" => [
                                "should" => [
                                    [
                                        "multi_match" => [
                                            "query" => $param,
                                            "fields" => ["host", "cname", "ename"]
                                        ]
                                    ],
                                    [
                                        "wildcard" => [
                                            "host" => "*" . $param . "*"
                                        ]
                                    ],
                                    [
                                        "wildcard" => [
                                            "cname" => "*" . $param . "*"
                                        ]
                                    ],
                                    [
                                        "wildcard" => [
                                            "ename" => "*" . $param . "*"
                                        ]
                                    ]
                                ]]
                        ],
                        "should" => [
                            ["term" => [
                                $field => "1"
                            ]],
                            ["bool" => [
                                "must" => [
                                    ["term" => [
                                        "isbrand" => "0"
                                    ]],
                                    ["term" => [
                                        "ispublisher" => "0"
                                    ]],
                                    ["term" => [
                                        "istracker" => "0"
                                    ]],

                                ]
                            ]
                            ]
                        ]


                    ]






                ],
                "sort" => [
                    [
                        "_score" => "desc"
                    ]
                ]
            ];
        }
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        $data1['status'] = 200;
        $data1['data'] = $data;
        return HttpUtil::retrun_json($data1, 200);
    }

    public function hostchart(Request $request)
    {
        $host = empty($request->role_value) ? "" : $request->role_value;
        $role = empty($request->role) ? "advertiser" : $request->role;
        $limit = empty($request->limt) ? "5" : $request->limt;
        $start_time = empty($request->start_time) ? "" : $request->start_time;
        $end_time = empty($request->end_time) ? "" : $request->end_time;
        $top = empty($request->top) ? "" : $request->top;
        $second = empty($request->second) ? "" : $request->second;
        if (!empty($top)) {
            switch ($top) {
                case 'publisher':
                    $topfield = 'publisher_na';
                    break;
                case 'advertiser':
                    $topfield = 'advertiser_na';
                    break;
                case 'tracker':
                    $topfield = 'tracker_list';
                    break;
                case 'subject':
                    $topfield = 'subject_md5';
                    break;
                case 'tags':
                    $topfield = 'tags_list';
                    break;
                default:
                    $topfield = $top;
                    break;

            }
        }
        if (!empty($second)) {
            switch ($second) {
                case 'publisher':
                    $secondfield = 'publisher_na';
                    break;
                case 'advertiser':
                    $secondfield = 'advertiser_na';
                    break;
                case 'tracker':
                    $secondfield = 'tracker_list';
                    break;
                case 'subject':
                    $secondfield = 'subject_md5';
                    break;
                case 'tags':
                    $secondfield = 'tags_list';
                    break;
                default:
                    $secondfield = $second;
                    break;
            }
        }


        $curl_param = [
            "query" => [
                "bool" => [
                    "must" => [],
                    "must_not" => [],
                    "should" => [],
                ]
            ],
            "from" => 0,
            "size" => 1,
            "sort" => [
                [
                    "_score" => [
                        "order" => "desc"
                    ]
                ],
                [
                    "date5" => [
                        "order" => "desc"
                    ]
                ]
            ],
            "aggs" => [
                $top => [
                    "terms" => [
                        "field" => $topfield,
                        "size" => 0,
                        "order" => [
                            "_count" => "desc"
                        ]
                    ],
                    "aggs" => [
                        $second => [
                            "terms" => [
                                "field" => $secondfield,
                                "size" => $limit,
                                "order" => [
                                    "_count" => "desc"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        if (!empty($role)) {
            switch ($role) {
                case 'publisher':
                    $vfield = 'publisher_na';
                    break;
                case 'advertiser':
                    $vfield = 'advertiser_na';
                    break;
                case 'tracker':
                    $vfield = 'tracker_list';
                    break;
                case 'subject':
                    $vfield = 'subject_md5';
                    break;
                case 'tags':
                    $vfield = 'tags_list';
                    break;
            }
            $valiedParam[$vfield] = $host;

        }
        if (!empty($host)) {
            $param["term"] = $valiedParam;
            array_push($curl_param["query"]["bool"]["must"], $param);
        }
        if (!empty($end_time) && !empty($start_time)) {
            $b["range"] = [
                "date5" => [
                    "gte" => $start_time,
                    "lt" => $end_time,
                    "format" => "epoch_millis"
                ]];
            array_push($curl_param["query"]["bool"]["must"], $b);
        }
        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if( $vfield == "tracker_list" && empty($data["data"])){
            $curl_param["query"]["bool"]["must"][0]["term"] = ["tags_list" => $host];
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $data = DBUtil::get_parse_es_data($rs);
        }
        $data['status'] = 200;
        return HttpUtil::retrun_json($rs, 200);
    }

    public function hostchartbar(Request $request)
    {
        $host = empty($request->role_value) ? "" : $request->role_value;
        $role = empty($request->role) ? "advertiser" : $request->role;
        $start_time = empty($request->start_time) ? "" : $request->start_time;
        $end_time = empty($request->end_time) ? "" : $request->end_time;
        $target = empty($request->target) ? "" : $request->target;
        $limit = empty($request->limit) ? "10" : $request->limit;
        if (!empty($target)) {
            switch ($target) {
                case 'publisher':
                    $targetfield = 'publisher_na';
                    break;
                case 'advertiser':
                    $targetfield = 'advertiser_na';
                    break;
                case 'tracker':
                    $targetfield = 'tracker_list';
                    break;
                case 'subject':
                    $targetfield = 'subject_md5';
                    break;
                case 'tags':
                    $targetfield = 'tags_list';
                    break;
                default:
                    $targetfield = $target;
                    break;

            }
        }
        $interval = empty($request->interval) ? "day" : $request->interval;
        $curl_param = [
            "query" => [
                "bool" => [
                    "must" => [],
                    "must_not" => [],
                    "should" => [],
                ]
            ],
            "from" => 0,
            "size" => 1,
            "sort" => [
                [
                    "_score" => [
                        "order" => "desc"
                    ]
                ],
                [
                    "date5" => [
                        "order" => "desc"
                    ]
                ]
            ],
            "aggs" => [
                "dates" => [
                    "date_histogram" => [
                        "field" => "date5",
                        "interval" => $interval,
                    ],
                    "aggs" => [
                        $target => [
                            "terms" => [
                                "field" => $targetfield,
                                "size" => $limit,
                                "order" => [
                                    "_count" => "desc"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        if (!empty($role)) {
            switch ($role) {
                case 'advertiser':
                    $vfield = 'advertiser_na';
                    break;
                case 'publisher':
                    $vfield = 'publisher_na';
                    break;
                case 'tracker':
                    $vfield = 'tracker_list';
                    break;
                case 'subject':
                    $vfield = 'subject_md5';
                    break;
                case 'tags':
                    $vfield = 'tags_list';
                    break;
            }
            $valiedParam[$vfield] = $host;

        }
        if (!empty($host)) {
            $param["term"] = $valiedParam;
            array_push($curl_param["query"]["bool"]["must"], $param);
        }
        if (!empty($end_time) && !empty($start_time)) {
            $b["range"] = [
                "date5" => [
                    "gte" => $start_time,
                    "lt" => $end_time,
                    "format" => "epoch_millis"
                ]];
            array_push($curl_param["query"]["bool"]["must"], $b);
        }
        //dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if( $vfield == "tracker_list" && empty($data["data"])){
            $curl_param["query"]["bool"]["must"][0]["term"] = ["tags_list" => $host];
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $data = DBUtil::get_parse_es_data($rs);
        }
        $data['status'] = 200;
        return HttpUtil::retrun_json($rs, 200);
    }

    public function getTopSearch(Request $request)
    {
        $width = empty($request->width) ? 0 : $request->width;
        $min_width = empty($request->min_width) ? 0 : $request->min_width;
        $min_height = empty($request->min_height) ? 0 : $request->min_height;
        $height = empty($request->height) ? 0 : $request->height;
        $order_type = empty($request->order_type) ? "0" : "1"; //0代表时间,1代表相关性
        $role = empty($request->role) ? "" : $request->role;
        $host = empty($request->host) ? "" : $request->host;
        $max_width = max($width, $min_width);
        $min_width = min($width, $min_width);
        $max_height = max($height, $min_height);
        $min_height = min($height, $min_height);

        $is_width = ($max_width === $min_width ? 1 : 0);
        $is_height = ($max_height === $min_height ? 1 : 0);

        $iscampaign = empty($request->iscampaign) ? '' : $request->iscampaign;

        if (($this->size && $this->size > 10000) || !is_numeric($this->size)) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $sort = empty($request->sort) ? 'desc' : $request->sort;

        if (!in_array($sort, ['desc', 'asc', 'DESC', 'ASC'])) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $param = empty($request->wd) ? '' : $request->wd;
        $oldparam = urldecode($param);
        $param = urldecode($param);

        // if (empty($param)) return HttpUtil::retrun_json(['data' => [], 'status' => 207], 200);


        $en_ch = DBUtil::check_str_cn_en($oldparam);

        // 2017-8-28 新增 ott 过滤条件 5
        //        $platform = empty($request->platform) ? [] : $request->platform; // 所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android
        if (!empty($request->platform) && strpos($request->platform, ",") == true) {
            $platform1 = explode(",", $request->platform);
            $attribute04 = [];
            $platform = [];
            foreach ($platform1 as $key => $value) {
                if ($value == 1) {
                    array_push($platform, "1");
                    continue;
                }
                if ($value == 2) {
                    array_push($platform, "2");
                    continue;
                }
                if ($value == 3) {
                    array_push($attribute04, "Android");
                    continue;
                }
                if ($value == 4) {
                    array_push($attribute04, "Ios");
                    continue;
                }
                if ($value == 5) {
                    array_push($platform, "4");
                    continue;
                    // $value = 4;
                }
            }


        } else if (!empty($request->platform) && strpos($request->platform, ",") == false) {
            $attribute04 = [];
            $platform = [];
            if ($request->platform == 3) {
                array_push($attribute04, "Android");
            }
            if ($request->platform == 4) {
                array_push($attribute04, "Ios");
            }
            if ($request->platform == 5) {
                array_push($platform, "4");
            } else {
                array_push($platform, $request->platform);
            }
        } else {
            $attribute04 = [];
            $platform = [];
        }
        $startTime = empty($request->start_time) ? '' : $request->start_time;
        $endTime = empty($request->end_time) ? '' : $request->end_time;
        $shape = empty($request->shape) ? [] : explode(",", $request->shape);  // 0  所有尺寸  1  ， 2 ，3 ...
        //        $type = empty($request->type) ? [] : explode(",", $request->type); // 1 图片 2 视频 3 flush 4 html5 5 原生
        if (!empty($request->type) && strpos($request->type, ",") == true) {
            $type = explode(",", $request->type);
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];
        } else if (!empty($request->type) && strpos($request->type, ",") == false) {
            $type[0] = $request->type;
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];

        } else {
            $type = [];
            $material = [];

        }
        // 相关性 查询
        $sortmode = empty($request->sortmode) ? '' : $request->sortmode;

        if ($startTime) {
            if ($endTime < $startTime) {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data, 200);
            }
        }

        $is_time = ($startTime === $endTime ? 1 : 0);

        // 验证参数格式
        $valiedParam = [
            'shape' => $shape
        ];
        if ($is_width) $valiedParam['width'] = $max_width;
        if ($is_height) $valiedParam['height'] = $max_height;
        $valiedParam['platform'] = $platform;
        $valiedParam['attribute04'] = $attribute04;
        $valiedParam['material'] = $material;
        $valiedParam['type'] = $type;


        if (!empty($role)) {
            switch ($role) {
                case '2':
                    $vfield = 'advertiser_na';
                    break;
                case '1':
                    $vfield = 'publisher_na';
                    break;
                case '3':
                    $vfield = 'tracker_list';
                    break;
                case '4':
                    $vfield = 'subject_md5';
                    break;
                case '6':
                    $vfield = 'tags_list';
                    break;
            }
            $valiedParam[$vfield] = $host;

        }

        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $param) : '';

        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $param : $c_param;
        // 默认
        $curl_param = $this->getCurlParam($oldparam, $this->size, 0, $this->from);
        //dd($valiedParam);
        $curl_param = Tools::valiedParamt($valiedParam, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime, $endTime, $is_time, $max_width, $min_width, $is_width, $max_height, $min_height, $is_height);
        $curl_param = Tools::rangeParam($range, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        $code = $curl_param["query"]["function_score"]["filter"]["bool"]["should"]["multi_match"]["query"];
        unset($curl_param["query"]["function_score"]["functions"]);
        $curl_param["query"]["function_score"]["functions"][0] = [
            'filter' => [
                'match' => [
                    'title' => [
                        'query' => $code,
                        'minimum_should_match' => '100%'
                    ]
                ]
            ],
            "weight" => '4'
        ];
        $curl_param["query"]["function_score"]["functions"][1] = [
            "script_score" => [
                "script" => "width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
            ]
        ];
        if ($order_type == 1) {
            unset($curl_param["sort"]);
            $curl_param["sort"][0] = [
                "_score" => [
                    "order" => "desc"
                ]
            ];

        }

        //echo config('param.ADDATA_SEARCH6');
        //dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        //dd($data);
        $data['last_id'] = 0;
        if (empty($data)) $data['data'] = [];

        $data = $this->getadvertiserDomain($data);
        $host = [];
        foreach ($data['data'] as $key => $value) {
            array_push($host, $value["advertiser_name"] . "&ad");
            array_push($host, $value["publisher_name"] . "&pu");
            if ($key == 29) {
                break;
            }
        }

        $host = array_count_values($host);
        arsort($host);
        $host_top = key($host);
        $host_top_arr = explode("&", $host_top);
        $host_top = $host_top_arr[0];
        $curl_param_domain = [
            'size' => 1,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'host_no' => $host_top
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
        $data_domain = DBUtil::get_parse_es_data($rs);
        $data["top"] = $this->getAdsList($data_domain["data"][0]->host_no, $data_domain["data"][0]->role);

        // 按照 时间 进行过滤
        if ($sortmode == 1) $data['data'] = DBUtil::my_sort($data['data'], 'created_date', SORT_DESC, SORT_NUMERIC);

        $data['status'] = empty($data['data']) ? 203 : 200;
        return HttpUtil::retrun_json($data, 200);
    }

    public function getadsdetail(Request $request)
    {
        $fingerprint = empty($request->fingerprint) ? "" : $request->fingerprint;
        $fingerprint = explode(",", $fingerprint);
        $curl_param = [
            "query" => [
                "bool" => [
                    "must" => [
                        [
                            "terms" => [
                                "fingerprint" => $fingerprint
                            ]
                        ]
                    ]
                ]
            ],
            "aggs" => [
                "fingerprint" => [
                    "terms" => [
                        "field" => "fingerprint",
                        "size" => $this->size
                    ],
                    "aggs" => [
                        "rated" => [
                            "top_hits" => [
                                "sort" => [
                                    [
                                        "date5" => [
                                            "order" => "desc"
                                        ]
                                    ]
                                ],
                                "size" => 1
                            ]
                        ]
                    ]
                ]
            ],
            "size" => 0
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs =json_decode($rs,true);
        $rs =$rs["aggregations"]["fingerprint"]["buckets"];
        $data = [];
        foreach ($rs as $key => $value){
            array_push($data,$value["rated"]["hits"]["hits"][0]["_source"]);
        }
        $tdata["data"] = $data;
        $tdata['status'] = empty($tdata['data']) ? 203 : 200;
        return HttpUtil::retrun_json($tdata, 200);
    }

    public function gethostsdetail(Request $request)
    {
        $hosts = empty($request->hosts) ? "" : $request->hosts;
        $hosts = explode(",", $hosts);
        $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["terms" => ["host_no" => $hosts]]
                    ]]
                ],
            "size" =>$this->size,
            "from" =>$this->from
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        return HttpUtil::retrun_json($data, 200);

    }

    public function getsubjectsdetail(Request $request)
    {
        $subjects_md5 = empty($request->subjects_md5) ? "" : $request->subjects_md5;
        $subjects_md5 = explode(",", $subjects_md5);
        $data = $this->getSubjectInfos($subjects_md5, "");
        //        $size = empty($request->size) ? "10" : $request->size;
        //        $curl_param = [
        //            "query" =>
        //                ["bool" =>
        //                    ["must" => [
        //                        ["terms" => ["md5" => $subjects_md5]]
        //                    ]]
        //                ],
        //            "size" => $size
        //        ];
        //        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH6'), $curl_param);
        //        $data = DBUtil::get_parse_es_data($rs);
        return HttpUtil::retrun_json($data, 200);
    }

    private function getSubjectInfos($md5, $fileds)
    {
        $md5_tmp = [];

        foreach ($md5 as $key => $value) {
            $md5_tmp[] = $value;
        }
        $data = [];
        foreach ($md5_tmp as $key => $value) {

            $curl_param = [
                'size' => 1,
                'sort' => [
                    'created_date' => [
                        'order' => 'asc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'subject_md5' => $value
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs' => [
                    'first_time' => [
                        'min' => [
                            'field' => 'created_date'
                        ]
                    ],
                    'last_time' => [
                        'max' => [
                            'field' => 'created_date'
                        ]
                    ],
                    'ads' => [
                        'cardinality' => [
                            'field' => 'id'
                        ]
                    ],
                    'advertisers' => [
                        'cardinality' => [
                            'field' => 'advertiser_na'
                        ]
                    ],
                    'publishers' => [
                        'cardinality' => [
                            'field' => 'publisher_na'
                        ]
                    ]

                ]
            ];
            if (!empty($fileds)) {
                foreach ($fileds as $fk => $fv) {

                    if (!empty($fv)) {
                        $item = [
                            'match' => [
                                $fk => $fv
                            ]
                        ];
                        array_push($curl_param['query']['bool']['must'], $item);
                    }


                }
            }

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $data_ad = DBUtil::get_parse_es_data($rs);

            if (empty($data_ad['data'][0])) {
                continue;
            }
            $ad_info = $data_ad['data'][0];

            $rs_parse = json_decode($rs, true);
            $first_detected = $rs_parse['aggregations']['first_time']['value'];
            $last_detected = $rs_parse['aggregations']['last_time']['value'];


            $ad_info->ads = empty($rs_parse['aggregations']['ads']['value']) ? 0 : $rs_parse['aggregations']['ads']['value'];


            $ad_info->advertisers = empty($rs_parse['aggregations']['advertisers']['value']) ? 0 : $rs_parse['aggregations']['advertisers']['value'];
            $ad_info->publishers = empty($rs_parse['aggregations']['publishers']['value']) ? 0 : $rs_parse['aggregations']['publishers']['value'];


            $ad_info->first_detected = empty($first_detected) ? 0 : date('Y-m-d H:i:s', $first_detected / 1000);
            $ad_info->last_detected = empty($last_detected) ? 0 : date('Y-m-d H:i:s', $last_detected / 1000);
            $data[] = $ad_info;
        }

        $data_tmp['data'] = $data;

        if (empty($data_tmp['data'])) return [];


        //安装时间重新排序
        // $new_data = [];
        // foreach ($data_tmp['data'] as $key => $value) {
        //     $new_data[] = (array)$value;
        // }
        // // dd($new_data);
        // $data_tmp['data'] = DBUtil::my_sort($new_data,'created_date',SORT_DESC,SORT_NUMERIC);
        $data_tmp['data'] = $this->sortData($data_tmp['data'], 'last_detected');
        // 暂无数据 2002
        return $data_tmp;
    }


    private function sortData($data, $field)
    {
        //安装时间重新排序
        $new_data = [];
        foreach ($data as $sk => $sv) {
            $new_data[] = (array)$sv;
        }
        return DBUtil::my_sort($new_data, $field, SORT_DESC, SORT_NUMERIC);
    }


    public function getDomainDetail(Request $request)
    {
        $host = empty($request->host) ? "" : $request->host;
        if(strpos($host,",")){
           $host =  explode(",", $host);
        }else{
            $host =[$host];
        }
        $role = empty($request->role) ? "" : $request->role;
        $vhost = empty($request->vhost) ? "" : $request->vhost;
        $vrole = empty($request->vrole) ? "" : $request->vrole;
        $vfield = empty($request->type) ? "" : $request->type;//需要聚合的字段
        $start_time = empty($request->start_time) ? "" : $request->start_time;
        $end_time = empty($request->end_time) ? "" : $request->end_time;
        $max = empty($request->max) ? "800" : $request->max;
        if (empty($this->user))$max = 10;
        if (!empty($this->user)) {
            if (empty($this->user["roleinfor"])) return HttpUtil::retrun_json(['message' => "用户数据错误.请联系管理员", 'status' => 212], 200);
            foreach ($this->user["roleinfor"] as $key => $value) {
                if ($value["type"] == "file") {
                    $fileinfor = $value;
                    break;
                }
            }
            if ($fileinfor["status"] == false) return HttpUtil::retrun_json(['message' => "用户查看档案权限已被禁用,请联系客服", 'status' => 211], 200);
            foreach ($fileinfor["limit"] as $key => $value) {
                if ($value["type"] == "rows") {
                    $rows_max = $value["max"];
                    break;
                }
            }
            if (isset($rows_max)) $max = $rows_max;
            if(($vfield == "fingerprint" || $vfield == "subject") && $max >= 1000){
                $max = 1000;
            }
        }
        if ($vfield == "negative" && (empty($host) || empty($role))) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
        if ($vfield == "negative") {
            $data_negative = $this->getClassify($host, $role);
            $data["data"] = $data_negative;
            $data["status"] = 200;
            return HttpUtil::retrun_json($data, 200);
        }

        if ($vfield == "area" && (empty($host) || empty($role))) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
        if ($vfield == "area") {
            $data_area = $this->getAreaClassify($host, $role, $max);
            $data["data"] = $data_area;
            $data["status"] = 200;
            return HttpUtil::retrun_json($data, 200);
        }

        if (empty($host) || empty($role) || empty($start_time) || empty($end_time)) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
        $curl_param = Tools2::paramStyle1(1, $vfield, $max);
        if ($vfield == "fingerprint" || $vfield == "subject") {
            $aggs = [
                "top_hits" => [
                    "sort" => [
                        [
                            "date5" => [
                                "order" => "desc"
                            ]
                        ]
                    ],
                    "_source"=>["subject_title","created_date","date5","title","am_url","am_source_url","type","target_url","id"],
                    "size" => 1
                ]
            ];
            $curl_param["aggs"][$vfield]["aggs"]["rated"] = $aggs;

            $aggs = [
                "top_hits" => [
                    "sort" => [
                        [
                            "date5" => [
                                "order" => "asc"
                            ]
                        ]
                    ],
                    "_source"=>["date5"],
                    "size" => 1
                ]
            ];
            $curl_param["aggs"][$vfield]["aggs"]["rated2"] = $aggs;
        }
        $identity = ["advertiser" => "advertiser_na", "publisher" => "publisher_na", "tracker" => "tracker_list", "tag" => "tags_list"];//domain身份信息
        $videntity = ["advertiser" => "advertiser_na", "publisher" => "publisher_na", "tracker" => "tracker_list", "tag" => "tags_list"];//合作domain身份信息
        if ($host && $role) $valiedParam[$identity[$role]] = $host;
        if ($vhost && $vrole) $valiedParam[$videntity[$vrole]] = $vhost;
        $curl_param = Tools2::valiedParam($valiedParam, $curl_param);
        if ($start_time && $end_time) {
            $rangeParam["created_date"] = [$start_time, $end_time];
        }
        $curl_param = Tools2::rangeParam($rangeParam, $curl_param);
        $curl_param["_source"] = ["id"];
        //dd($curl_param);
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = json_decode($rs, true);
        if ($role == "tracker" && $rs["hits"]["total"] == 0) {
            $tag_arr = [
                "terms" => [
                    "tags_list" => $host
                ]
            ];
            $curl_param["query"]["bool"]["must"][0] = $tag_arr;
            $curl_param = Tools2::rangeParam($rangeParam, $curl_param);
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $rs = json_decode($rs, true);

        }
        $total = $rs["aggregations"][$vfield . "_total"]["value"];
        $data["total"] = $total;
        $data["data"] = [];
        //dd($rs["aggregations"][$vfield]["buckets"]);
        foreach ($rs["aggregations"][$vfield]["buckets"] as $key => $value) {
            if (isset($value["rated"])) {
                array_push($data["data"], ["num" => $value["doc_count"], "rs" => $value["rated"]["hits"]["hits"][0]["_source"], "last_detected" => $value["rated"]["hits"]["hits"][0]["_source"]["created_date"], "date" => $value["rated"]["hits"]["hits"][0]["_source"]["date5"], "small_date" => $value["rated2"]["hits"]["hits"][0]["_source"]["date5"]]);
            } else {
                array_push($data["data"], [$value["key"] => $value["doc_count"], "num" => $value["doc_count"]]);
            }
        }
        if ($vfield == "fingerprint" || $vfield == "subject") {
            $data['data'] = $this->sortData($data["data"], 'last_detected');
        }
        if ($vfield == "tracker") {
            $aggs_arr = [
                "tag" => [
                    "terms" => [
                        "field" => "tags_list",
                        'execution_hint' => "map",
                        "size" => $max,
                        'collect_mode' => "breadth_first"
                    ]],
                "tag_total" => [
                    "cardinality" => [
                        "field" => "tags_list"
                    ]
                ]
            ];
            $curl_param["aggs"] = $aggs_arr;
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $rs = json_decode($rs, true);
            $total = $rs["aggregations"]["tag_total"]["value"];
            $data["total"] = $total + $data["total"];
            foreach ($rs["aggregations"]["tag"]["buckets"] as $key => $value) {
                array_push($data["data"], [$value["key"] => $value["doc_count"], "num" => $value["doc_count"]]);
            }
            $data['data'] = $this->sortData($data["data"], 'num');
        }
        if (in_array($vfield, ["advertiser", "publisher", "tracker"])) {
            $data_tmp = [];
            foreach ($data['data'] as $key => $value) {
                $curl_param_domain = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => array_keys($value)[0]]]
                            ]]
                        ],
                    "size" => 1
                ];
                $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
                $data_rs_1 = DBUtil::get_parse_es_data($rs);
                if (!empty($data_rs_1["data"])) {
                    $data_rs = ["cname" => $data_rs_1["data"][0]->cname, "logo" => $data_rs_1["data"][0]->logo, "host" => array_keys($value)[0], "ename" => $data_rs_1["data"][0]->ename, "num" => $value[array_keys($value)[0]]];
                    array_push($data_tmp, $data_rs);
                }
            }
            $data["data"] = $data_tmp;
        }
        $data['status'] = empty($data["data"]) ? 203 : 200;
        return HttpUtil::retrun_json($data, 200);
    }


    public function getBrandDetail(Request $request)
    {
        $brand = empty($request->brand) ? "" : $request->brand;
        $brand_role = empty($request->brand_role) ? "" : $request->brand_role;
        if ($brand_role == "advertiser") {
            $curl_param = [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "brand" => $brand
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        } elseif ($brand_role == "publisher") {
            $curl_param = [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "brand_publisher_cname" => $brand
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs = DBUtil::get_es_data($rs);
        $host_no = [];
        foreach ($rs["data"] as $key => $value) {
            array_push($host_no,$value->host_no);
        }
        $host_str = implode(",",$host_no);
        $request->offsetSet("host", $host_str);
        $request->offsetSet("role", $brand_role);
        $domain_arr = $this->getDomainDetail($request)->original;
        return HttpUtil::retrun_json($domain_arr, 200);
    }

    public function  getBrandRelatedInfor(Request $request)
    {
       $brand = empty($request->brand) ? "" : $request->brand;
       $brand_role = empty($request->brand_role) ? "" : $request->brand_role;
       $data = ["data"=>[],"status"=>200];
        if ($brand_role == "advertiser") {
            $curl_param = [
                "size" =>1,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "brand" => $brand
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        } elseif ($brand_role == "publisher") {
            $curl_param = [
                "size" =>1,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "brand_publisher_cname" => $brand
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs = DBUtil::get_es_data($rs);
        if(empty($rs["data"]))return HttpUtil::retrun_json($data, 200);
        if($brand_role == "advertiser"){
        $company_no = $rs["data"][0]->company_no;
        }elseif($brand_role == "publisher"){
        $company_no = $rs["data"][0]->brand_publisher_company;
        }
        if ($brand_role == "advertiser") {
            $curl_param = [
                "size" =>1000,
                "query" => [
                    "bool" => [
                        "must" => [
                              ['match_phrase'=>[
                                    'company'=>[
                                            'query'=>$company_no
                                            ]
                            ]],
                            ["term"=>["status"=>1]]
                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search('brand_infor_v4/brand_domain', $curl_param);
            $rs = DBUtil::get_es_data($rs);  
            foreach($rs["data"] as $key => $value){
                array_push($data["data"],["brand"=>$value->brand,"company"=>$value->company,"category"=>$value->industry,"logo"=>""]);
            } 
        } elseif ($brand_role == "publisher") {
             $curl_param = [
                "size" =>1000,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "company" => $company_no
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search('brand_publisher', $curl_param);
            $rs = DBUtil::get_es_data($rs);
             foreach($rs["data"] as $key => $value){
                array_push($data["data"],["brand"=>$value->brand,"company"=>$value->company,"category"=>$value->category,"logo"=>$value->logo]);
            } 
        }
       return HttpUtil::retrun_json($data, 200);

    }




  

    private function getClassify($domain, $role)
    {
        $array = ["01", "02","03","04","05","05","06","07", "08","98","99"];
        switch ($role) {
            case "publisher":
                $vfield = "publisher_na";
                break;
            case 'advertiser':
                $vfield = 'advertiser_na';
                break;
        }
        $data = [];
        foreach ($array as $key => $value) {
            $curl_param = [
                "from" => 0,
                "size" => 0,
                "query" => [
                    "bool" => [
                        "must" => [
                            ["nested" => [
                                "path" => "risk_cats_v1",
                                "query" => [
                                    "bool" => [
                                        "must" => [
                                            [
                                                "term" => [
                                                    "risk_cats_v1.cat" => $value
                                                ]
                                            ]
                                        ]
                                    ]
                                ]

                            ]
                            ],
                            [
                                "terms" => [
                                    $vfield => $domain
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            if($value == "98"){
                array_push($curl_param["query"]["bool"]["must"][0]["nested"]["query"]["bool"]["must"],[
                    "range" => [
                        "risk_cats_v1.score" => [
                            "gt"=>"6"
                        ]
                    ]
                ]);
            }
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $rs = json_decode($rs);
            if ($rs->hits->total != 0) {
                $data[] = ["category" => $value, "amount" => $rs->hits->total];
            }
        }
        return $data;
    }


    private function getAreaClassify($domain, $role, $size = 100)
    {
        switch ($role) {
            case "publisher":
                $vfield = "publisher_na";
                break;
            case 'advertiser':
                $vfield = 'advertiser_na';
                break;
        }
        $data = [];
        $curl_param = [
            "from" => 0,
            "size" => 0,
            "query" => [
                "bool" => [
                    "must" => [
                        [
                            "terms" => [
                                $vfield => $domain
                            ]
                        ]
                    ]
                ]
            ],
            "aggs" => [
                "area" => [
                    "terms" => [
                        "field" => "location_v1.id",
                        "size" => $size
                    ],
                    "aggs" => [
                        "rated" => [
                            "top_hits" => [
                                "_source" => [
                                    "includes" => "location_v1"
                                ],
                                "size" => 1
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = json_decode($rs, true);
        $data_temp = $rs["aggregations"]["area"]["buckets"];
        foreach ($data_temp as $key => $value) {
            $data_temp_1["country"] = $value["rated"]["hits"]["hits"][0]["_source"]["location_v1"]["country"];
            $data_temp_1["province"] = $value["rated"]["hits"]["hits"][0]["_source"]["location_v1"]["province"];
            $data_temp_1["city"] = $value["rated"]["hits"]["hits"][0]["_source"]["location_v1"]["city"];
            $data_temp_1["isp"] = $value["rated"]["hits"]["hits"][0]["_source"]["location_v1"]["isp"];
            $data_temp_1["id"] = $value["rated"]["hits"]["hits"][0]["_source"]["location_v1"]["id"];
            $data_temp_1["amount"] = $value["doc_count"];
            array_push($data, $data_temp_1);
        }
        return $data;
    }


    public function getDomainByAdsSearch(Request $request)
    {
        $param_domain = empty($request->querystring) ? '' : $request->querystring;
        $subject = empty($request->subject_title) ? '' : $request->subject_title;
        $role = empty($request->role) ? "2" : $request->role;
        $max = empty($request->max) ? "100" : $request->max;
        $width = empty($request->width) ? 0 : $request->width;
        $min_width = empty($request->min_width) ? 0 : $request->min_width;
        $min_height = empty($request->min_height) ? 0 : $request->min_height;
        $height = empty($request->height) ? 0 : $request->height;
        $order_type = empty($request->order_type) ? "0" : "1"; //0代表时间,1代表相关性
        $advertiser = empty($request->advertiser) ? "" : $request->advertiser;
        $publisher = empty($request->publisher) ? "" : $request->publisher;
        $tracker = empty($request->tracker) ? "" : $request->tracker;
        $max_width = max($width, $min_width);
        $min_width = min($width, $min_width);
        $accurate = empty($request->accurate) ? "" : $request->accurate;//模糊查询,准确查询
        $max_height = max($height, $min_height);
        $min_height = min($height, $min_height);

        $is_width = ($max_width === $min_width ? 1 : 0);
        $is_height = ($max_height === $min_height ? 1 : 0);

        $iscampaign = empty($request->iscampaign) ? '' : $request->iscampaign;

        if (($this->size && $this->size > 10000) || !is_numeric($this->size)) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $sort = empty($request->sort) ? 'desc' : $request->sort;

        if (!in_array($sort, ['desc', 'asc', 'DESC', 'ASC'])) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $param = empty($request->wd) ? '' : $request->wd;
        $oldparam = urldecode($param);
        $oldparam1 = urldecode($param);
        $param = urldecode($param);
        // if (empty($param)) return HttpUtil::retrun_json(['data' => [], 'status' => 207], 200);
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        if (!empty($request->platform) && strpos($request->platform, ",") == true) {
            $platform1 = explode(",", $request->platform);
            $attribute04 = [];
            $platform = [];
            foreach ($platform1 as $key => $value) {
                if ($value == 1) {
                    array_push($platform, "1");
                    continue;
                }
                if ($value == 2) {
                    array_push($platform, "2");
                    continue;
                }
                if ($value == 3) {
                    array_push($attribute04, "android");
                    continue;
                }
                if ($value == 4) {
                    array_push($attribute04, "ios");
                    continue;
                }
                if ($value == 5) {
                    array_push($platform, "4");
                    continue;
                    // $value = 4;
                }
            }


        } else if (!empty($request->platform) && strpos($request->platform, ",") == false) {
            $attribute04 = [];
            $platform = [];
            if ($request->platform == 3) {
                array_push($attribute04, "android");
            } elseif ($request->platform == 4) {
                array_push($attribute04, "ios");
            } elseif ($request->platform == 5) {
                array_push($platform, "4");
            } else {
                array_push($platform, $request->platform);
            }
        } else {
            $attribute04 = [];
            $platform = [];
        }
        $startTime = empty($request->start_time) ? '' : $request->start_time;
        $endTime = empty($request->end_time) ? '' : $request->end_time;
        $shape = empty($request->shape) ? [] : explode(",", $request->shape);  // 0  所有尺寸  1  ， 2 ，3 ...
        //        $type = empty($request->type) ? [] : explode(",", $request->type); // 1 图片 2 视频 3 flush 4 html5 5 原生
        if (!empty($request->type) && strpos($request->type, ",") == true) {
            $type = explode(",", $request->type);
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];
        } else if (!empty($request->type) && strpos($request->type, ",") == false) {
            $type[0] = $request->type;
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];

        } else {
            $type = [];
            $material = [];

        }
        // 相关性 查询
        $sortmode = empty($request->sortmode) ? '' : $request->sortmode;

        if ($startTime) {
            if ($endTime < $startTime) {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data, 200);
            }
        }

        $is_time = ($startTime === $endTime ? 1 : 0);

        // 验证参数格式
        $valiedParam = [
            'shape' => $shape
        ];
        if ($is_width) $valiedParam['width'] = $max_width;
        if ($is_height) $valiedParam['height'] = $max_height;
        $valiedParam['platform'] = $platform;
        $valiedParam['attribute04'] = $attribute04;
        $valiedParam['material'] = $material;
        $valiedParam['type'] = $type;

        if (!empty($advertiser) && $advertiser != "null") {
            $vfield = 'advertiser_na';
            $advertiser_t = $advertiser;
            if (strpos($advertiser, ",") == true) {
                $advertiser_t = explode(",", $advertiser);
            }
            $valiedParam[$vfield] = $advertiser_t;
        }
        if (!empty($publisher)) {
            $vfield = 'publisher_na';
            $publisher_t = $publisher;
            if (strpos($publisher, ",") == true) {
                $publisher_t = explode(",", $publisher);
            }
            $valiedParam[$vfield] = $publisher_t;
        }
        if (!empty($tracker) && $tracker != 'null') {
            $vfield = 'tracker_list';
            $tracker_t = $tracker;
            if (strpos($tracker, ",") == true) {
                $tracker_t = explode(",", $tracker);
            }
            $valiedParam[$vfield] = $tracker_t;
        }


        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $param) : '';

        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $param : $c_param;
        // 默认
        $curl_param = $this->getCurlParam($oldparam, 0, 0, 0);
        //dd($curl_param);
        //dd($valiedParam);
        $curl_param = Tools::valiedParamt($valiedParam, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime, $endTime, $is_time, $max_width, $min_width, $is_width, $max_height, $min_height, $is_height);
        $curl_param = Tools::rangeParamt($range, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        if ($order_type == 1) {
            unset($curl_param["sort"]);
            $curl_param["sort"][0] = [
                "_score" => [
                    "order" => "desc"
                ]
            ];

        }

        if (empty($oldparam1)) {
            array_splice($curl_param["query"]["function_score"]["filter"]["bool"]["must"], 2, 1);
        }
        if (!empty($tracker) && $tracker == 'null') {
            $item = [
                'exists' => [
                    "field" => "tracker_list"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if (!empty($advertiser) && $advertiser == 'null') {
            $item = [
                'term' => [
                    "advertiser_na" => "*"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if ($en_ch != 1 && !empty($accurate)) {
            $item = [
                'filter' => [
                    'match_phrase' => [
                        'title' => [
                            'query' => $oldparam
                        ]
                    ]
                ],
                "weight" => '4'
            ];
            array_push($curl_param['query']['function_score']['functions'], $item);
            $curl_param["query"]["function_score"]["max_boost"] = 14;
        }
        //echo config('param.ADDATA_SEARCH6');
        $curl_param["aggs"] = [
            'advertiser_total' => [
                'cardinality' => [
                    'field' => "advertiser_na"
                ]
            ],
            'publisher_total' => [
                'cardinality' => [
                    'field' => "publisher_na"
                ]
            ],
            'tracker_total' => [
                'cardinality' => [
                    'field' => "tracker_list"
                ]
            ],
            'tag_total' => [
                'cardinality' => [
                    'field' => "tags_list"
                ]
            ]
        ];
        $curl_param_temp = $curl_param;
        switch ($role) {
            case "1" :
                $curl_param["aggs"] = [
                    "publisher" => [
                        'terms' => [
                            'field' => "publisher_na",
                            'size' => $max
                        ],
                    ],
                    'publisher_total' => [
                        'cardinality' => [
                            'field' => "publisher_na"
                        ]
                    ]];
                break;
            case "2" :
                $curl_param["aggs"] = [
                    "advertiser" => [
                        'terms' => [
                            'field' => "advertiser_na",
                            'size' => $max
                        ],
                    ],
                    'advertiser_total' => [
                        'cardinality' => [
                            'field' => "advertiser_na"
                        ]
                    ]];
                break;
            case "3" :
                $curl_param["aggs"] = [
                    "tracker" => [
                        'terms' => [
                            'field' => "tracker_list",
                            'size' => $max
                        ],
                    ],
                    'tracker_total' => [
                        'cardinality' => [
                            'field' => "tracker_list"
                        ]
                    ],
                    "tag" => [
                        'terms' => [
                            'field' => "tags_list",
                            'size' => $max
                        ],
                    ],
                    'tag_total' => [
                        'cardinality' => [
                            'field' => "tags_list"
                        ]
                    ]
                ];
                break;
        }

        if (!empty($subject)) {
            $subject_term = [
                "match" => ["title" => $subject]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $subject_term);
        }

        if (!empty($param_domain)) {
            switch ($role) {
                case 1 :
                    $tfield = "publisher_na";
                    break;
                case 2 :
                    $tfield = "advertiser_na";
                    break;
            }
            $domain_arr = $this->getDomainByWd($param_domain, $role);
            if ($role == 1 || $role == 2) {
                $con_domain = [
                    "terms" => [
                        $tfield => $domain_arr
                    ]
                ];
                array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $con_domain);
            } else {
                $con_domain = [
                    "terms" => [
                        "tracker_list" => $domain_arr
                    ]
                ];
                array_push($curl_param["query"]["function_score"]["filter"]["bool"]["should"], $con_domain);
                $con_domain1 = [
                    "terms" => [
                        "tags_list" => $domain_arr
                    ]
                ];
                array_push($curl_param["query"]["function_score"]["filter"]["bool"]["should"], $con_domain1);
            }
        }
        if (!empty($subject)) {
            $subject_term = [
                "match" => ["title" => ["query" => $subject, "operator" => "and", "minimum_should_match" => "100%"]]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $subject_term);
        }
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = json_decode($rs, true);
        $data = [];
        if ($role == 1) {
            foreach ($rs["aggregations"]["publisher"]["buckets"] as $key => $value) {
                $data_tmp = [];
                $curl_param_domain = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => $value["key"]]]
                            ]]
                        ],
                    "size" => 1
                ];
                $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
                $data_rs_1 = DBUtil::get_parse_es_data($rs2);
                if (count($data_rs_1["data"]) != 0) {
                    $data_tmp["cname"] = $data_rs_1["data"][0]->cname;
                    $data_tmp["host"] = $data_rs_1["data"][0]->host_no;
                    $data_tmp["logo"] = $data_rs_1["data"][0]->logo;
                    $data_tmp["num"] = $value["doc_count"];
                    array_push($data, $data_tmp);
                }
            }
        } elseif ($role == 2) {
            foreach ($rs["aggregations"]["advertiser"]["buckets"] as $key => $value) {
                $data_tmp = [];
                $curl_param_domain = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => $value["key"]]]
                            ]]
                        ],
                    "size" => 1
                ];
                $rs1 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
                $data_rs_1 = DBUtil::get_parse_es_data($rs1);
                if (count($data_rs_1["data"]) != 0) {
                    $data_tmp["cname"] = $data_rs_1["data"][0]->cname;
                    $data_tmp["host"] = $data_rs_1["data"][0]->host_no;
                    $data_tmp["logo"] = $data_rs_1["data"][0]->logo;
                    $data_tmp["num"] = $value["doc_count"];
                    array_push($data, $data_tmp);
                }
            }
        } elseif ($role == 3) {
            foreach ($rs["aggregations"]["tracker"]["buckets"] as $key => $value) {
                $data_tmp = [];
                $curl_param_domain = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => $value["key"]]]
                            ]]
                        ],
                    "size" => 1
                ];
                $rs3 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
                $data_rs_1 = DBUtil::get_parse_es_data($rs3);
                if (count($data_rs_1["data"]) != 0) {
                    $data_tmp["cname"] = $data_rs_1["data"][0]->cname;
                    $data_tmp["host"] = $data_rs_1["data"][0]->host_no;
                    $data_tmp["logo"] = $data_rs_1["data"][0]->logo;
                    $data_tmp["num"] = $value["doc_count"];
                    array_push($data, $data_tmp);
                }
            }
            foreach ($rs["aggregations"]["tag"]["buckets"] as $key => $value) {
                $data_tmp = [];
                $curl_param_domain = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => $value["key"]]]
                            ]]
                        ],
                    "size" => 1
                ];
                $rs4 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
                $data_rs_1 = DBUtil::get_parse_es_data($rs4);
                if (!empty($data_rs_1)) {
                    $data_tmp["cname"] = isset($data_rs_1["data"][0]->cname) ? $data_rs_1["data"][0]->cname : "";
                    $data_tmp["host"] = isset($data_rs_1["data"][0]->host_no) ? $data_rs_1["data"][0]->host_no : "";
                    $data_tmp["logo"] = isset($data_rs_1["data"][0]->logo) ? $data_rs_1["data"][0]->logo : "";
                    $data_tmp["num"] = isset($value["doc_count"]) ? $value["doc_count"] : "";
                    array_push($data, $data_tmp);
                }
            }
            $data = $this->sortData($data, 'num');
        }
        $rs1 = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param_temp);
        $rs1 = json_decode($rs1, true);
        $tdata["sum"] = ["advertiser" => $rs1["aggregations"]["advertiser_total"]["value"], "publisher" => $rs1["aggregations"]["publisher_total"]["value"], "tracker" => $rs1["aggregations"]["tracker_total"]["value"] + $rs1["aggregations"]["tag_total"]["value"]];
        $tdata["data"] = $data;
        $tdata["total"] = $rs1["hits"]["total"];
        $tdata["status"] = 200;
        return HttpUtil::retrun_json($tdata, 200);

    }

    private function getDomainByWd($param,$role)
    {
        switch ($role) {
            case '1':
                $field = 'ispublisher';
                $vfield = 'publisher_ads';
                break;
            case '2':
                $field = 'isbrand';
                $vfield = 'brand_ads';
                break;
            case '3':
                $field = 'istracker';
                $vfield = 'tracker_ads';
                break;
        }
        if($role == 1 || $role == 2){
            $curl_param = [
                "size" => 1000,
                "_source"=>["id","host","cname"],
                "filter" => [
                    "bool" => [
                        "should" => [
                            [
                                "wildcard" => [
                                    "host_no" => "*".$param."*"
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "cname_no" => "*".$param."*"
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "ename_no" => "*".$param."*"
                                ]
                            ]

                        ],
                        "must" => [
                            "term" => [
                                $field=> "1"
                            ]
                        ]
                    ]
                ],
                "sort" => [
                    [
                        $vfield => "desc"
                    ]
                ]
            ];
        }elseif($role == 3){
            $curl_param = [
                "size" => 200,
                "_source" => ["id", "host", "cname"],
                "filter" => [
                    "bool" => [
                        "must" => [
                            "bool" => [
                                "should" => [
                                    [
                                        "wildcard" => [
                                            "host_no" => "*" . $param . "*"
                                        ]
                                    ],
                                    [
                                        "wildcard" => [
                                            "cname_no" => "*" . $param . "*"
                                        ]
                                    ],
                                    [
                                        "wildcard" => [
                                            "ename_no" => "*" . $param . "*"
                                        ]
                                    ]
                                ]]
                        ],
                        "should" => [
                            ["term" => [
                                $field => "1"
                            ]],
                            ["bool" => [
                                "must" => [
                                    ["term" => [
                                        "isbrand" => "0"
                                    ]],
                                    ["term" => [
                                        "ispublisher" => "0"
                                    ]],
                                    ["term" => [
                                        "istracker" => "0"
                                    ]],

                                ]
                            ]
                            ]
                        ]


                    ]

                ],
                "sort" => [
                    [
                        "$vfield" => "desc"
                    ]
                ]
            ];
        }
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        $domain = [];
        foreach ($data["data"] as $key => $value){
            array_push($domain,$value->host);
        }
        return $domain;
    }

    public function getAdsByDomain(Request $request)
    {
        if (empty($request->domains)) return HttpUtil::retrun_json(["status" => 211, "message" => "参数不能为空"], 200);
        $domains = [];
        if (strpos($request->domains, ",")) {
            $domains = explode(",", $request->domains);
        } else {
            array_push($domains, $request->domains);
        }
        $curl_param = [
            'filter' => [
                "bool" => [
                    "should" => [
                        ['terms' => [
                            'advertiser_na' => $domains
                        ]
                        ],
                        ['terms' => [
                            'publisher_na' => $domains
                        ]
                        ]
                    ],
                    "must" => []
                ]
            ],
            "from" => $this->from,
            "size" => $this->size,
            "sort" => [
                ["created_date" => "desc"]
            ]
        ];
        if (!empty($request->start_time) && !empty($request->end_time)) {
            $conditon_time = [
                'range' => [
                    'created_date' => [
                        'gte' => $request->start_time,
                        'lte' => $request->end_time
                    ]
                ]
            ];
            array_push($curl_param["filter"]["bool"]["must"], $conditon_time);
        }
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $result = DBUtil::get_parse_es_data($rs);
        $data['status'] = empty($result['data']) ? 203 : 200;
        $data["data"] = $result["data"];
        $data["total"] = $result["total"];
        return HttpUtil::retrun_json($data, 200);
    }


    public function getIdsSearch(Request $request)
    {
        $width = empty($request->width) ? 0 : $request->width;
        $min_width = empty($request->min_width) ? 0 : $request->min_width;
        $min_height = empty($request->min_height) ? 0 : $request->min_height;
        $height = empty($request->height) ? 0 : $request->height;
        $order_type = empty($request->order_type) ? "0" : "1"; //0代表时间,1代表相关性
        $advertiser = empty($request->advertiser) ? "" : $request->advertiser;
        $publisher = empty($request->publisher) ? "" : $request->publisher;
        $tracker = empty($request->tracker) ? "" : $request->tracker;
        $max_width = max($width, $min_width);
        $min_width = min($width, $min_width);
        $accurate = empty($request->accurate)? "0" : $request->accurate;//模糊查询,准确查询
        $max_height = max($height, $min_height);
        $min_height = min($height, $min_height);
        $subject = empty($request->subject_title) ? "" : $request->subject_title;

        $is_width = ($max_width === $min_width ? 1 : 0);
        $is_height = ($max_height === $min_height ? 1 : 0);

        $iscampaign = empty($request->iscampaign) ? '' : $request->iscampaign;

        if (($this->size && $this->size > 10000) || !is_numeric($this->size)) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $sort = empty($request->sort) ? 'desc' : $request->sort;

        if (!in_array($sort, ['desc', 'asc', 'DESC', 'ASC'])) {
            return HttpUtil::retrun_json(['data' => [], 'status' => 208], 200);
        }
        $param = empty($request->wd) ? '' : $request->wd;
        $oldparam = urldecode($param);
        $oldparam1 = urldecode($param);
        $param = urldecode($param);
        // if (empty($param)) return HttpUtil::retrun_json(['data' => [], 'status' => 207], 200);
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        if (!empty($request->platform) && strpos($request->platform, ",") == true) {
            $platform1 = explode(",", $request->platform);
            $attribute04 = [];
            $platform = [];
            foreach ($platform1 as $key => $value) {
                if ($value == 1) {
                    array_push($platform, "1");
                    continue;
                }
                if ($value == 2) {
                    array_push($platform, "2");
                    continue;
                }
                if ($value == 3) {
                    array_push($attribute04, "android");
                    continue;
                }
                if ($value == 4) {
                    array_push($attribute04, "ios");
                    continue;
                }
                if ($value == 5) {
                    array_push($platform, "4");
                    continue;
                    // $value = 4;
                }
            }


        } else if (!empty($request->platform) && strpos($request->platform, ",") == false) {
            $attribute04 = [];
            $platform = [];
            if ($request->platform == 3) {
                array_push($attribute04, "android");
            }
            elseif ($request->platform == 4) {
                array_push($attribute04, "ios");
            }
            elseif ($request->platform == 5) {
                array_push($platform, "4");
            } else {
                array_push($platform, $request->platform);
            }
        } else {
            $attribute04 = [];
            $platform = [];
        }
        $startTime = empty($request->start_time) ? '' : $request->start_time;
        $endTime = empty($request->end_time) ? '' : $request->end_time;
        $shape = empty($request->shape) ? [] : explode(",", $request->shape);  // 0  所有尺寸  1  ， 2 ，3 ...
        //        $type = empty($request->type) ? [] : explode(",", $request->type); // 1 图片 2 视频 3 flush 4 html5 5 原生
        if (!empty($request->type) && strpos($request->type, ",") == true) {
            $type = explode(",", $request->type);
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];
        } else if (!empty($request->type) && strpos($request->type, ",") == false) {
            $type[0] = $request->type;
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];

        } else {
            $type = [];
            $material = [];

        }
        // 相关性 查询
        $sortmode = empty($request->sortmode) ? '' : $request->sortmode;

        if ($startTime) {
            if ($endTime < $startTime) {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data, 200);
            }
        }

        $is_time = ($startTime === $endTime ? 1 : 0);

        // 验证参数格式
        $valiedParam = [
            'shape' => $shape
        ];
        if ($is_width) $valiedParam['width'] = $max_width;
        if ($is_height) $valiedParam['height'] = $max_height;
        $valiedParam['platform'] = $platform;
        $valiedParam['attribute04'] = $attribute04;
        $valiedParam['material'] = $material;
        $valiedParam['type'] = $type;

        if (!empty($advertiser) && $advertiser != "null") {
            $vfield = 'advertiser_na';
            $advertiser_t = $advertiser;
            if (strpos($advertiser, ",") == true){
                $advertiser_t = explode(",",$advertiser);
            }
            $valiedParam[$vfield] = $advertiser_t;
        }
        if (!empty($publisher)) {
            $vfield = 'publisher_na';
            $publisher_t = $publisher;
            if (strpos($publisher, ",") == true){
                $publisher_t = explode(",",$publisher);
            }
            $valiedParam[$vfield] = $publisher_t;
        }
        if (!empty($tracker) && $tracker != 'null') {
            $vfield = 'tracker_list';
            $tracker_t = $tracker;
            if (strpos($tracker, ",") == true){
                $tracker_t = explode(",",$tracker);
            }
            $valiedParam[$vfield] = $tracker_t;
        }


        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $param) : '';

        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $param : $c_param;
        // 默认
        if($accurate == 2){
            $curl_param = $this->getCurlParam2($oldparam, $this->size, 0, $this->from);
        }else{
            $curl_param = $this->getCurlParam($oldparam, $this->size, 0, $this->from);
        }
        //dd($curl_param);
        //dd($valiedParam);
        $curl_param = Tools::valiedParamt($valiedParam, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime, $endTime, $is_time, $max_width, $min_width, $is_width, $max_height, $min_height, $is_height);
        $curl_param = Tools::rangeParamt($range, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        if ($order_type == 1) {
            unset($curl_param["sort"]);
            $curl_param["sort"][0] = [
                "_score" => [
                    "order" => "desc"
                ]
            ];

        }

        if(empty($oldparam1)){
            array_splice($curl_param["query"]["function_score"]["filter"]["bool"]["must"], 2,1);
        }
        if (!empty($tracker) && $tracker == 'null') {
            $item = [
                'exists' => [
                    "field" => "tracker_list"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if (!empty($advertiser) && $advertiser == 'null') {
            $item = [
                'term' => [
                    "advertiser_na" => "*"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if($en_ch != 1 && $accurate == 1){
            $item = [
                'filter'=>[
                    'match_phrase'=>[
                        'title'=>[
                            'query'=>$oldparam
                        ]
                    ]
                ],
                "weight"=>'4'
            ];
            array_push($curl_param['query']['function_score']['functions'], $item);
            $curl_param["query"]["function_score"]["max_boost"] = 14;
        }
        if (!empty($subject)) {
            $subject_term = [
                "match" => ["title" =>["query"=>$subject,"operator"=>"and","minimum_should_match"=>"100%"]]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"],$subject_term);
        }
        //dd($curl_param);
        //echo config('param.ADDATA_SEARCH6');
        //dd(json_encode($curl_param));
        if($accurate == 2){
            $score_temp = [
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name_title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],

            ];
            $curl_param["query"]["function_score"]["functions"]=$score_temp;
        }
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?scroll=2m';
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data_id($rs);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }


        foreach ($data['data'] as $key => $value) {
            $return_id[] = $value;
        }

        $scrollId = $data['scroll_id'];

        $j = 1;

        if ($data['total'] > $this->size) {
            if ($data['total'] % $this->size == 0) {
                $j = $data['total'] / $this->size;
            } else {
                $j = intval($data['total'] / $this->size) + 1;
            }

        }

        for ($i=0; $i < $j; $i++) {
            $url = config('param.ES_URL').'/'.config('param.ES_SEARCH').'/scroll?scroll=2m';

            $curl_param_scroll = [
                'scroll_id'=>$scrollId
            ];
            $rs = HttpUtil::send_curl_methods($url,$curl_param_scroll,"POST");

            $data = DBUtil::get_parse_es_data_id($rs);

            if(!empty($data['scroll_id']))
            {
                $scrollId = $data['scroll_id'];
            }
            if(empty($data['data']))break;
            foreach ($data['data'] as $key => $value) {
                $return_id[] = $value;
            }

        }
        $data = [];
        $data['status'] = 200;
        $data['total'] = count($return_id);
        foreach ($return_id as $key => $value) {
            $data['data'][] = $value->id;
        }
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 收藏广告和档案
     */
    public function storeAdd(Request $request)
    {
        if (empty($request->host) && empty($request->id)) return HttpUtil::retrun_json(["status" => 207], 200);
        if (!empty($request->host)) {
            $curl_param = [
                "query" =>
                    ["bool" =>
                        ["must" => [
                            ["term" => ["host_no" => $request->host]]
                        ]]
                    ],
                "size" => 1,
            ];
            $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
            $data = DBUtil::get_parse_es_data($rs2);
            $store_id = $data["data"][0]->id;
            $type = 1;
            $user_id = $this->user["id"];
        } else {
            $store_id = $request->id;
            $type = 0;
            $user_id = $this->user["id"];

        }
        if (DB::table('user_store')->where(["user_id" => $user_id, "type" => $type, "store_id" => $store_id])->first()) return HttpUtil::retrun_json(["status" => "211", "message" => "该创意或档案已经收藏过了"], 200);
        if ($type == 1) {
            $curl_param = [
                "query" =>
                    ["bool" =>
                        ["must" => [
                            ["term" => ["id" => $store_id]]
                        ]]
                    ],
                "size" => 1,
            ];
            $rs3 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
            $data_temp = DBUtil::get_parse_es_data($rs3);
            $data1 = $this->getHostMessageNew($data_temp["data"][0]->host);
            $data2 = $data1["ads"];
            $data3 = $data_temp["data"][0]->role == 0 ? '3' : $data_temp["data"][0]->role;
            $role = $data3 == 1 ? "媒体" : ($data3 == 2 ? "广告主" : "广告科技");
            $cname = $data_temp["data"][0]->cname;
            $infor = ["role" => $role, "cname" => $cname, "amount" => $data2, "host" => $data_temp["data"][0]->host];
            DB::table('subscribe_log')->insert(["user_id" => $user_id, "store_id" => $store_id, "type" => 1, "infor" => serialize($infor), "created_time" => time()]);

        }
        if (DB::table('user_store')->insert(["user_id" => $user_id, "store_id" => $store_id, "type" => $type,"created_time"=>time()])) {
            $message = "收藏成功";
            $status = 200;
        } else {
            $message = "收藏失败";
            $status = 211;

        }
        return HttpUtil::retrun_json(["status" => $status, "message" => $message], 200);
    }


    /**
     * 收藏广告和档案查看
     */
    public function storeCheck(Request $request)
    {
        $user_id = $this->user["id"];
        $type = empty($request->type) ? "0" : $request->type;
        $data = [];
        $store_id = DB::table('user_store')->where(["user_id" => $user_id, "type" => $type])->select('store_id')->orderBy("id","DESC")->get();
        if (!empty($store_id)) {
            if($type == 0){
                foreach ($store_id as $key => $value) {
                    $curl_param = [
                        "query" => [
                            "bool" => [
                                "must" => [
                                    "term" => [
                                        "id" => $value->store_id
                                    ]
                                ]
                            ]
                        ]
                    ];
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $data1 = DBUtil::get_parse_es_data($rs);
                    array_push($data, ["id"=>$value->store_id,"title"=>$data1["data"][0]->title,"type"=>$data1["data"][0]->type,"am_url"=>$data1["data"][0]->am_url]);
                }

            }
            if ($type == 1) {
                $data = [];
                foreach ($store_id as $key => $value) {
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
                    $data_temp2["host"] = $data_temp["data"][0]->host;
                    $data_temp2["cname"] = $data_temp["data"][0]->cname;
                    array_push($data, $data_temp2);
                }


            }

        }
        return HttpUtil::retrun_json(["status" => 200, "data" => $data], 200);
    }


    /**
     * 取消收藏
     */
    public function storeDel(Request $request)
    {
        if (empty($request->host) && empty($request->id)) return HttpUtil::retrun_json(["status" => 207], 200);
        if (!empty($request->host)) {
            $curl_param = [
                "query" =>
                    ["bool" =>
                        ["must" => [
                            ["term" => ["host_no" => $request->host]]
                        ]]
                    ],
                "size" => 1,
            ];
            $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
            $data = DBUtil::get_parse_es_data($rs2);
            $store_id = $data["data"][0]->id;
            $type = 1;
            $user_id = $this->user["id"];
        } else {
            $store_id = $request->id;
            $type = 0;
            $user_id = $this->user["id"];

        }
        if(!DB::table('user_store')->where(["user_id" => $user_id, "type" => $type, "store_id" => $store_id])) return HttpUtil::retrun_json(["status" => "211", "message" => "该创意或档案已经取消过了"], 200);
        if($type == 1){
            DB::table('subscribe_log')->where(["user_id" => $user_id, "store_id" => $store_id, "type" => $type])->delete();
        }
        if (DB::table('user_store')->where(["user_id" => $user_id, "store_id" => $store_id, "type" => $type])->delete()) {
            $message = "取消收藏成功";
            $status = 200;
        } else {
            $message = "取消收藏失败";
            $status = 211;

        }
        return HttpUtil::retrun_json(["status" => $status, "message" => $message], 200);
    }


    /**
     *落地页查询,下载创意加1
     */
    public function downloadAdd(Request $request)
    {
        $id = $this->user["id"];
        if( DB::connection('mysql_oc2018')->table('users')->where('id','=',$id)->increment('downloads')){
            $message = "落地页下载自增成功";
            $status =200;
        }else{
            $message = "落地页下载自增失败";
            $status =211;

        }
        return HttpUtil::retrun_json(["status"=>$status,"message"=>$message],200);
    }


    /**
     *落地页查询,查看创意加1
     */
    public function landAdd(Request $request){
        $id = $this->user["id"];
        if( DB::connection('mysql_oc2018')->table('users')->where('id','=',$id)->increment('landing')){
            $message = "落地页查看自增成功";
            $status =200;
        }else{
            $message = "落地页查看自增失败";
            $status =211;

        }
        return HttpUtil::retrun_json(["status"=>$status,"message"=>$message],200);
    }


    /**
     * 搜索次数每日清零(定时器)
     * account 邮箱
     * @return boolean [description]
     */
    public function clearTimes(Request $request){
        DB::connection('mysql_oc2018')->table('users')->update(["search_times"=>0,"downloads"=>0,"landing"=>0]);
        return HttpUtil::retrun_json(["message"=>"成功"],200);
    }

    /**
     *会员下单操作
     */
    public function orderAdd(Request $request)
    {
        if (empty($request->id) || count($this->user) == 0) return HttpUtil::retrun_json(['status' => 211, 'message' => "缺少参数"], 200);
        if (!empty($request->c)) {
            $code_rs = DB::connection('mysql_oc2018')->table('pay_code_copy')->where("token", $request->c)->first();
        }
        $rs = DB::connection('mysql_oc2018')->table('vip_detail')->where("id", $request->id)->first();
        if (!$rs) return HttpUtil::retrun_json(['status' => 211, 'message' => "vip"], 200);
        if ($rs->vip_level == 2 && empty($request->time)) return HttpUtil::retrun_json(['status' => 211, 'message' => "缺少参数"], 200);
        $order_sno = date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $time_lenth = $rs->vip_level == 2 ? $rs->vip_period * $request->time : $rs->vip_period;
        $vip_level = $rs->vip_level;
        $amount = $rs->vip_level == 2 ? $rs->price * $request->time : $rs->price;
        $created_time = time();
        $buyer_id = $this->user["id"];
        $code = "";
        $code1 = 100;
        $discount = 0;
        if (isset($code_rs) && $time_lenth == 12) {
            $amount = $amount - $code_rs->price;
            $code = $code_rs->id;
            $code1 = $rs->vip_level;
            $discount = $code_rs->price;
        }
        if (DB::connection('mysql_oc2018')->table('order_detail')->insert(["sno" => $order_sno, "vip_level" => $vip_level, "time_length" => $time_lenth, "amount" => $amount, "created_time" => $created_time, "buyer_id" => $buyer_id,"code_id"=>$code])) {
            return HttpUtil::retrun_json(['status' => 200, 'data' => $order_sno,"code"=>$code1,"discount"=>$discount], 200);
        }
        return HttpUtil::retrun_json(['status' => 211, 'message' => "生成订单失败","code"=>$code1], 200);
    }


    /**
     *会员产品展示
     */
     public function vipDetail(Request $request)
     {
     $db = DB::connection('mysql_oc2018');
     $rs = [];
     $data = $db->table('vip_detail')->select('id','vip_name','vip_level','price','vip_period')->get();
     foreach($data as $key => $value){
        $temp["id"] = $value->id;
        $temp["vip_name"] =$value->vip_name;
        $temp["vip_level"] = $value->vip_level;
        $temp["price"] = $value->price;
        $temp["vip_period"] = $value->vip_period;
        array_push($rs,$temp);
     }
     if(!empty($this->user) && $db->table("order_detail")->select("id")->where("status",1)->where("buyer_id",$this->user["id"])->first()){array_splice($rs,0,1);}
     return HttpUtil::retrun_json($rs, 200);
     }



    /**
     * 广告举报
     * @return boolean [description]
     */
    public function adsReport(Request $request)
    {
        $id = $request->id;
        $type = $request->type;
        if (empty($id) || empty($type)) return HttpUtil::retrun_json(["status" => 211, "message" => "缺少参数"], 200);

        $curl_param = [
            "query" => [
                "bool" => [
                    "must" => [
                        "term" => [
                            "id" => $id
                        ]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data1 = DBUtil::get_parse_es_data($rs);
        if (empty($data1["data"])) return HttpUtil::retrun_json(["status" => 211, "message" => "创意不存在"], 200);
        if (in_array($type, config('param.NAGATIVE_CATEGORY'))) {
            $key = array_search($type, config('param.NAGATIVE_CATEGORY'));
            $curl_param = [
                "doc" => [
                    "category03" => $key
                ]
            ];
            $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . $id . '/_update';
            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $rs = json_decode($rs, true);
            if ($rs["_shards"]["failed"] == 0) {
                DB::table('ads_audit')->insert(["ad_id"=>$id,"category"=>$key]);
                return HttpUtil::retrun_json(["status" => 200, "message" => "举报成功"], 200);
            }
        };

        return HttpUtil::retrun_json(["status" => 211, "message" => "举报失败"], 200);
    }


    /**
     * 违法广告审核
     * @return boolean [description]
     */
    public function adsAudit(Request $request)
    {
        if (empty($this->user["type"] != 1)) return HttpUtil::retrun_json(["status" => 211, "message" => "该账号不具备最高权限"], 200);
        $id = $request->id;
        if (empty($id)) return HttpUtil::retrun_json(["status" => 211, "message" => "缺少参数"], 200);
        $status = empty($request->status) ? "0" : $request->status; // 0=>举报通过 1=>举报不通过
        if ($status == 1) {
            $curl_param = [
                "doc" => [
                    "category03" => 100
                ]
            ];
            $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . $id . '/_update';
            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $rs = json_decode($rs, true);
            if ($rs["_shards"]["failed"] == 0) {
                $data["status"] = 211;
                $data["message"] = "举报不通过";
            }
        } else {
            $data["status"] = 200;
            $data["message"] = "举报通过";
        }
        DB::table('ads_audit')->where("ad_id", $id)->delete();
        return HttpUtil::retrun_json($data, 200);
    }

    /**
     * 违法广告查看
     * @return boolean [description]
     */
    public function adsCheck(Request $request)
    {
        if (empty($this->user["type"] != 1)) return HttpUtil::retrun_json(["status" => 211, "message" => "该账号不具备最高权限"], 200);
        $data = DB::table('ads_audit')->get();
        $ads_id = [];
        foreach ($data as $key => $value){
            array_push($ads_id,$value->ad_id);
        }
        $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["terms" => ["id" => $ads_id]]
                    ]]
                ],
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data= DBUtil::get_parse_es_data($rs);
        return HttpUtil::retrun_json(["status" => 200, "data" => $data], 200);
    }


    /**
     * 根据角色 获取对应的排名数据
     * role 1 媒体 2 广告主 3 跟踪者 4 创意 5 营销活动
     * time_type 1 前一天 2 前一周 3 前半月 4 前一月
     * @return [type] [description]
     */
    public function getRankingList(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $time_type = empty($request->time_type)?'2':$request->time_type;

        if(empty($role))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $current_date = date('Y-m-d',time());  // 当天 时间
        $weekLater = date('Y-m-d',strtotime("$current_date - 1 week"));   // 一周前

        $mouthLater = date('Y-m-d',strtotime("$current_date - 2 week"));   // 半个月前

        $one_mouth = date('Y-m-d',strtotime("$current_date - 1 month"));
        $two_mouth = date('Y-m-d',strtotime("$current_date - 2 month"));


        $before_day = date("Y-m-d",strtotime("-1 day"));//一天前

        $start_time = '';
        $end_time = '';

        $role_field = '';
        switch ($role) {
            case '1':
                # code...
                $role_field = 'publisher_na';
                break;
            case '2':
                # code...
                $role_field = 'advertiser_na';
                break;
            case '3':
                # code...
                $role_field = 'tags_list';//tracker_list
                break;
            case '4':
                # code...
                $role_field = 'ad_id';
                break;
            case '5':
                # code...
                $role_field = 'subject_md5';
                break;
            default:
                # code...
                break;
        }

        // $role_field = $role==1?'publisher_na':($role==2?'advertiser_na':'tracker_list');

        switch ($time_type) {
            case '1':
                # code...
                $start_time = strtotime($before_day.' 00:00:00').'000';
                $end_time = strtotime($before_day.' 23:59:59').'000';

                break;
            case '2':
                $start_time = strtotime($weekLater.' 00:00:00').'000';
                $end_time = strtotime($before_day.' 23:59:59').'000';
                break;
            case '3':
                $start_time = strtotime($one_mouth.' 00:00:00').'000';
                $end_time = strtotime($before_day.' 23:59:59').'000';
                # code...
                break;
            default:
                # code...
                break;
        }

        if(Cache::has($role.$role_field.$start_time.$end_time)){
            $current_data = Cache::get($role.$role_field.$start_time.$end_time);
        }else{
            $current_data = $this->getBeforeRankList($role,$role_field,$start_time,$end_time);
            Cache::add($role.$role_field.$start_time.$end_time,$current_data,60);
        }
//
//        $this->getBeforeRankList($role,$role_field,$start_time,$end_time);
//        dd($current_data);
//





        if(empty($current_data))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        // 计算 过去的时间
        //
        switch ($time_type) {
            case '1':
                $before_day = date("Y-m-d",strtotime("-2 day"));//一天前
                # code...
                $start_time = strtotime($before_day.' 00:00:00').'000';
                $end_time = strtotime($before_day.' 23:59:59').'000';

                break;
            case '2':
                $start_time = strtotime($mouthLater.' 00:00:00').'000';
                $end_time = strtotime($weekLater.' 23:59:59').'000';
                break;
            case '3':
                $start_time = strtotime($two_mouth.' 00:00:00').'000';
                $end_time = strtotime($one_mouth.' 23:59:59').'000';
                break;

        }

        if(Cache::has($role.$role_field.$start_time.$end_time)){
            $last_data = Cache::get($role.$role_field.$start_time.$end_time);
        }else{
            $last_data = $this->getBeforeRankList($role,$role_field,$start_time,$end_time);
            Cache::add($role.$role_field.$start_time.$end_time,$last_data,60);
        }


        if(empty($last_data))
        {
            $data['data'] = $current_data;
            $data['status'] =200;
            return HttpUtil::retrun_json($data,200);
        }

        // 计算相比上次增幅情况
        foreach ($current_data as $key => &$value) {

            $v_tmp = '';
            foreach ($last_data as $k => $v) {
                if($value['host'] == $v['host'])
                {
                    $v_tmp = $v;
                    break;
                }
            }

            if($v_tmp)
            {
                $v_rank = empty($v_tmp['rank'])?'':$v_tmp['rank'];

                $value['before_rank'] = $v_rank;
                $value['cur_rank'] = $value['rank'];
                $value['rank'] = $v_rank-$value['rank'];
            }else{
                $value['before_rank'] = '';
                $value['cur_rank'] = $value['rank'];
                $value['rank'] = $value['rank'];
            }
            $value['md5'] = md5($value['cname'].$value['host']);


        }

        // dd($current_data);

        // 过滤 域名 开头是 . 的host 比如 .uri6.com => uri6.com
        foreach ($current_data as $key => &$value) {
            if(strpos($value['host'], '.')==0)
            {

                $host_tmp_r = explode('.', $value['host']);


                if(count($host_tmp_r)>1)
                {
                    unset($host_tmp_r[0]);
                    $value['host'] = implode('.', $host_tmp_r);
                }
            }
        }
        $data['data'] = $current_data;
        $data['status'] =200;
        return HttpUtil::retrun_json($data,200);
        // dd(json_encode($curl_param));
    }

    /**
     * 排名榜
     * @param  [type] $role       [description]
     * @param  [type] $role_field [description]
     * @param  [type] $start_time [description]
     * @param  [type] $end_time   [description]
     * @return [type]             [description]
     */
    private function getBeforeRankList($role, $role_field, $start_time, $end_time)
    {
        //创意
        if ($role == 4) {
            $curl_param = [
                'size' => 0,
                'from' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'range' => [
                                    'created_date' => [
                                        'gte' => $start_time,
                                        'lte' => $end_time
                                    ]
                                ]
                            ]
                        ],
                        'must_not' => [
                            [
                                'term' => [
                                    'fingerprint' => '0000000000000000'
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs' => [
                    "fingerprint" => [
                        'terms' => [
                            'field' => "fingerprint",
                            'size' => 300
                        ],
                        'aggs' => [
                            'rated' => [
                                "top_hits" => [
                                    "sort" => [
                                        [
                                            "date5" => [
                                                "order" => "desc"
                                            ]
                                        ]
                                    ],
                                    "size" => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $rs_parse = json_decode($rs, true);
            if (count($rs_parse['aggregations']['fingerprint']['buckets']) == 0) return [];
            $hits = $rs_parse['aggregations']['fingerprint']['buckets'];
            $hits_arr = [];
            foreach ($hits as $key => $value) {
                $array_tmp = $value["rated"]["hits"]["hits"][0];
                $array_tmp["times"] = $value["doc_count"];
                array_push($hits_arr, $array_tmp);
            }
            $host_domain = [];

            $ads_tmp_md5 = [];

            foreach ($hits_arr as $key => $value) {
                if (count($host_domain) >= 200) break;

                if (empty($value['_source']['advertiser']) && empty($value['_source']['title'])) continue;

                $ads_md5 = md5($value['_source']['advertiser'] . $value['_source']['title']);

                if (!in_array($ads_md5, $ads_tmp_md5)) {

                    $item['ad_id'] = $value['_source']['id'];
                    $item['host'] = $value['_source']['advertiser_name'];
                    $item['cname'] = $value['_source']['advertiser_name_title'];
                    $item['title'] = $value['_source']['title'];
                    $item["times"] = $value['times'];
                    $host_domain[] = $item;
                    $ads_tmp_md5[] = $ads_md5;
                }

            }
            $i = 0;
            foreach ($host_domain as $key => &$value) {
                $value['id'] = $i + 1;
                $value['rank'] = $i + 1;
                $i++;
            }
            return $host_domain;

            // dd(json_encode($curl_param));
        }
        // 营销活动
        if ($role == 5) {
            $curl_param = [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'range' => [
                                    'created_date' => [
                                        'gte' => $start_time,
                                        'lte' => $end_time
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs' => [
                    $role_field => [
                        'terms' => [
                            'field' => $role_field,
                            'size' => 300,
                            'order' => [
                                'score' => 'desc'
                            ]
                        ],
                        'aggs' => [
                            'score' => [
                                'sum' => [
                                    'field' => 'score'
                                ]
                            ],
                            'host' => [
                                'terms' => [
                                    'field' => 'advertiser_na',
                                    'size' => 1
                                ]
                            ],
                            'title' => [
                                'terms' => [
                                    'field' => 'title',
                                    'size' => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $url = config('param.ES_URL') . config('param.RANKROLE_SEARCH') . '/' . config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");

            $rs_parse = json_decode($rs, true);


            if (empty($rs_parse['aggregations'][$role_field]['buckets'])) {
                return [];
            }

            $rs_data = $rs_parse['aggregations'][$role_field]['buckets'];


            $host_domain = [];

            foreach ($rs_data as $key => $value) {


                if (count($host_domain) >= 200) break;

                $host_tmp = $value['host']['buckets'];

                $title_tmp = $value['title']['buckets'];

                if (empty($host_tmp)) continue;

                $is_host_null = false;

                foreach ($host_tmp as $k => $v) {
                    if (empty($v['key'])) {
                        $is_host_null = true;
                        break;
                    }
                }
                foreach ($title_tmp as $k => $v) {
                    if (empty($v['key'])) {
                        $is_host_null = true;
                        break;
                    }
                }
                if ($is_host_null) continue;

                $item['score'] = $value['score']['value'];


                foreach ($host_tmp as $k => $v) {
                    $item['host'] = $v['key'];
                    break;
                }
                foreach ($title_tmp as $k => $v) {
                    $item['cname'] = $v['key'];
                    break;
                }

                $host_domain[] = $item;

            }
            $i = 0;
            foreach ($host_domain as $key => &$value) {
                $value['id'] = $i + 1;
                $value['rank'] = $i + 1;
                $i++;
            }
            return $host_domain;
        }

        // 广告组 媒体 跟踪者
        $curl_param = [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'range' => [
                                'created_date' => [
                                    'gte' => $start_time,
                                    'lte' => $end_time
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs' => [
                $role_field => [
                    'terms' => [
                        'field' => $role_field,
                        'size' => 200
                    ]
                ]
            ]
        ];
        $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $rs_parse = json_decode($rs, true);
        if (empty($rs_parse['aggregations'][$role_field]['buckets'])) {
            return [];
        }

        $rs_data = $rs_parse['aggregations'][$role_field]['buckets'];


        $host_domain = [];
        foreach ($rs_data as $key => $value) {

            if (!empty($value['key'])) {
                if ($value['key'] == 'com' || $value['key'] == 'cn') {
                    continue;
                } else {

                    $item['host'] = $value['key'];
                    $item['times'] = $value['doc_count'];
                    $host_domain[] = $item;
                }

            }
        }

        // 如果是 跟踪者 需要特殊处理
        if ($role == 3) {
            foreach ($host_domain as $key => $value) {
                if (is_numeric($value['host']) || empty($value['host'])) {
                    unset($host_domain[$key]);
                }
            }
        }


        foreach ($host_domain as $key => $value) {
            if (empty($value['host'])) {
                unset($host_domain[$key]);
            }
        }

        //  dd($host_domain);
        //广告主 过滤掉 百度
        if($role==2)
        {
            foreach ($host_domain as $key => $value) {
                if(stristr($value['host'], 'baidu'))unset($host_domain[$key]);
            }
        }
        //dd($host_domain);
        foreach ($host_domain as $key => &$value) {
            # code...
            $v_host = $value['host'];

            if (empty($v_host)) unset($host_domain[$key]);


            if ($role == 3) {
                $v_host = $this->parseHost($v_host);

            }
            $curl_param_domain = [
                'size' => 1,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'role' => $role
                                ]
                            ],
                            [
                                'term' => [
                                    'host_no' => $v_host
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // dd(json_encode($curl_param_domain));
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
            $data = DBUtil::get_parse_es_data($rs);
            if (!empty($data['data'])) {
                $value['cname'] = $data['data'][0]->cname;

                if ($role == 1) {
                    // $is_host_flag = false;
                    if (empty($data['data'][0]->cname) && empty($data['data'][0]->ename)) {
                        // $is_host_flag = true;
                        $value['cname'] = explode('.', $v_host)[0];
                    } else {
                        $value['cname'] = empty($data['data'][0]->cname) ? $data['data'][0]->ename : $data['data'][0]->cname;
                    }

                    $curl_param_domain_app = [
                        'size' => 0,
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'range' => [
                                            'created_date' => [
                                                'gte' => $start_time,
                                                'lte' => $end_time
                                            ]
                                        ]
                                    ],
                                    [
                                        'term' => [
                                            'publisher_na' => $v_host
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'aggs' => [
                            'platform' => [
                                'terms' => [
                                    'field' => 'platform'
                                ]
                            ],
                            'attr' => [
                                'terms' => [
                                    'field' => 'attribute04'
                                ]
                            ]
                        ]
                    ];

                    $curl_param_domain_app['_source'] = config('param.ADDATA_FILED_SOURCE');
                    // dd(json_encode($curl_param_domain));
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param_domain_app);


                    $rs_app = json_decode($rs, true);


                    // dd($rs_app);


                    $aggs_arttr = empty($rs_app['aggregations']['attr']['buckets']) ? '' : $rs_app['aggregations']['attr']['buckets'];

                    $pc_info = 'PC';

                    if ($aggs_arttr) {
                        if (count($aggs_arttr) >= 2) {
                            // dd(json_encode($curl_param_domain_app));
                            $pc_info = '(iOS + Android)';
                        } else {
                            foreach ($aggs_arttr as $agg => $avv) {

                                if (strtolower($avv['key']) == 'ios') {
                                    $pc_info = 'iOS';
                                } else {
                                    $pc_info = ucfirst($avv['key']);
                                }

                            }
                        }
                    }

                    if ($v_host == 'com.tencent.qqlive' || $v_host == 'com.tencent.news') {
                        $pc_info = 'Android';
                    }
                    $value['cname'] = empty($value['cname']) ?: $value['cname'] . ' - ' . $pc_info;
                }

            } else {
                if ($role != 3) {
                    unset($host_domain[$key]);
                } else {
                    $value['cname'] = '';
                }
            }
        }

        $new_data = [];


        $i = 1;

        $is_exist_host = [];

        foreach ($host_domain as $key => $value) {

            if (count(explode('.', $value['host'])) > 1) {
                $host_md5 = md5($value['cname'] . $value['host']);

                if (count($new_data) < 200 && !in_array($host_md5, $is_exist_host)) {

                    $item['id'] = $i;
                    $item['rank'] = $i;
                    $item['cname'] = $value['cname'];
                    $item['times'] = $value['times'];
                    $item['host'] = $value['host'];
                    $new_data[] = $item;
                    $i++;

                    $is_exist_host[] = $host_md5;
                }
            }

        }
        // dd($host_domain);
        return $new_data;
    }
    /**
     * 排行榜 host 解析
     * @param  [type] $url [description]
     * @return [type]      [description]
     */
    private function parseHost($url){
        $data = explode('.', $url);
        $co_ta = count($data);
        //判断是否是双后缀
        $zi_tow = true;
        $host_cn = 'com.cn,net.cn,org.cn,gov.cn';
        $host_cn = explode(',', $host_cn);
        foreach($host_cn as $host){
            if(strpos($url,$host)){
                $zi_tow = false;
            }
        }
        //如果是返回FALSE ，如果不是返回true
        if($zi_tow == true){
            if(empty($data[$co_ta-2]))return '';
            $host = $data[$co_ta-2].'.'.$data[$co_ta-1];
        }else{
            if(empty($data[$co_ta-3]))return '';
            $host = $data[$co_ta-3].'.'.$data[$co_ta-2].'.'.$data[$co_ta-1];
        }
        return $host;
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
        if ($size > 10) $size = 10;
        $curl_param = [
            'query' => [
                "bool" => [
                    "must" => [
                        [
                            "exists" => ["field" => "title"]
                        ],
                        [
                            'term' => ["attribute06" => "ott"]
                        ]

                    ]

                ]
            ],
            'size' => $size,
            'from' => 0,
            'sort' => [
                'created_date' => [
                    'order' => 'desc'
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


    /**
     * 获取最新广告(移动和pc,违法和虚假,抖音广告)
     * @return [type] [description]
     */
    public function getCurrAds(Request $request)
    {
        $type = empty($request->type) ? "mobile" : $request->type;
        $size = $this->size;
        if ($size > 10) $size = 10;
        if ($type == "mobile") {
            $curl_param = [
                'size' => $size,
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'range' => [
                                'platform' => [
                                    'gte' => 2
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        } elseif ($type == "false") {
            $curl_param = [
                'size' => $size,
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                "nested" => [
                                    "path" => "risk_cats_v1",
                                    "query" => [
                                        "bool" => [
                                            "must" => [
                                                [
                                                    "term" => [
                                                        "risk_cats_v1.cat" => "98"
                                                    ]
                                                ],
                                                [
                                                    "range" => [
                                                        "risk_cats_v1.score" => [
                                                            "gt" => "6"
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        } elseif ($type == "illegal") {
            $curl_param = [
                'size' => $size,
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                "nested" => [
                                    "path" => "risk_cats_v1",
                                    "query" => [
                                        "bool" => [
                                            "must" => [
                                                [
                                                    "term" => [
                                                        "risk_cats_v1.cat" => "01"
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        } elseif ($type == "douyin") {
            $curl_param = [
                'size' => $size,
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'terms' => [
                                'publisher_na' =>["com.ss.iphone.ugc.aweme","com.ss.android.ugc.aweme"]
                            ]
                        ]
                    ]
                ]
            ];

        }elseif ($type == "wechat") {
            $curl_param = [
                'size' => $size,
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'terms' => [
                                'publisher_na' =>["wechat"]
                            ]
                        ]
                    ]
                ]
            ];

        }else {
            $curl_param = [
                'size' => $size,
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'term' => [
                                'platform' => "1"
                            ]
                        ]
                    ]
                ]
            ];

        }
        // dd(json_encode($curl_param));
        $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $data = DBUtil::get_parse_es_data($rs);
        return HttpUtil::retrun_json($data, 200);
    }



    /**
     * 根据fingerprint聚合相应的信息
     * @return [type] [description]
     */

    public function getFpAggsInfor(Request $request)
    {
        if (!empty($request->fingerprint) && strpos($request->fingerprint, ",") == true) {
            $fingerprint = explode(",", $request->fingerprint);
        } elseif (!empty($request->fingerprint) && strpos($request->fingerprint, ",") == false) {
            $fingerprint = [$request->fingerprint];
        } else {
            $fingerprint = "";
        }
        $request = $request->all();
        $keys = array_keys($request);
        if (empty($fingerprint)) return HttpUtil::retrun_json(["status" => "211", "message" => "缺少参数"], 200);
        $data_top = [];
        foreach ($fingerprint as $key5 => $value5) {
            $data_top_tmp = [];
            $data_top_tmp["name"] = $value5;
            $curl_param = [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [],
                        'must_not' => [],
                        'should_not' => []
                    ]
                ],
                "aggs" => [],
                'from' => 0,
                'size' => 0
            ];
            $aggs_array = ["advertiser" => "advertiser_na", "publisher" => "publisher_na"];
            $filter = [
                "term" => ["fingerprint" => $value5]
            ];
            array_push($curl_param["query"]["bool"]["must"], $filter);
            if (in_array("agg1", $keys) && in_array($request["agg1"], array_keys($aggs_array))) {
                $curl_param["aggs"][$request["agg1"]] =
                    ["terms" => [
                        'field' => $aggs_array[$request["agg1"]],
                        'size' => 500,
                    ]];
            }
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $rs = json_decode($rs, true);
            $rs = $rs["aggregations"][$request["agg1"]]["buckets"];
            $data = [];
            foreach ($rs as $key => $value) {
                $curl_param_1 = [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    "term" => [
                                        "advertiser_na" => $value["key"]
                                    ]
                                ],
                                [
                                    "term" => [
                                        "fingerprint" => $value5
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'from' => 0,
                    'size' => 500
                ];
                $rs1 = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param_1);
                $rs1 = json_decode($rs1, true);
                $data2 = [];
                foreach ($rs1["hits"]["hits"] as $key1 => $value1) {
                    array_push($data2, $value1["_source"]["title"]);

                }
                $data2 = array_unique($data2);
                $data3 = [];
                foreach ($data2 as $key2 => $value2) {
                    $curl_param_2 = [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        "term" => [
                                            "advertiser_na" => $value["key"]
                                        ]
                                    ],

                                    [
                                        "match_phrase" => [
                                            "title" => [
                                                "query" => $value2,
                                                "minimum_should_match" => "100%"
                                            ]
                                        ]
                                    ],
                                    [
                                        "term" => [
                                            "fingerprint" => $value5
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'from' => 0,
                        'size' => 500
                    ];
                    $rs2 = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param_2);
                    $rs2 = json_decode($rs2, true);
                    $data4 = [];
                    foreach ($rs2["hits"]["hits"] as $key3 => $value3) {
                        array_push($data4, $value3["_source"]["target_url"]);
                    }
                    array_push($data3, ["name" => $value2, "children" => array_unique($data4)]);
                }
                $domain_param = [
                    "query" => [
                        "bool" => [
                            "must" => [
                                [
                                    "term" => [
                                        "host_no" => $value["key"]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "from" => 0,
                    "size" => 1

                ];
                $rs_domain = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $domain_param);
                $rs_domain = DBUtil::get_parse_es_data($rs_domain);
                if (!empty($rs_domain['data'])) {
                    $cname = $rs_domain['data'][0]->cname;
                }

                array_push($data, ["name" => $value["key"], "cname" => $cname, "children" => $data3]);
            }
            $data_top_tmp["children"] = $data;
            array_push($data_top, $data_top_tmp);
        }
        return HttpUtil::retrun_json($data_top, 200);
    }


    public function getIdsInfor(Request $request)
    {
        if (!empty($request->ids) && strpos($request->ids, ",") == true) {
            $ids = explode(",", $request->ids);
        } elseif (!empty($request->ids) && strpos($request->ids, ",") == false) {
            $ids = [$request->ids];
        } else {
            $ids = "";
        }
        if (empty($ids)) return HttpUtil::retrun_json(["status" => "211", "message" => "缺少参数"], 200);
        $data_tmp = [];
        foreach ($ids as $key => $value) {
            $curl_param = [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "id" => $value
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $rs = json_decode($rs, true);
            if (isset($rs["hits"]["hits"][0]["_source"])) array_push($data_tmp, $rs["hits"]["hits"][0]["_source"]);
        }
        $data["data"] = $data_tmp;
        $data["code"] = 200;
        return HttpUtil::retrun_json($data, 200);

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
        $vfield = ["id","username", "email", "isonline", "type","role","downloads","vip_start_time","vip_deadline","referee_count","vreferee_count","search_times","landing","is_activity","roleinfor","phonenum","character"];
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
     * 获取某个domain其它相关domain
     */
    public function getRelatedDomain(Request $request)
    {
        $host = $request->host;
        if (empty($host)) return HttpUtil::retrun_json(['status' => 211, 'message' => "缺少参数"], 200);
        $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["term" => ["host_no" => $host]]
                    ]]
                ],
            "size" => 1,
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data_temp = DBUtil::get_parse_es_data($rs);
        if(!isset($data_temp["data"][0]->parent_host))return HttpUtil::retrun_json(["code" => 200, "data" =>[]], 200);
        $parant_host = $data_temp["data"][0]->parent_host;
        $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["term" => ["parent_host" => $parant_host]]
                    ]]
                ],
            "size" => 100,
            "from" => 0
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data_temp = DBUtil::get_parse_es_data($rs);
        $data = [];
        $showfield = ["host", "cname", "role"];
        foreach ($data_temp["data"] as $key => $value) {
            $data_temp = [];
            foreach ($showfield as $key1 => $value1) {
                $data_temp[$value1] = isset($value->$value1) ? $value->$value1 : "";
            }
            array_push($data, $data_temp);
        }
        return HttpUtil::retrun_json(["code" => 200, "data" => $data], 200);
    }


    public function getWarmMessage(Request $request)
    {
        if(!empty($request->type)){
            $curl_param = [
                "_source" =>["key"],
                "size" => 0,
                "from" => 0,
                "aggs" => [
                    "amount" => [
                        "terms" => [
                            "field" => "host_full",
                            "size" => 400,
                        ],
                    ]]];
            $rs = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param);
            $rs = json_decode($rs, true);
            $data = [];
            foreach ($rs["aggregations"]["amount"]["buckets"] as $key =>$value){
                $curl_param = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_full" =>$value["key"]]]
                            ]]
                        ],
                    "size" => 1,
                ];
                $rs = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param);
                $rs = json_decode($rs, true);
                $title = $rs["hits"]["hits"][0]["_source"]["title"];
                array_push($data,["host"=>$value["key"],"amount"=>$value["doc_count"],"cname"=>$title]);
            }
            return HttpUtil::retrun_json(["code" => 200, "data" => $data], 200);
        }
        if(empty($request->host) && empty($request->brand_id))return HttpUtil::retrun_json(['status' => 211, 'message' => "缺少参数"], 200);
        if(!empty($request->host))$host_arr = [$request->host];
        if(!empty($request->brand_id)){
           $host_arr = $this->getHostsBysearch($request->brand_id,"brand_resource");
           if(empty($host_arr)){
           $host_arr = ["12345"];
           }
        }

        $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["terms" => ["host" => $host_arr]]
                    ]]
                ],
            "size" => 50,
        ];
        $data = [];
        $rs = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param);
        $rs = json_decode($rs, true);
        foreach ($rs["hits"]["hits"] as $key => $value){
            array_push($data, $value["_source"]);
        }
        return HttpUtil::retrun_json(["code" => 200, "data" => $data], 200);

    }

    public function getBrandWarmMessage(Request $request)
    {
        $domain_arr = $this->getWarmMessage($request)->original;
        $host_arr =[];
        foreach($domain_arr["data"] as $key => $value){
         if(empty($value["host"]))continue;
         array_push($host_arr,$value["host"]);
        }
         $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["terms" => ["host" => $host_arr]],
                        ["range"=>["brand_resource"=>["gt"=>0]]]
                    ]]
                ],
            "size" =>0,
            "aggs"=>[
                "brand" => [
                    "terms" => [
                        "field" => "brand_resource",
                        'execution_hint' => "map",
                        "size" => 200,
                        'collect_mode' => "breadth_first"
                    ]]
            ]
        ];
        $curl_param["aggs"]["brand"]["aggs"] = [
                "rated"=>[
                    "top_hits" => [
                        "_source"=>["brand_resource","brand"],
                        "size" => 1
                    ]
                ]
            ];
        $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs2 = json_decode($rs2,true);
        $data["data"] = [];
        $data["status"] = 200;
        foreach($rs2["aggregations"]["brand"]["buckets"] as $key => $value){
            $temp["brand_id"] = $value["rated"]["hits"]["hits"][0]["_source"]["brand_resource"];
            $temp["brand"] = $value["rated"]["hits"]["hits"][0]["_source"]["brand"];
            $temp["role"] = "advertiser"; 
            array_push($data["data"],$temp);

        }
       return HttpUtil::retrun_json($data, 200);
    }

    public function getAggsData(Request $request)
    {
        $re_arr = $request->all();
        // $re_key = implode(",", $re_arr);
        // $re_key = md5($re_key);
        // if (!empty(Redis::get($re_key))) {
        //     $data2['status'] = 200;
        //     $data2["data"] = unserialize(Redis::get($re_key));
        //     return HttpUtil::retrun_json($data2, 200);
        // }
        $max = empty($request->max) ? 100 : $request->max;
        if (count($this->user) == 0) {
            $max = 10;
        } elseif (count($this->user) != 0 && $this->user["role"] == 0) {
            $max = 100;
        }
        if(!empty($request->adminid)){$max = 1000;}
        $width = empty($request->width) ? 0 : $request->width;
        $min_width = empty($request->min_width) ? 0 : $request->min_width;
        $min_height = empty($request->min_height) ? 0 : $request->min_height;
        $height = empty($request->height) ? 0 : $request->height;
        $order_type = empty($request->order_type) ? "0" : "1"; //0代表时间,1代表相关性
        $advertiser = empty($request->advertiser) ? "" : $request->advertiser;
        $publisher = empty($request->publisher) ? "" : $request->publisher;
        $tracker = empty($request->tracker) ? "" : $request->tracker;
        $max_width = max($width, $min_width);
        $min_width = min($width, $min_width);
        $accurate = empty($request->accurate) ? "0" : $request->accurate;//模糊查询,准确查询
        $max_height = max($height, $min_height);
        $min_height = min($height, $min_height);
        $subject = empty($request->subject_title) ? "" : $request->subject_title;
        $negative = empty($request->negative) ? "" : $request->negative;
        $categories = empty($request->categories) ? "" : $request->categories;
        $area = empty($request->area) ? "" : $request->area;
        $is_width = ($max_width === $min_width ? 1 : 0);
        $is_height = ($max_height === $min_height ? 1 : 0);
        $iscampaign = empty($request->iscampaign) ? '' : $request->iscampaign;
        $param = empty($request->wd) ? '' : $request->wd;
        $param2 = empty($request->wd2) ? '' : $request->wd2;
        $oldparam = urldecode($param);
        $oldparam1 = urldecode($param);
        $param = urldecode($param);
        $oldparam2 = urldecode($param2);
        $field = empty($request->field) ? 'advertiser' : $request->field;
        $aggs_field = ["area" => "location_v1.id", "shape" => "shape", "platform" => "platform", "type" => "type", "subject" => "subject_md5", "advertiser" => "advertiser_na", "publisher" => "publisher_na", "tracker" => "tracker_list", "tag" => "tags_list", "negative" => "negative","categories" =>"categories","brand" => "advertiser_na","company" => "advertiser_na","industry"=> "advertiser_na","brands" => "advertiser_na","mainpublisher"=> "publisher_na"];
        if($field == "industry" && empty($param) && empty($request->brand) && empty($request->advertiser) && empty($request->publisher) && empty($request->company) && empty($request->mainpublisher) && empty($request->industry)){
            $data2['status'] = 200;
            $data2["data"]["industry"] = $this->getTagsInfor("行业");
           // Redis::setex($re_key, 180, serialize($data2["data"]));
            return HttpUtil::retrun_json($data2, 200);
        }
        if($field == "company" && empty($param) && empty($request->brand) && empty($request->advertiser) && empty($request->publisher) && empty($request->company) && empty($request->mainpublisher) && empty($request->industry)){
            $data2['status'] = 200;
            $data2["data"]["company"] = $this->getTagsInfor("集团");
           // Redis::setex($re_key, 180, serialize($data2["data"]));
            return HttpUtil::retrun_json($data2, 200);
        }
        if($field == "brands" && empty($param) && empty($request->brand) && empty($request->advertiser) && empty($request->publisher) && empty($request->company) && empty($request->mainpublisher) && empty($request->industry)){
            $data2['status'] = 200;
            $data2["data"]["brands"] = $this->getTagsInfor("品牌");
           // Redis::setex($re_key, 180, serialize($data2["data"]));
            return HttpUtil::retrun_json($data2, 200);
        }
        if($field == "mainpublisher" && empty($param) && empty($request->brand) && empty($request->advertiser) && empty($request->publisher) && empty($request->company) && empty($request->mainpublisher) && empty($request->industry)){
            $sql = 'SELECT * FROM brand_publisher';
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'), $sql, "POST");
            $rs = DBUtil::get_es_data($rs);
            $rs = $rs["data"];
            $mainpublisher_arr = [];
            foreach($rs as $key => $value)
            {
             array_push($mainpublisher_arr,["cname"=>$value->brand,"company"=>$value->company,"category"=>$value->category,"logo"=>$value->logo]);
            }
            $data2['status'] = 200;
            $data2["data"]["mainpublisher"] = $mainpublisher_arr;
           // Redis::setex($re_key, 180, serialize($data2["data"]));
            return HttpUtil::retrun_json($data2, 200);
        }
        $afield = $aggs_field[$field];
        if(in_array($field,["company","industry","brands","mainpublisher"]))$max =150;
        //   if (empty($param)) return HttpUtil::retrun_json(['data' => [], 'status' => 207], 200);
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        if (!empty($request->platform) && strpos($request->platform, ",") == true) {
            $platform1 = explode(",", $request->platform);
            $attribute04 = [];
            $platform = [];
            foreach ($platform1 as $key => $value) {
                if ($value == 1) {
                    array_push($platform, "1");
                    continue;
                }
                if ($value == 2) {
                    array_push($platform, "2");
                    continue;
                }
                if ($value == 3) {
                    array_push($attribute04, "android");
                    continue;
                }
                if ($value == 4) {
                    array_push($attribute04, "ios");
                    continue;
                }
                if ($value == 5) {
                    array_push($platform, "4");
                    continue;
                    // $value = 4;
                }
            }
        } else if (!empty($request->platform) && strpos($request->platform, ",") == false) {
            $attribute04 = [];
            $platform = [];
            if ($request->platform == 3) {
                array_push($attribute04, "android");
            } elseif ($request->platform == 4) {
                array_push($attribute04, "ios");
            } elseif ($request->platform == 5) {
                array_push($platform, "4");
            } else {
                array_push($platform, $request->platform);
            }
        } else {
            $attribute04 = [];
            $platform = [];
        }
        $startTime = empty($request->start_time) ? '' : $request->start_time;
        $endTime = empty($request->end_time) ? '' : $request->end_time;
        $shape = empty($request->shape) ? [] : explode(",", $request->shape);  // 0  所有尺寸  1  ， 2 ，3 ...
        if (!empty($request->type) && strpos($request->type, ",") == true) {
            $type = explode(",", $request->type);
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];
        } else if (!empty($request->type) && strpos($request->type, ",") == false) {
            $type[0] = $request->type;
            $parseType = Tools::parseTypeArr($type);
            $type = $parseType['type'];
            $material = $parseType['material'];

        } else {
            $type = [];
            $material = [];

        }
        // 相关性 查询
        $sortmode = empty($request->sortmode) ? '' : $request->sortmode;

        if ($startTime) {
            if ($endTime < $startTime) {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data, 200);
            }
        }

        $is_time = ($startTime === $endTime ? 1 : 0);

        // 验证参数格式
        $valiedParam = [
            'shape' => $shape
        ];
        if ($is_width) $valiedParam['width'] = $max_width;
        if ($is_height) $valiedParam['height'] = $max_height;
        $valiedParam['platform'] = $platform;
        $valiedParam['attribute04'] = $attribute04;
        $valiedParam['material'] = $material;
        $valiedParam['type'] = $type;

        if (!empty($advertiser) && $advertiser != "null") {
            $vfield = 'advertiser_na';
            $advertiser_t = $advertiser;
            if (strpos($advertiser, ",") == true) {
                $advertiser_t = explode(",", $advertiser);
            }
            $valiedParam[$vfield] = $advertiser_t;
        }
        if (!empty($publisher)) {
            $vfield = 'publisher_na';
            $publisher_t = $publisher;
            if (strpos($publisher, ",") == true) {
                $publisher_t = explode(",", $publisher);
            }
            $valiedParam[$vfield] = $publisher_t;
        }
        if (!empty($tracker) && $tracker != 'null') {
            $vfield = 'tracker_list';
            $tracker_t = $tracker;
            if (strpos($tracker, ",") == true) {
                $tracker_t = explode(",", $tracker);
            }
            $valiedParam[$vfield] = $tracker_t;
        }

        if (!empty($area) && $area != 'null') {
            $vfield = 'location_v1.id';
            $area_t = $area;
            if (strpos($area, ",") == true) {
                $area_t = explode(",", $area);
            }
            $valiedParam[$vfield] = $area_t;
        }
        $synonym = $en_ch == 1 ? $this->getSynonym($en_ch, $param) : '';

        $c_param = empty($synonym['c_param']) ? '' : $synonym['c_param'];
        $c_host = empty($synonym['c_host']) ? '' : $synonym['c_host'];

        $param = empty($c_param) ? $param : $c_param;
        // 默认
        if ($accurate == 2 || $accurate == 3) {
            $curl_param = $this->getCurlParam2($oldparam, 0, 0, 0);
        } else {
            $curl_param = $this->getCurlParam($oldparam, 0, 0, 0);
        }
        //dd($curl_param);
        //dd($valiedParam);
        $curl_param = Tools::valiedParamt($valiedParam, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime, $endTime, $is_time, $max_width, $min_width, $is_width, $max_height, $min_height, $is_height);
        $curl_param = Tools::rangeParamt($range, $curl_param, $iscampaign, DBUtil::check_str_cn_en($oldparam));
        if ($order_type == 1) {
            unset($curl_param["sort"]);
            $curl_param["sort"][0] = [
                "_score" => [
                    "order" => "desc"
                ]
            ];

        }

        if (empty($oldparam1)) {
            array_splice($curl_param["query"]["function_score"]["filter"]["bool"]["must"], 2, 1);
        }
        if (!empty($tracker) && $tracker == 'null') {
            $item = [
                'exists' => [
                    "field" => "tracker_list"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if (!empty($advertiser) && $advertiser == 'null') {
            $item = [
                'term' => [
                    "advertiser_na" => "*"
                ]
            ];
            if ($iscampaign == 1) {
                array_push($curl_param['query']['function_score']['query']['bool']['must_not'], $item);
            } else {

                array_push($curl_param['query']['function_score']['filter']['bool']['must_not'], $item);
            }
        }
        if ($en_ch != 1 && $accurate == 1) {
            $item = [
                'filter' => [
                    'match_phrase' => [
                        'title' => [
                            'query' => $oldparam
                        ]
                    ]
                ],
                "weight" => '4'
            ];
            array_push($curl_param['query']['function_score']['functions'], $item);
            $curl_param["query"]["function_score"]["max_boost"] = 14;
        }
        if (!empty($subject)) {
            $subject_term = [
                "match" => ["title" => ["query" => $subject, "operator" => "and", "minimum_should_match" => "100%"]]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $subject_term);
        }
        $mustno = [
            "term" => [
                "fingerprint" => "0000000000000000"
            ]
        ];
        array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must_not"], $mustno);

        $mustno = [
            "terms" => [
                "category03" => array_keys(config('param.NAGATIVE_CATEGORY'))
            ]
        ];
        array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must_not"], $mustno);

        if (!empty($request->platform) && ($request->platform == 3 || $request->platform == 4)) {
            $must = [
                "term" => [
                    "platform" => 2
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);
        }
        if ($accurate == 2) {
            $score_temp = [
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name_title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],

            ];
            $curl_param["query"]["function_score"]["functions"] = $score_temp;
        }
        if ($accurate == 3) {
            $score_temp = [
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name_title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "advertiser_name" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],
                [
                    "filter" => [
                        "match_phrase" => [
                            "title" => [
                                "query" => $oldparam,
                                "minimum_should_match" => "100%"
                            ]
                        ]
                    ],
                    "weight" => "10"
                ],

            ];
            $curl_param["query"]["function_score"]["functions"] = $score_temp;
            $curl_param["sort"] = [
                [
                    "v1_trend_times" => [
                        "order" => "desc"
                    ]
                ],
                [
                    "_score" => [
                        "order" => "desc"
                    ]
                ],
                [
                    "date5" => [
                        "order" => "desc"
                    ]
                ]
            ];
        }
        if (!empty($negative)) {
            $array_negative["nested"] = [
                "path" => "risk_cats_v1",
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "risk_cats_v1.cat" => $negative
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            if ($negative == "98") {
                array_push($array_negative["nested"]["query"]["bool"]["must"], [
                    "range" => [
                        "risk_cats_v1.score" => [
                            "gt" => "6"
                        ]
                    ]
                ]);
            }
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $array_negative);
        }
        if (!empty($categories)) {
            $categories_arr = [$categories];
            if (strpos($categories, ",") == true) {
                $categories_arr = explode(",", $categories);
            }
            $array_categories["nested"] = [
                "path" => "risk_cats_v1",
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "terms" => [
                                    "risk_cats_v1.cat" => $categories_arr
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $array_categories);
        }
        $curl_param["aggs"] = [
            $field => [
                "terms" => [
                    "field" => $afield,
                    'execution_hint' => "map",
                    "size" => $max,
                    'collect_mode' => "breadth_first"
                ],
            ],
            $field . '_total' => [
                'cardinality' => [
                    'field' => $afield
                ]
            ]

        ];
        if ($field == "area" || $field == "subject") {
            $curl_param["aggs"] = [
                $field => [
                    'terms' => [
                        'field' => $afield,
                        'execution_hint' => "map",
                        "size" => $max,
                        'collect_mode' => "breadth_first"
                    ],
                    'aggs' => [
                        "rated" => [
                            "top_hits" => [
                                "_source" => [
                                    "includes" => ["location_v1", "title", "subject_md5"]
                                ],
                                "sort" => [
                                    [
                                        "date5" => [
                                            "order" => "desc"
                                        ]
                                    ]
                                ],
                                "size" => 1
                            ]
                        ]
                    ]
                ],
                $field.'_total' => [
                    'cardinality' => [
                        'field' => $afield
                    ]
                ]
            ];

        }
        if ($field == "categories") {
            $curl_param["aggs"] = [
                "categories" => [
                    "aggs" => [
                        "cat" => [
                            "terms" => [
                                "field" => "risk_cats_v1.cat",
                                "size" => 100
                            ]
                        ]
                    ],
                    "nested" => [
                        "path" => "risk_cats_v1"
                    ]
                ]
            ];

        }
        if ($field == "negative") {
            $data["nagative"] = [];
            $array_ne = ["01", "02", "03", "04", "05", "06", "07", "08", "98"];
            unset($curl_param["aggs"]);
            foreach ($array_ne as $key => $value) {
                $curl_param_temp = $curl_param;
                $array_negative["nested"] = [
                    "path" => "risk_cats_v1",
                    "query" => [
                        "bool" => [
                            "must" => [
                                [
                                    "term" => [
                                        "risk_cats_v1.cat" => $value
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                if ($value == "98") {
                    array_push($array_negative["nested"]["query"]["bool"]["must"], [
                        "range" => [
                            "risk_cats_v1.score" => [
                                "gt" => "6"
                            ]
                        ]
                    ]);
                }
                array_push($curl_param_temp["query"]["function_score"]["filter"]["bool"]["must"], $array_negative);
                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param_temp);
                $rs = json_decode($rs, true);
                array_push($data["nagative"], ["id" => $value, "doc_count" => $rs["hits"]["total"]]);
            }
            $data2['status'] = 200;
            $data2["data"] = $this->sortData($data["nagative"], 'doc_count');
          //  Redis::setex($re_key, 180, serialize($data2["data"]));
            return HttpUtil::retrun_json($data2, 200);
        }
        if(!empty($oldparam2)){
            $wd_array = [
                "multi_match" => [
                    "query" => $oldparam2,
                    "fields" => [
                        "title",
                        "advertiser_name",
                        "advertiser_name_title"
                    ],
                    "type"=>"phrase"
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"],$wd_array);
        }
        $curl_param_domain = [
            "_source" => ["id"],
            "size" => 2000,
            "query" => [
                "bool"=>[
                    "must"=>[
                    ["term"=>["status"=>1]]
                    ]
                ]

            ]
        ];
        if (!empty($request->brand)) {
            if (strpos($request->brand, ",")) {
                $brand_arr = explode(",", $request->brand);
            } else {
                $brand_arr = [$request->brand];
            }

            $curl_temp = [
                "bool" => [
                    "should" => []
                ]
            ];
            foreach ($brand_arr as $key => $value) {
                array_push($curl_temp["bool"]["should"], ["multi_match" => [
                    "query" => $value,
                    "fields" => ["brand","brand_cname","brand_name"],
                    "type" => "phrase"
                ]
                ]);

            }
            array_push($curl_param_domain["query"]["bool"]["must"], $curl_temp);
        }
        if (!empty($request->industry)) {
            if (strpos($request->industry, ",")) {
                $industry_arr = explode(",", $request->industry);
            } else {
                $industry_arr = [$request->industry];
            }
            $curl_temp = [
                "bool" => [
                    "should" => []
                ]
            ];
            foreach ($industry_arr as $key => $value) {
                array_push($curl_temp["bool"]["should"], [
                    "term" => [
                        "industry_no" => "$value"
                    ]
                ]);
            }
            array_push($curl_param_domain["query"]["bool"]["must"], $curl_temp);

        }
        if (!empty($request->company)) {
            if (strpos($request->company, ",")) {
                $company_arr = explode(",", $request->company);
            } else {
                $company_arr = [$request->company];
            }
            $curl_temp = [
                "bool" => [
                    "should" => []
                ]
            ];
            foreach ($company_arr as $key => $value) {
                array_push($curl_temp["bool"]["should"], ["multi_match" => [
                    "query" => $value,
                    "fields" => ["company"],
                    "type" => "phrase"
                ]
                ]);
            }
            array_push($curl_param_domain["query"]["bool"]["must"], $curl_temp);
        }
        if(!empty($request->brand) || !empty($request->industry) || !empty($request->company)){
            $rs = HttpUtil::parse_curl_search("brand_infor_v4/brand_domain", $curl_param_domain);
            $rs = DBUtil::get_es_data($rs);
            $brand_ids = [];
            foreach ($rs["data"] as $key => $value) {
                $brand_ids[]= $value->id;
            }
            if(empty($brand_ids))$brand_ids[]=123456;
            $curl_param_domain_1 = [
                "_source" => ["host_no"],
                "size" => 3000,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "terms" => [
                                    "brand_resource" => $brand_ids
                                ]
                            ]
                        ]
                    ]

                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain_1);
            $rs = DBUtil::get_es_data($rs);
            $host_no = [];
            foreach ($rs["data"] as $key => $value) {
                $host_no[]= $value->host_no;
            }
            if(empty($host_no))$host_no[]= 123456;//不存在的广告主
            // $valiedParam["advertiser_na"] = $host_no;
            $must = [
                "terms" => [
                    "advertiser_na" => $host_no
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);

        }
        if(!empty($request->mainpublisher)){
            if (strpos($request->mainpublisher, ",")) {
                $mainpublisher_arr = explode(",", $request->mainpublisher);
            } else {
                $mainpublisher_arr = [$request->mainpublisher];
            }
            $mainpublisher_str = "";
            foreach($mainpublisher_arr as $key => $value){
              $mainpublisher_str =  $mainpublisher_str."'".$value."',";
            }
            $mainpublisher_str = rtrim($mainpublisher_str,",");
            $sql = 'SELECT host_no FROM domain where brand_publisher_cname  in(' . $request->mainpublisher . ')';
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'), $sql, "POST");
            $rs = DBUtil::get_es_data($rs);
            $main_arr = [];
            foreach ($rs["data"] as $key => $value) {
                array_push($main_arr,$value->host_no);
            }
            if(empty($main_arr))$main_arr[]= 123456;//不存在的媒体
            //$valiedParam["publisher_na"] = $main_arr;
            $must = [
                "terms" => [
                    "publisher_na" => $main_arr
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);

        }
        if($field == "mainpublisher"){
            $sql = 'SELECT host_no FROM domain where brand_publisher_cname is not null';
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'), $sql, "POST");
            $rs = DBUtil::get_es_data($rs);
            $main_arr = [];
            foreach ($rs["data"] as $key => $value) {
                array_push($main_arr,$value->host_no);
            }
            $must = [
                "terms" => [
                    "publisher_na" => $main_arr
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"], $must);

        }
        $curl_param["_source"] = ["id"];
      //  dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = json_decode($rs, true);
        if ($field == "platform") {
            $curl_param_1 = $curl_param;
            $curl_param_1["aggs"] = [
                "mobile" => [
                    "terms" => [
                        "field" => "attribute04",
                        'execution_hint' => "map",
                        "size" => $max,
                        'collect_mode' => "breadth_first"
                    ]
                ]
            ];
            array_push($curl_param_1["query"]["function_score"]["filter"]["bool"]["must"], [
                "term" => [
                    "platform" => 2
                ]
            ]);
            $rs2 = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param_1);
            $rs2 = json_decode($rs2, true);
            $mobile = [];
            if (!empty($rs2["aggregations"]["mobile"]["buckets"])) {
                foreach ($rs2["aggregations"]["mobile"]["buckets"] as $key => $value) {
                    array_push($mobile, $value);
                }
            }
        }
        $data = [];
        $data_field = ["advertiser", "publisher", "shape", "type", "platform", "subject", "tag", "tracker", "area","brand", "brands","company","industry","mainpublisher"];
        foreach ($rs["aggregations"] as $key => $value) {
            if (in_array($key, $data_field)) {
                $data_temp = [];
                foreach ($value["buckets"] as $key1 => $value1) {
                    if (isset($value1['rated']) && $key == "subject") {
                        $data_temp2 = ["cname" => $value1['rated']['hits']['hits'][0]['_source']['title'], "host" => $value1['rated']['hits']['hits'][0]['_source']['subject_md5'], "doc_count" => $value1['doc_count']];
                        array_push($data_temp, $data_temp2);
                    } elseif (isset($value1['rated']) && $key == "area") {
                        $data_temp3 = ["area_data" => $value1['rated']['hits']['hits'][0]['_source']['location_v1'], "doc_count" => $value1['doc_count']];
                        array_push($data_temp, $data_temp3);
                    } else {
                        if ($key == "advertiser" || $key == "publisher" || $key == "tracker" || $key == "tag") {
                            $curl_param_1 = [
                                "query" =>
                                    ["bool" =>
                                        ["must" => [
                                            ["term" => ["host_no" => $value1['key']]]
                                        ]]
                                    ],
                                "size" => 1,
                            ];
                            $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_1);
                            $data2 = DBUtil::get_parse_es_data($rs2);
                            if (!empty($data2["data"])) {
                                $temp["host"] = $data2["data"][0]->host;
                                $temp["cname"] = $data2["data"][0]->cname;
                                $temp["brand"] = isset($data2["data"][0]->brand)? $data2["data"][0]->brand : "";
                                $temp["brand_resource"] = isset($data2["data"][0]->brand_resource)? $data2["data"][0]->brand_resource : "";
                                $temp["doc_count"] = $value1['doc_count'];
                                array_push($data_temp, $temp);
                            } else {
                                $temp["cname"] = "";
                                $temp["doc_count"] = $value1['doc_count'];
                                array_push($data_temp, $temp);
                            }
                        } else {
                            $data_temp[$value1['key']] = $value1['doc_count'];
                        }
                    }
                }
                $data[$key] = $data_temp;
            } elseif ($key == "categories") {
                $cat = $value["cat"]["buckets"];
                $data["categories"] = $cat;
            } else {
                $data[$key] = $value["value"];
            }
        }

        if (isset($data["platform"])) {
            $b = [];
            foreach ($data["platform"] as $key => $value) {
                if ($key == 1) {
                    array_push($b, ["cname" => "pc", "doc_count" => $value]);
                } elseif ($key == 2) {
                    array_push($b, ["cname" => "mobile", "doc_count" => $value]);
                } elseif ($key == 4) {
                    array_push($b, ["cname" => "ott", "doc_count" => $value]);
                }

            }
            if (empty($mobile)) {
                array_push($b, ["cname" => "android", "doc_count" => 0]);
                array_push($b, ["cname" => "ios", "doc_count" => 0]);
            } else {
                foreach ($mobile as $key => $value) {
                    if ($value["key"] == "ios") {
                        array_push($b, ["cname" => "ios", "doc_count" => $value["doc_count"]]);
                    } elseif ($value["key"] == "android") {
                        array_push($b, ["cname" => "android", "doc_count" => $value["doc_count"]]);
                    }
                }
            }
            unset($data["platform"]);
            $data["platform"] = $b;
        }
        if (isset($data["type"])) {
            $b = [];
            foreach ($data["type"] as $key => $value) {
                array_push($b, ["cname" => $key, "doc_count" => $value]);
            }
            unset($data["type"]);
            $data["type"] = $b;
        }
        if (isset($data["shape"])) {
            $b = [];
            foreach ($data["shape"] as $key => $value) {
                array_push($b, ["cname" => $key, "doc_count" => $value]);
            }
            unset($data["shape"]);
            $data["shape"] = $b;
        }
        if (isset($data["brand"])) {
            $b = [];
            $is_brand = isset($request->is_brand)? $request->is_brand : "";
            $db = DB::table("domains");
            foreach ($data["brand"] as $key => $value) {
                $curl_param_1 = [
                    "_source"=>["advertiser_na","advertiser_id","advertiser_name_title"],
                    'query' => [
                        'term' => [
                            'advertiser_na' => $key
                        ]
                    ],
                    "size"=>1
                ];
                $url = config('param.ES_URL') . config('param.ADDATA_SEARCH') . '/' . config('param.ES_SEARCH');
                $rs = HttpUtil::send_curl_methods($url, $curl_param_1, "POST");
                $rs= DBUtil::get_es_data($rs);
                if(!isset($rs["data"][0]->advertiser_name_title)){
                    $curl_param_1 = [
                        "_source"=>["cname","id"],
                        'query' => [
                            'term' => [
                                'host_no' => $key
                            ]
                        ],
                        "size"=>1
                    ];
                    $url = config('param.ES_URL') . config('param.DOMAIN_SEARCH') . '/' . config('param.ES_SEARCH');
                    $rs = HttpUtil::send_curl_methods($url, $curl_param_1, "POST");
                    $rs= DBUtil::get_es_data($rs);
                    $cname = $rs["data"][0]->cname;
                    $domain_id = $rs["data"][0]->id;
                }else{
                    $cname = $rs["data"][0]->advertiser_name_title;
                    $domain_id = isset($rs["data"][0]->advertiser_id)? $rs["data"][0]->advertiser_id : "" ;
                }
                if(!empty($is_brand) && $db->select("id")->where("host",$key)->where("brand_resource",">",0)->first())continue;
                array_push($b, ["cname" => $cname, "host" => $key, "doc_count" => $value,"domain_id"=>$domain_id]);
            }
            unset($data["brand"]);
            $data["brand"] = $b;
        }
        if (isset($data["company"])){
            $b = $this->getAggsDomain($data["company"],"company");
            unset($data["company"]);
            $data["company_total"] = 0;
            $data["company"] = $b;
        }
        if(isset($data["industry"])){
            $b = $this->getAggsDomain($data["industry"],"industry");
            unset($data["industry"]);
            $data["industry_total"] = 0;
            $data["industry"] = $b;
        }
        if(isset($data["brands"])){
            $b = $this->getAggsDomain($data["brands"],"brand");
            unset($data["brands"]);
            $data["brands_total"] = 0;
            $data["brands"] = $b;
        }
        if(isset($data["mainpublisher"])){
            $b = $this->getAggsDomain($data["mainpublisher"],"mainpublisher");
            unset($data["mainpublisher"]);
            $data["mainpublisher_total"] = 0;
            $data["mainpublisher"] = $b;
        }
        $data2['status'] = 200;
        $data2["data"] = $data;
       // Redis::setex($re_key, 180, serialize($data2["data"]));
        return HttpUtil::retrun_json($data2, 200);
    }


    public function positiveCategories(Request $request)
    {
        if (!empty(Redis::get("categories"))) {
            $data2['status'] = 200;
            $data2["data"] = unserialize(Redis::get("categories"));
            return HttpUtil::retrun_json($data2, 200);
        }
        $curl_param = [
            "size" => 0,
            "aggs" => [
                "categories" => [
                    "aggs" => [
                        "cat" => [
                            "terms" => [
                                "field" => "risk_cats_v1.cat",
                                "size" => 100
                            ]
                        ]
                    ],
                    "nested" => [
                        "path" => "risk_cats_v1"
                    ]
                ]
            ]

        ];
        $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $rs = json_decode($rs, true);
        $data = [];
        foreach ($rs["aggregations"]["categories"]["cat"]["buckets"] as $key => $value) {
            array_push($data, $value);

        }
        Redis::setex("categories", 86400, serialize($data));
        return HttpUtil::retrun_json(["data" => $data, "code" => 200], 200);

    }


    /**
     * 获取创意详细
     * id 创意编号
     * @return [type] [description]
     */
    public function getAdInfo(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $data = $this->getAdDetail($id);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


        foreach ($data['data'] as $key => &$value) {
            $value->created_date = $value->date5;
            $value->last_seen = empty($value->last_seen)?$value->date5:date('Y-m-d H:i:s',$value->last_seen/1000);
        }
        $advertiser = $data['data'][0]->advertiser_na;

        $curl_param = [
            'size'=>0,
            'query'=>[
                'term'=>[
                    'advertiser_na'=>$advertiser
                ]
            ],
            'aggs'=>[
                'publishers'=>[
                    'cardinality'=>[
                        'field'=>'publisher_na'
                    ]
                ],
                'subjects'=>[
                    'cardinality'=>[
                        'field'=>'subject_md5'
                    ]
                ],
                'ads'=>[
                    'cardinality'=>[
                        'field'=>'id'
                    ]
                ],
                'trackers'=>[
                    'cardinality'=>[
                        'field'=>'tracker_list'
                    ]
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $aggs = $this->getAggs($rs);
        $data['status'] = 200;
        $data['aggs'] = $aggs;
        $curl_param_1 = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["term" => ["host" => $advertiser]]
                    ]]
                ],
            "size" => 1,
        ];
        $rs1 = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param_1);
        $rs1 = json_decode($rs1, true);
        $data["risk"] = [];
        if(!empty($rs1["hits"]["hits"]))$data["risk"] = $rs1["hits"]["hits"][0]["_source"]["riskCategory"];
        return HttpUtil::retrun_json($data,200);
    }

    private function getAdDetail($id)
    {
        $curl_param = [
            'query'=>[
                'term'=>[
                    'id'=>$id
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);
        return $data;
    }

    public function getDomainNewTime(Request $request)
    {
        $role = $request->input("role", "advertiser");
        $host = $request->input("host", "");
        $identity = ["advertiser" => "advertiser_na", "publisher" => "publisher_na", "tracker" => "tracker_list", "tag" => "tags_list"];//domain身份信息
        if(!isset($identity[$role]))return HttpUtil::retrun_json(["data"=>"","status"=>200],200);
        $curl_param = [
            'size' => 1,
            '_source' => ["created_date"],
            'sort' => [
                'created_date' => [
                    'order' => 'desc'
                ]
            ],
            'query' => [
                'bool' => [
                    'must' => [
                        'term' => [
                            $identity[$role] => $host
                        ]
                    ]
                ]
            ]
        ];
        $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $rs = json_decode($rs,true);
        if($role == "tracker" && empty($rs["hits"]["hits"])){
            $role = "tag";
            $curl_param = [
                'size' => 1,
                '_source' => ["created_date"],
                'sort' => [
                    'created_date' => [
                        'order' => 'desc'
                    ]
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'term' => [
                                $identity[$role] => $host
                            ]
                        ]
                    ]
                ]
            ];
            $url = config('param.ES_URL') . config('param.ADDATA_SEARCH6') . '/' . config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
            $rs = json_decode($rs,true);
        }
        $data["data"] = $rs["hits"]["hits"][0]["_source"][ "created_date"];
        $data["status"] = 200;
        return HttpUtil::retrun_json($data,200);
    }


    public function getWechatAds(Request $request)
    {
        $id = $request->id;
        if (empty($id)) return HttpUtil::retrun_json(["message" => "缺少参数", "status" => "211"], 200);
        $data = [];
        $data["status"] = 200;
        $source = ["wechat_label_v1", "keywords_v1.advertiser", "keywords_v1.thumb", "keywords_v1.video", "keywords_v1.title", "keywords_v1.type", "keywords_v1.desc", "md5", "id","metas"];
        try {
            $curl_param = [
                "_source" => $source,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "term" => [
                                    "id" => $id,
                                ]
                            ]
                        ]
                    ]
                ],
                "size" => 1
            ];
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
            $rs = DBUtil::get_parse_es_data($rs);
            if (empty($rs["data"])) {
                $data["data"] = [];
            } elseif (empty($rs["data"][0]->wechat_label_v1)) {
                $data["data"][] = $rs["data"][0];
                $curl_param = [
                    "_source" => $source,
                    "query" => [
                        "bool" => [
                            "must" => [
                                [
                                    "term" => [
                                        "publisher_na" => "wechat",
                                    ]
                                ]
                            ],
                            "must_not" => [
                                [
                                    "term" => [
                                        "id" => $id,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "size" => 6,
                    "sort" => [
                        "_script" => [
                            "script" => "Math.random()",
                            "type" => "number",
                            "order" => "asc"
                        ]
                    ]
                ];
                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                $rs = DBUtil::get_parse_es_data($rs);
                foreach ($rs["data"] as $key => $value) {
                    array_push($data["data"], $value);
                }

            } else {
                $data["data"][] = $rs["data"][0];
                $curl_param = [
                    "_source" => $source,
                    "query" => [
                        "bool" => [
                            "must" => [
                                [
                                    "multi_match" => [
                                        "query" => $data["data"][0]->wechat_label_v1,
                                        "fields" => ["wechat_label_v1"]
                                    ]
                                ]
                            ],
                            "must_not" => [
                                [
                                    "term" => [
                                        "id" => $id,
                                    ]
                                ]
                            ]
                        ]],

                    "size" => 6
                ];
                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                $rs = DBUtil::get_parse_es_data($rs);
                $ids = [];
                foreach ($rs["data"] as $key => $value) {
                    array_push($data["data"], $value);
                    array_push($ids, $value->id);
                }
                if (count($rs["data"]) < 6) {
                    $curl_param = [
                        "query" => [
                            "bool" => [
                                "must" => [
                                    [
                                        "term" => [
                                            "publisher_na" => "wechat",
                                        ]
                                    ]
                                ],
                                "must_not" => [
                                    [
                                        "term" => [
                                            "id" => $id,
                                        ]
                                    ],
                                    [
                                        "terms" => [
                                            "id" => $ids,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "size" => 6 - count($rs["data"]),
                        "sort" => [
                            "_script" => [
                                "script" => "Math.random()",
                                "type" => "number",
                                "order" => "asc"
                            ]
                        ]
                    ];
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $rs = DBUtil::get_parse_es_data($rs);
                    foreach ($rs["data"] as $key => $value) {
                        array_push($data["data"], $value);
                    }

                }

            }
        } catch (\Exception $e) {
            $data["data"] = [];
        }
        return HttpUtil::retrun_json($data, 200);
    }

    /**
     * 根据关键词货取品牌信息以及该关键词下面所有真实广告主信息
     * id 创意编号
     * @return [type] [description]
     */
    public function getBrandList(Request $request)
    {   
        $wd = $request->wd;
        if (empty($wd)) return HttpUtil::retrun_json(["status" => 200, "data" => []], 200);
        $page = $request->input("page", "1");
        $size = 20;
        $brand_infor = [];
        $result = DB::table('brand_detail_new')->where("Chinese_brand_name", "like", "%$wd%")->orWhere("brand_name", "like", "%$wd%")->offset($size * ($page - 1))->limit($size)->get();
        foreach ($result as $key => $value) {
            array_push($brand_infor, (array)$value);
        }
        $data["brand_infor"] = $brand_infor;
        $result = $this->geturldata("http://".$_SERVER["HTTP_HOST"]."/api/v9/get/aggs/data/new?accurate=2&field=advertiser&adminid=1&wd=".$wd);
        $result = json_decode($result, true);
        $result = $result["data"];
        $data["host"] = $result["advertiser"];
        return HttpUtil::retrun_json(["status" => 200, "data" => $data], 200);
    }

   
    private function geturldata($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $content = curl_exec($ch);
        return $content;
    }


    public function  brandDomainAdd(Request $request)
    {
        $brandid = $request->brandid;
        $host = $request->host;
        if (empty($brandid) || empty($host)) return HttpUtil::retrun_json(["status" => 211, "message"=>"缺少参数"], 200);
        if(strpos($host, ",")){
            $host_arr =  explode(",", $host);
        }else{
            $host_arr = [$host];
        }
        foreach($host_arr as $key => $value){
            if(!empty(DB::table('brand_domain_4')->where("advertiser_na",$value)->first())){
               DB::table('brand_domain_4')->where("advertiser_na",$value)->update(["brand_id"=>$brandid]);
            }else{
               DB::table('brand_domain_4')->insert(["brand_id" => $brandid,"advertiser_na" => $value]);  
            }       
        }
        $data["status"] = 200;
        $data["message"] = "添加成功";
        return HttpUtil::retrun_json($data, 200);
    
    }

     public function BrandHostUnblind(Request $request)
    {
        $host = $request->host;
        $data["status"] = 200;
        $data["message"] = "修改成功";
        if (empty($host)) return HttpUtil::retrun_json(["status" => 211, "message" => "缺少参数"], 200);
        if(!DB::table("brand_domain_4")->where("advertiser_na",$host)->update(["brand_id"=>null]))return HttpUtil::retrun_json(["status" => 212, "message" => "解绑失败"], 200);
        return HttpUtil::retrun_json($data, 200);

    }

    public function  domainInforUpdate(Request $request)
    {
        $host = $request->host;
        $cname = $request->cname;
        if (empty($host) || empty($cname)) return HttpUtil::retrun_json(["status" => 211, "message"=>"缺少参数"], 200);
        $db = DB::table("domains");
        if(!$db->where("host",$host)->first()) return HttpUtil::retrun_json(["status" => 212, "message"=>"domain不存在"], 200);
        if(!$db->where("host",$host)->update(["cname"=>$cname])) return HttpUtil::retrun_json(["status" => 213, "message"=>"修改失败"], 200);
        $data["status"] = 200;
        $data["message"] = "修改成功";
        return HttpUtil::retrun_json($data, 200);
    }

    // private function getTagsData()
    // {
    //     $data = ["publisher" => [], "brand" => [], "industry" => [],"company"=>[]];
    //     $key = "adbugtag11";
    //     $rs1 = Redis::get($key);
    //     if(!empty($rs1))return $rs1;
    //     $rs = DB::table("classfy_label")->get();
    //     foreach ($rs as $key => $value) {
    //         $data["publisher"][] = ["cname"=>$value->name];
    //     }
    //     $data["brand"] = $this->getTagsInfor("品牌");
    //     $data["industry"] = $this->getTagsInfor("行业");
    //     $data["company"] = $this->getTagsInfor("集团");
    //     //  Redis::setex($key, 600, $data);
    //     return $data;

    // }


    private function getTagsInfor($where)
    {
        $data = [];
        if($where == "媒体"){
            $rs = DB::table("classfy_label")->get();
            foreach ($rs as $key => $value) {
                $data[] = ["cname"=>$value->name];
            }
        }else{
            $rs = DB::table("home_tab")->select("name")->where("type",$where)->get();
            $data = [];
            foreach ($rs as $key => $value) {
                $data[] = ["cname"=>$value->name];
            }
        }
        return $data;
    }



    private function getBrandInfor($brand_id)
    {

        $array = $this->getBrandSearch($brand_id,20,$field="brand");
        $brand_id_arr = [];
        foreach($array as $key => $value){
            if(!empty($value[0]["id"]))array_push($brand_id_arr,$value[0]["id"]);
        }
        // $brand_id = isset($brand_id) ? $brand_id : "";
        // if (empty($brand_id)) return [];
        // if(strpos($brand_id, ",")){
        //  $brand_id_arr =  explode(",", $brand_id);
        // }else{
        //  $brand_id_arr = [$brand_id];
        // }
        $curl_param = [
            "_source" => ["host_no"],
            'query' => [
                'terms' => [
                    'brand_resource' => $brand_id_arr
                ]
            ],
            "size" =>4000,
            "from"=>0,
        ];
        $domain_url = "domain_v13/domain_index";
        $rs = HttpUtil::parse_curl_search($domain_url, $curl_param);
        $rs = DBUtil::get_es_data($rs);
        if(empty($rs["data"])) return [];
        $outinput = [];
        foreach ($rs["data"] as $key => $value) {
            array_push($outinput, ["host" =>$value->host_no]);
        }
        return $outinput;
    }



    //提示//从品牌库里获取数据
    private function getBrandBySearch($wd,$field = "company",$max = 50)
    {
        $field_arr = ["company"=>"company_no","industry"=>"industry_no"];
        $curl_param = [
            "_source" => ["host_no"],
            "size" => $max,
            "query" => [
                "wildcard"=>[
                    $field_arr[$field]=>"*$wd*"
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search("domain_v13/domain_index", $curl_param);
        $rs = DBUtil::get_es_data($rs);
        $host_no = [];
        foreach ($rs["data"] as $key => $value) {
            $host_no[]= ["host"=>$value->host_no];
        }
        return $host_no;
    }


    public function getQueryList(Request $request)
    {
        $wd = $request->input("wd", "");
        $data["data"] =[];
        $host_infor = $this->geturldata("http://testapi.adbug.cn/api/v9/get/top/host?wd=$wd");
        $host_infor = json_decode($host_infor,true);
        $host_arr = [];
        if(empty($host_infor["count"])){
            $data["data"]["host"] = ["data"=>[],"total"=>0];
        }else{
            foreach ($host_infor["count"] as $key => $value){
                array_push($host_arr,$value["data"][0]);
            }
            $data["data"]["host"] = ["data"=>$host_arr,"total"=>$host_infor["total"]];
        }
        $data["data"]["brand"] = $this->getBrandSearch($wd,20,$field="brand");
        $data["data"]["industry"] = $this->getBrandSearch($wd,20,$field="industry");
        $company_infor = $this->getBrandSearch($wd,20,$field="company");
        $company_infor_temp = [];
        if(!empty($company_infor["data"])){
            foreach($company_infor["data"] as $key => $value){
                array_push($company_infor_temp,["key"=>$value["rated"]["hits"]["hits"][0]["_source"]["company_no"],"doc_count"=>$value["doc_count"],"industry"=>$value["rated"]["hits"]["hits"][0]["_source"]["industry_no"]]);
            }
        }
        $data["data"]["company"] = ["data"=>$company_infor_temp,"total"=>$company_infor["total"]];
        if(empty($company_infor_temp))$data["data"]["company"] = ["data"=>[],"total"=>0];
        $data["status"] = 200;
        return HttpUtil::retrun_json($data, 200);
    }

    public function getBrandByWd(Request $request){
        $wd = $request->input("wd", "");
        $result = $this->getBrandSearch($wd,20,$field="brand");
        foreach ($result["data"] as $key => $value) {
              if($value["role"] == "advertiser"){
                $request->offsetSet("brand_id", $value["id"]);
                $rs = $this->getWarmMessage($request)->original;
                $result["data"][$key]["risk"] = $rs["data"];
              }
             
        }
        $result["status"] = 200;
        return HttpUtil::retrun_json($result, 200);
    }



    public function getCurrentBrandAds(Request $request)
    {   
        $rs = DB::table("homepage_brand")->select("brand_id")->get();
        $brand_id = [];
        foreach ($rs as $key => $value) {
            array_push($brand_id, $value->brand_id);
        }
        $curl_param = [
            "_source" => ["host_no"],
            "size" => 3000,
            "query" => [
                "bool" => [
                    "must" => [
                        ["terms" => [
                            "brand_resource" => $brand_id
                        ]]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs = DBUtil::get_es_data($rs);
        $host_no = [];
        foreach ($rs["data"] as $key => $value) {
            $host_no[] = $value->host_no;
        }
        $timetoday = strtotime(date("Y-m-d", strtotime("-3 days"))) * 1000;
        $curl_param = [
            "size" => 50,
            "query" => [
                "bool" => [
                    "must" => [
                        ["terms" => [
                            "advertiser_na" => $host_no
                        ]],
                        ["range" => [
                            'created_date' => [
                                'gte' => $timetoday
                            ]
                        ]]
                    ]
                ]
            ],
            "sort" => [
                "_script" => [
                    "script" => "Math.random()",
                    "type" => "number",
                    "order" => "asc"
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = DBUtil::get_parse_es_data($rs);
        $data = [];
        $data["status"] = 200;
        $data["data"] = $rs["data"];
        return HttpUtil::retrun_json($data, 200);
    }


    private function getBrandSearch($wd, $size = 50, $field = "brand")
    {
        if (empty($wd)) return ["data" => [], "total" => 0];
        $wd = strtolower($wd);
        if ($field == "brand") {
            $curl_param = [
                "size" => $size,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "wildcard" => [
                                    "brand" => "*$wd*"
                                ]
                            ],
                            ["term"=>["status"=>1]]

                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search("brand_infor_v4/brand_domain", $curl_param);
            $rs = json_decode($rs, true);
            $data["data"] = [];
            if (!empty($rs["hits"]["hits"])){
              foreach ($rs["hits"]["hits"] as $key => $value) {
                $host_no = $this->getHostsBysearch($value["_source"]["id"],"brand_resource");
                $curl_param_1 = [
                "_source"=> "riskCategory",
                "query" =>
                    ["bool" =>
                        ["must" => [
                            ["terms" => ["host" => $host_no]]
                        ]]
                    ],
                "size" => 100,
               ];
               $rs1 = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param_1);
            $rs1 = json_decode($rs1, true);
            $value["_source"]["risk"] = [];
            foreach ($rs1["hits"]["hits"] as $key_1 => $value_1){
                if(!empty($value_1["_source"]["riskCategory"])){
                 $value["_source"]["risk"] = $value_1["_source"]["riskCategory"];
                }
            }
            $value["_source"]["role"] = "advertiser";
            array_push($data["data"], $value["_source"]);
            }
            }
            $data["total"] = $rs["hits"]["total"];
             $curl_param = [
                "size" => $size,
                "from" => 0,
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "wildcard" => [
                                    "brand" => "*$wd*"
                                ]
                            ]

                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search("brand_publisher/index", $curl_param);
            $rs = json_decode($rs, true);
            if (!empty($rs["hits"]["hits"])){
            foreach ($rs["hits"]["hits"] as $key => $value) {
                 unset($value_temp);
                 $value_temp["role"] = "publisher";
                 $value_temp["industry"] = $value["_source"]["category"];
                 $value_temp["industry_no"] = $value["_source"]["category"];
                 $value_temp["company_no"] = $value["_source"]["company"];
                 $value_temp["company"] = $value["_source"]["company"];
                 $value_temp["logo"] = $value["_source"]["logo"];
                 $value_temp["brand"] = $value["_source"]["brand"];
                 array_push($data["data"], $value_temp);
            }}
            $data["total"] = $data["total"] + $rs["hits"]["total"];
            return $data;
        }
        $field_arr = ["company" => "company_no", "industry" => "industry_no"];
        if (!isset($field_arr[$field])) return ["data" => [], "total" => 0];
        $curl_param = [
            "size" => 0,
            "_source"=>["id"],
             "query" => [
                    "bool" => [
                        "must" => [
                            [
                                 "wildcard" => [
                                  $field_arr[$field] => "*$wd*"
                                 ]
                            ],
                            ["term"=>["status"=>1]]

                        ]
                    ]
                ],
            "aggs" => [
                $field => [
                    "terms" => [
                        "field" => $field_arr[$field],
                        "execution_hint" => "map",
                        "size" => $size,
                        "collect_mode" => "breadth_first"
                    ]

                ],
                $field . "_total" => [
                    "cardinality" => [
                        "field" => $field_arr[$field]
                    ]
                ]
            ]
        ];
        if($field == "company"){
            $curl_param["aggs"]["company"]["aggs"] = [
                "rated"=>[
                    "top_hits" => [
                        "size" => 1
                    ]
                ]
            ];
        }
        $rs = HttpUtil::parse_curl_search("brand_infor_v4/brand_domain", $curl_param);
        $rs = json_decode($rs, true);
        $data["total"] = $rs["aggregations"][$field . "_total"]["value"];
        $data["data"] = $rs["aggregations"][$field]["buckets"];
        return $data;

    }


    private function getAggsDomain($host,$field = "brand")
    {
        $aggs_field =["brand"=>"brand","company"=>"company_no","industry"=>"industry_no","mainpublisher"=>
        "brand_publisher_cname"];
        $curl_param =[
            "query"=>[
                "bool" =>[
                    "must"=>[
                        [
                            "terms"=>["host_no"=>array_keys($host)]
                        ]
                    ]
                ]
            ],
            "size"=>0,
            "_source"=>["id"],
            "aggs"=>[
                $field => [
                    "terms" => [
                        "field" => $aggs_field[$field],
                        'execution_hint' => "map",
                        "size" => 100,
                        'collect_mode' => "breadth_first"
                    ]],
                $field."_total" => [
                    "cardinality" => [
                        "field" => $aggs_field[$field]
                    ]
                ]
            ]
        ];
        if($field == "brand" || $field == "company" || $field == "mainpublisher"){
            $curl_param["aggs"][$field]["aggs"] = [
                "rated"=>[
                    "top_hits" => [
                        "size" => 1
                    ]
                ]
            ];
        }
        $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs2 = json_decode($rs2,true);
        $data = [];
        foreach ($rs2["aggregations"][$field]["buckets"] as $key => $value) {
            $tmp_arr1["cname"] = $value["key"];
            if($field =="brand"){
                $tmp_arr1["company"] =$value["rated"]["hits"]["hits"][0]["_source"]["company_no"];
                $tmp_arr1["industry"] =$value["rated"]["hits"]["hits"][0]["_source"]["industry_no"];
                $tmp_arr1["logo"] =$value["rated"]["hits"]["hits"][0]["_source"]["brand_logo"];
            }
            if($field =="company"){
                $tmp_arr1["industry"] =$value["rated"]["hits"]["hits"][0]["_source"]["industry_no"];
            }
            if($field =="mainpublisher"){
                $tmp_arr1["category"] =$value["rated"]["hits"]["hits"][0]["_source"]["brand_publisher_category"];
                $tmp_arr1["logo"] =$value["rated"]["hits"]["hits"][0]["_source"]["brand_publisher_logo"];
                $tmp_arr1["company"] =$value["rated"]["hits"]["hits"][0]["_source"]["brand_publisher_company"];
            }
            $tmp_arr1["doc_count"] = 0;
            $temp_arr = $this->getHostsBysearch($value["key"],$aggs_field[$field]);
            foreach($host as $key1 => $value1){
                if(in_array($key1,$temp_arr)){
                    $tmp_arr1["doc_count"] = $tmp_arr1["doc_count"] + $value1;
                }
            }
            array_push($data,$tmp_arr1);
        }
        $data = DBUtil::my_sort($data, 'doc_count', SORT_DESC, SORT_NUMERIC);
        return $data;
    }



    private function getHostsBysearch($wd,$field)
    {

        $curl_param =[
            "query"=>[
                "bool" =>[
                    "must"=>[
                        [
                            "term"=>[$field=>$wd]
                        ]
                    ]
                ]
            ],
            "size"=>1000,
            "_source"=>["host_no"],
        ];
        $rs2 = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $rs2= DBUtil::get_es_data($rs2);
        $host_no = [];
        foreach ($rs2["data"] as $key => $value) {
            $host_no[] = $value->host_no;
        }
        return $host_no;
    }

    
    public function getRiskBysearch(Request $request)
    {
        $wd = $request->input("wd", "abcdefg");//不存在的数据
        $size = $request->input("size", "10");
        $curl_param = [
            'size' => $size,
            'query' => [
                'function_score' => [
                    'filter' => [
                        'bool' => [
                            'must' => [
                                ['match_phrase' => [
                                    'keywords' => [
                                        'query' => $wd
                                    ]
                                ]]
                            ]
                        ]]
                ]

            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.WARNINGS'), $curl_param);
        $data = DBUtil::get_es_data($rs);
        $data["status"] = 200;
        return HttpUtil::retrun_json($data, 200);
    }




}
