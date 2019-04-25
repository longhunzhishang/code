<?php

namespace App\Http\Controllers\Api8;

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

    //adbug 用户登录 加密 key
    protected $user_key = 'oS1wcdz9ysuxalNhH5AXkWVC4vbFE7ZDYOfnMQPq';

    /**
     * 初始化一些数据
     * @param Request $request [description]
     */
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
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
                                            'advertiser_name_title'
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
         if($role == 3 && $aggs["ads"] == 0 && $aggs["advertisers"] == 0 && $aggs["publishers"] == 0 && $aggs["subjects"] == 0){
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

    public function getAdsSearch(Request $request)
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
        if($accurate == 2 || $accurate == 3){
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
         $mustno = [
            "term"=>[
                "fingerprint"=>"0000000000000000"
            ]
        ];
        array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must_not"],$mustno);
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
       if($accurate == 3){
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
            $curl_param["sort"] = [
                [
                    "times" => [
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
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(isset($tracker) && strpos($tracker, ",") == false && empty($data["data"])){
            foreach ($curl_param["query"]["function_score"]["filter"]["bool"]["must"] as $key => $value){
                if(array_key_exists("term",$value) && array_key_exists("tracker_list",$value["term"])){
                    $array_temp = $curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tracker_list"];
                    unset($curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tracker_list"]);
                    $curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tags_list"] = $array_temp;
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $data = DBUtil::get_parse_es_data($rs);
                }
            }
        }
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

        if(count($host) == 0){
            $data['host_top'] = "";
        }else{
            $host = array_count_values($host);
            arsort($host);
            $host_top = key($host);
            if($host[$host_top] >= 20){
                $host_top_arr = explode("&", $host_top);
                $host_top = $host_top_arr[0];
                $data['host_top'] = $host_top;
            }else{
                $data['host_top'] = "";
            }
        }
        $data['wd'] = $this->wdSplit($oldparam1);
        // 按照 时间 进行过滤
        if ($sortmode == 1) $data['data'] = DBUtil::my_sort($data['data'], 'created_date', SORT_DESC, SORT_NUMERIC);

        $data['status'] = empty($data['data']) ? 203 : 200;
        return HttpUtil::retrun_json($data, 200);
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
                                    "fields" => ["host", "cname"]
                                ]
                            ],
                            [
                                "wildcard" => [
                                    "host" => "*" . $oldparam."*",
                                ]
                            ],
                            [
                                "range" => [
                                    "brand_ads" => ["gte" => 20],
                                ]
                            ],
                            [
                                "range" => [
                                    "tracker_ads" => ["gte" => 20],
                                ]
                            ],
                            [
                                "range" => [
                                    "publisher_ads" => ["gte" => 20],
                                ]
                            ]
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
             if($value->role == 0){
                $rrolve = 3;
            }else{
                $rrolve = $value->role;
            }
            $rsd = $this->getAdsList($value->host_no, $rrolve);
            array_push($data["count"], $rsd);
        }
        $data['status'] = 200;
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
    
    // public function getDomainDetail(Request $request)
    // {
    //     $host = empty($request->host) ? "" : $request->host;
    //     $role = empty($request->role) ? "" : $request->role;
    //     $vhost = empty($request->vhost) ? "" : $request->vhost;
    //     $vrole = empty($request->vrole) ? "" : $request->vrole;
    //     $vfield = empty($request->type) ? "":$request->type;//需要聚合的字段
    //     $start_time = empty($request->start_time) ? "" : $request->start_time;
    //     $end_time = empty($request->end_time) ? "" : $request->end_time;
    //     $max = empty($request->max)? "" : $request->max;
    //     if (empty($host) || empty($role) || empty($start_time) || empty($end_time)) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
    //     $curl_param = Tools2::paramStyle(1,$vfield,$max);
    //     if($vfield == "fingerprint" || $vfield == "subject"){
    //         $aggs = [
    //                 "top_hits" => [
    //                     "sort" => [
    //                         [
    //                             "date5" => [
    //                                 "order" => "desc"
    //                             ]
    //                         ]
    //                     ],
    //                     "size" => 1
    //                 ]
    //             ];
    //         $curl_param["aggs"][$vfield]["aggs"]["rated"] = $aggs;
    //     }
    //     $identity = ["advertiser" => "advertiser_na", "publisher" => "publisher_na", "tracker" => "tracker_list", "tag" => "tags_list"];//domain身份信息
    //     $videntity = ["advertiser" => "advertiser_na", "publisher" => "publisher_na", "tracker" => "tracker_list", "tag" => "tags_list"];//合作domain身份信息
    //     if ($host && $role) $valiedParam[$identity[$role]] = $host;
    //     if ($vhost && $vrole) $valiedParam[$videntity[$vrole]] = $vhost;
    //     $curl_param = Tools2::valiedParam($valiedParam, $curl_param);
    //     if ($start_time && $end_time) {
    //         $rangeParam["created_date"] = [$start_time, $end_time];
    //     }
    //     $curl_param = Tools2::rangeParam($rangeParam, $curl_param);
    //     $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
    //     $rs = json_decode($rs, true);
    //     $total = $rs["aggregations"][$vfield."_total"]["value"];
    //     $data["total"] = $total;
    //     $data["data"] = [];
    //     foreach ($rs["aggregations"][$vfield]["buckets"] as $key => $value){
    //         if(isset($value["rated"])){
    //             $temp_array = ["data"=>[],"type_num"=>0];
    //             foreach ($value[$vfield."_type"]["buckets"] as $key1 => $value1){
    //                 array_push($temp_array["data"],[$value1["key"] => $value1["doc_count"]]);
    //             }
    //             $temp_array["type_num"] = $value[$vfield."_type_total"]["value"];
    //             array_push($data["data"],["num"=> $value["doc_count"],"rs"=>$value["rated"]["hits"]["hits"][0]["_source"],"last_detected"=>$value["rated"]["hits"]["hits"][0]["_source"]["created_date"],"date"=>$value["rated"]["hits"]["hits"][0]["_source"]["date5"],"type"=>$temp_array]);

    //         }else{
    //             $temp_array = ["data"=>[],"type_num"=>0];
    //             foreach ($value[$vfield."_type"]["buckets"] as $key1 => $value1){
    //                 array_push($temp_array["data"],[$value1["key"] => $value1["doc_count"]]);
    //             }
    //             $temp_array["type_num"] = $value[$vfield."_type_total"]["value"];

    //             array_push($data["data"],[$value["key"] => $value["doc_count"],"type"=>$temp_array]);
    //         }
    //     }
    //    // dd($data["data"]);
    //     if($vfield == "fingerprint" || $vfield == "subject"){
    //         $data['data'] = $this->sortData($data["data"], 'last_detected');
    //     }
    //     if($vfield == "tracker"){
    //         $aggs_arr = [
    //             "tag" => [
    //                 "terms" => [
    //                     "field" => "tags_list",
    //                     "size" => $max
    //                 ],
    //                 "aggs" => [
    //                     'tag_type' => [
    //                         'terms' => [
    //                             'field' => "type"
    //                         ]
    //                     ],
    //                     'tag_type_total'=>[
    //                         'cardinality'=>[
    //                             'field'=>"type"
    //                         ]
    //                     ]
    //                 ]
    //             ],
    //             "tag_total" => [
    //                 "cardinality" => [
    //                     "field" => "tags_list"
    //                 ]
    //             ]
    //         ];
    //         $curl_param["aggs"] = $aggs_arr;
    //         $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
    //         $rs = json_decode($rs, true);
    //         $total = $rs["aggregations"]["tag_total"]["value"];
    //         $data["total"] = $total + $data["total"];
    //         foreach ($rs["aggregations"]["tag"]["buckets"] as $key => $value){
    //             $temp_array = ["data"=>[],"type_num"=>0];
    //             foreach ($value["tag_type"]["buckets"] as $key1 => $value1){
    //                 array_push($temp_array["data"],[$value1["key"] => $value1["doc_count"]]);
    //             }
    //             $temp_array["type_num"] = $value["tag_type_total"]["value"];

    //             array_push($data["data"],[$value["key"] => $value["doc_count"],"type"=>$temp_array]);
    //         }

    //     }
    //     if(in_array($vfield,["advertiser","publisher","tracker"])){
    //         $data_tmp = [];
    //         foreach ( $data['data'] as $key => $value){
    //             $curl_param_domain = [
    //                 "query" =>
    //                     ["bool" =>
    //                         ["must" => [
    //                             ["term" => ["host_no" => array_keys($value)[0]]]
    //                         ]]
    //                     ],
    //                 "size" =>1
    //             ];
    //             $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
    //             $data_rs_1 = DBUtil::get_parse_es_data($rs);
    //             if(!empty($data_rs_1["data"])){
    //                 $data_rs = ["cname" => $data_rs_1["data"][0]->cname,"logo"=>$data_rs_1["data"][0]->logo, "host" => array_keys($value)[0], "ename" => $data_rs_1["data"][0]->ename,"num"=>$value[array_keys($value)[0]],"type"=>$value["type"]];
    //                 array_push($data_tmp,$data_rs);
    //             }
    //         }
    //         $data["data"] = $data_tmp;
    //     }
    //     $data['status'] = empty($data["data"]) ? 203 : 200;
    //     return HttpUtil::retrun_json($data, 200);
    // }

    public function getDomainDetail(Request $request)
    {
        $host = empty($request->host) ? "" : $request->host;
        $role = empty($request->role) ? "" : $request->role;
        $vhost = empty($request->vhost) ? "" : $request->vhost;
        $vrole = empty($request->vrole) ? "" : $request->vrole;
        $vfield = empty($request->type) ? "":$request->type;//需要聚合的字段
        $start_time = empty($request->start_time) ? "" : $request->start_time;
        $end_time = empty($request->end_time) ? "" : $request->end_time;
        $max = empty($request->max)? "" : $request->max;
        if (empty($host) || empty($role) || empty($start_time) || empty($end_time)) return HttpUtil::retrun_json(HttpUtil::returnStatus(206), 200);
        $curl_param = Tools2::paramStyle(1,$vfield,$max);
        if($vfield == "fingerprint" || $vfield == "subject"){
            $aggs = [
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
            ];
            $curl_param["aggs"][$vfield]["aggs"]["rated"] = $aggs;
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
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = json_decode($rs, true);
        $total = $rs["aggregations"][$vfield."_total"]["value"];
        $data["total"] = $total;
        $data["data"] = [];
        //dd($rs["aggregations"][$vfield]["buckets"]);
        foreach ($rs["aggregations"][$vfield]["buckets"] as $key => $value){
            $temp_array = ["data"=>[],"type_num"=>$value[$vfield."_type_total"]["value"]];
            $temp_array1 = ["data"=>[],"advertiser_num"=>$value[$vfield."_advertiser_total"]["value"]];
            $temp_array2 = ["data"=>[],"publisher_num"=>$value[$vfield."_publisher_total"]["value"]];
            $temp_array3 = ["data"=>[],"tracker_num"=>$value[$vfield."_tracker_total"]["value"]+$value[$vfield."_tag_total"]["value"]];
            $temp_array4 = ["data"=>[],"platform_num"=>0];//终端
            foreach ($value[$vfield."_type"]["buckets"] as $key1 => $value1){
                array_push($temp_array["data"],[$value1["key"] => $value1["doc_count"]]);
            }
            foreach ($value[$vfield."_platform"]["buckets"] as $key1 => $value1){
                $data_temp = explode(",",$value1["key"]);
                if($value1["key"] == "2,android")$value1["key"] = "android";
                if($value1["key"] == "2,ios")$value1["key"] = "ios";
                if($value1["key"] == "2,null" || $value1["key"] == "null,2")$value1["key"] = "mobile";
                if(array_search("4",$data_temp))$value1["key"] = "ott";
                array_push($temp_array4["data"],[$value1["key"] => $value1["doc_count"]]);
            }
            if(isset($value["rated"])){
                array_push($data["data"],["num"=> $value["doc_count"],"rs"=>$value["rated"]["hits"]["hits"][0]["_source"],"last_detected"=>$value["rated"]["hits"]["hits"][0]["_source"]["created_date"],"date"=>$value["rated"]["hits"]["hits"][0]["_source"]["date5"],"type"=>$temp_array,"advertiser"=>$temp_array1,"publisher"=>$temp_array2,"tracker"=>$temp_array3,"platform"=>$temp_array4]);
            }else{
                array_push($data["data"],[$value["key"] => $value["doc_count"],"type"=>$temp_array,"advertiser"=>$temp_array1,"publisher"=>$temp_array2,"tracker"=>$temp_array3,"platform"=>$temp_array4]);
            }
        }
        if($vfield == "fingerprint" || $vfield == "subject"){
            $data['data'] = $this->sortData($data["data"], 'last_detected');
        }
        if($vfield == "tracker"){
            $aggs_arr = [
                "tag" => [
                    "terms" => [
                        "field" => "tags_list",
                        'execution_hint'=>"map",
                        "size" => $max,
                        'collect_mode'=>"breadth_first"
                    ],
                    "aggs" => [
                        'tag_type' => [
                            'terms' => [
                                'field' => "type"
                            ]
                        ],
                        'tag_type_total'=>[
                            'cardinality'=>[
                                'field'=>"type"
                            ]
                        ],
                        'tag_platform' => [
                            'terms' => [
                                'script' => [
                                    "inline"=>"doc['platform'].value +','+ doc['attribute04'].value"
                                ]
                            ]
                        ],
                        'tag_advertiser_total'=>[
                            'cardinality'=>[
                                'field'=>"advertiser_na"
                            ]
                        ],
                        'tag_publisher_total'=>[
                            'cardinality'=>[
                                'field'=>"publisher_na"
                            ]
                        ],
                        'tag_tracker_total'=>[
                            'cardinality'=>[
                                'field'=>"tracker_list"
                            ]
                        ],
                        'tag_tag_total'=>[
                            'cardinality'=>[
                                'field'=>"tags_list"
                            ]
                        ]
                    ],
                ],
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
            foreach ($rs["aggregations"]["tag"]["buckets"] as $key => $value){
                $temp_array = ["data"=>[],"type_num"=>$value["tag_type_total"]["value"]];
                $temp_array1 = ["data"=>[],"advertiser_num"=>$value["tag_advertiser_total"]["value"]];
                $temp_array2 = ["data"=>[],"publisher_num"=>$value["tag_publisher_total"]["value"]];
                $temp_array3 = ["data"=>[],"tracker_num"=>$value["tag_tracker_total"]["value"]+$value["tag_tag_total"]["value"]];
                $temp_array4 = ["data"=>[],"platform_num"=>0];//终端
                foreach ($value["tag_type"]["buckets"] as $key1 => $value1){
                    array_push($temp_array["data"],[$value1["key"] => $value1["doc_count"]]);
                }
                foreach ($value["tag_platform"]["buckets"] as $key1 => $value1){
                    $data_temp = explode(",",$value1["key"]);
                    if($value1["key"] == "2,android")$value1["key"] = "android";
                    if($value1["key"] == "2,ios")$value1["key"] = "ios";
                    if($value1["key"] == "2,null" || $value1["key"] == "null,2")$value1["key"] = "mobile";
                    if(array_search("4",$data_temp))$value1["key"] = "ott";
                    array_push($temp_array4["data"],[$value1["key"] => $value1["doc_count"]]);
                }
                array_push($data["data"],[$value["key"] => $value["doc_count"],"type"=>$temp_array,"advertiser"=>$temp_array1,"publisher"=>$temp_array2,"tracker"=>$temp_array3,"platform"=>$temp_array4]);
            }

        }
        if(in_array($vfield,["advertiser","publisher","tracker"])){
            $data_tmp = [];
            foreach ( $data['data'] as $key => $value){
                $curl_param_domain = [
                    "query" =>
                        ["bool" =>
                            ["must" => [
                                ["term" => ["host_no" => array_keys($value)[0]]]
                            ]]
                        ],
                    "size" =>1
                ];
                $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param_domain);
                $data_rs_1 = DBUtil::get_parse_es_data($rs);
                if(!empty($data_rs_1["data"])){
                    $data_rs = ["cname" => $data_rs_1["data"][0]->cname,"logo"=>$data_rs_1["data"][0]->logo, "host" => array_keys($value)[0], "ename" => $data_rs_1["data"][0]->ename,"num"=>$value[array_keys($value)[0]],"type"=>$value["type"],"advertiser"=>$value["advertiser"],"publisher"=>$value["publisher"],"tracker"=>$value["tracker"],"platform"=>$value["platform"]];
                    array_push($data_tmp,$data_rs);
                }
            }
            $data["data"] = $data_tmp;
        }
        $data['status'] = empty($data["data"]) ? 203 : 200;
        return HttpUtil::retrun_json($data, 200);
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
//        if(!empty($param_domain)){
//            switch ($role){
//                case 1 : $tfield = "publisher_full";break;
//                case 2 : $tfield = "advertiser_full";break;
//                case 3 : $tfield = "tracker_full";break;
//            }
//            if($role ==1 || $role ==2){
//                $con_domain = [
//                    "match" => [
//                        $tfield => $param_domain
//                    ]
//                ];
//            }else{
//                $con_domain = [
//                    "multi_match" => [
//                        "query" => $param_domain,
//                        "fields" => ["tracker_full", "tags_list"]
//                    ]
//                ];
//            }
//            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"],$con_domain);
//        }
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

          $curl_param_domain = [
            "_source" => ["id"],
            "size" => 2000,
            "query" => [
                "bool"=>[
                    "must"=>[]
                ]

            ]
        ];
        if (!empty($request->wd)) {
            $brand_arr = [$request->wd];
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
            $host_no_v1 = [];
            foreach ($rs["data"] as $key => $value) {
                $host_no_v1[]= $value->host_no;
            }
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
       // dd($curl_param);
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
            if(!empty($host_no_v1)){
            $should = [
                "terms" => [
                    "advertiser_na" => $host_no_v1
                ]
            ];
            array_push($curl_param["query"]["function_score"]["filter"]["bool"]["must"][2]["bool"]["should"],$should);
            }
        }
        $curl_param["_source"] = ["id"];
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?scroll=2m';
        $rs = HttpUtil::send_curl_methods_v1($url,$curl_param,"POST");
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
            $rs = HttpUtil::send_curl_methods_v1($url,$curl_param_scroll,"POST");

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


  public function getAggsDataBySearch(Request $request)
    {
        $width = empty($request->width) ? 0 : $request->width;
        $max = empty($request->max) ? 100 : $request->max;
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
        if($accurate == 2 || $accurate == 3){
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
        if($accurate == 3){
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
            $curl_param["sort"] = [
                [
                    "times" => [
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
        $curl_param["aggs"] = [
            "shape" => [
                'terms' => [
                    'field' => "platform",
                    'size' => 10
                ],
            ],
            "platform" => [
                'terms' => [
                    'script' => [
                        "inline" => "doc['platform'].value +','+ doc['attribute04'].value"
                    ]
                ]
            ],
            "type" => [
                'terms' => [
                    'field' => "type",
                    'size' => 10
                ],
            ],
            "subject" => [
                'terms' => [
                    'field' => "subject_md5",
                    'execution_hint' => "map",
                    "size" => $max,
                    'collect_mode' => "breadth_first"
                ],
                'aggs' => [
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
            ],
            'subject_total' => [
                'cardinality' => [
                    'field' => "subject_md5"
                ]
            ],
            "advertiser" => [
                'terms' => [
                    'field' => "advertiser_na",
                    'execution_hint' => "map",
                    "size" => $max,
                    'collect_mode' => "breadth_first"
                ],
            ],
            'advertiser_total' => [
                'cardinality' => [
                    'field' => "advertiser_na"
                ]
            ],
            "publisher" => [
                'terms' => [
                    'field' => "publisher_na",
                    'execution_hint' => "map",
                    "size" => $max,
                    'collect_mode' => "breadth_first"
                ],
            ],
            'publisher_total' => [
                'cardinality' => [
                    'field' => "publisher_na"
                ]
            ],
            "tracker" => [
                'terms' => [
                    'field' => "tracker_list",
                    'execution_hint' => "map",
                    "size" => $max,
                    'collect_mode' => "breadth_first"
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
                    'execution_hint' => "map",
                    "size" => $max,
                    'collect_mode' => "breadth_first"
                ],
            ],
            'tag_total' => [
                'cardinality' => [
                    'field' => "tags_list"
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $rs = json_decode($rs, true);
        // dd($rs["aggregations"]);
        $data_field = ["advertiser", "publisher", "shape", "type", "platform", "subject", "tag", "tracker"];
        $data = [];
        foreach ($rs["aggregations"] as $key => $value) {
            if (in_array($key, $data_field)) {
                $data_temp = [];
                foreach ($value["buckets"] as $key1 => $value1) {
                    if (isset($value1['rated'])) {
                        $data_temp2 = ["cname" => $value1['rated']['hits']['hits'][0]['_source']['title'], "host" => $value1['rated']['hits']['hits'][0]['_source']['subject_md5'], "doc_count" => $value1['doc_count']];
                        array_push($data_temp, $data_temp2);
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
            } else {
                $data[$key] = $value["value"];
            }
        }
        $a["pc"] = 0;
        $a["mobile"] = 0;
        $a["android"] = 0;
        $a["ios"] = 0;
        $a["ott"] = 0;
        $b = [];
        foreach ($data["platform"] as $key => $value) {
            if (strstr($key, '1')) $a["pc"] = $a["pc"] + $value;
            if (strstr($key, '4')) $a["ott"] = $a["ott"] + $value;
            if ($key == "2,android") {
                $a["android"] = $a["android"] + $value;
            }
            if ($key == "2,ios") {
                $a["ios"] = $a["ios"] + $value;
            }
            if (strstr($key, '2')) $a["mobile"] = $a["mobile"] + $value;
        }
        array_push($b, ["cname" => "pc", "doc_count" => $a["pc"]]);
        array_push($b, ["cname" => "android", "doc_count" => $a["android"]]);
        array_push($b, ["cname" => "ios", "doc_count" => $a["ios"]]);
        array_push($b, ["cname" => "mobile", "doc_count" => $a["mobile"]]);
        array_push($b, ["cname" => "ott", "doc_count" => $a["ott"]]);
        unset($data["platform"]);
        $data["platform"] = $b;

        $b = [];
        foreach ($data["type"] as $key => $value) {
            array_push($b, ["cname" => $key, "doc_count" => $value]);
        }
        unset($data["type"]);
        $data["type"] = $b;

        $b = [];
        foreach ($data["shape"] as $key => $value) {
            array_push($b, ["cname" => $key, "doc_count" => $value]);
        }
        unset($data["shape"]);
        $data["shape"] = $b;

        $data["tracker_total"] = $data["tracker_total"] + $data["tag_total"];
        foreach ($data["tag"] as $key => $value) {
            $data["tracker"][$key] = $value;
        }
        arsort($data["tracker"]);
        unset($data["tag"]);
        unset($data["tag_total"]);
        $data2['status'] = 200;
        $data2["data"] = $data;
        return HttpUtil::retrun_json($data2, 200);
    }

    public function test1(Request $request)
    {
        $json = $request->json;
        //dd(json_encode($json,true));
        $type = empty($request->type) ? "" : $request->type;
        $curl_param = json_decode($json);
        if (empty($type)) {
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        } else {
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        }


        $data = DBUtil::get_parse_es_data($rs);
        $data['status'] = 200;
        return HttpUtil::retrun_json($rs, 200);

    }



      public function test(Request $request)
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
        $accurate = empty($request->accurate)? "" : $request->accurate;//模糊查询,准确查询
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
        $curl_param = $this->getCurlParam($oldparam, $this->size, 0, $this->from);
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
         if($en_ch != 1 && !empty($accurate)){
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
        dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(isset($tracker) && strpos($tracker, ",") == false && empty($data["data"])){
            foreach ($curl_param["query"]["function_score"]["filter"]["bool"]["must"] as $key => $value){
                if(array_key_exists("term",$value) && array_key_exists("tracker_list",$value["term"])){
                  $array_temp = $curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tracker_list"];
                  unset($curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tracker_list"]);
                  $curl_param["query"]["function_score"]["filter"]["bool"]["must"][$key]["term"]["tags_list"] = $array_temp;
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'), $curl_param);
                    $data = DBUtil::get_parse_es_data($rs);
                }
            }
        }
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

        if(count($host) == 0){
            $data['host_top'] = "";
        }else{
            $host = array_count_values($host);
            arsort($host);
            $host_top = key($host);
            if($host[$host_top] >= 20){
                $host_top_arr = explode("&", $host_top);
                $host_top = $host_top_arr[0];
                $data['host_top'] = $host_top;
            }else{
                $data['host_top'] = "";
            }
        }
        $data['wd'] = $this->wdSplit($oldparam1);
        // 按照 时间 进行过滤
        if ($sortmode == 1) $data['data'] = DBUtil::my_sort($data['data'], 'created_date', SORT_DESC, SORT_NUMERIC);

        $data['status'] = empty($data['data']) ? 203 : 200;
        return HttpUtil::retrun_json($data, 200);
    }

  public function login(Request $request)
  {  
    dd($request->session());
    Auth::attempt(array('email' => $request->email, 'password' => $request->password));
    dd(Auth::user());
    dd(1111);

  }


}
