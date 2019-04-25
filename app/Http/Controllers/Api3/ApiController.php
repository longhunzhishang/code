<?php
namespace App\Http\Controllers\Api3;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\ESUtil;
use App\Http\Utils\DSLUtil;
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

use Illuminate\Support\Facades\Redis as Redis;
/**
 *  1 获取 所有 域名 
 *  2 domians sort 排序
 *  3 getPubTacAdsIndex 广告主 媒体 追踪者 index  接口
 */
class ApiController extends Controller
{
    /**
     * 获取 ott 信息列表
     * size 列表个数 默认 10 条 最大200
     * @return [type] [description]
     */
    public function getOttList(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if($size>1000)$size = 200;

        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'term'=>[
                    'ott_type'=>1
                ]
            ]
        ];
        $url = config('param.ES_URL').config('param.ADDATA_OTT_SEARCH').'/'.config('param.ES_SEARCH');

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
        $data['status'] = 200;

        DBUtil::setRedisData(md5(implode('', $request->all())),$data,config('param.REDIS_TIME'));

        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 垃圾代码
     * 获取 最近一个月  的数据
     * 媒体 对应的平台 和 创意 数
     * @return [type] [description]
     */
    public function getAppPIds(Request $request)
    {
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>'2017-07-10 00:00:00',
                                    'lte'=>'2017-08-10 23:59:59'
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                'platform'=>2
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'publisher'=>[
                    'terms'=>[
                        'field'=>'publisher_na',
                        'size'=>0
                    ],
                    'aggs'=>[
                        'attribute04'=>[
                            'terms'=>[
                                'field'=>'attribute04'
                            ],
                            'aggs'=>[
                                'ids'=>[
                                    'cardinality'=>[
                                        'field'=>'id'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $rs_data = json_decode($rs,true);


        $rs_data_pub = $rs_data['aggregations']['publisher']['buckets'];


        $cellData = [
            ['媒体','名称','平台','创意数(去重)']
        ];
        foreach ($rs_data_pub as $key => $value) {
            $item['name'] = $value['key'];
            $domain = Domains::where('host',$value['key'])->first();

            $item['cname'] = empty($domain->cname)?(empty($domain->ename)?'':$domain->ename):$domain->cname;
            $item['platform'] = $value['attribute04']['buckets'][0]['key'];
            $item['ads'] = $value['attribute04']['buckets'][0]['ids']['value'];

            array_push($cellData, $item);
        }
         Excel::create('媒体 app 详细',function($excel) use ($cellData){
                    $excel->sheet('score', function($sheet) use ($cellData){
                        $sheet->rows($cellData);
                    });
            })->export();

        dd('ok');
    }

    /** 
     * 垃圾代码
     * @return [type] [description]
     */
    public function datars()
    {
        dd(404);
    }
    /**
     * 定时 更新最近数据到 es
     * es/bulk
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function bulkEsData(Request $request)
    {
        
        // 一次性导入 数据条数
        $limit = empty($request->limit)?100:$request->limit;
        // index 类型
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
            case '8':
                # code...
                $search = 'addata_aggs/addata_index';
                break;
            case '9':
                # code...
                $search = config('param.RANKROLE_SEARCH');
                break;
            case '10':
                # code...
                $search = config('param.ADDATA_ATTR_SEARCH');
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
        $last_id = empty($rs->aggregations->maxid->value)?0:$rs->aggregations->maxid->value;

        if(empty($last_id))dd('is no new data');
        // $last_id = 100;

        $sql = 'SELECT addata.id,(CASE WHEN u.risk_4=1 OR u.risk_1=1 OR u.risk_2=1 OR u.risk_3=1 THEN 1 ELSE 0 END ) as risk ,addata.tags as tags,t.tags as tags_list,t.trackers_list as tracker_list,addata.width/addata.height as whdivided,addata.advertiser as advertiser_na,addata.publisher as publisher_na,addata.trackers as tracker_na,addata.attribute08,addata.title as title_no,addata.width * addata.height as wharea,main_index.`subject`,addata.url,addata.session_id,addata.volume,addata.material,addata.screen,addata.url_md5,a.publisher_advertiser,main_index.ranker as ad_rank,b.tracker_subjects,b.role,b.host as domain_host,b.brand_ads,b.publisher_ads,b.tracker_advertiser,b.brand_subjects,b.tracker_ads,d.title as subject_title,d.md5 as subject_md5,addata.advertiser,addata.trackers, addata.publisher,addata.platform, addata.type,addata.created_date,FROM_UNIXTIME(addata.created_date/1000,"%Y-%m-%d %H:%i:%s") as date5,addata.shape,addata.domain,addata.attribute04,addata.width,addata.height ,addata.size,addata.x,addata.y,IF(LEFT(addata.target_url,1)="{" || RIGHT(addata.target_url,1)="}","",addata.target_url) as target_url,addata.thumbnail,addata.last_seen,addata.md5,addata.thumb_width,addata.thumb_height,addata.original_url,addata.thumb_url,addata.share_url, addata.title,b.host as advertiser_name, concat(IFNULL(b.cname,""), IFNULL(b.ename,"")) as advertiser_name_title, concat(b.host, " ", concat(IFNULL(b.cname,""), IFNULL(b.ename,""))) as advertiser_full,  a.host as publisher_name,  concat(a.host, " ", concat(IFNULL(a.cname,""), IFNULL(a.ename,""))) as publisher_full, c.host as tracker_name, concat(c.host, " ", concat(IFNULL(c.cname,""), IFNULL(c.ename,""))) as tracker_full, d.rank as subject_rand FROM addata LEFT JOIN main_index on addata.id = main_index.id LEFT JOIN addata_tags as t ON (addata.id = t.id) LEFT JOIN subjects as d ON (d.id = main_index.subject) LEFT JOIN domains as a ON (a.id = main_index.publisher) LEFT JOIN domains as b ON (b.id = main_index.advertiser) LEFT JOIN domains as c ON (c.id = main_index.tracker) LEFT JOIN original_urls AS u ON (u.md5=addata.url_md5)  WHERE addata.id>'.$last_id.' ORDER BY addata.id asc limit '.$limit;

        $ad = DB::select($sql);

        $new_data = [];

        foreach ($ad as $key => &$value) {
            $value = (array)$value;
        };


        foreach ($ad as $k => &$v) {

            // $url = config('param.ES_URL').'addata_v26/addata_index/'.$v['id'];
            
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.$v['id'];

            if(!empty($v['tags_list']))
            {
                if(stristr($v['tags_list'], '['))
                {
                    $v['tags_list'] = str_replace('[', '', $v['tags_list']);
                }
                if(stristr($v['tags_list'], ']'))
                {
                    $v['tags_list'] = str_replace(']', '', $v['tags_list']);
                }

                if(stristr($v['tags_list'], '"'))
                {
                    $v['tags_list'] = str_replace('"', '', $v['tags_list']);
                }
                
                $v['tags_list'] = explode(',',$v['tags_list']);
            }

            if(!empty($v['tracker_list']))
            {
                $v['tracker_list'] = str_replace('"', '', $v['tracker_list']);
                $v['tracker_list'] = explode(',',$v['tracker_list']);
            }
            // 添加新的数据
            HttpUtil::send_curl_methods($url,$v,'PUT');
        }
    }
    /**
     * 根据 binlog 指定的 域名  获取 昨天新增的创意
     * @return [type] [description]
     */
    public function getBinLogAds(Request $request)
    {
        $before_day = date("Y-m-d",strtotime("-1 day"));//一天前
        $start_time = strtotime($before_day.' 00:00:00').'000';
        $end_time = strtotime($before_day.' 23:59:59').'000';

        $role = empty($request->role)?'':$request->role;
        $host = empty($request->host)?'':$request->host;
        $is_tag = empty($request->is_tag)?'':$request->is_tag;// 跟踪者 二级域名

        if(empty($role))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);

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
                $role_field = 'tracker_list';//tracker_list
                break;
            default:
                # code...
                break;
        }
        if($is_tag==1 && $role==3)$role_field = 'tags_list';


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
                        ],
                        [
                            'term'=>[
                                $role_field=>$host
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
                ]
            ]
        ];


        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        $ads = $rs_parse['aggregations']['ads']['value'];


        return HttpUtil::retrun_json(['data'=>[],'ads'=>$ads,'status'=>200],200);

    }
    /**
     * 获取 排名榜 前 200 广告主
     * @return [type] [description]
     */
    public function getRankAdvertiser(Request $request)
    {
        $before_day = date("Y-m-d",strtotime("-1 day"));//一天前

        $start_time = strtotime($before_day.' 00:00:00').'000';
        $end_time = strtotime($before_day.' 23:59:59').'000';



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
                'advertiser'=>[
                    'terms'=>[
                        'field'=>'advertiser_na',
                        'size'=>200,
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

        $url = config('param.ES_URL').config('param.RANKROLE_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        if(empty($rs_parse['aggregations']['advertiser']['buckets']))
        {
            return [];
        }

        $rs_data = $rs_parse['aggregations']['advertiser']['buckets'];


        $host_domain = [];
        foreach ($rs_data as $key => $value) {

            if(!empty($value['key']))
            {
                if($value['key']=='com' || $value['key']=='cn')
                {
                    continue;
                }else{

                    $item['host'] = $value['key'];
                    $host_domain[] = $item;
                }
                
            }
            # code...
        }
        shuffle($host_domain);
        


        foreach ($host_domain as $key => $value) {

            if(empty($value['host']))unset($host_domain[$key]);
            if(strlen($value['host'])>10)unset($host_domain[$key]);
        }

        foreach ($host_domain as $key => &$value) {

            $curl_param_domain = [
                'query'=>[
                    'term'=>[
                        'host_no'=>$value['host']
                    ]
                ]
            ];


            $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param_domain,"POST");

           

            $data = DBUtil::get_parse_es_data($rs);
            // 暂无数据 2002
            if(empty($data['data']))
            {
                unset($host_domain[$key]);
                continue;
            }else{
                if($data['data'][0]->role!=2)
                {
                     unset($host_domain[$key]);
                     continue;
                }else{
                    $value['cname'] = $data['data'][0]->cname;
                    $value['ename'] = $data['data'][0]->ename;
                }
            }

        }

        $host_domain_new = [];
        $is_cname = 0;
        $is_ename = 0;
        foreach ($host_domain as $key => $value) {
            if(!empty($value['cname']) && strlen($value['cname'])<8 && $is_cname<2 && count($host_domain_new)<5)
            {
                $host_domain_new[] = $value['cname'];
                $is_cname ++;

                continue;
            }

            if(!empty($value['ename']) && strlen($value['ename'])<8 && $is_ename<2 && count($host_domain_new)<5)
            {
                $host_domain_new[] = $value['ename'];
                $is_ename ++;
                continue;
            }

            if(count($host_domain_new)<5)
            {
                $host_domain_new[] = $value['host'];
                continue;
            }
        }

        // dd($host_domain_new);

        $return_data['data'] = $host_domain_new;
        $return_data['status'] = 200;
        return HttpUtil::retrun_json($return_data,200);
    }

    /**
     * 定时添加数据 到 domains 表里面
     * trackers 二级域名
     */
    public function addDomainInfo()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        $before_day = date("Y-m-d",strtotime("-1 day"));

        $before_day_start_time = $before_day.' 00:00:00';

        $before_day_end_time = $before_day.' 23:59:59';
        $start_time = strtotime($before_day_start_time).'000'; // 前一天开始时间
      //  $end_time = strtotime($before_day_end_time).'000'; // 前一天 结束时间

        $adTrack = DB::table('addata_rank_role')
        ->join('addata','addata_rank_role.ad_id','=','addata.id')
        ->select('addata_rank_role.id','addata_rank_role.created_date','addata_rank_role.ad_id','addata.trackers','addata.tags')
        ->where('addata_rank_role.created_date','>=',$start_time)
        ->get();

        // 
        $tracker_tmp = [];
        $tracker_tmp_exist = [];
        foreach ($adTrack as $key => $value) {
            $tags_list = $value->tags;

            $tags_list_new = '';
            if(!empty($tags_list))
            {
                $tags_list_tmp = explode(';', $tags_list);

                $tags_list_tmp_num = count($tags_list_tmp);


                foreach ($tags_list_tmp as $k => $v) {

                    if(!empty($v) && !in_array($v, $tracker_tmp_exist) && stristr($v, '//') == FALSE)
                    {
                        $tracker_tmp[] = $v;
                        $tracker_tmp_exist[] = $v;
                    }
                }

            }
        }


        foreach ($tracker_tmp as $key => $value) {
            
            // $item['host'] = $value;
            // $item['md5'] = md5($value);


            if(stristr($value, 'dc_trk_cid'))continue;
            
            $domainTracker = Domains::where('host',$value)->first();

            // $domainTracker = DomainTracker::where('host',$value)->first();

            if(!$domainTracker)
            {
                $item['host'] = $value;
                $item['md5'] = md5($value);
                $item['role'] = 3;
                $item['flag'] = 1;
                DB::table('domains')->insert($item);
            }else{
                // $u_item['role'] = 3;
                // $u_item['flag'] = 1;
                // DB::table('domains')->where('host',$value)->update($u_item);
            }

            
        }
        dd('ok');

    }

    /**
     * 每晚定时更新 trackers_list tags 数据
     * 排名
     * @return [type] [description]
     */
    public function upTagsInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        $before_day = date("Y-m-d",strtotime("-1 day"));

        $before_day_start_time = $before_day.' 00:00:00';

        $before_day_end_time = $before_day.' 23:59:59';
        $start_time = strtotime($before_day_start_time).'000'; // 前一天开始时间
      //  $end_time = strtotime($before_day_end_time).'000'; // 前一天 结束时间

        $adTrack = DB::table('addata_rank_role')
        ->join('addata','addata_rank_role.ad_id','=','addata.id')
        ->select('addata_rank_role.id','addata_rank_role.created_date','addata_rank_role.ad_id','addata.trackers','addata.tags')
        ->where('addata_rank_role.created_date','>=',$start_time)
        ->get();

        // dd($adTrack);


        foreach ($adTrack as $key => $value) {

            $tag_list = $value->trackers;

            $tag_list_new = '';
            if(!empty($tag_list))
            {
                $tag_list_tmp = explode(';', $tag_list);

                $tag_list_tmp_num = count($tag_list_tmp);

                foreach ($tag_list_tmp as $k => $v) {

                    if($v)
                    {

                        if(stristr($v, '"'))
                        {
                            continue;
                        }
                        if($k==$tag_list_tmp_num-1)
                        {
                            $tag_list_new = $tag_list_new.'"'.$v.'"';
                        }else{
                            $tag_list_new = $tag_list_new.'"'.$v.'",';
                        }
                    }
                    
                }

            }


            $tags_list = $value->tags;

            $tags_list_new = '';
            if(!empty($tags_list))
            {
                $tags_list_tmp = explode(';', $tags_list);

                $tags_list_tmp_num = count($tags_list_tmp);

                foreach ($tags_list_tmp as $k => $v) {

                    if($v)
                    {

                        if(stristr($v, '"'))
                        {
                            continue;
                        }
                        if($k==$tags_list_tmp_num-1)
                        {
                            $tags_list_new = $tags_list_new.'"'.$v.'"';
                        }else{
                            $tags_list_new = $tags_list_new.'"'.$v.'",';
                        }
                    }
                }

            }

            if(empty($tag_list_new) && empty($tags_list_new))
            {

            }else{
                $data['trackers_list'] = $tag_list_new;
                $data['tags'] = $tags_list_new;
                DB::table('addata_rank_role')->where('id',$value->id)->update($data);
            }
        }

        dd('ok');


    }


    /**
     * 每晚定时更新 trackers_list tags 数据
     * 处理 addata tags 内容
     * 排名
     * @return [type] [description]
     */
    public function upAdTagsInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $id = empty($request->id)?0:$request->id;

        if(empty($id))dd('id is null');

        $limit = empty($request->limit)?10000:$request->limit;


        $before_day = date("Y-m-d",strtotime("-1 day"));

        $before_day_start_time = $before_day.' 00:00:00';

        $before_day_end_time = $before_day.' 23:59:59';
        $start_time = strtotime($before_day_start_time).'000'; // 前一天开始时间
      //  $end_time = strtotime($before_day_end_time).'000'; // 前一天 结束时间

        //11274176
        $adTrack = DB::table('addata')
        ->select('id','created_date','tags')
        // ->where('created_date','>=',$start_time)
        ->where('id','<=',$id)
        ->orderBy('id','desc')
        ->limit($limit)
        ->get();

        // dd($adTrack);


        foreach ($adTrack as $key => $value) {

            $tags_list = $value->tags;

            $tags_list_new = '';
            if(!empty($tags_list))
            {
                $tags_list_tmp = explode(';', $tags_list);

                $tags_list_tmp_num = count($tags_list_tmp);

                foreach ($tags_list_tmp as $k => $v) {

                    if($v)
                    {

                        if(stristr($v, '"'))
                        {
                            continue;
                        }
                        if($k==$tags_list_tmp_num-1)
                        {
                            $tags_list_new = $tags_list_new.'"'.$v.'"';
                        }else{
                            $tags_list_new = $tags_list_new.'"'.$v.'",';
                        }
                    }
                }

            }

            if(!empty($tags_list_new))
            {
                $data['tags'] = $tags_list_new;
                DB::table('addata')->where('id',$value->id)->update($data);
            }
        }

        dd('ok');


    }
      /**
     * 导出信息
     * max_id_nums  每天最大设置不得超过这个值
     * size 分页 条数
     * end_time 结束时间
     * range_type 1 一天 2 一周 3 一月 默认 一周
     * @return [type] [description]
     */
    public function getAdsInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $max_id_nums = empty($request->max_id_nums)?100:$request->max_id_nums;

        //dd($before_day);
        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;


        if($start_time>=$end_time)dd('开始时间不能大于结束时间');

        $range_type = empty($request->range_type)?1:$request->range_type;

        $pc_area = empty($request->pc_area)?750000:$request->pc_area;
        $app_area = empty($request->app_area)?(1280*720):$request->app_area;

        if(empty($start_time) || empty($end_time))dd('参数不对,请输入开始时间结束时间 精确到 千毫秒（1493568000000，1494172799000）');


        $size = empty($request->size)?100:$request->size;

        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'created_date'=>'desc'
            ],
            '_source'=>[
                'id','md5','advertiser','publisher','trackers','width','height','wharea','role',
                'date5','title','platform','size','type','shape','created_date','subject_md5'
            ],
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'created_date'=>[
                                    'gte'=>$start_time,//'1493568000000',//5-1
                                    'lte'=>$end_time//'1494172799000'//5-7
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        // $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        // 
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?scroll=2m';

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $return_id = [];
        $data = DBUtil::get_parse_es_data_id($rs);

        if(empty($data['data']))dd("暂无数据");



        foreach ($data['data'] as $key => $value) {
            $return_id[] = $value;
        }

        $scrollId = $data['scroll_id'];

        $j = 1;

        if($data['total']>$size)
        {
            if($data['total']%$size==0)
            {
                $j = $data['total']/$size;
            }else{
                $j = intval($data['total']/$size)+1;
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

        foreach ($return_id as $key => &$value) {

            $value = (array)$value;

            $curl_param_md5 = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'term'=>[
                                    'md5'=>$value['md5']
                                ]
                            ],
                            [
                                'range'=>[
                                    'created_date'=>[
                                        'gte'=>$start_time,//'1493568000000',//5-1
                                        'lte'=>$end_time//'1494172799000'//5-7
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'ids'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ],
                    'day_ids'=>[
                        'terms'=>[
                            'field'=>'date_day_long',
                            'size'=>0
                        ]
                    ]
                ]
            ];

            // dd(json_encode($curl_param_md5));

            $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param_md5,"POST");

            $data_json = json_decode($rs,true);

            // // 获取 最大 最小 时间
           $ids = empty($data_json['aggregations']['ids']['value'])?'':$data_json['aggregations']['ids']['value'];


            // if($ids)
            $max_ids = empty($data_json['aggregations']['day_ids']['buckets'])?'':$data_json['aggregations']['day_ids']['buckets'];

            $max_ids_days = 0;

            if($max_ids)
            {

                foreach ($max_ids as $mk => $mv) {
                    if($mv['doc_count']>$max_id_nums)
                    {
                        $max_ids_days = $max_ids_days + $max_id_nums;
                    }else{
                        $max_ids_days = $max_ids_days + $mv['doc_count'];
                    }
                }
            }

            $value['ids_items'] = $ids;
            $value['max_ids_items'] = $max_ids_days;

            // 第几屏
            $screen_ratio = 0;

            // 面积比
            $area_ratio = 0;

            if($value['platform']==2)
            {
                $screen_ratio = $value['size']/$app_area;

                $area_ratio = $value['wharea'] / $app_area;

            }else{
                $screen_ratio = $value['size']/$pc_area;
                $area_ratio = $value['wharea'] / $pc_area;
            }

            $value['screen_ratio'] = $screen_ratio;
            $value['area_ratio'] = $area_ratio;

            $type_socre = 0;

            switch ($value['type']) {
                case 'image':
                    $type_socre = 1;
                    break;
                case 'flv':
                    $type_socre = 5;
                    break;
                case 'swf':
                    $type_socre = 2;
                    break;
                case 'html5':
                    $type_socre = 3;
                    break;
            }
            $value['type'] = $type_socre;

            $value['score'] = $type_socre * $value['platform'] * $area_ratio * $max_ids_days;

            $value['score_desc'] = 'type * platform * area_ratio * max_ids_days';
            $value['ad_id'] = $value['id'];
        }

        // dd($return_id);

        // 统计各个 角色 的得分排名
        foreach ($return_id as $key => $value) {
            
            $md5 = md5($value['advertiser'].$start_time.$end_time);
            $adRRole = AddataRankRole::where('ad_id',$value['ad_id'])->first();
            if(!$adRRole)$adRRole = new AddataRankRole();

            $adRRole->ad_id = $value['ad_id'];
            $adRRole->type = $value['type'];
            $adRRole->score = $value['score'];
            $adRRole->score_desc = $value['score_desc'];
            $adRRole->score_desc_md5 = $md5;
            $adRRole->md5 = $value['md5'];
            $adRRole->shape = $value['shape'];
            $adRRole->platform = $value['platform'];
            $adRRole->advertiser = $value['advertiser'];
            $adRRole->publisher = $value['publisher'];
            $adRRole->size = $value['size'];
            $adRRole->trackers = $value['trackers'];
            $adRRole->width = $value['width'];
            $adRRole->height = $value['height'];
            $adRRole->wharea = $value['wharea'];
            $adRRole->role = $value['role'];
            $adRRole->ids_items = $value['ids_items'];
            $adRRole->max_ids_items = $value['max_ids_items'];
            $adRRole->screen_ratio = $value['screen_ratio'];
            $adRRole->area_ratio = $value['area_ratio'];
            $adRRole->title = $value['title'];
            $adRRole->subject_md5 = $value['subject_md5'];

            $adRRole->created_date = $value['created_date'];

            $adRRole->date5 = $value['date5'];
            $adRRole->range_type = $range_type;//一周
            $adRRole->save();


        }


        // $cellData = [
        //     ['id','title','width','height','wharea','area_ratio','size','screen_ratio','advertiser','publisher','trackers','ids_items','max_ids_items','md5','platform','role','date','type','shape','score']
        // ];

        // foreach ($return_id as $key => $value) {

        //     $item['id'] = $value['id'];
        //     $item['title'] = $value['title'];
        //     $item['width'] = $value['width'];
        //     $item['height'] = $value['height'];
        //     $item['wharea'] = $value['wharea'];
        //     $item['area_ratio'] = $value['area_ratio'];
        //     $item['size'] = $value['size'];
        //     $item['screen_ratio'] = $value['screen_ratio'];
        //     $item['advertiser'] = $value['advertiser'];

        //     $item['publisher'] = $value['publisher'];
        //     $item['trackers'] = $value['trackers'];
        //     $item['ids_items'] = $value['ids_items'];
        //     $item['max_ids_items'] = $value['max_ids_items'];
        //     $item['md5'] = $value['md5'];
        //     $item['platform'] = $value['platform'];
        //     $item['role'] = $value['role'];
        //     $item['date'] = $value['date5'];
        //     $item['type'] = $value['type'];
        //     $item['shape'] = $value['shape'];
        //     $item['score'] = $value['score'];
        //     array_push($cellData, $item);
        // }

        // Excel::create('Adbug ads 详细',function($excel) use ($cellData){
        //   $excel->sheet('score', function($sheet) use ($cellData){
        //     $sheet->rows($cellData);
        //   });
        // })->export('xls');




            // 分别计算  广告主 媒体 跟踪者 一周返回内数据

            // $domain = Domains::where('host',$value['advertiser'])->first();

            


            // if($domain && $domain->role==2)
            // {
            //     $rrlist_md5 = md5($start_time.$end_time.$range_type.$value['advertiser']);
            //     $this->addARRList($value,2,$value['advertiser'],$range_type,$rrlist_md5);
            // }

            // $domain = Domains::where('host',$value['publisher'])->first();

            // if($domain && $domain->role==1)
            // {
            //     $rrlist_md5 = md5($start_time.$end_time.$range_type.$value['publisher']);

            //     $this->addARRList($value,1,$value['publisher'],$range_type,$rrlist_md5);
            // }

            // $trackers = $value['trackers'];

            // if(!empty($trackers))
            // {
            //     $trackers_tmp = explode(';', $trackers);

            //     foreach ($trackers_tmp as $k => $v) {
            //         if($v)
            //         {
            //             $domain = Domains::where('host',$v)->first();

            //             if($domain && $domain->role==3)
            //             {

            //                 $rrlist_md5 = md5($start_time.$end_time.$range_type.$value['publisher']);

            //                 $adrrList = AdRRoleList::where(['role'=>3,'host'=>$v,'range_type'=>$range_type,'md5'=>$rrlist_md5])->first();

            //                 $score = $value['score'];
            //                 if($adrrList)
            //                 {
            //                     $score = $score + $adrrList->score;
            //                 }else{
            //                     $adrrList = new AdRRoleList();
            //                 }
            //                 $adrrList->score = $score;
            //                 $adrrList->role = 3;
            //                 $adrrList->host = $v;
            //                 $adrrList->md5 = $rrlist_md5;
            //                 $adrrList->created_date = $value['created_date'];
            //                 $adrrList->range_type = $range_type;
            //                 $adrrList->save();
            //             }
            //         }
            //     }

            // }

        dd('ok');
        dd($data_d['data']);

    }
    /**
     * 根据角色 获取对应的排名数据
     * role 1 媒体 2 广告主 3 媒体 4 创意 5 营销活动
     * time_type 1 前一天 2 前一周 3 前半月 4 前一月
     * @return [type] [description]
     */
    public function getRankingList(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $time_type = empty($request->time_type)?'2':$request->time_type;

        if(empty($role))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);


        $key_cache = md5(implode('', $request->all()));
       if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
       {
            return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
       }

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

        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
        // dd(json_encode($curl_param));
    }

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


    public function getBeforeRankList($role,$role_field,$start_time,$end_time)
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
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param_domain_app);
                    

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
     * 获取时间戳
     * @return [type] [description]
     */
    public function getTimeSto($time)
    {
        return [
            'start_time'=>strtotime($time.' 00:00:00').'000',
            'end_time'=>strtotime($time.' 23:59:59').'000',
        ];
    }
    /**
     * 根据 广告主 查询
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function rankListAdvertiser(Request $request)
    {
        set_time_limit(0);
        $start_time = empty($request->start_time)?'':$request->start_time;// 开始时间

        $end_time = empty($request->end_time)?'':$request->end_time; // 结束时间


        if(empty($start_time) || empty($end_time))dd('请选择一段时间段查询');


        $curl_param = [
            'size'=>0,
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        [
                            'range'=>[
                                'date_day_long'=>[
                                    'gte'=>$start_time,
                                    'lte'=>$end_time
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'advertiser'=>[
                    'terms'=>[
                        'field'=>'advertiser_na',
                        'size'=>200,
                        'order'=>[
                            'ids_dis'=>'desc'
                        ]
                    ],
                    'aggs'=>[
                        'ids_dis'=>[
                            'cardinality'=>[
                                'field'=>'md5'
                            ]
                        ],
                        'ids'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ],
                        'subjects_dis'=>[
                            'cardinality'=>[
                                'field'=>'subject_md5'
                            ]
                        ],
                        'trackers'=>[
                            'cardinality'=>[
                                'field'=>'trackers_na'
                            ]
                        ],
                        'publishers'=>[
                            'cardinality'=>[
                                'field'=>'domain_na'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_AGG_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        if(empty($rs_parse['aggregations']['advertiser']['buckets']))
        {
            dd('暂无数据');
        }

        $aggs = $rs_parse['aggregations']['advertiser']['buckets'];


        $cellData = [
            ['广告主','创意数(去重)','营销活动数','跟踪者','媒体']
        ];

        $data = [];
        foreach ($aggs as $key => $value) {

            if(empty($value['key']))continue;

            $item['advertise'] = $value['key'];
            $item['ids_dis'] = $value['ids_dis']['value'];
            $item['ads'] = $value['ids']['value'];
            $item['subjects_dis'] = $value['subjects_dis']['value'];

                $item['trackers'] = $value['trackers']['value'];
                $item['publishers'] = $value['publishers']['value'];
                array_push($cellData, $item);



            Excel::create('新增用户信息',function($excel) use ($cellData){
                    $excel->sheet('score', function($sheet) use ($cellData){
                        $sheet->rows($cellData);
                    });
            })->export('xls');         
        }
        dd('ok');

    }
    /**
     * 根据 角色 获取最近一段时间内的数据
     * 排行榜
     * api/v3/addata/ranking/list
     * @return [type] [description]
     */
    public function rankingList(Request $request)
    {
        set_time_limit(0);
        $start_time = empty($request->start_time)?'':$request->start_time;// 开始时间

        $end_time = empty($request->end_time)?'':$request->end_time; // 结束时间

        $role = empty($request->role)?'':$request->role;

        if(empty($start_time) || empty($end_time) || empty($role))dd('请选择一段时间段查询');
        $role_aggs = '';


        switch ($role) {
            case '1':
                $role_aggs = 'publisher';
                break;
            case '2':
                $role_aggs = 'advertiser';
                break;
            case '3':
                $role_aggs = 'tracker';
                break;
        }

        $curl_param = [
            'size'=>0,
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        [
                            'range'=>[
                                'date_hour_long'=>[
                                    'gte'=>$start_time,
                                    'lte'=>$end_time
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                $role_aggs=>[
                    'terms'=>[
                        'field'=>'domain_na',
                        'size'=>0
                    ],
                    'aggs'=>[
                       'ids'=>[
                            'cardinality'=>[
                                'field'=>'md5'
                            ]
                        ], 
                        'type'=>[
                            'terms'=>[
                                'field'=>'type',
                                'size'=>0
                            ],
                            'aggs'=>[
                                'id'=>[
                                    'cardinality'=>[
                                        'field'=>'md5'
                                    ]
                                ]
                            ]
                        ],
                        'platform'=>[
                            'terms'=>[
                                'field'=>'platform',
                                'size'=>0
                            ],
                            'aggs'=>[
                                'id'=>[
                                    'cardinality'=>[
                                        'field'=>'md5'
                                    ]
                                ]
                            ]
                        ],
                        'shape'=>[
                            'terms'=>[
                                'field'=>'shape',
                                'size'=>0
                            ],
                            'aggs'=>[
                                'id'=>[
                                    'cardinality'=>[
                                        'field'=>'md5'
                                    ]
                                ]
                            ]
                        ],
                        'subjects'=>[
                            'cardinality'=>[
                                'field'=>'subject_md5'
                            ]
                        ],
                        'advertiser'=>[
                            'cardinality'=>[
                                'field'=>'advertiser_na'
                            ]
                        ],
                        'trackers'=>[
                            'cardinality'=>[
                                'field'=>'trackers_na'
                            ]
                        ],
                        'publishers'=>[
                            'cardinality'=>[
                                'field'=>'domain_na'
                            ]
                        ]
                    ]
                ]
            ]
        ];


        switch ($role) {
            case '2':
                $curl_param['aggs'][$role_aggs]['terms']['field'] = 'advertiser_na';
                break;
            case '3':
                $curl_param['aggs'][$role_aggs]['terms']['field'] = 'tracker_list';
                break;
        }
        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_AGG_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);


        if(empty($rs_parse['aggregations'][$role_aggs]['buckets']))
        {
            dd('暂无数据');
        }

        $aggs = $rs_parse['aggregations'][$role_aggs]['buckets'];

        $data = [];
        foreach ($aggs as $key => $value) {

            if(empty($value['key']))continue;
            $role_md5 = md5($start_time.$end_time.$role.$role_aggs.$value['key']);
            $adRankList = AddataRankList::where(['role_md5'=>$role_md5])->first();
            if(!$adRankList)$adRankList = new AddataRankList();
            
            $adRankList->role = $role;
            $adRankList->role_md5 = $role_md5;
            $adRankList->$role_aggs = $value['key'];
            $adRankList->ads = $value['ids']['value'];
            $adRankList->subjects = $value['subjects']['value'];
            $adRankList->advertisers = $value['advertiser']['value'];
            $adRankList->trackers = $value['trackers']['value'];
            $adRankList->publishers = $value['publishers']['value'];
            $type = $value['type']['buckets'];

            foreach ($type as $k => $v) {
                $type_field = 'type_'.$v['key'];

                $adRankList-> $type_field = $v['id']['value'];
            }
            $platform = $value['platform']['buckets'];
            foreach ($platform as $k => $v) {
              
               $type_field = 'platform_'.$v['key'];
               $adRankList-> $type_field = $v['id']['value'];
            }
            $shape = $value['shape']['buckets'];
            foreach ($shape as $k => $v) {

                $type_field = 'shape_'.$v['key'];

                $adRankList-> $type_field = $v['id']['value'];
            }
            $adRankList->save();

        }
        dd('ok');
    }
    /**
     * 搜索 addata 获取 对应 的 角色 域名 
     * q_role 待查询 角色 1 媒体 2 广告主 2 跟踪者
     * @return [type] [description]
     */
    public function getDomainByAd(Request $request)
    {

        $cul_role = empty($request->cul_role)?'':$request->cul_role;

        $cul_role_v = empty($request->cul_role_v)?'':$request->cul_role_v;

        $q_role = empty($request->q_role)?'':$request->q_role;

        $size = empty($request->size)?0:$request->size;

        $param = empty($request->wd)?'':$request->wd;

        // redis 
        $key_cache = md5(implode('',$request->all()));

        if(empty($cul_role_v))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $term_field = '';

        $child = '';

        switch ($q_role) {
            case '1':
                # code...
                $term_field = 'publisher_na';
                $child = 'child01';
                break;
            case '2':
                # code...
                $term_field = 'advertiser_na';
                $child = 'child02';
                break;
            case '3':
                # code...
                $term_field = 'tracker_na';
                $child = 'child03';
                break;
        }


        // 如果是 subejct 
        if($cul_role=='subject')
        {
            $curl_param_sub = [
                'query'=>[
                    'term'=>[
                        'id'=>$cul_role_v
                    ]
                ]
            ];

            // dd(json_encode($curl_param_sub));
            $url_sub = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');
   
            // echo $url;
            $rs_sub = HttpUtil::send_curl_methods($url_sub,$curl_param_sub,"POST");

            $data_sub = DBUtil::get_parse_es_data($rs_sub);

            if(empty($data_sub['data']))return HttpUtil::retrun_json(HttpUtil::returnStatus(200),200);



            $cul_role = 'subject_md5';
            $cul_role_v = $data_sub['data'][0]->md5;

        }


        $curl_param = [
            'size'=>0,
            'query'=>[
                'term'=>[
                    $cul_role=>$cul_role_v
                ]
            ],
            'aggs'=>[
                $term_field=>[
                    'terms'=>[
                        'field'=>$term_field,
                        'size'=>$size
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
   
        // echo $url;
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        $q_data = empty($rs_parse['aggregations'][$term_field]['buckets'])?'':$rs_parse['aggregations'][$term_field]['buckets'];


        if(empty($q_data))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $doamin_arr = [];

        foreach ($q_data as $key => $value) {
            $key_v = $value['key'];
            if(strchr($key_v,';'))
            {
                $keys = explode(';', $key_v);

                foreach ($keys as $k => $v) {
                    # code...
                    if($v && !in_array($v, $doamin_arr))$doamin_arr[] = $v;
                }

            }else{
                $doamin_arr[] = $key_v;
            }
        }

        // dd($doamin_arr);


        $curl_param_host = [
            'size'=>count($doamin_arr),
            'query'=>[
              'terms'=>[
                'host_no'=>$doamin_arr
              ]
            ]
        ];

        // print_r(json_encode($curl_param_host));
        // 
        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');
   
        // echo $url;
        $rs_host = HttpUtil::send_curl_methods($url,$curl_param_host,"POST");

        $data_host = DBUtil::get_parse_es_data($rs_host);

      
        if(empty($data_host['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


        // dd($data_host['data']);


        foreach ($data_host['data'] as $key => $value) {

            if(stristr($value->host,$param)===FALSE && stristr($value->cname,$param)===FALSE)
            {
               unset($data_host['data'][$key]);
            }
        }
       
        if(empty($data_host['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


        $data_host['status'] = 200;
        return HttpUtil::retrun_json($data_host,200);
    }

    /**
     * 根据 type 获取对应的广告信息
     * @param  Request $request [description]
     * @return [type]  3 最新视频 2 flush 1 image 
     */
    public function getFieldByType(Request $request)
    {
        $field = empty($request->field)?'':$request->field;
        $param = empty($request->value)?'':$request->value;
        $size = empty($request->size)?10:$request->size;
        $size = $size>10000?10000:$size;
        if(empty($field) || empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'bool'=>[
                    'must_not'=>[],
                    'must'=>[]
                ]
            ]
        ];

        if($param==3)
        {
            array_push($curl_param['query']['bool']['must_not'], ['term'=>['type'=>'image']]);
            array_push($curl_param['query']['bool']['must_not'], ['term'=>['type'=>'html5']]);
        }
        // dd(json_encode($curl_param));
        $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
       
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 修改 
     * 2017-4-13
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAllField(Request $request)
    {
        $field = empty($request->field)?'':$request->field;
        $param = empty($request->value)?'':$request->value;
        $size = empty($request->size)?10:$request->size;
        $size = $size>10000?10000:$size;
        if(empty($field) || empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'date5'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'bool'=>[
                    'must_not'=>[
                        'term'=>[
                            $field=>$param
                        ]
                    ]
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        

        if(empty($data))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
       
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    
    /**
     * 获取 广告主 媒体 跟踪者 top 100
     * 广告主 根据 创意数 排名
     * 媒体 根据 创意一数 跟踪者
     * type  1 媒体  2 广告主  3 DSP 跟踪者
     * size 显示 数据 条数 默认 100
     * @return [type] [description]
     */
    public function getAdsDSPPub(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $min_id = 10877499;

        echo 'start time:',' ',date("Y-m-d H:i:s",time());

        echo '<br/>';

        $addata_num = Addata::where('id','>=',$min_id)->where('id','<=',11024877)->count();


        echo 'addata_num:',$addata_num;

        echo '<br/>';

        // dd($addata_num);
        // $addata_num = 132653;

        $id = $min_id;

        $limit = 5000;
        if($addata_num>10000)
        {
            $nums = $addata_num/$limit;

            if(!is_int($nums))$nums = intval($nums) + 1;
           
            for($i=0;$i<$nums;$i++)
            {
                if($i>0)$id = $id+$limit;

                $this->addAdScore($id,$limit);
            }
        }

        echo 'end time:',' ',date("Y-m-d H:i:s",time());

        dd('ok');
    }


    public function addAdScore($id,$limit)
    {
        echo 'addAdScore:',' ',$id,' - ',$limit,'<br/>';


        $addata = Addata::where('id','>=',$id)->limit($limit)->get();
                // $data = [];
        foreach ($addata as $key => &$value) {
            $screenRatio = 0;
                    // 面积
            $adArea = $value['width'] * $value['height'];
            $adAreaScore = 0;
                    // pc
                    // =IF(P2<=100000,”1”,IF(P2<=200000,"2",IF(P2<=300000,"3",IF(P2<=400000,"4",IF(P2<=500000,"5",IF(P2<=600000,"6",IF(P2<=700000,"7",IF(P2<=800000,"8",IF(P2<=900000,"9",IF(P2>=900000,"10"))))))))))
            if($value['platform']==1)
            {
                if($adArea<=100000)
                {
                    $adAreaScore = 1;
                }else if($adArea<=200000)
                {
                    $adAreaScore = 2;
                }else if($adArea<=300000)
                {
                   $adAreaScore = 3;
                }else if($adArea<=400000)
                {
                    $adAreaScore = 4;
               }else if($adArea<=500000)
                {
                    //ddcdc
                        $adAreaScore = 5;
                }else if($adArea<=600000)
                {
                    $adAreaScore = 6;
                }else if($adArea<=700000)
                {
                    $adAreaScore = 7;
                }else if($adArea<=800000)
                {
                    $adAreaScore = 8;
                }else if($adArea<=900000)
                {
                    $adAreaScore = 9;
                }else
                {
                    $adAreaScore = 10;
                }
            }else{
                //=IF(P3982<=25000,”1",IF(P3982<=50000,"2",IF(P3982<=75000,"3",IF(P3982<=100000,"4",IF(P3982<=125000,"5",IF(P3982<=150000,"6",IF(P3982<=175000,"7",IF(P3982<=200000,"8",IF(P3982<=225000,"9",IF(P3982>=225000,"10"))))))))))
                if($adArea<=25000)
                {
                    $adAreaScore = 1;
                }else if($adArea<=50000)
                {
                    $adAreaScore = 2;
                }else if($adArea<=75000)
                {
                    $adAreaScore = 3;
                }else if($adArea<=100000)
                {
                    $adAreaScore = 4;
                }else if($adArea<=125000)
                {
                    $adAreaScore = 5;
                }else if($adArea<=150000)
                {
                    $adAreaScore = 6;
                }else if($adArea<=175000)
                {
                    $adAreaScore = 7;
                }else if($adArea<=200000)
                {
                    $adAreaScore = 8;
                }else if($adArea<=225000)
                {
                    $adAreaScore = 9;
                }else
                {
                    $adAreaScore = 10;
                }
            }

            // 计算屏占比
            if($value['attribute07'])
            {
                $attr7 = json_decode($value['attribute07'],true);


                if(!empty($attr7['page_meta']) && !empty($attr7['page_meta']['screenH']))
                {
                    // dd($attr7['page_meta']['screenH']);
                    $screenArea = $attr7['page_meta']['screenH'] * $attr7['page_meta']['screenW'];

                    $screenRatio = $adArea / $screenArea;
                }
            }

            $screenRatioScore = 0;

            if($screenRatio<=1)
            {
                $screenRatioScore = 10;
            }else if($screenRatio<=2)
            {
                $screenRatioScore = 8;
            }else if($screenRatio<=3)
            {
                $screenRatioScore = 6;
            }else if($screenRatio<=4)
            {
                $screenRatioScore = 4;
            }else if($screenRatio<=5)
            {
                $screenRatioScore = 2;
            }

            $typeScore = 0;
            switch ($value['type']) {
                case 'flv':
                    # code...
                    $typeScore = 10;
                    break;
                case 'swf':
                    # code...
                    $typeScore = 9;
                    break;
                case 'html5':
                    # code...
                    $typeScore = 9;
                    break;
                case 'image':
                    # code...
                    $typeScore = 8;
                    break;
                default:
                    $typeScore = 0;
                    break;
            }
            $sizeScore = 0;

            $size = $value['size']/1024;

            //=IF(J3<=25,”1”,IF(J3<=50,"2",IF(J3<=75,"3",IF(J3<=100,"4",IF(J3<=125,"5",IF(J3<=150,"6",IF(J3<=200,"7",IF(J3<=250,"8",IF(J3<=300,"9",IF(J3>300,"10"))))))))))
            if($size<=25)
            {
                $sizeScore = 1;
            }else if($size<=50)
            {
                $sizeScore = 2;
            }else if($size<=75)
            {
                $sizeScore = 3;
            }else if($size<=100)
            {
                $sizeScore = 4;
            }else if($size<=125)
            {
                $sizeScore = 5;
            }else if($size<=150)
            {
                $sizeScore = 6;
            }else if($size<=200)
            {
                $sizeScore = 7;
            }else if($size<=250)
            {
                $sizeScore = 8;
            }else if($size<=300)
            {
                $sizeScore = 9;
            }else{
                $sizeScore = 10;
            }
            //25% screen + 25% type + 25% 占屏比 + 25% size 
            $allScore = 0.25 * ($screenRatioScore+$typeScore+$adAreaScore+$sizeScore);

            // $value['allScore'] = $allScore;
            $addata_score = AddataScore::where('ad_id',$value['id'])->first();

            if(!$addata_score) $addata_score = new AddataScore();

            $addata_score['ad_id'] = $value['id'];
            $addata_score['score'] = $allScore;
            $addata_score['created_at'] = date('Y-m-d H:i:s',$value['created_date']/1000);
            $addata_score->save();
        }
    }
    /**
     * 获取 散点关系图
     * role  角色 1 媒体 2 广告主 3 追踪者  4 营销活动
     * wd  域名  （广告主 媒体 追踪者）
     * 查看 http://echarts.baidu.com
     * finn
     *  2017-3-24
     * @return [type] [description]
     */
    public function findEchartData(Request $request)
    {
        $host = empty($request->wd)?'':$request->wd;

        $role = empty($request->role)?'':$request->role;


        if(empty($host) || empty($role))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);


        // $key_cache = md5(implode('',$request->all()));
        // if(DBUtil::isHasRedisData($key_cache))HttpUtil::retrun_json(['data'=>json_decode(Redis::get($key_cache)),'status'=>200],200);

        $termfiled = 'advertiser';

        switch ($role) {
            case '1':
                $termfiled = 'publisher';
                break;
            case '2':
                # code...
                $termfiled = 'advertiser';
                break;
            case '3':
                $termfiled = 'tracker';
                break;
            case '4':
                # code...
                $termfiled = 'subject_md5';
                break;
        }
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            $termfiled=>$host
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'title_na'=>""
                        ]
                    ]
                ]
            ]
        ];

        $curl_param = $this->echartRoleAggs($curl_param,$role,30);


        if($role==4)$curl_param['size'] = 1;

        // dd(json_encode($curl_param));

        $rs_re = HttpUtil::parse_curl_search(config('param.ADDATA_CHART_SEARCH'),$curl_param);
    
        $rs_parse = json_decode($rs_re,true);

        $subjects_tmp = [];
        if($role!=4)
        {
            if(empty($rs_parse['aggregations']['subjects']['buckets']))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
            $rs_data = $rs_parse['aggregations']['subjects']['buckets'];
        
            // 媒体
            if($role==1)
            {
                
                foreach ($rs_data as $key => $value) {

                    $item['subject'] = $value['key'];

                    if(!empty($value['ads']['buckets']))
                    {
                        $ads = $value['ads']['buckets'];

                        foreach ($ads as $ak => $av) {
                            $item_ads['ads'] = $av['key'];
                            $item_ads['tracker'] = [];
                            $item_ads['advertiser'] = [];

                            # code...
                            if(!empty($av['trackers']['buckets']))
                            {

                                foreach ($av['trackers']['buckets'] as $tk => $tv) {
                                    $item_ads['tracker'][] = $tv;
                                }
                            }

                            if(!empty($av['advertiser']['buckets']))
                            {

                                foreach ($av['advertiser']['buckets'] as $pk => $pv) {
                                    $item_ads['advertiser'][] = $pv;
                                }
                            }

                            $item['ads'] = $item_ads;

                            unset($item_ads);
                        }
                    }
                    $subjects_tmp[] = $item;
                    unset($item);

                }

            }

            if($role==2)
            {
                
                foreach ($rs_data as $key => $value) {


                    $item['subject'] = $value['key'];

                    if(!empty($value['ads']['buckets']))
                    {
                        $ads = $value['ads']['buckets'];

                        foreach ($ads as $ak => $av) {
                            $item_ads['ads'] = $av['key'];
                            $item_ads['tracker'] = [];
                            $item_ads['publisher'] = [];

                            # code...
                            if(!empty($av['trackers']['buckets']))
                            {

                                foreach ($av['trackers']['buckets'] as $tk => $tv) {
                                    $item_ads['tracker'][] = $tv;
                                }
                            }

                            if(!empty($av['publisher']['buckets']))
                            {

                                foreach ($av['publisher']['buckets'] as $pk => $pv) {
                                    $item_ads['publisher'][] = $pv;
                                }
                            }

                            $item['ads'] = $item_ads;

                            unset($item_ads);
                        }
                    }
                    $subjects_tmp[] = $item;
                    unset($item);
                }
            }

            if($role==3)
            {
                
                foreach ($rs_data as $key => $value) {


                    $item['subject'] = $value['key'];

                    if(!empty($value['ads']['buckets']))
                    {
                        $ads = $value['ads']['buckets'];

                        foreach ($ads as $ak => $av) {
                            $item_ads['ads'] = $av['key'];
                            $item_ads['advertiser'] = [];
                            $item_ads['publisher'] = [];

                            # code...
                            if(!empty($av['advertiser']['buckets']))
                            {

                                foreach ($av['advertiser']['buckets'] as $tk => $tv) {
                                    $item_ads['advertiser'][] = $tv;
                                }
                            }

                            if(!empty($av['publisher']['buckets']))
                            {

                                foreach ($av['publisher']['buckets'] as $pk => $pv) {
                                    $item_ads['publisher'][] = $pv;
                                }
                            }

                            $item['ads'] = $item_ads;

                            unset($item_ads);
                        }
                    }
                    $subjects_tmp[] = $item;
                    unset($item);

                }
            }
        }else{

            if(empty($rs_parse['aggregations']['ads']['buckets']))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

            $rs_parse_data_id = $rs_parse['hits']['hits'][0]['_source']['id'];

            $rs_parse_data_title = $rs_parse['hits']['hits'][0]['_source']['title_na'];

            $rs_data = $rs_parse['aggregations']['ads']['buckets'];

            foreach ($rs_data as $key => $av) {

                $item_ads['ads'] = $av['key'];
                $item_ads['tracker'] = [];
                $item_ads['advertiser'] = [];

                $item_ads['subject_id'] = $rs_parse_data_id;
                $item_ads['title'] = $rs_parse_data_title;

                            # code...
                if(!empty($av['trackers']['buckets']))
                {

                    foreach ($av['trackers']['buckets'] as $tk => $tv) {
                        $item_ads['tracker'][] = $tv;
                    }
                }
                if(!empty($av['advertiser']['buckets']))
                {

                    foreach ($av['advertiser']['buckets'] as $tk => $tv) {
                        $item_ads['advertiser'][] = $tv;
                    }
                }

                if(!empty($av['publisher']['buckets']))
                {

                    foreach ($av['publisher']['buckets'] as $tk => $tv) {
                        $item_ads['publisher'][] = $tv;
                    }
                }

                $subjects_tmp['ads'][] = $item_ads;
                unset($item);
            }
           
        }
        // 获取 对应创意名称 title_na
        if($role!=4)
        {
            $ads_data  = $this->getAdsTitle($subjects_tmp,0);

            foreach ($subjects_tmp as $key => &$value) {
                if(in_array($value['ads']['ads'], $ads_data))
                {
                    $value['ads']['title'] = array_search($value['ads']['ads'], $ads_data);
                }
            }

            $subject_data = $this->getSubTitle($subjects_tmp);

            foreach ($subjects_tmp as $key => &$value) {
                if(in_array($value['subject'], $subject_data))
                {
                    $subject_title_id = array_search($value['subject'], $subject_data);

                    $subject_title_id_arr = explode('_TITLE_SUBJECT_ID_', $subject_title_id);
                    $value['title'] = $subject_title_id_arr[0];

                    $value['subject_id'] = $subject_title_id_arr[1];
                }
            }




        }else{
            $ads_data  = $this->getAdsTitle($subjects_tmp,1);
            // $ads_key = array_keys($ads_data);

            // foreach ($subjects_tmp['ads'] as $key => &$value) {

            //     if(in_array($value['ads'], $ads_key))
            //     {
            //         $value['title'] = $ads_data[$value['ads']];
            //     }
            // }
        }


        //DBUtil::setRedisData($key_cache,$subjects_tmp,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json(['data'=>$subjects_tmp,'status'=>200],200);
    }
    /**
     * 获取 对应的subject 名称
     * @return [type] [description]
     */
    public function getSubTitle($subjects_tmp)
    {
        $subject_title = [];

        foreach ($subjects_tmp as $key => $value) {
            $subject_title[] = $value['subject'];
        }


        $curl_param = [
            'size'=>count($subject_title),
            'query'=>[
                'terms'=>[
                    'md5'=>$subject_title
                ]
            ]
        ];
        $rs_re_ads = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH'),$curl_param);
    
        $rs_re_ads_parse = json_decode($rs_re_ads,true);


        $hits = empty($rs_re_ads_parse['hits']['hits'])?'':$rs_re_ads_parse['hits']['hits'];

        $data = [];

        foreach ($hits as $key => $value) {
            $data[$value['_source']['title'].'_TITLE_SUBJECT_ID_'.$value['_source']['id']] = $value['_source']['md5'];
        }
        return $data;
    }

    public function getAdsTitle($subjects_tmp,$type)
    {

        $ads_tmp = [];

        if(empty($type))
        {
            foreach ($subjects_tmp as $key => $value) {
                $ads_tmp[] = $value['ads']['ads'];
            }
        }else{
            foreach ($subjects_tmp['ads'] as $key => $value) {
                $ads_tmp[] = $value['ads'];
            }
        }


        $curl_ads_param = [
            'size'=>count($ads_tmp),
            'query'=>[
                'terms'=>[
                    'id'=>$ads_tmp
                ]
            ]
        ];

        $rs_re_ads = HttpUtil::parse_curl_search(config('param.ADDATA_CHART_SEARCH'),$curl_ads_param);
    
        $rs_re_ads_parse = json_decode($rs_re_ads,true);


        $hits = empty($rs_re_ads_parse['hits']['hits'])?'':$rs_re_ads_parse['hits']['hits'];

        $data = [];

        if($type)
        {
            foreach ($hits as $key => $value) {
                $data[$value['_source']['id']] = $value['_source']['title_na'];
            }
        }else{
            foreach ($hits as $key => $value) {
                $data[$value['_source']['title_na']] = $value['_source']['id'];
            }
        }
        
        return $data;
    }
    /**
     * finn 2017-3-24
     * @param  [type] $curl_param [数组]
     * @param  [type] $role       [角色]
     * @param  [type] $size       [显示条数]
     */
    public function echartRoleAggs($curl_param,$role,$size)
    {

        $aggs = '';
        switch ($role) {
            case '1':
                $aggs = [
                    'subjects'=>[
                        'terms'=>[
                            'field'=>'subject_md5',
                            'size'=>$size
                        ],
                        'aggs'=>[
                            'ads'=>[
                                'terms'=>[
                                    'field'=>'id',
                                    'size'=>$size
                                ],
                                'aggs'=>[
                                    'advertiser'=>[
                                        'terms'=>[
                                            'field'=>'advertiser',
                                            'size'=>$size
                                        ]
                                    ],
                                    'trackers'=>[
                                        'terms'=>[
                                            'field'=>'tracker',
                                            'size'=>$size
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;
            case '2':
                $aggs = [
                    'subjects'=>[
                        'terms'=>[
                            'field'=>'subject_md5',
                            'size'=>$size
                        ],
                        'aggs'=>[
                            'ads'=>[
                                'terms'=>[
                                    'field'=>'id',
                                    'size'=>$size
                                ],
                                'aggs'=>[
                                    'publisher'=>[
                                        'terms'=>[
                                            'field'=>'publisher',
                                            'size'=>$size
                                        ]
                                    ],
                                    'trackers'=>[
                                        'terms'=>[
                                            'field'=>'tracker',
                                            'size'=>$size
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                break;
            case '3':
                # code...
                $aggs = [
                    'subjects'=>[
                        'terms'=>[
                            'field'=>'subject_md5',
                            'size'=>$size
                        ],
                        'aggs'=>[
                            'ads'=>[
                                'terms'=>[
                                    'field'=>'id',
                                    'size'=>$size
                                ],
                                'aggs'=>[
                                    'advertiser'=>[
                                        'terms'=>[
                                            'field'=>'advertiser',
                                            'size'=>$size
                                        ]
                                    ],
                                    'publisher'=>[
                                        'terms'=>[
                                            'field'=>'publisher',
                                            'size'=>$size
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;
            case '4':
                # code...
                $aggs = [
                    'ads'=>[
                        'terms'=>[
                            'field'=>'id',
                            'size'=>$size
                        ],
                        'aggs'=>[
                            'advertiser'=>[
                                'terms'=>[
                                    'field'=>'advertiser',
                                    'size'=>$size
                                ]
                            ],
                            'publisher'=>[
                                'terms'=>[
                                    'field'=>'publisher',
                                    'size'=>$size
                                ]
                            ],
                            'trackers'=>[
                                'terms'=>[
                                    'field'=>'tracker',
                                    'size'=>$size
                                ]
                            ]
                        ]
                    ]
                ];
                break;
        }

        $curl_param['aggs'] = $aggs;

        return $curl_param;
    }

    /**
     * 统计 前一个月 数据
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function trandPubAds40(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        // 第一步 获取域名信息
        $app_cn_host = [
            "腾讯新闻"=>"com.tencent.news",
            "腾讯视频"=>"com.tencent.qqlive",
            "今日头条"=>"com.ss.android.article.news",
            "爱奇艺视频"=>"com.qiyi.video",
            "天天快报"=>"com.tencent.reading",
            "优酷视频"=>"com.youku.phone",
            "网易新闻"=>"com.netease.newsreader.activity",
            "新浪新闻"=>"com.sina.news",
            "快手"=>"com.smile.gifmaker",
            "搜狐新闻"=>"com.sohu.newsclient",
            "乐视视频"=>"com.letv.android.client",
            "一点资讯"=>"com.hipu.yidian",
            "Flipboard 中国版"=>"flipboard.cn",
            "搜狐视频"=>"com.sohu.sohuvideo",
            "凤凰新闻"=>"com.ifeng.news2",
            "暴风影音"=>"com.storm.smart",
            "土豆视频"=>"com.tudou.android",
            "百度视频"=>"com.baidu.video",
            "百度新闻"=>"com.baidu.news",
            "央视影音"=>"cn.cntv",
            "ZAKER"=>"com.myzaker.zaker",
            "畅读"=>"viva.reader",
            "爱奇艺PPS影音"=>"tv.pps.mobile",
            "新浪财经"=>"cn.com.sina.finance",
            "哔哩哔哩动画"=>"tv.danmaku.bili",
            "东方财富网"=>"com.eastmoney.android.berlin",
            "美拍"=>"com.meitu.meipaimv",
            "YY"=>"com.duowan.mobile",
            "秒拍"=>"com.yixia.videoeditor",
            "风行视频"=>"com.funshion.video.mobile",
            "澎湃新闻"=>"com.wondertek.paper",
            "球探体育比分"=>"com.bet007.mobile.score",
            "人民日报"=>"com.peopledailychina.activity",
            "芒果TV"=>"com.hunantv.imgo.activity",
            "斗鱼"=>"air.tv.douyu.android",
            "影视大全"=>"com.cinema2345",
            "虎牙直播"=>"com.duowan.kiwi",
            "军事头条"=>"com.tiexue.mobile.topnews",
            "新浪体育"=>'cn.com.sina.sports',
            "虎扑体育"=>'com.hupu.games',
            "冲浪快讯"=>'com.cplatform.surfdesktop',
            "UC头条"=>'com.uc.infoflow',
            '腾讯体育'=>'com.tencent.qqsports',
            '小米视频'=>'com.miui.video'
        ];
        // $is_app_link = [
        //     '芒果TV'=>'芒果tv',
        //     '斗鱼'=>'斗鱼TV',//air.tv.douyu.android 斗鱼TV
        //     '影视大全'=>'影视大全(com.le123)'//com.cinema2345 影视大全(com.le123)
        // ];

        // $is_app_link_domain = [
        //     'com.hunantv.imgo.activity',
        //     'air.tv.douyu.android',//air.tv.douyu.android 斗鱼TV
        //     'com.cinema2345'//com.cinema2345 影视大全(com.le123)
        // ];

        $is_not_app = [
            '浙江新闻','懂球帝','韩剧TV','咪咕视频','Whats New','直播吧','和讯外汇','视吧',
            '花椒直播','唔哩','国务院','头条视频','百度好看',
            '聚力视频','映客直播','乐视体育',"国务院"
        ];

        // 组合 label 数据
        // 获取一个月前的时间
        $begin_time = date("Y-m-d", strtotime('-30 day'));


        // echo $begin_time;
        // $last_month = date('Y-m',strtotime('-1 month'));

        $last_month = date('Y-m-d',strtotime('-30 day'));

       // $end_time = date("Y-m-d", strtotime(-date('d').'day'));

        $end_time = date('Y-m-d',time());

        // 统计两端时间内 有多少天
        $second1 = strtotime($begin_time);
        $second2 = strtotime($end_time);
        $day_num = ($second2 - $second1)/86400;
        $label = [];

        for($i=$day_num;$i>=0;$i--)
        {
            $label[] = date("Y-m-d", strtotime('-'.$i.' day'));;
        }


        $app_cn_host_data = $this->getPubTop40Data($app_cn_host,$begin_time,$end_time);

        $new_doc = [];

        foreach ($app_cn_host_data as $key => &$value) {
            $value['label'] = $label;

            $key_val = array_search($value['publisher'],$app_cn_host);
            $value['publisher'] = $value['publisher'].' - '.$key_val;

            $time_range = empty($value['time'])?'':$value['time'];


            if($time_range)
            {
                $doc_range = $value['doc'];
                foreach ($label as $lk => $lv) {
                if(array_search($lv, $time_range))
                   {
                        $key_time_doc = array_search($lv, $time_range);

                        $new_doc[] = $doc_range[$key_time_doc];
                   }else{
                        $new_doc[] = 0;
                   }
                }
                $value['doc'] = $new_doc;
            }
            unset($new_doc);
        }

        // dd($app_cn_host_data);

    

        $data['data'] = $app_cn_host_data;
        $data['nodata'] = $is_not_app;

        return HttpUtil::retrun_json(['data'=>$data,'status'=>'200'],200);

    }


    public function getPubTop40Data($array,$begin_time,$end_time)
    {
        $item_data = [];

       
        foreach ($array as $key => $value) {
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'date_day'=>[
                                        'gte'=>$begin_time,
                                        'lte'=>$end_time
                                    ]
                                ]
                            ],
                            [
                                'term'=>[
                                    'domain_na'=>$value
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'md5'
                        ]
                    ],
                    'date_num'=>[
                        'terms'=>[
                            'field'=>'date_day',
                            'size'=>0,
                            'order'=>[
                                'date_day'=>'asc'
                            ]
                        ],
                        'aggs'=>[
                            'date_day'=>[
                                'max'=>[
                                    'field'=>'date_day'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $item['publisher'] = $value;
            $item['ads'] = 0;
            $item['data'] = 0;


            // dd(json_encode($curl_param));

            $rs_re = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param);
    
            $rs_parse = json_decode($rs_re,true);

            if(!empty($rs_parse['aggregations']['ads']['value']))
            {
                $item['ads'] = $rs_parse['aggregations']['ads']['value'];
                $item['data'] = $rs_parse['aggregations']['date_num']['buckets'];

            }
            $item_data[] = $item;
        }

        $item_key_doc = [];
        $item_key_time = [];
        foreach ($item_data as $key => &$value) {

            if(!empty($value['data']))
            {
                foreach ($value['data'] as $k => &$v) {
                    unset($v['key']);
                    

                    $item_key_time[] = $v['key_as_string'];
                    unset($v['key_as_string']);
                    unset($v['date_day']);
                    $item_key_doc[] = $v['doc_count'];
                }

                $value['doc'] = $item_key_doc;
                $value['time'] = $item_key_time;
                unset($item_key_doc);

                unset($item_key_time);
            }
        }
        return $item_data;
    } 
    /**
     * 创建 文件 目录
     */
    public function createDir($dir)
    {
        return is_dir($dir) or ($this->createDir(dirname($dir)) and mkdir($dir, 0777));
    }


    /**
     * 获取 前 300 媒体 投放趋势 
     * index addata_aggs
     *  
     * @return [type] [description]
     */
    public function trandPubTop(Request $request)
    {
        set_time_limit(0);
        
        $last_month = date('Y-m-d',strtotime("-2 month"));
        $now_month = date('Y-m-d',time());
        $day = (time()-strtotime("-2 month")) / 86400;

        $label = [];

        $data_num_doc = [];

        for($i=0;$i<$day;$i++)
        {
            $label[] = date("Y-m-d",strtotime("-".($i+1)." day"));
            $data_num_doc[] = 0;
        }
        sort($label);

        // 第一步 获取 移动端 数据
        $appData = $this->getPublisherTopData("mobile",$label,$data_num_doc);

        // 第二步 获取 pc 端数据
        $pcData = $this->getPublisherTopData("",$label,$data_num_doc);


        $data['pc'] = $pcData;
        $data['app'] = $appData;


        return HttpUtil::retrun_json(['data'=>$data,'status'=>'200'],200);

    }

    /**
     * platform 1 pc 端数据 2 移动端数据 
     * @param  [type] $platform [description]
     * @return [type]           [description]
     */
    public function getPublisherTopData($platform,$label,$data_num_doc)
    {
        $curl_param = [
            'size'=>0,
            'query'=>[
                'term'=>[
                    'attribute06'=>$platform
                ]
            ],
            'aggs'=>[
                'daterange'=>[
                    'filter'=>[
                        'range'=>[
                            'date'=>[
                                'from'=>'now-2M'
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'publisher'=>[
                            'terms'=>[
                                'field'=>'domain_na',
                                'size'=>100,
                                'order'=>[
                                    'ads'=>'desc'
                                ]
                            ],
                            'aggs'=>[
                                'ads'=>[
                                    'cardinality'=>[
                                        'field'=>'md5'
                                    ]
                                ],
                                'date_num'=>[
                                    'terms'=>[
                                        'field'=>'date_day',
                                        'size'=>0,
                                        'order'=>[
                                            'date_day'=>'asc'
                                        ]
                                    ],
                                    'aggs'=>[
                                        'date_day'=>[
                                            'max'=>[
                                                'field'=>'date_day'
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


        // dd(json_encode($curl_param));


        $rs_re = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param);
    
        $rs_parse = json_decode($rs_re,true);

        if(empty($rs_parse['aggregations']['daterange']['publisher']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);



        $aggs = $rs_parse['aggregations']['daterange']['publisher'];


        if(empty($aggs['buckets']))return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);


        $buckets = $aggs['buckets'];
        $data = [];

        $data_num = [];
        foreach ($buckets as $key => $value) {

            if($value['key'])
            {
                $data_num[$value['key']] = '';

                if(!empty($value['date_num']['buckets']))
                {

                    $data_num[$value['key']] = $value['date_num']['buckets'];

                }
            }

        }

        $data_num_domain = [];

        $key_num_doc = [];


        foreach ($data_num as $key => $value) {

            foreach ($value as $k => $value2) {
                $date = $value2['key_as_string'];
                $doc = $value2['doc_count'];
                if(in_array($date, $label))
                {
                    $lable_key = array_search($date, $label);
                    $data_num_doc[$lable_key] = $doc;
                }

            }
            $key_num_doc['key'] = $key;
            $key_num_doc['label'] = $label;
            $key_num_doc['data_num_doc'] = $data_num_doc;
            $data_num_domain[] = $key_num_doc;

            foreach ($data_num_doc as $nv => &$nv) {
                # code...
                $nv = 0;
            }
        }

        return $data_num_domain;
    }
    
    
    public function pinpaiRand($num=0,$pinpai,$result=[])
    {
        $num ++;
        global $result;
        if($num<8)
        {
            $pin_key = rand(1,count($pinpai)-1);
            if(!in_array($pinpai[$pin_key], $result))
            {
                $result[] = $pinpai[$pin_key];
            }
            $this->pinpaiRand($num,$pinpai,$result);
        }
        return $result;
    }
    public function test($pinpai,$result=array()){
      global $result;
      if (count($result)<10) {
        $result[]=$pinpai[0];
        $this->test(count($result),$result);
      }
      return $result;
    }
    public function GetRandStr($len) 
    { 
        $file = file_get_contents('allB5.json');
        $chars = json_decode($file,true);
        $charsLen = count($chars) - 1;
        $output = []; 
        for ($i=0; $i<$len; $i++) 
        { 
            $output[] = $chars[mt_rand(0, $charsLen)]; 
        }
        return $output;  
    } 
    /**
     * 探索发现
     * 品牌 广告
     * 根据 widtH * height  desc 排名
     * 随机 根据 品牌库 进行 排名
     */
    public function getAdsBySize(Request $request)
    {
        // 随机 获取 广告品牌 7条数据
        $size = empty($request->size)?30:$request->size;
        $arr = $this->GetRandStr($size);
        $new_data = [];
        foreach ($arr as $key => $value) {
            $curl_param = [
                'size'=>1,
                 'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'multi_match'=>[
                                    'query'=>$value['name'],
                                    'fields'=>[
                                        'title',
                                        'advertiser_name_title',
                                        'advertiser_full'
                                    ]
                                ]
                            ],
                            [
                                'range'=>[
                                    'wharea'=>[
                                        'lte'=>'3140000',
                                        'gte'=>'200000'
                                    ]
                                ]
                            ],
                            [
                                'range'=>[
                                    'whdivided'=>[
                                        'gte'=>0.3,
                                        'lte'=>1.8
                                    ]
                                ]
                            ]
                        ],
                        'should'=>[
                            [
                                'range'=>[
                                    'width'=>[
                                        'gte'=>400,
                                        'lte'=>2048
                                    ]
                                ]
                            ],
                            [
                              'range'=>[
                                'height'=>[
                                    'gte'=>400,
                                    'lte'=>2000
                                ]
                              ]  
                            ]
                        ]
                    ]
                 ],
                'sort'=>[
                    [
                        '_score'=>[
                            'order'=>'desc'
                        ]
                    ]
                ]
            ];
            $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
            $data = DBUtil::get_parse_es_data($rs);
            if(!empty($data['data']))
            {
                foreach ($data['data'] as $k => $v) {
                    $new_data[] = (array)$v;
                }
            }
        }
        $new_data = array_filter($new_data);
        $new_data2 = [];
        $new_tmp = [];
        foreach ($new_data as $key => $value) {
            if(!in_array($value['title'], $new_tmp))
            {
                $new_tmp[] = $value['title'];
                $new_data2[] = $value;
            }
        }
        $data['data'] = $new_data2;
        $data = $this->getadvertiserDomain($data);
        // dd($new_data2);
        return json_encode(['data'=>$data['data']]);
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
     * 根据 收缩词 获取数据
     * @return [type] [description]
     */
    public function getSearchRs(Request $request)
    {
        $wd = empty($request->wd)?'':$request->wd;
        $width = empty($request->width)?'':$request->width;
        $height = empty($request->height)?'':$request->height;
        if(empty($wd) || empty($width) || empty($height))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $curl_param = [
            'size'=>2,
            'query'=>[
                'bool'=>[
                    'should'=>[
                        [
                            'exists'=>[
                                'field'=>'cname'
                            ]
                        ],
                        [
                            'exists'=>[
                                'field'=>'ename'
                            ]
                        ],
                        [
                            'match'=>[
                                'host'=>[
                                    'query'=>$wd
                                ]
                            ]
                        ],
                        [
                            'match'=>[
                                'ename'=>[
                                    'query'=>$wd
                                ]
                            ]
                        ],
                        [
                            'match'=>[
                                'cname'=>[
                                    'query'=>$wd
                                ]
                            ]
                        ]
                    ],
                    'must'=>[
                        [
                            'term'=>[
                                'role'=>2
                            ]
                        ]
                    ]
                ]
            ],
            'sort'=>[
                [
                    '_score'=>[
                        'order'=>'desc'
                    ]
                ],
                [
                    'brand_ads'=>[
                        'order'=>'desc'
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data3($rs);
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
        // 获取 所有 subject
        $sub_data_tmp = [];
        foreach ($data['data'] as $key => $value) {
            $curl_param = [
                'size'=>20,
                'sort'=>[
                    'last_detected'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'term'=>[
                        'advertiser'=>$value->id
                    ]
                ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH'),$curl_param);
            $sub_data = DBUtil::get_parse_es_data($rs);
            if(empty($sub_data['data']))continue;
            foreach ($sub_data['data'] as $k => $v) {
                $sub_data_tmp[] = (array)$v;
            }
        }
        foreach ($sub_data_tmp as $k => &$v) {
            $curl_param_sub = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                'term'=>[
                                    'subject'=>$v['id']
                                ]
                            ],
                            'should'=>[
                                [
                                    'term'=>[
                                        'width'=>$width
                                    ]
                                ],
                                [
                                    'term'=>[
                                        'height'=>$height
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'ads_id'=>[
                            'terms'=>[
                                'field'=>'id',
                                'size'=>5,
                                'order'=>[
                                    [
                                        'max_wdith'=>'desc',
                                    ],
                                    [
                                        'max_height'=>'desc'
                                    ]
                                ]
                            ],
                            'aggs'=>[
                                'max_wdith'=>[
                                    'max'=>[
                                        'field'=>'width'
                                    ]
                                ],
                                'max_height'=>[
                                    'max'=>[
                                        'field'=>'height'
                                    ]
                                ]
                            ]
                        ]
                    ]
            ];
            $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param_sub);
            $mi_data = json_decode($rs,true);
            $mi_arr = empty($mi_data['aggregations']['ads_id']['buckets'])?[]:$mi_data['aggregations']['ads_id']['buckets'];
            if(empty($mi_arr))continue;
            // shuffle($mi_arr);
            $ads_tmp = [];
            foreach ($mi_arr as $k_m => $v_m) {
                    $curl_param_m = [
                        'query'=>[
                            'bool'=>[
                                'must'=>[
                                    'term'=>[
                                        'id'=>$v_m['key']
                                    ]
                                ]
                               
                            ]
                        ]
                    ];
                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param_m);
                    $ads_data = DBUtil::get_parse_es_data($rs);
                    if(!empty($ads_data['data']))
                    {
                        $ads_tmp[] = (array)$ads_data['data'][0];
                    }
            }
            // shuffle($ads_tmp);
            $v['subject_ads'] = $ads_tmp;
        }


        foreach ($sub_data_tmp as $logk => &$logv) {
            if(!empty($logv['subject_ads']))
            {
                foreach ($logv['subject_ads'] as $logkk => $logvv) {
                    $curl_param = [
                        'size'=>0,
                        'query'=>[
                            'bool'=>[
                                'must'=>[
                                    'term'=>[
                                        'md5'=>$logvv['md5']
                                    ]
                                ]
                            ]
                        ],
                        'aggs'=>[
                            'logads'=>[
                                'cardinality'=>[
                                    'field'=>'id'
                                ]
                            ]
                        ]
                    ];

                    $rs = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param);
                    $rs_log = json_decode($rs,true);

                    $logv['subject_ads'][$logkk]['ads_logs'] = empty($rs_log['aggregations']['logads']['value'])?1:$rs_log['aggregations']['logads']['value'];
                }
            }
        }
        $data['data'] = $sub_data_tmp;
        $data['total'] = count($sub_data_tmp);
        
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 根据编号 获取时间段内投放情况
     * start_time 开始时间 时间戳
     * end_time 结束时间 时间戳
     * id  创意编号
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getSearchAds(Request $request)
    {
        $start_time  = empty($request->start_time)?'':$request->start_time;

        $end_time  = empty($request->end_time)?'':$request->end_time;

        $id  = empty($request->id)?'':$request->id;

        if(empty($id) || empty($start_time) || empty($end_time))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>206,'smg'=>'数据参数不可为空'],200);
        }

        if($start_time > $end_time)
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>206,'smg'=>'开始时间不可大于结束时间'],200);
        }


        $addata = DB::connection('mysql_topmain_true')->table('addata')
        ->select('id','md5')
        ->where('id',$id)->first();

        if(empty($addata))
        {
            $data['allday'] = 0;
            $data['allads'] = 0;
            return HttpUtil::retrun_json($data,200);
        }
        $md5 = $addata->md5;


        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'md5'=>$md5
                            ]
                        ],
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
                'allads'=>[
                    'cardinality'=>[
                        'field'=>'id'
                    ]
                ],
                'allday'=>[
                    'cardinality'=>[
                        'field'=>'date_day_long'
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_AGG_SEARCH'),$curl_param);
        $rs_log = json_decode($rs,true);

        $allads = empty($rs_log['aggregations']['allads']['value'])?'':$rs_log['aggregations']['allads']['value'];

        $allday = empty($rs_log['aggregations']['allday']['value'])?'':$rs_log['aggregations']['allday']['value'];

        $data['allday'] = $allday;
        $data['allads'] = $allads;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取广告主
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAdsNew(Request $request)
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
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH_4'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        // rsort($data['data']);
        $data['status'] = 200;
        // dd($data);
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取营销活动
     */
    public function getSubjectAds(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $size = 100;
        $page = rand(1,5);
        $size_all = $size * $page;
        $curl_param = [
            'size'=>0,
            'query'=>[
                    'bool'=>[
                        'must'=>[]
                    ]
                ],
                'aggs'=>[
                    'count'=>[
                        'cardinality'=>[
                            'field'=>'subject_md5'
                        ]
                    ],
                    'groub'=>[
                        'terms'=>[
                            'field'=>'subject_md5',
                            'size'=>$size_all,
                            'order'=>[
                                [
                                    'max_score'=>'desc',
                                ],
                                [
                                    'max_date'=>'desc'
                                ]
                                
                            ]
                        ],
                        'aggs'=>[
                            'max_score'=>[
                                'max'=>[
                                    'script'=>'_score'
                                ]
                            ],
                            'max_date'=>[
                                'max'=>[
                                    'field'=>'date5'
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        $valiedParam = [];
        if($role)$valiedParam['role'] = $role;
        if($valiedParam)
        {
          foreach ($valiedParam as $key => $value) {
              $item = [
                'term'=>[
                  $key=>$value
                ]
              ];
              array_push($curl_param['query']['bool']['must'], $item);
          }
        }
        // $url = config('param.ES_URL').'addata_v13/'.config('param.ES_SEARCH');


        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $rs_des = json_decode($rs,true);
        $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];
        $filed_tmp = empty($rs_des['aggregations']['groub']['buckets'])?[]:$rs_des['aggregations']['groub']['buckets'];
        if(empty($filed_tmp))return HttpUtil::retrun_json(['data'=>[],'total'=>0],200);
        $rm_limit = 0;
        if($page>1)
        {
            if($total>200)
            {
                if($size_all!=$total)
                {
                    $rm_limit = $size * ($page-1);
                }
            }
        }
        if($page>1)
        {
            foreach ($filed_tmp as $key => $value) {
                if($key<$rm_limit)unset($filed_tmp[$key]);
            }
        }
        foreach ($filed_tmp as $key => $value) {
            $curl_param = [
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'term'=>[
                                'md5'=>$value['key']
                            ]
                        ]
                    ]
                ]
            ];
            // dd(json_encode($curl_param));
            $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $subject_data = json_decode($rs,true);
                
            $subject_tmp = empty($subject_data['hits']['hits'][0]['_source'])?[]:$subject_data['hits']['hits'][0]['_source'];
            if($subject_tmp)
            {
                $subject_tmp['ads'] = $value['doc_count'];
                $curl_param = [
                    'size'=>1,
                    'sort'=>[
                        'created_date'=>[
                            'order'=>'desc'
                        ]
                    ],
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                'term'=>[
                                    'subject_md5'=>$value['key']
                                ]
                            ]
                        ]
                    ]
                ];
                $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
                $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
                $rs_des = json_decode($rs,true);
                $subject_tmp['last_detected'] = $rs_des['hits']['hits'][0]['_source']['created_date'];
                $data['subject'][] = $subject_tmp;
            }
        }
        
        // 暂无数据 2002
        if(empty($data['subject']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $data['total'] = count($data['subject']);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取 广告创意
     * @return [type] [description]
     */
    public function getAdsMind(Request $request)
    {
        $type = empty($request->type)?'':$request->type;
        $shape = empty($request->shape)?'':$request->shape;
        $min_data = empty($request->start_time)?0:$request->start_time;
        $end_time = empty($request->end_time)?0:$request->end_time;
        $platform = empty($request->platform)?'':$request->platform;
        $role = empty($request->role)?'':$request->role;
        $shape_width = empty($request->shape_width)?0:$request->shape_width;
        $shape_hight = empty($request->shape_hight)?0:$request->shape_hight;
        // 每页显示 数据个数
        $page = rand(1,10);
        $from = $page>1?$page*200:0;
        // 搜索内容
        $param = empty($request->wd)?'':$request->wd;
        // 参数不可为空
        if($end_time < $min_data)return HttpUtil::retrun_json(['data'=>[],'status'=>204],200);
        // 验证参数格式
        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        $valiedParam = [];
        if($type)$valiedParam['type'] = $type;
        if($shape_hight && $shape_width)
        {
            $valiedParam['width'] = $shape_width;
            $valiedParam['height'] = $shape_hight;
        }elseif($shape_width || $shape_hight)
        {
            if($shape_width)$valiedParam['width'] = $shape_width;
            if($shape_hight)$valiedParam['height'] = $shape_hight;
        }else{
            if($shape)$valiedParam['shape'] = $shape;
        }
        if($platform)$valiedParam['platform'] = $platform;
        if($role)$valiedParam['role'] = $role;
        $curl_param = [
                'from'=>$page,
                'size'=>200,
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
                                        ]
                                    ]
                                ],
                                'must'=>[
                                ]
                            ]
                        ],
                        'functions'=>[
                            [
                                'filter'=>[
                                    'match_phrase'=>[
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
                                        'title'=>[
                                          'query'=>$param,
                                          'minimum_should_match'=>'100%'
                                        ]
                                    ]
                                ],
                                "weight"=>'2'
                            ],
                            [
                                'filter'=>[
                                    'match_phrase'=>[
                                        'advertiser_name'=>$param
                                    ]
                                ],
                                "weight"=>'10'
                            ],
                            [
                                'script_score'=>[
                                    'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                                ]
                            ]
                        ],
                        'score_mode'=>'sum'
                    ],
                ],
                 'sort'=>[
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
        $curl_param2 = [
                'from'=>$from,
                'size'=>200,
                'query'=>[
                    'bool'=>[
                        'must'=>[]
                    ]
                ]
            ];
        if(empty($param))$curl_param = $curl_param2;
        $field_arr = [];
        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];
                if($param)
                {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
                }else{
                    array_push($curl_param['query']['bool']['must'], $item);
                }
                
            }
        }
        $range = [];
        if($min_data && $end_time){
            $range[] = [
                'range'=>[
                    'date5'=>[
                            'gte'=>$min_data,
                            'lte'=>$end_time
                        ]
                    ]
                ];
        }else if($min_data)
        {
             $range[] = [
                'range'=>[
                    'date5'=>[
                        'gte'=>$min_data
                    ]
                ]
            ];
        }
        
        if($range)
        {
            foreach ($range as $key => $value) {
                 if($param)
                 {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);
                }else{
                    array_push($curl_param['query']['bool']['must'], $value);
                }
            }
        }
        // $rs = HttpUtil::parse_curl_search('addata_v13',$curl_param);

        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);




        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 统计
     * 点击 营销活动  表格
     * @return [type] [description]
     */
    public function getTableSubjectInfo(Request $request)
    {
        $size = empty($request->size)?16:$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $param = empty($request->wd)?'':$request->wd;
        $page = empty($request->page)?1:$request->page;
        if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $size_all = $page * $size;
        $curl_param = [
                'size'=>0,
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
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'functions'=>[
                            [
                                'filter'=>[
                                    'match_phrase'=>[
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
                                        'title'=>[
                                          'query'=>$param,
                                          'minimum_should_match'=>'100%'
                                        ]
                                    ]
                                ],
                                "weight"=>'2'
                            ],
                            [
                                'filter'=>[
                                    'match_phrase'=>[
                                        'advertiser_name'=>$param
                                    ]
                                ],
                                "weight"=>'10'
                            ]
                        ],
                        'score_mode'=>'sum',
                        'max_boost'=>'10'
                    ],
                ],
                'aggs'=>[
                    'count'=>[
                        'cardinality'=>[
                            'field'=>'subject_md5'
                        ]
                    ],
                    'groub'=>[
                        'terms'=>[
                            'field'=>'subject_md5',
                            'size'=>$size_all,
                            'order'=>[
                                [
                                    'max_score'=>'desc',
                                ],
                                [
                                    'max_date'=>'desc'
                                ]
                                
                            ]
                        ],
                        'aggs'=>[
                            'max_score'=>[
                                'max'=>[
                                    'script'=>'_score'
                                ]
                            ],
                            'max_date'=>[
                                'max'=>[
                                    'field'=>'date5'
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        if(empty($param))unset($curl_param['query']);
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $rs_des = json_decode($rs,true);
        $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];
        $filed_tmp = empty($rs_des['aggregations']['groub']['buckets'])?[]:$rs_des['aggregations']['groub']['buckets'];
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
        if($page>1)
        {
            foreach ($filed_tmp as $key => $value) {
                if($key<$rm_limit)unset($filed_tmp[$key]);
            }
        }
        $data['subject'] = [];
        $subject_tmp = [];
        foreach ($filed_tmp as $key => $value) {
            $curl_param = [
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'term'=>[
                                'md5'=>$value['key']
                            ]
                        ]
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $subject_data = json_decode($rs,true);
            if(!empty($subject_data['hits']['hits'][0]['_source']))
            {
                $subject_tmp = $subject_data['hits']['hits'][0]['_source'];
                $subject_tmp['decode_id'] = base64_encode($subject_data['hits']['hits'][0]['_source']['id']);
                $subject_tmp['ads'] = $value['doc_count'];
                $curl_param = [
                    'size'=>1,
                    'sort'=>[
                        'created_date'=>[
                            'order'=>'desc'
                        ]
                    ],
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                'term'=>[
                                    'subject_md5'=>$value['key']
                                ]
                            ]
                        ]
                    ]
                ];
                $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
                $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
                $rs_des = json_decode($rs,true);
                $subject_tmp['last_detected'] = $rs_des['hits']['hits'][0]['_source']['created_date'];
                $data['subject'][] = $subject_tmp;
            }
            
        }
        $data['total'] = $total;
        // 暂无数据 2002
        if(empty($data['subject']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    
    /**
     * 获取 广告主 信息
     * 
     * @return [type] [description]
     */
    public function getDomainInfo(Request $request)
    {
        $host = $request->host;
        $host = base64_decode($host);
        $curl_param = [
            'query'=>[
                'term'=>[
                    'host'=>$host
                ]
            ]
        ];
        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH_4').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data3($rs);
        if(empty($data['data'][0]))return HttpUtil::retrun_json(['data'=>[],'status'=>207,'msg'=>'暂无数据']);
        $data['data'] = $data['data'][0];
        $data['total'] = count($data['data']);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    } 
    /**
     * 根据角色 获取 最新 图表数据
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function geTableInfoByRole(Request $request)
    {
        $data = $this->adsCount();
        foreach ($data as $key => $value) {
            
        }
        $data_new['ads'] = empty($data[0]['aggregations']['role_count']['value'])?0:$data[0]['aggregations']['role_count']['value'];
        $data_new['publisher'] = empty($data[1]['aggregations']['role_count']['value'])?0:$data[1]['aggregations']['role_count']['value'];
        $data_new['tracker'] = empty($data[2]['aggregations']['role_count']['value'])?0:$data[2]['aggregations']['role_count']['value'];
        return HttpUtil::retrun_json($data_new,200);
    }
    public function adsCount()
    {
        $data = [];
        for ($i=1; $i < 4; $i++) { 
            $curl_param = [
                'size'=>0,
                'query'=>[
                    'term'=>[
                        'role'=>$i
                    ]
                ],
                'aggs'=>[
                    'role_count'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ]
                ]
            ];
            // $url = config('param.ES_URL').'addata_v13/'.config('param.ES_SEARCH');
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data[] = json_decode($rs,true);
        }
        return $data;
    }
    /**
     * 根据 角色 时间 过滤
     * @return [type] [description]
     */
    public function geInfoByRoleFilter()
    {
    }
    /**
     * @return [type] [description]
     * role 1 广告主 2 媒体 3 追踪者
     */
    public function getTableInfo(Request $request)
    {
        $role = $request->role;
        $orderBy = ($role==1?'publisher_ads':($role==2?'brand_subjects':'tracker_advertiser'));
        $sort = empty($request->sort)?'desc':$request->sort;
        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;
        $size = empty($request->size)?1000:$request->size;
        $range = [];
       
        if($start_time && $end_time){
              $range[] = [
                'range'=>[
                    'date5'=>[
                            'gte'=>$start_time,
                            'lte'=>$end_time
                        ]
                    ]
                ];
            
        }else if($start_time){
            $range[] = [
                'range'=>[
                    'date5'=>[
                        'gte'=>$start_time
                    ]
                ]
            ];
        }
       
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'role'=>$role
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'data'=>[
                    'terms'=>[
                        'field'=>'domain_host',
                        'size'=>$size,
                        'order'=>[
                            [
                                'max_date'=>'desc'
                            ]
                            
                        ]
                    ],
                    'aggs'=>[
                            'max_date'=>[
                                'max'=>[
                                    'field'=>$orderBy
                                ]
                            ]
                        ]
                ]
            ]
        ];
        if($range)
        {
            foreach ($range as $key => $value) {
              if($value) array_push($curl_param['query']['bool']['must'], $value);
            }
        }
        // $url = config('param.ES_URL').'addata_v13/'.config('param.ES_SEARCH');
        
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $rs_param = json_decode($rs,true);
        $domain_buckets = $rs_param['aggregations']['data']['buckets'];
        $doamin_list = [];
        foreach ($domain_buckets as $key => $value) {
            $curl_param = [
                'size'=>1,
                'query'=>[
                    'term'=>[
                        'host'=>$value['key']
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data = DBUtil::get_parse_es_data($rs);
            if(!empty($data['data'][0]))$doamin_list[] = (array)$data['data'][0];
        }
        if(empty($doamin_list))return HttpUtil::retrun_json(['data'=>[],'status'=>207,'msg'=>'暂无数据'],200);
    
        $edition = [];
        foreach ($doamin_list as $k => $v) {
          $edition[] = $v[$orderBy];
        }
        array_multisort($edition, SORT_ASC, $doamin_list);
        $data['total'] = count($doamin_list);
        $data['data'] = $doamin_list;
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 广告数据库 搜索
     * 四福联动
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getTableInfoAds(Request $request)
    {
    }
    
    
    /**
     * type 1 addata 2 mainindex 3 subject 4 domain
     * 10 addata_attr07 
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
            case '8':
                # code...
                $search = 'addata_aggs/addata_index';
                break;
            case '9':
                # code...
                $search = config('param.RANKROLE_SEARCH');
                break;
            case '10':
                # code...
                $search = config('param.ADDATA_ATTR_SEARCH');
                break;
            case '11':
                # code...
                $search = config('param.ADDATA_SEARCH6');
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
     * 获取 用户 搜索的最多的数据
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
     * 2017-6-2 判断域名是否是 跟踪者 二级 域名
     * 2017-6-16 新版本 所有都来自 addata 查询
     * 新增 type  来区分 是否是点击 排行榜 跟踪者 过来 1 是 0 不是
     * @return [type] [description]
     */
    public function getInfoByRole(Request $request)
    {
        $role = empty($request->role)?'':$request->role;
        $rolevalue = empty($request->rolevalue)?'':$request->rolevalue;
        $size = empty($request->size)?1:$request->size;
        $type = empty($request->type)?0:$request->type;


        $tr_host = '';
        if($role=='subject')
        {
            $tr_host = $rolevalue;
        }else if($role=='risk'){
            $tr_host =1;
        }else {
            // 新版本
            $is_flag_domian = DBUtil::getFlagById($rolevalue);
            if(empty($is_flag_domian))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
            $tr_host = $is_flag_domian[0]->host;
        }
        
        $role_f = '';
        // 获取 risk 数
        switch ($role) {
            case 'advertiser':
                $role_f = 'advertiser_na';
                break;
            case 'publisher':
                $role_f = 'publisher_na';
                break;
            case 'tracker':
                $role_f = 'tracker_list';
                break;
            case 'subject':
                $role_f = 'subject';
                break;
            case 'risk':
                $role_f = 'risk';
                break;
        }
        if($role=='tracker' && !empty($is_flag_domian[0]->flag) && $is_flag_domian[0]->flag==1)$role_f = 'tags_list';

        if($role=='tracker' && $type==1)$role_f = 'tags_list';

        $curl_param_ad = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $role_f=>$tr_host
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
                ],
                'advertisers'=>[
                    'cardinality'=>[
                        'field'=>'advertiser_na'
                    ]
                ],
                'risk'=>[
                    'sum'=>[
                        'field'=>'risk'
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
                'minid'=>[
                    'min'=>[
                        'field'=>'created_date'
                    ]
                ],
                'maxid'=>[
                    'max'=>[
                        'field'=>'created_date'
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param_ad));


        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param_ad);
        $ad_da = DBUtil::get_parse_es_data($rs);
        $ad_id = empty($ad_da['data'][0]->id)?0:$ad_da['data'][0]->id;
        
        $da = json_decode($rs,true);

        if(empty($da['hits']['hits']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $item['ads'] = empty($da['aggregations']['ads']['value'])?'':$da['aggregations']['ads']['value'];
        $item['subjects'] = empty($da['aggregations']['subjects']['value'])?'':$da['aggregations']['subjects']['value'];
        $item['advertisers'] = empty($da['aggregations']['advertisers']['value'])?'':$da['aggregations']['advertisers']['value'];
        $item['publishers'] = empty($da['aggregations']['publishers']['value'])?'':$da['aggregations']['publishers']['value'];
        $item['trackers'] = empty($da['aggregations']['trackers']['value'])?'':$da['aggregations']['trackers']['value'];
        $item['risk'] = empty($da['aggregations']['risk']['value'])?'':$da['aggregations']['risk']['value'];
       
        
        $maxid = empty($da['aggregations']['maxid']['value'])?'':$da['aggregations']['maxid']['value'];
        $minid = empty($da['aggregations']['minid']['value'])?'':$da['aggregations']['minid']['value'];
        $data['data'] = $item;
        $data['status'] = 200;
        $data['ad_id'] = $ad_id;
        $data['maxid'] = $maxid;
        $data['minid'] = $minid;
        return HttpUtil::retrun_json($data,200);


        dd('new version is ok');



        // 老版本

        if($role=='tracker')
        {
            $is_flag_domian = DBUtil::getFlagById($rolevalue);

            if(empty($is_flag_domian))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

            if($is_flag_domian[0]->flag==1)
            {
               
                $tr_host = $is_flag_domian[0]->host;

                $curl_param_ad = [
                    'size'=>$size,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        'tags_list'=>$tr_host
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
                        ],
                        'advertisers'=>[
                            'cardinality'=>[
                                'field'=>'advertiser_na'
                            ]
                        ],
                        'risk'=>[
                            'sum'=>[
                                'field'=>'risk'
                            ]
                        ],
                        'publishers'=>[
                            'cardinality'=>[
                                'field'=>'publisher_na'
                            ]
                        ],
                        'trackers'=>[
                            'cardinality'=>[
                                'field'=>'tracker_na'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'created_date'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'created_date'
                            ]
                        ]
                    ]
                ];

                $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param_ad);
                $ad_da = DBUtil::get_parse_es_data($rs);
                $ad_id = empty($ad_da['data'][0]->id)?0:$ad_da['data'][0]->id;
                
                $da = json_decode($rs,true);

                if(empty($da['hits']['hits']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

                $item['ads'] = empty($da['aggregations']['ads']['value'])?'':$da['aggregations']['ads']['value'];
                $item['subjects'] = empty($da['aggregations']['subjects']['value'])?'':$da['aggregations']['subjects']['value'];
                $item['advertisers'] = empty($da['aggregations']['advertisers']['value'])?'':$da['aggregations']['advertisers']['value'];
                $item['publishers'] = empty($da['aggregations']['publishers']['value'])?'':$da['aggregations']['publishers']['value'];
                $item['trackers'] = empty($da['aggregations']['trackers']['value'])?'':$da['aggregations']['trackers']['value'];
                $item['risk'] = empty($da['aggregations']['risk']['value'])?'':$da['aggregations']['risk']['value'];
               
                
                $maxid = empty($da['aggregations']['maxid']['value'])?'':$da['aggregations']['maxid']['value'];
                $minid = empty($da['aggregations']['minid']['value'])?'':$da['aggregations']['minid']['value'];
                $data['data'] = $item;
                $data['status'] = 200;
                $data['ad_id'] = $ad_id;
                $data['maxid'] = $maxid;
                $data['minid'] = $minid;
                return HttpUtil::retrun_json($data,200);
            }else{}
        }
       

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
        
        if(empty($da['hits']['hits']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        }
        $item['ads'] = empty($da['aggregations']['ads']['value'])?'':$da['aggregations']['ads']['value'];
        $item['subjects'] = empty($da['aggregations']['subjects']['value'])?'':$da['aggregations']['subjects']['value'];
        $item['advertisers'] = empty($da['aggregations']['advertisers']['value'])?'':$da['aggregations']['advertisers']['value'];
        $item['publishers'] = empty($da['aggregations']['publishers']['value'])?'':$da['aggregations']['publishers']['value'];
        $item['trackers'] = empty($da['aggregations']['trackers']['value'])?'':$da['aggregations']['trackers']['value'];

        // 获取 risk 统计
        $is_flag_domain = DBUtil::getFlagById($rolevalue);

        if(empty($is_flag_domain))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $rolevalue = $is_flag_domain[0]->host;

        $role_f = '';

        // 获取 risk 数
        switch ($role) {
            case 'advertiser':
                $role_f = 'advertiser_na';
                break;
            case 'publisher':
                $role_f = 'publisher_na';
                break;
            case 'subject':
                $role_f = 'subject_md5';
                break;
            case 'tracker':
                $role_f = 'trackers';
                break;
        }

        $curl_param_d = [
            'size'=>0,
            'query'=>[
                'term'=>[
                    $role_f => $rolevalue
                ]
            ],
            'aggs'=>[
                'risk'=>[
                    'sum'=>[
                        'field'=>'risk'
                    ]
                ]
            ]
        ];
        // dd(json_encode($curl_param_d));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param_d);
        $rs_des = json_decode($rs,true);
        $item['risk'] = empty($rs_des['aggregations']['risk']['value'])?0:$rs_des['aggregations']['risk']['value'];
        // 重新计算
        $maxid = empty($da['aggregations']['maxid']['value'])?'':$da['aggregations']['maxid']['value'];
        $minid = empty($da['aggregations']['minid']['value'])?'':$da['aggregations']['minid']['value'];
        $data['data'] = $item;
        $data['status'] = 200;
        $data['ad_id'] = $ad_id;
        $data['maxid'] = $maxid;
        $data['minid'] = $minid;
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
    
   
    //=========================== 暂勿使用 =============================
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

    public function my_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){   
        if(is_array($arrays)){   
            foreach ($arrays as $array){   
                if(is_array($array)){   
                    $key_arrays[] = $array[$sort_key];   
                }else{   
                    return false;   
                }   
            }   
        }else{   
            return false;   
        }  
        array_multisort($key_arrays,$sort_order,$sort_type,$arrays);   
        return $arrays;   
    } 
    /**
     * 获取注册用户 公司 和 每个公司 下用户数
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getUserInfo(Request $request)
    {
        set_time_limit(0);

        // SELECT * from oc_ucenter_member 
        $db = DB::connection('mysql_oc_true')->table('oc_ucenter_member')->select('email','id')->get();

        $return = [];
        $cam_exist = [];

        foreach ($db as $key => $value) {
            $email = $value->email;

            $email_arr = explode('@', $email);
            if(in_array($email_arr[1], $cam_exist))
            {
                $return[$email_arr[1]] = $return[$email_arr[1]] + 1;
            }else{
                $cam_exist[] = $email_arr[1];

                $return[$email_arr[1]] = 1;
            }
        }


        $cellData = [
            ['域名','中文名','人数']
        ];
        if(count($return))
        {
            // $return = rsort($return);
            $new_data = [];
            foreach ($return as $key => $value) {

                $domain = Domains::where('host',$key)->first();

                $item['host'] = $key;

                $item['cname'] = empty($domain->cname)?'':$domain->cname;

                $item['count'] = $value;

                $new_data[] = $item;
                
            }

            $new_data = $this->my_sort($new_data,'count',SORT_DESC,SORT_NUMERIC);


            foreach ($new_data as $key => $value) {
                $item1['host'] = $value['host'];

                $item1['cname'] = $value['cname'];

                $item1['count'] = $value['count'];
                array_push($cellData, $item1);
            }

            // array_push($cellData, $item);
            Excel::create('adbug 注册公司统计信息',function($excel) use ($cellData){
                    $excel->sheet('info', function($sheet) use ($cellData){
                        $sheet->rows($cellData);
                    });
            })->export('xls');
        }
            


        dd($return);
    }
    /**
     * 获取 上月 新增 用户
     */
    public function getUserInfoNew(Request $request)
    {
        $db = DB::connection('mysql_oc')->table('oc_maxuser')->where('id',1)->first();
        $id = 0;
        if($db)$id = $db->uid;
        $userinfo = DB::connection('mysql_oc')->table('oc_ucenter_member as um')
                    ->join('oc_member as m','m.uid','=','um.id')
                    ->select('um.email','um.id','um.reg_time','m.nickname')
                    ->where('um.id','>',$id)
                    ->orderBy('um.id','desc')
                    ->get();
        $cellData = [
            ['编号','名称','邮箱','注册时间']
        ];
        if(count($userinfo))
        {
            foreach ($userinfo as $key => $value) {
                $item['id'] = $value->id;
                $item['name'] = $value->nickname;
                $item['email'] = $value->email;
                $item['date'] =empty($value->reg_time)?'':date('Y-m-d H:i:s',$value->reg_time);
                array_push($cellData, $item);
            }
            Excel::create('新增用户信息',function($excel) use ($cellData){
                    $excel->sheet('score', function($sheet) use ($cellData){
                        $sheet->rows($cellData);
                    });
            })->export('xls');
            // Mail::send('emails.user',['date'=>date('Y-m-d'),'desc'=>'用户新增'],function($message){
            //         $to = '1620683945@qq.com';
            //         $fileName = date('Ymd',time());
            //         $message->to($to)->subject('用户信息邮件');
            //         $attachment = '/public/excels/' . $fileName.'/'.$fileName.'.xls';
            //                 // $attachment = storage_path('excel/exports/'.$fileName.'xls');
            //                 //在邮件中上传附件
            //         $message->attach($attachment,['as'=>"=?UTF-8?B?".base64_encode('新增用户信息')."?=.xls"]);
            // });
            $max_id = $userinfo[0]->id;
            if($db)
            {
                DB::connection('mysql_oc')->table('oc_maxuser')
                    ->where('id', 1)
                    ->update(['uid' => $max_id]);
            }else{
                    DB::connection('mysql_oc')->table('oc_maxuser')
                    ->insert(['uid' => $max_id,'id'=>1]);
            }
                        
        }
    }
     /**
     * type 1 广告主 2 媒体 3 追踪者
     * @param  content 搜索内容
     * @param  size  当前页显示条数
     * @param  page 分页
     * @param  last_id 当前页 最大 编号 id
     */
    protected function getPubTacAdsIndex(Request $request)
    {
//        $page = empty($request->page)?1:$request->page;
//        $content = empty($request->content)?'':$request->content;
//        $size = empty($request->size)?config('param.PAGE_LIMIT'):$request->size;
//        $last_id = empty($request->last_id)?0:$request->last_id;
//        $type = empty($request->type)?'':$request->type;
//         //参数不可为空 2001
//        if(empty($content) || empty($type))return HttpUtil::retrun_json([],200);
//        switch ($type) {
//            case '1':
//                # code...
//                $type = 'brand_subjects';
//                break;
//            case '2':
//                $type = 'publisher_ads';
//                break;
//            case '3':
//                # code...
//                $type = 'tracker_advertiser';
//                break;
//        }
//        $curl_param = [
//            'sort'=>[
//                $type=>[
//                    'order'=>$sort
//                ]
//            ],
//            'size'=>$size,
//            'query'=>[
//                'match'=>[
//                    'host'=>$content
//                ]
//            ]
//        ];
//        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
//        $data = DBUtil::get_parse_es_data($rs);
//        // 暂无数据 2002
//        if(empty($data['data']))return HttpUtil::retrun_json($data,2002);
//        $data['last_id'] = $data['data'][0]->id;
//        if($sort=='asc')$data['last_id'] = $data['data'][$size-1]->id;
//        $data['status'] = empty($data['data'])?201:200;
//        return HttpUtil::retrun_json($data,200);
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
                'date5'=>[
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
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');
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
                'created_date'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size
            
        ];
        
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');
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
     * 获取 跟踪者 信息 
     * 2017-1-11 临时 使用
     * 导出 数据
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    protected function getTopTrackerAdsTest(Request $request)
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
        $rand_id = rand(1, 1000099);
        $sort = empty($request->sort)?'desc':$request->sort;

        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $from = rand(1,100)*$size;



        // if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($sort && !DBUtil::sortValied($sort))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        $curl_param = [
            'sort'=>[
                'date5'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size,
            'from'=>$from,
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
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');
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
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');
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
                        'risk'=>[
                            'sum'=>[
                                'field'=>'risk'
                            ]
                        ],
                        // 'minid'=>[
                        //     'min'=>[
                        //         'field'=>'id'
                        //     ]
                        // ],
                        // 'maxid'=>[
                        //     'max'=>[
                        //         'field'=>'id'
                        //     ]
                        // ]
                        'minid'=>[
                            'min'=>[
                                'field'=>'created_date'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'created_date'
                            ]
                        ]
                        // created_date
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
                        'risk'=>[
                            'sum'=>[
                                'field'=>'risk'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'created_date'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'created_date'
                            ]
                        ]
                        // 'minid'=>[
                        //     'min'=>[
                        //         'field'=>'id'
                        //     ]
                        // ],
                        // 'maxid'=>[
                        //     'max'=>[
                        //         'field'=>'id'
                        //     ]
                        // ]
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
                        'risk'=>[
                            'sum'=>[
                                'field'=>'risk'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'created_date'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'created_date'
                            ]
                        ]
                        // 'minid'=>[
                        //     'min'=>[
                        //         'field'=>'id'
                        //     ]
                        // ],
                        // 'maxid'=>[
                        //     'max'=>[
                        //         'field'=>'id'
                        //     ]
                        // ]
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
                        'risk'=>[
                            'sum'=>[
                                'field'=>'risk'
                            ]
                        ],
                        'minid'=>[
                            'min'=>[
                                'field'=>'created_date'
                            ]
                        ],
                        'maxid'=>[
                            'max'=>[
                                'field'=>'created_date'
                            ]
                        ]
                        // 'minid'=>[
                        //     'min'=>[
                        //         'field'=>'id'
                        //     ]
                        // ],
                        // 'maxid'=>[
                        //     'max'=>[
                        //         'field'=>'id'
                        //     ]
                        // ]
                ];
                break;
        }
        return $curl_param;
         
    }
    public function ppku(Request $request)
    {
        $param = $request->wd;
        $curl_param = [
            'size'=>1,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'multi_match'=>[
                                'query'=>$param,
                                'fields'=>[
                                    'title',
                                    'advertiser_name_title',
                                    'advertiser_full'
                                ]
                            ]
                        ],
                        [
                            'range'=>[
                                'wharea'=>[
                                    'lte'=>'4000000',
                                    'gte'=>'160000'
                                ]
                            ]
                        ]
                    ],
                    'should'=>[
                        [
                            'range'=>[
                                'width'=>[
                                    'gte'=>400,
                                    'lte'=>2048
                                ]
                            ]
                        ],
                        [
                          'range'=>[
                            'height'=>[
                                'gte'=>400,
                                'lte'=>2000
                            ]
                          ]  
                        ]
                    ]
                ]
            ]
        ];
        $rs_re = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs_re);
        return $data;
    }

    /*
     * @param  Request $request [description]
     * @return [type]           [description]
     */
     public function getTablePubTracInfo(Request $request)
    {
        
        // $orderBy = empty($request->orderBy)?'':$request->orderBy;
        $role = empty($request->role)?'':$request->role;
        $size = empty($request->size)?3:$request->size;
        $param = empty($request->wd)?'':$request->wd;
        $page = empty($request->page)?0:$request->page;
        
        $from = $page * $size;
        if(empty($role) || empty($size) || empty($param) || ($size && !is_numeric($size)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        
        if($size > config('param.MAXSIZE'))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'sort'=>[
                'id'=>[
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
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH_4'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * 临时 api
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function deleteJDB(Request $request)
    {

       // $curl_param = [
       //     'size'=>6197,
       //     'query'=>[
       //         'bool'=>[
       //             'must'=>[
       //                 [
       //                     'term'=>[
       //                         'domain'=>'com.hunantv.imgo.activity'
       //                     ]
       //                 ],
       //                  [
       //                     'term'=>[
       //                         'type'=>'flv'
       //                     ]
       //                 ],
       //                  [
       //                     'term'=>[
       //                         'platform'=>'2'
       //                     ]
       //                 ]
       //             ]
       //         ]
       //     ]
       // ];
       // // dd(json_encode($curl_param));
       // $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
       // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

       // $rs_data = json_decode($rs,true);
       // $data = $rs_data['hits']['hits'];
       // $exist = [
       // 8933167,8936208,8940305,9005859
       // ];
       // // $url = 'http://192.168.1.47:9200/addatas/addata_index/4548058';
       // // file_get_contents($url);
       // foreach ($data as $key => $value) {
       //     if(!empty($value['_source']['id']) && !in_array($value['_source']['id'], $exist))
       //     {
       //         $url = 'http://192.168.1.47:9200/addatas/addata_index/'.$value['_source']['id'];
       //         // file_get_contents($url);
       //          $ch = curl_init(); //初始化CURL句柄
       //          curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL
       //             curl_setopt($ch, CURLOPT_RETURNTRANSFER,true); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出
       //             curl_setopt($ch, CURLOPT_HEADER, false);  // 显示 头信息
       //             curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); //设置请求方式
       //             // curl_setopt($ch, CURLOPT_TIMEOUT, 30);
       //             // curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method"));//设置HTTP头信息
       //             // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));//设置提交的字符串
       //             $rs = curl_exec($ch);//执行预定义的CURL
       //             curl_close($ch);
       //     }else{
       //         echo $value['_source']['id'];
       //         echo '<br/>';
       //     }
       // }
       // $data = DBUtil::get_parse_es_data($rs);
       // foreach ($data as $key => $value) {
       //     // print_r($value);
       //     $value = (array)$value;
       // }
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
     * 手动处理 addata tags  的数据
     * 处理 addata tags 内容
     * 排名
     * @return [type] [description]
     */
    public function upAdTagsInfoAuto(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $id = empty($request->id)?0:$request->id;

        if(empty($id))dd('id is null');

        $limit = empty($request->limit)?10000:$request->limit;

        //11348465 都要处理
        $adTrack = DB::table('addata')
        ->where('id','<=',$id)
        ->select('id','tags','trackers')
        ->orderBy('id','desc')
        ->limit($limit)
        ->get();
        foreach ($adTrack as $key => $value) {

            $tags_list = $value->tags;
            $ad_id = $value->id;

            $trackers_list = $value->trackers;

            $tags_list_new = '';
            $trackers_list_new = '';
            if(!empty($tags_list))$tags_list_new = $this->splitTags($tags_list);
            if(!empty($trackers_list))$trackers_list_new = $this->splitTags($trackers_list);


            $data['tags'] = $tags_list_new;
            $data['trackers_list'] = $trackers_list_new;
            $data['id'] = $ad_id;
            $data['ad_id'] = $ad_id;

            $addata_tags = DB::table('addata_tags')->where('id',$value->id)->first();

            if(!$addata_tags)
            {
                DB::table('addata_tags')->insert($data);
            }else{
                DB::table('addata_tags')->where('id',$value->id)->update(['tags'=>$tags_list_new,'trackers_list'=>$trackers_list_new]);
            }
        }

        dd('ok');


    }

    /**
     * 分割字段内容
     * @return [type] [description]
     */
    private function splitTags($tags_list)
    {
        $tags_list_new = '';
        if(stristr($tags_list, ';'))
        {
            $tags_list_tmp = explode(';', $tags_list);

            $tags_list_tmp_num = count($tags_list_tmp);

            foreach ($tags_list_tmp as $k => $v) {

                if($v)
                {

                    if(stristr($v, '"'))
                    {
                        if($k==$tags_list_tmp_num-1)
                        {
                            $tags_list_new = $tags_list_new.$v;
                        }else{
                            $tags_list_new = $tags_list_new.$v.',';
                        }
                        continue;
                    }
                    if($k==$tags_list_tmp_num-1)
                    {
                        $tags_list_new = $tags_list_new.'"'.$v.'"';
                    }else{
                        $tags_list_new = $tags_list_new.'"'.$v.'",';
                    }
                }
            }
        }else if(stristr($tags_list, ','))
        {
            $tags_list_tmp = explode(',', $tags_list);

            $tags_list_tmp_num = count($tags_list_tmp);

            foreach ($tags_list_tmp as $k => $v) {

                if($v)
                {

                    if(stristr($v, '"'))
                    {
                        if($k==$tags_list_tmp_num-1)
                        {
                            $tags_list_new = $tags_list_new.$v;
                        }else{
                            $tags_list_new = $tags_list_new.$v.',';
                        }

                        continue;
                    }
                    if($k==$tags_list_tmp_num-1)
                    {
                        $tags_list_new = $tags_list_new.'"'.$v.'"';
                    }else{
                        $tags_list_new = $tags_list_new.'"'.$v.'",';
                    }
                }
            }
        }else{

            if(stristr($tags_list, '"'))
            {
                $tags_list_new = $tags_list;
            }else{
                $tags_list_new = $tags_list_new.'"'.$tags_list.'"';
            }
            
        }
        return $tags_list_new;
    }

    /**
     * 识别  图片中的文字
     * @return [type] [description]
     */
    public function ocrTitle(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        echo '图片文字识别','<br/>';

        echo 'start time:',' ',date("Y-m-d H:i:s",time());

        echo '<br/>';

       
        //57333
        $min_id = 8510000;

        $max_id = 8520000;
        // $addata_num = 500;

        $addata_num = Addata::where('id','>=',$min_id)->where('id','<=',$max_id)->where('title','')->where('type','image')->count();
       // $addata_num = $max_id-$min_id;
        echo 'addata_num:',$addata_num;

        // dd($addata_num);

        echo '<br/>';

        $limit = 2000;
        $id = $min_id;

        $no_title_num = 0;
        if($addata_num>=$limit)
        {
            $nums = $addata_num/$limit;

            if(!is_int($nums))$nums = intval($nums) + 1;
           
            for($i=0;$i<$nums;$i++)
            {
                // if($i>0)$id = $id+$limit;
                // $id = $id+$limit*$i;

                $offset = $i*$limit;
                $no_title_num = $no_title_num + $limit;
                $this->upAddataTitle($id,$max_id,$limit,$offset);
            }
        }else{
            $this->upAddataTitle($id,$max_id,$addata_num,0);
        }

        echo '<br/>','end time:',' ',date("Y-m-d H:i:s",time());


        echo '<br/>',$no_title_num,'<br/>';

        dd('ok');
    }


    /**
     * 跟新 addata title 的值
     * @return [type] [description]
     */
    public function upAddataTitle($id,$max_id,$limit,$offset)
    {
        // $addata = Addata::where('id','>=',$id)->where('id','<=',$max_id)->
        // where('type','image')->where('title','')->select('id','domain','thumbnail','thumb_url')->limit($limit)->get();


        $sql = 'select * from addata where id >= '.$id.' and id<='.$max_id.' and title="" and type="image" limit '.$offset.' ,'.$limit;
        $addata = DB::select($sql);
        //changan/AD_63_2013_11_26_10_06_24_976.jpg

        foreach ($addata as $key => $value) {

            $value = (array)$value;
            $url = 'http://121.43.166.146/adbug/service/ocr?url=http://file.adbug.cn/datasync/';
            $url = $url.$value['domain'].'/'.$value['thumb_url'];

            echo '开始Url:',$url,'<br/>';

            $url_data = HttpUtil::send_orc_curl_get($url);
            $url_data = json_decode($url_data,true);

            if(!empty($url_data['code']) && $url_data['code']=='Success')
            {
                $data['title'] = $url_data['ocrText'];
                DB::table('addata')->where('id',$value['id'])->update($data);
                unset($data);

                $adTitle = AdTitle::where('ad_id',$value['id'])->first();
                if(!$adTitle)$adTitle = new AdTitle();

                $adTitle->ad_id = $value['id'];
                $adTitle->save();

            }else{

                $adTitle = new AdTitle();
                $adTitle->ad_id = $value['id'];
                $adTitle->type = 1;
                $adTitle->save();

                echo '错误Url:','<br/>',$url,'<br/>',$value['id'],'<br/>';
            }
            $url = '';

            // sleep(1);
            usleep(250000);
        }
    }

    public function ocrTitle1(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        echo '图片文字识别','<br/>';

        echo 'start time:',' ',date("Y-m-d H:i:s",time());

        echo '<br/>';

       
        //57333
        $min_id = 8520000;
        $max_id = 8530000;
        // $addata_num = 500;

        $addata_num = Addata::where('id','>=',$min_id)->where('id','<=',$max_id)->where('title','')->where('type','image')->count();
        // $addata_num = $max_id-$min_id;
        echo 'addata_num:',$addata_num;

        echo '<br/>';

        $limit = 2000;
        $id = $min_id;

        $no_title_num = 0;
        if($addata_num>=$limit)
        {
            $nums = $addata_num/$limit;

            if(!is_int($nums))$nums = intval($nums) + 1;
           
            for($i=1;$i<$nums;$i++)
            {
                // if($i>1)$id = $id+$limit;

                $offset = $i*$limit;
                
                $no_title_num = $no_title_num + $limit;
                $this->upAddataTitle1($id,$max_id,$limit,$offset);
            }
        }else{
            $this->upAddataTitle1($id,$max_id,$addata_num,0);
        }

        echo '<br/>','end time:',' ',date("Y-m-d H:i:s",time());


        echo '<br/>',$no_title_num,'<br/>';

        dd('ok');
    }


    /**
     * 更新 addata trackers 字段值 为 数组
     * @return [type] [description]
     */
    public function upAddataTrackers(Request $request)
    {
        ini_set('memory_limit', '1024');
        set_time_limit(0);

        $id = empty($request->id)?0:$request->id;
        $limit = empty($request->limit)?50:$request->limit;
        if(empty($id))dd('id not null');

        $d_a_t = DB::table('addata')
        ->orderBy('id','desc')
        ->where('id','<=',$id)
        ->limit($limit)
        ->get();


        


    }
    /**
     * 获取 广告主 对应的子广告主数据
     * @return [type] [description]
     */
    public function getAdvertiserTmpPClinkEs(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $id = empty($request->id)?0:$request->id;

        $limit = empty($request->limit)?50:$request->limit;


        if(empty($id))dd('id not null');


        $d_a_t = DB::table('domian_advertiser_tmp')
        ->orderBy('id','desc')
        ->where('id','<=',$id)
        ->limit($limit)
        ->get();


        $data = [];

        foreach ($d_a_t as $key => $value) {
            $search = $value->ename;
            // $site = $value->csite;
            // $old_site = $site;
            $this->searchDataEs($search,$value->id);
            // $this->searchDataEs($search,$value->id,$old_site,$old_site);
            // dd($data);
        }
        dd('ok');
    }

     public function searchDataEs($search,$id)
    {
          
            $item['ename'] = $search;
        
            $oldparam = $search;
            $item['cid'] = $id;
            $item['domain'] = '';
            $item['ccname'] = '';
            // $item['child'] = 0;
            // $item['swd'] = $search;

            $en_ch = 1;
            $synonym = $this->getSynonym($en_ch,$search);

            $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
            $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];
            
            $search = empty($c_param)?$search:$c_param;

            // 默认
            $curl_param = [
                'size'=>0,
                'min_score'=>2,
                'query'=>[
                    'function_score'=>[
                        'filter'=>[
                            'bool'=>[
                                'should'=>[
                                    'multi_match'=>[
                                        'query'=>$search,
                                        'fields'=>[
                                            'title',
                                            'advertiser_name',
                                            'advertiser_name_title',
                                            'trackers',
                                            'tags'
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
                'sort'=>DSLUtil::baseSort(),
                'aggs'=>[
                    'advertiser_na'=>[
                        'terms'=>[
                            'field'=>'advertiser_na',
                            'size'=>20
                        ]
                    ]
                ]
            ];

            $curl_param = $this->isCurlCampaignMerge($curl_param,0,$c_host,$oldparam,$search);


            // dd(json_encode($curl_param));
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");


            $rs_data = json_decode($rs,true);


            if(empty($rs_data['aggregations']['advertiser_na']['buckets']))
            {
                DB::table('domain_tmp_child_es')->insert($item);
                $item['child'] = 0;
            }else{
                $rs_domain = $rs_data['aggregations']['advertiser_na']['buckets'];


                foreach ($rs_domain as $k => $v) {

                    if(empty($v))
                    {
                        continue;
                    }
                    $curl_param_domain = [
                        'query'=>[
                            'term'=>[
                                'host_no'=>$v['key']
                            ]
                        ]
                    ];

                    $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

                    $rs = HttpUtil::send_curl_methods($url,$curl_param_domain,"POST");
                    $data_d = DBUtil::get_parse_es_data($rs);

                    $item['domain'] = $v['key'];


                    if(!empty($data_d['data']))
                    {
                        $item['ccname'] = $data_d['data'][0]->cname;

                        // if(!empty($data_d['data'][0]->host) && !empty($old_site))
                        // {
                        //     if(stristr($old_site,$data_d['data'][0]->host) || stristr($data_d['data'][0]->host,$old_site))
                        //     {
                        //         $item['child'] = 1;
                        //     }

                        //     if($old_site == $data_d['data'][0]->host)
                        //     {
                        //         $item['child'] = 2;
                        //     }
                        // }
                    }

                    DB::table('domain_tmp_child_es')->insert($item);

                    // $item['child'] = 0;
                }

            }


            unset($item);


            // $data = DBUtil::get_parse_es_data($rs);


            // dd($data);

            // DB::table('domain_tmp_child3')->insert($item);
    }
    // public function searchDataEs($search,$id,$site,$old_site)
    // {
    //         $item['cid'] = $id;
    //         $item['ename'] = $search;
    //         $item['oldsite'] = $site;
    //         $oldparam = $search;
            
    //         $item['csite'] = $site;
    //         $item['domain'] = '';
    //         $item['ccname'] = '';
    //         $item['child'] = 0;
    //         $item['swd'] = $search;

    //         $en_ch = 1;
    //         $synonym = $this->getSynonym($en_ch,$search);

    //         $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
    //         $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];
            
    //         $search = empty($c_param)?$search:$c_param;

    //         // 默认
    //         $curl_param = [
    //             'size'=>0,
    //             'min_score'=>2,
    //             'query'=>[
    //                 'function_score'=>[
    //                     'filter'=>[
    //                         'bool'=>[
    //                             'should'=>[
    //                                 'multi_match'=>[
    //                                     'query'=>$search,
    //                                     'fields'=>[
    //                                         'title',
    //                                         'advertiser_name',
    //                                         'advertiser_name_title',
    //                                         'trackers',
    //                                         'tags'
    //                                     ]
    //                                 ]
    //                             ],
    //                             'must'=>[]
    //                         ]
    //                     ],
    //                     'functions'=>[
                            
    //                     ],
    //                     'score_mode'=>'sum',
    //                     'max_boost'=>'10'
    //                 ],
    //             ],
    //             'sort'=>DSLUtil::baseSort(),
    //             'aggs'=>[
    //                 'advertiser_na'=>[
    //                     'terms'=>[
    //                         'field'=>'advertiser_na',
    //                         'size'=>20
    //                     ]
    //                 ]
    //             ]
    //         ];

    //         $curl_param = $this->isCurlCampaignMerge($curl_param,0,$c_host,$oldparam,$search);


    //         // dd(json_encode($curl_param));
    //         $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

    //         $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");


    //         $rs_data = json_decode($rs,true);


    //         if(empty($rs_data['aggregations']['advertiser_na']['buckets']))
    //         {
    //             DB::table('domain_tmp_child_es')->insert($item);
    //             $item['child'] = 0;
    //         }else{
    //             $rs_domain = $rs_data['aggregations']['advertiser_na']['buckets'];


    //             foreach ($rs_domain as $k => $v) {

    //                 if(empty($v))
    //                 {
    //                     continue;
    //                 }
    //                 $curl_param_domain = [
    //                     'query'=>[
    //                         'term'=>[
    //                             'host_no'=>$v['key']
    //                         ]
    //                     ]
    //                 ];

    //                 $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

    //                 $rs = HttpUtil::send_curl_methods($url,$curl_param_domain,"POST");
    //                 $data_d = DBUtil::get_parse_es_data($rs);

    //                 $item['domain'] = $v['key'];


    //                 if(!empty($data_d['data']))
    //                 {
    //                     $item['ccname'] = $data_d['data'][0]->cname;

    //                     if(!empty($data_d['data'][0]->host) && !empty($old_site))
    //                     {
    //                         if(stristr($old_site,$data_d['data'][0]->host) || stristr($data_d['data'][0]->host,$old_site))
    //                         {
    //                             $item['child'] = 1;
    //                         }

    //                         if($old_site == $data_d['data'][0]->host)
    //                         {
    //                             $item['child'] = 2;
    //                         }
    //                     }
    //                 }

    //                 DB::table('domain_tmp_child_es')->insert($item);

    //                 $item['child'] = 0;
    //             }

    //         }


    //         unset($item);


    //         // $data = DBUtil::get_parse_es_data($rs);


    //         // dd($data);

    //         // DB::table('domain_tmp_child3')->insert($item);
    // }
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
     * 获取 广告主 对应的子广告主数据
     * @return [type] [description]
     */
    public function getAdvertiserTmpPClink(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $id = empty($request->id)?0:$request->id;

        $limit = empty($request->limit)?50:$request->limit;


        if(empty($id))dd('id not null');


        $d_a_t = DB::table('domian_advertiser_tmp')
        ->orderBy('id','desc')
        ->where('id','<',$id)
        ->limit($limit)
        ->get();


        $data = [];

        foreach ($d_a_t as $key => $value) {
            $search = $value->ename;
            $site = $value->csite;
            $old_site = $site;
            $item['cid'] = $value->id;
            $item['ename'] = $search;
            $item['oldsite'] = $site;
            
            $this->searchData($search,$value->id,$old_site,$old_site);

            if(stristr($site, '/'))
            {
                $site = explode('/', $site);
                $site = $site[0];
            }
            
            $site  = str_replace('www.', '',$site);

            $item['csite'] = $site;
            $item['domain'] = '';
            $item['ccname'] = '';
            $item['child'] = 0;
            $item['swd'] = $search;


            

            $tem = [];
            $s_num = explode(' ', $search);

            // dd($s_num);

            if(count($s_num)>1)
            {
                foreach ($s_num as $k => $v) {
                    $d_a_t_s = DB::table('domains')->where('host','like','%'.$v.'%')->limit(10)->get();

                    if($d_a_t_s)
                    {
                        foreach ($d_a_t_s as $sk => $sv) {

                            if(empty($sv))continue;

                            $item['ccname'] = $sv->cname;
                            $item['domain'] = $sv->host;

                            if(!empty($sv->host) && !empty($old_site))
                            {
                                if(stristr($old_site,$sv->host) || stristr($sv->host,$old_site))
                                {
                                    $item['child'] = 1;
                                }

                                if($site == $sv->host)
                                {
                                    $item['child'] = 2;
                                }
                            }
                            
                            $item['swd'] = $v;

                            // $data[] = $item;

                            DB::table('domain_tmp_child3')->insert($item);
                            $item['child'] = 0;
                        }
                    }else{
                        DB::table('domain_tmp_child3')->insert($item);
                    }
                    $item['child'] = 0;
                }
            }

            $search = str_replace(' ', '', $search);
            $this->searchData($search,$value->id,$old_site,$old_site);


            // dd($data);
        }
        dd('ok');
    }


    /**
     * 临时 处理  attr07 
     * 解析 获取 trackers【names ids】  domains isHidden 等信息
     * @return [type] [description]
     */
    public function getAttr07Tmp(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $id = empty($request->id)?0:$request->id;


        $limit = empty($request->limit)?50:$request->limit;


        if(empty($id))dd('id not null');


        $d_a_t = DB::table('addata')
        ->orderBy('id','desc')
        ->where('id','>=',$id)
        ->where('platform',1)
        ->where('attribute07','!=','')
        ->select('id','attribute07')
        ->limit($limit)
        ->get();


        $data = [];

        foreach ($d_a_t as $key => $value) {
            $ad_id = $value->id;

            $attribute07 = $value->attribute07;

            $attr_json = json_decode($attribute07,true);

            $isHidden = $attr_json['isHidden'];

            $domain = $attr_json['placement']['domains'];
            $names = $attr_json['placement']['names'];//trackers 对应的名称
            $ids = $attr_json['placement']['ids'];// trackers 对应的编号 和 fun 已有的表可以建立联系

            $domains_list = [];

            if(!empty($domain))
            {
                foreach ($domain as $k => $v) {
                    $v_t = $v;
                    if(!is_array($v_t)){
                        $v_t = str_replace('http://', '', $v_t);
                        $v_t = str_replace('https://', '', $v_t);
                        if(stristr($v_t, '/'))$v_t = explode('/', $v_t)[0];
                    }
                    $domains_list[] = $v_t;
                }
            }


            $attr_table  = AddataAttr::where('ad_id',$ad_id)->first();

            if(!$attr_table)$attr_table = new AddataAttr();

            //ids
            $ids_list = '';
            if(!empty($ids)){
                $ids_list = $this->paseChar($ids);
            }

            $trackers_list = empty($names)?'':$this->paseChar($names);

            $domains_list = empty($domains_list)?'':$this->paseChar($domains_list);

            $attr_table['ad_id'] = $ad_id;
            $attr_table['attr07'] = $attribute07;
            $attr_table['trackers'] = empty($names)?'':implode(',', $names);
            $attr_table['trackers_list'] = $trackers_list;
            $attr_table['is_hidden'] = $isHidden;
            $attr_table['domains'] = empty($domain)?'':implode(',', $domain);
            $attr_table['domains_list'] = $domains_list;
            $attr_table['ids'] = empty($ids)?'':implode(',', $ids);
            $attr_table['ids_list'] = $ids_list;

            $attr_table->save();

            // dd($attr_json);

        }
        dd('ok');
    }


    /**
     * 临时 处理  attr07 
     * 解析 获取 trackers【names ids】  domains isHidden 等信息
     * @return [type] [description]
     */
    public function getDomainFlagTmp(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $id = empty($request->id)?0:$request->id;


        $limit = empty($request->limit)?50:$request->limit;


        if(empty($id))dd('id not null');


        $d_a_t = DB::table('domains')
        ->orderBy('id','desc')
        ->where('id','<=',$id)
        ->select('id','flag','host','role')
        ->limit($limit)
        ->get();

        // dd($d_a_t);

        $data = [];

        foreach ($d_a_t as $key => $value) {
            $id = $value->id;
            $host = $value->host;

            $host_tmp = explode('.', $host);

            $flag = false;

            if(stristr($host, '.com.cn')!=false)
            {
                if(count($host_tmp)>3)$flag = true;
            }else
            {
                if(count($host_tmp)>2)$flag = true;
            }

            if(!empty($flag))
            {
                $item['flag'] = 1;
                $item['role'] = 3;
                DB::table('domains')->where('id',$id)->update($item);
            }
        }
        dd('ok');
    }
    /**
     * 每个 string 加上 “”
     * 方便 es 后期 统计
     * @param  [type] $param [description]
     * @return [type]        [description]
     */
    private function paseChar($param)
    {
        $new_param = '';
        foreach ($param as $k => $v) {

            if($k==count($param)-1)
            {
                $new_param = $new_param .'"'.$v.'"';
            }else{
                $new_param = $new_param .'"'.$v.'",';
            }
        }
        return $new_param;
    }

    public function searchData($search,$id,$site,$old_site)
    {
            $item['cid'] = $id;
            $item['ename'] = $search;
            $item['oldsite'] = $site;
            


            if(stristr($site, '/'))
            {
                $site = explode('/', $site);
                $site = $site[0];
            }
            
            $site  = str_replace('www.', '',$site);

            $item['csite'] = $site;
            $item['domain'] = '';
            $item['ccname'] = '';
            $item['child'] = 0;
            $item['swd'] = $search;

            $d_a_t_s = DB::table('domains')->where('host','like','%'.$search.'%')->limit(10)->get();

            if($d_a_t_s)
            {
                foreach ($d_a_t_s as $sk => $sv) {

                    if(empty($sv))continue;
                    $item['ccname'] = $sv->cname;
                    $item['domain'] = $sv->host;

                    if(!empty($sv->host) && !empty($old_site))
                    {
                        if(stristr($old_site,$sv->host) || stristr($sv->host,$old_site))
                        {
                            $item['child'] = 1;
                        }

                        if($site == $sv->host)
                        {
                            $item['child'] = 2;
                        }
                    }
                    
                    
                    $item['swd'] = $search;

                    DB::table('domain_tmp_child3')->insert($item);
                    $item['child'] = 0;
                    $item['swd'] = '';
                    // $data[] = $item;

                }
            }else{
                DB::table('domain_tmp_child3')->insert($item);
                $item['child'] = 0;
                $item['swd'] = '';
            }
    }
}
