<?php

namespace App\Http\Controllers\Apis;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtil;


use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;

use Elasticsearch\Client;

use Illuminate\Http\Response;
use Search;

/**
 *  1 获取 所有 域名 
 *  2 domians sort 排序
 *  3 getPubTacAdsIndex 广告主 媒体 追踪者 index  接口
 */

class ApiController extends Controller
{


     /**
     */
    public function deleteJDB(Request $request)
    {
        
        $curl_param = [
            'size'=>6197,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'domain'=>'com.hunantv.imgo.activity'
                            ]
                        ],
                         [
                            'term'=>[
                                'type'=>'flv'
                            ]
                        ],
                         [
                            'term'=>[
                                'platform'=>'2'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        
        $rs_data = json_decode($rs,true);

        $data = $rs_data['hits']['hits'];

        $exist = [
        8933167,8936208,8940305,9005859
        ];


        // $url = 'http://192.168.1.47:9200/addatas/addata_index/4548058';

        // file_get_contents($url);
        foreach ($data as $key => $value) {
            if(!empty($value['_source']['id']) && !in_array($value['_source']['id'], $exist))
            {
                $url = 'http://192.168.1.47:9200/addatas/addata_index/'.$value['_source']['id'];

                // file_get_contents($url);


                 $ch = curl_init(); //初始化CURL句柄 
                 curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出 
                    curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); //设置请求方式
                    // curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                    // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
                    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
                    $rs = curl_exec($ch);//执行预定义的CURL 
                    curl_close($ch);
            }else{
                echo $value['_source']['id'];
                echo '<br/>';
            }
        }


        // $data = DBUtil::get_parse_es_data($rs);

        // foreach ($data as $key => $value) {
        //     // print_r($value);
        //     $value = (array)$value;


        // }

    }
    /**
     * type 1 addata 2 mainindex 3 subject 4 domain
     */
    public function getMaxId(Request $request)
    {
       
        $type = $request->type;
        $field = ($type==2?'index_id':'id');

        $search = '';

        switch ($type) {
            case '1':
                $search = config('param.ADDATA_SEARCH');
                break;
            case '2':
                $search = config('param.MAININDEX_SEARCH');
                break;
            case '3':
                $search = config('param.SUBJECT_SEARCH');
                break;
            case '4':
                $search = config('param.DOMAIN_SEARCH');
                break;
            case '5':
                $search = config('param.COOKIE_SEARCH');
                break;
            case '6':
                $search = 'test/test_index';
                break;
            case '7':
                $search = 'amtrue/addata_index';
                break;
        }


        $curl_param = [
            'size'=>0,
            'aggs'=>[
                'maxid'=>[
                    'max'=>[
                        'field'=>$field
                    ]
                ]
            ]
           
        ];

        $rs = HttpUtil::parse_curl_search($search,$curl_param);
        
        $rs = json_decode($rs);

        return empty($rs->aggregations->maxid->value)?0:$rs->aggregations->maxid->value;

    }

    /**
     * {
  "size": 0,
  "aggs": {
    "queryinfo": {
      "terms": {
        "field": "query_info",
        "size": 100
      }
    }
  }
}
     * 获取 用户 搜索的最多的数据
     * @return [type] [description]
     */
    public function getCookieTop10(Request $request)
    {

        $curl_param = [
            'size'=>0,
            'aggs'=>[
                'queryinfo'=>[
                    'terms'=>[
                        'field'=>'query_info',
                        'size'=>20
                    ]
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.COOKIE_SEARCH'),$curl_param);
        
        $rs = json_decode($rs);

        $info_tmp = empty($rs->aggregations->queryinfo->buckets)?[]:$rs->aggregations->queryinfo->buckets;

        $data = [];
        $filter_redis = [
            '18av','美女秀场','第一坊','Array','array'
        ];

        foreach ($info_tmp as $key => $value) {
            if(count($data)<10)
            {
                if(!in_array($value->key, $filter_redis))$data[] = $value->key;
            }
        }

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 角色 查询 
     * 20160712
     * finn
     * type 1 根据角色 值  获取对应的数据 2 统计该角色的总条数 默认1 
     * @return [type] [description]
     */
    public function getInfoByRole(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $rolevalue = empty($request->rolevalue)?'':$request->rolevalue;
        $size = empty($request->size)?1:$request->size;


//         {
//     "query": {
//         "bool": {
//             "must": [
//                 {
//                     "term": {
//                         "tracker": "12"
//                     }
//                 }
//             ]
//         }
//     },
//     "size": 20,
//     "aggs": {
//         "publishers": {
//             "cardinality": {
//                 "field": "publisher"
//             }
//         },
//         "advertisers": {
//             "cardinality": {
//                 "field": "advertiser"
//             }
//         }
//     }
// }

        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            $role=>$rolevalue
                        ]
                    ]
                ]
            ],
            'size'=>$size
        ];

        $curl_param = $this->getRoleCurl($curl_param,$role);



        $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

        $ad_da = DBUtil::get_parse_es_data($rs);

        $ad_id = empty($ad_da['data'][0]->id)?0:$ad_da['data'][0]->id;

        

        $da = json_decode($rs,true);
        

        if(empty($da['hits']['hits']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $item['ads'] = empty($da['aggregations']['ads']['value'])?'':$da['aggregations']['ads']['value'];
        $item['subjects'] = empty($da['aggregations']['subjects']['value'])?'':$da['aggregations']['subjects']['value'];
        $item['advertisers'] = empty($da['aggregations']['advertisers']['value'])?'':$da['aggregations']['advertisers']['value'];
        $item['publishers'] = empty($da['aggregations']['publishers']['value'])?'':$da['aggregations']['publishers']['value'];
        $item['trackers'] = empty($da['aggregations']['trackers']['value'])?'':$da['aggregations']['trackers']['value'];
        // $item['maxid'] = empty($da['aggregations']['maxid']['value'])?'':$da['aggregations']['maxid']['value'];
        // $item['minid'] = empty($da['aggregations']['minid']['value'])?'':$da['aggregations']['minid']['value'];


        if(empty($ad_da['data'][0]->advertiser))$item['ads']=0;
        if(empty($ad_da['data'][0]->subject))$item['subjects']=0;
        if(empty($ad_da['data'][0]->advertiser))$item['advertisers']=0;
        if(empty($ad_da['data'][0]->publisher))$item['publishers']=0;
        if(empty($ad_da['data'][0]->tracker))$item['trackers']=0;
        


        $maxid = empty($da['aggregations']['maxid']['value'])?'':$da['aggregations']['maxid']['value'];
        $minid = empty($da['aggregations']['minid']['value'])?'':$da['aggregations']['minid']['value'];

        $data['data'] = $item;
        $data['status'] = 200;
        $data['ad_id'] = $ad_id;
        $data['maxid'] = empty($maxid)?'':$this->geDateById($maxid);
        $data['minid'] = empty($minid)?'':$this->geDateById($minid);

        return HttpUtil::retrun_json($data,200);
    }




    /**
     * 获取 addata 信息
     * 20170705
     */
    protected function getAddataInfo(Request $request)
    {
        $param = empty($request->param)?'':$request->param;


        $size = empty($request->size)?config('page.PAGE_LIMIT'):$request->size;


        $curl_param = [
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size
        ];

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);

      
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 根据 id 获取  addata 信息
     * @return [type] [description]
     */
    protected function getAddataById(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($id))return HttpUtil::retrun_json([],200);

        $rs = ESUtil::getInfoById(config('param.ADDATA_SEARCH'),$id);
        return HttpUtil::retrun_json($rs,200);
    }


     /**
     * 根据 id 获取  addata 信息
     * @return [type] [description]
     */
    protected function geDateById($id)
    {
        $rs = ESUtil::getInfoById(config('param.ADDATA_SEARCH'),$id);

        return empty($rs[0]->created_date)?'':$rs[0]->created_date;
    }


    




    /**
     * 收索 营销活动
     * platform 1 pc  2 移动端  0 所有平台 【所有平台】
     * type 1 flush 2 图片 3 视频 0 所有 【所有形式】
     * date 时间  【不限时间】
     * shape 尺寸 【所有尺寸】
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getSearchSubject(Request $request)
    {

        $this->_wd = $request->wd;

        if(empty($this->_wd))return HttpUtil::retrun_json([],config('param.PARAM_NULL'),false);

        $type = empty($request->type)?'':$request->type;
        $platform = empty($request->platform)?'':$request->platform;
        


        $filterBox = config('param.SEA_SUB_FILTER');

        $selected = DBUtil::parse_filter($filterBox['filters'],$request,2);

        $args = array();
        foreach ($selected as $key => $select) {
            $args['filters'][$select['value']] = $select['selected']['value'];
        }



  
        $pages = empty($request->pages)?1:$request->pages;
        $limit = empty($request->limit)?100:$request->limit;

        if($type)
        {
            $type = ($type==1?'image':($type==2?'swf':'flv'));
        }

        $data['filterBox'] = $filterBox;
        // 暂时不实现
        // $args['sort'] = '@id DESC, @count DESC , @relevance DESC';
        // $args['limit'] = empty($request->limit)?100:$request->limit;

        // $args['groupby'] = 'subject';
        $orderBy = 'id';
        $filters['title'] = $this->_wd;
        if($type)$filters['type'] = $type;
        if($platform)$filters['platform'] = $platform;
        
        $curl_param = ESUtil::es_param_parse_search($limit,$orderBy,$filters);

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH'),$curl_param);

        $parse_data = DBUtil::get_parse_es_data($rs);

        return HttpUtil::retrun_json($parse_data,200);


    }


    
   

    /**
     *  追踪者 index
     *  fyj
     *  20160629
     *  filter_value 1  pushliser 2 advertisher 3 traker
     */
    public function getTrackerIndex(Request $request)
    {
     
        $content = empty($request->content)?'':$request->content;

        $size = empty($request->size)?10:$request->size;

        $last_id = empty($request->last_id)?0:$request->last_id;

        $sort = empty($request->sort)?'desc':'asc';
        //参数不可为空 2001
        if(empty($content))return HttpUtil::retrun_json([],2001);

        $curl_param = [
            'sort'=>[
                'tracker_advertiser'=>[
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
     * 媒体 index 
     * role: 1  pushliser 2 advertisher 3 traker
     * size 每页显示 数量
     * last_id  每页最大 编号 id
     * content 搜索内容
     */
    public function getPublisherIndex(Request $request)
    {
        $content = empty($request->content)?'':$request->content;

        $size = empty($request->size)?10:$request->size;

        $last_id = empty($request->last_id)?0:$request->last_id;

        $sort = empty($request->sort)?'desc':'asc';


        //参数不可为空 2001
        if(empty($content))return HttpUtil::retrun_json([],2001);

        $curl_param = [
            'sort'=>[
                'publisher_ads'=>[
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
     *  广告主 index
     *  fyj
     *  20160629
     *  role: 1  pushliser 2 advertisher 3 traker
     * @param  content [搜索内容]
     * @return [type]           [description]
     */
    public function getAdsIndex(Request $request)
    {

        $content = empty($request->content)?'':$request->content;

        $size = empty($request->size)?10:$request->size;

        $last_id = empty($request->last_id)?0:$request->last_id;

        $sort = empty($request->sort)?'desc':'asc';


        //参数不可为空 2001
        if(empty($content))return HttpUtil::retrun_json([],2001);

        $curl_param = [
            'sort'=>[
                'brand_subjects'=>[
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


    //===================================== 暂勿使用 api======================================================================

     /**
     * type 1 广告主 2 媒体 3 追踪者
     * @param  content 搜索内容
     * @param  size  当前页显示条数
     * @param  page 分页
     * @param  last_id 当前页 最大 编号 id
     */
    protected function getPubTacAdsIndex(Request $request)
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
     * 最新 视频广告
     * type  1 图片 2 flush 3 视频 0 所有
     * limit 100
     * type 　视频　图片　flush
     */
    protected function getRecentVideoAds(Request $request)
    {

        $type = empty($request->type)?'':$request->type;
        $sord = empty($request->sort)?'desc':$request->sort;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if($sord && !DBUtil::sortValied($sord))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($type && !DBUtil::typeValied($type))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200); 

        if($type)
        {
            $type = ($type==1?'image':($type==2?'swf':'flv'));
        }
        
        if(($size && $size>10000) || !is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $curl_param = [
            'sort'=>[
                'created_date'=>[
                    'order'=>$sord
                ]
            ],
            'size'=>$size,
            'query'=>[
                'term'=>[
                    'type'=>$type
                ]
            ]
        ];

        if(empty($type))
        {
            unset($curl_param['query']);
        }

        $url = config('param.ES_URL').'addatas/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data'])) return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }

     /**
     * 最新广告 【实时最新广告】
     * finn
     * 20160705
     * @param  size 显示数据数量
     */
    protected function getRecentAds(Request $request)
    {

        $size = empty($request->size)?200:$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        if($sort && !in_array($sort, ['desc','DESC','asc','ASC']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }

        if(!is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size
            
        ];

        $url = config('param.ES_URL').'addatas/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;
        

        return HttpUtil::retrun_json($data,200);

    }

   

    /**
     * 每周跟踪排行
     * fyj
     * 20160630
     */
    protected function getTopTrackerAds(Request $request)
    {

        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $role = empty($request->role)?'':$request->role;


        if($sort && !DBUtil::sortValied($sort))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($role && !is_numeric($role))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $curl_param = [
            'sort'=>[
                'tracker_advertiser'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size,
            'query'=>[
                'term'=>[
                    'role'=>$role
                ]
            ]
        ];
        

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 随机 获取 广告
     * fyj
     * 20160630
     */
    protected function getRandomAds(Request $request)
    {
        $rand_id = rand(1, 999);

        $sort = empty($request->sort)?'desc':$request->sort;

        // $size = empty($request->size)?rand(100,999):$request->size;

        // if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($sort && !DBUtil::sortValied($sort))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $curl_param = [
            'sort'=>[
                'created_date'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>100,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'range'=>[
                            'id'=>[
                                'gt'=>$rand_id
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $url = config('param.ES_URL').'addatas/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 最新 移动 广告
     * platform 1 pc 2 移动
     */
    protected function getRecentMobileAds(Request $request)
    {

        $platform = empty($request->platform)?0:$request->platform;
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($platform && !is_numeric($platform))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }
        if(($size && !is_numeric($size)) || $size>10000)return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        
        if($sort && !DBUtil::sortValied($sort))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $curl_param = [
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size,
            'query'=>[
                'term'=>[
                    'platform'=>$platform
                ]
            ]
        ];



        if(empty($platform))
        {
            unset($curl_param['query']);
        }

        $url = config('param.ES_URL').'addatas/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data'])) return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
        
    }


    /**
     * 根据角色获取总条数
     * @return [type] [description]
     */
    public function getRoleCurl($curl_param,$role)
    {
        switch ($role) {
            case 'tracker':
                $curl_param['aggs'] = [
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ],
                        'subjects'=>[
                            'cardinality'=>[
                                'field'=>'subject'
                            ]
                        ],
                        'publishers'=>[
                            'cardinality'=>[
                                'field'=>'publisher'
                            ]
                        ],
                        'advertisers'=>[
                            'cardinality'=>[
                                'field'=>'advertiser'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'id'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'id'
                            ]
                        ]
                ];
                break;
            case 'publisher':
                $curl_param['aggs'] = [
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ],
                        'subjects'=>[
                            'cardinality'=>[
                                'field'=>'subject'
                            ]
                        ],
                        'trackers'=>[
                            'cardinality'=>[
                                'field'=>'tracker'
                            ]
                        ],
                        'advertisers'=>[
                            'cardinality'=>[
                                'field'=>'advertiser'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'id'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'id'
                            ]
                        ]
                ];
                break;
            case 'advertiser':
                $curl_param['aggs'] = [
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ],
                        'subjects'=>[
                            'cardinality'=>[
                                'field'=>'subject'
                            ]
                        ],
                        'trackers'=>[
                            'cardinality'=>[
                                'field'=>'tracker'
                            ]
                        ],
                        'publishers'=>[
                            'cardinality'=>[
                                'field'=>'publisher'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'id'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'id'
                            ]
                        ]
                ];
                break;
            case 'subject':
                $curl_param['aggs'] = [
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ],
                        'publishers'=>[
                            'cardinality'=>[
                                'field'=>'publisher'
                            ]
                        ],
                        'trackers'=>[
                            'cardinality'=>[
                                'field'=>'tracker'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'id'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'id'
                            ]
                        ]
                ];
                break;
        }
        return $curl_param;
         
    }

}
