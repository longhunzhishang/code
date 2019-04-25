<?php
namespace App\Http\Controllers\Api3;

use App\Http\Utils\ESUtil;
use App\Http\Utils\DSLUtil;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use Search;
use DB;
use Illuminate\Support\Facades\Redis as Redis;
/**
 * finn
 * 20170706
 * domain api
 *  header('Access-Control-Allow-Origin:*');
 */
class DomainController extends Controller
{

    /**
     * 自动纠错 功能
     * finn 目前只针对 英文 中文 后续 研究
     * 2017-7-7
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getDomainAutoCompletion(Request $request)
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
     * 根据 域名获取相关信息
     * @return [type] [description]
     */
    public function getAllInfoByHost(Request $request)
    {
        $host = empty($request->host)?'':$request->host;

        $hosts = array_filter(explode('_H_', $host));

        if(empty($hosts))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        

        $item = [];

        foreach ($hosts as $key => $value) {
            $curl_param = [
                'query'=>[
                    'term'=>[
                        'host_no'=>$value
                    ]
                ]
            ];

            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
            $data = DBUtil::get_parse_es_data($rs);

            if(!empty($data['data']))
            {
                $item[] = $data['data'][0];
            }
        }
        $data['data'] = $item;
        // dd(json_encode($curl_param));
        
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = empty($data['data'])?203:200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据 字段 获取 对应的数据
     * @return [type] [description]
     */
    public function getOneByfield(Request $request)
    {
        $field = empty($request->field)?'':$request->field;
        $value = empty($request->value)?'':$request->value;

        if(empty($field) || empty($value))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        $curl_param = [
            'query'=>[
                'term'=>[
                    $field=>$value
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
     * 获取 domain 所有 信息 
     * @return [type] [description]
     */
    public function getAll(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($sort && !in_array($sort, ['desc','asc']))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if(!is_numeric($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),DSLUtil::baseDSLQuery(0,$size));
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
        $from = empty($request->from)?0:$request->from;
        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $param = explode('_domain_id_', $param);

        $id_tmp = [];
        foreach ($param as $key => $value) {
            if(empty($value))
            {
                unset($param[$key]);
            }
            else{
                $id_tmp[] = $value;
            }
        }

        $size = count($id_tmp);
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        'terms'=>[
                            'id'=>$id_tmp
                        ]
                    ]
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
     */
    public function getInfoInHosts(Request $request)
    {
       $param = $request->param;
       $param = urldecode($param);
        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $param = explode('_domain_id_', $param);
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
     * role 2 广告主 1 媒体 3 追踪者
     */
    public function getInfoByField(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $role =empty($request->role)?'':$request->role;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
    
        // $curl_param = DSLUtil::domainDLSOrderQuery($size,$param,$role);
        $curl_param = '';
        if(DBUtil::check_str_cn_en($param)!=1)
        {
            $curl_param = DSLUtil::domainDLSOrderQuery($size,$param,$role);
        }else{
            $curl_param = DSLUtil::domainDLSOrderQueryEN($size,$param,$role);
        }

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.DOMAIN_FILED');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        // if(!count($data_db))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        // $data['data'] = $data_db;
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 订阅
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getQueryInfoByBinlog(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;
        $power = empty($request->power)?'':$request->power;// 判断是否是超管

        // 1成纯英文 2 中文 3 中英混合
        $is_en_ch = empty($request->is_en_ch)?'':$request->is_en_ch;
        // 字段 排序
        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        // 判断是否含有 redis 缓存
        $data = [];
        // if(count(explode('-', $param))>1)
        // {
        //     $is_en_ch = 4;
        // }
        if($is_en_ch==1)
        {

            $sql = 'SELECT id,host,cname,role FROM domain where ';


            if(empty($power))$sql = $sql .' role=2 AND ';

            // 2017-6-2 改成 sql 查看
            $sql = $sql.' host like "%'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%" limit '.$size;

            // echo $sql;exit;

            if($orderBy)$sql=$sql.' '.$orderBy.' desc';


            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

            $data = DBUtil::get_parse_es_data($rs);

            if(empty($data['data']))
            {
                $param_num = explode(' ', $param);

                if(count($param_num)>1)
                {
                    $param = str_replace(' ', '', $param);

                    $param = strtolower($param);


                    $sql = 'SELECT id,host,cname,role FROM domain where ';


                    if(empty($power))$sql = $sql .' role=2 AND ';

                    $sql = $sql.' host like "%'.$param.'%" limit '.$size;

                    if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                    $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                    $data = DBUtil::get_parse_es_data($rs);
                }

            }

        }else if($is_en_ch==4){
            
            // $curl_param = DSLUtil::domainDLSQueryEn($size,$param,0,0);
            // if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

            // $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            // $data = DBUtil::get_parse_es_data($rs);

        }else{
            $curl_param = DSLUtil::domainDLSQuery($size,$param,0,0);
            if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            $data = DBUtil::get_parse_es_data($rs);
        }
        if(empty($data['data']))
        {
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

            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAIN_AUTO'),$curl_param_auto,$type);

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
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param_host,$type);
            $data = DBUtil::get_parse_es_data($rs);
            
        }
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['status'] = 200;
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 首页 查询 使用
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     * type 0 返回所有字段 1 只返回 role host cname ename 字段
     * 2017-1-13 finn 代码优化
     */
    public function getQueryInfoByField(Request $request)
    {
        // $key_cache = md5(implode('',$request->all()));
        // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        // {
        //     return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        // }


        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;

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
            $sql = 'SELECT * FROM domain where host like "%'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%" limit '.$size;


            // echo $sql;exit;


            if($orderBy)$sql=$sql.' '.$orderBy.' desc';


            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

            $data = DBUtil::get_parse_es_data($rs);

            if(empty($data['data']))
            {
                $param_num = explode(' ', $param);

                if(count($param_num)>1)
                {
                    $param = str_replace(' ', '', $param);

                    $param = strtolower($param);

                    $sql = 'SELECT * FROM domain where host like "%'.$param.'%" limit '.$size;

                    if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                    // dd($sql);


                    $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                    $data = DBUtil::get_parse_es_data($rs);
                }

            }



        }else{
            // $curl_param = DSLUtil::domainDLSQuery($size,$param,0,0);
            

            $curl_param = DSLUtil::domainDLSQueryParse($size,$param,0,0,'must',50,'cname');
            if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            $data = DBUtil::get_parse_es_data($rs);
        }
        // $curl_param = DSLUtil::domainDLSQuery($size,$param,0,0);
            
//        dd(json_encode($curl_param));
        
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
                // 'query'=>[
                //     'filtered'=>[
                //         'filter'=>[
                //             'should'=>[]
                //         ]
                //     ]
                // ],
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
                // array_push($curl_param_do['query']['filtered']['filter']['should'],$item);
                // array_push($curl_param_do['query']['filtered']['filter']['should'],$item_domain);
                array_push($curl_param_do['query']['bool']['should'],$item);
                array_push($curl_param_do['query']['bool']['should'],$item_domain);
                $curl_param_do['_source'] = config('param.ADDATA_FILED_SOURCE');

                $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param_do,'');

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
     * 高级搜索 
     * 根据 角色 字段
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getQueryInfoByRole(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $role = empty($request->role)?'':$request->role;

        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $curl_param = DSLUtil::domainDLSQuery($size,$param,$role,1);
        $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,1);
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


         // 记录 域名 平台
        // 2016-12-23 finn
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
                        'should'=>[

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
                $item_domain = [
                    'term'=>[
                        'domain'=>$value->host
                    ]
                ];
                array_push($curl_param_do['query']['bool']['should'],$item);
                array_push($curl_param_do['query']['bool']['should'],$item_domain);

                $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param_do,'');

                $data_do = DBUtil::get_parse_es_data($rs_do);
                $value->attr04 = empty($data_do['data'])?'':$data_do['data'][0]->attribute04;
                // $value->platform = empty($data_do['data'])?'':$data_do['data'][0]->platform;
            }
        }

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
     * 2017-6-2 修改 判断 是否是跟踪者 二级域名
     * @return [type] [description]
     */
    public function getInfoByMd5(Request $request)
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


            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
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
     * 监测 是否 含有 flag 标识
     * flag 0 顶级域名  1 二级以上域名 跟踪者
     * 2017-6-2 
     * @return [type] [description]
     */
    public static function getFlagById($id)
    {
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);
        return ESUtil::getInfoById(config('param.DOMAIN_SEARCH'),$id);
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
     * role 1 媒体 2 广告主 3 跟踪者
     */
    public function getRoleFilter(Request $request)
    {
        //orderBy
        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $role = empty($request->role)?'':$request->role;
        $size = empty($request->size)?10:$request->size;
        $param = empty($request->wd)?'':$request->wd;
        $oldparam = $param;
        $page = empty($request->page)?1:$request->page;
        $sort = empty($request->sort)?'desc':$request->sort;

        $from = 0;
        if($page>1)$from = ($page-1) * $size;
        $all_size = $page * $size;


        if(empty($orderBy) || empty($role) || empty($size) || empty($param) || ($size && !is_numeric($size)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        
        if($size > config('param.MAXSIZE'))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        // 1 英文 2 中文 3 中英

        // $is_en_cn = DBUtil::check_str_cn_en($param);

        $data_sql = [];
        // 2017-6-9 改成 sql 查看
        $sql = 'SELECT * FROM domain where (host_no like "%'.$param.'%" or ename like "%'.$param.'%" or cname_no like "%'.$param.'%") AND role='.$role;

        if($orderBy)$sql=$sql.' ORDER BY '.$orderBy.' '.$sort;

        $sql = $sql.' limit '.$from.' , '.$size;

        $total = 0;
        // dd($sql);

        $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))
        {


            $param_num = explode(' ', $param);

            if(count($param_num)>1)
            {
                $param = str_replace(' ', '', $param);

                $param = strtolower($param);

                $sql = 'SELECT * FROM domain where (host_no like "%'.$param.'%" or ename like "%'.$param.'%" or cname_no like "%'.$param.'%") AND role='.$role;

                if($orderBy)$sql=$sql.' ORDER BY '.$orderBy.' '.$sort;

                $sql = $sql.' limit '.$from.' , '.$size;
                // dd($sql);


                $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                $data1 = DBUtil::get_parse_es_data($rs);

                if(empty($data1['data']))
                {
                    $param_num_2 = explode(' ', $param);
                    if(count($param_num_2)>1)
                    {
                        foreach ($param_num_2 as $ki => $vi) {

                            $sql = 'SELECT * FROM domain where (host_no like "%'.$vi.'%"  or ename like "%'.$vi.'%" or cname_no like "%'.$vi.'%") AND role='.$role;

                            
                            if($orderBy)$sql=$sql.' ORDER BY '.$orderBy.' '.$sort;

                            $sql = $sql.' limit '.$from.' , '.$size;

                            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                            $data_2 = DBUtil::get_parse_es_data($rs);

                            if(!empty($data_2['data']))
                            {
                                $total = $data['total'];
                                $data_sql[] = $data_2['data'];
                            }
                            
                        }
                    }
                }else{
                     $data_sql = $data1['data'];
                     $total = $data['total'];
                }
            }

        }
        else{
            $data_sql = $data['data'];
            $total = $data['total'];
        }


        if(empty($data_sql))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $rdata['data'] = $data_sql;
        $rdata['total'] = $total;
        $rdata['status'] = 200;


        return HttpUtil::retrun_json($rdata,200);
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

        // 如果 条数 大于 10
        if($data['total'] > 10)
        {
            $total = $data['total'];
            $curl_param['size'] = $total;
        }

        $rs = HttpUtil::parse_curl_search(config('param.DICTS_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

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
            'sort'=>[
                $sort=>[
                    'order'=>'desc'
                ]
            ],
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
                $itme['brand_ads'] = $value['brand_ads'];
                $itme['publisher_ads'] = $value['publisher_ads'];
                $itme['brand_subjects'] = $value['brand_subjects'];
                $itme['tracker_subjects'] = $value['tracker_subjects'];
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

    /**
     * 获取 对应的域名
     * @return [type] [description]
     */
    public function getHostIsExist(Request $request)
    {
        $value = empty($request->wd)?'':$request->wd;

        $value = urldecode($value);

        if(empty($value))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);
        $curl_param = [
            'query'=>[
                'term'=>[
                    'host_no'=>$value
                ]
            ],
            '_source'=>[
                'id','host','role','flag'
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }

    //以下所有 API　暂时没用用到　＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝＝
    
    /**
     * @param Request $request
     * @return $thi
     * 测试使用
     */
    public function getQueryInfoByFieldTest(Request $request)
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
                                'host.cn','host.en','cname.cn^10','cname.en'
                            ],
                            'tie_breaker'=>0.7
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
                                'host.en'=>$param
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

        $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS_2'),$curl_param,$type);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

         // 记录 域名 平台
        // 2016-12-23 finn
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
                        'should'=>[

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
                $item_domain = [
                    'term'=>[
                        'domain'=>$value->host
                    ]
                ];
                array_push($curl_param_do['query']['bool']['should'],$item);
                array_push($curl_param_do['query']['bool']['should'],$item_domain);

                $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param_do,'');

                $data_do = DBUtil::get_parse_es_data($rs_do);
                $value->attr04 = empty($data_do['data'])?'':$data_do['data'][0]->attribute04;
                // $value->platform = empty($data_do['data'])?'':$data_do['data'][0]->platform;
            }
        }
        $data['status'] = 200;
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
        return HttpUtil::retrun_json($data,200);
    }


    public function getQueryAllInfo(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $host = empty($request->host)?'':$request->host;
        if(empty($host))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        if($size && !is_numeric($size)) return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        $se = Search::index(config('param.DOMAINS_4'))->search(null,$host)->limit($size)->get();
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
