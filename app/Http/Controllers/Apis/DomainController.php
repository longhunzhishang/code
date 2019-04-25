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
 * domain api
 *  header('Access-Control-Allow-Origin:*');
 */
class DomainController extends Controller
{

    /**
     * 获取 domain 所有 信息 
     * @return [type] [description]
     */
    public function getAll(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($sort && !in_array($sort, ['desc','asc']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        
        if(!is_numeric($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $curl_param = [
            // 'sort'=>[
            //     '_score'=>'desc'
            // ],
            'size'=>$size
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;
        

        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 根据　host　获取　domain　信息
     * trackers publisher advertiser
     * @return [type] [description]
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
            // 'sort'=>[
            //     '_score'=>'desc'
            // ],
            'size'=>$size,
            'query'=>[
                'term'=>[
                    'host'=>$host
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;


        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 host 获取 数据
     * host 数组
     * @return [type] [description]
     */
    public function getInfoInIds(Request $request)
    {

        $param = $request->param;
        $size = $request->size?10:$request->size;

        $from = empty($request->from)?0:$request->from;



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
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'should'=>$should_arr
                ]
            ]
        ];
        if(empty($from))unset($curl_param['from']);

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 根据 host 获取 数据
     * host 数组
     * @return [type] [description]
     */
    public function getInfoInHosts(Request $request)
    {

       $param = $request->param;
       $param = urldecode($param);

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $param = explode('_domain_id_', $param);

        $size = count($param);

        $should_arr = [];

        foreach ($param as $key => $value) {

            if($value)
            {
                $should_arr[] = [
                    'term'=>[
                        'host'=>$value
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

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;
        
        return HttpUtil::retrun_json($data,200);

    }

      /**
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function getInfoByField(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $role =empty($request->role)?'':$request->role;


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
                'bool'=>[
                    'must'=>[
                        [
                            'multi_match'=>[
                                'query'=>$param,
                                'fields'=>[
                                    'host','cname','ename'
                                ],
                                'minimum_should_match'=>'30%'
                            ]
                        ]//,
                        // [
                        //     'term'=>[
                        //         'role'=>$role
                        //     ]
                        // ]
                    ]
                ]
                
            ]
        ];

        if($role)
        {
            $item = [
                'term'=>[
                    'role'=>$role
                ]
            ];
            array_push($curl_param['query']['bool']['must'], $item);
        }

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.DOMAIN_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }


       /**
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function getInfoByField2(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $role =empty($request->role)?'':$request->role;


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
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'host'=>$param
                            ]
                        ]
                    ]
                ]
                
            ]
        ];

        if($role)
        {
            $item = [
                'term'=>[
                    'role'=>$role
                ]
            ];
            array_push($curl_param['query']['bool']['must'], $item);
        }

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.DOMAIN_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 首页 查询 使用
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function getQueryInfoByField(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        // 字段 排序
        $orderBy = empty($request->orderBy)?'':$request->orderBy;


        // if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        // if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

         //多字段查询
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

        // dd(json_encode($curl_param));

        if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 根据 编号 获取 domain 信息
     * @return [type] [description]
     */
    public function getInfoById(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);

        $es = ESUtil::getInfoById(config('param.DOMAIN_SEARCH'),$id);

        $data = DBUtil::pareseESDate($es,1);
        
        return HttpUtil::retrun_json($data,200);

    }
      /**
     * 根据 编号 获取 domain 信息
     * @return [type] [description]
     */
    public function getInfoByMd5(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;

        if(empty($md5))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $es = ESUtil::getInfoByMd5(config('param.DOMAIN_SEARCH'),$md5);

        $data = DBUtil::pareseESDate($es,1);

        return HttpUtil::retrun_json($data,200);

    }


      /**
     * 根据 字段 统计 数目
     * @return [type] [description]
     */
    public function getInfo(Request $request)
    {
        $param = empty($request->field)?'':$request->field;

        $value = empty($request->value)?'':$request->value;

        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if(empty($field) && empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);


        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            $field=>$value
                        ]
                    ]
                ]
            ],
            'size'=>$size,
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
 

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $ad_id = DBUtil::get_parse_es_data($rs);
       

        $da = json_decode($rs,true);
        

        if(empty($da['hits']['hits']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $item['ads'] = empty($da['aggregations']['ads']['value'])?'':$da['aggregations']['ads']['value'];
        $item['subjects'] = empty($da['aggregations']['subjects']['value'])?'':$da['aggregations']['subjects']['value'];
       
        $data['data'] = $ad_id['data'];
        $data['status'] = 200;
        $data['total'] = $ad_id['total'];
        $data['count'] = $item;

      
        return HttpUtil::retrun_json($data,200);



    }
    /**
     * 根据 字段 进行排序
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getRoleFilter(Request $request)
    {
        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $role = empty($request->role)?'':$request->role;
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->wd)?'':$request->wd;
        $page = empty($request->page)?1:$request->page;

        
        $from = 0;

        if($page>1)$from = $page * $size;

        if(empty($orderBy) || empty($role) || empty($size) || empty($param) || ($size && !is_numeric($size)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        
        if($size > config('param.MAXSIZE'))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'sort'=>[
                $orderBy=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'multi_match'=>[
                            'query'=>$param,
                            'type'=>'cross_fields',
                            'operator'=>'or',
                            'fields'=>[
                                'host','cname','ename'
                            ]
                        ],
                        'match'=>[
                            'term'=>[
                                'role'=>$role
                            ]
                        ]
                    ]
                    
                ]
                
            ]
        ];

        if(!$orderBy)unset($curl_param['sort']);


        // dd(json_encode($curl_param));

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        // rsort($data['data']);
        
        $data['status'] = 200;

        // dd($data);

        return HttpUtil::retrun_json($data,200);
    }



    /**
     * 根据 字段 进行排序
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getRoleFilter2(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;
        
        // 媒体 1 广告主 2 追踪者 3
        $publiser = $this->sendFilters($param,1);
        $advertiser = $this->sendFilters($param,2);
        $trackers = $this->sendFilters($param,3);
        $dictinfo = $this->getRelationDicts($param);

        $data['publiser'] = $publiser;
        $data['advertiser'] = $advertiser;
        $data['trackers'] = $trackers;
        $data['dictinfo'] = $dictinfo;
        
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }


    public function getRelationDicts($param)
    {

        // 第一步 获取 所有 条数

        $curl_param = [
            'size'=>20,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'query_string'=>[
                            'default_field'=>'_all',
                            'query'=>$param
                        ]
                    ]
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DICTS_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data))return HttpUtil::retrun_json([],200);


        $total = 10;
        // 如果 条数 大于 10
        if($data['total'] > 10)
        {
            $total = $data['total'];
            $curl_param['size'] = $total;
        }

        $rs = HttpUtil::parse_curl_search(config('param.DICTS_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);



        // $dicts = Dicts::where('title','like','%'.$param.'%')->get();
        // if(!$dicts)return HttpUtil::retrun_json([],200);
        $dict_info = [];
         foreach ($data['data'] as $key => $value) {

            $value = (array)$value;
                if($param==$value['title'])unset($data['data'][$key]);

                if(strlen($value['title']) > 10) unset($data['data'][$key]);

        }



        $title_filter = [];
        foreach ($data['data'] as $key => $value) {

                $value = (array)$value;

                if($value['title'] && count($dict_info)<11)
                {

                    if(!in_array($value['title'], $title_filter))
                    {
                        $title_filter[] = $value['title'];
                        $curl_param = [
                            'size'=>1,
                            'query'=>[
                                'multi_match'=>[
                                    'query'=>$value['title'],
                                    'fields'=>[
                                        'advertiser_name','advertiser_name_title','title'
                                    ]
                                ]
                            ]
                        ];
                        
                        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

                        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
                        $isexist = DBUtil::get_parse_es_data($rs);
                        if(!empty($isexist['data']))
                        {
                            $dict_info[] = $value;
                        }
                    }
                    
                }                
        }


        return $dict_info;

    }

    /**
     * role 1 publisher_ads  2 brand_subjects 3 tracker_subjects
     * @param  [type] $param [description]
     * @param  [type] $role  [description]
     * @return [type]        [description]
     */
    public function sendFilters($param,$role)
    {


        $sort = 'brand_subjects'; // 2

        switch ($role) {
            case '1':
                $sort = 'publisher_ads';
                break;
            case '2':
                $sort = 'brand_subjects';
                break;
            case '3':
                $sort = 'tracker_subjects';
                break;
        }
        $curl_param = [
            'from'=>0,
            'size'=>5,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'multi_match'=>[
                            'query'=>$param,
                            'fields'=>[
                                'host','cname','ename'
                            ]
                        ],
                        'match'=>[
                            'term'=>[
                                'role'=>$role
                            ]
                        ]
                    ]
                    
                ]
                
            ],
            'sort'=>[
                $sort=>[
                    'order'=>'desc'
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        $publiser_return=[];
        if(!empty($data['data']))
        {
            
            foreach ($data['data'] as $key => $value) {
                $value = (array)$value;
                $itme['id'] = $value['id'];
                $itme['host'] = $value['host'];
                

                if(empty($value['cname']) && empty($value['ename'])){
                        $itme['name'] = $value['host'];
                }else{
                    $itme['name'] = $value['host'].' '.$value['cname'];
                }
                $itme['md5'] = $value['md5'];
                $publiser_return[]=$itme;
            }
        }
        return $publiser_return;
    }


        /**
     * 根据 字段 获取 信息
     * @return [type] [description]
     */
    public function getInfoGroupByFiled(Request $request)
    {

        $field = empty($request->field)?'':$request->field;
        $value = empty($request->value)?'':$request->value;

       
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $orderBy = empty($request->orderBy)?'':$request->orderBy;

       
        if(empty($field)||empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        $curl_param = [
            // 'sort'=>[
            //     '_score'=>'desc'
            // ],
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            $field=>$value
                        ]
                    ],
                    'should'=>[
                        'cname'=>$param
                    ]
                ],
            ],
            'aggs'=>[
                'group_by_state'=>[
                    'terms'=>[
                        'field'=>$groupBy
                    ]
                ]
            ]
        ];

        if($orderBy)$curl_param['sort']=[$orderBy=>['order'=>'desc']];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 根据 role role_value host
     * 获取 信息
     * @return [type] [description]
     */
    public function getInfoByRole(Request $request)
    {
        $field = empty($request->field)?'':$request->field;
       
        $host = empty($request->host)?'':$request->host;

        if(empty($field) || empty($host))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $field=>1
                            ]
                        ],
                        [
                            'match'=>[
                                'host'=>$host
                            ]
                        ]
                    ]
                ]
                
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }


    //以下所有 API　暂时没用用到　＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝


    /**
     * 分组 查询 
     * groupBy
     * 20160706
     * finn
     *  type 1 
     */
    public function getAllGroup()
    {
        $page = empty($request->page)?1:$request->page;
        $content = empty($request->content)?'':$request->content;
        $size = empty($request->size)?config('param.PAGE_LIMIT'):$request->size;
        $last_id = empty($request->last_id)?0:$request->last_id;

        $type = empty($request->type)?'':$request->type;
         //参数不可为空 2001
        if(empty($content) || empty($type))return HttpUtil::retrun_json([],200);

        switch ($type) {
            case '1':
                # code...
                $type = 'brand_subjects';
                break;
            case '2':
                $type = 'publisher_ads';
                break;
            case '3':
                # code...
                $type = 'tracker_advertiser';
                break;
        }

        $curl_param = [
            'sort'=>[
                $type=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size,
            'query'=>[
                'match'=>[
                    'host'=>$content
                ]
            ]
        ];


        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json($data,2002);


        $data['last_id'] = $data['data'][0]->id;

        if($sort=='asc')$data['last_id'] = $data['data'][$size-1]->id;


        $data['status'] = empty($data['data'])?201:200;

        return HttpUtil::retrun_json($data,200);
    }

   

     /**
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function getRolesinfo(Request $request)
    {

        $host = empty($request->host)?'':$request->host;
        $role = empty($request->role)?'':$request->role;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        if(empty($host) || empty($role) || !is_numeric($role) || $role>3)return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $role = ($role==1?'isbrand':($role==2?"ispublisher":"istracker"));


        $se = Search::index('domain')->search('host',$host)->where($role,1)->limit($size)->get();
        $data['data'] = $se;
        $data['total'] = $size;
        $data['status'] = empty($se)?203:200;
        return HttpUtil::retrun_json($data,200);

    }

    
    /**
     * 根据　搜索内容 获取 domain　信息
     * trackers publisher advertiser
     * @return [type] [description]
     */
    public function getQueryInfo(Request $request)
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
            // 'sort'=>[
            //     '_score'=>'desc'
            // ],
            'size'=>$size,
            'query'=>[
                'match'=>[
                    'host'=>$host
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;

        dd($data);


        return HttpUtil::retrun_json($data,200);
    }


    public function getQueryAllInfo(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $host = empty($request->host)?'':$request->host;

        if(empty($host))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        if($size && !is_numeric($size)) return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $se = Search::index(config('param.DOMAINS'))->search(null,$host)->limit($size)->get();

        if(empty($se))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['data']=$se;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }


    //最新 版本 api  调用
    /**
     * field 查询字段 
     * param 对应的值
     * $return [] 返回结果
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getInfoByField_3(Request $request)
    {

        $field = $request->field;
        $param = $request->param;

        if(empty($field))return json_encode(['data'=>[],'status'=>206,'msg'=>'参数不可为空']);

        $curl_param = [
            'query'=>[
                'term'=>[
                    $field=>$param
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'][0])?203:200;
        $data['data'] = $data['data'][0];

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取 所有信息
     */
    public function getAll_3(Request $request)
    {
        $size = empty($request->size)?config('param.size'):$request->size;
        $page = empty($request->page)?0:$request->page;

        $from = $page * $size;

        $curl_param = [
            'size'=>$size,
            'from'=>$from
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;
        
        return HttpUtil::retrun_json($data,200);
    }
}
