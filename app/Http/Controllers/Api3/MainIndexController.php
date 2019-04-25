<?php

namespace App\Http\Controllers\Api3;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtill4;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DSLUtil;
use App\Http\Utils\Tools;
use Search;
USE App\Http\Utils\DBUtil;
use Illuminate\Support\Facades\Redis as Redis;
use App\Http\Controllers\Api3\DomainController;
use DB;

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

        $key_cache = md5(implode('', $request->all()));
        if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        {
            $cache = DBUtil::getRedisData($key_cache);

            if(!empty($cache['data']))return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        }


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

        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));

        

        return HttpUtil::retrun_json($data,200);
    }

    public function getInfoId(Request $request)
    {

        $key_cache = md5(implode('', $request->all()));
        if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        {
            $cache = DBUtil::getRedisData($key_cache);

            if(!empty($cache['data']))return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        }



        $id = empty($request->id)?'':$request->id;
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $es = ESUtill4::getInfoById(config('param.MAININDEX_SEARCH'),$id);
        if(empty($es[0]))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);
        $data['data'] = $es[0];
        $data['status'] = 200;

        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));

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
                $es = ESUtill4::getInfoById(config('param.MAININDEX_SEARCH'),$value);
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
        $es = ESUtill4::getFieldInfo(config('param.MAININDEX_SEARCH'),$field,$param,$size);
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
        $data['total'] = empty($rs_data['hits']['total'])?'':$rs_data['hits']['total'];
        $data['fieldtotal'] =  empty($rs_data['aggregations']['publisher']['value'])?[]:$rs_data['aggregations']['publisher']['value'];
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 根据 字段 统计 数目
     * 2017-6-8 新增 过滤
     * is_tag 判断 是否是点击 排名榜 跟踪者过来 1 是 0 不是
     * @return [type] [description]
     */
    public function getCountByFiledInfo(Request $request)
    {
        $field = empty($request->field)?'':$request->field;

        $value = empty($request->value)?'':$request->value;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $sort = empty($request->sort)?'desc':$request->sort;
        $filterfield = empty($request->filterfield)?'':$request->filterfield;


        
        $is_tag = empty($request->is_tag)?'':$request->is_tag;

        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $page = empty($request->page)?1:$request->page;
        // if($page>1)$size = $page * $size;

        if(empty($field) && empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);



        $type = empty($request->type)?'':$request->type;

        // $type = $type==1?'image'
        // type 3 flv, 2 swf , 1 image 4 html5 5 material = native(原生)
        // $type_material = '';
        // switch ($type) {
        //      case '1':
        //          $type_material = 'image';
        //          break;
        //     case '2':
        //         $type_material = 'swf';
        //         break;
        //     case '3':
        //         $type_material = 'flv';
        //         break;
        //      case '4':
        //         $type_material = 'html5';
        //         break;
        //     case '5':
        //         $type_material = 'native';
        //         break;
        // }
        // // 类型
        $type_key = $type==5?'material':'type';


        $platform = empty($request->platform)?'':$request->platform;
        $url_query[$field] = $value;

        



        //2017-6-8 新增 过滤 条件
        $shape = empty($request->shape)?'':$request->shape;// 3 视频 2 flush 1 图片 4 h5 5 原生
        $min_width = empty($request->min_width)?'':$request->min_width;// shape 自定义尺寸
        $width = empty($request->width)?'':$request->width;
        $min_height = empty($request->min_height)?'':$request->min_height;
        $height = empty($request->height)?'':$request->height;


        $sortmode = empty($request->sortmode)?'':$request->sortmode;//1 时间  2  相关性
        $date = empty($request->date)?'':$request->date;// 4 一天  3 一周 2 一月 1 一年
        $start_time = empty($request->start_time)?'':$request->start_time;// 时间 自定义
        $end_time = empty($request->end_time)?'':$request->end_time;


        if(empty($start_time) && empty($end_time))
        {
            $date_tmp = [];
            switch ($date) {
                case '1':
                    $date_tmp = DBUtil::getOneYearNew();
                    break;
                case '2':
                    $date_tmp = DBUtil::getNowMothNew();
                    break;
                case '3':
                    $date_tmp = DBUtil::getOneWeekNew();
                    break;
                case '4':
                    $date_tmp['start_time'] = date('Y-m-d',time());
                    $date_tmp['end_time'] = date('Y-m-d',time());

                    break;
            }
            if($date)
            {
                $start_time = strtotime($date_tmp['start_time'].' 00:00:00').'000';
                $end_time = strtotime($date_tmp['end_time'].' 23:59:59').'000';
            }
            
        }else{
            $start_time = $start_time?strtotime($start_time.' 00:00:00').'000':'';
            $end_time = $end_time?strtotime($end_time.' 23:59:59').'000':'';
        }

        $min_width = min($min_width,$width);
        $max_width = max($min_width,$width);
        $min_height = min($min_height,$height);
        $max_height = max($min_height,$height);

        $curl_param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);

        if($shape)$url_query['shape'] = $shape;

         //判断是否是跟踪者 二级域名
         //正式情况下不会执行
        if($field=='tracker')
        {
            $is_flag_domain = DBUtil::getFlagById($value);
            if(empty($is_flag_domain))return HttpUtil::retrun_json(['data'=>[],'status'=>200],207);

            if($is_flag_domain[0]->flag==1 || $is_tag==1)
             {
                switch ($filterfield) {
                    case 'advertiser':
                        $filterfield = 'advertiser_na';
                        break;
                     case 'publisher':
                        $filterfield = 'publisher_na';
                        break;
                     case 'tracker':
                        $filterfield = 'tags_list';
                        break;
                }
                $old_value = $value;
                $value = $is_flag_domain[0]->host;

                if($type)
                {
                    // $type = $type==1?'image'
                    // type 3 flv, 2 swf , 1 image 4 html5 5 material = native(原生)
                    $type_material = '';
                    switch ($type) {
                         case '1':
                             $type_material = 'image';
                             break;
                        case '2':
                            $type_material = 'swf';
                            break;
                        case '3':
                            $type_material = 'flv';
                            break;
                         case '4':
                            $type_material = 'html5';
                            break;
                        case '5':
                            $type_material = 'native';
                            break;
                    }
                    $url_query[$type_key] = $type_material;
                }

                if($platform==3)$url_query['attribute04'] = 'android';

                if($platform==4)$url_query['attribute04'] = 'ios';

                // 跟踪者 二级域名
                $curl_param_trac = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        'tags_list'=>$value
                                    ]
                                ]
                            ],
                            'must_not'=>[
                                [
                                    'term'=>[
                                        $filterfield=>$old_value
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'count'=>[
                            'cardinality'=>[
                                'field'=>$filterfield
                            ]
                        ]
                    ]
                ];

                foreach ($curl_param_range as $ck => $cv) {
                    array_push($curl_param_range['query']['bool']['must'], $cv);
                }
                foreach ($url_query as $key => $v) {
                    if($key==$field)
                    {
                        $curl_param_trac['query']['bool']['must'][] = ['term'=>['tags_list'=>$value]];
                    }
                    else{
                        $curl_param_trac['query']['bool']['must'][] = ['term'=>[$key=>$v]];
                    }
                }
                $curl_param_trac['_source'] = config('param.ADDATA_FILED_SOURCE');
                // dd(json_encode($curl_param_trac));
                $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
                $rs = HttpUtil::send_curl_methods($url,$curl_param_trac,"POST");

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
                    }else{
                        $rm_limit = $size * ($page-1);
                    }
                }


                $curl_param_trac2 = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[]
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
                            ],
                            'aggs'=>[
                                'ads'=>[
                                    'cardinality'=>[
                                        'field'=>'id'
                                    ]
                                ],
                                'subjects'=>[
                                    'cardinality'=>[
                                        'field'=>'subject_md5'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                foreach ($curl_param_range as $ck2 => $cv2) {
                    array_push($curl_param_trac2['query']['bool']['must'], $cv2);
                }

                foreach ($url_query as $key => $v) {
                    if($key==$field)
                    {
                        $curl_param_trac2['query']['bool']['must'][] = ['term'=>['tags_list'=>$value]];
                    }
                    else{
                        $curl_param_trac2['query']['bool']['must'][] = ['term'=>[$key=>$v]];
                    }
                }
                if(!$orderBy)unset($curl_param_trac2['sort']);

                // dd(json_encode($curl_param_trac2));
                $curl_param_trac2['_source'] = config('param.ADDATA_FILED_SOURCE');
                $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
                $rs = HttpUtil::send_curl_methods($url,$curl_param_trac2,"POST");
                $rs_data = json_decode($rs,true);

                $publisher_tmp = empty($rs_data['aggregations']['publisher']['buckets'])?[]:$rs_data['aggregations']['publisher']['buckets'];


                $data['fieldtotal'] = $total;

                 switch ($filterfield) {
                    case 'advertiser_na':
                        $filterfield = 'advertiser';
                        break;
                     case 'publisher_na':
                        $filterfield = 'publisher';
                        break;
                     case 'tags_list':
                        $filterfield = 'tracker';
                        break;
                }
                // $data['publisher'] = [];
                $item_data = [];
                foreach ($publisher_tmp as $key => $value) {
                    // 第一步 获取对应的域名 消息消息

                    $curl_param_domain = [
                        'query'=>[
                            'term'=>[
                                'host_no'=>$value['key']
                            ]
                        ]
                    ];
                    $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');
                    $rs = HttpUtil::send_curl_methods($url,$curl_param_domain,"POST");
                    $data_domain = DBUtil::get_parse_es_data($rs);

                    if(!empty($data_domain['data']))
                    {
                        $item['ads'] = empty($value['ads']['value'])?0:$value['ads']['value'];
                        $item['subjects'] = empty($value['subjects']['value'])?0:$value['subjects']['value'];
                        $item['type'] = $filterfield;
                        $item['id'] = $data_domain['data'][0]->id;
                        $item_data[] = $item;
                    }
                    unset($item); 
                }

                // dd($item_data);
                if($page>1)
                {
                    foreach ($item_data as $key => $value) {
                        if($key<$rm_limit)unset($item_data[$key]);
                    }
                }
                $data['status'] = 200;
                $data['data'] = $item_data;
                return HttpUtil::retrun_json($data,200);
             }

        }
        if($type)$url_query[$type_key] = $type;
         //顶级域名 执行以下内容
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
                'count'=>[
                    'cardinality'=>[
                        'field'=>$filterfield
                    ]
                ]
            ]
        ];
        
        if(!$orderBy)unset($curl_param['sort']);


        
        $platform_range = [];

        if($platform)
        {
            if($platform==2)
            {
                $platform_range = [
                    'range'=>[
                        'platform'=>[
                            'gte'=>$platform
                        ]
                    ]
                ];
                
            }else{
                $url_query['platform'] = $platform;
            }
        }

        foreach ($url_query as $key => $v) {
            $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$v]];
        }

        if(!empty($platform_range))array_push($curl_param['query']['bool']['must'],$platform_range);

        


        foreach ($curl_param_range as $key => $v) {

            array_push($curl_param['query']['bool']['must'],$v);
        }

         //$curl_param['query']['bool']['must'] $arrayName = array('' => , );

        // dd($filterfield);

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.MAININDEX_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_data = json_decode($rs,true);

        $total = empty($rs_data['aggregations']['count']['value'])?0:$rs_data['aggregations']['count']['value'];


        if(empty($total))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $size_total = $page * $size;
        $rm_limit = 0;
        if($page>1)
        {
            $all_total = $page*$size;
            if($all_total>$total)
            {
                $rm_limit = $size * ($page-1);
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
                ]
            ]
        ];

        if(!empty($platform_range))array_push($curl_param2['query']['bool']['must'],$platform_range);


        // dd($url_query);
        foreach ($url_query as $key => $v) {
            $curl_param2['query']['bool']['must'][] = ['term'=>[$key=>$v]];
        }
        if(!$orderBy)unset($curl_param2['sort']);


        foreach ($curl_param_range as $key => $v) {

            array_push($curl_param2['query']['bool']['must'],$v);
        }

        // dd(json_encode($curl_param2));
        $url = config('param.ES_URL').config('param.MAININDEX_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param2,"POST");
        $rs_data = json_decode($rs,true);


        $publisher_tmp = empty($rs_data['aggregations']['publisher']['buckets'])?[]:$rs_data['aggregations']['publisher']['buckets'];


        $data['fieldtotal'] = $total;


        // $data['publisher'] = [];
        $item_data = [];
        foreach ($publisher_tmp as $key => $value) {
           // [] = $value['key'];
            //$item[$filterfield] = $value['key'];
            $item['ads'] = empty($value['ads']['value'])?0:$value['ads']['value'];
            $item['subjects'] = empty($value['subjects']['value'])?0:$value['subjects']['value'];
            $item['type'] = $filterfield;
            $item['id'] = $value['key'];
            $item_data[] = $item;
            unset($item); 
        }

        // dd($item_data);
        if($page>1)
        {
            foreach ($item_data as $key => $value) {
                if($key<$rm_limit)unset($item_data[$key]);
            }
        }
        $data['status'] = 200;
        $data['data'] = $item_data;
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 字段 统计 数目
     * @return [type] [description]
     */
    public function getInfoCountAll(Request $request)
    {
        $param = empty($request->param)?'':$request->param;
        // // advertiser 待查的对象 menu tab
        $field = empty($request->field)?'':$request->field;

        // 当前角色 
        $role = empty($request->role)?"":$request->role;
        // advertiser 的值
        $value = empty($request->value)?"":$request->value;

        if(empty($param) || empty($field))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $ids = explode('_domain_id_', $param);

        $type = empty($request->type)?'':$request->type;
        // // 类型

        $platform = empty($request->platform)?'':$request->platform;

        $url_query = [];

        if($platform)$url_query['platform'] = $platform;

        if($type)$url_query['type'] = $type;

        // 判断是否是从 排名榜 点击跟踪者 过来
        $is_tag = empty($request->is_tag)?'':$request->is_tag;

           //2017-6-8 新增 过滤 条件
        $shape = empty($request->shape)?'':$request->shape;// 3 视频 2 flush 1 图片 4 h5 5 原生
        $min_width = empty($request->min_width)?'':$request->min_width;// shape 自定义尺寸
        $width = empty($request->width)?'':$request->width;
        $min_height = empty($request->min_height)?'':$request->min_height;
        $height = empty($request->height)?'':$request->height;


        $sortmode = empty($request->sortmode)?'':$request->sortmode;//1 时间  2  相关性
        $date = empty($request->date)?'':$request->date;// 4 一天  3 一周 2 一月 1 一年
        $start_time = empty($request->start_time)?'':$request->start_time;// 时间 自定义
        $end_time = empty($request->end_time)?'':$request->end_time;


        if(empty($start_time) && empty($end_time))
        {
            $date_tmp = [];
            switch ($date) {
                case '1':
                    $date_tmp = DBUtil::getOneYearNew();
                    break;
                case '2':
                    $date_tmp = DBUtil::getNowMothNew();
                    break;
                case '3':
                    $date_tmp = DBUtil::getOneWeekNew();
                    break;
                case '4':
                    $date_tmp['start_time'] = date('Y-m-d',time());
                    $date_tmp['end_time'] = date('Y-m-d',time());

                    break;
            }
            if($date)
            {
                $start_time = strtotime($date_tmp['start_time'].' 00:00:00').'000';
                $end_time = strtotime($date_tmp['end_time'].' 23:59:59').'000';
            }
            
        }else{
            $start_time = $start_time?strtotime($start_time.' 00:00:00').'000':'';
            $end_time = $end_time?strtotime($end_time.' 23:59:59').'000':'';
        }

        $min_width = min($min_width,$width);
        $max_width = max($min_width,$width);
        $min_height = min($min_height,$height);
        $max_height = max($min_height,$height);

        $curl_param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);

        if($shape)$url_query['shape'] = $shape;

        if($is_tag==1)
        {
            $is_flag_domain = DBUtil::getFlagById($value);
            if(empty($is_flag_domain))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
            $role = 'tags_list';
            $value = $is_flag_domain[0]->host;
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    $role=>$value
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
                            'field'=>'subject_md5'
                        ]
                    ]
                ]
            ];
            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
            $da = json_decode($rs,true);

            $item['ads'] = empty($da['aggregations']['ads']['value'])?0:$da['aggregations']['ads']['value'];
            $item['subjects'] = empty($da['aggregations']['subjects']['value'])?0:$da['aggregations']['subjects']['value'];
            $item['type'] = $field;
            $item['id'] = $is_flag_domain[0]->id;

            $return_data[] = $item;
            $data['data'] = $return_data;
            $data['total'] = count($return_data);
            $data['status'] = 200;
            return HttpUtil::retrun_json($data,200);
        }

        $return_data = [];
        foreach ($ids as $key => $v) {
            if($v)
            {
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



                foreach ($curl_param_range as $ck => $cv) {
                    array_push($curl_param['query']['bool']['must'], $cv);
                }

                foreach ($url_query as $uk => $uv) {
                    $curl_param['query']['bool']['must'][] = ['term'=>[$uk=>$uv]];
                }

                dd(json_encode($curl_param));
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
     * 2017-7-2 判断域名是否是跟踪者 二级域名
     * 2017-6-7 新增 过滤 条件
     * @return [type] [description]
     */
    public function getInfoGroupByFiled(Request $request)
    {

        $key_cache = md5(implode('', $request->all()));
        // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        // {
        //     $cache = DBUtil::getRedisData($key_cache);

        //     if(!empty($cache['data']))return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        // }
        $groupBy = empty($request->groupBy)?'':$request->groupBy;
        $field = empty($request->field)?'':$request->field;
        $value = empty($request->value)?'':$request->value;
        // $from = 0;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;

        $type = empty($request->ad_type)?'':$request->ad_type;
        $platform = empty($request->platform)?'':$request->platform;// 1 pc  2 移动 4 Ios 3 android
        $page = empty($request->page)?1:$request->page;
        $sort = empty($request->sort)?'desc':$request->sort;
        // 是否随机
        $random = empty($request->random)?'':$request->random;

        //2017-6-7 新增 过滤 条件
        $shape = empty($request->shape)?'':$request->shape;// 3 视频 2 flush 1 图片 4 h5 5 原生
        $min_width = empty($request->min_width)?'':$request->min_width;// shape 自定义尺寸
        $width = empty($request->width)?'':$request->width;
        $min_height = empty($request->min_height)?'':$request->min_height;
        $height = empty($request->height)?'':$request->height;


        $sortmode = empty($request->sortmode)?'':$request->sortmode;//1 时间  2  相关性
        $date = empty($request->date)?'':$request->date;// 4 一天  3 一周 2 一月 1 一年
        $start_time = empty($request->start_time)?'':$request->start_time;// 时间 自定义
        $end_time = empty($request->end_time)?'':$request->end_time;



        $isbinlog = empty($request->isbinlog)?'':$request->isbinlog;
        $b_time = empty($request->b_time)?'':$request->b_time;



        if(empty($start_time) && empty($end_time))
        {
            $date_tmp = [];
            switch ($date) {
                case '1':
                    $date_tmp = DBUtil::getOneYearNew();
                    break;
                case '2':
                    $date_tmp = DBUtil::getNowMothNew();
                    break;
                case '3':
                    $date_tmp = DBUtil::getOneWeekNew();
                    break;
                case '4':
                    $date_tmp['start_time'] = date('Y-m-d',time());
                    $date_tmp['end_time'] = date('Y-m-d',time());

                    break;
            }
            if($date)
            {
                $start_time = strtotime($date_tmp['start_time'].' 00:00:00').'000';
                $end_time = strtotime($date_tmp['end_time'].' 23:59:59').'000';
            }
            
        }else{
            $start_time = strtotime($start_time.' 00:00:00').'000';
            $end_time = strtotime($end_time.' 23:59:59').'000';
        }

        $min_width = min($min_width,$width);
        $max_width = max($min_width,$width);
        $min_height = min($min_height,$height);
        $max_height = max($min_height,$height);

        // 待查的角色
        $role_type = empty($request->role_type)?'':$request->role_type;
        $role_value = empty($request->role_value)?'':$request->role_value;


        if(empty($field)||empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        $url_query[$field] = $value;


        if($type)$url_query['type'] = $type;


        $risk = empty($request->risk)?'':$request->risk;


        if($role_value && $role_type)$url_query[$role_type] = $role_value;


        $size_all = $page * $size;
        if($size_all>5000)$size_all=5000;
        $all_random = $random==1?$size_all * rand(1,4):$size_all;



        $param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);


        $is_tag = empty($request->is_tag)?'':$request->is_tag;

        if($field=='tracker')
        {
           
            // dd($field);
            $is_flag_domain = DBUtil::getFlagById($value);

            // dd($is_flag_domain);
            // dd($is_tag);

            if(empty($is_flag_domain))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

            $tracker_tag_list = 'tracker_list';
            //跟踪者 二级域名
            if($is_flag_domain[0]->flag==1 || $is_tag==1)
            {
               $tracker_tag_list = 'tags_list';
            }



             unset($url_query[$field]);

                if($role_value && $role_type)unset($url_query[$role_type]);
                // 此时 有可能是 tags 二级域名
                $value = $is_flag_domain[0]->host;

                if(empty($value))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

                if($role_value && $role_type)
                {
                    $is_role_value__domain = DBUtil::getFlagById($role_value);
                    unset($url_query[$role_type]);

                    switch ($role_type) {
                        case 'advertiser':
                            $url_query['advertiser_na'] = $is_role_value__domain[0]->host;
                            break;
                        case 'publisher':
                            $url_query['publisher_na'] = $is_role_value__domain[0]->host;
                            break;
                    }
                }


                $curl_param_tr2 = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        $tracker_tag_list=>$value
                                    ]
                                ]
                            ]
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
                                'size'=>$all_random,
                                'order'=>[
                                    [
                                        'max_date'=>'desc'
                                    ],
                                    [
                                        'max_filed'=>$sort
                                    ]
                                ]
                            ],
                            'aggs'=>[
                                'max_date'=>[
                                    'max'=>[
                                        'field'=>'created_date'
                                    ]
                                ],
                                'max_filed'=>[
                                    'max'=>[
                                        'field'=>$groupBy
                                    ]
                                ]
                            ]
                        ]

                    ]
                ];

                foreach ($url_query as $key => $value) {
                    $curl_param_tr2['query']['bool']['must'][] = ['term'=>[$key=>$value]];
                }

                if($orderBy)$curl_param_tr2['sort']=[$orderBy=>['order'=>'desc']];
                $curl_param_tr2['_source'] = config('param.ADDATA_FILED_SOURCE');

                // dd($isbinlog);

                // dd($request->all());
                if($isbinlog==1)
                {
                    // $b_time_start = date_add("d",-1,$b_time);

                    $b_time_start = date('Y-m-d',strtotime("$b_time-1 day")); 
                    
                    $b_time = empty($b_time)?date("Y-m-d",strtotime("-1 day")):$b_time;

                   

                    $start_time = strtotime($b_time_start.' 00:00:00').'000';
                    $end_time = strtotime($b_time.' 00:00:00').'000';


                    // $start_time = strtotime($b_time.' 00:00:00').'000';
                    // $end_time = strtotime($b_time.' 23:59:59').'000';
                
                    $bin_t_range = [
                        'range'=>[
                            'created_date'=>[
                                'gte'=>$start_time,
                                'lte'=>$end_time
                            ]
                        ]
                    ];
                    array_push($curl_param_tr2['query']['bool']['must'], $bin_t_range);
                    // $curl_param['query']['bool']['must']
                }else{
                    $param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);

                    foreach ($param_range as $pk => $pv) {
                        array_push($curl_param_tr2['query']['bool']['must'], $pv);
                    }
                }


            
                // dd(json_encode($curl_param_tr2));
                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param_tr2);
                $rs_des = json_decode($rs,true);
                $total = empty($rs_des['aggregations']['group_count']['value'])?0:$rs_des['aggregations']['group_count']['value'];

                 $aggs = empty($rs_des['aggregations']['group_by_state']['buckets'])?[]:$rs_des['aggregations']['group_by_state']['buckets'];
                $data[$groupBy] = [];
                foreach ($aggs as $key => $value) {
                    $data[$groupBy][] = $value['key'];
                }
                if(empty($data[$groupBy]))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
                // shuffle($chars);   
                $output = []; 
                if($random)
                {
                    shuffle($data[$groupBy]);
                    $charsLen = count($data[$groupBy]) - 1; 
                    for ($i=0; $i<$size_all; $i++) 
                    { 
                        $output[] = $data[$groupBy][mt_rand(0, $charsLen)]; 
                    }
                }else{
                    if($page>1)
                    {
                        $rm_limit = $size * ($page-1);
                        foreach ($data[$groupBy] as $key => $value) {
                            if($key<$rm_limit)unset($data[$groupBy][$key]);
                        }
                    }

                    foreach ($data[$groupBy] as $key => $value) {
                        $output[] = $value;
                    }
                }
                $data_new = [];
        
                foreach ($output as $key => $value) {
                    $ad_url = [
                        'query'=>[
                            'term'=>[
                                'id'=>$value
                            ]
                        ]
                    ];
                    $ad_url['_source'] = config('param.ADDATA_FILED_SOURCE');
                    $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

                    $ad_rs = HttpUtil::send_curl_methods($url,$ad_url,"POST");

                    $ad_parse = json_decode($ad_rs,true);

                    if(!empty($ad_parse['hits']['hits'][0]['_source']))
                    {
                        $data_new[] = $ad_parse['hits']['hits'][0]['_source'];
                        
                    }
                }

                if($random==1)shuffle($data_new);

                $data_new2['status'] = 200;

                $data_new2['total'] = $total;
                $data_new2['data'] = $data_new;
                DBUtil::setRedisData($key_cache,$data_new2,config('param.REDIS_TIME'));
                return HttpUtil::retrun_json($data_new2,200);
        }
        // 风险 查询
        if($risk==1)
        {
            $is_flag_domain = DBUtil::getFlagById($value);

            $page_from = 0;
            if($page>1)$page_from = ($page-1) * $size;



            if(empty($is_flag_domain))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


             unset($url_query[$field]);

            if($role_value && $role_type)unset($url_query[$role_type]);
            // 此时 有可能是 tags 二级域名
            $value = $is_flag_domain[0]->host;

            $field_new = '';
            switch ($field) {
                case 'advertiser':
                    $field_new = 'advertiser_na';
                    break;
                case 'publisher':
                    $field_new = 'publisher_na';
                    break;
                case 'subject':
                    $field_new = 'subject_md5';
                    break;
                case 'tracker':
                    # code...
                    $field_new = 'trackers';
                    break;
            }

            if($shape)$url_query['shape'] = $shape;

            if($type)unset($url_query['type']);

                // $type = $type==1?'image'
            // type 3 flv, 2 swf , 1 image 4 html5 5 material = native(原生)
            $type_material = '';
            switch ($type) {
                 case '1':
                     $type_material = 'image';
                     break;
                case '2':
                    $type_material = 'swf';
                    break;
                case '3':
                    $type_material = 'flv';
                    break;
                 case '4':
                    $type_material = 'html5';
                    break;
                case '5':
                    $type_material = 'native';
                    break;
            }
            // if($type && $type!=5)$type = 
            $type_key = $type==5?'material':'type';


            if($role_value && $role_type)
            {
                $is_role_value__domain = DBUtil::getFlagById($role_value);
                unset($url_query[$role_type]);

                switch ($role_type) {
                    case 'advertiser':
                        $url_query['advertiser_na'] = $is_role_value__domain[0]->host;
                        break;
                    case 'publisher':
                        $url_query['publisher_na'] = $is_role_value__domain[0]->host;
                        break;
                }
            }



            if($type)$url_query[$type_key] = $type_material;

            $url_query['risk'] = 1;

            $curl_param = [
                'from'=>$page_from,
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
                                    $field_new=>$value
                                ]
                            ]
                        ]
                    ]
                ],
                '_source'=>[
                    // 'id','original_url','publisher_full','tracker_full','attribute07','publisher'
                    'id','original_url','publisher_full','tracker_full','publisher'
                ]
            ];

            if($platform)
            {
                if($platform<=2)$url_query['platform'] = $platform;

                if($platform==3)$url_query['attribute04'] = 'android';

                if($platform==4)$url_query['attribute04'] = 'ios';
            }
            foreach ($url_query as $key => $value) {
                $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$value]];
            }
            // 时间
            foreach ($param_range as $pk => $pv) {
                array_push($curl_param['query']['bool']['must'], $pv);
            }
            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);

            $data = DBUtil::get_parse_es_data($rs);

            if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


            $risk_id = [];
            foreach ($data['data'] as $rk => &$rv) {
                $risk_id[] = $rv->id;
                $rv->attribute07 = '';
            }


            $curl_risk_param = [
                'size'=>count($risk_id),
                'query'=>[
                    'terms'=>[
                        'id'=>$risk_id
                    ]
                ]
            ];

            $rs_risk = HttpUtil::parse_curl_search(config('param.ADDATA_ATTR_SEARCH'),$curl_risk_param);

            $data_risk = DBUtil::get_parse_es_data($rs_risk);

            if(!empty($data_risk['data']))
            {
                foreach ($data['data'] as $rk1 => $rv1) {
                    foreach ($data_risk['data'] as $kt => $vt) {
                        if($vt->id==$rv1->id)
                        {
                            $rv1->attribute07 = $vt->attribute07;
                            break;
                        }
                    }
                }
            }
            

            if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

            $data['status'] = 200;
            DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
            return HttpUtil::retrun_json($data,200);

            //上一个 版本
        }

        if($shape)$url_query['shape'] = $shape;


        if($groupBy=='id')
        {
            if($type)unset($url_query['type']);
            if($field!='subject')
            {
                $is_flag_domain = DBUtil::getFlagById($value);
                if(empty($is_flag_domain))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
                unset($url_query[$field]);
                $field = Tools::filedParse($field,3)['type_field'];
                $url_query[$field] = $is_flag_domain[0]->host; 
            }

            if($role_value && $role_type)
            {
                $is_role_value__domain = DBUtil::getFlagById($role_value);
                unset($url_query[$role_type]);

                $role_type = Tools::filedParse($role_type,3)['type_field'];
                $url_query[$role_type] = $is_role_value__domain[0]->host; 
            }
            $from_size = $page>1?$page*($size-1):0;
            // 最新版本 更改
            $curl_param = [
                'from'=>$from_size,
                'size'=>$size,
                'sort'=>[
                    [
                        'created_date'=>[
                            'order'=>'desc'
                        ]
                    ],
                    [
                        $groupBy=>[
                            'order'=>'desc'
                        ]
                    ]
                ],
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
                    ]
                ]
            ];

            if($platform)
            {
                $field_tmp = Tools::filedParse($platform,2);

                if($platform==5){
                    $url_query['platform'] = 4;
                }else{
                    $url_query[$field_tmp['type_field']] = $field_tmp['type_material'];
                }
                
            }
            if($type)
            {
                // $type = $type==1?'image'
                // type 3 flv, 2 swf , 1 image 4 html5 5 material = native(原生)
                $field_tmp = Tools::filedParse($type,1);
                $url_query[$field_tmp['type_field']] = $field_tmp['type_material'];
            }
            
            foreach ($url_query as $key => $value) {
                $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$value]];
            }

            // 判断 是否是订阅信息  创意

            if($isbinlog==1)
            {

                // $before_day = date("Y-m-d",strtotime("-1 day"));//一天前
                // $start_time = strtotime($before_day.' 00:00:00').'000';
                // $end_time = strtotime($before_day.' 23:59:59').'000';
                $b_time = empty($b_time)?date("Y-m-d",strtotime("-1 day")):$b_time;

                $start_time = strtotime($b_time.' 00:00:00').'000';
                $end_time = strtotime($b_time.' 23:59:59').'000';

                $bin_t_range = [
                    'range'=>[
                        'created_date'=>[
                            'gte'=>$start_time,
                            'lte'=>$end_time
                        ]
                    ]
                ];
                array_push($curl_param['query']['bool']['must'], $bin_t_range);
                // $curl_param['query']['bool']['must']
            }else{
                $param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);

                foreach ($param_range as $pk => $pv) {
                    array_push($curl_param['query']['bool']['must'], $pv);
                }
            }
            // 这一步在后续实现
            // if($orderBy)$curl_param['sort']=[$orderBy=>['order'=>'desc']];
            $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);

            $data = DBUtil::get_parse_es_data($rs);
            $rs_des = json_decode($rs,true);
            $total = empty($rs_des['aggregations']['group_count']['value'])?0:$rs_des['aggregations']['group_count']['value'];

            $data['total'] = $total;

            DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
            return HttpUtil::retrun_json($data,200);
        }

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
                        'size'=>$all_random,
                        'order'=>[
                            [
                                'max_date'=>'desc'
                            ],
                            [
                                'max_filed'=>$sort
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'max_date'=>[
                            'max'=>[
                                'field'=>'created_date'
                            ]
                        ],
                        'max_filed'=>[
                            'max'=>[
                                'field'=>$groupBy
                            ]
                        ]
                    ]
                ]

            ]
        ];

        $platform_range = [];
        if($platform)
        {
            if($platform>2)$url_query['platform'] = $platform;

            if($platform==2)
            {
                $platform_range = [
                    'range'=>[
                        'platform'=>[
                            'gte'=>$platform
                        ]
                    ]
                ];
            }

        }
        foreach ($url_query as $key => $value) {
            $curl_param['query']['bool']['must'][] = ['term'=>[$key=>$value]];
        }


        if(!empty($platform_range))
        {
            array_push($curl_param['query']['bool']['must'], $platform_range);
        }


        if($isbinlog==1)
        {

            // $before_day = date("Y-m-d",strtotime("-1 day"));//一天前
            $b_time = empty($b_time)?date("Y-m-d",strtotime("-1 day")):$b_time;
            $start_time = strtotime($b_time.' 00:00:00').'000';
            $end_time = strtotime($b_time.' 23:59:59').'000';

            $bin_t_range = [
                'range'=>[
                    'created_date'=>[
                        'gte'=>$start_time,
                        'lte'=>$end_time
                    ]
                ]
            ];
            array_push($curl_param['query']['bool']['must'], $bin_t_range);
            // $curl_param['query']['bool']['must']
        }else{
            $param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);

            foreach ($param_range as $pk => $pv) {
                array_push($curl_param['query']['bool']['must'], $pv);
            }
        }


        // if($orderBy)$curl_param['sort']=[$orderBy=>['order'=>'desc']];
        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);
        $rs_des = json_decode($rs,true);
        $total = empty($rs_des['aggregations']['group_count']['value'])?0:$rs_des['aggregations']['group_count']['value'];

        $aggs = empty($rs_des['aggregations']['group_by_state']['buckets'])?[]:$rs_des['aggregations']['group_by_state']['buckets'];
        $data[$groupBy] = [];
        foreach ($aggs as $key => $value) {
            $data[$groupBy][] = $value['key'];
        }
        if(empty($data[$groupBy]))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        // shuffle($chars);   
        $output = []; 
        if($random)
        {
            shuffle($data[$groupBy]);
            $charsLen = count($data[$groupBy]) - 1; 
            for ($i=0; $i<$size_all; $i++) 
            { 
                $output[] = $data[$groupBy][mt_rand(0, $charsLen)]; 
            }
        }else{
            if($page>1)
            {
                $rm_limit = $size * ($page-1);
                foreach ($data[$groupBy] as $key => $value) {
                    if($key<$rm_limit)unset($data[$groupBy][$key]);
                }
            }

            foreach ($data[$groupBy] as $key => $value) {
                $output[] = $value;
            }
        }
        $data_new = [];
        foreach ($output as $key => $value) {
            # code...
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

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);
            $rs_de = json_decode($rs,true);

            $data_new['data'][] = $rs_de['hits']['hits'][0]['_source'];
        }
        // 暂无数据 2002
        if(empty($data_new['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


        if($random==1)shuffle($data_new['data']);



        foreach ($data_new['data'] as $k => &$v) {
            $ad_url = [
                'query'=>[
                    'term'=>[
                        'id'=>$v['id']
                    ]
                ]
            ];
            // $ad_url['_source'] = config('param.ADDATA_FILED_SOURCE');
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source=id,type,attribute08,thumbnail';

            $ad_rs = HttpUtil::send_curl_methods($url,$ad_url,"POST");

            $ad_parse = json_decode($ad_rs,true);

            if(!empty($ad_parse['hits']['hits'][0]['_source']))
            {
                $v['type'] = $ad_parse['hits']['hits'][0]['_source']['type'];
                if($ad_parse['hits']['hits'][0]['_source']['type']=='html5')
                {
                    $v['attribute08'] = $ad_parse['hits']['hits'][0]['_source']['attribute08'];
                }
                
            }
        }
        // unset($value);

        if($sortmode==1)
        {
            $data_new['data'] = DBUtil::my_sort($data_new['data'],'created_date',SORT_ASC,SORT_NUMERIC);
        }

        $data_new['status'] = 200;
        $data_new['total'] = $total;
        DBUtil::setRedisData($key_cache,$data_new,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data_new,200);
    }


    /**
     * 根据 字段 获取 信息
     * @return [type] [description]
     */
    public function getInfoGroupByFiledParam(Request $request)
    {
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
            ]
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

    /**
     * 2017-6-7 新增 
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getInfoByDidRole(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $role_value = empty($request->value)?'':$request->value;
        $field = empty($request->field)?'':$request->field;

        $filed_value = empty($request->filed_value)?'':$request->filed_value;
        $ad_type = empty($request->ad_type)?0:$request->ad_type;
        $platform = empty($request->platform)?0:$request->platform;

        $size = empty($request->size)?10:$request->size;
        $page = empty($request->page)?1:$request->page;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $from = 0;
        if($page>1)$from = $page * $size;



        $shape = empty($request->shape)?'':$request->shape;

        $date = empty($request->date)?'':$request->date;
        $sortmode = empty($request->sortmode)?'':$request->sortmode;
        $min_width = empty($request->min_width)?'':$request->min_width;
        $width = empty($request->width)?'':$request->width;
        $min_height = empty($request->min_height)?'':$request->min_height;
        $height = empty($request->height)?'':$request->height;

        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;



        if(empty($start_time) && empty($end_time))
        {
            $date_tmp = [];
            switch ($date) {
                case '1':
                    $date_tmp = DBUtil::getOneYearNew();
                    break;
                case '2':
                    $date_tmp = DBUtil::getNowMothNew();
                    break;
                case '3':
                    $date_tmp = DBUtil::getOneWeekNew();
                    break;
                case '4':
                    $date_tmp['start_time'] = date('Y-m-d',time());
                    $date_tmp['end_time'] = date('Y-m-d',time());

                    break;
            }
            if($date)
            {
                $start_time = strtotime($date_tmp['start_time'].' 00:00:00').'000';
                $end_time = strtotime($date_tmp['end_time'].' 23:59:59').'000';
            }
            
        }else{
            $start_time = $start_time?strtotime($start_time.' 00:00:00').'000':'';
            $end_time = $end_time?strtotime($end_time.' 23:59:59').'000':'';
        }

        $min_width = min($min_width,$width);
        $max_width = max($min_width,$width);
        $min_height = min($min_height,$height);
        $max_height = max($min_height,$height);




        $filterfield = [];
        if($field)$filterfield[$field] = $filed_value;
        if($ad_type)$filterfield['type'] = $ad_type;
        if($platform)$filterfield['platform'] = $platform;
        $size_all = $page * $size;


        $is_tag = empty($request->is_tag)?'':$request->is_tag;



        if($field=='tracker')
        {
            $is_flag_domain = DBUtil::getFlagById($filed_value);

            if(empty($is_flag_domain))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

            if($is_flag_domain[0]->flag==1 || $is_tag==1)
            {
                $filed_value = $is_flag_domain[0]->host;

                $is_role_domain = DBUtil::getFlagById($role_value);

                $role_value = $is_role_domain[0]->host;

                unset($filterfield[$field]);
                $filterfield['tags_list']=$filed_value;

                switch ($role) {
                    case 'advertiser':
                        $role = 'advertiser_na';
                        break;
                    case 'publisher':
                        $role = 'publisher_na';
                        break;
                    
                    default:
                        # code...
                        break;
                }

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
                                ]
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

                $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
                // dd(json_encode($curl_param));
                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
                $data = DBUtil::get_parse_es_data($rs);

                $rs_des = json_decode($rs,true);
                $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];
                $aggs = empty($rs_des['aggregations']['publisher']['buckets'])?[]:$rs_des['aggregations']['publisher']['buckets'];

                if(!empty($aggs))
                {
                    $data[$orderBy] = [];

                    foreach ($aggs as $key => $value) {
                        $data[$orderBy][] = $value['key'];
                    }
                    //$rm_limit = 0;
                    if($page>1)
                    {
                        $rm_limit = $size * ($page-1);
                        // $rm_limit = 0;
                        foreach ($data[$orderBy] as $key => $value) {
                            if($key<$rm_limit)unset($data[$orderBy][$key]);
                        }
                    }
                    if(empty($data[$orderBy]))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
                }
                
                $data['status'] = 200;
                $data['total'] = $total;
                return HttpUtil::retrun_json($data,200);
            }

            
        }

        if($shape)$filterfield['shape'] = $shape;

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
                        ]
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

        $param_range = DSLUtil::rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height);

        foreach ($param_range as $pk => $pv) {
            array_push($curl_param['query']['bool']['must'], $pv);
        }

        

        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        $rs_des = json_decode($rs,true);


        $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];
        $aggs = empty($rs_des['aggregations']['publisher']['buckets'])?[]:$rs_des['aggregations']['publisher']['buckets'];



        if(!empty($aggs))
        {
            $data[$orderBy] = [];

            foreach ($aggs as $key => $value) {
                $data[$orderBy][] = $value['key'];
            }
            //$rm_limit = 0;
            if($page>1)
            {
                $rm_limit = $size * ($page-1);
                // $rm_limit = 0;
                foreach ($data[$orderBy] as $key => $value) {
                    if($key<$rm_limit)unset($data[$orderBy][$key]);
                }
            }
            if(empty($data[$orderBy]))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        }
        
        if($sortmode==1)
        {
            $data[$orderBy] = DBUtil::my_sort($data[$orderBy],'created_date',SORT_ASC,SORT_NUMERIC);
        }

        
        $data['status'] = 200;
        $data['total'] = $total;
        return HttpUtil::retrun_json($data,200);
    }


    //=====================下个版本丢弃=======================
    
    /**
     * 根据 当前角色 去获取 想要获取的domain 信息
     * cr 当前 角色
     * cr_v 当前角色对应的编号
     *
     * qr 想要过滤的角色 
     * qr_v 想要 获取的对应 编号
     * 
     * @return [type] [description]
     */
    public function getDomianIdBYRole(Request $request)
    {
        $current_role = empty($request->cr)?'':$request->cr;
        $current_role_value = empty($request->cr_v)?'':$request->cr_v;

        $query_role = empty($request->qr)?'':$request->qr;
        $query_role_value = empty($request->qr_v)?'':$request->qr_v;

        $domain_id = explode('-', $query_role_value);


        if(empty($current_role) && empty($current_role_value) && empty($query_role) && empty($query_role_value))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }

        $domain_tmp = [];

        foreach ($domain_id as $key => $value) {

            if($value)$domain_tmp[] = $value;
        }


        $db_data = DB::table('main_index')
        ->where($current_role,$current_role_value)
        ->whereIn($query_role,$domain_tmp)->get();


        $data_tmp = [];
        foreach ($db_data as $key => $value) {

            if(!in_array($value->$query_role, $data_tmp))$data_tmp[] = $value->$query_role;
        }


        // foreach ($domain_id as $key => $value) {
        //     $curl_param = [
        //         'size'=>1,
        //         'query'=>[
        //             'bool'=>[
        //                 'must'=>[
        //                     [
        //                         'term'=>[
        //                             $current_role=>$current_role_value
        //                         ]
        //                     ],
        //                     [
        //                         'term'=>[
        //                             $query_role=>$value
        //                         ]
        //                     ]
        //                 ]
        //             ]
        //         ]
        //     ];
        //     $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);
        //     $rs_data = json_decode($rs,true);

        //     if(!empty($rs_data['hits']['hits']))
        //     {
        //         $domain_tmp[] = $value;
        //     }
        // }

        // $curl_param_domain = [
        //     'query'=>[
        //         'filtered'=>[
        //             'filter'=>[
        //                 'terms'=>[
        //                     'id'=>$domain_tmp
        //                 ]
        //             ]
        //         ]
        //     ]
        // ];

        // $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param_domain);
        // $data = DBUtil::get_parse_es_data($rs);
        // // 暂无数据 2002
        // if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['data'] = $data_tmp;
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
        
    }
}
