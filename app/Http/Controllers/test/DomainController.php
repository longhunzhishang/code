<?php

namespace App\Http\Controllers\test;

use App\Http\Controllers\TestController as Controller;


use Illuminate\Http\Request;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
USE App\Http\Utils\DBUtil;


/**
 * finn
 * 20170706
 * home test api
 */
class DomainController extends Controller
{
    
    /**
     * 测试
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getQueryInfoByField2(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;
        // 字段 排序
        $orderBy = empty($request->orderBy)?'':$request->orderBy;


        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $is_en_dian = '';

        if(DBUtil::check_str_cn_en($param)==1 && strlen($param)==4 && strchr($param,'.'))$is_en_dian = 1;

        

        if(empty($is_en_dian))
        {
            // 暂时解决 app 域名 搜索
            $app_domain = [
                'com.','cn.','tv.','ctrip.','yong.','jp.'
            ];
            foreach ($app_domain as $key => $value) {
                if(stripos($param,$value)===0)
                {
                    $is_en_dian = 1;
                    break;
                }
            }
        }
        
        

        
        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'multi_match'=>[
                            'query'=>$param,
                            'fields'=>[
                                'host','cname^10','ename'
                            ],
                            'tie_breaker'=>0.3
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'role'=>0
                        ]
                    ]
                ]
                
            ]
        ];

        $curl_param_dian = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'prefix'=>[
                                'host'=>$param
                            ]
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'role'=>0
                        ]
                    ]
                ]
                
            ]
        ];

        if($is_en_dian)$curl_param = $curl_param_dian;
        
        if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

        // dd(json_encode($curl_param));


        $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        // 记录 域名 平台
        // 2016-12-23 finn
        if(!empty($data['data']))
        {
            $domain_platform = [];
            


            $curl_param_do = [
                'size'=>1,
                'sort'=>[
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[

                        ]
                    ]
                ]
            ];

            foreach ($data['data'] as $key => &$value) {
                $item = [
                    'term'=>[
                        'domain_host'=>$value->host
                    ]
                ];

                array_push($curl_param_do['query']['bool']['must'],$item);

                $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param_do,'');

                $data_do = DBUtil::get_parse_es_data($rs_do);
                $value->attr04 = empty($data_do['data'])?'':$data_do['data'][0]->attribute04;
            }
        }

        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);

    }



    public function getAll()
    {
        return view('test.domain.all');
    }

    public function getAllGroup()
    {
        return view('test.domain.groupby');
    }
    public function getAllByHost()
    {
    	return view('test.domain.index');
    }

    //20160713
    public function getRoleFilter()
    {
        return view('test.domain.rolefilter');
    }


    public function getRoleFilterTest(Request $request)
    {
        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $role = empty($request->role)?'':$request->role;
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->wd)?'':$request->wd;

        if(empty($orderBy) || empty($role) || empty($size) || empty($param) || ($size && !is_numeric($size)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        
        if($size > config('param.MAXSIZE'))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $curl_param = [
            'size'=>$size,
            'sort'=>[
                $orderBy=>'desc'
            ],
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'match'=>[
                                'host'=>$param
                            ]
                        ],
                        [
                            'term'=>[
                                'role'=>$role
                            ]
                        ]
                    ]
                ]
               
            ]
        ];

        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
    }

    public function getInfoByMd5()
    {
        return view('test.domain.infomd5');
    }


       /**
     * 根据 编号 获取 domain 信息
     * @return [type] [description]
     */
    public function getInfoByMd5Test(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;

        if(empty($md5))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $es = ESUtil::getInfoByMd5(config('DOMAIN'),$md5);

        $data = DBUtil::pareseESDate($es,1);

        return HttpUtil::retrun_json($data,200);

    }

    public function getInfoByField()
    {
        return view('test.domain.field');
    }

    public function getInfoByFieldTest(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;

        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

         //多字段查询
        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size,
            'query'=>[
                'multi_match'=>[
                    'query'=>$param,
                    'type'=>'best_fields',
                    'fields'=>[
                        'host','cname','ename'
                    ],
                    'tie_breaker'=>0.3,
                    'minimum_should_match'=>'30%'
                ]
                
            ]
        ];

        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH').'?_source='.config('param.DOMAIN_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

    public function getInfoInHosts()
    {
        return view('test.domain.infohost');
    }

       /**
     * 根据 host 获取 数据
     * host 数组
     * @return [type] [description]
     */
    public function getInfoInHostsTest(Request $request)
    {

        $param = $request->param;

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $param = explode('_domain_id_', $param);

        $size = count($param);

        $should_arr = [];

        foreach ($param as $key => $value) {

            if($value)
            {
                $should_arr[] = [
                    'term'=>[
                        'id'=>$value
                    ]
                ];
            }
            
        }

        $curl_param = [
            'query'=>[
                'bool'=>[
                    'should'=>$should_arr
                ]
            ]
        ];

        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }


    /**
     * size 显示数目
     * sort 排序 默认 desc 
     */
    public function getAllTest(Request $request)
    {

        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($sort && !in_array($sort, ['desc','asc']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        
        if(!is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size
        ];

        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;
        

        return HttpUtil::retrun_json($data,200);
    }

    /**
     *  根据 id 获取 domain 信息
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getInfoByHost(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $host = empty($request->host)?'':$request->host;

        if(empty($host))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        if($size && !is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        if($sort && !in_array($sort, ['desc','asc','DESC','ASC']) || $size >10000)
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }

        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size,
            'query'=>[
                'term'=>[
                    'host'=>$host
                ]
            ]
        ];

        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;


        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 首页 查询 使用
     * query 
     * @return [type] [description]
     */
    public function getQueryInfoByField()
    {
        return view('test.domain.query');
    }

    /**
     * 首页 查询 使用
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function getQueryInfoByFieldTest(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        // 字段 排序
        $orderBy = empty($request->orderBy)?'':$request->orderBy;


        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

         //多字段查询
        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size,
            'query'=>[
                'multi_match'=>[
                    'query'=>$param,
                    'type'=>'best_fields',
                    'fields'=>[
                        'host','cname','ename'
                    ],
                    'tie_breaker'=>0.3,
                    'minimum_should_match'=>'30%'
                ]
                
            ]
        ];

        if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

}
//public function getQueryInfoByField(Request $request)
//{
//
//    $size = empty($request->size)?config('param.SIZE'):$request->size;
//    $param = empty($request->host)?'':$request->host;
//    $type = empty($request->type)?'':$request->type;
//    // 字段 排序
//    $orderBy = empty($request->orderBy)?'':$request->orderBy;
//
//
//    $key_cache = md5($param.$size.$type.$orderBy);
//
//    if(DBUtil::isHasRedisData($key_cache))
//    {
//        return HttpUtil::returnStatus(json_decode(Redis::get($key_cache)),200);
//    }
////        try {
////            if(Redis::get($key_cache))
////            {
////                return HttpUtil::returnStatus(json_decode(Redis::get($key_cache)),200);
////            }
////        } catch (\Exception $e) {
////        }
//
//    if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);
//
//    if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
//// 2017-1-12 暂时去掉 start
////        $is_en_dian = '';
//
////        if(DBUtil::check_str_cn_en($param)==1 && strlen($param)==4 && strchr($param,'.'))$is_en_dian = 1;
//
//
//
//    // if(empty($is_en_dian))
//    // {
//    //     // 暂时解决 app 域名 搜索
//    //     $app_domain = [
//    //         'com.','cn.','tv.','ctrip.','yong.','jp.'
//    //     ];
//    //     foreach ($app_domain as $key => $value) {
//    //         if(stripos($param,$value)===0)
//    //         {
//    //             $is_en_dian = 1;
//    //             break;
//    //         }
//    //     }
//    // }
//
//
//
//
//    // $curl_param = [
//    //     'size'=>$size,
//    //     'query'=>[
//    //         'bool'=>[
//    //             'must'=>[
//    //                 'multi_match'=>[
//    //                     'query'=>$param,
//    //                     'fields'=>[
//    //                         'host','cname^10','ename'
//    //                     ],
//    //                     'tie_breaker'=>0.3
//    //                 ]
//    //             ],
//    //             'must_not'=>[
//    //                 'term'=>[
//    //                     'role'=>0
//    //                 ]
//    //             ]
//    //         ]
//
//    //     ]
//    // ];
//
//    // $curl_param_dian = [
//    //     'size'=>$size,
//    //     'query'=>[
//    //         'bool'=>[
//    //             'must'=>[
//    //                 [
//    //                     'prefix'=>[
//    //                         'host'=>$param
//    //                     ]
//    //                 ]
//    //             ],
//    //             'must_not'=>[
//    //                 'term'=>[
//    //                     'role'=>0
//    //                 ]
//    //             ]
//    //         ]
//
//    //     ]
//    // ];
//
//    // if($is_en_dian)$curl_param = $curl_param_dian;
//    //
//    // 2017-1-12 end
//
//
//    $curl_param = [
//        'size'=>$size,
//        'query'=>[
//            'bool'=>[
//                'should'=>[
//                    [
//                        'match'=>[
//                            'cname'=>[
//                                'query'=>$param,
//                                'boost'=>10
//                            ]
//                        ]
//                    ],
//                    [
//                        'match_phrase_prefix'=>[
//                            'host'=>$param
//                        ]
//                    ]
//                ],
//                'must_not'=>[
//                    'term'=>[
//                        'role'=>0
//                    ]
//                ]
//            ]
//
//        ]
//    ];
//
//    if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];
//
//    $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
//
//    // $rs = HttpUtil::parse_curl_search_type('domain_v8/domain_index',$curl_param,$type);
//    $data = DBUtil::get_parse_es_data($rs);
//    // 暂无数据 2002
//    if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
//    // 记录 域名 平台
//    // 2016-12-23 finn
//    if(!empty($data['data']))
//    {
//        $curl_param_do = [
//            'size'=>1,
//            'sort'=>[
//                'date5'=>[
//                    'order'=>'desc'
//                ]
//            ],
//            'query'=>[
//                'filtered'=>[
//                    'filter'=>[
//                        'should'=>[]
//                    ]
//                ]
//            ]
//        ];
//
//
//        foreach ($data['data'] as $key => &$value) {
//            $item = [
//                'term'=>[
//                    'domain_host'=>$value->host
//                ]
//            ];
//            $item_domain = [
//                'term'=>[
//                    'domain'=>$value->host
//                ]
//            ];
//            array_push($curl_param_do['query']['filtered']['filter']['should'],$item);
//            array_push($curl_param_do['query']['filtered']['filter']['should'],$item_domain);
//
//            $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param_do,'');
//
//            $data_do = DBUtil::get_parse_es_data($rs_do);
//            $value->attr04 = empty($data_do['data'])?'':$data_do['data'][0]->attribute04;
//            // $value->platform = empty($data_do['data'])?'':$data_do['data'][0]->platform;
//        }
//    }
//    $data['status'] = 200;
//
//    DBUtil::setRedisData($key_cache,$data,5000);
////        try{
////           Redis::set($key_cache,json_encode($data));
////            Redis::expire($key_cache,5000);
////        }catch(\Exception $e){
////
////        }
//    return HttpUtil::retrun_json($data,200);
//
//}