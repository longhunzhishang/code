<?php

namespace App\Http\Controllers\Api4;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use App\Http\Utils\Tools;
use App\Synonym;
use Illuminate\Support\Facades\Redis as Redis;
use DB;
use App\Addata;
use App\Http\Utils\DSLUtil;
use Excel;
use App\RoleList;
use App\RoleHost;

/**
 * finn
 * 20170706
 * domain api
 * 2017-8-14 专为 杭州 数治科技 开发 接口
 */
class ApiController extends Controller
{
    private $_from = 0;
    /**
     *  权限 控制
     *  _TOKEN 必须是 数治科技
     * @param Request $request [description]
     */
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin:*');

        $_TOKEN = empty($request->TOKEN)?'':$request->TOKEN;

        if($_TOKEN!=config('param._AUTH_GATHER_TOKEN'))dd('用户认证失败');
    }
    /**
     * http://www.adbugapi.com/api/v4/iad/adlm?type=1&TOKEN=b04b86c026eb5a81f846b0f4af168b5b
     *
     * testapi.adbug.cn/api/v4/iad/adlm?type=1&role=1&TOKEN=b04b86c026eb5a81f846b0f4af168b5b
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function lianMengAds(Request $request)
    {   
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        // $now_time = date('Y-m-d H:i:s',time());
        // $my_y_time= date("Y-m-d H:i:s", strtotime("-1 year"));

        $st = $request->str;
        $en = $request->en;

        // $date_week = DBUtil::diffBetweenTwoDays('2016-12-15','2017-12-16');

        

        // $date_week = DBUtil::get_weeks('2016-12-27','2017-12-27');

        // // dd($date_week);

        // foreach ($date_week as $k => &$v) {
        //     $v['start_time'] = $v['start_time'].' 00:00:00';
        //     $v['end_time'] = $v['end_time'].' 23:59:59';
        // }

        // dd($date_week);




        // $my_y_time = '2016-12-15 10:59:28';
        // $now_time = '2017-01-15 10:59:28';


        // foreach ($date_week as $key => $value) {


        //     echo $value['start_time'],'====',$value['end_time'],'<br/>';


        //      $curl_param = [
        //         'size'=>0,
        //         'query'=>[
        //             'bool'=>[
        //                 'must'=>[
        //                     [
        //                         'range'=>[
        //                             'date5'=>[
        //                                 'gte'=>$value['start_time'],
        //                                 'lte'=>$value['end_time']
        //                             ]
        //                         ]
        //                     ]
        //                 ]
        //             ]
        //         ],
        //         'aggs'=>[
        //             // 'tracker'=>[
        //             //     'terms'=>[
        //             //         'field'=>'tracker_list',
        //             //         'min_doc_count'=>10,
        //             //         'size'=>0
        //             //     ]
        //             // ]//,
        //             // 'publisher'=>[
        //             //     'terms'=>[
        //             //         'field'=>'publisher_na',
        //             //         'min_doc_count'=>10,
        //             //         'size'=>0
        //             //     ]
        //             // ]//,
        //             'advertiser'=>[
        //                 'terms'=>[
        //                     'field'=>'advertiser_na',
        //                     'min_doc_count'=>10,
        //                     'size'=>0
        //                 ]
        //             ]
        //         ]
        //     ];

        //     // dd(json_encode($curl_param));
        //     $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        //     $rs_parse = json_decode($rs,true);

        //     // $agg_tracker= empty($rs_parse['aggregations']['tracker']['buckets'])?'':$rs_parse['aggregations']['tracker']['buckets'];

        //     // $agg_publisher= empty($rs_parse['aggregations']['publisher']['buckets'])?'':$rs_parse['aggregations']['publisher']['buckets'];

        //     $agg_advertiser= empty($rs_parse['aggregations']['advertiser']['buckets'])?'':$rs_parse['aggregations']['advertiser']['buckets'];


        //     if(!empty($agg_advertiser))
        //     {
        //          foreach ($agg_advertiser as $akey => $av) {

        //             if(!empty($av['key']))
        //             {
        //                 $roleHost = RoleHost::where(['role'=>2,'host'=>$av['key']])->first();
        //                 if(empty($roleHost))$roleHost = new RoleHost();

        //                 $roleHost->role = 2;
        //                 $roleHost->host = $av['key'];
        //                 $roleHost->ads = $av['doc_count'];
        //                 $roleHost->save();
        //             }
                   
        //         }
        //     }

        //     // if(!empty($agg_tracker))
        //     // {
        //     //     foreach ($agg_tracker as $ak => $av) {

        //     //         if(!empty($av['key']))
        //     //         {
        //     //             $roleHost = RoleHost::where(['role'=>3,'host'=>$av['key']])->first();

        //     //             if(empty($roleHost))$roleHost = new RoleHost();

        //     //             $roleHost->role = 3;
        //     //             $roleHost->host = $av['key'];
        //     //             $roleHost->ads = $av['doc_count'];

        //     //             $roleHost->save();
        //     //         }
                    
        //     //     }
        //     // }
            
        //     // if(!empty($agg_publisher))
        //     // {

        //     //     // dd($agg_publisher);
        //     //     foreach ($agg_publisher as $ak => $av) {
                    
        //     //         if(!empty($av['key']))
        //     //         {
        //     //             $roleHost = RoleHost::where(['role'=>1,'host'=>$av['key']])->first();

        //     //             if(empty($roleHost))$roleHost = new RoleHost();
        //     //             $roleHost->role = 1;
        //     //             $roleHost->host = $av['key'];
        //     //             $roleHost->ads = $av['doc_count'];
        //     //             $roleHost->save();
        //     //         }
                    
        //     //     }
        //     // }

        // }
       
        // dd('ok');


        // if(empty($aggs))continue;


        // dd(json_encode($curl_param));



        // $param = empty($request->wd)?'':$request->wd;

        // $role = empty($request->role)?0:$request->role;
        // 1 DSP 广告主 媒体 都是上海
        // 2 DSP 非上海 媒体 广告主都是上海
        // 3 DSP 媒体非上海 广告主是上海
        // 
        $type = empty($request->type)?'':$request->type;

        if(empty($type))dd('type 不可为空');

        // $start_time = empty($request->start_time)?'':$request->start_time;
        // $end_time = empty($request->end_time)?'':$request->end_time;


        $now_time = date('Y-m-d H:i:s',time());
        //得到最近一月
        $my_d_time= date("Y-m-d H:i:s", strtotime("-1 month"));

        //前三个月
        $my_m_time= date("Y-m-d H:i:s", strtotime("-3 month"));  

        $my_y_time= date("Y-m-d H:i:s", strtotime("-1 year")); 

        $date = [
            [
                'start_time'=>$my_d_time,
                'end_time'=>$now_time
            ],
            [
                'start_time'=>$my_m_time,
                'end_time'=>$now_time
            ],
            [
                'start_time'=>$my_y_time,
                'end_time'=>$now_time
            ]
        ];
        $area_sh = '上海';

        $term_area_must = [];

        $term_area_must_not = [];

        $term_area_should = [];


        switch ($type) {
            case '1':
                $term_area_must = [
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '2':
                $term_area_must = [
                   
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ]
                ];
                $term_area_must_not = [
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '3':
                $term_area_must = [
                   
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ]
                    
                ];
                $term_area_must_not = [
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '4':
                $term_area_must = [
                   
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ]
                    
                ];
                $term_area_must_not = [
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '5':
                $term_area_must = [
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ]
                ];
                $term_area_must_not = [
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '6':
                $term_area_must = [
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ]
                ];
                $term_area_must_not = [
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '7':
                $term_area_should = [
                    [
                        'match'=>[
                            'tracker_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'publisher_full'=>$area_sh
                        ]
                    ],
                    [
                        'match'=>[
                            'advertiser_full'=>$area_sh
                        ]
                    ]
                ];
                break;
        }


        foreach ($date as $key => $value) {

             $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'date5'=>[
                                        'gte'=>$value['start_time'],
                                        'lte'=>$value['end_time']
                                    ]
                                ]
                            ]
                        ],
                        'must_not'=>[],
                        'should'=>[]
                    ]
                ],
                'aggs'=>[
                    'tracker'=>[
                        'terms'=>[
                            'field'=>'tracker_list',
                            'size'=>0
                        ],
                        'aggs'=>[
                            'advertiser'=>[
                                'cardinality'=>[
                                    'field'=>'advertiser_na'
                                ]
                            ],
                            'publisher'=>[
                                'cardinality'=>[
                                    'field'=>'publisher_na'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            if(!empty($term_area_must))
            {
                foreach ($term_area_must as $mk => $mv) {

                    array_push($curl_param['query']['bool']['must'], $mv);
                }
            }
            if(!empty($term_area_must_not))
            {
                foreach ($term_area_must_not as $mk => $mv) {
                
                    array_push($curl_param['query']['bool']['must_not'], $mv);
                }
            }
            if(!empty($term_area_should))
            {
                foreach ($term_area_should as $mk => $mv) {
                
                    array_push($curl_param['query']['bool']['should'], $mv);
                }
            }
            
            dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

            $rs_parse = json_decode($rs,true);

            $aggs = empty($rs_parse['aggregations']['tracker']['buckets'])?'':$rs_parse['aggregations']['tracker']['buckets'];

            if(empty($aggs))continue;
            foreach ($aggs as $ak => $av) {

                $domain = DB::table('domains')->where('host',$av['key'])->first();



                if($type==1)
                {
                    if(empty($domain) || empty($domain->cname) || strstr($domain->cname, '上海')===FALSE)continue;
                }
                


                $roleList = RoleList::where(['host'=>$av['key'],'type'=>$type])->first();
                if(empty($roleList))$roleList = new RoleList();
                $roleList->cname = empty($domain->cname)?'':$domain->cname;

                $roleList->host = $av['key'];
                $roleList->advertiser = empty($av['advertiser']['value'])?0:$av['advertiser']['value'];


                $roleList->publisher = empty($av['publisher']['value'])?0:$av['publisher']['value'];

                switch ($key) {
                    case '0':
                        # code...
                        $roleList->day_num = $av['doc_count'];
                        break;
                    case '1':
                        # code...
                        $roleList->mouth_num = $av['doc_count'];
                        break;
                    case '2':
                        # code...
                        $roleList->year_num = $av['doc_count'];
                        break;
                }
                $roleList->type = $type;
                $roleList->save();
                // $item['ads'] = $
            }
        }


        dd(77);


        // $new_tracker = [];
        // foreach ($date as $key => $value) {

        //     $curl_param = [
        //         'size'=>0,
        //         'query'=>[
        //             'bool'=>[
        //                 'must'=>[
        //                     [
        //                         'range'=>[
        //                             'date5'=>[
        //                                 'gte'=>$value['start_time'],
        //                                 'lte'=>$value['end_time']
        //                             ]
        //                         ]
        //                     ]
        //                 ],
        //                 'must_not'=>[]
        //             ]
        //         ],
        //         'aggs'=>[
        //             'ids'=>[
        //                 'cardinality'=>[
        //                     'field'=>'id'
        //                 ]
        //             ]
        //             // 'advertiser'=>[
        //             //     'cardinality'=>[
        //             //         'field'=>'advertiser_na'
        //             //     ]
        //             // ],
        //             // 'publisher'=>[
        //             //     'cardinality'=>[
        //             //         'field'=>'publisher_na'
        //             //     ]
        //             // ]
        //         ]
        //     ];

        //     foreach ($term_area_must as $mk => $mv) {

        //         array_push($curl_param['query']['bool']['must'], $mv);
        //     }
        //     foreach ($term_area_must_not as $mk => $mv) {
                
        //         array_push($curl_param['query']['bool']['must_not'], $mv);
        //     }
            
        //     // dd(json_encode($curl_param));
        //     $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        //     $rs_parse = json_decode($rs,true);

        //     $ids = empty($rs_parse['aggregations']['ids']['value'])?'':$rs_parse['aggregations']['ids']['value'];

        //     // $tracker_num = empty($rs_parse['aggregations']['tracker']['value'])?'':$rs_parse['aggregations']['tracker']['value'];
            

        //     // $advertiser_num = empty($rs_parse['aggregations']['advertiser']['value'])?'':$rs_parse['aggregations']['advertiser']['value'];


        //     // $publisher_num = empty($rs_parse['aggregations']['publisher']['value'])?'':$rs_parse['aggregations']['publisher']['value'];

        //     $roleList = RoleList::where(['type'=>$type])->first();
        //     if(empty($roleList))$roleList = new RoleList();
          
        //     switch ($key) {
        //         case '0':
        //             # code...
        //             $roleList->a_day_num = $ids;
        //             // $roleList->t_day_num = $tracker_num;
        //             // $roleList->p_day_num = $publisher_num;
        //             break;
        //         case '1':
        //             # code...
        //             $roleList->a_mouth_num = $ids;
        //             // $roleList->t_mouth_num = $tracker_num;
        //             // $roleList->p_mouth_num = $publisher_num;
        //             break;
        //         case '2':
        //             # code...
        //             $roleList->a_year_num = $ids;
        //             // $roleList->t_year_num = $tracker_num;
        //             // $roleList->p_year_num = $publisher_num;
        //             break;
        //     }
        //     $roleList->type = $type;
        //     $roleList->save();
        // }
        // dd(99);


        $sh_host = [
            'irs01.com',
            'pingcoo.com',
            'adsame.com',
            'thepaper.cn',
            'fdsy123.com',
            'mediav.com',
            'wkanx.com',
            'stockstar.com',
            'admaster.com.cn',
            'ximalaya.com',
            'zhiziyun.com'
        ];

        $new_tracker = [];
        foreach ($date as $key => $value) {


            foreach ($sh_host as $shk => $shv) {
                    $curl_param = [
                        'size'=>0,
                        'query'=>[
                            'bool'=>[
                                'must'=>[
                                    [
                                        'range'=>[
                                            'date5'=>[
                                                'gte'=>$value['start_time'],
                                                'lte'=>$value['end_time']
                                            ]
                                        ]
                                    ],
                                    [
                                        'term'=>[
                                            'tracker_list'=>$shv
                                        ]
                                    ]
                                ],
                                'must_not'=>[],
                                'should'=>[]
                            ]
                        ],
                        'aggs'=>[
                            'tracker'=>[
                                'terms'=>[
                                    'field'=>'tracker_list',
                                    'size'=>0
                                ],
                                'aggs'=>[
                                    'advertiser'=>[
                                        'cardinality'=>[
                                            'field'=>'advertiser_na'
                                        ]
                                    ],
                                    'publisher'=>[
                                        'cardinality'=>[
                                            'field'=>'publisher_na'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];

                    if(!empty($term_area_must))
                    {
                        foreach ($term_area_must as $mk => $mv) {

                            array_push($curl_param['query']['bool']['must'], $mv);
                        }
                    }
                    if(!empty($term_area_must_not))
                    {
                        foreach ($term_area_must_not as $mk => $mv) {
                        
                            array_push($curl_param['query']['bool']['must_not'], $mv);
                        }
                    }
                    if(!empty($term_area_should))
                    {
                        foreach ($term_area_should as $mk => $mv) {
                        
                            array_push($curl_param['query']['bool']['should'], $mv);
                        }
                    }
                    
                    // dd(json_encode($curl_param));
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

                    $rs_parse = json_decode($rs,true);

                    $aggs = empty($rs_parse['aggregations']['tracker']['buckets'])?'':$rs_parse['aggregations']['tracker']['buckets'];

                    if(empty($aggs))continue;
                    foreach ($aggs as $ak => $av) {

                        $domain = DB::table('domains')->where('host',$av['key'])->first();



                        if($type==1)
                        {
                            if(empty($domain) || empty($domain->cname) || strstr($domain->cname, '上海')===FALSE)continue;
                        }
                        


                        $roleList = RoleList::where(['host'=>$av['key'],'type'=>$type])->first();
                        if(empty($roleList))$roleList = new RoleList();
                        $roleList->cname = empty($domain->cname)?'':$domain->cname;

                        $roleList->host = $av['key'];
                        $roleList->advertiser = empty($av['advertiser']['value'])?0:$av['advertiser']['value'];


                        $roleList->publisher = empty($av['publisher']['value'])?0:$av['publisher']['value'];

                        switch ($key) {
                            case '0':
                                # code...
                                $roleList->day_num = $av['doc_count'];
                                break;
                            case '1':
                                # code...
                                $roleList->mouth_num = $av['doc_count'];
                                break;
                            case '2':
                                # code...
                                $roleList->year_num = $av['doc_count'];
                                break;
                        }
                        $roleList->type = $type;
                        $roleList->save();
                        // $item['ads'] = $
                    }
            }
        }

        dd(99);

        // foreach ($new_tracker as $key => &$value) {
        //     $domain = DB::table('domains')->where('host',$value['host'])->first();
        //     $value['cname'] = empty($domain->cname)?'':$domain->cname;
        // }


        // foreach ($new_tracker as $key => $value) {
        //     if(empty($value['cname']) || strstr($value['cname'], '上海') === FALSE)
        //     {
        //         unset($new_tracker[$key]);
        //     }
        // }

        // $cellData = [
        //     ['广告联盟','中文名','媒体数','广告主数','创意数','类型']
        // ];


        //  foreach ($new_tracker as $key => $value) {
        //     $items['host'] = $value['host'];
        //     $items['cname'] = $value['cname'];
        //     $items['publisher'] = $value['publisher'];
        //     $items['advertiser'] = $value['advertiser'];
        //     $items['ads'] = $value['ads'];
        //     $items['type'] = $value['type'];
        //     array_push($cellData, $items);
        // }

        // Tools::exportData('广告联盟数据详细',$cellData);


        dd('new ok');





        switch ($type) {
            case '1':
                $term_area_must = [
                    [
                        'term'=>[
                            'p_p_area'=>$area_sh
                        ]
                    ],
                    [
                        'term'=>[
                            'a_p_area'=>$area_sh
                        ]
                    ],
                    [
                        'term'=>[
                            't_p_area'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '2':
                $term_area_must = [
                    [
                        'term'=>[
                            'p_p_area'=>$area_sh
                        ]
                    ],
                    [
                        'term'=>[
                            'a_p_area'=>$area_sh
                        ]
                    ]
                ];
                $term_area_must_not = [
                    [
                        'term'=>[
                            't_p_area'=>$area_sh
                        ]
                    ]
                ];
                break;
            case '3':
                $term_area_must = [
                    [
                        'term'=>[
                            'a_p_area'=>$area_sh
                        ]
                    ]
                ];
                $term_area_must_not = [
                    [
                        'term'=>[
                            't_p_area'=>$area_sh
                        ]
                    ],
                    [
                        'term'=>[
                            'p_p_area'=>$area_sh
                        ]
                    ]
                ];
                break;
        }
        $new_data = [];


        // $top_main = DB::table('top_main')->where('city','上海')->where('websitetype','like','%广告联盟%')->get();


        $top_main = [
            'mediav.com','adsame.com'
        ];
        // dd($top_main);

        foreach ($date as $key => $value) {
            foreach ($top_main as $tk => $tv) {

                 $curl_param = [
                    'size'=>1000,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'range'=>[
                                        'date5'=>[
                                            'gte'=>$value['start_time'],
                                            'lte'=>$value['end_time']
                                        ]
                                    ]
                                ],
                                [
                                    'term'=>[
                                        'tracker_list'=>$tv
                                    ]
                                ]
                            ],
                            'must_not'=>[]
                        ]
                    ]
                ];
                foreach ($term_area_must as $mk => $mv) {

                    array_push($curl_param['query']['bool']['must'], $mv);
                }
                foreach ($term_area_must_not as $mk => $mv) {
                    
                    array_push($curl_param['query']['bool']['must_not'], $mv);
                }
                // dd(json_encode($curl_param));
                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

                $data = DBUtil::get_parse_es_data($rs);

                if(empty($data['data']))continue;

                // foreach ($data['data'] as $k => &$v) {
                //     $curl_param_log = [
                //         'size'=>0,
                //         'query'=>[
                //             'bool'=>[
                //                 'must'=>[
                //                     [
                //                         'range'=>[
                //                             'date'=>[
                //                                 'gte'=>$value['start_time'],
                //                                 'lte'=>$value['end_time']
                //                             ]
                //                         ]
                //                     ],
                //                     [
                //                         'term'=>[
                //                             'md5'=>$v->md5
                //                         ]
                //                     ]
                //                 ],
                //                 'must_not'=>[]
                //             ]
                //         ],
                //     ];


                //     foreach ($term_area_must as $mk => $mv) {

                //         array_push($curl_param_log['query']['bool']['must'], $mv);
                //     }
                //     foreach ($term_area_must_not as $mk => $mv) {
                        
                //         array_push($curl_param_log['query']['bool']['must_not'], $mv);
                //     }

                //     // dd(json_encode($curl_param_log));
                //     $rs_log = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param_log);
                //     $hits_log = json_decode($rs_log,true);
                //     $type_log = empty($hits_log['hits']['total'])?0:$hits_log['hits']['total'];
                //     $v->ads_log = $type_log;

                // }

                // dd($data['data']);

                foreach ($data['data'] as $kk => $vv) {
                   
                    // $role_list = RoleList::where(['type'=>$type,'md5'=>$vv->md5])->first();

                    // if(empty($role_list))$role_list = new RoleList();

                    $role_list = new RoleList();
                    $role_list->advertiser = $vv->advertiser_na;

                    $role_list->publisher = $vv->publisher_na;

                    $role_list->tracker = $tv;
                    $role_list->md5 = $vv->md5;
                    $role_list->type = $type;
                    $role_list->start_time = $value['start_time'];
                    $role_list->end_time = $value['end_time'];

                    // if($type==1)
                    // {
                    //     $role_list->type1_log = $vv->ads_log;
                    // }
                    // if($type==2)
                    // {
                    //      $role_list->type2_log = $vv->ads_log;
                    // }
                    // if($type==3)
                    // {
                    //     $role_list->type3_log = $vv->ads_log;
                    // }
                    $role_list->save();
                }

            }
         
        }
        // 更细domain 信息
        $rolelist = RoleList::where('type',$type)->get();

        if(empty($rolelist))dd('no data');
        foreach ($rolelist as $key => $value) {
            # code...
            if(!empty($value->tracker))
            {
                self::saveHostInfo($value->tracker,$value->id,1);
            }

            if(!empty($value->advertiser))
            {
                self::saveHostInfo($value->advertiser,$value->id,2);
            }

            if(!empty($value->publisher))
            {
                self::saveHostInfo($value->publisher,$value->id,3);
            }
        }
        dd('ok');
        // dd($new_main_info);
      
    }

    public static function saveHostInfo($host,$id,$type)
    {
        $roleList = RoleList::find($id);
        $domain = DB::table('domains')->where('host',$host)->first();

        $top_main = DB::table('top_main')->where('domain',$host)->first();

        switch ($type) {
            case '1':
                $roleList->t_domain = $host;
                $roleList->t_cname = empty($domain->cname)?'':$domain->cname;
                $roleList->t_unityname = empty($top_main->unitname)?'':$top_main->unitname;
                break;
            case '2':
                $roleList->a_domain = $host;
                $roleList->a_cname = empty($domain->cname)?'':$domain->cname;
                $roleList->a_unityname = empty($top_main->unitname)?'':$top_main->unitname;
                break;
            case '3':
                $roleList->p_domain = $host;
                $roleList->p_cname = empty($domain->cname)?'':$domain->cname;
                $roleList->p_unityname = empty($top_main->unitname)?'':$top_main->unitname;
                break;
        }
        $roleList->save();
    }

    public static function saveLogsNew($t_role,$role,$roleparam,$a_nums,$rowid,$start_time,$end_time,$term_area_must,$term_area_must_not)
    {
        $curl_param_log = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'date'=>[
                                    'gte'=>$start_time,
                                    'lte'=>$end_time
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                $role=>$roleparam
                            ]
                        ],
                        [
                            'term'=>[
                                'tracker_list'=>$t_role
                            ]
                        ]
                    ],
                    'must_not'=>[]
                ]
            ],
        ];


        foreach ($term_area_must as $mk => $mv) {

            array_push($curl_param_log['query']['bool']['must'], $mv);
        }
        foreach ($term_area_must_not as $mk => $mv) {
            
            array_push($curl_param_log['query']['bool']['must_not'], $mv);
        }

        // dd(json_encode($curl_param_log));
        $rs_log = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param_log);

        $hits_log = json_decode($rs_log,true);
        $rolelist_only = RoleList::find($rowid);
        $rolelist_only->$a_nums = empty($hits_log['hits']['total'])?0:$hits_log['hits']['total'];
        $rolelist_only->save();
    }


    public static function saveLogs($role,$roledata,$termfield,$type,$start_time,$end_time,$term_area_must,$term_area_must_not,$key)
    {
       foreach ($roledata as $k => $v) {
            $curl_param_log = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'date'=>[
                                        'gte'=>$start_time,
                                        'lte'=>$end_time
                                    ]
                                ]
                            ],
                            [
                                'term'=>[
                                    $termfield=>$v['key']
                                ]
                            ]
                        ],
                        'must_not'=>[]
                    ]
                ],
            ];


            foreach ($term_area_must as $mk => $mv) {

                array_push($curl_param_log['query']['bool']['must'], $mv);
            }
            foreach ($term_area_must_not as $mk => $mv) {
                
                array_push($curl_param_log['query']['bool']['must_not'], $mv);
            }

            // dd(json_encode($curl_param_log));
            $rs_log = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param_log);

            $hits_log = json_decode($rs_log,true);
           

            $roleList = RoleList::where(['domain'=>$v['key'],'role'=>$role,'type'=>$type])->first();
            if(empty($roleList))$roleList = new RoleList();

            $domain = DB::table('domains')->where('host',$v['key'])->first();


            $top_main = DB::table('top_main')->where('domain',$v['key'])->first();
            // dd($hits_log);

            switch ($key) {
                case '0':
                    $roleList->type1 = $v['doc_count'];
                    $roleList->type1_log = empty($hits_log['hits']['total'])?0:$hits_log['hits']['total'];
                    break;
                case '1':
                    $roleList->type2 = $v['doc_count'];
                    $roleList->type2_log = empty($hits_log['hits']['total'])?0:$hits_log['hits']['total'];
                    break;
                
                case '2':
                    $roleList->type3 = $v['doc_count'];
                    $roleList->type3_log = empty($hits_log['hits']['total'])?0:$hits_log['hits']['total'];
                    break;
            }
            $roleList->domain = $v['key'];
            $roleList->type = $type;

            $roleList->role = $role;

            $roleList->cname = empty($domain->cname)?'':$domain->cname;

            $roleList->unityname = empty($top_main->unitname)?'':$top_main->unitname;
            $roleList->save();
        }
        
    }
    public function setCurlParam($start_time,$end_time,$param)
    {
        $curl_param = [
            'size'=>0,
            'query'=>[
                'function_score'=>[
                    'filter'=>[
                        'bool'=>[
                            'must'=>[
                                'range'=>[
                                    'date5'=>[
                                        'gte'=>$start_time,
                                        'lte'=>$end_time
                                    ]
                                ]
                            ],
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title',
                                        'publisher_full',
                                        'trackers',
                                        'tags'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'functions'=>[
                        [
                            'filter'=>[
                                'match'=>[
                                    'advertiser_name_title'=>[
                                        'query'=>$param,
                                            'minimum_should_match'=>'100%'
                                        ]
                                    ]
                                ],
                            "weight"=>'4'
                        ],
                        [
                            'filter'=>[
                                'match'=>[
                                    'advertiser_name'=>$param
                                ]
                            ],
                            "weight"=>'10'
                        ],
                        [
                            'filter'=>[
                                'match'=>[
                                    'title'=>[
                                        // 'query'=>$oldparam,
                                        'query'=>$param,
                                         'minimum_should_match'=>'100%'
                                    ]
                                ]
                            ],
                            "weight"=>'2'
                        ],
                        [
                            'filter'=>[
                                'match'=>[
                                    'publisher_full'=>$param
                                ]
                            ],
                            "weight"=>'10'
                        ],
                        [
                            'filter'=>[
                                'match'=>[
                                    'trackers'=>$param
                                ]
                            ],
                            "weight"=>'10'
                        ],
                        [
                            'filter'=>[
                                'match'=>[
                                    'tags'=>$param
                                ]
                            ],
                            "weight"=>'10'
                        ]
                    ],
                    'score_mode'=>'sum',
                    'max_boost'=>'10'
                ],
            ]
        ];

        $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
        $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';

        return $curl_param;
    }
    /**
     * 获取 es 最近一年 2000 的排名
     * @return [type] [description]
     */
    public function iadTagsRank(Request $request)
    {

        $dsp_info = DB::table('dsp_info')->get();

        foreach ($dsp_info as $key => $value) {
            $r_t = DB::table('rank_test')->where('rank','like','%'.$value->old.'%')->first();
            $type = empty($r_t)?0:1;

            DB::table('dsp_info')->where('id',$value->id)->update(['type'=>$type]);
        }
        // dd($dsp_info);

        // 1 获取 对应 excel 表格的数据
        

        //2 对比 rank_test rank 字段的值
        // 过滤出不存在 rank 里面的 excel 数据 
        


        //3 交给小马 爬取excel 过滤的数据

        // $curl_param = [
        //     'size'=>0,
        //     'query'=>[
        //         'filtered'=>[
        //             'filter'=>[
        //                 'range'=>[
        //                     'date5'=>[
        //                         'gte'=>'2016-12-01 00:00:00'
        //                     ]
        //                 ]
        //             ]
        //         ]
        //     ],
        //     'aggs'=>[
        //         'tags'=>[
        //             'terms'=>[
        //                 'field'=>'tag_list',
        //                 'size'=>2000
        //             ]
        //         ]
        //     ]
        // ];

        // $url = config('param.ES_URL').config('param.RANKROLE_SEARCH').'/'.config('param.ES_SEARCH');

        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $rs_parse = json_decode($rs,true);

        // $aggs = empty($rs_parse['aggregations']['tags']['buckets'])?'':$rs_parse['aggregations']['tags']['buckets'];

        // if(empty($aggs))dd('暂无数据');

        // $tags_list = [];
        // foreach ($aggs as $key => $value) {
        //     $item['rank'] = $value['key'];
        //     $tags_list[] = $item;
        // }

        // DB::table('rank_test')->insert($tags_list);
    }
      /**
     * 根据 编号 获取  addata 信息
     */
    public function getAllById(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $es = ESUtil::getInfoById(config('param.ADDATA_SEARCH6'),$id);

        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $es = DBUtil::getBodyWH($es);

        $data['data'] = $es;
        $data['status'] = empty($es)?203:200;
        return HttpUtil::retrun_json($data,200);
    }

    public function getInfoInIds(Request $request)
    {
        $ids = $request->ids;
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
        $ids = explode(',', $ids);
        //参数格式不对
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        foreach ($ids as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }
        if(count($ids)>100)return HttpUtil::retrun_json(['data'=>[],'status'=>207,'smg'=>'请求id 参数个数过多'],200);
        $curl_param = [
            'size'=>count($ids),
            'sort'=>DSLUtil::sortByTime('date5','desc'),
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        'terms'=>[
                            'id'=>$ids
                        ]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = 200;
        // $data['total'] = empty($es_data)?'':count($es_data);
        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 获取对应的域名 
     * @return [type] [description]
     */
    public function iadDomainHost(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;

        $curl_param = [
            'query'=>[
                'term'=>[
                    'md5'=>$md5
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data_inner = DBUtil::get_parse_es_data($rs);

        return HttpUtil::retrun_json($data_inner,200);
    }

    public function iadHostAggs(Request $request)
    {
        $host = $request->host;

        $host_arr = explode('_H_',$host);

        $new_data = [];

        foreach ($host_arr as $key => $value) {
             $curl_param = [
                'size'=>0,
                'query'=>[
                    'term'=>[
                        'advertiser_na'=>$value
                    ]
                ],
                'aggs'=>[
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ]
                ]
            ];
            // dd(json_encode($curl_param));
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $rs_parse = json_decode($rs,true);

            $ads = empty($rs_parse['aggregations']['ads']['value'])?0:$rs_parse['aggregations']['ads']['value'];

            $item['host'] = $value;
            $item['ads'] = $ads;
            $new_data[] = $item;
        }

        $data['data'] = $new_data;
        $data['status'] = 200;
        if(empty($data['data']))return HttpUtil::returnStatus(207);
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取 媒体 app 广告联盟 最多创意的信息
     * type 1 媒体 2 app  3  广告联盟
     * size 返回条数 默认 5 条
     * @return [type] [description]
     */
    public function iadIndexData(Request $request)
    {
        $type = empty($request->type)?'':$request->type;
        $size = empty($request->size)?5:$request->size;
        $area = empty($request->area)?'上海':$request->area;

        if($type==1)
        {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'term'=>[
                                'p_p_area'=>$area
                            ]
                        ],
                        'must_not'=>[
                            'term'=>[
                                'p_a_area'=>''
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'domain'=>[
                        'terms'=>[
                            'field'=>'publisher_na'
                        ]
                    ]
                ]
            ];

        }

        if($type==2)
        {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'range'=>[
                                'platform'=>[
                                    'gt'=>1
                                ]
                            ]
                        ],
                        'should'=>[
                            [
                                'term'=>[
                                    'p_p_area'=>$area
                                ]
                            ],
                            [
                                'term'=>[
                                    'a_p_area'=>$area
                                ]
                            ],
                            [
                                'term'=>[
                                    't_p_area'=>$area
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'domain'=>[
                        'terms'=>[
                            'field'=>'publisher_na'
                        ]
                    ]
                ]
            ];
        }

        if($type==3)
        {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'platform'=>[
                                        'gt'=>1
                                    ]
                                ]
                            ]
                        ],
                        'should'=>[
                            [
                                'term'=>[
                                    'p_p_area'=>$area
                                ]
                            ],
                            [
                                'term'=>[
                                    'a_p_area'=>$area
                                ]
                            ],
                            [
                                'term'=>[
                                    't_p_area'=>$area
                                ]
                            ]
                        ],
                        'must_not'=>[
                            'term'=>[
                                'field'=>'tracker_list'
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'domain'=>[
                        'terms'=>[
                            'field'=>'tracker_list'
                        ]
                    ]
                ]
            ];
        }

        //1 获取对应的域名

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        $aggs = empty($rs_parse['aggregations']['domain']['buckets'])?'':$rs_parse['aggregations']['domain']['buckets'];

        if(empty($aggs))return HttpUtil::returnStatus(207);

        $domain_tmp = [];

        foreach ($aggs as $key => $value) {
            if(!empty($value['key']) && count($domain_tmp) < 5)
            {
                $domain_tmp[] = $value['key'];
            }
        }

        $term = '';

        if($type==1 || $type==2)
        {
            $term = 'publisher_na';
        }else{
            $term = 'tracker_list';
        }

        $data_return = [];


        // 2 获取域名对应的最新一条创意
        foreach ($domain_tmp as $key => $value) {
            
            $curl_param_domain = [
                'size'=>1,
                'sort'=>[
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    $term=>$value
                                ]
                            ]
                        ],
                        'should'=>[
                            [
                                'term'=>[
                                    'p_p_area'=>$area
                                ]
                            ],
                            [
                                'term'=>[
                                    'a_p_area'=>$area
                                ]
                            ],
                            [
                                'term'=>[
                                    't_p_area'=>$area
                                ]
                            ]
                        ],
                    ]
                ]
            ];

            // dd(json_encode($curl_param_domain));

            $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param_domain,"POST");

            $data_inner = DBUtil::get_parse_es_data($rs);

            if(!empty($data_inner['data'][0]))$data_return[$value] = $data_inner['data'][0];
            
        }

        $data['data'] = $data_return;
        $data['status'] = 200;
        if(empty($data['data']))return HttpUtil::returnStatus(207);

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 广告投放量
     * @return [type] [description]
     */
    public function iadAdStatis(Request $request)
    {
        $type = empty($request->type)?2:$request->type;

        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;

        $area = empty($request->area)?'上海':$request->area;


        $date_range = '';

        $range_field = '';

        switch ($type) {
            case '1':
                # code...
                $date_range = DBUtil::diffBetweenTwoDays($start_time,$end_time);
                $range_field = 'date_day';
                break;
            case '2':
                $date_range = DBUtil::get_weeks($start_time,$end_time);
                $range_field = 'date_day';
                break;
            case '3':
                # code...
                $date_range = DBUtil::get_months($start_time,$end_time);
                $range_field = 'date_day';
                break;
        }

        $size = count($date_range);

        if($type==1 && $size>15)dd('当前天数过多,请重新设置');

        if($type==2 && $size>7)dd('当前周数过多,请重新设置');

        if($type==3 && $size>12)dd('当前月数过多,请重新设置');

        // dd($date_range);
        $data_range = [];
        foreach ($date_range as $key => $value) {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    $range_field=>[
                                        'gte'=>$value['start_time'],
                                        'lte'=>$value['end_time']
                                    ]
                                ]
                            ],
                            [
                                'term'=>[
                                    'p_p_area'=>$area
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ],
                    'publisher'=>[
                        'cardinality'=>[
                            'field'=>'publisher_na'
                        ]
                    ],
                    'trackers'=>[
                        'cardinality'=>[
                            'field'=>'tracker_list'
                        ]
                    ]
                ]
            ];

            // dd(json_encode($curl_param));

            $url = config('param.ES_URL').config('param.ADDATA_AGG_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $rs_parse = json_decode($rs,true);

            $item['time'] = $value['start_time']==$value['end_time']?$value['start_time']:$value['start_time'].'-'.$value['end_time'];

            $item['ads'] = empty($rs_parse['aggregations']['ads']['value'])?0:$rs_parse['aggregations']['ads']['value'];
            $item['publisher'] = empty($rs_parse['aggregations']['publisher']['value'])?0:($rs_parse['aggregations']['publisher']['value']);


            $item['trackers'] = empty($rs_parse['aggregations']['trackers']['value'])?0:($rs_parse['aggregations']['trackers']['value']);

            $data_range[] = $item;
        }
        //获取 最大的值
        $max_num = 0;

        $time_range = [];
        $publisher_range = [];
        $trackers_range = [];
        $ads_range = [];

        foreach ($data_range as $key => $value) {
            $max = max($value['ads'],$value['publisher'],$value['trackers']);

            if($max>$max_num)$max_num = $max;

            $time_range[] = $value['time'];
            $ads_range[] = $value['ads'];
            $trackers_range[] = $value['trackers'];
            $publisher_range[] = $value['publisher'];
        }

        $data['publisher_range'] = $publisher_range;
        $data['ads_range'] = $ads_range;
        $data['trackers_range'] = $trackers_range;
        $data['time_range'] = $time_range;
        $data['max_num'] = $max_num;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

    public function iadAdTypeStatis(Request $request)
    {
        $type = empty($request->type)?2:$request->type;

        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;

        $area = empty($request->area)?'上海':$request->area;

        // dd($date_range);
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>$start_time.' 00:00:00',
                                    'lte'=>$end_time.' 23:59:59'
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                'p_p_area'=>$area
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'type'=>[
                    'terms'=>[
                        'field'=>'type',
                        'size'=>0
                    ]
                ],
                'material'=>[
                    'terms'=>[
                        'field'=>'material',
                        'size'=>0
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        $type_range = empty($rs_parse['aggregations']['type']['buckets'])?'':$rs_parse['aggregations']['type']['buckets'];

        $material_range = empty($rs_parse['aggregations']['material']['buckets'])?'':$rs_parse['aggregations']['material']['buckets'];

        $item['视频'] = 0;
        $item['flash'] = 0;
        $item['图片'] = 0;
        $item['H5'] = 0;
        $item['material'] = 0;
        
        if(!empty($type_range))
        {
            
            foreach ($type_range as $t_k => $t_v) {
                switch ($t_v['key']) {
                    case 'swf':
                        $item['视频'] = $t_v['doc_count'];
                        break;
                    case 'flv':
                        $item['flash'] = $t_v['doc_count'];
                        break;
                    case 'html5':
                        $item['H5'] = $t_v['doc_count'];
                        break;
                    case 'image':
                        $item['图片'] = $t_v['doc_count'];
                        break;
                }
            }
        }

        if(!empty($material_range))
        {
            foreach ($material_range as $mk => $mv) {
                # code...
                if(!empty($mv['key']) && $mv['key']=='native')
                {
                    $item['material'] = $mv['doc_count'];
                    break;
                }
            }
        }


        $data_new = [];
        foreach ($item as $key => $value) {
           $data_new[] = $value;
        }
        //获取 最大的值
        $max_num = $max = max($item['视频'],$item['flash'],$item['H5'],$item['图片'],$item['material']);;

       
        $data['data'] = $data_new;
        $data['max_num'] = $max_num;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    public function iadAdPlatformStatis(Request $request)
    {
        $type = empty($request->type)?2:$request->type;

        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;

        $area = empty($request->area)?'上海':$request->area;


        // dd($date_range);
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>$start_time.' 00:00:00',
                                    'lte'=>$end_time.' 23:59:59'
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                'a_p_area'=>$area
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'platform'=>[
                    'terms'=>[
                        'field'=>'platform',
                        'size'=>0
                    ]
                ],
                'attribute04'=>[
                    'terms'=>[
                        'field'=>'attribute04',
                        'size'=>0
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        $attribute04 = empty($rs_parse['aggregations']['attribute04']['buckets'])?'':$rs_parse['aggregations']['attribute04']['buckets'];

        $platform = empty($rs_parse['aggregations']['platform']['buckets'])?'':$rs_parse['aggregations']['platform']['buckets'];

        $item['PC端'] = 0;
        $item['IOS'] = 0;
        $item['Android'] = 0;
        
        if(!empty($attribute04))
        {
            
            foreach ($attribute04 as $t_k => $t_v) {
                switch ($t_v['key']) {
                    case 'ios':
                        $item['IOS'] = $t_v['doc_count'];
                        break;
                    case 'android':
                        $item['Android'] = $t_v['doc_count'];
                        break;
                }
            }
        }

        if(!empty($platform))
        {
            foreach ($platform as $mk => $mv) {

                if(!empty($mv['key']) && $mv['key']==1)
                {
                    $item['PC端'] = $mv['doc_count'];
                    break;
                }
            }
        }

        $data_new = [
            [
                'value'=>$item['PC端'],
                'name'=>'PC端'
            ],
            [
                'value'=>$item['IOS'],
                'name'=>'IOS'
            ],
            [
                'value'=>$item['Android'],
                'name'=>'Andriod'
            ]
        ];

        //获取 最大的值
        $max_num = $item['IOS'] + $item['Android'] + $item['PC端'];

       
        $data['data'] = $data_new;
        $data['max_num'] = $max_num;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 地域排名 默认根据 媒体
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function iadAreaRankStatis(Request $request)
    {
        $area = empty($request->area)?'上海':$request->area;

        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            'p_p_area'=>$area
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'p_a_area'=>''
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'area'=>[
                    'terms'=>[
                        'field'=>'p_c_area',
                        'size'=>0
                    ],
                    'aggs'=>[
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ],
                        'publisher'=>[
                            'cardinality'=>[
                                'field'=>'publisher_na'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        if(empty($rs_parse['aggregations']['area']['buckets']))return HttpUtil::returnStatus(207);


        $rs_area = $rs_parse['aggregations']['area']['buckets'];

        $rs_area_data = [];
        foreach ($rs_area as $key => $value) {
            if(!empty($value['key']))
            {
                $item['ads'] = empty($value['ads']['value'])?0:$value['ads']['value'];
                $item['publisher'] = empty($value['publisher']['value'])?0:$value['publisher']['value'];
                $item['area'] = $value['key'];
                $rs_area_data[] = $item;
            }
        }
        $data['data'] = $rs_area_data;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 根据地域查询相关媒体
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getPublishersByMap(Request $request)
    {
        $area = empty($request->area)?'上海':$request->area;
        $district = empty($request->district)?'':$request->district;

        if(!empty($district)){
            $must = [
                'term'=>[
                    'p_p_area'=> $area
                ],
                'term'=>[
                    'p_c_area'=> $district
                ]
            ];
        }else{
            $must = [
                'term'=>[
                    'p_p_area'=> $area
                ]
            ];
        }

        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>$must,
                    'must_not'=>[
                        'term'=>[
                            'p_a_area'=>''
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'area'=>[
                    'terms'=>[
                        'field'=>'publisher_na',
                        'size'=> 0
                    ],
                    'aggs'=>[
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        if(empty($rs_parse['aggregations']['area']['buckets']))return HttpUtil::returnStatus(207);

        $rs_area = $rs_parse['aggregations']['area']['buckets'];

        $rs_area_data = [];
        foreach ($rs_area as $key => $value) {
            if(!empty($value['key']))
            {
                $item['ads'] = empty($value['ads']['value'])?0:$value['ads']['value'];
                $item['publisher'] = $value['key'];
                $rs_area_data[] = $item;
            }
        }

        $data['data'] = $rs_area_data;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据域名 或者 营销活动的 md5 的值 获取相关创意的数据 
     * @return [type] [description]
     */
    public function iadRelatedAdsInfo(Request $request)
    {
        $param = empty($request->md5)?'':$request->md5;
        $type = empty($request->type)?'':$request->type;
        $size = empty($request->size)?config('param.SIZE'):$request->size;


        if(empty($type) || empty($param))return HttpUtil::returnStatus(206);

        // dd($request->all());

        $term = '';
        switch ($type) {
            case '1':
                # code...
                $term = 'publisher_na';
                 break;
            case '2':
                # code...
                $term = 'advertiser_na';
                 break;
            case '3':
                # code...
                $term = 'tracker_list';
                 break;
            case '4':
                # code...
                $term = 'subject_md5';
                break;
        }

        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'term'=>[
                    $term=>$param
                ]
            ]
        ];

         // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::returnStatus(207);

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 编号 获取 domain 信息
     * 2017-6-2 修改 判断 是否是跟踪者 二级域名
     * @return [type] [description]
     */
    public function iadDomainInfoByMd5(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;
        if(empty($md5))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        $es = ESUtil::getInfoByMd5(config('param.DOMAINS'),$md5);


        //判断是否是 跟踪者 二级以上域名
        if(!empty($es[0]->flag) && $es[0]->flag==1)
        {
            $host = $es[0]->host;
            // 获取对应的广告主 媒体 跟踪者 营销活动 对应的值
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'term'=>[
                        'tags_list'=>$host
                    ]
                ],
                'aggs'=>[
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ],
                    'advertiser'=>[
                        'cardinality'=>[
                            'field'=>'advertiser_na'
                        ]
                    ],
                    'publiser'=>[
                        'cardinality'=>[
                            'field'=>'publisher_na'
                        ]
                    ],
                    'subject'=>[
                        'cardinality'=>[
                            'field'=>'subject_md5'
                        ]
                    ]
                ]
            ];


            $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
            $rs_d = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $rs_data = json_decode($rs_d,true);

            $es[0]->tracker_ads = $rs_data['aggregations']['ads']['value'];
            $es[0]->tracker_advertiser = $rs_data['aggregations']['advertiser']['value'];
            $es[0]->tracker_publishers = $rs_data['aggregations']['publiser']['value'];
            $es[0]->tracker_subjects = $rs_data['aggregations']['subject']['value'];
        }

        $data = DBUtil::pareseESDate($es,1);

        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 场景还原
     * wd 编号 或者 md5 的值
     * type 1 编号 2 md5 的值
     * @return [type] [description]
     */
    public function iadSceneRestore(Request $request)
    {
        $type = empty($request->type)?'':$request->type;

        $param = empty($request->wd)?'':$request->wd;


        $term = $type==1?'id':($type==2?'md5':'');

        if(empty($term) || empty($param))return HttpUtil::returnStatus(['data'=>[],'status'=>207],200);


        $curl_param = [
            'query'=>[
                'term'=>[
                    $term=>$param
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::returnStatus(['data'=>[],'status'=>'207'],200);

        foreach ($data['data'] as $key => &$value) {
            # code...
            $value->attribute07 = $this->getAdsAttr07($value->id);
        }

        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 根据 创意编号 获取对应的 att07 属性
     * @return [type] [description]
     */
    private function getAdsAttr07($id)
    {
        $curl_param = [
            'query'=>[
                'term'=>[
                    'id'=>$id
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_ATTR_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        return empty($data['data'][0])?'':$data['data'][0];
    }

    private static function replaceSpecialChar($strParam){
        $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\/|\"|\;|\'|\`|\-|\=|\\\|\|/";
        return preg_replace($regex,"",$strParam);
    }

    /**
     * 详细 广告主 媒体 跟踪者 点击广告投放 接口
     *
     * click_role 点击 tab 接口  1 广告媒体 2 广告主 3 投放联盟 4 广告投放 5 创意
     *
     * cur_role 当前 角色 1 媒体 2 广告主 3 跟踪者 4 营销活动
     * cur_value 当前角色对应的值 
     * sort 默认排序 DESC 
     * size 显示条数
     * 
     * @return [type] [description]
     */
    public function iadInnerSubjectOrAds(Request $request)
    {

        $key_cache = md5(implode('', $request->all()));
        // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        // {
        //     return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        // }


        $click_role = empty($request->click_role)?'':$request->click_role;
        $cur_role = empty($request->cur_role)?'':$request->cur_role;

        //是否点击 当前广告主 媒体 跟踪者 过来
        $t_role = empty($request->t_r)?'':$request->t_r;// 角色
        $t_value = empty($request->t_v)?'':$request->t_v; // 域名 


        $cur_value = empty($request->cur_value)?'':$request->cur_value;
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort_desc = empty($request->sort)?'DESC':$request->sort;


        // 详情过来的相关字段查询
        $old_qu_field = '';
        switch ($t_role) {
            case '1':
                $old_qu_field = 'publisher_na';
                break;
            case '2':
                $old_qu_field = 'advertiser_na';
                break;
            case '3':
                $old_qu_field = 'tracker_list';
                break;
            case '4':
                $old_qu_field = 'subject_md5';
                # code...
                break;
        }

        // 待查询字段
        $qu_field = '';
        $qu_cl_field = '';
        $order_by = '';
        switch ($cur_role) {
            case '1':
                $qu_field = 'publisher_na';
                break;
            case '2':
                $qu_field = 'advertiser_na';
                break;
            case '3':
                $qu_field = 'tracker_list';
                break;
            case '4':
                $qu_field = 'subject_md5';
                # code...
                break;
        }
        switch ($click_role) {
            case '1':
                $qu_cl_field = 'publisher_na';
                $order_by = 'id';
                break;
            case '2':
                $qu_cl_field = 'advertiser_na';
                $order_by = 'subject_md5';
                break;
            case '3':
                $qu_cl_field = 'tracker_list';
                $order_by = 'advertiser_na';
                break;
            case '4':
                $qu_cl_field = 'subject_md5';
                $order_by = 'created_date';
                # code...
                break;
            case '5':
                $qu_cl_field = 'id';
                $order_by = 'created_date';
                break;
        }

        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $qu_field=>$cur_value
                            ]
                        ]
                    ],
                    'must_not'=>[
                        [
                            'term'=>[
                                'publisher_na'=>''
                            ]
                        ],
                        [
                            'term'=>[
                                'subject_md5'=>''
                            ]
                        ],
                        [
                            'term'=>[
                                'advertiser_na'=>''
                            ]
                        ],
                        [
                            'term'=>[
                                'tracker_list'=>''
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                $qu_cl_field=>[
                    'terms'=>[
                        'field'=>$qu_cl_field,
                        'size'=>$size//,
                        // 'order'=>[
                        //     'order_desc'=>$sort_desc
                        // ]
                    ],
                    'aggs'=>[
                        
                    ]
                ]
            ]
        ];


        if(!empty($old_qu_field))
        {
            array_push($curl_param['query']['bool']['must'], [
                    'term'=>[
                        $old_qu_field=>$t_value
                    ]
                ]);
        }

        if($click_role==4)
        {
            $curl_param['aggs'][$qu_cl_field]['aggs'] = [
                'order_desc'=>[
                    'max'=>[
                        'field'=>$order_by
                    ]
                ],
                'ads'=>[
                    'cardinality'=>[
                        'field'=>'id'
                    ]
                ],
                'last_time'=>[
                    'max'=>[
                        'field'=>'created_date'
                    ]
                ],
                'start_time'=>[
                    'min'=>[
                        'field'=>'created_date'
                    ]
                ]
            ];
        }else{
            $curl_param['aggs'][$qu_cl_field]['aggs'] = [
                'order_desc'=>[
                    'cardinality'=>[
                        'field'=>$order_by
                    ]
                ],
                'subject'=>[
                    'cardinality'=>[
                        'field'=>'subject_md5'
                    ]
                ],
                'ads'=>[
                    'cardinality'=>[
                        'field'=>'id'
                    ]
                ]
            ];
        }
        // dd(json_encode($curl_param));
        
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        $rs_data = empty($rs_parse['aggregations'][$qu_cl_field]['buckets'])?'':$rs_parse['aggregations'][$qu_cl_field]['buckets'];

        if(empty($rs_data))return HttpUtil::returnStatus(['data'=>[],'status'=>207],200);

        // 获取 对应的统计 字段的key
        $rs_data_key = [];
        foreach ($rs_data as $key => $value) {
            $item[$qu_cl_field] = $value['key'];
            $item['ads'] = empty($value['ads']['value'])?0:$value['ads']['value'];
            $item['subject'] = empty($value['subject']['value'])?0:$value['subject']['value'];
            $item['last_time'] = 0;
            $item['start_time'] = 0;
            if($cur_role == 4)
            {
                // 营销活动
                $item['last_time'] = empty($value['last_time']['value'])?0:$value['last_time']['value'];
                $item['start_time'] = empty($value['start_time']['value'])?0:$value['start_time']['value'];
            }
            $rs_data_key[] = $item;
        }


        // dd($rs_data_key);
        // 角色 为 营销活动 情况 下
        if($cur_role==4)
        {
            // 创意
            if($click_role==5)
            {
                // 获取 营销活动基本信息
                foreach ($rs_data_key as $skey => &$svalue) {
                    $svalue['info'] = $this->getAdsById($svalue[$qu_cl_field]);
                }
            }else
            {
                foreach ($rs_data_key as $skey => &$svalue) {
                    $svalue['info'] = $this->getDomainInfoByHost($svalue[$qu_cl_field],2);
                }
                // 广告主 媒体 或者 跟踪者
            }

            $data['data'] = $rs_data_key;
            $data['status'] = 200;

            return HttpUtil::retrun_json($data,200);
        }else{
            // 广告主 媒体 跟踪者 信息
            //点击 广告投放
            if($click_role==4)
            {
                foreach ($rs_data_key as $dkey => &$dvalue) {
                    $subject_info = $this->getSubjectByMd5($dvalue[$qu_cl_field]);
                    // $dvalue['info'] = $this->getSubjectByMd5($dvalue[$qu_cl_field]);

                    $dvalue['info'] = $subject_info;

                    $dvalue['title'] = $subject_info->title;
                    $dvalue['start_time'] = $subject_info->first_detected;
                    $dvalue['last_time'] =$this->getSubjectLastTime($dvalue[$qu_cl_field]);
                }
            }else{

                // dd($rs_data_key);
                foreach ($rs_data_key as $dkey => &$dvalue) {
                    $new_host = $dvalue[$qu_cl_field];
                    if(preg_match("/[\'., :;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$new_host))
                    {
                        $new_host = self::replaceSpecialChar($new_host);
                        $dvalue[$qu_cl_field] = $new_host;
                    }

                    $dvalue['info'] = $this->getDomainInfoByHost($new_host,2);
                }
            }
        }

        

        $data['data'] = $rs_data_key;
        $data['status'] = 200;

        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));

        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 根据 字段 获取 创意基本信息
     * type 1 获取一条数据  2 获取 多条数据
     * ids 对应的创意编号
     * @return [type] [description]
     */
    public function getAdInfoById(Request $request)
    {
        $type = empty($request->type)?'':$request->type;
        $ids = empty($request->id)?'':$request->id;

        if(empty($ids) || empty($type))return HttpUtil::returnStatus(207);


        $term = $type==1?'term':'terms';

        $curl_param = [
            'query'=>[
                $term=>[
                    'id'=>$ids
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $ad_data = DBUtil::get_parse_es_data($rs);

        return HttpUtil::retrun_json($ad_data,200);

    }
    /**
     * 首页 创意展现量排序
     * 根据 addata_logs ads 日志 数
     * type 1 根据广告主 2 根据媒体 地域查询
     * @return [type] [description]
     */
    public function iadAdsTop(Request $request)
    {
        $size = empty($request->size)?config('param.IAD_SIZE'):$request->size;

        $type = empty($request->type)?1:$request->type;

        $times = DBUtil::getOneWeekNew();

        $term_area = $type==1?'a_p_area':'p_p_area';


        // $curl_param_d = [
        //     'sort'=>[
        //         'brand_ads'=>[
        //             'order'=>'desc'
        //         ]
        //     ],
        //     'query'=>[
        //         'filtered'=>[
        //             'filter'=>[
        //                 'term'=>[
        //                     'parent_area'=>'上海'
        //                 ]
        //             ]
        //         ]
        //     ],
        //     '_source'=>[
        //         'id','host'
        //     ]
        // ];

        // $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // dd(json_encode($curl_param_d));


        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'date'=>[
                                    'gte'=>$times['start_time'].config('param._START_TIME'),
                                    'lte'=>$times['end_time'].config('param._END_TIME')
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                $term_area=>'上海'
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'ads'=>[
                    'terms'=>[
                        'field'=>'md5',
                        'size'=>$size
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
       


        // dd(json_encode($curl_param));

        // 第一步 获取 对应logs 最多的 创意
        $url = config('param.ES_URL').config('param.ADDATA_AGG_SEARCH').'/'.config('param.ES_SEARCH');
        //$url = config('param.ES_URL').'addata_aggs_v3/addata_index/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        $rs_data = empty($rs_parse['aggregations']['ads']['buckets'])?[]:$rs_parse['aggregations']['ads']['buckets'];



        if(!$rs_data)return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        $mds_arr = [];

        foreach ($rs_data as $key => $value) {
            $mds_arr[] = $value['key'];
        }



        $ads_data = $this->getAdsInfoByMd5($mds_arr);

        $data = $this->getadvertiserDomain($ads_data);

        return HttpUtil::retrun_json($data,200);
    }
   

    /**
     * 首页 广告展现量排序
     * @return [type] [description]
     */
    public function iadIdsTop(Request $request)
    {

    }
    /**
     * 定时 获取 最近数据 给 iad 杭州
     * 
     * 2017-8-14
     * @author  finn <[<fyj@adbug.cn>]> 
     */
    public function iadCrondData(Request $request)
    {
        $size = empty($request->size)?500:$request->size;

        $size = $size>5000?5000:$size;

        $last_id = empty($request->last_id)?'':$request->last_id;

        // 广告链接地址 暂无
        // 广告发布页面截图 暂无
        // http://file.adbug.cn/datasync/domian/thumb_url 素材地址
        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'id'=>[
                    'order'=>'asc'
                ]
            ],
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        'range'=>[
                            'id'=>[
                                'gt'=>$last_id
                            ]
                        ]
                    ]
                ]
            ],
            '_source'=>[
                'id','advertiser_na','publisher_na','tracker_na',
                'title','platform','type','created_date','shape',
                'attribute04','width','height','size','x','y','attribute08',
                'target_url','thumb_width','thumb_height','original_url',
                'thumb_url','share_url','domain','attribute08','thumbnail'
            ]
        ];

        if(empty($last_id))unset($curl_param['query']);


        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);


        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        // $domain
        foreach ($data['data'] as $key => &$value) {
            # code...

            $value->advertiser_id = empty($value->advertiser_na)?0:$this->getDomainInfo($value->advertiser_na);
            $value->publisher_id = empty($value->publisher_na)?0:$this->getDomainInfo($value->publisher_na);

            $value->old_file_url = $value->attribute08;

            $tac_arr_id = [];

            $tracker_na = $value->tracker_na;

            $tac_arr = explode(';', $tracker_na);

            if(count($tac_arr)>1)
            {
                
                foreach ($tac_arr as $tk => $tv) {
                    $item['host'] = $tv;
                    $item['id'] = $this->getDomainInfo($tv);
                    $tac_arr_id[] = $item;
                }
            }else{
                $item['host'] = $tracker_na;
                $item['id'] = $this->getDomainInfo($tracker_na);
                $tac_arr_id[] = $item;
            }
            $value->trackers_info = $tac_arr_id;

            $file_rp_url = 'http://file.adbug.cn/datasync/'.$value->domain.'/'.$value->thumb_url;
            
            $file_rp_url = Tools::replaceFileUrl($file_rp_url);
            $value->file_url = str_replace('file.adbug.cn', 'file.adlife.com.cn', $file_rp_url);


            $domain = $value->domain;
            $thumb_url = $value->thumb_url;
            $thumbnail = $value->thumbnail;

            $type = $value->type;

            $share_url = $value->share_url;
            $src = '';
            if($type != "image"){
                $src = Tools::getFileUrl($domain).$domain."/share/".$thumbnail;
            }else{
                $src = Tools::getFileUrl($domain).$domain."/".$thumb_url;
            }
            if($type == "swf"){
                $src = Tools::getFileUrl($domain).$domain."/share/".$share_url;
            }
            if($type=='html5')
            {
                $src = $value->attribute08;
            }
            $src = Tools::replaceFileUrl($src);
            $value->thumb_url_true = $src;

            //$value->file_url = 'http://file.adbug.cn/datasync/'.$value->domain.'/share/'.$value->share_url;
            $value->old_page_url = '';
            // 排除 没有用到的字段
            unset($value->domain);
            unset($value->attribute08);
        }

        //file.adlife.com.cn
        // foreach ($data['data'] as $ak => $av) {
        //     $ad = Addata::find($av->id);
        //     $av->old_file_url = $ad->attribute08;
        // }
        // dd($data);
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 域名 或者 对应的编号
     * @return [type] [description]
     */
    private function getDomainInfo($host)
    {
        if(empty($host))return 0;
        
        $curl_param = [
            'size'=>1,
            'query'=>[
                'term'=>[
                    'host_no'=>$host
                ]
            ],
            '_source'=>[
                'id','host_no'
            ]
        ];

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs_ad_domain = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $domain_arr = DBUtil::get_parse_es_data($rs_ad_domain);

        return empty($domain_arr['data'][0])?0:$domain_arr['data'][0]->id;

    }
    /**
     * 获取 domain  列表
     * @return [type] [description]
     */
    public function iadDomainList(Request $request)
    {
        $size = empty($request->size)?config('param.IAD_SIZE'):$request->size;
        $page = empty($request->page)?0:$request->size;

        $from = $page * $size;

        $curl_param = [
            'size'=>$size,
            'from'=>$from
        ];

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs_ad_domain = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $doamin_info = DBUtil::get_parse_es_data($rs_ad_domain);

        return HttpUtil::retrun_json($doamin_info,200);
    }
    /**
     * 热点 广告
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function iadHotList(Request $request)
    {
        
    }
    /**
     * 首页 默认展示 接口
     * size 最大值 300
     * @return [type] [description]
     */
    public function iadAdsList(Request $request)
    {
        $size = empty($request->size)?config('param.IAD_SIZE'):$request->size;

        if($size>300)$size = 300;

        $page = empty($request->page)?config('param.IAD_PAGE'):$request->page;

        $sort = empty($request->sort)?config('param.IAD_SORT'):$request->sort;

        $this->_from = $page>1?$size*$page:0;


        $curl_param = DSLUtil::baseDSLSortQuery($this->_from,$size,$sort);

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);


        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        return HttpUtil::retrun_json($data,200);


    }
    /**
     * 导出 功能
     * 根据 传递 参数 来决定
     * 等待开发
     * @return [type] [description]
     */
    public function iadExportData(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;
        $tab = empty($request->tab)?'':$request->tab;

        if(empty($tab) || empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);


        $search_index = $tab==5?config('param.ADDATA_SEARCH6'):config('param.SUBJECT_SEARCH6');

        $ids_tmp = explode('-', $ids);


        $curl_param = [
            'size'=>count($ids_tmp),
            'query'=>[
                'terms'=>[
                    'id'=>$ids_tmp
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').$search_index.'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        return HttpUtil::retrun_json($data,200);
    }

    public function iadExportKaiData(Request $request)
    {

        $key_cache = md5(implode('', $request->all()));
        
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $type = empty($request->type)?'':$request->type;
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);
        $oldparam = urldecode($param);
        // 2016-12-08 finn 判断是否是点击 campagin 1 是 默认不是
        $isclick = empty($request->isclik)?'':$request->isclik;
        $suid = empty($request->suid)?'':$request->suid;
        // 2016-11-30 新增  finn 判断是否 点击 营销活动
        $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;
        $page = empty($request->page)?1:$request->page;

        if($size>1000)$size = 1000;

        $from = 0;
        if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(($type && !is_numeric($type)))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        if($page>1)$from = $size * $page;

       
        $en_ch = empty($request->en_ch)?'':$request->en_ch;
        $synonym= '';


        if(empty($en_ch))$en_ch = DBUtil::check_str_cn_en($param);
        
        if(empty($iscampaign) || $en_ch==1)$synonym = $this->getSynonym($en_ch,$param);

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param.' '.$c_host;

        // $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));
        // 默认
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>2,
            'query'=>[
                'function_score'=>[
                    'filter'=>[
                        'bool'=>[
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title',
                                        'publisher_full'
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ]//,
                            // 'must'=>[
                            //     [
                            //         'range'=>[
                            //             'date5'=>[
                            //                 'gte'=>$min_date
                            //             ]
                            //         ]
                            //     ]
                            // ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'sum',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort(),
            '_source'=>[
                'id','domain','title','advertiser_na','publisher_na','target_url',
                'original_url','date5','platform'
            ]
        ];
        // 营销活动
        $curl_param_compan = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>1,
            'query'=>[
                'function_score'=>[
                    'filter'=>[
                        'bool'=>[
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title',
                                        'publisher_full'
                                        //,
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ]//,
                            // 'must'=>[
                            //     [
                            //         'range'=>[
                            //             'date5'=>[
                            //                 'gte'=>$min_date
                            //             ]
                            //         ]
                            //     ]
                            // ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'max',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort(),
            '_source'=>[
                'id','domain','title','advertiser_na','publisher_na','target_url',
                'original_url','date5','platform','created_date'
            ]
        ];

        if($iscampaign==1)$curl_param = $curl_param_compan;


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }
        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);


        if(in_array($oldparam, config('param.WHITE_MIN_SCORE')))
        {
            $curl_param['min_score'] = 0;
        }

        // dd(json_encode($curl_param));

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // dd($data);
        if(empty($data['data']) || !count($data['data'])) $data = DBUtil::resetGetData($curl_param,config('param.ADDATA_SEARCH6'));


        // $data['data'] = DBUtil::my_sort($data['data'],'created_date',SORT_DESC,SORT_NUMERIC );
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }



        $cellData = [
            ['名称','广告主','媒体','平台','初始页','落地页','时间','创意链接']
        ];

        $new_data = [];

        foreach ($data['data'] as $key => $value) {
            $platform = 'PC';

            switch ($value->platform) {
                case '1':
                    $platform = 'PC';
                    break;
                case '2':
                    $platform = '移动';
                    break;
                case '4':
                    $platform = 'OTT';
                    break;
            }
            $item['title'] = $value->title;
            $item['advertiser_na'] = $value->advertiser_na;
            $item['publisher_na'] = $value->publisher_na;
            $item['platform'] = $platform;
            $item['original_url'] = $value->original_url;
            $item['target_url'] = $value->target_url;
            $item['date'] = $value->date5;
            $item['ads_url'] = 'http://iad.adlife.com.cn/index/adUrl?id='.$value->id;

            $new_data[] = $item;
            // array_push($cellData, $item);
        }

        $data_sort = DBUtil::my_sort($new_data,'date',SORT_DESC,SORT_NUMERIC);

        // dd($data_sort);


        foreach ($data_sort as $key => $value) {
            $items['title'] = $value['title'];
            $items['advertiser_na'] = $value['advertiser_na'];
            $items['publisher_na'] = $value['publisher_na'];
            $items['platform'] = $value['platform'];
            $items['original_url'] = $value['original_url'];
            $items['target_url'] = $value['target_url'];
            $items['date'] = $value['date'];
            $items['ads_url'] = $value['ads_url'];
            array_push($cellData, $items);
        }

        Tools::exportData($param.' 详细',$cellData);

        // Excel::create($param.' 详细',function($excel) use ($cellData){
        //             $excel->sheet('score', function($sheet) use ($cellData){
        //                 $sheet->rows($cellData);
        //             });
        //     })->export();

        dd('id ok');
        // 获取 attr08 属性 finn 2017-4-17 解决  html5 问题
        // $data = $this->getAtt08($data);

        // $data['status'] = 200;
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        // return HttpUtil::retrun_json($data,200);
    }

    /**
     * 按照广告主域名 完全匹配查询
     * wd 广告主域名 size 条数 最大 1000
     * page 分页
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function iadExportKaiAdverData(Request $request)
    {
        $key_cache = md5(implode('', $request->all()));
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->wd)?'':$request->wd;
        $page = empty($request->page)?1:$request->page;

        if($size>1000)$size = 1000;
        $from = 0;
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        if($page>1)$from = $size * $page;

        // 默认
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            'advertiser_na'=>$param
                        ]
                    ]
                ]
            ],
            'sort'=>DSLUtil::baseIdSort()
        ];
        // dd(json_encode($curl_param));

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);
        // dd($data);
        if(empty($data['data']) || !count($data['data'])) $data = DBUtil::resetGetData($curl_param,config('param.ADDATA_SEARCH6'));
        // $data['data'] = DBUtil::my_sort($data['data'],'created_date',SORT_DESC,SORT_NUMERIC );
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $cellData = [
            ['名称','广告主','媒体','类型','是否原生广告','形状','宽','高',
            '平台','初始页','落地页','素材地址','抓取时间','最后出现时间','创意链接']
        ];
        $new_data = [];
        foreach ($data['data'] as $key => $value) {
            $platform = 'PC';

            switch ($value->platform) {
                case '1':
                    $platform = 'PC';
                    break;
                case '2':
                    $platform = '移动';
                    break;
                case '4':
                    $platform = 'OTT';
                    break;
            }
            $item['title'] = $value->title;
            $item['advertiser_na'] = $value->advertiser_na;
            $item['publisher_na'] = $value->publisher_na;
            $item['type'] = $value->type;
            $material = empty($value->material)?'':$value->material;
            $item['material'] = ($material=='native'?'是':'否');
            $shape = $value->shape;
            $item['shape'] = '';

            switch ($shape) {
                case '1':
                    $item['shape'] = '竖幅';
                    break;
                case '2':
                    # code...
                    $item['shape'] = '正方形';
                    break;
                case '3':
                    # code...
                    $item['shape'] = '小横幅';
                    break;
                case '4':
                    # code...
                    $item['shape'] = '中横幅';
                    break;
                case '5':
                    # code...
                    $item['shape'] = '大横幅';
                    break;
            }

            $item['width'] = $value->width;
            $item['height'] = $value->height;
            $item['platform'] = $platform;
            $item['original_url'] = $value->original_url;
            $item['target_url'] = $value->target_url;

            $domain = $value->domain;
            $thumb_url = $value->thumb_url;
            $thumbnail = $value->thumbnail;

            $type = $value->type;

            $share_url = $value->share_url;
            $src = '';
            if($type != "image"){
                $src = Tools::getFileUrl($domain).$domain."/share/".$thumbnail;
            }else{
                $src = Tools::getFileUrl($domain).$domain."/".$thumb_url;
            }
            if($type == "swf"){
                $src = Tools::getFileUrl($domain).$domain."/share/".$share_url;
            }
            if($type=='html5')
            {
                $src = $value->attribute08;
            }
            $src = Tools::replaceFileUrl($src);
            $item['src'] = $src;

            $item['date'] = $value->date5;

            if(!empty($value->last_seen))
            {
                $item['last_seen'] = date('Y-m-d H:i:s',$value->last_seen/1000);
            }else{
                $item['last_seen'] = '';
            }
            $item['ads_url'] = 'http://iad.adlife.com.cn/index/adUrl?id='.$value->id;
            array_push($cellData, $item);
            // array_push($cellData, $item);
        }
        Tools::exportData($param.' 详细',$cellData);
    }

    public function getDomain($url) { 
        $host = strtolower ( $url ); 
        if (strpos ( $host, '/' ) !== false) { 
            $parse = @parse_url ( $host ); 
            $host = $parse ['host']; 
        } 
        $topleveldomaindb = array ('com', 'edu', 'gov', 'int', 'mil', 'net', 'org', 'biz', 'info', 'pro', 'name', 'museum', 'coop', 'aero', 'xxx', 'idv', 'mobi', 'cc', 'me' ); 
        $str = ''; 
        foreach ( $topleveldomaindb as $v ) { 
            $str .= ($str ? '|' : '') . $v; 
        }
        $matchstr = "[^\.]+\.(?:(" . $str . ")|\w{2}|((" . $str . ")\.\w{2}))$"; 
        if (preg_match ( "/" . $matchstr . "/ies", $host, $matchs )) { 
            $domain = $matchs ['0']; 
        } else { 
            $domain = $host; 
        } 
        return $domain; 
    } 
    /**
     * 临时提高导出数据
     * 杭州 2018-3-30
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function iadExportKaiAdverTmpData(Request $request)
    {


        $cellData = [
            ['ads_id','publisherna','advertiserna','advertisername','allianceid','alliancena','attribute04','title','decodeid','shareurl','oldfileurl','fileurl','thumburl','thumb_url_true','thumbwidth','thumbheight','originalurl','oldpageurl','targeturl','targetimgurl','shape','types','height','width','x','y','createddate','attribute','forensic_code','ads_classify','ads_classify_code','brand','website','illegal_content','illegal_clause','illegal_clause_codes','spokesman','snapshot','opinion','websitename','id_publisher','alexaweek_publisher','company_publisher','domain_publisher','area_publisher','platform_publisher','website_ip_publisher','website_ip_address_publisher','websiterecord_publisher','domain_registrar_publisher','industry_publisher','unitname_publisher','fregionid_publisher','faddress_publisher','flinkman_publisher','ftel_publisher','fmail_publisher','registration_code_publisher','registration_office_publisher','operation_scope_publisher','tadowner_websiterecord_publisher','organization_name_publisher','website_address_publisher','id','alexaweek','company','domain','area','platform','website_ip','website_ip_address','websiterecord','domain_registrar','industry','unitname','fregionid','faddress','flinkman','ftel','fmail','registration_code','registration_office','operation_scope','tadowner_websiterecord','organization_name','website_address']
        ];
        $urls = DB::connection('mysql_topmain_true')->table('url20180330')->get();
        $size = 5000;
      
        $ads_data = [];

        foreach ($urls as $key => $value) {
            $advertiserna = $value->host;

            $item['ads_id'] = '';
            $item['publisherna'] = '';
            $item['advertiserna'] = $advertiserna;
            $item['advertisername'] = '';



            $item['allianceid'] = '';//广告联盟 id
            $item['alliancena'] = '';// 广告联盟

            $item['attribute04'] = '';
            $item['title'] = '';
            $item['decodeid'] = '';//广告指纹
            $item['shareurl'] = '';//文件名
            $item['oldfileurl'] = '';//原始素材地址
            $item['fileurl'] = '';//原始素材存储地址
            $item['thumburl'] = '';//缩略素材名称
            $item['thumb_url_true'] = '';//缩略素材名称全地址
            $item['thumbwidth'] = '';
            $item['thumbheight'] = '';
            $item['originalurl'] = '';//广告原始地址
            $item['oldpageurl'] = '';//原始页面截图
            $item['targeturl'] = '';//落地页地址
            $item['targetimgurl'] = '';//落地页快照地址
            $item['shape'] = '';

            $item['types'] = '';
            $item['height'] = '';
            $item['width'] = '';
            $item['x'] = '';
            $item['y'] = '';
            $item['createddate'] = '';//广告发布时间
            $item['attribute'] = '';
            $item['forensic_code'] = '';//取证码


            $item['ads_classify'] = '';
            $item['ads_classify_code'] = '';
            $item['brand'] = '';//品牌
            $item['website'] = '';//网站
            $item['illegal_content'] = '';//违法内容
            $item['illegal_clause'] = '';//违规判定法律条款，&分隔
            $item['illegal_clause_codes'] = '';
            $item['spokesman'] = '';//代言人
            $item['snapshot'] = '';//广告快照地址
            $item['opinion'] = '';//专家建议
            $item['websitename'] = '';//网站媒介名


            //媒体
            $item['id_publisher'] = '';//媒体网站id
            $item['alexaweek_publisher'] = '';//媒体网站alexa周排名
            $item['company_publisher'] = '';//媒体网站网站名
            $item['domain_publisher'] = '';//媒体网站网站域名
            $item['area_publisher'] = '';//媒体网站地区
            $item['platform_publisher'] = '';//媒体网站平台 1PC 2移动
            $item['website_ip_publisher'] = '';//媒体网站网站ip
            $item['website_ip_address_publisher'] = '';//媒体网站网站地址
            $item['websiterecord_publisher'] = '';//媒体网站ICP备案号
            $item['domain_registrar_publisher'] = '';//媒体网站域名注册商
            $item['industry_publisher'] = '';//媒体网站行业（媒介类型）
            $item['unitname_publisher'] = '';//媒体网站广告主机构名称unitna
            $item['fregionid_publisher'] = '';//媒体网站行政区划
            $item['faddress_publisher'] = '';//媒体网站地址
            $item['flinkman_publisher'] = '';//媒体网站联系人
            $item['ftel_publisher'] = '';//媒体网站电话
            $item['fmail_publisher'] = '';//媒体网站电子邮件
            $item['registration_code_publisher'] = '';//媒体网站注册号
            $item['registration_office_publisher'] = '';//媒体网站登记机关
            $item['operation_scope_publisher'] = '';//媒体网站经营范围
            $item['tadowner_websiterecord_publisher'] = '';//媒体网站备案号
            $item['organization_name_publisher'] = '';//媒体网站机构名称
            $item['website_address_publisher'] = '';//媒体网站网站地址

            //广告主
            $item['id'] = '';
            $item['alexaweek'] = '';
            $item['company'] = '';
            $item['domain'] = '';
            $item['registration_code'] = '';
            $item['alexaweek'] = '';
            $item['company'] = '';
            $item['domain'] = '';
            $item['area'] = '';
            $item['platform'] = '';
            $item['website_ip'] = '';
            $item['website_ip_address'] = '';
            $item['websiterecord'] = '';
            $item['domain_registrar'] = '';
            $item['industry'] = '';
            $item['unitname'] = '';
            $item['fregionid'] = '';
            $item['faddress'] = '';
            $item['flinkman'] = '';
            $item['ftel'] = '';
            $item['fmail'] = '';
            $item['registration_code'] = '';
            $item['registration_office'] = '';
            $item['operation_scope'] = '';
            $item['tadowner_websiterecord'] = '';
            $item['organization_name'] = '';
            $item['website_address'] = '';

            $curl_param = [
                'size'=>1,
                'query'=>[
                    'term'=>[
                        'host_no'=>$advertiserna
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
            $domain_data = DBUtil::get_parse_es_data($rs);

            if(empty($domain_data['data']))
            {
                array_push($cellData, $item);
                continue;
            }else{


                //广告主
                $item['domain'] = $domain_data['data'][0]->host;
                $platform = $domain_data['data'][0]->platform;
                if(!empty($platform))
                {
                    $platform = $platform ==1?'pc':'移动';

                }
                $item['platform'] = $platform;
            }
            $item['advertisername'] = $domain_data['data'][0]->cname;

             // 默认
            $curl_param = [
                'size'=>$size,
                'sort'=>[
                    'created_date'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    'advertiser_na'=>$advertiserna
                                ]
                            ],
                            [
                                'range'=>[
                                    'date5'=>[
                                        'gte'=>'2017-01-01 00:00:00'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);
            $data = DBUtil::get_parse_es_data($rs);


            if(empty($data['data']))
            {
                array_push($cellData, $item);
                continue;
            }

            $file_path = 'http://file.adbug.cn/m/';


            foreach ($data['data'] as $k => $v) {
                # code...
                $item['ads_id'] = $v->id;
                $item['publisherna'] = $v->publisher_na;
                $item['alliancena'] = $v->tracker_na;

                $item['attribute04'] = $v->attribute04;
                $item['title'] = $v->title;
                $item['shareurl'] = $file_path.$v->type.'/'.$v->am_url;//文件名
                $item['oldfileurl'] = $file_path.$v->type.'/'.$v->am_source_url;//原始素材地址
                $item['fileurl'] = $file_path.$v->type.'/'.$v->am_source_url;//原始素材存储地址
                $item['thumbwidth'] = $v->width;
                $item['thumbheight'] = $v->height;
                $item['originalurl'] = $file_path.$v->type.'/'.$v->am_source_url;//广告原始地址
                $item['oldpageurl'] = $file_path.$v->type.'/'.$v->am_url;//原始页面截图
                $item['targeturl'] = $v->target_url;//落地页地址

                $shape = $v->shape;
                switch ($shape) {
                    case '1':
                        $item['shape'] = '竖幅';
                        break;
                    case '2':
                        # code...
                        $item['shape'] = '正方形';
                        break;
                    case '3':
                        # code...
                        $item['shape'] = '小横幅';
                        break;
                    case '4':
                        # code...
                        $item['shape'] = '中横幅';
                        break;
                    case '5':
                        # code...
                        $item['shape'] = '大横幅';
                        break;
                }
                $item['types'] = $v->type;
                $item['height'] = $v->height;
                $item['width'] = $v->width;
                $item['x'] = $v->x;
                $item['y'] = $v->y;
                $item['createddate'] = $v->created_date/1000;//广告发布时间
                array_push($cellData, $item);
            }
        }
        Tools::exportData('最新广告详细',$cellData);
        
    }
    /**
     * 根据 条件 获取 数据
     * type 1 视频 2 flash 3 图片 4 h5 5 原生
     * platform 1 pc 2 移动 3 iOS 4 android
     * area 地域 对应 SH_AREA 的数组的索引
     * c_r 针推 wd 更加详细的搜索 对应的角色
     * c_v 角色对应的域名
     * @return [type] [description]
     */
    public function iadAllFilter(Request $request)
    {
        // SH_AREA

        $param = empty($request->wd)?'':$request->wd;

        $oldparam = $param;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $from = empty($request->from)?0:$request->from;

        $type = empty($request->type)?'':$request->type;
        $platform = empty($request->platform)?'':$request->platform;
        $area = empty($request->area)?'':$request->area;
        $c_r = empty($request->c_r)?'':$request->c_r;
        $c_v = empty($request->c_v)?'':$request->c_v;

        $t_type = '';

        // $type = 
        $attribute04 = ''; // Android IOS
        $material = '';//原生字段 native
        if($type)
        {
            switch ($type) {
                case '1':
                    $type = 'swf';
                    
                    break;
                case '2':
                    $type = 'flv';
                    break;
                case '3':
                    $type = 'image';
                    break;
                case '4':
                    $type = 'html5';
                    break;
                case '5':
                    $material = 'native';
                    break;
            }
        }

        if($platform>2)$attribute04 = ($platform==3?'ios':'android');

            // 验证参数格式
        $valiedParam = [];


        if($attribute04)$valiedParam['attribute04'] = $attribute04;

        if($type>4 && $material)
        {
            $valiedParam['material'] = $material;
        }else if($type){
            $valiedParam['type'] = $type;
        }

        $t_c_field = $c_r==1?'publisher_na':($c_r==2?'advertiser_na':'tracker_list');
        $t_area = '';
        if(empty($area))
        {
            switch ($c_r) {
                case '1':
                    $t_area = 'p_p_area';
                    break;
                case '2':
                    $t_area = 'a_p_area';
                    break;
                case '3':
                    $t_area = 't_p_area';
                    break;
            }
        }else{
            switch ($c_r) {
                case '1':
                    $t_area = 'p_c_area';
                    break;
                case '2':
                    $t_area = 'a_c_area';
                    break;
                case '3':
                    $t_area = 't_c_area';
                    break;
            }
        }

        // dd(config('param.SH_AREA'));


        if(!empty($c_r) && !empty($area))
        {
            $valiedParam[$t_area] = empty($area)?config('param.IAD_AREA'):$area;
        }
        

        $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;



        if(!empty($c_v))$valiedParam[$t_c_field] = $c_v;

        $en_ch = DBUtil::check_str_cn_en($oldparam);

        $synonym= '';
        if(empty($iscampaign))$synonym = $this->getSynonym($en_ch,$param);

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];
        
        $param = empty($c_param)?$param:$c_param;

        // $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));
        // 默认
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>2,
            'query'=>[
                'function_score'=>[
                    'filter'=>[
                        'bool'=>[
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title'
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ],
                            'must'=>[
                                // 'term'=>[
                                //     'p_p_area'=>$area
                                // ]
                            ],
                            'must_not'=>[
                                // 'term'=>[
                                //     'p_a_area'=>''
                                // ]
                            ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'sum',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort()
        ];
        // 营销活动
        $curl_param_compan = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>1,
            'query'=>[
                'function_score'=>[
                    'query'=>[
                        'bool'=>[
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title'//,
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ],
                            'must'=>[
                                // 'term'=>[
                                //     'p_p_area'=>$area
                                // ]
                            ],
                            'must_not'=>[
                                // 'term'=>[
                                //     'p_a_area'=>''
                                // ]
                            ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'max',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort()
        ];

        if($iscampaign==1)$curl_param = $curl_param_compan;


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }



        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);

        if(!empty($valiedParam))
        {

            if(!empty($area))
            {
                if($iscampaign==1)
                {
                 
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], [

                                'term'=>[
                                    'p_p_area'=>$area
                                ]
                            ]);
                }else{
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], [
                                'term'=>[
                                    'p_p_area'=>$area
                                ]
                            ]);
                }
            }
            
            
            foreach ($valiedParam as $v_k => $v) {
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], [

                            'term'=>[
                                $v_k=>$v
                            ]
                        ]);
                }else{
                     array_push($curl_param['query']['function_score']['filter']['bool']['must'], [

                            'term'=>[
                                $v_k=>$v
                            ]
                        ]);
                }
                
            }
        }

        // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        // dd(json_encode($curl_param));
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        $data = $this->getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        // 获取 attr08 属性 finn 2017-4-17 解决  html5 问题
        // $data = $this->getAtt08($data);

        $data['status'] = 200;
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 获取 所有营销活动
     * md5 营销活动 md5 的值
     * @return [type] [description]
     */
    public function iadAllSubject(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;

        $curl_param = [
            'query'=>[
                'term'=>[

                ]
            ]
        ];
    }
    /**
     * 获取 营销活动 详细 信息
     * @return [type] [description]
     */
    public function iadSubjectInfo(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;

        // 是否点击 域名 等详细页面过来 为aggs 过滤
        $t_v = empty($request->t_v)?'':$request->t_v;
        $t_r = empty($request->t_r)?'':$request->t_r;


        $curl_param = [
            'query'=>[
                'term'=>[
                    'md5'=>$md5
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.SUBJECT_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        // 获取 营销活动 最后 出现的时间 和对应的 广告主 信息
        

        foreach ($data['data'] as $key => &$value) {

            $value->last_detected = $this->getSubjectLastTime($md5);
            $value->advertiser_info = $this->getHostInfo($value->advertiser);
        }
        // 统计 广告主 媒体 跟踪者 等信息
        $data['aggs'] =$this->getCardinality(5,$md5,$t_r,$t_v);

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 MD5 的值 获取 对应的subject 基本信息
     * @param  [type] $md5 [description]
     * @return [type]      [description]
     */
    private function getSubjectByMd5($md5)
    {
        $curl_param = [
            'query'=>[
                'term'=>[
                    'md5'=>$md5
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.SUBJECT_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        //获取营销活动对应的第一个创意的am_url am_source_url
        //信息
        $curl_param = [
            'size'=>1,
            'query'=>[
                'term'=>[
                    'subject_md5'=>$md5
                ]
            ],
            '_source'=>[
                'am_url',
                'am_source_url',
                'id'
            ]
        ];

        $url_ad = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs_ad = HttpUtil::send_curl_methods($url_ad,$curl_param,"POST");
        $data_ad = DBUtil::get_parse_es_data($rs_ad);
        foreach ($data['data'] as $k => &$v) {
            $v->am_url = empty($data_ad['data'][0]->am_url)?'':$data_ad['data'][0]->am_url;
            $v->am_source_url = empty($data_ad['data'][0]->am_url)?'':$data_ad['data'][0]->am_source_url;
        }
        return $data['data'][0];
    }
    /**
     * 根据 创意 编号 查询基本信息
     * @param  [type] $md5 [description]
     * @return [type]      [description]
     */
    private function getAdsById($id)
    {
        $curl_param = [
            'query'=>[
                'term'=>[
                    'id'=>$id
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        return $data['data'][0];
    }


    private function getSubjectLastTime($md5)
    {
        $curl_param = [
            'size'=>0,
            'query'=>[
                'term'=>[
                    'subject_md5'=>$md5
                ]
            ],
            'aggs'=>[
                'last_time'=>[
                    'max'=>[
                        'field'=>'created_date'
                    ]
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        return empty($rs_parse['aggregations']['last_time']['value'])?'':$rs_parse['aggregations']['last_time']['value'];

    }
    /**
     * 统计 广告主 媒体 广告联盟 创意数
     * type 根据什么来统计 1 统计 媒体对应的其他字段 2 广告主 3 跟踪者 4 创意 5 营销活动 
     * role 过滤 角色　host 过滤对应角色的域名
     * @return [type] [description]
     */
    private function getCardinality($type,$param,$role,$host)
    {

        $role_t_field = '';
        switch ($role) {
            case '1':
                $role_t_field = 'publisher_na';
                break;
            case '2':
                $role_t_field = 'advertiser_na';
                break;
            case '3':
                $role_t_field = 'tracker_list';
                break;
            case '4':
                $role_t_field = 'id';
                break;
            case '5':
                $role_t_field = 'subject_md5';
                break;
        }



        $card_filed = '';
        switch ($type) {
            case '1':
                $card_filed = 'publisher_na';
                break;
            case '2':
                $card_filed = 'advertiser_na';
                break;
            case '3':
                $card_filed = 'tracker_list';
                break;
            case '4':
                $card_filed = 'id';
                break;
            case '5':
                $card_filed = 'subject_md5';
                break;
        }

        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $card_filed=>$param
                            ]
                        ]
                    ],
                    'must_not'=>[
                        [
                            'term'=>[
                                'publisher_na'=>''
                            ]
                        ],
                        [
                            'term'=>[
                                'subject_md5'=>''
                            ]
                        ],
                        [
                            'term'=>[
                                'advertiser_na'=>''
                            ]
                        ],
                        [
                            'term'=>[
                                'tracker_list'=>''
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'ads'=>[
                    'cardinality'=>[
                        'field'=>'id'
                    ]
                ],
                'publisher'=>[
                    'cardinality'=>[
                        'field'=>'publisher_na'
                    ]
                ],
                'subject'=>[
                    'cardinality'=>[
                        'field'=>'subject_md5'
                    ]
                ],
                'advertiser'=>[
                    'cardinality'=>[
                        'field'=>'advertiser_na'
                    ]
                ],
                'tracker'=>[
                    'cardinality'=>[
                        'field'=>'tracker_list'
                    ]
                ]
            ]
        ];


        if(!empty($role))
        {
            array_push($curl_param['query']['bool']['must'], [
                    'term'=>[
                        $role_t_field=>$host
                    ]
                ]);
        }

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        $data['ads'] = empty($rs_parse['aggregations']['ads']['value'])?0:$rs_parse['aggregations']['ads']['value'];
        $data['advertiser'] = empty($rs_parse['aggregations']['advertiser']['value'])?0:$rs_parse['aggregations']['advertiser']['value'];
        $data['tracker'] = empty($rs_parse['aggregations']['tracker']['value'])?0:$rs_parse['aggregations']['tracker']['value'];
        $data['publisher'] = empty($rs_parse['aggregations']['publisher']['value'])?0:$rs_parse['aggregations']['publisher']['value'];
        $data['subject'] = empty($rs_parse['aggregations']['subject']['value'])?0:$rs_parse['aggregations']['subject']['value'];

        return $data;
    }
    /**
     * 
     * 获取 域名对应的信息
     * ID 域名对应的编号
     * @return [type]     [description]
     */
    private function getHostInfo($id)
    {
         $curl_param = [
            'query'=>[
                'term'=>[
                    'id'=>$id
                ]
            ]
        ];
        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        return empty($data['data'][0])?[]:$data['data'][0];
    }
    
    /**
     * 自动搜素结果
     *  host 搜索关键词 
     *  is_en_ch 1 英文 2 中文 3 中英混合
     *  size 搜索返回条数 默认 10条数据
     *  orderBy 按照 什么字段排序
     *  role 指明 角色
     * @return [type] [description]
     */
    public function iadAutoSeach(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;

        $role = empty($request->role)?'':$request->role;

        $area = empty($request->area)?'上海':$request->area;

        // 1成纯英文 2 中文 3 中英混合
        $is_en_ch = empty($request->is_en_ch)?'':$request->is_en_ch;
        // 字段 排序
        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        // 判断是否含有 redis 缓存
        
        $data = [];

        if($is_en_ch==1)
        {
            // 2017-6-2 改成 sql 查看
            $sql = 'SELECT * FROM domain where parent_area ="'.$area.'" AND ( host like "%'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%")';

            if(!empty($role)) $sql = $sql.' AND role="'.$role.'"';

            $sql = $sql.' limit '.$size;

            // $sql = 'SELECT * FROM domain where host like "%'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%" limit '.$size;


            if($orderBy)$sql=$sql.' '.$orderBy.' desc';


            dd($sql);


            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

            $data = DBUtil::get_parse_es_data($rs);

            if(empty($data['data']))
            {
                $param_num = explode(' ', $param);

                if(count($param_num)>1)
                {
                    $param = str_replace(' ', '', $param);

                    $param = strtolower($param);

                    // $sql = 'SELECT * FROM domain where host like "%'.$param.'%" limit '.$size;

                    $sql = 'SELECT * FROM domain where parent_area ="'.$area.'" AND (host like "%'.$param.'%")';

                    if(!empty($role))$sql = $sql.' AND role="'.$role.'"';



                    if($orderBy)$sql=$sql.' '.$orderBy.' desc';


                    $sql = $sql.' limit '.$size;

                    // dd($sql);
                    $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                    $data = DBUtil::get_parse_es_data($rs);
                }

            }

        }else{
            $curl_param ='';
            if(empty($role))
            {
                 $curl_param = DSLUtil::domainDLSQueryParse($size,$param,0,0,'must',50,'cname');
            }else{
                $curl_param = DSLUtil::domainDLSQueryParseRole($size,$param,0,0,'must',50,'cname',$role);
            }
           
            if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            $data = DBUtil::get_parse_es_data($rs);
        }
        
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        if(!empty($data['data']))
        {
            $curl_param_do = [
                'size'=>1,
                'sort'=>[
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'should'=>[]
                    ]
                ]
            ];


            foreach ($data['data'] as $key => &$value) {
                $item = [
                    'term'=>[
                        'domain_host'=>$value->host
                    ]
                ];
                $item_domain = [
                    'term'=>[
                        'domain'=>$value->host
                    ]
                ];
                array_push($curl_param_do['query']['bool']['should'],$item);
                array_push($curl_param_do['query']['bool']['should'],$item_domain);
                $curl_param_do['_source'] = config('param.ADDATA_FILED_SOURCE');

                $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH6'),$curl_param_do,'');

                $data_do = DBUtil::get_parse_es_data($rs_do);
                $value->attr04 = empty($data_do['data'])?'':$data_do['data'][0]->attribute04;
                // $value->platform = empty($data_do['data'])?'':$data_do['data'][0]->platform;
            }
        }
        $data['status'] = 200;
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 自动纠错 功能
     * finn 目前只针对 英文 中文 后续 研究
     * 
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function iadDomainAutoCompletion(Request $request)
    {
        $param = empty($request->param)?'':$request->param;

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        $curl_param_auto = [
            'size'=>0,
            'suggest'=>[
                'my_suggest'=>[
                    'text'=>$param,
                    'completion'=>[
                        'field'=>'host',
                        'fuzzy'=>[
                            'fuzziness'=>1
                        ]
                    ]
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search_type(config('param.DOMAIN_AUTO'),$curl_param_auto,'');

        $rs_auto = json_decode($rs,true);


        if(empty($rs_auto['suggest']['my_suggest'][0]['options']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $rs_auto_data = $rs_auto['suggest']['my_suggest'][0]['options'];

        $rs_auto_host = [];

        foreach ($rs_auto_data as $rk => $rv) {
            $rs_auto_host[] = $rv['text'];
        }
        $curl_param_host = [
            'size'=>count($rs_auto_host),
            'query'=>[
                'terms'=>[
                    'host_no'=>$rs_auto_host
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param_host,'');
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = empty($data['data'])?203:200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取 对应的 domain 信息
     * type 查询类型 1 md5 2 host 默认 MD5
     * host 查询 参数
     * @return [type] [description]
     */
    public function iadDomainInfo(Request $request)
    {
        $type = empty($request->type)?1:$request->type;

        $param = empty($request->host)?'':$request->host;

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $field = $type==1?'md5':'host_no';

        $curl_param = [
            'query'=>[
                'term'=>[
                    $field=>$param
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);


        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

        $data['aggs'] = $this->getCardinality($data['data'][0]->role,$data['data'][0]->host,'','');

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 host 获取 对应的域名 基本信息
     * @param  [type] $host [description]
     * @return [type]       [description]
     */
    private function getDomainInfoByHost($param,$type)
    {
        $field = $type==1?'md5':'host_no';

        $curl_param = [
            'query'=>[
                'term'=>[
                    $field=>$param
                ]
            ]
        ];
        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        return empty($data['data'][0])?[]:$data['data'][0];
    }
    /**
     * 获取 投放量 排名
     * role 1 媒体 2 广告主 3 跟踪者 4 创意 5 营销活动
     * time_type 1 当天 2 一周 3 当月
     * size 统计 获取 排名 条数
     * @return [type] [description]
     */
    public function iadRankTop(Request $request)
    {

        $role = empty($request->role)?'2':$request->role;

        $size = empty($request->size)?'10':$request->size;

        $time_type = empty($request->time_type)?'3':$request->time_type;

        // 新增 area  地域
        $area = empty($request->area)?'':$request->area;
        $platform = empty($request->platform)?'':$request->platform;


        // dd($area);

        $wd = empty($request->wd)?'':$request->wd;


        if(empty($role))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $current_date = date('Y-m-d',time());  // 当天 时间
        $weekLater = date('Y-m-d',strtotime("$current_date - 1 week"));   // 一周前

        $mouthLater = date('Y-m-d',strtotime("$current_date - 2 week"));   // 半个月前

        $one_mouth = date('Y-m-d',strtotime("$current_date - 1 month"));
        $two_mouth = date('Y-m-d',strtotime("$current_date - 2 month"));


        $before_day = date("Y-m-d",strtotime("-1 day"));//一天前

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
                $role_field = 'tag_list';//tracker_list
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


        $current_data = $this->getBeforeRankList($role,$role_field,$start_time,$end_time,$size,$area,$platform,$wd);


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

        $last_data = $this->getBeforeRankList($role,$role_field,$start_time,$end_time,$size,$area,$platform,$wd);

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


        // dd($data['data']);
        // 计算 最大的score的值
        $max_score = 0;
        foreach ($data['data'] as $sk => $sv) {
            if($sv['score']>=$max_score)$max_score = intval($sv['score']);
        }
        // 统计每个数据排放量指数的值 的变化
        
        foreach ($data['data'] as $sk => &$sv) {
            $sv['score_rate'] = $max_score?intval(($sv['score']/$max_score)*100):0;
        }
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 获取对应的排名详细
     * @param  [type] $role       [description]
     * @param  [type] $role_field [description]
     * @param  [type] $start_time [description]
     * @param  [type] $end_time   [description]
     * @param  [type] $role_size  [description]
     * @return [type]             [description]
     * area //目前只针对广告主
     */
    private function getBeforeRankList($role,$role_field,$start_time,$end_time,$role_size,$area,$platform,$wd)
    {
        $valiedParam = [];
        if(empty($area))
        {
            $valiedParam['a_p_area'] = '上海';
        }else{
            //$valiedParam['a_p_area'] = '上海';

            if(empty(config('param.SH_AREA')[$area]))
            {
                $valiedParam['a_p_area'] = $area;
            }else{
                $valiedParam['a_c_area'] = config('param.SH_AREA')[$area];
            }
            
        }

        if(!empty($platform))
        {
            if($platform==1)$valiedParam['platform'] = 1;
            if($platform==2)$valiedParam['attribute04'] = 'IOS';
            if($platform==3)$valiedParam['attribute04'] = 'Android';
        }

        //创意
        if($role==4)
        {
            $curl_param = [
                'size'=>300,
                'sort'=>[
                    'score'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'created_date'=>[
                                        'gte'=>$start_time,
                                        'lte'=>$end_time
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            if(!empty($valiedParam))
            {

                foreach ($valiedParam as $vk => $vv) {
                    array_push($curl_param['query']['bool']['must'], [
                        'term'=>[
                            $vk=>$vv
                        ]
                    ]);
                }
                
            }




            $url = config('param.ES_URL').config('param.RANKROLE_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $rs_parse = json_decode($rs,true);

            if(empty($rs_parse['hits']['hits']))return [];

            $hits = $rs_parse['hits']['hits'];

            $host_domain = [];

            $ads_tmp_md5 = [];
            foreach ($hits as $key => $value) {
                if(count($host_domain)>=200)break;

                if(empty($value['_source']['advertiser']) && empty($value['_source']['title']))continue;

                $ads_md5 = md5($value['_source']['advertiser'].$value['_source']['title']);

                if(!in_array($ads_md5, $ads_tmp_md5))
                {
                  
                    $item['ad_id'] = $value['_source']['ad_id'];
                    $item['host'] = $value['_source']['advertiser'];
                    $item['cname'] = $value['_source']['title'];
                    $item['score'] = $value['_source']['score'];
                    $host_domain[] = $item;
                    $ads_tmp_md5[] = $ads_md5;
                }
                
            }
            $i = 0;
            foreach ($host_domain as $key => &$value) {
                $value['id'] = $i+1;
                $value['rank'] = $i+1;
                $i++;
            }
            // dd($host_domain);
            return $host_domain;

            // dd(json_encode($curl_param));
        }
        // 营销活动
        if($role==5)
        {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'created_date'=>[
                                        'gte'=>$start_time,
                                        'lte'=>$end_time
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    $role_field=>[
                        'terms'=>[
                            'field'=>$role_field,
                            'size'=>300,
                            'order'=>[
                                'score'=>'desc'
                            ]
                        ],
                        'aggs'=>[
                            'score'=>[
                                'sum'=>[
                                    'field'=>'score'
                                ]
                            ],
                            'host'=>[
                                'terms'=>[
                                    'field'=>'advertiser_na',
                                    'size'=>1
                                ]
                            ],
                            'title'=>[
                                'terms'=>[
                                    'field'=>'title',
                                    'size'=>1
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            if(!empty($valiedParam))
            {

                foreach ($valiedParam as $vk => $vv) {
                    array_push($curl_param['query']['bool']['must'], [
                        'term'=>[
                            $vk=>$vv
                        ]
                    ]);
                }
                
            }


            $url = config('param.ES_URL').config('param.RANKROLE_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $rs_parse = json_decode($rs,true);


            if(empty($rs_parse['aggregations'][$role_field]['buckets']))
            {
                return [];
            }

            $rs_data = $rs_parse['aggregations'][$role_field]['buckets'];


            $host_domain = [];

            foreach ($rs_data as $key => $value) {


                if(count($host_domain)>=200)break;

                $host_tmp = $value['host']['buckets'];

                $title_tmp = $value['title']['buckets'];

                if(empty($host_tmp))continue;

                $is_host_null = false;

                foreach ($host_tmp as $k => $v) {
                    if(empty($v['key']))
                    {
                        $is_host_null = true;
                        break;
                    }
                }
                foreach ($title_tmp as $k => $v) {
                    if(empty($v['key']))
                    {
                        $is_host_null = true;
                        break;
                    }
                }
                if($is_host_null)continue;

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
                $value['id'] = $i+1;
                $value['rank'] = $i+1;
                $i++;
            }
            return $host_domain;
        }

        // 广告组 媒体 跟踪者
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'created_date'=>[
                                    'gte'=>$start_time,
                                    'lte'=>$end_time
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                $role_field=>[
                    'terms'=>[
                        'field'=>$role_field,
                        'size'=>$role_size,
                        'order'=>[
                            'score'=>'desc'
                        ]
                    ],
                    'aggs'=>[
                        'score'=>[
                            'sum'=>[
                                'field'=>'score'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if(!empty($valiedParam))
        {

            foreach ($valiedParam as $vk => $vv) {
                array_push($curl_param['query']['bool']['must'], [
                    'term'=>[
                        $vk=>$vv
                    ]
                ]);
            }
            
        }

        if(!empty($wd))
        {
            // advertiser
             array_push($curl_param['query']['bool']['must'], [
                    'match'=>[
                        'advertiser'=>$wd
                    ]
                ]);
        }

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.RANKROLE_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        if(empty($rs_parse['aggregations'][$role_field]['buckets']))
        {
            return [];
        }

        $rs_data = $rs_parse['aggregations'][$role_field]['buckets'];


        $host_domain = [];
        foreach ($rs_data as $key => $value) {

            if(!empty($value['key']))
            {
                if($value['key']=='com' || $value['key']=='cn')
                {
                    continue;
                }else{

                    $item['host'] = $value['key'];
                    $item['score'] = $value['score']['value'];
                    $host_domain[] = $item;
                }
                
            }
            # code...
        }

        // 如果是 跟踪者 需要特殊处理
        if($role==3)
        {
            foreach ($host_domain as $key => $value) {
                if(is_numeric($value['host']) || empty($value['host']))
                {
                    unset($host_domain[$key]);
                }
            }
        }


        foreach ($host_domain as $key => $value) {
            if(empty($value['host']))
            {
                unset($host_domain[$key]);
            }
        }

            
        // dd($host_domain);
        //广告主 过滤掉 百度
        if($role==2)
        {
            foreach ($host_domain as $key => $value) {
                if(stristr($value['host'], 'baidu'))unset($host_domain[$key]);
            }
        }
        // dd($host_domain);
        foreach ($host_domain as $key => &$value) {
            # code...
            $v_host = $value['host'];

            if(empty($v_host))unset($host_domain[$key]);


            if($role==3)
            {
                $v_host = DBUtil::parseHost($v_host);
                
            }
            $curl_param_domain = [
                'size'=>1,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    'role'=>$role
                                ]
                            ],
                            [
                                'term'=>[
                                    'host_no'=>$v_host
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // dd(json_encode($curl_param_domain));
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param_domain);
            $data = DBUtil::get_parse_es_data($rs);

            if(!empty($data['data']))
            {
                $value['cname'] = $data['data'][0]->cname;

                if($role==1)
                {
                    // $is_host_flag = false;
                    if(empty($data['data'][0]->cname) && empty($data['data'][0]->ename))
                    {
                        // $is_host_flag = true;
                        $value['cname'] = explode('.', $v_host)[0];
                    }else{
                        $value['cname'] = empty($data['data'][0]->cname)?$data['data'][0]->ename:$data['data'][0]->cname;
                    }
                    
                    $curl_param_domain_app = [
                        'size'=>0,
                        'query'=>[
                            'bool'=>[
                                'must'=>[
                                    [
                                        'range'=>[
                                            'created_date'=>[
                                                'gte'=>$start_time,
                                                'lte'=>$end_time
                                            ]
                                        ]
                                    ],
                                    [
                                        'term'=>[
                                            'publisher_na'=>$v_host
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'aggs'=>[
                            'platform'=>[
                                'terms'=>[
                                    'field'=>'platform'
                                ]
                            ],
                            'attr'=>[
                                'terms'=>[
                                    'field'=>'attribute04'
                                ]
                            ]
                        ]
                    ];

                    $curl_param_domain_app['_source'] = config('param.ADDATA_FILED_SOURCE');
                    // dd(json_encode($curl_param_domain));
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param_domain_app);
                    

                    $rs_app = json_decode($rs,true);


                    // dd($rs_app);


                    $aggs_arttr = empty($rs_app['aggregations']['attr']['buckets'])?'':$rs_app['aggregations']['attr']['buckets'];

                    $pc_info = 'PC';

                    if($aggs_arttr)
                    {
                        if(count($aggs_arttr)>=2)
                        {
                            // dd(json_encode($curl_param_domain_app));
                            $pc_info = '(iOS + Android)';
                        }else{
                            foreach ($aggs_arttr as $agg => $avv) {

                                if(strtolower($avv['key'])=='ios')
                                {
                                    $pc_info = 'iOS';
                                }else{
                                    $pc_info = ucfirst($avv['key']);
                                }
                                
                            }
                        }
                    }

                    if($v_host=='com.tencent.qqlive' || $v_host=='com.tencent.news')
                    {
                        $pc_info = 'Android';
                    }
                    $value['cname'] = empty($value['cname'])?:$value['cname'].' - '.$pc_info;
                }
                
            }else{
                if($role!=3)
                {
                    unset($host_domain[$key]);
                }else{
                   $value['cname'] = '';
                }
                // $value['cname'] = '';
               // unset($host_domain[$key]);
            }
        }

        $new_data = [];



        $i = 1;

        $is_exist_host = [];

        // dd($host_domain);
        foreach ($host_domain as $key => $value) {

            if(count(explode('.',$value['host']))>1)
            {
                $host_md5 = md5($value['cname'].$value['host']);

                if(count($new_data)<200 && !in_array($host_md5, $is_exist_host))
                {

                    $item['id'] = $i;
                    $item['rank'] = $i;
                    $item['cname'] = $value['cname'];
                    $item['score'] = $value['score'];
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
    public function iadAreaSearch(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if($size>200)$size = 200;

        $sort = empty($request->sort)?'desc':$request->sort;
        $type = empty($request->type)?'':$request->type;
        $param = empty($request->wd)?'':$request->wd;
        $page = empty($request->page)?1:$request->page;

        $from = 0;

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        if(($type && !is_numeric($type)))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        if($page>1)$from = $size * $page;

        // $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));
        // 默认
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'p_c_area'=>$param
                            ]
                        ]
                    ]
                ]
            ],
            'sort'=>DSLUtil::baseSort()
        ];
      
        // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        $data = $this->getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        // 获取 attr08 属性 finn 2017-4-17 解决  html5 问题
        // $data = $this->getAtt08($data);
        $data['status'] = 200;
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }
     /**
      * 2016-12-08
      * finn 
     * 获取 domain 所有 信息 
     * type 0 返回所有数据 1 简化数据
     * wd 搜索内容
     * en_ch 1 英文 2 中文 3 中英混合
     */
    public function iadAllSearch(Request $request)
    {
        // $key_cache = md5(implode('', $request->all()));
        // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        // {
        //     return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        // }
       
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if($size>200)$size = 200;

        $sort = empty($request->sort)?'desc':$request->sort;
        $type = empty($request->type)?'':$request->type;
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);
        $oldparam = urldecode($param);
        // 2016-12-08 finn 判断是否是点击 campagin 1 是 默认不是
        $isclick = empty($request->isclik)?'':$request->isclik;
        $suid = empty($request->suid)?'':$request->suid;
        // 2016-11-30 新增  finn 判断是否 点击 营销活动
        $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;
        $page = empty($request->page)?1:$request->page;

        $from = 0;
        if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        if(($type && !is_numeric($type)))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

       

        if($page>1)$from = $size * $page;

        
        $en_ch = DBUtil::check_str_cn_en($oldparam);

        $synonym= '';
        if(empty($iscampaign))$synonym = $this->getSynonym($en_ch,$param);

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];
        
        $param = empty($c_param)?$param:$c_param;

        // $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));
        // 默认
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>2,
            'query'=>[
                'function_score'=>[
                    'filter'=>[
                        'bool'=>[
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title'
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ]//,
                            // 'must'=>[
                            //     [
                            //         'range'=>[
                            //             'date5'=>[
                            //                 'gte'=>$min_date
                            //             ]
                            //         ]
                            //     ]
                            // ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'sum',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort()
        ];
        // 营销活动
        $curl_param_compan = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>1,
            'query'=>[
                'function_score'=>[
                    'query'=>[
                        'bool'=>[
                            'should'=>[
                                'multi_match'=>[
                                    'query'=>$param,
                                    'fields'=>[
                                        'title',
                                        'advertiser_name',
                                        'advertiser_name_title'//,
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ]//,
                            // 'must'=>[
                            //     [
                            //         'range'=>[
                            //             'date5'=>[
                            //                 'gte'=>$min_date
                            //             ]
                            //         ]
                            //     ]
                            // ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'max',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort()
        ];

        if($iscampaign==1)$curl_param = $curl_param_compan;


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }



        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);

        // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        $data = $this->getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        // 获取 attr08 属性 finn 2017-4-17 解决  html5 问题
        // $data = $this->getAtt08($data);

        $data['status'] = 200;
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 获取 中英文对比
     * @param  [type] $synonym [description]
     * @return [type]          [description]
     */
    private function getSynonym($en_ch,$param)
    {
        $synonym = '';

        if($en_ch==1)
        {
            $synonym = Synonym::where('ename',$param)->get();
        }else if($en_ch==2 || $en_ch==3)
        {
            $synonym = Synonym::where('cname',$param)->get();
            $synonym = !count($synonym)?Synonym::where('cname','like','%'.$param.'%')->orwhere('ename','like','%'.$param.'%')->limit(3)->get():$synonym;
        }
        $c_param = '';
        $c_host = '';
        if($synonym)
        {
            foreach ($synonym as $key => $value) {
                $c_param = $c_param .' '. $value['cname'].' '.$value['ename'];
                $c_host = $c_host.' '.$value['host'];
            }
        }
        
        return ['c_host'=>$c_host,'c_param'=>$c_param];
    }

    /**
     * 根据 iscampaign 添加 参数
     * @return boolean [description]
     */
    private function isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param)
    {
        if(!$iscampaign)
        {
            // $filter_range_es = ESUtil::filterESParam($param,$c_host);
            $filter_range_es = ESUtil::filterESParam3($param,$c_host,$oldparam);
            foreach ($filter_range_es as $key => $value) {
                array_push($curl_param['query']['function_score']['functions'], $value);
            }
            $curl_param['min_score']=1;
            // unset($curl_param['min_score']);
        }else if($iscampaign==1){
           
            $campain_title = [
                'filter'=>[
                    'match'=>[
                        'title'=>[
                            'query'=>$param,
                             'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'2'
            ];
            array_push($curl_param['query']['function_score']['functions'], $campain_title);
        }else if($iscampaign==2)
        {
            $curl_param = $this->compainCurl($curl_param,config('param.DOMAIN_SEARCH_HOST'),$param);
            array_push($curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'], 'publisher');
        }
        return $curl_param;
    }
    /**
     * 条件 添加 
     * curl_param 待添加 参数 domain_arr 域名 条件参数 param 参数对应的值
     * @return [type] [description]
     */
    private function compainCurl($curl_param,$domain_arr,$param)
    {
        
        foreach ($domain_arr as $key => $value) {
            $item = [
                'filter'=>[
                    'match_phrase'=>[
                        $value=>$param
                    ]
                ],
                "weight"=>'2'
            ];
            array_push($curl_param['query']['function_score']['functions'], $item);
        }
        return $curl_param;
    } 


    /**
     * 获取每条数据 对应的域名
     * @param  [type] $data [原始数据]
     * @return [type]       [description]
     */
    private function getadvertiserDomain($data)
    {
        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            
            if(!empty($value->advertiser) && !in_array($value->advertiser, $domain_tmp))
            {
                $domain_tmp[] = $value->advertiser;
            }
        }

        $curl_param = [
            'size'=>count($domain_tmp),
            'query'=>DSLUtil::baseTerms('host_no',$domain_tmp)
        ];

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs_ad_domain = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $domain_arr = DBUtil::get_parse_es_data($rs_ad_domain);

        $return_data = [];
        foreach ($data['data'] as $key => $value) {
            $return_data[] = (array)$value;
        }
        foreach ($return_data as $key => &$value) {

           if(!empty($value['advertiser']))
           {
                foreach ($domain_arr['data'] as $k => $v) {

                   if($v->host==$value['advertiser'])
                   {
                        $value['advertiser'] = $v;
                   }
               }
           }
        }
        $data['data'] = $return_data;
        return $data;
    }


     /**
     * 根据 md5 的值 获取 对应的详细
     * @return [type] [description]
     */
    private function getAdsInfoByMd5($md5)
    {

        $terms = 'term';
        $size = 1;

        if(is_array($md5))
        {
            $terms = 'terms';
            $size = count($md5);
        }
        $curl_param = [
            'size'=>$size,
            'query'=>[
                $terms=>[
                    'md5'=>$md5
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        return $data;
    }
    /**
     * 提交url 到现在正在执行的任务列表
     * @return [type] [description]
     */
    public function submitUrl(Request $request)
    {
        $url = empty($request->url)?'':$request->url;

        if(empty($url))return HttpUtil::retrun_json(['smg'=>'URL 不可为空'],200);

        
    }
    /**
     * 获取Url 对应的爬取的广告数据
     * @return [type] [description]
     */
    public function getSubmitUrl(Request $request)
    {
        $url = empty($request->url)?'':$request->url;

        if(empty($url))return HttpUtil::retrun_json(['smg'=>'URL 不可为空'],200);

        
    }
}