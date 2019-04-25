<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use DB;
use Illuminate\Http\Request;
use App\Http\Utils\Tools;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use App\AddataRankRole;
use App\Domains;
use App\AdRRoleList;
use App\RankTracker;
use App\Company;
use Excel;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /** 
     * 获取最近一个月的数据
     * @return [type] [description]
     */
    public function getDateMouth(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $size = 1000;

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
                        'range'=>[
                            'date5'=>[
                                'gte'=>'2018-03-17 00:00:00'
                            ]
                        ]
                    ]
                ]
            ]
        ];


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
        $cellData = [
            ['id','title','advertiser','publisher','trackers','md5','last_seen',
            'type','fingerprint','attribute08','shape','attribute06','attribute04','
            size','domain','target_url','platform','am_source_url','am_url','material',
            'original_url','x','y','date5']
        ];

        foreach ($return_id as $key => $value) {
            $item['id'] = $value->id;
            $item['title'] = $value->title;
            $item['advertiser'] = $value->advertiser;
            $item['publisher'] = $value->publisher;
            $item['trackers'] = $value->trackers;
            $item['md5'] = $value->md5;
            $item['last_seen'] = $value->last_seen;
            $item['type'] = $value->type;
            $item['fingerprint'] = $value->fingerprint;
            $item['attribute08'] = $value->attribute08;
            $item['shape'] = $value->shape;
            $item['attribute06'] = $value->attribute06;
            $item['attribute04'] = $value->attribute04;

            $item['size'] = $value->size;
            $item['domain'] = $value->domain;
            $item['target_url'] = $value->target_url;
            $item['platform'] = $value->platform;
            $item['am_source_url'] = $value->am_source_url;
            $item['am_url'] = $value->am_url;
            $item['material'] = $value->material;
            $item['original_url'] = $value->original_url;

            $item['x'] = $value->x;
            $item['y'] = $value->y;
            $item['date'] = $value->date5;

            array_push($cellData, $item);

        }


        Excel::create('创意分类详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');


        // dd($cellData);
    }
    /**
     * APi7 使用说明
     * @return [type] [description]
     */
    public function getApiDesc7(Request $request)
    {
        return view('api7desc');
    }
    /**
     * {
  "size": 100,
  "query": {
    "bool": {
      "must": {
        "match_phrase": {
          "title": "污染处理"
        }
      }
    }
  }
}
     * @return [type] [description]
     */
    public function savePinpaiInfo(Request $request)
    {
        set_time_limit(0);

        $min_id = empty($request->minid)?'':$request->minid;
        $max_id = empty($request->maxid)?'':$request->maxid;

        if(empty($min_id) || empty($max_id))dd('id is not empty');

        $pinpai = DB::connection('mysql_topmain_true')->table('pinpai')
        ->where('id','>',$min_id)
        ->where('id','<=',$max_id)
        ->get();

        foreach ($pinpai as $key => $value) {

            if(empty($value->c_class))continue;

            $curl_param = [
                'size'=>4000,
                '_source'=>[
                    'title','id','domain','advertiser'
                ],
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            'match_phrase'=>[
                                'title'=>$value->c_class
                            ]
                        ]
                    ]
                ]
            ];

            $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
            $data = DBUtil::get_parse_es_data($rs);

            if(empty($data['data']))continue;
            
            foreach ($data['data'] as $k => $v) {

                DB::connection('mysql_topmain_true')->table('pinpaiinfo')->insert(['title'=>$v->title]);
            }


        }


    }
    /**
     * 清除没用的数据
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function delMianIndex(Request $request)
    {
        set_time_limit(0);
        $max_id = $request->max_id;
        $min_id = $request->min_id;

        $size = empty($request->size)?1000:$request->size;

        $mains = DB::connection("mysql_adbug_local")
        ->table('main_index')
        ->select('id')->where('index_id','>=',$min_id)
        ->where('index_id','<=',$max_id)->limit($size)->get();

        $ad_id = [];
        foreach ($mains as $key => $value) {
            $addatas = DB::connection("mysql_adbug_local")->table('addata_2018')->find($value->id);

            if(empty($addatas))
            {
                $ad_id[] = $value->id;
            }
        }

        dd($ad_id);
    }
    /**
     * 处理品牌
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function pinpai(Request $request)
    {
        set_time_limit(0);
        $pinpai = DB::connection('mysql_topmain_true')->table('pinpai')->get();
        $cellData = [
            ['大类','小类','域名','中文名称','英文名称']
        ];
        foreach ($pinpai as $key => $value) {

            if(empty($value->c_class))continue;

            $domains = DB::connection('mysql_topmain_true')->table('domains')
            ->where('role',2)
            ->where('cname','like','%'.$value->c_class.'%')->limit(200)->get();

            if(!empty($domains))
            {
                foreach ($domains as $k => $v) {

                    $item['parent_class'] = $value->p_class;
                    $item['child_class'] = $value->c_class;
                    $item['host'] = $v->host;
                    $item['cname'] = $v->cname;
                    $item['ename'] = $v->ename;
                    array_push($cellData, $item);
                }
            }

        }
        Excel::create('品牌分类详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');
    }

    public  function  login(Request $request)
    {
        Auth::login($request);
    }


    public function file2(Request $request)
    {


        $json = '{
  "query": {
    "bool": {
      "must": [
        {
          "term": {
            "type": "flv"
          }
        },
        {
          "range": {
            "date5": {
              "gte": "2018-01-04 00:00:00",
              "lte": "2018-01-04 23:59:59"
            }
          }
        }
      ]
    }
  }
}';

    $curl_param = json_decode($json,true);


        $host = self::getdomain($request->url);

        dd($host);

        return view('file2');
    }


    public static function getDomain($url) { 
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

    public function showDb2(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $min_id = empty($request->min_id)?0:$request->min_id;

        $max_id = empty($request->max_id)?0:$request->max_id;

        $urls = DB::connection('mysql_url')->table('main2')->select('url','id')->where('id','>=',$min_id)
        ->where('id','<=',$max_id)->get();


        foreach ($urls as $key => $value) {

            $url_info = parse_url($value->url);

            $up['domain'] = self::getDomain($value->url);

            $up['host'] = $url_info['host'];
            $up['md5'] = md5($value->url);

             DB::connection('mysql_url')->table('main2')->where('id',$value->id)->update($up);
         }



        // foreach ($urls as $key => $value) {

        //     $url = $value->url;

        //     $url_info = explode('/', $url);

        //     $index = count($url_info);

        //     if(empty($url_info[$index-1]))
        //     {
        //         $url = substr($url,0,strlen($url)-1);

        //         DB::connection('mysql_url')->table('main2')->where('id',$value->id)->update(['url'=>$url]);
        //     }
        // }

        dd(88);
        // dd($urls);



        // $url = 'http://15501864.1024sj.com';


        // $url_info = explode('/', $url);

        // dd($url_info);
        // $url_only = DB::connection('mysql_url_true')->table('mainold')->where('host','tv.sohu.com')->get();

        // foreach ($url_only as $key => $value) {
        //     # code...
        //     $md5 = md5($value->url);
        //     $url = DB::connection('mysql_url_true')->table('main')->where('md5',$md5)->first();
        //     $up['url'] = $value->url;
        //     $up['domain'] = $value->domain;
        //     $up['host'] = $value->host;
        //     $up['md5'] = $md5;

        //     if(empty($url))
        //     {
        //         DB::connection('mysql_url_true')->table('main')->insert($up);
        //     }else{
        //         DB::connection('mysql_url_true')->table('main')->where('md5',$md5)->updata($up);
        //     }
        // }
        $host_in = [
            'iqiyi.com',
            'pptv.com',
            'youku.com'
        ];

        $url_more = DB::connection('mysql_url_true')->table('mainold')->whereIn('domain',$host_in)->get();

        // dd($url_more);
        foreach ($url_more as $key => $value) {
            
            $md5 = md5($value->url);
            $url = DB::connection('mysql_url_true')->table('main')->where('md5',$md5)->first();
            $up1['url'] = $value->url;
            $up1['domain'] = $value->domain;
            $up1['host'] = $value->host;
            $up1['md5'] = $md5;

            if(empty($url))
            {
                DB::connection('mysql_url_true')->table('main')->insert($up1);
            }else{
                DB::connection('mysql_url_true')->table('main')->where('md5',$md5)->update($up1);
            }
        }

    }
    public function showDb(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $top_mainnew = DB::table('hubei_url')->get();


        // dd($top_mainnew);

        foreach ($top_mainnew as $key => $value) {
            # code...
            $domain = $value->website_domain;
            $md5 = md5($domain);
            // $website_record = $value->website_record;

            // dd($website_record);
            // $first_c = mb_substr($website_record,0,1,'utf-8');
            // $area_key = array_search($first_c, config('param.AREA_JIAN'));
            
            // DB::table('top_main_new')->where('id',$value->id)->update(['city'=>$area_key]);
            
            $da['unitname'] = $value->organizer_name;
            $da['websitename'] = $value->organizer_name;
            $da['company'] = $value->organizer_name;
            $da['unittype'] = $value->organizer_nature;
            $da['domain'] = $value->website_domain;
            $da['websiterecord'] = $value->website_record;
            $da['city'] = $value->city;
            $da['code'] = $value->code;
            $da['md5'] = $md5;
            $da['pcode'] = $value->pcode;
            $da['platform'] = 1;

            // $area_info = DB::table('areainfo')->where('name','like','%'.$value->city.'%')->first();

            // $code = empty($area_info->code)?"":$area_info->code;
            // $pcode = empty($area_info->pcode)?"":$area_info->pcode;



            // DB::table('top_main_new')->where('id',$value->id)->update(['code'=>$code,'pcode'=>$pcode]);


            // dd($area_info);

    //          +"organizer_name": "昆山市信源宏光学仪器有限公司"
    // +"organizer_nature": "企业"
    // +"website_record": "苏ICP备14036161号-5"
    // +"website_name": "昆山市信源宏光学仪器有限公司介绍"
    // +"website_url": "wddsnxn.org"
    // +"website_domain": "wddsnxn.org"
    // +"website_chief": "" 企业法人
    // +"audit_time": "2014-07-30" 备案时间
            // dd($domain);

            

            $topmain = DB::connection('mysql_topmain_true')->table('top_main')
            ->where('md5',$md5)->first();

            if(empty($topmain))
            {
                DB::connection('mysql_topmain_true')->table('top_main')->insert($da);
            }

            // dd($topmain);

        }

        dd('up top_main');




        $dcc_id = empty($request->dcc_id)?0:$request->dcc_id;
        $min_id = empty($request->min_id)?0:$request->min_id;
        $max_id = empty($request->max_id)?0:$request->max_id;

        // $mains = DB::connection('mysql_local_url')->table('main_new3')->select(DB::raw('count(*) as count,domain'))->groupBy('domain')->get();


        // foreach ($mains as $key => $value) {
        //     $count = $value->count;

        //     $domain = $value->domain;

        //     $limit = $count>100?100:$count;

        //     $urls = DB::connection('mysql_local_url')->table('main_new3')
        //     ->where('domain',$domain)->limit($limit)->get();


        //     foreach ($urls as $k => $v) {
        //         $item['md5'] = $v->md5;
        //         $item['url'] = $v->url;
        //         $item['domain'] = $v->domain;
        //         DB::connection('mysql_local_url')->table('main_new33')->insert($item);
        //     }
        //     // dd($urls);

        // }

        // dd('ok');






        //  $url = DB::connection('mysql_local_url')->table('main_new33')->where('id','>=',$min_id)->where('id','<=',$max_id)->get();


        //  foreach ($url as $key => $value) {

        //     $wh['md5'] = $value->md5;
        //     $da['domain'] = $value->domain;
        //     $da['host'] = $value->host;
        //     $da['url'] = $value->url;

        //     $main = DB::connection('mysql_url_true')->table('main')->where($wh)->first();

        //     if(empty($main))
        //     {
        //         $da['md5'] = $value->md5;
        //         DB::connection('mysql_url_true')->table('main')->insert($da);
        //     }else{
        //         // DB::connection('mysql_url_true')->table('main')->where($wh)->update($da);
        //     }
        // }


         // dd('up_domain');
        

        // dd($request->all());
        $url = DB::connection('mysql_url_true')->table('main')->where('id','>',$min_id)->where('id','<=',$max_id)->get();

        // dd($url);

         foreach ($url as $key => $value) {
            $wh['dcc_id'] = $dcc_id;
            $wh['task_id'] = 24;
            $wh['url_id'] = $value->id;


            $da['dcc_id'] = $dcc_id;
            $da['task_id'] = 24;
            $da['url_id'] = $value->id;
            $da['url'] = $value->url;

            $urls_queue = DB::connection('mysql_url_true')->table('urls_queue')->where($wh)->first();

            if(!empty($urls_queue))
            {
                DB::connection('mysql_url_true')->table('urls_queue')->where($wh)->update($da);
            }else{
                DB::connection('mysql_url_true')->table('urls_queue')->insert($da);
            }
            $task_wh['taskid'] = 24;
            $task_wh['urlid'] = $value->id;
            $task_wh['dccid'] = $dcc_id;

            $task = DB::connection('mysql_dtadbug_true')->table('tasksurls')->where($task_wh)->first();


            if(empty($task))
            {
                DB::connection('mysql_dtadbug_true')->table('tasksurls')->insert($task_wh);
            }
         }

         dd('ok');

        // $urls = DB::connection('mysql_url')->table('main2')->where('id','>=',$min_id)
        // ->where('id','<=',$max_id)->get();

        // foreach ($urls as $key => $value) {

        //     $md5 = $value->md5;
        //     $url = DB::connection('mysql_url_true')->table('main')->where('md5',$md5)->first();
        //     if(empty($url))
        //     {
        //         $ad['url'] = $value->url;
        //         $ad['domain'] = $value->domain;
        //         $ad['host'] = $value->host;
        //         $ad['md5'] = $value->md5;
        //         DB::connection('mysql_url_true')->table('main')->insert($ad);
        //     }

        //  }

        //  dd('ok');

        // $tables = DB::connection('mysql_116')->select('show tables');


        // foreach ($tables as $key => $value) {
        //     $rows = DB::connection('mysql_116')->table($value->Tables_in_resultdb)->select('url')->get();

        //     foreach ($rows as $k => $v) {
        //         $md5 = md5($v->url);

        //         $url_info = parse_url($v->url);

        //         $host = $url_info['host'];


        //         $host_www = explode('.', $host);

        //         $domain = '';

        //         if(stristr($host, 'www.')!==FALSE && $host_www[0]=='www')
        //         {
        //             $domain = str_replace('www.','',$host);
        //         }else{
        //             $domain = $host;
        //         }


        //         $up['url'] = $v->url;
        //         $up['domain'] = $domain;
        //         $up['host'] = $host;
        //         $up['md5'] = $md5;
        //         $url = DB::connection('mysql_url')->table('main_new')->where('md5',$md5)->first();

        //         if(!empty($url))
        //         {
        //             DB::connection('mysql_url')->table('main_new')->where('md5',$md5)->update($up);

        //         }else{
        //             DB::connection('mysql_url')->table('main_new')->insert($up);
        //         }
        //     }
        // }
        // dd($tables);
        // $tables = DB::select('show tables');
    }
    /**
     * 处理 116 table 里面的数据 到新的表里面
     * 方便 分配url 给指定的爬虫
     * finn 2017-12-18
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getTableInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $tables = DB::connection('mysql_116')->select('show tables');

        // dd($tables);

        foreach ($tables as $key => $value) {

            $rows = DB::connection('mysql_116')->table($value->Tables_in_resultdb2)->select('url')->get();

            foreach ($rows as $k => $v) {
                $md5 = md5($v->url);

                $url_info = parse_url($v->url);

                $host = $url_info['host'];
                $up['url'] = $v->url;
                $up['domain'] = self::getDomain($v->url);
                $up['host'] = $host;
                $up['md5'] = $md5;
                $url = DB::connection('mysql_url')->table('main_new3')->where('md5',$md5)->first();

                if(!empty($url))
                {
                    DB::connection('mysql_url')->table('main_new3')->where('md5',$md5)->update($up);

                }else{
                    DB::connection('mysql_url')->table('main_new3')->insert($up);
                }
            }
        }
        dd('ok');
        // dd($tables);
    }
    /**
     * 处理domain 字段
     * @return [type] [description]
     */
    public function upUrlDomain(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        // $min_id = empty($request->min_id)?0:$request->min_id;

        // $max_id = empty($request->max_id)?0:$request->max_id;

        // if(empty($min_id) || empty($max_id))dd('id is not null');

        // $urls = DB::connection('mysql_url')->table('main_new2')->select('url','id')->where('id','>=',$min_id)
        // ->where('id','<=',$max_id)->get();

        $urls = DB::connection('mysql_test')->table('hubei_url')->get();

        foreach ($urls as $key => $value) {

            // $url_info = parse_url($value->url);

            // $up['domain'] = self::getDomain($value->url);
            // $up['domain'] = self::getDomain();

            if(stristr($value->host, 'www.')===false)
            {
                $url = 'www.'.$value->host;
                // $up['host'] = $value->host;
                $up['md5'] = md5($url);
                $up['url'] = $url;

                DB::connection('mysql_test')->table('hubei_url')->where('id',$value->id)->update($up);
            }
           

             // DB::connection('mysql_url')->table('main_new2')->where('id',$value->id)->update($up);
         }
    }
    public function upComInfo()
    {
        // Company::where('socialCreditCode',$value['socialCreditCode'])->first();
        
        //http://api.shenjianshou.cn/?appid=4856d117ba3520c5e9f78a8ef1974d66&socialCreditCode=91310106132810291R 
        
        $company = Company::get();

        foreach ($company as $key => $value) {
            $socialCreditCode = $value['socialCreditCode'];
            $url = 'http://api.shenjianshou.cn/?appid=4856d117ba3520c5e9f78a8ef1974d66&socialCreditCode='.$socialCreditCode;


            $data = file_get_contents($url);
            $data_json = json_decode($data,true);

            // dd($data_json['data']);
            if(empty($data_json['data']))continue;

            $data = $data_json['data'];

            $updata['companyName'] = $data['companyName'];
            $updata['companyType'] = $data['companyType'];
            $updata['legalPersonName'] = $data['legalPersonName'];
            $updata['regCapital'] = $data['regCapital'];
            $updata['regDate'] = $data['regDate'];

            $updata['operatingFrom'] = $data['operatingFrom'];

            $updata['operatingEnd'] = $data['operatingEnd'];
            $updata['regOrgans'] = $data['regOrgans'];


            $updata['approvedDate'] = $data['approvedDate'];
            $updata['regStatus'] = $data['regStatus'];
            $updata['regAddress'] = $data['regAddress'];
            $updata['businessScope'] = $data['businessScope'];

            
            $updata['form'] = $data['form'];
            $updata['tel'] = $data['tel'];
            $updata['email'] = $data['email'];
            $updata['website'] = $data['website'];

            $updata['usedName'] = $data['usedName'];

            $updata['industry'] = $data['industry'];
            $updata['generalIndustry'] = $data['generalIndustry'];

            // dd($updata);
            Company::where('socialCreditCode',$socialCreditCode)->update($updata);

            // dd();
        }

    }
    /**
     * 获取 企业基本信息 
     * @return [type] [description]
     */
    public function getComInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        $total = 126944+1;


        $i = empty($request->i)?1:$request->i;


        $this->upComInfo();

        // $area = empty($request->area)?'':$request->area;

        // if(empty($area))dd('area is null');



         // $url = 'http://api.shenjianshou.cn/?appid=3c7d180cdd32ca81f7e995f6783d7712&companyName=%E4%B8%8A%E6%B5%B7&p='.($i);
            

         //    echo $url.'<br/>';

         //    $data = file_get_contents($url);
         //    $data_json = json_decode($data,true);



         //    if(empty($data_json['data']['companies']))continue;

         //    $companies = $data_json['data']['companies'];

         //    // dd($companies);

         //    echo $i.'<br/>';


         //    foreach ($companies as $key => $value) {
         //        $com = Company::where('socialCreditCode',$value['socialCreditCode'])->first();

         //        if(!$com)$com = new Company();

         //        $com->socialCreditCode = $value['socialCreditCode'];
         //        $com->companyName = $value['companyName'];
         //        $com->regAddress = $value['regAddress'];
         //        $com->province = $value['province'];
         //        $com->city = $value['city'];
         //        $com->county = $value['county'];
         //        $com->save();

         //    }

        // "socialCreditCode" => "310230000039672"
        // "companyName" => "上海桔园"
        // "regAddress" => "崇明县长兴岛前卫农场"
        // "province" => "上海市"
        // "city" => "上海市"
        // "county" => "-"

        // for ($i=1; $i < 100; $i++) { 
        //     $url = 'http://api.shenjianshou.cn/?appid=3c7d180cdd32ca81f7e995f6783d7712&companyName='.$area.'&p='.($i);
        //     $data = file_get_contents($url);
        //     $data_json = json_decode($data,true);


        //     if(empty($data_json['data']['companies']))continue;

        //     $companies = $data_json['data']['companies'];

        //     echo $i.'<br/>';


        //     foreach ($companies as $key => $value) {
        //         $com = Company::where('socialCreditCode',$value['socialCreditCode'])->first();

        //         if(!$com)$com = new Company();

        //         $com->socialCreditCode = $value['socialCreditCode'];
        //         $com->companyName = $value['companyName'];
        //         $com->regAddress = $value['regAddress'];
        //         $com->province = $value['province'];
        //         $com->city = $value['city'];
        //         $com->county = $value['county'];
        //         $com->save();

        //     }

        //     // sleep(30);
        //         // /company
        // }

        dd('is ok');
        
    }

    public function getEsBySql(Request $request)
    {

        set_time_limit(0);

        $test_domain = DB::table('test_main')->get();

        foreach ($test_domain as $key => $value) {


            $area = explode(',,', $value->child);
            
            $data['child'] = empty($area[0])?'':$area[0];
            // $data['child'] = empty($area[1])?'':$area[1];

            if(!empty($area[0]))
            {
                DB::table('test_main')->where('id',$value->id)->update($data);
            }

            
            
        }
        dd('ok');

        // $es_syno2 = DB::table('es_syno2')->get();

        // foreach ($es_syno2 as $key => $value) {
            

        //     $is_en = DBUtil::check_str_cn_en($value->esname);



        //     $url = 'testapi.adbug.cn/api/v3/addata/all?size=100&page=1&type=0&iscampaign=0&en_ch='.$is_en.'&isclik=0&suid=0&TOKEN=9a1323ac97bbd7fb3d9c7a9570538d6f&wd='.$value->esname;

        //     $url_d = HttpUtil::send_orc_curl_get($url);


        //     $url_data = json_decode($url_d,true);


        //     if($url_data['total']<100)
        //     {
        //         DB::table('es_syno2')->where('id',$value->id)->delete();
        //     }


        // }

        dd();

        // dd(config('param.ES_STATUS_DEBUG'));


        $rs = HttpUtil::send_orc_curl_get(config('param.ES_STATUS_DEBUG'));


        $rs_status = json_decode($rs,true);

        if($rs_status['status']=='red')
        {
            dd('es 47 dead');
        }



       $wd = empty($request->wd)?'':$request->wd;
       $size = empty($request->size)?10:$request->size;


        $sql = 'SELECT * FROM domain where host like "%'.$wd.'%" limit '.$size;

        $rs = HttpUtil::send_curl_post_sql(config('param.ES_URL_SQL'),$sql,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        dd($data);
    }



    /**
     * 获取 微信 广告
     * 判断是否是广告
     * @return [type] [description]
     */
    public function getWenXinData(Request $request)
    {

     

        $wx = DB::connection('mysql_adbug_mid')->table('wx_messages')
        ->select('id','content')->get();


        $is_ad_id = [];

        foreach ($wx as $key => $value) {
            $sim = $value->content;

            if(strstr($sim,'<expandOutsideTitle>'))
            {
                $str = strpos($sim, '<expandOutsideTitle>');

                $end = strpos($sim, '</expandOutsideTitle>');

                $sub_str = substr($sim, $str,$end);


                if(stristr($sub_str, '广告') || stristr($sub_str, '廣告'))
                {
                    $is_ad_id[] = $value->id;
                }

            }
           
        }

        $wx = DB::connection('mysql_adbug_mid')->table('wx_messages')->whereIn('id',$is_ad_id)->get();

        $cellData = [
            ['id','fromusername','tousername','content','url','status','createtime','date','meta']
        ];

        foreach ($wx as $key => $value) {
            $value = (array)$value;
            $item['id'] = $value['id'];
            $item['fromusername'] = $value['fromusername'];
            $item['tousername'] = $value['tousername'];
            $item['content'] = $value['content'];
            $item['url'] = $value['url'];
            $item['status'] = $value['status'];
            $item['createtime'] = $value['createtime'];
            $item['date'] = date('Y-m-d H:i:s',$value['createtime']);
            $item['meta'] = $value['meta'];
            array_push($cellData, $item);
        }

        Excel::create('Adbug 微信广告 详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');

        dd('ok');
    }

    /**
     * 临时 处理  main_inde type  platform 字段的值
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public  function mainIndexTmp(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $id = empty($request->id)?0:$request->id;

        if(empty($id))dd('id is not null');


        $limit = empty($request->limit)?10000:$request->limit;

        //ranks
        // $adTrack = DB::table('addata_rank_role')->where('id','>=','207269')->get();
        $mInfo = DB::table('main_index')
        ->join('addata','main_index.id','=','addata.id')
        ->select('addata.type','addata.attribute04','addata.platform','addata.material','main_index.index_id')
        ->where('main_index.index_id','<=',$id)
        ->orderBy('main_index.index_id','desc')
        ->limit($limit)
        ->get();
        // dd($mInfo);

        foreach ($mInfo as $key => $value) {

            $type = $value->type;

            $attribute04 = $value->attribute04;
            $material = $value->material;

            $index_id = $value->index_id;

            $platform = $value->platform;


            $up_item = [];

            switch ($type) {
                case 'image':
                    $up_item['type'] = 1;
                    break;
                case 'swf':
                    $up_item['type'] = 2;
                    break;
                case 'flv':
                    $up_item['type'] = 3;
                    break;
                case 'html5':
                    $up_item['type'] = 4;
                    break;
                
            }

            if(!empty($material) && $material=='native')$up_item['type'] = 5;

            if(!empty($platform))$up_item['platform'] = $platform;
            

            if(!empty($attribute04) && strtolower($attribute04)=='android')$up_item['platform'] = 3;
            if(!empty($attribute04) && strtolower($attribute04)=='ios')$up_item['platform'] = 4;


            if(!empty($up_item))
            {
                DB::table('main_index')->where('index_id',$index_id)->update($up_item);
            }
            
            
        }

        dd('ok');
    }

    /**
     * 临时处理 数据
     * @return [type] [description]
     */
    public  function addataRankTrackerTmp(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        //ranks
        // $adTrack = DB::table('addata_rank_role')->where('id','>=','207269')->get();
        $adTrack = DB::table('addata_rank_role')->get();
        foreach ($adTrack as $key => $value) {

            $tag_list = $value->trackers;
            $tag_list_new = '';

            $is_flag = false;
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
                    $is_flag = true;
                    
                }

            }
            if($is_flag)
            {
                $data['trackers_list'] = $tag_list_new;
                DB::table('addata_rank_role')->where('id',$value->id)->update($data);
            }
            
        }

        // $adTrack = DB::table('addata_rank_tracker_new_tmp')->get();

        // foreach ($adTrack as $key => $value) {
        //     $tags = $value->tag_list;

        //     $tags_list = explode(';', $tags);
        //     $addata = DB::table('addata_rank_role')->where('id',$value->ad_r_r_id)->first();

        //     foreach ($tags_list as $k => $v) {
        //         if($v)
        //         {
                    
        //             $rankTracker = RankTracker::where('tag_list',$v)->first();

        //             $score = '';
        //             if(!$rankTracker)
        //             {
        //                 $rankTracker = new RankTracker();
        //             }else{
        //                 $score = $rankTracker->score + $addata->score;
        //             }

        //             $rankTracker->ad_r_r_id = $addata->id;
        //             $rankTracker->tag_list = $v;
        //             $rankTracker->ad_id = $addata->ad_id;
        //             $rankTracker->score = $score;
        //             $rankTracker->date5 = $addata->date5;
        //             $rankTracker->created_date = $addata->created_date;
        //             $rankTracker->save();


        //         }
        //     }

        // }


    }
    /**
     * 临时 根据  时间
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAggsDataRank(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $data_range = [

        ];

        for ($i=1; $i < 32; $i++) { 
            $item['start_time'] = '2017-03-'.$i.' 00:00:00';
            $item['end_time'] = '2017-03-'.$i.' 23:59:59';
            $data_range[] = $item;
        }

        // print_r($data_range);
        // dd();

        foreach ($data_range as $key => $value) {

            $this->getAdsInfoBetween(strtotime($value['start_time']).'000',strtotime($value['end_time']).'000',100);
        }

        dd('0k');
    }

    /**
     * 导出信息
     * max_id_nums  每天最大设置不得超过这个值
     * size 分页 条数
     * end_time 结束时间
     * range_type 1 一天 2 一周 3 一月 默认 一周
     * @return [type] [description]
     */
    public function getAdsInfoBetween($start_time,$end_time,$max_id_nums)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

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

            // $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
            $url = config('param.ES_URL').config('param.ADDATA_AGG_SEARCH').'/'.config('param.ES_SEARCH');
            //ADDATA_AGG_SEARCH

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

        dd('ok');
        dd($data_d['data']);

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


        $before_day = date("Y-m-d",strtotime("-1 day"));

        $before_day_start_time = $before_day.' 00:00:00';

        $before_day_end_time = $before_day.' 23:59:59';


        $start_time = strtotime($before_day_start_time).'000';
        $end_time = strtotime($before_day_end_time).'000';

        //dd($before_day);
        //$start_time = empty($request->start_time)?'':$request->start_time;
        //$end_time = empty($request->end_time)?'':$request->end_time;

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
        // $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?scroll=2m';

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH').'?scroll=2m';

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

            // $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
            

            $url = config('param.ES_URL').config('param.ADDATA_AGG_SEARCH').'/'.config('param.ES_SEARCH');
            
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
        dd('ok');
    }

    /**
     * publisher_host
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getScoreByAdvertiser(Request $request)
    {
        set_time_limit(0);

        $type = empty($request->type)?'':$request->type;

        $table = '';

        switch ($type) {
            case '1':
                $table = 'publisher_host';
                break;
            case '2':
                $table = 'advertiser_host';
                break;
            case '3':
                # code...
                $table = 'trackers_host';
                break;
            
            default:
                # code...
                break;
        }

        $ad = DB::table($table)->get();

        $cellData = [
            ['host','score']
        ];

        foreach ($ad as $key => $value) {

            if($type==3)
            {
                if($value->host)
                {
                    $hosts_tmp = explode(';', $value->host);

                    $is_exist = false;
                    foreach ($hosts_tmp as $k => $v) {

                        if($v)
                        {
                            $domain = Domains::where(['host'=>$v])->first();

                            if($domain && $domain->role==3)
                            {
                                
                                foreach ($cellData as $ck => &$cv) {
                                    if(!empty($cv['host']) && $v==$cv['host'])
                                    {
                                        $cv['score'] = $cv['score']+$value->score;
                                        $is_exist = true;
                                        break;
                                    }
                                }
                                if(!$is_exist)
                                {
                                    $item['host'] = $v;
                                    $item['score'] = $value->score;
                                    $item['role'] = $type;
                                    array_push($cellData, $item);
                                }
                                $is_exist = false;
                            }
                        }
                        
                    }

                }
            }else{
                if($value->host)
                {
                    $domain = Domains::where(['host'=>$value->host])->first();

                    if($domain && $domain->role==$type)
                    {
                        $item['host'] = $value->host;
                        $item['score'] = $value->score;
                        $item['role'] = $type;
                        array_push($cellData, $item);
                    }
                }
            }
            
        }

        Excel::create('Adbug '.$table.' 详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');

    }


    public function addARRList($item,$role,$host,$range_type,$md5)
    {

        $adrrList = AdRRoleList::where(['role'=>$role,'host'=>$host,'range_type'=>$range_type,'md5'=>$md5])->first();

        $score = $item['score'];
        if($adrrList)
        {
            $score = $score + $adrrList->score;
        }else{
            $adrrList = new AdRRoleList();
        }
        $adrrList->score = $score;
        $adrrList->role = $role;
        $adrrList->host = $host;
        $adrrList->range_type = $range_type;
        $adrrList->md5 = $md5;
        $adrrList->created_date = $item['created_date'];
        $adrrList->save();
    }
    /**
     * 爱奇艺 信息
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getRandingAiQiYi(Request $request)
    {
        $curl_param = [
            'size'=>1000,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'platform'=>2
                            ]
                        ],
                        [
                            'term'=>[
                                'publisher_na'=>'com.qiyi.video'
                            ]
                        ],
                        [
                            'range'=>[
                                'created_date'=>[
                                    'gte'=>'1493568000000',//5-1
                                    'lte'=>'1494172799000'//5-7
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data_d = DBUtil::get_parse_es_data($rs);

        $cellData = [
            ['id','host','width','height','rank']
        ];
        
        foreach ($data_d['data'] as $key => $value) {
            $item['id'] = $value->id;
            $item['host'] = $value->publisher_na;
            $item['width'] = $value->width;
            $item['height'] = $value->height;
            $item['rank'] = ($value->width * $value->height)/(1280*720);
            array_push($cellData, $item);
        }

        Excel::create('Adbug爱奇艺 详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');
    }

    public function savefile2(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        // $cellData = [
        //     ['row_labels','count_of_score','sum_of_score','sum_of_times','sum_of_trackers_count','sum_of_servicenames','role']
        // ];
        Excel::load('/public/excels/20170110/giverole.xlsx', function($reader) {
            $data = $reader->toarray();

            $db = DB::connection('mysql');
            foreach ($data as $key => $value) {
                # code...
                
                $domain = $db->table('domains')->where(['host'=>$value['row_labels']])->first();

                $item['row_labels'] = $value['row_labels'];
                $item['count_of_score'] = $value['count_of_score'];
                $item['sum_of_score'] = $value['sum_of_score'];
                $item['sum_of_times'] = $value['sum_of_times'];
                $item['sum_of_trackers_count'] = $value['sum_of_trackers_count'];
                $item['sum_of_servicenames'] = $value['sum_of_servicenames'];
                $item['role'] = '';
                if($domain)
                {
                    $item['role'] = $domain->role;
                }
                $db->table('domain_advertiser')->insert($item);
                // dd($item);
                // array_push($cellData, $item);
            }
        });


        dd($cellData);

        // if(!empty($_FILES['file']['name']))
        // {
        //     $imgurl = Tools::fileUpload($_FILES['file']);


        //     dd($imgurl);
        //     if(!file_exists("images/uploads/".$imgurl))return redirect()->back()->withErrors(['msg'=>'该文件不存在,请重新上传'],'store');
        //     $fileinfo = pathinfo("images/uploads/".$imgurl);


        //     $excel_arr = ['xlsx','xls'];

        //     if(!in_array($fileinfo['extension'], $excel_arr))
        //     {
        //         dd('请上传xls格式的文件');
        //     }

        //     Excel::load("images/uploads/".$imgurl, function($reader) {
        //         $data = $reader->toarray();

        //         dd($data);
        //     });
        // }
    }

    /**
     * 拆分 跟踪者  获取 对应的分数
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function savefileTrasker(Request $request)
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        Excel::load('/public/excels/20170110/tracker_score2.xlsx', function($reader) {
            $data = $reader->toarray();

            // dd($data);
            $db = DB::connection('mysql');
            foreach ($data as $key => $value) {

                if(!empty($value['trackers']))
                {
                    $score = $value['score_times'];
                    $trackers = explode(';', $value['trackers']);
                    foreach ($trackers as $k => $v) {

                        if($v)
                        {
                            $dt = $db->table('domain_trackers')->where('host',$v)->first();

                            if($dt)
                            {
                                $score = $score + $dt->score;
                                $db->table('domain_trackers')->where('host',$v)->update(['score'=>$score]);
                            }else{
                                $domain = $db->table('domains')->where(['host'=>$v])->first();
                                $item['score'] = $score;
                                $item['host'] = $v;
                                if($domain)
                                {
                                    $item['role'] = $domain->role;
                                }
                                $db->table('domain_trackers')->insert($item);
                            }
                            
                        }
                        # code...
                    }

                }
            }
        });


        dd('ok');
    }
    /**
     * 小马 测试接口 
     * 返回 输入的内容
     * @return [type] [description]
     */
    public function getSendResult(Request $request)
    {
        header('Access-Control-Allow-Origin:*');
        
        $wd = empty($request->args)?'':$request->args;

        if(empty($wd))return json_encode(['data'=>[],'status'=>200,'smg'=>'请输入有效内容']);

        return json_encode(['data'=>$wd,'status'=>200]);

    }
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        // $token_arr = [
        //     ['url'=>'http://www.adbug.cn/home/subject/index/p/','token'=>'eb41b3a072941c81c9a07eaacd403222'],
        //     ['url'=>'http://www.adbug.cn/home/subject/index/p/','token'=>'e1791591f63256eb4df01c4bc76b3a01']
        // ];
        // if(!empty($token_arr))
        // {
        //     $table = env('DB_OC_TABLE')?env('DB_OC_TABLE'):'oc_ul_token';
            
        //     foreach ($token_arr as $key => $value) {
        //        // $sql = 'insert into '.$table.'(url,token) value("'.$value['url'].'","'.$value['token'].'")';
        //        $db = DB::connection('mysql_oc_true')->table($table);

        //         $is_token = $db->where($value)->first();

        //         if(!$is_token)
        //         {
        //             $db->insert($value);
        //         }
        //     }
        // }


        // $dd = '%E5%AE%89%E5%8D%93-%E5%BC%80%E5%B1%8F--%E5%A4%A9%E5%A4%A9%E5%BF%AB%E6%8A%A5%EF%BC%88%E7%94%B5%E5%BD%B1%EF%BC%89%EF%BC%88%E6%8E%A7%E9%87%8F%EF%BC%89';


        // dd(urldecode($dd));
        return view('test.index');
    }
}
