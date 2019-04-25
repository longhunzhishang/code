<?php
namespace App\Http\Controllers\Api7;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\ESUtil;
use App\Http\Utils\DSLUtil;
use App\Http\Utils\Tools;
use Illuminate\Support\Facades\Auth;
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

        $this->size = empty($request->size)?config('param.SIZE'):$request->size;

        if($this->size>1200)$this->size = 1200;

        $this->page = empty($request->page)?1:$request->page;

        $this->aggs_size = $this->size;
        $this->aggs_all = $this->page *  $this->aggs_size;

        if($this->aggs_all>1200)$this->aggs_all = 1200;

        if($this->page > 1)
        {
            $this->from = ($this->page-1) *  $this->size;
        }



    }
    /**
     * 热门广告主 媒体 跟踪者
     * type 1 add 2 delete
     * wd 域名对应的值
     * role 2 广告主 1 媒体 3 跟踪者
     * @return [type] [description]
     */
    public function hotRoles(Request $request)
    {
        $type = empty($request->type)?'':$request->type;
        $host = empty($request->wd)?"":$request->wd;

        if(empty($type) || empty($host))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $isExist = $this->isExistHost($host);

        //判断该域名是否存在
        if(!$isExist)
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(600),200);
        }
        $role = $isExist->role;


        $hotroles = DB::connection('mysql_oc2018')->table('hotroles');

        $da['host'] = $host;
        $da['status'] = $type;
        $da['role'] = $role;

        if($type==1)
        {

            if(empty($hotroles->where('host',$host)->first()))
            {
                $hotroles->insert($da);
            }else{
                $hotroles->where('host',$host)->update($da);
            }

            return HttpUtil::retrun_json(['status'=>200],200);
        }
        if(!empty($hotroles->where('host',$host)->first()))
        {
            $hotroles->where('host',$host)->delete();
        }

        return HttpUtil::retrun_json(['status'=>200],200);
    }

    /**
     * 获取热门域名列表
     * type 1 首页 2 详细页列表
     * @return [type] [description]
     */
    public function hotRolesList(Request $request)
    {
        $type = empty($request->type)?'':$request->type;

        if(empty($type))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);



        $hosts = DB::connection('mysql_oc2018')->table('hotroles')->get();

        $alltotal = count($hosts);



        // $hotroles = DB::connection('mysql_oc2018')->table('hotroles');

        $data['total'] = $alltotal;

        switch ($type) {
            case '1':
                # code...
                $data['publishers'] = DB::connection('mysql_oc2018')->table('hotroles')->where('role',1)->limit($this->size)->get();
                $data['advertisers'] = DB::connection('mysql_oc2018')->table('hotroles')->where('role',2)->limit($this->size)->get();
                $data['trackers'] = DB::connection('mysql_oc2018')->table('hotroles')->where('role',3)->limit($this->size)->get();
                break;
            case '2':
                $from = ($this->page-1) * $this->size;
                # code...
                $data['all'] = DB::connection('mysql_oc2018')->table('hotroles')->offset($from)->limit($this->size)->get();
                break;
        }

        if($type==1)
        {

            $data['publishers'] = empty($data['publishers'])?[]:$this->mergeHostInfo($data['publishers']);
            $data['advertisers'] = empty($data['advertisers'])?[]:$this->mergeHostInfo($data['advertisers']);
            $data['trackers'] = empty($data['trackers'])?[]:$this->mergeHostInfo($data['trackers']);
        }else{
            $data['all'] = empty($data['all'])?[]:$this->mergeHostInfo($data['all']);
        }
        $data['cur_page'] = $this->page;
        $data['size'] = $this->size;
        $data['all_page'] = ceil($alltotal/$this->size);
        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 合拼host 详细信息
     * @return [type] [description]
     */
    private function mergeHostInfo($data)
    {
        $host_info = [];

        foreach ($data as $key => $value) {
            $curl_param = [
                'query'=>[
                    'term'=>[
                        'host_no'=>$value->host
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

            $hots_data = DBUtil::get_parse_es_data($rs);
            $host_info[] = empty($hots_data['data'][0])?'':$hots_data['data'][0];
        }
        return $host_info;
    }

    private function isExistHost($host)
    {
        $curl_param = [
            'query'=>[
                'term'=>[
                    'host_no'=>$host
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);

        return empty($data['data'])?[]:$data['data'][0];
    }
    /**
     * 获取热门广告
     * @return [type] [description]
     */
    public function getHotAds(Request $request)
    {

        $from = ($this->page-1) * $this->size;


        $hotads = DB::connection('mysql_oc2018')
            ->table('hotads')
            ->where('status',1)->get();

        $alltotal = count($hotads);

        $hotads = DB::connection('mysql_oc2018')
            ->table('hotads')
            ->where('status',1)
            ->orderBy('id','desc')
            ->offset($from)->limit($this->size)->get();


        $new_ads = [];

        if(!empty($hotads))
        {
            $ids = [];

            foreach ($hotads as $key => $value) {
                $ids[] = $value->ad_id;
            }

            $curl_param = [
                'size'=>count($ids),
                'query'=>[
                    'terms'=>[
                        'id'=>$ids
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data = DBUtil::get_parse_es_data($rs);
            // 暂无数据 2002
        }

        $data['total'] = $alltotal;

        $data['cur_page'] = $this->page;
        $data['size'] = $this->size;
        $data['all_page'] = ceil($alltotal/$this->size);
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 热门广告
     * id 创意编号
     * status 是否热门 1 是 0 不是
     * @return [type] [description]
     */
    public function hotAds(Request $request)
    {
        $id = empty($request->id)?'':$request->id;

        $status = empty($request->status)?0:$request->status;

        if(empty($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $db = DB::connection('mysql_oc2018')->table('hotads');

        //mysql_oc_true
        $hotads = $db->where(['ad_id'=>$id])->first();

        $da['ad_id'] = $id;
        $da['status'] = $status;

        $data['status'] = 200;

        if(empty($hotads))
        {
            if(!$db->insert($da)) $data['status'] = 209;
        }else
        {
            if(!$db->where('ad_id',$id)->update($da)) $data['status'] = 209;
        }
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取热门广告
     * @return [type] [description]
     */
    public function getHotsAd(Request $request)
    {
        $hotads = DB::connection('mysql_oc2018')
            ->table('hotads')
            ->where('status',1)->limit($this->size)->get();

        $data['status'] = 200;
        $new_ads = [];

        if(!empty($hotads))
        {
            $ids = [];

            foreach ($hotads as $key => $value) {
                $ids[] = $value->ad_id;
            }

            $curl_param = [
                'size'=>count($ids),
                'query'=>[
                    'terms'=>[
                        'id'=>$ids
                    ]
                ]
            ];
            // dd(json_encode($curl_param));
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data = DBUtil::get_parse_es_data($rs);
            // 暂无数据 2002
        }
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取最新广告
     * @return [type] [description]
     */
    public function getCurrAds(Request $request)
    {
        $curl_param = [
            'size'=>$this->size,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'range'=>[
                            'platform'=>[
                                'gte'=>2
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 相关创意
     * 获取同一个广告主下面的广告信息
     * host 广告主 域名
     * id 当前创意编号
     * @return [type] [description]
     */
    public function getRelationAds(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        $host = empty($request->host)?'':$request->host;

        if(empty($id) || empty($host))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $curl_param = [
            'size'=>$this->size,
            'from'=>$this->from,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            'advertiser_na'=>$host
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'id'=>$id
                        ]
                    ]
                ]
            ],
            'sort'=>DSLUtil::baseSort()
        ];
        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        //日期处理
        foreach ($data['data'] as $key => &$value) {
            $value->last_seen = DBUtil::timeToDate($value->last_seen);
            $value->created_date = DBUtil::timeToDate($value->created_date);
        }

        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);

    }


    /**
     * 主页 收索 过滤 条件
     * width 宽
     * min_width 范围
     * wd 关键词 urlencode 编码
     * height 高 min_height 范围
     * platform 平台 默认所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android
     * start_time 开始时间 end_time 结束时间 时间查询 时间戳 精确到秒
     * shape 形状  所有尺寸  1  ， 2 ，3 ...
     * type 类型 1 图片 2 视频 3 flush 4 html5 5 原生
     *
     * @return [type]           [description]
     */
    public function getSearchFilterAds(Request $request)
    {
        $width = empty($request->width)?0:$request->width;
        $min_width = empty($request->min_width)?0:$request->min_width;
        $min_height = empty($request->min_height)?0:$request->min_height;
        $height = empty($request->height)?0:$request->height;

        $max_width = max($width,$min_width);
        $min_width = min($width,$min_width);
        $max_height = max($height,$min_height);
        $min_height = min($height,$min_height);

        $is_width= ($max_width===$min_width?1:0);
        $is_height= ($max_height===$min_height?1:0);

        $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;

        if(($this->size && $this->size > 10000) || !is_numeric($this->size))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        $sort = empty($request->sort)?'desc':$request->sort;

        if(!in_array($sort, ['desc','asc','DESC','ASC']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        $param = empty($request->wd)?'':$request->wd;
        $oldparam = urldecode($param);
        $param = urldecode($param);

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);


        $en_ch = DBUtil::check_str_cn_en($oldparam);

        // 2017-8-28 新增 ott 过滤条件 5
        $platform = empty($request->platform)?'':$request->platform; // 所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android

        $startTime = empty($request->start_time)?'':$request->start_time;
        $endTime = empty($request->end_time)?'':$request->end_time;

        $shape = empty($request->shape)?'':$request->shape;  // 0  所有尺寸  1  ， 2 ，3 ...
        $type = empty($request->type)?'':$request->type; // 1 图片 2 视频 3 flush 4 html5 5 原生

        if($platform>5 || $type > 5 || $shape > 5)return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        // 相关性 查询
        $sortmode = empty($request->sortmode)?'':$request->sortmode;


        if(($shape && !is_numeric($shape)) || ($type && !is_numeric($type)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $attribute04 = ''; // Android IOS
        $parseType = Tools::parseType($type);
        $type = $parseType['type'];
        $material = $parseType['material'];


        if($platform>2 && $platform !=5 )$attribute04 = ($platform==3?'Android':'IOS');

        if($platform==5) $platform = 4;

        if($startTime)
        {
            if($endTime < $startTime)
            {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data,200);
            }
        }


        $is_time = ($startTime===$endTime?1:0);

        // 验证参数格式
        $valiedParam = [
            'shape'=>$shape,
            'platform'=>$platform
        ];
        if($is_width)$valiedParam['width'] = $max_width;
        if($is_height)$valiedParam['height'] = $max_height;

        if($attribute04)$valiedParam['attribute04'] = $attribute04;
        if($material)$valiedParam['material'] = $material;
        if($type)$valiedParam['type'] = $type;


        if($attribute04)unset($valiedParam['platform']);
        if($material)unset($valiedParam['type']);

        $synonym = $en_ch==1?$this->getSynonym($en_ch,$param):'';

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param;
        // 默认
        $curl_param = $this->getCurlParam($oldparam,$this->size,0,$this->from);
        $curl_param = Tools::valiedParam($valiedParam,$curl_param,$iscampaign,DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime,$endTime,$is_time,$max_width,$min_width,$is_width,$max_height,$min_height,$is_height);

        $curl_param = Tools::rangeParam($range,$curl_param,$iscampaign,DBUtil::check_str_cn_en($oldparam));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);
        $data['last_id'] = 0;
        if(empty($data))$data['data'] = [];

        $data = $this->getadvertiserDomain($data);
        // 按照 时间 进行过滤
        if($sortmode==1)$data['data'] = DBUtil::my_sort($data['data'],'created_date',SORT_DESC,SORT_NUMERIC);

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 竞争情报
     * wd 关键词
     * tab 1 广告主 2 媒体 3 跟踪者
     * @return [type] [description]
     */
    public function getCompetitiveInfos(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;
        $tab = empty($request->tab)?'1':$request->tab;
        $page = empty($request->page)?'1':$request->page;
        $param = urldecode($param);
        $oldparam = urldecode($param);
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if($tab > 4)return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $type = empty($request->type)?'':$request->type;


        // 1成纯英文 2 中文 3 中英混合
        $is_en_ch = DBUtil::check_str_cn_en($param);
        // 字段 排序
        $orderBy = empty($request->orderBy)?'brand_ads':$request->orderBy;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);


        $role_field = 'advertiser_na';

        $role = '2';
        switch ($tab) {
            case '1':
                $role = '2';
                $role_field = 'advertiser_na';
                break;
            case '2':
                $role = '1';
                $role_field = 'publisher_na';
                break;
            case '3':
                $role = '3';
                $role_field = 'tracker_list';
                break;
        }

        // 判断是否含有 redis 缓存

        $data = [];
        if($is_en_ch==1)
        {
            // 2017-6-2 改成 sql 查看
            $sql = 'SELECT * FROM domain where role='.$role.' AND ( host like "'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%")';
            if($orderBy)$sql=$sql.'order by '.$orderBy.' desc';
            $sql = $sql.' limit '.$size*($page-1).', '.$size;
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");
            $data = DBUtil::get_parse_es_data($rs);
            if($param == "qq.com" && $role == 3 ){
                $role_1 = 1;
                $sql = 'SELECT * FROM domain where istracker='. $role_1.' AND ( host = "'.$param.'" or ename = "'.$param.'%" or cname = "'.$param.'") limit 0 , '.$size;
                if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");
                $data1 = DBUtil::get_parse_es_data($rs);
                if($page == 1){
                    array_push($data["data"],$data1["data"][0]);
                }
                $data["total"] = $data["total"]+1;
            }



            if(empty($data['data']))
            {
                $param_num = explode(' ', $param);

                if(count($param_num)>1)
                {
                    $param = str_replace(' ', '', $param);

                    $param = strtolower($param);

                    $sql = 'SELECT * FROM domain where role='.$role.' AND ( host like "%'.$param.'%") limit '.$this->page.' , '.$size;

                    if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                    // dd($sql);


                    $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                    $data = DBUtil::get_parse_es_data($rs);
                }

            }



        }else{
            $curl_param = DSLUtil::domainDLSQueryParse($size,$param,$role,0,'must',50,'cname');

            $curl_param['from'] = $this->from;

            if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            $data = DBUtil::get_parse_es_data($rs);
        }

        // 暂无数据 2002
        if(empty($data['data']))
        {

            if($is_en_ch==1)
            {
                $data = $this->getDomainAutoCompletion($param,$role);

                if(empty($data['data']))
                {
                    return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
                }
            }else{
                return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
            }
        }
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

        foreach ($data['data'] as $k => &$v) {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'term'=>[
                                $role_field=>$v->host
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
                    'advertisers'=>[
                        'cardinality'=>[
                            'field'=>'advertiser_na'
                        ]
                    ],
                    'publishers'=>[
                        'cardinality'=>[
                            'field'=>'publisher_na'
                        ]
                    ],
                    'trackers'=>[
                        'cardinality'=>[
                            'field'=>'tracker_list'
                        ]
                    ],
                    'subjects'=>[
                        'cardinality'=>[
                            'field'=>'subject_md5'
                        ]
                    ]
                ]
            ];
            if($tab == 3){
                unset($curl_param["query"]["bool"]["must"]);
                $curl_param["query"]["bool"]["should"] = [[
                    'term'=>[
                        $role_field=>$v->host
                    ]
                ],[
                    'term'=>[
                        "tags_list"=>$v->host
                    ]
                ]];

            }
            $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param,'');
            $rs_parse = json_decode($rs_do,true);
            $v->ads = $rs_parse['aggregations']['ads']['value'];
            $v->advertiser_num = $rs_parse['aggregations']['advertisers']['value'];
            $v->publisher_num = $rs_parse['aggregations']['publishers']['value'];
            $v->tracker_num = $rs_parse['aggregations']['trackers']['value'];
            $v->subject_num = $rs_parse['aggregations']['subjects']['value'];
        }
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);



        dd();




        $param = empty($request->wd)?'':$request->wd;
        $tab = empty($request->tab)?'1':$request->tab;
        $param = urldecode($param);
        $oldparam = urldecode($param);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if($tab > 4)return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch==1?$this->getSynonym($en_ch,$param):'';

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param.' '.$c_host;

        $curl_param = $this->getCurlParam($oldparam,0,0,0);

        $field_aggs = 'advertiser_na';
        switch ($tab) {
            case '1':
                $field_aggs = 'advertiser_na';
                break;
            case '2':
                $field_aggs = 'publisher_na';
                break;
            case '3':
                $field_aggs = 'tracker_list';
                break;
            case '4':
                $field_aggs = 'subject_md5';
                break;
        }
        $curl_param['aggs'] = [
            $field_aggs=>[
                'terms'=>[
                    'field'=>$field_aggs,
                    'size'=>$this->aggs_all
                ]
            ],
            'alltotal'=>[
                'cardinality'=>[
                    'field'=>$field_aggs
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $rs_parse = json_decode($rs,true);

        $data['status'] = 200;
        if(empty($rs_parse['aggregations'][$field_aggs]['buckets']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }


        $aggs_buckets = $rs_parse['aggregations'][$field_aggs]['buckets'];

        $aggs_buckets_item = $this->parsePage($aggs_buckets);

        $data['status'] = 200;

        if($tab != 4)
        {
            $data = $this->getDomainInfos($aggs_buckets_item,$field_aggs,'','');
        }else{
            $data = $this->getSubjectInfos($aggs_buckets_item,'');
        }

        $alltotal = empty($rs_parse['aggregations']['alltotal']['value'])?0:$rs_parse['aggregations']['alltotal']['value'];
        $data['total'] = $alltotal;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 搜索结果接口
     * 获取 domain 所有 信息
     * type 0 返回所有数据 1 简化数据
     * sort 排序 默认 desc
     * wd 搜索内容 urlencode
     * page 分页 默认 1
     */
    public function getAllAds(Request $request)
    {
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

        if(!DBUtil::valiedSizeSort($this->size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(($type && !is_numeric($type)))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch==1?$this->getSynonym($en_ch,$param):'';

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param.' '.$c_host;

        $curl_param = $this->getCurlParam($oldparam,$this->size,0,$this->from);

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']) || !count($data['data'])) $data = DBUtil::resetGetData($curl_param,config('param.ADDATA_SEARCH6'));

        $data = $this->getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 相关营销活动
     * wd 关键词
     * @return [type] [description]
     */
    public function getRelationSubjects(Request $request)
    {
        $width = empty($request->width)?0:$request->width;
        $min_width = empty($request->min_width)?0:$request->min_width;
        $min_height = empty($request->min_height)?0:$request->min_height;
        $height = empty($request->height)?0:$request->height;

        $max_width = max($width,$min_width);
        $min_width = min($width,$min_width);
        $max_height = max($height,$min_height);
        $min_height = min($height,$min_height);

        $is_width= ($max_width===$min_width?1:0);
        $is_height= ($max_height===$min_height?1:0);

        $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;

        if(($this->size && $this->size > 10000) || !is_numeric($this->size))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }


        $sort = empty($request->sort)?'desc':$request->sort;

        if(!in_array($sort, ['desc','asc','DESC','ASC']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        $param = empty($request->wd)?'':$request->wd;
        $oldparam = urldecode($param);
        $param = urldecode($param);

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        $en_ch = DBUtil::check_str_cn_en($oldparam);

        // 2017-8-28 新增 ott 过滤条件 5
        $platform = empty($request->platform)?'':$request->platform; // 所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android

        $startTime = empty($request->start_time)?'':$request->start_time;
        $endTime = empty($request->end_time)?'':$request->end_time;

        $shape = empty($request->shape)?'':$request->shape;  // 0  所有尺寸  1  ， 2 ，3 ...
        $type = empty($request->type)?'':$request->type; // 1 图片 2 视频 3 flush 4 html5 5 原生

        if($platform>5 || $type > 5 || $shape > 5)return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        // 相关性 查询
        $sortmode = empty($request->sortmode)?'':$request->sortmode;


        if(($shape && !is_numeric($shape)) || ($type && !is_numeric($type)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $attribute04 = ''; // Android IOS
        $parseType = Tools::parseType($type);
        $type = $parseType['type'];
        $material = $parseType['material'];//原生字段 native


        if($platform>2 && $platform !=5 )$attribute04 = ($platform==3?'Android':'IOS');

        if($platform==5) $platform = 4;

        if($startTime)
        {
            if($endTime < $startTime)
            {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data,200);
            }
        }


        $is_time = ($startTime===$endTime?1:0);

        // 验证参数格式
        $valiedParam = [
            'shape'=>$shape,
            'platform'=>$platform
        ];


        if($is_width)$valiedParam['width'] = $max_width;
        if($is_height)$valiedParam['height'] = $max_height;

        if($attribute04)$valiedParam['attribute04'] = $attribute04;
        if($material)$valiedParam['material'] = $material;
        if($type)$valiedParam['type'] = $type;


        if($attribute04)unset($valiedParam['platform']);
        if($material)unset($valiedParam['type']);

        $synonym = $en_ch==1?$this->getSynonym($en_ch,$param):'';

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param;

        $curl_param = $this->getCurlParam($oldparam,0,0,0);

        $curl_param['aggs'] =[
            'subjects'=>[
                'terms'=>[
                    'field'=>'subject_md5',
                    'size'=>$this->aggs_all
                ]
            ],
            'alltotal'=>[
                'cardinality'=>[
                    'field'=>'subject_md5'
                ]
            ]
        ];

        $curl_param = Tools::valiedParam($valiedParam,$curl_param,$iscampaign,DBUtil::check_str_cn_en($oldparam));
        $range = Tools::rangeWHTParam($startTime,$endTime,$is_time,$max_width,$min_width,$is_width,$max_height,$min_height,$is_height);
        $curl_param = Tools::rangeParam($range,$curl_param,$iscampaign,DBUtil::check_str_cn_en($oldparam));

        // dd(json_encode($curl_param));

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $rs_parse = json_decode($rs,true);

        if(empty($rs_parse['aggregations']['subjects']['buckets']))
        {
            return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        }
        //获取营销活动
        $aggs_buckets = $rs_parse['aggregations']['subjects']['buckets'];

        // dd($aggs_buckets);

        $alltotal = empty($rs_parse['aggregations']['alltotal']['value'])?0:$rs_parse['aggregations']['alltotal']['value'];


        $subjects_tmp = $this->parsePage($aggs_buckets);


        $data = $this->getSubjectInfos($subjects_tmp,$valiedParam);



        $data['total'] = $alltotal;
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据英雄活动 md5 的值 获取对应的额数据
     * tab 1 广告主 2 媒体 3 跟踪者 4 创意 默认
     * last_id 最后一次最大的编号 分页使用
     * irole 点击对应的具体域名角色
     * ivrole点击对应的具体域名角色 对应的域名
     * code 208 tab 不合法
     * @return [type] [description]
     */
    public function getSubjectDetailInfoList(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;

        $oldparam = urldecode($param);

        $tab = empty($request->tab)?4:$request->tab;
        $md5 = empty($request->md5)?'':$request->md5;

        $last_id = empty($request->last_id)?0:$request->last_id;

        //点击具体详细列表里面的域名
        $irole = empty($request->irole)?'':$request->irole;
        $ivrole = empty($request->ivrole)?'':$request->ivrole;

        if(empty($md5))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if($tab > 4 || !is_numeric($tab))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);


        $curl_param = [];

        $aggs_field = 'id';
        switch ($tab) {
            case '1':
                # code...
                $aggs_field = 'advertiser_na';
                break;
            case '2':
                # code...
                $aggs_field = 'publisher_na';
                break;
            case '3':
                # code...
                $aggs_field = 'tracker_list';
                break;
            case '6':
                # code...
                $aggs_field = 'tags_list';
                break;
        }

        $click_role = '';

        if(!empty($irole) && !empty($ivrole))
        {
            switch ($irole) {
                case '1':
                    $click_role = 'advertiser_na';
                    break;
                case '2':
                    $click_role = 'publisher_na';
                    break;
                case '3':
                    $click_role = 'tracker_list';
                    break;
                case '4':
                    $click_role = 'subject_md5';
                    break;
                case '5':
                    $click_role = 'id';
                    break;
            }
        }


        if(empty($oldparam))
        {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    'subject_md5'=>$md5
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            if(!empty($click_role))
            {
                array_push($curl_param['query']['bool']['must'],    [
                    'term'=>[
                        $click_role=>$ivrole
                    ]
                ]);
            }


        }else{
            $curl_param = $this->getCurlParam($oldparam,0,$last_id,0);
            if(!empty($click_role))
            {
                array_push($curl_param['query']['function_score']['filter']['bool']['must'],    [
                    'term'=>[
                        $click_role=>$ivrole
                    ]
                ]);
            }
        }


        if($tab == 4)
        {
            $curl_param['size'] = $this->size;
            $curl_param['from'] = $this->from;
        }else{
            $aggs = [
                $aggs_field=>[
                    'terms'=>[
                        'field'=>$aggs_field,
                        'size'=>$this->aggs_all
                    ]
                ],
                'alltotal'=>[
                    'cardinality'=>[
                        'field'=>$aggs_field
                    ]
                ]
            ];
            $curl_param['aggs'] = $aggs;
        }
        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        if($tab == 4)
        {
            $data = DBUtil::get_parse_es_data($rs);
            // 暂无数据 2002
            if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


            $data['data'] = $this->sortData($data['data'],'created_date');

            $data['status'] = 200;
            return HttpUtil::retrun_json($data,200);
        }
        //非创意 聚合统计
        $rs_parse = json_decode($rs,true);

        if(empty($rs_parse['aggregations'][$aggs_field]['buckets']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $aggs_buckets = $rs_parse['aggregations'][$aggs_field]['buckets'];

        $alltotal = $rs_parse['aggregations']['alltotal']['value'];

        //聚合数据
        $aggs_buckets_item = $this->parsePage($aggs_buckets);
        //获取对应的域名列表
        $data = $this->getDomainInfos($aggs_buckets_item,$aggs_field,'subject_md5',$md5);

        // dd($data);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['status'] = 200;
        $data['total'] = $alltotal;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 解析分页
     * @param  [type] $aggs_buckets_item [description]
     * @return [type]                    [description]
     */
    private function parsePage($aggs_data)
    {

        $aggs_data_tmp = [];
        foreach ($aggs_data as $key => $value) {
            $aggs_data_tmp[] = $value['key'];
        }
        if($this->page  <= 1)return $aggs_data_tmp;

        $re_limit = ($this->page -1) * $this->aggs_size;

        foreach ($aggs_data_tmp as $k => $v) {
            if($k < $re_limit)
            {
                unset($aggs_data_tmp[$k]);
            }
        }

        // dd($aggs_data_tmp);
        return $aggs_data_tmp;
    }
    /**
     * 获取对应的curl_param
     * @param  [type] $oldparam [description]
     * @param  [type] $size     [description]
     * @return [type]           [description]
     */
    private function getCurlParam($oldparam,$size,$last_id,$from)
    {
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym= $en_ch==1?$this->getSynonym($en_ch,$oldparam):'';
        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$oldparam:$c_param.' '.$c_host;
        // 默认
        if($en_ch == 1){
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
//                                            'advertiser_name_title',
                                            'publisher_full'
                                            // 'trackers',
                                            // 'tags'
                                        ]
                                    ]
                                ],
                                'must'=>[]
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
        }else{
            $curl_param = [
                'from'=>$from,
                'size'=>$size,
                'min_score'=>2,
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
                                            'advertiser_name_title',
                                            'publisher_full'
                                            // 'trackers',
                                            // 'tags'
                                        ]
                                    ]
                                ],
                                'must'=>[]
                            ]
                        ],
                        'functions'=>[

                        ],
                        'score_mode'=>'sum',
                        'max_boost'=>'20'
                    ],
                ],
                'sort'=>DSLUtil::baseSort()
            ];
        }

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
                            ],
                            'must'=>[]
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


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }

        $curl_param = $this->isCurlCampaignMerge($curl_param,'',$c_host,$oldparam,$param);

        if(in_array($oldparam, config('param.WHITE_MIN_SCORE')))
        {
            $curl_param['min_score'] = 0;
        }
        return $curl_param;
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


        $current_data = $this->getBeforeRankList($role,$role_field,$start_time,$end_time);


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

        $last_data = $this->getBeforeRankList($role,$role_field,$start_time,$end_time);

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
    private function getBeforeRankList($role,$role_field,$start_time,$end_time)
    {
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
                        'size'=>400,
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
                $v_host = $this->parseHost($v_host);

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

    /**
     * 获取 ott 信息列表
     * 满足首页需求
     * size 列表个数 默认 10 条 最大200
     * @return [type] [description]
     */
    public function getOttList(Request $request)
    {

        if($this->size >1000 )$this->size = 200;

        $curl_param = [
            'size'=>$this->size,
            'from'=>$this->from,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            // 'query'=>[
            //     'term'=>[
            //         'ott_type'=>1
            //     ]
            // ],
            '_source'=>[
                'id','advertiser','publisher'
            ]
        ];
        $url = config('param.ES_URL').config('param.ADDATA_OTT_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $alltotal = $data['total'];
        $ott_id = [];

        foreach ($data['data'] as $key => $value) {
            $ott_id[] = $value->id;
        }
        $data = $this->getOttListInfo($ott_id);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $data = $this->getadvertiserDomain($data);
        $data['status'] = 200;
        $data['total'] = $alltotal;
        return HttpUtil::retrun_json($data,200);

    }
    /**
     * 满足首页需求
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getOttListInfo($ids)
    {
        $curl_param = [
            'size'=>count($ids),
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'terms'=>[
                    'id'=>$ids
                ]
            ]
        ];
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        return $data;
    }
    /**
     * role 1 广告主 2 媒体 3 跟踪者 4 营销互动
     * rv 对应域名的值
     * size 每页显示条数
     * page 翻页 默认 1
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAdsByRoleList(Request $request)
    {
        $role = empty($request->role)?'1':$request->role;
        $role_val = empty($request->rv)?'':$request->rv;
        if(empty($role))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        //营销活动
        if($role==4)
        {
            $curl_param = [
                'query'=>[
                    'term'=>[
                        'md5'=>$role_val
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.SUBJECT_SEARCH6').'/'.config('param.ES_SEARCH');

        }else{
            $curl_param = [
                'query'=>[
                    'term'=>[
                        'host_no'=>$role_val
                    ]
                ]
            ];
        }


        // dd(json_encode($curl_param));
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data3($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $ad_id = $role==4?$data['data'][0]->ad_id:0;


        $field = 'advertiser_na';

        switch ($role) {
            case '1':
                $field = 'advertiser_na';
                break;
            case '2':
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
            'size'=>0,
            'query'=>[
                'term'=>[
                    $field=>$role_val
                ]
            ],
            'aggs'=>[
                'ads'=>[
                    'cardinality'=>[
                        'field'=>'id'
                    ]
                ]
                ,
                'subjects'=>[
                    'cardinality'=>[
                        'field'=>'subject_md5'
                    ]
                ]
                ,
                'publishers'=>[
                    'cardinality'=>[
                        'field'=>'publisher_na'
                    ]
                ]
                ,
                'trackers'=>[
                    'cardinality'=>[
                        'field'=>'tracker_list'
                    ]
                ],
                'advertisers'=>[
                    'cardinality'=>[
                        'field'=>'advertiser_na'
                    ]
                ],
                'first_detected'=>[
                    'min'=>[
                        'field'=>'created_date'
                    ]
                ],
                'last_detected'=>[
                    'max'=>[
                        'field'=>'created_date'
                    ]
                ]

            ]
        ];
        if($role == 3){
            unset($curl_param["query"]);
            $curl_param["query"]["bool"]["should"] = [[
                'term'=>[
                    $field=>$role_val
                ]
            ],[
                'term'=>[
                    "tags_list"=>$role_val
                ]
            ]];


        }

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $aggs = $this->getAggs($rs);
        if($role == 4)
        {
            if(!empty($ad_id))
            {
                $ad_info = $this->getAdDetail($ad_id);
                $aggs['advertiser_host'] = empty($ad_info['data'][0])?'':$ad_info['data'][0]->advertiser_na;
                $aggs['advertiser_cname'] = empty($ad_info['data'][0])?'':$ad_info['data'][0]->advertiser_name_title;
            }
            if(!empty($aggs['first_detected']))
            {
                $aggs['first_detected'] = date('Y-m-d H:i:s',$aggs['first_detected']/1000);
            }

            if(!empty($aggs['last_detected']))
            {
                $aggs['last_detected'] = date('Y-m-d H:i:s',$aggs['last_detected']/1000);
            }
        }
        $data['aggs'] = $aggs;
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据角色域名 获取对应的营销活动
     * role 1 广告主 2 媒体 3 跟踪者
     * rv 对应域名的值
     * tab 1 广告主 2 媒体 3 跟踪者 4 营销活动 5 创意
     * last_id 当前页最大的编号
     * 点击角色 irole 1 广告主 2 媒体 3 跟踪者
     * 点击角色对应的值 ivrole
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getDetailInfoByRoleList(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $rv = empty($request->rv)?'':$request->rv;
        $tab = empty($request->tab)?5:$request->tab;
        $param = empty($request->wd)?'':$request->wd;
        $oldparam = urldecode($param);
        $dw = empty($request->dw)?'':$request->dw;
        $olddw = urldecode($dw);

        $irole = empty($request->irole)?'':$request->irole;

        $ivrole = empty($request->ivrole)?'':$request->ivrole;

        $last_id = empty($request->last_id)?0:$request->last_id;

        if(empty($role))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        if($role > 6)return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $field = 'advertiser_na';

        switch ($role) {
            case '1':
                $field = 'advertiser_na';
                break;
            case '2':
                $field = 'publisher_na';
                break;
            case '3':
                $field = 'tracker_list';
                break;
            case '6':
                $field = 'tags_list';
                break;
        }

        $rfiled = '';
        switch ($irole) {
            case '1':
                $rfiled = 'advertiser_na';
                break;
            case '2':
                $rfiled = 'publisher_na';
                break;
            case '3':
                $rfiled = 'tracker_list';
                break;
            case '4':
                $rfiled = 'tags_list';
                break;
        }

        $click_tab = '';

        switch ($tab) {
            case '1':
                $click_tab = 'advertiser_na';
                break;
            case '2':
                $click_tab = 'publisher_na';
                break;
            case '3':
                $click_tab = 'tracker_list';
                break;
            case '4':
                $click_tab = 'subject_md5';
                break;
            case '5':
                $click_tab = 'id';
                break;
            case '6':
                $click_tab = 'tags_list';
                break;
        }
        $curl_param = [];
        if(empty($oldparam))
        {
            if($tab == 5)
            {
                $curl_param = [
                    'size'=>$this->size,
                    'from'=>$this->from,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        $field=>$rv
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'sort'=>DSLUtil::baseSort()
                ];
                if($field == "tracker_list"){
                    unset($curl_param["query"]["bool"]["must"]);
                    $curl_param["query"]["bool"]["should"] = [[
                        'term'=>[
                            $field=>$rv
                        ]
                    ],[
                        'term'=>[
                            "tags_list"=>$rv
                        ]
                    ]];

                }
            }else{
                $curl_param = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        $field=>$rv
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'aggs'=>[
                        $click_tab=>[
                            'terms'=>[
                                'field'=>$click_tab,
                                'size'=>$this->aggs_all
                            ]
                        ],
                        'alltotal'=>[
                            'cardinality'=>[
                                'field'=>$click_tab
                            ]
                        ]
                    ]
                ];
                if($field == "tracker_list"){
                    unset($curl_param["query"]["bool"]["must"]);
                    $curl_param["query"]["bool"]["should"] = [[
                        'term'=>[
                            $field=>$rv
                        ]
                    ],[
                        'term'=>[
                            "tags_list"=>$rv
                        ]
                    ]];
                }
                switch ($tab) {
                    case 1 :
                        $checkfield_en = 'advertiser_na';
                        $checkfield_ch = 'advertiser_name_title';
                        break;
                    case 2 :
                        $checkfield_en = 'publisher_na';
                        $checkfield_ch = 'publisher_name';
                        break;
                    case 3 :
                        $checkfield_en = 'tracker_list';
                        $checkfield_ch = 'tracker_name';
                        break;
                    case 6 :
                        $checkfield_en = 'tags_list';
                        $checkfield_ch = 'tracker_name';
                        break;
                }
                if(!empty($olddw) && DBUtil::check_str_cn_en($olddw) == 2){
                    $checkfield =  $checkfield_ch;
                }elseif(!empty($olddw)){
                    $checkfield = $checkfield_en;

                }
                if(!empty($olddw)){
                    $array['wildcard'] = [$checkfield=>'*'.$olddw.'*'];
                    array_push($curl_param["query"]["bool"]["must"],$array);

                }
            }


            if(!empty($ivrole))
            {
                $ritem = [
                    'term'=>[
                        $rfiled=>$ivrole
                    ]
                ];
                $curl_param['query']['bool']['must'][ ] = $ritem;
                if($field == "tracker_list"){
                    $curl_value_param = [
                        "bool" => [
                            'should' => [[
                                'term' => [
                                    $field =>$rv
                                ]
                            ], [
                                'term' => [
                                    "tags_list" => $rv
                                ]
                            ]]]];
                    array_push($curl_param['query']['bool']['must'], $curl_value_param);
                }
            }
//             dd(json_encode($curl_param));

            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

            if($tab == 5)
            {
                $data = DBUtil::get_parse_es_data($rs);
                // 暂无数据 2002
                if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

                $data['status'] = 200;

                return HttpUtil::retrun_json($data,200);
            }

            $rs_parse = json_decode($rs,true);

            if(empty($rs_parse['aggregations'][$click_tab]['buckets']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

            $aggs_data = $rs_parse['aggregations'][$click_tab]['buckets'];

            $alltotal = $rs_parse['aggregations']['alltotal']['value'];

            //获取对应的domain 信息
            $aggs_data_tmp = $this->parsePage($aggs_data);

            //营销活动
            if($tab==4)
            {
                $data = $this->getSubjectInfos($aggs_data_tmp,'');

            }else{
                $data = $this->getDomainInfos($aggs_data_tmp,$click_tab,$field,$rv);
            }


            $data['status'] = 200;
            $data['total'] = $alltotal;

            return HttpUtil::retrun_json($data,200);
        }
        //含有搜索内容 param 不为空

        $curl_param = $this->getAdsCurlParam($oldparam,$field,$rv,$last_id,$tab);
        //创意列表
        if($tab == 5)
        {
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

            $data = DBUtil::get_parse_es_data($rs);
            // 暂无数据 2002
            if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
            $aggs = $this->getAggs($rs);

            $data['status'] = 200;

            return HttpUtil::retrun_json($data,200);
        }
        //非创意信息
        $curl_param['aggs'] = [
            $click_tab=>[
                'terms'=>[
                    'field'=>$click_tab,
                    'size'=>$this->aggs_all
                ]
            ],
            'alltotal'=>[
                'cardinality'=>[
                    'field'=>$click_tab
                ]
            ]
        ];

        // dd(json_decode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);
        $rs_parse = json_decode($rs,true);

        if(empty($rs_parse['aggregations'][$click_tab]['buckets']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $aggs_data = $rs_parse['aggregations'][$click_tab]['buckets'];
        $alltotal = $rs_parse['aggregations']['alltotal']['value'];

        $aggs_data_tmp = [];
        foreach ($aggs_data as $key => $value) {
            $aggs_data_tmp[] = $value['key'];
        }

        //去重上一页的数据
        if($this->page > 1)
        {
            $rm_limit = $this->aggs_size * ($this->page-1);

            foreach ($aggs_data_tmp as $key => $value) {
                if($key <= $rm_limit)
                {
                    unset($aggs_data_tmp[$key]);
                }
            }
        }

        //营销活动
        if($tab==4)
        {
            $data = $this->getSubjectInfos($aggs_data_tmp,'');

        }else{
            $data = $this->getDomainInfos($aggs_data_tmp,$click_tab,$field,$rv);
        }
        $data['status'] = 200;
        $data['total'] = $alltotal;
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 返回统计数据
     * @param  [type] $rs [description]
     * @return [type]     [description]
     */
    private function getAggs($rs)
    {
        $ads_json = json_decode($rs,true);
        $aggs['ads'] = empty($ads_json['aggregations']['ads']['value'])?0:$ads_json['aggregations']['ads']['value'];
        $aggs['trackers'] = empty($ads_json['aggregations']['trackers']['value'])?0:$ads_json['aggregations']['trackers']['value'];
        $aggs['advertisers'] = empty($ads_json['aggregations']['advertisers']['value'])?0:$ads_json['aggregations']['advertisers']['value'];

        $aggs['publishers'] = empty($ads_json['aggregations']['publishers']['value'])?0:$ads_json['aggregations']['publishers']['value'];

        $aggs['subjects'] = empty($ads_json['aggregations']['subjects']['value'])?0:$ads_json['aggregations']['subjects']['value'];

        $aggs['first_detected'] = empty($ads_json['aggregations']['first_detected']['value'])?0:$ads_json['aggregations']['first_detected']['value'];

        $aggs['last_detected'] = empty($ads_json['aggregations']['last_detected']['value'])?0:$ads_json['aggregations']['last_detected']['value'];

        return $aggs;
    }
    /**
     * 根据md5 的值 获取数据
     * @param  [type] $md5 [description]
     * @return [type]      [description]
     */
    private function getSubjectInfo($md5)
    {
        $curl_param = [
            'query'=>[
                'terms'=>[
                    'md5'=>$md5
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH6'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        return empty($data['data'])?[]:$data['data'];
    }
    /**
     * 获取营销活动信息
     * @param  [type] $md5  [营销活动md5 的值 数组]
     * @param  [fileds] $fileds [过滤字段 数组 ['type'=>'images']]
     * @return [type]       [description]
     */
    private function getSubjectInfos($md5,$fileds)
    {
        $md5_tmp = [];

        foreach ($md5 as $key => $value) {
            $md5_tmp[] = $value;
        }
        $data = [];
        foreach ($md5_tmp as $key => $value) {

            $curl_param = [
                'size'=>1,
                'sort'=>[
                    'created_date'=>[
                        'order'=>'asc'
                    ]
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    'subject_md5'=>$value
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'first_time'=>[
                        'min'=>[
                            'field'=>'created_date'
                        ]
                    ],
                    'last_time'=>[
                        'max'=>[
                            'field'=>'created_date'
                        ]
                    ],
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ],
                    'advertisers'=>[
                        'cardinality'=>[
                            'field'=>'advertiser_na'
                        ]
                    ],
                    'publishers'=>[
                        'cardinality'=>[
                            'field'=>'publisher_na'
                        ]
                    ]

                ]
            ];
            if(!empty($fileds))
            {
                foreach ($fileds as $fk => $fv) {

                    if(!empty($fv))
                    {
                        $item = [
                            'match'=>[
                                $fk=>$fv
                            ]
                        ];
                        array_push($curl_param['query']['bool']['must'], $item);
                    }


                }
            }

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);
            $data_ad = DBUtil::get_parse_es_data($rs);

            if(empty($data_ad['data'][0]))
            {
                continue;
            }
            $ad_info = $data_ad['data'][0];

            $rs_parse = json_decode($rs,true);
            $first_detected = $rs_parse['aggregations']['first_time']['value'];
            $last_detected = $rs_parse['aggregations']['last_time']['value'];


            $ad_info->ads = empty($rs_parse['aggregations']['ads']['value'])?0:$rs_parse['aggregations']['ads']['value'];


            $ad_info->advertisers = empty($rs_parse['aggregations']['advertisers']['value'])?0:$rs_parse['aggregations']['advertisers']['value'];
            $ad_info->publishers = empty($rs_parse['aggregations']['publishers']['value'])?0:$rs_parse['aggregations']['publishers']['value'];



            $ad_info->first_detected = empty($first_detected)?0:date('Y-m-d H:i:s',$first_detected/1000);
            $ad_info->last_detected = empty($last_detected)?0:date('Y-m-d H:i:s',$last_detected/1000);
            $data[] = $ad_info;
        }

        $data_tmp['data'] = $data;

        if(empty($data_tmp['data']))return [];


        //安装时间重新排序
        // $new_data = [];
        // foreach ($data_tmp['data'] as $key => $value) {
        //     $new_data[] = (array)$value;
        // }
        // // dd($new_data);
        // $data_tmp['data'] = DBUtil::my_sort($new_data,'created_date',SORT_DESC,SORT_NUMERIC);
        $data_tmp['data'] = $this->sortData($data_tmp['data'],'last_detected');
        // 暂无数据 2002
        return $data_tmp;
    }
    /**
     * 对数据重新排序
     * @return [type] [description]
     */
    private function sortData($data,$field)
    {
        //安装时间重新排序
        $new_data = [];
        foreach ($data as $sk => $sv) {
            $new_data[] = (array)$sv;
        }
        return DBUtil::my_sort($new_data,$field,SORT_DESC,SORT_NUMERIC);
    }
    /**
     *
     * @param  [type] $hosts      [域名数组]
     * @param  [type] $field      [当前点击统计字段]
     * @param  [type] $curl_role  [当前角色]
     * @param  [type] $curl_value [当前角色对应的值]
     * @return [type]             [description]
     */
    private function getDomainInfos($hosts,$field,$curl_role,$curl_value)
    {
        $hosts_tmp = [];
        foreach ($hosts as $k => $v) {
            $hosts_tmp[] = $v;
        }
        $curl_param = [
            'size'=>count($hosts),
            'query'=>[
                'terms'=>[
                    'host_no'=>$hosts_tmp
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return [];

        $curl_value_param = [];

        if(empty(!$curl_role) && !empty($curl_value))
        {
            if($curl_role == "tracker_list"){
                $curl_value_param = [
                    "bool" => [
                        'should' => [[
                            'term' => [
                                $curl_role => $curl_value
                            ]
                        ], [
                            'term' => [
                                "tags_list" => $curl_value
                            ]
                        ]]]];

            }else{
                $curl_value_param = [
                    'term'=>[
                        $curl_role=>$curl_value
                    ]
                ];

            }


        }

        //统计对应的创意数 营销活动数
        foreach ($data['data'] as $key => &$value) {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    $field=>$value->host_no
                                ]
                            ]//,
                            // [
                            //     'term'=>[
                            //         $curl_role=>$curl_value
                            //     ]
                            // ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ],
                    'advertisers'=>[
                        'cardinality'=>[
                            'field'=>'advertiser_na'
                        ]
                    ],
                    'publishers'=>[
                        'cardinality'=>[
                            'field'=>'publisher_na'
                        ]
                    ],
                    'trackers'=>[
                        'cardinality'=>[
                            'field'=>'tracker_list'
                        ]
                    ],
                    'subjects'=>[
                        'cardinality'=>[
                            'field'=>'subject_md5'
                        ]
                    ]
                ]
            ];

            if(!empty($curl_value_param))
            {
                array_push($curl_param['query']['bool']['must'], $curl_value_param);
            }

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

            $rs_parse = json_decode($rs,true);

            $value->ads = $rs_parse['aggregations']['ads']['value'];
            $value->advertiser_num = $rs_parse['aggregations']['advertisers']['value'];
            $value->publisher_num = $rs_parse['aggregations']['publishers']['value'];
            $value->tracker_num = $rs_parse['aggregations']['trackers']['value'];
            $value->subject_num = $rs_parse['aggregations']['subjects']['value'];
        }
        // 暂无数据 2002
        return empty($data['data'])?[]:$data;
    }

    /**
     * 获取对应的curl_param
     * @param  [type] $oldparam [description]
     * @param  [type] $size     [description]
     * @return [type]           [description]
     */
    private function getAdsCurlParam($oldparam,$field,$rv,$last_id,$tab)
    {
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym= $en_ch==1?$this->getSynonym($en_ch,$oldparam):'';
        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$oldparam:$c_param.' '.$c_host;
        // 默认
        $curl_param = [
            'size'=>$tab==5?$this->size:0,
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
                            ],
                            'must'=>[
                                [
                                    'term'=>[
                                        $field=>$rv
                                    ]
                                ],
                                [
                                    'range'=>[
                                        'id'=>[
                                            'lte'=>$last_id
                                        ]
                                    ]
                                ]
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
        if($field == "tracker_list"){
            unset($curl_param["query"]["bool"]["must"]);
            $curl_param["query"]["bool"]["should"] = [[
                'term'=>[
                    $field=>$rv
                ]
            ],[
                'term'=>[
                    "tags_list"=>$rv
                ]
            ]];



        }
        // 营销活动
        $curl_param_compan = [
            'size'=>$tab==5?$this->size:0,
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
                            ],
                            'must'=>[
                                [
                                    'term'=>[
                                        $field=>$rv
                                    ]
                                ],
                                [
                                    'range'=>[
                                        'id'=>[
                                            'lte'=>$last_id
                                        ]
                                    ]
                                ]
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


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }

        $curl_param = $this->isCurlCampaignMerge($curl_param,'',$c_host,$oldparam,$param);

        if(in_array($oldparam, config('param.WHITE_MIN_SCORE')))
        {
            $curl_param['min_score'] = 0;
        }
        return $curl_param;
    }

    /**
     * 首页 查询 使用
     * 获取 广告主 媒体　追踪者
     * host 关键词
     * type 0 返回所有字段 1 只返回 role host cname ename 字段
     */
    public function getQueryList(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $param = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;
        // 1成纯英文 2 中文 3 中英混合
        $is_en_ch = DBUtil::check_str_cn_en($param);
        // 字段 排序
        $orderBy = empty($request->orderBy)?'':$request->orderBy;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        // 判断是否含有 redis 缓存

        $data = [];
        if($is_en_ch==1)
        {
            // 2017-6-2 改成 sql 查看
            $sql = 'SELECT * FROM domain where role>0 AND ( host like "%'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%") limit '.$size;

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

                    $sql = 'SELECT * FROM domain where role>0 AND ( host like "%'.$param.'%") limit '.$size;

                    if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                    // dd($sql);


                    $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                    $data = DBUtil::get_parse_es_data($rs);
                }

            }



        }else{
            $curl_param = DSLUtil::domainDLSQueryParse($size,$param,0,0,'must',50,'cname');
            if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            $data = DBUtil::get_parse_es_data($rs);
        }

        // 暂无数据 2002
        if(empty($data['data']))
        {

            if($is_en_ch==1)
            {
                $data = $this->getDomainAutoCompletion($param,'');

                if(empty($data['data']))
                {
                    return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
                }
            }else{
                return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
            }
        }
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
     * 自动纠错 功能
     * finn 目前只针对 英文 中文 后续 研究
     * 2017-7-7
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    private function getDomainAutoCompletion($param,$role='')
    {
        $curl_param_auto = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'role'=>[
                                    'gte'=>0
                                ]
                            ]
                        ]
                    ]
                ]
            ],
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

        if(!empty($role))
        {
            $item = [
                'term'=>[
                    'role'=>$role
                ]
            ];
            array_push($curl_param_auto['query']['bool']['must'], $item);
        }

        $rs = HttpUtil::parse_curl_search_type(config('param.DOMAIN_AUTO'),$curl_param_auto,'');

        $rs_auto = json_decode($rs,true);


        if(empty($rs_auto['suggest']['my_suggest'][0]['options']))
        {
            return [];

        }

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
        return empty($data['data'])?[]:$data;
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

    /**
     * 根据关键词获取相关广告主信息
     * wd 关键词
     * size 当前页显示的条数
     * sort 排序方式 默认 desc
     * @return [type] [description]
     */
    public function getRelationAdvertisers(Request $request)
    {
        $key_cache = md5(implode('', $request->all()));
        // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        // {
        //     return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        // }
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $type = empty($request->type)?'':$request->type;
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);

        if(stristr($param,'????��?����??��???????'))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $oldparam = urldecode($param);
        // 2016-12-08 finn 判断是否是点击 campagin 1 是 默认不是
        $isclick = empty($request->isclik)?'':$request->isclik;
        $suid = empty($request->suid)?'':$request->suid;
        // 2016-11-30 新增  finn 判断是否 点击 营销活动
        $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;
        $page = empty($request->page)?1:$request->page;

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

        $role = empty($request->role)?2:$request->role;

        $orderBy = empty($request->orderBy)?'brand_ads':$request->orderBy;


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

        // DBUtil::setRedisData($key_cache,$rdata,config('param.REDIS_TIME'));

        return HttpUtil::retrun_json($rdata,200);
    }





    public function getadvertiserDomain($data)
    {
        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            $value = is_array($value)?$value:(array)$value;

            if(!empty($value['advertiser']) && !in_array($value['advertiser'], $domain_tmp))
            {
                $domain_tmp[] = $value['advertiser'];
            }
        }
        $domain_arr = [];
        foreach ($domain_tmp as $key => $value) {
            $curl_param = [
                'query'=>[
                    'term'=>[
                        'host'=>$value
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.DOMAIN_SEARCH_4').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data_d = DBUtil::get_parse_es_data3($rs);
            if(!empty($data_d['data']))
            {
                $domain_arr[] = (array)$data_d['data'][0];
            }
        }
        $return_data = [];
        foreach ($data['data'] as $key => $value) {
            $return_data[] = (array)$value;
        }
        foreach ($return_data as $key => &$value) {
            if(!empty($value['advertiser']))
            {
                foreach ($domain_arr as $k => $v) {
                    if($v['host']==$value['advertiser'])
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
     * 相关创意接口
     *
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function relationAds(Request $request)
    {

    }

    /**
     * 获取左侧 点击域名时期
     * 获取创意
     * @return [type] [description]
     */
    public function getAdverLeftAds(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $rv = empty($request->rv)?'':$request->rv;

        if(empty($role) || empty($rv))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $role_field = '';
        switch ($role) {
            case '1':
                # code...
                $role_field = 'advertiser_na';
                break;
            case '2':
                # code...
                $role_field = 'publisher_na';
                break;
            case '3':
                # code...
                $role_field = 'tracker_list';//tracker_list
                break;
        }
        $curl_param = [
            'size'=>$this->size,
            'from'=>$this->from,
            'query'=>[
                'term'=>[
                    $role_field=>$rv
                ]
            ],
            'sort'=>[
                [
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");


        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取广告主列表
     * wd 关键词
     * size 条数
     * 满足web 页面 左侧功能
     * @return [type] [description]
     */
    public function getAdverLeftListTop(Request $request)
    {
        $curl_param = [
            'size'=>0,
            'aggs'=>[
                'advertisers'=>[
                    'terms'=>[
                        'field'=>'advertiser_na',
                        'size'=>$this->size
                    ]
                ]
            ]
        ];
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $parse = json_decode($rs,true);

        if(empty($parse['aggregations']['advertisers']['buckets']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $advertisers = $parse['aggregations']['advertisers']['buckets'];
        $advertisers_tmp = [];
        foreach ($advertisers as $key => $value) {
            $advertisers_tmp[] = $value['key'];
        }
        $data = $this->getDomainByHosts($advertisers_tmp);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取域名
     * @param  [type] $param [description]
     * @return [type]        [description]
     */
    private function getDomainByHosts($param)
    {
        $curl_param = [
            'size'=>count($param),
            'query'=>[
                'terms'=>[
                    'host_no'=>$param
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        return $data;
    }
    /**
     * 根据 iscampaign 添加 参数
     * @return boolean [description]
     */
    private function isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param)
    {
        // dd($c_host);
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

            // dd($c_host);
            $curl_param = $this->compainCurl($curl_param,config('param.DOMAIN_SEARCH_HOST'),empty($c_host)?$oldparam:$c_host);


            if(DBUtil::check_str_cn_en($oldparam) == 1) {
                array_push($curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'], 'publisher');
            }else{
                array_push($curl_param['query']['function_score']['query']['bool']['should']['multi_match']['fields'], 'publisher');
            }

        }
        return $curl_param;
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

            $synonym = !count($synonym)?Synonym::where('ename','like','%'.$param.'%')->orwhere('host','like',$param.'%')->limit(3)->get():$synonym;

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
     * key rxJOYsbo04R6vpK13NViAmIhWS7T5PFdyq8ujEHk
     * @param  [type] $str [description]
     * @param  string $key [description]
     * @return [type]      [description]
     */
    private function userKey($str)
    {
        return '' === $str ? '' : md5(sha1($str) . $this->user_key);
    }
    /**
     * 非企业邮箱过滤
     * finn
     * 2016-12-01
     */
    public function emailFree($email)
    {
        $email_free = [
            'sohu.com','163.com','google.com',
            'yahoo.com','sina.com.cn','outlook.com','tom.com',
            '21cn.com','10086.cn','alo.com','aliyun.com','icloud.com',
            '126.com','yeah.net','netease.com','sogou.com','chinaren.com',
            '17173.com','189.com','wo.cn','cctv.com',
            '173.com','sina.com','tom.com','21cn.com','yeah.com',
            '5460.net','ymail.com','xuite.net','zzn.com','hushmail.com',
            'csdn.net','ovi.com','heimail.com','shortmail.com','opera.com',
            'baihui.com','facebook.com','lycos.com',
            'zj.com','188.com','21cn.com','bxemail.com','eyou.com',
            'gmail.com','china.com','lantern.cn','foxmail.com','live.cn','qq.com','dingtalk.com','vip.qq.com','139.com','hotmail.com',
            'hotmail.com','vip.163.com'
        ];

        $r_a = $_SERVER['SERVER_ADDR'];

        // if(!in_array($r_a, C('WHITE_HOST')))
        // {
        //     return false;
        // }

        $email = strtolower($email);
        $email_is = explode('@', $email);

        if(empty($email_is[1]))
        {
            return true;
        }
        foreach ($email_free as $key => $value) {
            if($email_is[1]==$value)
            {
                return true;
            }
        }
        return false;
    }



    /**
     * 登录
     * code  210  账号或者密码不可为空
     *       211 用户或者密码密码 212 未激活
     * account 邮箱
     * pwd 密码
     * @return [type] [description]
     */
    public function login(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $pwd = empty($request->pwd)?'':$request->pwd;

        if(empty($account) || empty($pwd))
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(210),200);
        }
        $pwd = $this->userKey($pwd);


        //mysql_oc_true
        $user = DB::connection('mysql_oc2018')->table('users')
            ->where(['email'=>$account,'password'=>$pwd])
            ->select('id','username','email','isonline','status','type')->first();

        if(empty($user))
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(211),200);
        }
         

        if($this->emailFree($user->email))return HttpUtil::retrun_json(HttpUtil::returnStatus(213),200);
        if($user->status != 1)
        {
            $token =  md5($user->id.$account);
            $url = 'http://testapi.adbug.cn/api/v7/valid/email/alive?uid='.$user->id.'&account='.$account.'&token='.$token;
            $title = 'Adbug';
            $data['email'] = $account;
            $data['subject'] = "Adbug激活码";
            $data['url'] = $url;
            $data['title'] = $title;
            $data['account'] = $account;
            $data['uid'] = $user->id;
            $data['token'] = $token;
            $data['view'] = 'email';
            Tools::sendMailWarn($data);
            return HttpUtil::retrun_json(HttpUtil::returnStatus(212),200);
        }

        $data['status'] = 200;
        $data['user'] = $user;

        //更改用户状态


        $this->upUserStatus($account,1);
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);



    }
    /**
     * 注销用户
     * account 邮箱
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function userDestory(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $this->upUserStatus($sccount,0);

        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 注销 登录状态更改
     * @return [type] [description]
     */
    private function upUserStatus($account,$alive)
    {
        DB::connection('mysql_oc2018')->table('users')
            ->where(['email'=>$account])->update(['isonline'=>$alive]);
    }
    /**
     * 判断是否登录
     * account 邮箱
     * @return boolean [description]
     */
    public function isOnline(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        if(empty($account))return HttpUtil::retrun_json(HttpUtil::returnStatus(210),200);
        $user = DB::connection('mysql_oc2018')->table('users')
            ->where('email',$account)->select('id','username','email','isonline','type')->first();

        if(empty($user))return HttpUtil::retrun_json(HttpUtil::returnStatus(301),200);
        $data['status'] = 200;
        $data['user'] = $user;
        $data['alive'] = empty($user->isonline)?0:$user->isonline;

        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 重置密码
     * code 210 账号密码不可为空
     *      301 用户不存在
     *      302 验证码不存在
     *      305 更新密码失败
     * active 激活码
     * pwd 密码
     * account 账号
     * type 1 获取激活码 2 提交 修改密码
     * @return [type] [description]
     */
    public function resetPwd(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $pwd = empty($request->pwd)?'':$request->pwd;
        $type = empty($request->type)?'1':$request->type;

        $active = empty($request->active)?'':$request->active;



        if(empty($account))return HttpUtil::retrun_json(HttpUtil::returnStatus(210),200);

        $user = DB::connection('mysql_oc2018')->table('users')
            ->where('email',$account)->first();

        if(empty($user))return HttpUtil::retrun_json(HttpUtil::returnStatus(301),200);

        //获取激活码
        if($type == 1)
        {
            $reset_user = DB::connection('mysql_oc2018')->table('resets')->where('email',$account)->first();

            $code = empty($reset_user)?rand(100000,999999):$reset_user->code;

            if(empty($reset_user)){
                DB::connection('mysql_oc2018')->table('resets')->insert(['email'=>$account,'code'=>$code]);
            }

            $data['email'] = $account;
            $data['view'] = 'reset';
            $data['subject'] = "Adbug 验证码";
            $data['title'] = 'Adbug';
            $data['code'] = $code;
            Tools::sendMailWarn($data);
            return HttpUtil::retrun_json(HttpUtil::returnStatus(200),200);
        }

        if($type==2)
        {
            if(empty($active) && empty($pwd))return HttpUtil::retrun_json(HttpUtil::returnStatus(210),200);


            $reset_user = DB::connection('mysql_oc2018')->table('resets')->where(['email'=>$account,'code'=>$active])->first();

            if(empty($reset_user))return HttpUtil::retrun_json(HttpUtil::returnStatus(302),200);

            $pwd = $this->userKey($pwd);

            if(!DB::connection('mysql_oc2018')->table('users')->where('email',$account)->update(['password'=>$pwd]))
            {
                return HttpUtil::retrun_json(HttpUtil::returnStatus(305),200);
            }
            return HttpUtil::retrun_json(HttpUtil::returnStatus(200),200);
        }
    }

    /**
     * 激活邮箱验证码
     * @return [type] [description]
     */
    public function validEmailAlive(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $uid = empty($request->uid)?'':$request->uid;
        $referee_id = empty($request->referee_id)?'':$request->referee_id;
        $token = empty($request->token)?'':$request->token;
        if(empty($account) || empty($uid) || empty($token))dd('valid error');

        if(md5($uid.$account) != $token)abort('403','token 验证失败, 请联系管理人员 1620683945@qq.com');

        $user = DB::connection('mysql_oc2018')->table('users')
            ->where('id',$uid)->first();

        if(empty($user))abort('403','该用户不存在, 请联系管理人员 1620683945@qq.com');

        if($user->email != $account)abort('403','邮箱和用户不符合, 请联系管理人员 1620683945@qq.com');
        if($user->status == 1)
        {
            header('Location: http://www.adbug.cn');
            exit;
        }
        if(!DB::connection('mysql_oc2018')->table('users')->where('id',$uid)->update(['status'=>1]))
        {
            abort('403','激活失败, 请联系管理人员 1620683945@qq.com');
        }
        if(!empty($referee_id))$this->RefereeDo($uid,$referee_id);
        header('Location: http://www.adbug.cn');
        exit;
    }

     private function RefereeDo($user_id, $referee_id)
    {
        if (DB::connection('mysql_oc2018')->table('user_recommend_infor')->where("user_id", $user_id)->update(["is_activation" => 1])) {
            $count = DB::connection('mysql_oc2018')->table('user_recommend_infor')->where("referee_id", $referee_id)->where("is_valid", 0)->where("is_activation", 1)->count();
            if ($count == 3) {
                $vip_deadline = date("Y-m-d H:i:s", strtotime("+1 month "));
                $rs = DB::connection('mysql_oc2018')->table('users')->where('id', $referee_id)->first();
                if ($rs->role == 0) {
                    DB::connection('mysql_oc2018')->table('users')->where('id', $referee_id)->update(['role' => 1, 'vip_deadline' => $vip_deadline]);
                    DB::connection('mysql_oc2018')->table('user_recommend_infor')->where("referee_id", $referee_id)->where("is_activation", 1)->update(['is_valid' => 1]);
                }
            }
        }
    }
    
    /**
     * 注册
     * code 201 请使用企业邮箱注册
     *      202 账号 密码不可为空
     *      203 两次输入密码不一致
     *      205 该邮箱已经被注册
     *      206 用户名已经存在
     * account 账号
     * pwd 密码
     * setpwd 重复密码
     * username 用户名
     * @return [type] [description]
     */
    public function register(Request $request)
    {
        $account = empty($request->account)?'':$request->account;
        $pwd = empty($request->pwd)?'':$request->pwd;
        $username = empty($request->username)?'':$request->username;
        $setpwd = empty($request->setpwd)?'':$request->setpwd;
        $referee_id = empty($request->referee_id)?'':$request->referee_id;
        // dd($request->all());

        //字段不可为空
        if(empty($account) || empty($pwd) || empty($setpwd))
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(202),200);
        }
        //邮箱验证
        if($this->emailFree($account))return HttpUtil::retrun_json(HttpUtil::returnStatus(201),200);
        //重置密码
        if($pwd!=$setpwd)
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);
        }
        //判断用户名是否存在

        // $user = DB::connection('mysql_oc2018')->table('users')
        // ->where(['nickname'=>$username])->first();

        // if(!empty($user))
        // {
        //     return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        // }

        //判断账号是否存在
        $user = DB::connection('mysql_oc2018')->table('users')
            ->where(['email'=>$account])->first();

        if(!empty($user))
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(205),200);
        }
        //插入数据
        $data['password'] = $this->userKey($pwd);
        // $data['username'] = $username;
        // $data['nickname'] = $username;
        $data['email'] = $account;
        $data['status'] = 3;//待激活
        $data['type'] = 2;
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $data['Referee'] = $referee_id;

        $flag = false;
        //开启事物
        DB::connection('mysql_oc2018')->beginTransaction();
        if(!DB::connection('mysql_oc2018')->table('users')->insert($data))
        {

            $flag = true;
            DB::connection('mysql_oc2018')->rollBack();
        }
        if($flag)
        {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        }
        DB::connection('mysql_oc2018')->commit();

        //发送激活邮箱
        $user = DB::connection('mysql_oc2018')->table('users')
            ->where(['email'=>$account])->first();

        $token =  md5($user->id.$account);

        $url = 'http://testapi.adbug.cn/api/v7/valid/email/alive?uid='.$user->id.'&account='.$account.'&token='.$token.'&referee_id='.$referee_id;
        $title = 'Adbug';

        $data['email'] = $account;
        $data['subject'] = "Adbug激活码";
        $data['url'] = $url;
        $data['title'] = $title;
        $data['account'] = $account;
        $data['uid'] = $user->id;
        $data['token'] = $token;
        $data['view'] = 'email';
        Tools::sendMailWarn($data);
        if(!empty($referee_id)){
            DB::connection('mysql_oc2018')->table('user_recommend_infor')->insert(["user_id"=>$user->id,"referee_id"=>$referee_id]);
        }
        return HttpUtil::retrun_json(HttpUtil::returnStatus(200),200);
    }


    /**author:zhujh
     * 更改广告创意的信息
     * id 广告创意id
     * host 广告主域名
     * title 广告标题
     * name 广告主名称
     * @return [type] [description]
     */
    public function adsinforupdate(Request $request){
        $id = empty($request->id)? "89007069" : $request->id;
        $host = empty($request->advertiser_na)? "yslbeautycn.com" : $request->advertiser_na;
        $name = empty($request->advertiser_name_title)? "YSL圣罗兰美妆官方网站" : $request->advertiser_name_title;
        $ename = empty($request->advertiser_ename_title)? "" : $request->advertiser_ename_title;
        $title = empty($request->title)? "YSL圣罗兰美妆官方网站暨网上商城 - 着我装者, 着我妆" : $request->title;
        $type = empty($request->type)? "" : $request->type;
        $host_id = empty($request->host_id)? "" : $request->host_id;
        DB::beginTransaction();
        try{
            if($type == 1){
                if(empty($host_id)){
                    throw new \Exception("缺少参数");
                }else{
                    $data["host"]=$host;
                    $data["cname"]=$name;
                    $data["ename"]=$ename;
                    DB::table('domains')->where('id',$host_id)->update($data);
                }
            }else{
                if(empty($id) || empty($host) || empty($title)) throw new \Exception("缺少参数");
                $rs = DB::table('domains')->where('host',$host)->first();
                if( !isset($rs))throw new \Exception("该主机域名不存在，请重新填写");
                DB::table('addata_2018')->where('id',$id)->update(['title' => $title,'advertiser'=>$rs->host]);
                DB::table('main_index')->where('id',$id)->update(["advertiser"=>$rs->id]);


            }

            DB::commit();
            $data['message'] = "success";
            $data['status'] = "200";
        } catch (\Exception $e){
            DB::rollback();
            $data['message'] = $e->getMessage();
            $data['status'] = "208";
        }
        return HttpUtil::retrun_json($data,200);
    }


    /**author:zhujh
     * 获取 ott 信息列表(新版本)
     * 满足首页需求
     * size 列表个数 默认 10 条 最大200
     * @return [type] [description]
     */
    public function getOttListNew(Request $request)
    {  $size = empty($request->size)? "10" :$request->size;
        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'term'=>[
                    "attribute06"=>"ott"
                ]
            ],
        ];
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $data = $this->getadvertiserDomain($data);
        $data['status'] = 200;
        $data['total'] = $size;
        return HttpUtil::retrun_json($data,200);

    }

    /** author:zhujh
     * 统计最新注册人数以及注册人数详细信息
     * type 新增类型 0 所有用户 1今日新增用户  2本周新增用户 3 上周新增用户
     * @return [type] [description]
     */
    public function addnewcount(Request $request){
        $type = empty($request->type)? "" : $request->type;
        $page = empty($request->page)? "0"  : $request->page;
        if(!empty($request->search_email)){
            $query = DB::connection('mysql_oc2018')->table('users')->where('email', 'like', '%'.$request->search_email.'%');
        }else{
            if($type == 1){
                $query = DB::connection('mysql_oc2018')->table('users')->where('created_at','>=',date('Y-m-d',time()));
            }elseif($type == 2){
                //$query = DB::connection('mysql_oc2018')->table('users')->where('created_at','>=',date('Y-m-d',strtotime('-1 week')));
                $query = DB::connection('mysql_oc2018')->table('users')->where('created_at','>=',date('Y-m-d',strtotime('this week')));
            }elseif($type == 3){
                $query = DB::connection('mysql_oc2018')->table('users')->whereBetween('created_at',[date('Y-m-d',strtotime("last week")),date('Y-m-d',strtotime('this week'))]);
            }else{
                $query = DB::connection('mysql_oc2018')->table('users');
            }
        }

        $alltotal = $query->count();
        $rs = $query->orderBy('created_at','desc')->offset($this->size*($page-1))->limit($this->size)->get();
        $result = [];
        foreach ($rs as $v => $value){
            $k["uid"] = $value->id;
            $k["username"] = $value->username;
            $k["uid"] = $value->id;
            $k["username"] = $value->username;
            $k["email"] = $value->email;
            $k["nickname"] = $value->nickname;
            $k["uid"] = $value->id;
            $k["username"] = $value->username;
            $k["status"] = $value->status;
            $k["created_at"] = $value->created_at;
            array_push($result,$k);
        }

        $data['total'] = $alltotal;
        $data['size'] = $this->size;
        $data['all_page'] = ceil($alltotal/$this->size);
        $data["result"] = $result;
        return HttpUtil::retrun_json($data,200);

    }

    /**author:zhujh
     * 注册用户处理
     * type 处理 0 启用 1 禁用 2 删除 3 重置密码
     * uid  用户id
     * pwd  重置密码
     * @return [type] [description]
     */
    public function userdeal(Request $request){
        $type = empty($request->type)? "" : $request->type;
        $uid = empty($request->uid)? "" : $request->uid;
        $pwd = empty($request->pwd)? "" : $request->pwd;

        if(strstr($uid, ',')){
            $condition = explode(",",$uid);
        }else{
            $condition[0] = $uid;
        }
        if($type == 1){
            foreach ($condition as $key=>$value){
                DB::connection('mysql_oc2018')->table('users')->where(["id"=>$value])->update(["status"=>3]);
            }
        }elseif($type == 0){
            foreach ($condition as $key=>$value){
                DB::connection('mysql_oc2018')->table('users')->where(["id"=>$value])->update(["status"=>1]);
            }
        }elseif ($type == 2){
            foreach ($condition as $key=>$value){
                DB::connection('mysql_oc2018')->table('users')->where(["id"=>$value])->delete();
            }
        }elseif ($type == 3){
            $pwd = $this->userKey($pwd);
            foreach ($condition as $key=>$value){
                DB::connection('mysql_oc2018')->table('users')->where(["id"=>$value])->update(["password"=>$pwd]);
            }
        }
        return HttpUtil::retrun_json("修改成功",200);
    }


	public function cnameUpdate(Request $request)
    {
        $host = $request->host;
        $cname = $request->cname;
        if (empty($host) || empty($cname)) return HttpUtil::retrun_json(["status" => 211, "message" => "缺少参数"], 200);
        $curl_param = [
            "query" =>
                ["bool" =>
                    ["must" => [
                        ["term" => ["host_no" => $host]]
                    ]]
                ],
        ];
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'), $curl_param);
        $data= DBUtil::get_parse_es_data($rs);
        $id = $data["data"][0]->id;
        $curl_param = [
            "doc" => [
                "cname" => $cname
            ]
        ];
        $url = config('param.ES_URL') . config('param.DOMAIN_SEARCH') . '/' . $id . '/_update';
        $rs = HttpUtil::send_curl_methods($url, $curl_param, "POST");
        $rs = json_decode($rs, true);
        if ($rs["_shards"]["failed"] == 0) {
            $data["status"] = 200;
            $data["message"] = "修改成功";
            return HttpUtil::retrun_json($data, 200);
        }
        $data["status"] = 211;
        $data["message"] = "修改失败";
        return HttpUtil::retrun_json($data, 200);
    }



    /**
     * 获取热门域名列表
     * type 1 首页 2 详细页列表
     * @return [type] [description]
     */
    public function test3(Request $request)
    {
        $type = empty($request->type)?'':$request->type;

        if(empty($type))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);



        $hosts = DB::connection('mysql_oc2018')->table('hotroles')->get();

        $alltotal = count($hosts);



        // $hotroles = DB::connection('mysql_oc2018')->table('hotroles');

        $data['total'] = $alltotal;

        switch ($type) {
            case '1':
                # code...
                $data['publishers'] = DB::connection('mysql_oc2018')->table('hotroles')->where('role',1)->limit($this->size)->get();
                $data['advertisers'] = DB::connection('mysql_oc2018')->table('hotroles')->where('role',2)->limit($this->size)->get();
                $data['trackers'] = DB::connection('mysql_oc2018')->table('hotroles')->where('role',3)->limit($this->size)->get();
                break;
            case '2':
                $from = ($this->page-1) * $this->size;
                # code...
                $data['all'] = DB::connection('mysql_oc2018')->table('hotroles')->offset($from)->limit($this->size)->get();
                break;
        }

        if($type==1)
        {

            $data['publishers'] = empty($data['publishers'])?[]:$this->mergeHostInfo($data['publishers']);
            $data['advertisers'] = empty($data['advertisers'])?[]:$this->mergeHostInfo($data['advertisers']);
            $data['trackers'] = empty($data['trackers'])?[]:$this->mergeHostInfo($data['trackers']);
        }else{
            $data['all'] = empty($data['all'])?[]:$this->mergeHostInfo($data['all']);
        }
        dd($data);
        $data['cur_page'] = $this->page;
        $data['size'] = $this->size;
        $data['all_page'] = ceil($alltotal/$this->size);
        return HttpUtil::retrun_json($data,200);

    }

    public function loginNew(Request $request)
    {
        $account = empty($request->account) ? '' : $request->account;
        $pwd = empty($request->pwd) ? '' : $request->pwd;

        if (empty($account) || empty($pwd)) {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(210), 200);
        }
        if (!Auth::attempt(array('email' => $account, 'password' => $pwd))) {
            return HttpUtil::retrun_json(HttpUtil::returnStatus(211), 200);
        }
        $rs = Auth::user()->toarray();
        if ($this->emailFree($rs["email"])) return HttpUtil::retrun_json(HttpUtil::returnStatus(213), 200);
        if ($rs["status"] != 1) {
            $token = md5($rs["id"] . $account);
            $url = 'http://testapi.adbug.cn/api/v7/valid/email/alive?uid=' . $rs["id"] . '&account=' . $account . '&token=' . $token . '&referee_id=' . $rs["Referee"];
            $title = 'Adbug';
            $data['email'] = $account;
            $data['subject'] = "Adbug激活码";
            $data['url'] = $url;
            $data['title'] = $title;
            $data['account'] = $account;
            $data['uid'] = $rs["id"];
            $data['token'] = $token;
            $data['view'] = 'email';
            Tools::sendMailWarn($data);
            return HttpUtil::retrun_json(HttpUtil::returnStatus(212), 200);
        }

        $data['status'] = 200;
        $data['message'] = "登录成功";

        //更改用户状态


        $this->upUserStatus($account, 1);
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data, 200);


    }




      public function test(Request $request)
    {   
        $param = empty($request->wd)?'':$request->wd;
        $tab = empty($request->tab)?'1':$request->tab;
        $page = empty($request->page)?'1':$request->page;
        $param = urldecode($param);
        $oldparam = urldecode($param);
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if($tab > 4)return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $type = empty($request->type)?'':$request->type;


        // 1成纯英文 2 中文 3 中英混合
        $is_en_ch = DBUtil::check_str_cn_en($param);
        // 字段 排序
        $orderBy = empty($request->orderBy)?'brand_ads':$request->orderBy;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);


        $role_field = 'advertiser_na';

        $role = '2';
        switch ($tab) {
            case '1':
                $role = '2';
                $role_field = 'advertiser_na';
                break;
            case '2':
                $role = '1';
                $role_field = 'publisher_na';
                break;
            case '3':
                $role = '3';
                $role_field = 'tracker_list';
                break;
        }

        // 判断是否含有 redis 缓存

        $data = [];
        if($is_en_ch==1)
        {
            // 2017-6-2 改成 sql 查看
            $sql = 'SELECT * FROM domain where role='.$role.' AND ( host like "'.$param.'%" or ename like "%'.$param.'%" or cname like "%'.$param.'%")';
            if($orderBy)$sql=$sql.'order by '.$orderBy.' desc';
            $sql = $sql.' limit '.$size*($page-1).', '.$size;
            dd($sql);
            $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");
            $data = DBUtil::get_parse_es_data($rs);
            if($param == "qq.com" && $role == 3 ){
                $role_1 = 1;
                $sql = 'SELECT * FROM domain where istracker='. $role_1.' AND ( host = "'.$param.'" or ename = "'.$param.'%" or cname = "'.$param.'") limit 0 , '.$size;
                if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");
                $data1 = DBUtil::get_parse_es_data($rs);
                if($page == 1){
                    array_push($data["data"],$data1["data"][0]);
                }
                $data["total"] = $data["total"]+1;
            }


            dd($data);
            if(empty($data['data']))
            {
                $param_num = explode(' ', $param);

                if(count($param_num)>1)
                {
                    $param = str_replace(' ', '', $param);

                    $param = strtolower($param);

                    $sql = 'SELECT * FROM domain where role='.$role.' AND ( host like "%'.$param.'%") limit '.$this->page.' , '.$size;

                    if($orderBy)$sql=$sql.' '.$orderBy.' desc';

                    // dd($sql);


                    $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

                    $data = DBUtil::get_parse_es_data($rs);
                }

            }



        }else{
            $curl_param = DSLUtil::domainDLSQueryParse($size,$param,$role,0,'must',50,'cname');

            $curl_param['from'] = $this->from;

            if($orderBy)$curl_param['sort']=[$orderBy=>'desc'];

            // dd(json_encode($curl_param));
            $rs = HttpUtil::parse_curl_search_type(config('param.DOMAINS'),$curl_param,$type);
            $data = DBUtil::get_parse_es_data($rs);
        }

        // 暂无数据 2002
        if(empty($data['data']))
        {

            if($is_en_ch==1)
            {
                $data = $this->getDomainAutoCompletion($param,$role);

                if(empty($data['data']))
                {
                    return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
                }
            }else{
                return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
            }
        }
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

        foreach ($data['data'] as $k => &$v) {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'term'=>[
                                $role_field=>$v->host
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
                    'advertisers'=>[
                        'cardinality'=>[
                            'field'=>'advertiser_na'
                        ]
                    ],
                    'publishers'=>[
                        'cardinality'=>[
                            'field'=>'publisher_na'
                        ]
                    ],
                    'trackers'=>[
                        'cardinality'=>[
                            'field'=>'tracker_list'
                        ]
                    ],
                    'subjects'=>[
                        'cardinality'=>[
                            'field'=>'subject_md5'
                        ]
                    ]
                ]
            ];
            if($tab == 3){
                unset($curl_param["query"]["bool"]["must"]);
                $curl_param["query"]["bool"]["should"] = [[
                    'term'=>[
                        $role_field=>$v->host
                    ]
                ],[
                    'term'=>[
                        "tags_list"=>$v->host
                    ]
                ]];

            }
            $rs_do = HttpUtil::parse_curl_search_type(config('param.ADDATA_SEARCH'),$curl_param,'');
            $rs_parse = json_decode($rs_do,true);
            $v->ads = $rs_parse['aggregations']['ads']['value'];
            $v->advertiser_num = $rs_parse['aggregations']['advertisers']['value'];
            $v->publisher_num = $rs_parse['aggregations']['publishers']['value'];
            $v->tracker_num = $rs_parse['aggregations']['trackers']['value'];
            $v->subject_num = $rs_parse['aggregations']['subjects']['value'];
        }
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);



        dd();




        $param = empty($request->wd)?'':$request->wd;
        $tab = empty($request->tab)?'1':$request->tab;
        $param = urldecode($param);
        $oldparam = urldecode($param);

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if($tab > 4)return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym = $en_ch==1?$this->getSynonym($en_ch,$param):'';

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param.' '.$c_host;

        $curl_param = $this->getCurlParam($oldparam,0,0,0);

        $field_aggs = 'advertiser_na';
        switch ($tab) {
            case '1':
                $field_aggs = 'advertiser_na';
                break;
            case '2':
                $field_aggs = 'publisher_na';
                break;
            case '3':
                $field_aggs = 'tracker_list';
                break;
            case '4':
                $field_aggs = 'subject_md5';
                break;
        }
        $curl_param['aggs'] = [
            $field_aggs=>[
                'terms'=>[
                    'field'=>$field_aggs,
                    'size'=>$this->aggs_all
                ]
            ],
            'alltotal'=>[
                'cardinality'=>[
                    'field'=>$field_aggs
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH6'),$curl_param);

        $rs_parse = json_decode($rs,true);

        $data['status'] = 200;
        if(empty($rs_parse['aggregations'][$field_aggs]['buckets']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }


        $aggs_buckets = $rs_parse['aggregations'][$field_aggs]['buckets'];

        $aggs_buckets_item = $this->parsePage($aggs_buckets);

        $data['status'] = 200;

        if($tab != 4)
        {
            $data = $this->getDomainInfos($aggs_buckets_item,$field_aggs,'','');
        }else{
            $data = $this->getSubjectInfos($aggs_buckets_item,'');
        }

        $alltotal = empty($rs_parse['aggregations']['alltotal']['value'])?0:$rs_parse['aggregations']['alltotal']['value'];
        $data['total'] = $alltotal;
        return HttpUtil::retrun_json($data,200);
    }
}
