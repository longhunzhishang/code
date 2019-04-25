<?php

namespace App\Http\Controllers\Apis;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use Search;
USE App\Http\Utils\DBUtil;
use Elasticsearch\Client;

/**
 * finn
 * 20170706
 * main index api
 */
class MainIndexController extends Controller
{


    public function postCount(Request $request)
    {
        dd($request->all());
    }
    public function getAdsBySub(Request $request)
    {
        $id = $request->id;

        $curl_param = [
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                'term'=>[
                                    'subject'=>$id
                                ]
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'addata'=>[
                            'terms'=>[
                                'field'=>'id'
                            ]
                        ]
                    ]
                ];



                $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

                $parse_rs = json_decode($rs);

                $ads = empty($parse_rs->aggregations->addata->buckets)?1:count($parse_rs->aggregations->addata->buckets);

        return $ads;
        
    }

    /**
     * 获取 main_index 所有 信息 
     * @return [type] [description]
     */
    public function getAll(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($size && !is_numeric($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        if(!DBUtil::sortValied($sort) || $size > config('param.MAXSIZE'))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        $curl_param = [
            // 'sort'=>[
            //     '_score'=>'desc'
            // ],
            'size'=>$size
        ];


        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['last_id'] = $data['data'][0]->id;

        if($sort=='asc')$data['last_id'] = $data['data'][$size-1]->id;

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    public function getInfoBySubjects(Request $request)
    {
        $subject = empty($request->subject)?'':$request->subject;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $size = $size>10000?10000:$size;



        if(empty($subject) || !is_numeric($subject))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        if(!is_numeric($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);


        $curl_param = [
            'size'=>$size,
            'query'=>[
                'term'=>[
                    'subject'=>$subject
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        return HttpUtil::retrun_json($data,200);

    }

    public function getInfoId(Request $request)
    {
        $id = empty($request->id)?'':$request->id;

        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $es = ESUtil::getInfoById(config('param.MAININDEX_SEARCH'),$id);

        if(empty($es[0]))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);

        $data['data'] = $es[0];
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 根据 角色 查询
     * 20170711 修改 数组处理
     * @return [type] [description]
     */
    public function getInfoInIds(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;

        if(empty($ids))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $ids = urldecode($ids);

        $ads_id = explode('ad_id', $ids);

        foreach ($ads_id as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        }
        $es_data = [];
        foreach ($ads_id as $key => $value) {
            if($value)
            {
                $es = ESUtil::getInfoById(config('param.MAININDEX_SEARCH'),$value);
                if(!empty($es))$es_data[] = $es[0];
            }
        }
        
        $data['data'] = $es_data;
        $data['total'] = count($es_data);
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 字段 获取 对应的消息
     * 20170712
     * finn
     */
    public function getAllField(Request $request)
    {
        $field = empty($request->field)?'':$request->field;
        $param = empty($request->value)?'':$request->value;
        $size = empty($request->size)?1:$request->size;
        $size = $size>10000?10000:$size;

        if(empty($field) || empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        $es = ESUtil::getFieldInfo(config('param.MAININDEX_SEARCH'),$field,$param,$size);

        
        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['data'] = $es;
        $data['total'] = count($es);
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 字段 获取 对应的消息
     * 20170712
     * finn
     */
    public function getAllFieldTop6(Request $request)
    {

        $field = empty($request->field)?"":$request->field;
        $value = $request->value;

        $curl_param = [
            'from'=>0,
            'size'=>'7',
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $field=>$value
                            ]
                        ],
                        [
                            'match'=>[
                                'width'=>1080
                            ]
                        ],
                        [
                            'match'=>[
                                'height'=>1596
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        
        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 字段 统计 数目
     * @return [type] [description]
     */
    public function getCountByFiled(Request $request)
    {
        $field = empty($request->field)?'':$request->field;

        $value = empty($request->value)?'':$request->value;

        $filterfield = empty($request->filterfield)?'':$request->filterfield;


        $file = empty($request->file)?'':$request->file;

        $page = empty($request->page)?1:$request->page;
        $size = empty($request->size)?config('param.SIZE'):$request->size;


        $from = 0;
        if($page>1)$from = $page * $size;


        if(empty($field) && empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $type = empty($request->type)?'':$request->type;
        $platform = empty($request->platform)?'':$request->platform;


        $url_query[$field] = $value;

        if($type)$url_query['type'] = $type;

        if($platform)$url_query['platform'] = $platform;

        $size = $size>10000?10000:$size;

        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                $filterfield=>[
                                    'gt'=>0
                                ]
                            ]
                        ]
                    ],
                    'must_not'=>[
                        [
                            'term'=>[
                                $filterfield=>$value
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'publisher'=>[
                    'cardinality'=>[
                        'field'=>$filterfield
                    ]
                ]
            ]
        ];
        foreach ($url_query as $key => $v) {
            $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$v]];
        }


        $url = config('param.ES_URL').config('param.MAININDEX_SEARCH').'/'.config('param.ES_SEARCH');

        if($file)$url = $url.'?search_type=count';


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_data = json_decode($rs,true);

        $data = DBUtil::get_parse_es_data($rs);

        // $data['publisher'] = [];

        // foreach ($data['data'] as $key => $value) {
        //     $data['publisher'][] = $value
        // }


        // $publisher_tmp = empty($rs_data['aggregations']['publisher']['buckets'])?[]:$rs_data['aggregations']['publisher']['buckets'];

        $data['total'] = empty($rs_data['hits']['total'])?'':$rs_data['hits']['total'];
        $data['fieldtotal'] =  empty($rs_data['aggregations']['publisher']['value'])?[]:$rs_data['aggregations']['publisher']['value'];
        
        $data['status'] = 200;
     
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 根据 字段 统计 数目
     * @return [type] [description]
     */
    public function getCountByFiledInfo(Request $request)
    {
        $field = empty($request->field)?'':$request->field;

        $value = empty($request->value)?'':$request->value;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $sort = empty($request->sort)?'desc':$request->sort;


        $filterfield = empty($request->filterfield)?'':$request->filterfield;


        $file = empty($request->file)?'':$request->file;

        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $page = empty($request->page)?1:$request->page;

        // if($page>1)$size = $page * $size;


        if(empty($field) && empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $type = empty($request->type)?'':$request->type;
        $platform = empty($request->platform)?'':$request->platform;


        $url_query[$field] = $value;

        if($type)$url_query['type'] = $type;

        if($platform)$url_query['platform'] = $platform;


        // $size = $size>10000?10000:$size;

        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                $filterfield=>[
                                    'gt'=>0
                                ]
                            ]
                        ]
                    ],
                    'must_not'=>[
                        [
                            'term'=>[
                                $filterfield=>$value
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                // 'publisher'=>[
                //     'terms'=>[
                //         'field'=>$filterfield,
                //         'size'=>$size_total,
                //         'order'=>[
                //             '_term'=>$sort
                //         ]
                //     ]
                // ],
                'count'=>[
                    'cardinality'=>[
                        'field'=>$filterfield
                    ]
                ]
            ]
        ];

    //     "count": {
    //   "cardinality": {
    //     "field": "publisher"
    //   }
    // }
        foreach ($url_query as $key => $v) {
            $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$v]];
        }


        if(!$orderBy)unset($curl_param['sort']);


        $url = config('param.ES_URL').config('param.MAININDEX_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_data = json_decode($rs,true);

        $total = empty($rs_data['aggregations']['count']['value'])?0:$rs_data['aggregations']['count']['value'];


        $size_total = $page * $size;


        $rm_limit = 0;

        if($page>1)
        {
            $all_total = $page*$size;
            if($all_total>$total)
            {
                $rm_limit = $size * ($page-1);
                // $rm_limit = ($all_total-$total + ($size * ($page-1)));
            }else{
                $rm_limit = $size * ($page-1);
            }
        }


        $curl_param2 = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                $filterfield=>[
                                    'gt'=>0
                                ]
                            ]
                        ]
                    ],
                    'must_not'=>[
                        [
                            'term'=>[
                                $filterfield=>$value
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'publisher'=>[
                    'terms'=>[
                        'field'=>$filterfield,
                        'size'=>$size_total,
                        'order'=>[
                            '_count'=>$sort
                        ]
                    ]
                ]//,
                // 'count'=>[
                //     'cardinality'=>[
                //         'field'=>$filterfield
                //     ]
                // ]
            ]
        ];

    //     "count": {
    //   "cardinality": {
    //     "field": "publisher"
    //   }
    // }
        foreach ($url_query as $key => $v) {
            $curl_param2['query']['bool']['must'][] = ['term'=>[$key=>$v]];
        }


        if(!$orderBy)unset($curl_param2['sort']);



        $url = config('param.ES_URL').config('param.MAININDEX_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param2,"POST");

        $rs_data = json_decode($rs,true);

        $publisher_tmp = empty($rs_data['aggregations']['publisher']['buckets'])?[]:$rs_data['aggregations']['publisher']['buckets'];


        $data['publisher'] = [];

        foreach ($publisher_tmp as $key => $value) {

            $data['publisher'][] = $value['key'];
        }


        if($page>1)
        {
            foreach ($data['publisher'] as $key => $value) {
                if($key<$rm_limit)unset($data['publisher'][$key]);
            }
        }

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 字段 统计 数目
     * @return [type] [description]
     */
    public function getInfoCountAll(Request $request)
    {
        $param = empty($request->param)?'':$request->param;

        // // advertiser
        $field = empty($request->field)?'':$request->field;

        
        $role = empty($request->role)?"":$request->role;
        // advertiser 的值
        $value = empty($request->value)?"":$request->value;



        if(empty($param) || empty($field))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $ids = explode('_domain_id_', $param);

        


        $return_data = [];
        foreach ($ids as $key => $v) {
            if($v)
            {
                // $items = [
                //     'term'=>[
                //         $field=>$v
                //     ]
                // ];

                $curl_param = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'range'=>[
                                        $field=>[
                                            'gt'=>0
                                        ]
                                    ]
                                ],
                                [
                                    'term'=>[
                                        $role=>$value
                                    ]
                                ],
                                [
                                    'term'=>[
                                        $field=>$v
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
                        'subjects'=>[
                            'cardinality'=>[
                                'field'=>'subject'
                            ]
                        ]
                    ]
                ];
                // $curl_param['query']['bool']['must'] = ['term'=>[$field=>$v]];
                // array_push($curl_param['query']['bool']['must'], $items);

                // print_r($curl_param);

                $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

                $da = json_decode($rs,true);

                $item['ads'] = empty($da['aggregations']['ads']['value'])?0:$da['aggregations']['ads']['value'];
                $item['subjects'] = empty($da['aggregations']['subjects']['value'])?0:$da['aggregations']['subjects']['value'];
                $item['type'] = $field;
                $item['id'] = $v;

                $return_data[] = $item;
            }
        }

        $data['data'] = $return_data;
        $data['total'] = count($return_data);
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }


    /**
     * 根据 字段 获取 信息
     * @return [type] [description]
     */
    public function getInfoGroupByFiled(Request $request)
    {
        $groupBy = empty($request->groupBy)?'':$request->groupBy;
        $field = empty($request->field)?'':$request->field;
        $value = empty($request->value)?'':$request->value;

        // $from = 0;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;

        $type = empty($request->ad_type)?'':$request->ad_type;
        $platform = empty($request->platform)?'':$request->platform;
        $page = empty($request->page)?1:$request->page;

        $sort = empty($request->sort)?'desc':$request->$sort;




        // if($page>1)$from = $page * $size;

        if(empty($field)||empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        $url_query[$field] = $value;

        if($type)$url_query['type'] = $type;

        if($platform)$url_query['platform'] = $platform;

        $size_all = $page * $size;
        if($size_all>10000)$size_all=9999;

        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[]
                ]
            ],
            'aggs'=>[
                'group_count'=>[
                    'cardinality'=>[
                        'field'=>$groupBy
                    ]
                ],
                'group_by_state'=>[
                    'terms'=>[
                        'field'=>$groupBy,
                        'size'=>$size_all,
                        'order'=>[
                            '_term'=>$sort
                        ]
                    ]
                ]

            ]
        ];

        foreach ($url_query as $key => $value) {
            $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$value]];
        }

        if($orderBy)$curl_param['sort']=[$orderBy=>['order'=>'desc']];


        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $rs_des = json_decode($rs,true);

        $total = empty($rs_des['aggregations']['group_count']['value'])?0:$rs_des['aggregations']['group_count']['value'];


        $aggs = empty($rs_des['aggregations']['group_by_state']['buckets'])?[]:$rs_des['aggregations']['group_by_state']['buckets'];

        $data[$groupBy] = [];
        foreach ($aggs as $key => $value) {
            $data[$groupBy][] = $value['key'];
        }

        $rm_limit = 0;

        if($page>1)
        {
            $rm_limit = $size * ($page-1);
            $all_total = $page*$size;
            if($all_total>$total)
            {
                $rm_limit = $size * ($page-1);
            }else{
                $rm_limit = $size * ($page-1);
            }
        }

        if($page>1)
        {
            foreach ($data[$groupBy] as $key => $value) {
                if($key<$rm_limit)unset($data[$groupBy][$key]);
            }
        }
        


        $data_new = [];

        foreach ($data[$groupBy] as $key => $value) {
            $curl_param = [
                'size'=>1,
                'sort'=>[
                    'index_id'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'term'=>[
                                $groupBy=>$value
                            ]
                        ]
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);
            $rs_de = json_decode($rs,true);

            $data_new['data'][] = $rs_de['hits']['hits'][0]['_source'];

        }

        // 暂无数据 2002
        // if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        if(empty($data_new['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data_new['status'] = 200;
        $data_new['total'] = $total;
        // $data['status'] = 200;

        return HttpUtil::retrun_json($data_new,200);
    }


    /**
     * 根据 字段 获取 信息
     * @return [type] [description]
     */
    public function getInfoGroupByFiledParam(Request $request)
    {
        $groupBy = empty($request->groupBy)?'':$request->groupBy;
        $field = empty($request->field)?'':$request->field;
        $value = empty($request->value)?'':$request->value;

        
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;

        $param = empty($request->q)?'':$request->q;


        if(empty($field)||empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $size = $size>10000?10000:$size;
        $url_query[$field] = $value;

        if($param)
        {
            $url_query['cname'] = $param;
            $url_query['ename'] = $param;
        }

        $curl_param = [
            'sort'=>[
                '_score'=>'desc'
            ],
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[]
                ]
            ]//,
            // 'aggs'=>[
            //     'group_by_state'=>[
            //         'terms'=>[
            //             'field'=>$groupBy
            //         ]
            //     ]
            // ]
        ];

        foreach ($url_query as $key => $value) {
            $curl_param['query']['bool']['must'][] = ['match'=>[$key=>$value]];
        }

        if($orderBy)$curl_param['sort']=[$orderBy=>['order'=>'desc']];

        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }


    public function getInfoByDidRole(Request $request)
    {

        $role = empty($request->role)?'':$request->role;
        $role_value = empty($request->value)?'':$request->value;

        $field = empty($request->field)?'':$request->field;

        $domain = empty($request->ids)?'':$request->ids;
        $filed_value = empty($request->filed_value)?'':$request->filed_value;
        $ad_type = empty($request->ad_type)?0:$request->ad_type;
        $platform = empty($request->platform)?0:$request->platform;

        $size = empty($request->size)?10:$request->size;
        $page = empty($request->page)?1:$request->page;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;

        $from = 0;

        if($page>1)$from = $page * $size;


        // if(!$role || !$domain || !$field || !$role_value || !$filed_value)return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        // $domain_id = explode('_domain_id_', $domain);



        $filterfield = [];

        if($field)$filterfield[$field] = $filed_value;

        if($ad_type)$filterfield['type'] = $ad_type;
        if($platform)$filterfield['platform'] = $platform;

        $size_all = $page * $size;

        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $role=>$role_value
                            ]
                        ]//,
                        // [
                        //     'term'=>[
                        //         $field=>$filed_value
                        //     ]
                        // ]

                    ]
                ]
            ],
            'aggs'=>[
                 'publisher'=>[
                    'terms'=>[
                        'field'=>$orderBy,
                        'size'=>$size_all,
                        'order'=>[
                            '_term'=>'desc'
                        ]
                    ]
                ],
                'count'=>[
                    'cardinality'=>[
                        'field'=>$orderBy
                    ]
                ]
            ]
        ];


        if(!$orderBy)unset($curl_param['aggs']);


        if($filterfield)
        {
            foreach ($filterfield as $key => $value) {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];

                array_push($curl_param['query']['bool']['must'], $item);
            }
        }

        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);


        $rs_des = json_decode($rs,true);

        $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];


        $aggs = empty($rs_des['aggregations']['publisher']['buckets'])?[]:$rs_des['aggregations']['publisher']['buckets'];

        $data[$orderBy] = [];
        foreach ($aggs as $key => $value) {
            $data[$orderBy][] = $value['key'];
        }


        // print_r($data[$orderBy]);


        $rm_limit = 0;

        if($page>1)
        {
            // $rm_limit = $size * ($page-1);
            $all_total = $page*$size;
            if($all_total>$total)
            {
                $rm_limit = $size * ($page-1);
            }else{
                $rm_limit = $size * ($page-1);
            }
        }

        if($page>1)
        {
            foreach ($data[$orderBy] as $key => $value) {
                if($key<$rm_limit)unset($data[$orderBy][$key]);
            }
        }


        
        if(empty($data[$orderBy]))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['status'] = 200;
        $data['total'] = $total;
        // $data2['data'] = $data_tmp;

        return HttpUtil::retrun_json($data,200);



    }
}
