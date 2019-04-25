<?php

namespace App\Http\Controllers\test;

use Illuminate\Http\Request;
use App\Http\Controllers\TestController as Controller;
use App\Http\Utils\ESUtil;


use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;

use Elasticsearch\Client;

use Illuminate\Http\Response;
use Search;
use Excel;

use DB;
use App\SubjectTitle;
use App\SubjectAds;



/**
 *  1 获取 所有 域名 
 *  2 domians sort 排序
 *  3 getPubTacAdsIndex 广告主 媒体 追踪者 index  接口
 */

class ApiController extends Controller
{
    
    public function datars()
    {
        dd('test 404');
    }
    /**
     * 华治数聚 接口
     * @return [type] [description]
     */
    public function huazhi(Request $request)
    {
        return view('test.huazhi.index');
    }
    public function getDomainBySql(Request $request)
    {
        $host = empty($request->host)?'':$request->host;

        $sql = "select * from domain where host like '%".$host."' or cname like '%".$host."'";


        // dd($sql);


        $url = 'http://127.0.0.1:9200/_sql';

        $rs = HttpUtil::send_curl_post_sql($url,$sql);

        $data_d = DBUtil::get_parse_es_data($rs);
        
        dd($data_d);
    }
    /**
     * 根据 subject_title 获取 addata信息
     * @return [type] [description]
     */
    public function getAddataByMd5(Request $request)
    {
        ini_set('memory_limit', '1024M');

        set_time_limit(0);
        $subjecttitel = SubjectTitle::get();

        $subject_data = [];

        $subject_md5_tmp = [];

        // 获取 总面积 和平均面积 总数
        foreach ($subjecttitel as $key => &$value) {

            $subject_title = $value['name'];

            $subject_md5 = $value['md5'];

            $subject_md5_tmp[] = $subject_md5;

            $item['subject_title'] = $subject_title;

            $item['subject_md5'] = $subject_md5;


            $item['platform'] = 0;

            $item['allarea'] = 0;
            $item['allnum'] = 0;

            $item['allratio'] = 0;

            $item['md5plat'] = md5($subject_md5);


            $curl_param = [
                'size'=>0,
                'query'=>[
                    'term'=>[
                        'subject_md5'=>$subject_md5
                    ]
                ],
                'aggs'=>[
                    'platform'=>[
                        'terms'=>[
                            'field'=>'platform'
                        ],
                        'aggs'=>[
                            'allarea'=>[
                                'sum'=>[
                                    'field'=>'wharea'
                                ]
                            ],
                            'allnum'=>[
                                'cardinality'=>[
                                    'field'=>'id'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");


            $rs_data = json_decode($rs,true);


            if(!empty($rs_data['aggregations']['platform']['buckets']))
            {
                $rs_platform = $rs_data['aggregations']['platform']['buckets'];


                foreach ($rs_platform as $key => $value) {
                  
                    if($value['key'])
                    {
                        $item['platform'] = $value['key'];
                        $allarea = empty($value['allarea']['value'])?0:$value['allarea']['value'];
                        $allnum = empty($value['allnum']['value'])?0:$value['allnum']['value'];
                        $item['allarea'] = $allarea;
                        $item['allnum'] = $allnum;

                        $item['allratio'] = $allnum?$allarea/$allnum:0;

                        $item['md5plat'] = md5($subject_md5.$value['key']);

                        $subject_data[] = $item;

                    }
                    
                }
            }else{
                $subject_data[] = $item;
            }

            unset($item);
        }



        // 第二步 获取具体数据  计算 每条创意平均 面积

        $_source = 'id,whdivided,wharea,hwdivided,subject_md5,title,platform,type,date5,advertiser_full,publisher_full,tracker_full';

        $ads_data_tmp = [];
        foreach ($subject_md5_tmp as $key => $value) {

            $curl_param = [
                'size'=>1000,
                'query'=>[
                    'term'=>[
                        'subject_md5'=>$value
                    ]
                ]
            ];



            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source='.$_source;

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $data_detail = DBUtil::get_parse_es_data($rs);

            if(!empty($data_detail['data']))
            {
                foreach ($data_detail['data'] as $k => $v) {
                    $ads_data_tmp[] = $v;
                }
            }
            
            
        }


        foreach ($ads_data_tmp as $key => &$value) {
          //  $$item['md5plat'] = md5($subject_md5.$value['key']);
            $md5plat = md5($value->subject_md5.$value->platform);

            foreach ($subject_data as $sk => $sv) {

               if(array_search($md5plat, $sv))
               {
                    $value->allarea = $sv['allarea'];
                    $value->allnum = $sv['allnum']; 
                    if($sv['allratio'])
                    {
                        $value->avgarea = ($value->wharea/$sv['allratio']);
                    }else{
                        $value->avgarea = 0;
                    }
                    break;
               }
            }
        }
        // 第三步 计算每条 和 总面积 比例 
        // dd($ads_data_tmp);


        $cellData = [
            [
                'id','title','platform','wharea','allarea','allnum','avgarea','subject_md5','type','advertiser_full','publisher_full','tracker_full','date5'
            ]
        ];

        foreach ($ads_data_tmp as $key => $value) {
            $value = (array)$value;

            // dd($value);
            // $item['advertiser']=$value['advertiser'];
            $item['ads_id']=$value['id'];
            $item['title']=$value['title'];
            $item['platform']=$value['platform'];
            $item['wharea'] = $value['wharea'];
            $item['allarea']=$value['allarea'];
            $item['allnum']=$value['allnum'];
            $item['avgarea']=$value['avgarea'];
            $item['subject_md5']=$value['subject_md5'];            
            $item['type']=$value['type'];
            $item['advertiser_full']=$value['advertiser_full'];
            $item['publisher_full']=$value['publisher_full'];

            $trackers = '';
            if(is_array($value['tracker_full']))
            {
                $trackers = implode(',', $value['tracker_full']);
            }else{
                $trackers = $value['tracker_full'];
            }
            $item['tracker_full']= $trackers;
            
            $item['date5']=$value['date5'];

            // DB::table('subject_ads')->insert($item);

            // unset($item);


            array_push($cellData, $item);
        }

        // DB::table('subject_ads')->insert($cellData);

        // dd('ok');

        Excel::create('Adbug 活动 详细',function($excel) use ($cellData){
          $excel->sheet('subject', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');

    }
    public function updateSubjectTitle(Request $request)
    {
        $subject_title = DB::table('subject_title')->get();

        foreach ($subject_title as $key => $value) {
            # code...
            $name = $value->name;
            $subject = DB::table('subjects')->where('title',$name)->first();

            if($subject)
            {
                $subtitle = SubjectTitle::find($value->id);

                $subtitle->md5 = $subject->md5;

                $subtitle->subject_id = $subject->id;
                $subtitle->save();

            }

        }


    }

    public function readXmlData(Request $request)
    {

        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $xml_uri= 'http://'.$_SERVER['HTTP_HOST'].'/images/uploads/setting.xml';


        $xml = simplexml_load_file($xml_uri);//调用该文件
        $xmlData = [];//定义数组

        foreach ($xml as $key => $value) {
        
            $xmlData = (array)$value;
        }

        $host_data = [];
        $dd = [];
        // 获取 所有 xml  item 节点
        foreach ($xmlData as $key => $value) {
   
            foreach ($value as $k => $v) {

                $v = (array)$v;


                $name = empty($v['name'])?'':$v['name'];

                $host = empty($v['host'])?'':$v['host'];

                if($host)
                {
                    $item['name'] = $name;
                    $item['host'] = $host;

                    $host_data[]=$item;
                }
            }
          
        }

        // DB::beginTransaction();

        $flag = true;

        foreach ($host_data as $key => $value) {

            $domain = DB::table('domains')->where(['host'=>$value['host']])->first();

            $udata['cname'] = $value['name'];

            if($domain)
            {

                if(!DB::table('domains')->where(['host'=>$value['host']])->update($udata))
                {
                   $flag = false;
                   // DB::rollback();
                }

            }else{
                $udata['host'] = $value['host'];
                $udata['md5'] = md5($value['host']);
                if(!DB::table('domains')->insert($udata))
                {
                    $flag = false;
                    // DB::rollback();
                }
            }

            unset($udata);
            // if(!$flag)
            // {
            //     break;
            // }

        }

        // if($flag)
        // {
        //     DB::commit();
        //     dd('is ok');
        // }else{
        //     dd('is no ok');
        // }

    }
    /**
     * 根据广告主 获取 最近半年的跟踪者 信息
     * 包括 title advertisers trackers_full platfrom date5
     * 2017-1-21
     * @return [type] [description]
     */
    public function getNowTraByAds(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $min_id = empty($request->min_id)?10674234:$request->min_id;
        $max_id = empty($request->max_id)?'':$request->max_id;


        // $curl_param = [
        //     'size'=>0,
        //     'query'=>[
        //         'bool'=>[
        //             'must'=>[
        //                 [
        //                     'range'=>[
        //                         'date5'=>[
        //                             'gte'=>'2016-06-01',
        //                             'lte'=>'2016-12-31'
        //                         ]
        //                     ]
        //                 ],
        //                 [
        //                     'term'=>[
        //                         'type'=>'flv'
        //                     ]
        //                 ],
        //                 [
        //                     'exists'=>[
        //                         'field'=>'title'
        //                     ]
        //                 ]//,
        //                 // [
        //                 //     'range'=>[
        //                 //         'id'=>[
        //                 //             'gte'=>$min_id,
        //                 //             'lte'=>$max_id
        //                 //         ]
        //                 //     ]
        //                 // ]
        //             ]
        //         ]
        //     ],
        //     'aggs'=>[
        //         'advertiser'=>[
        //             'terms'=>[
        //                 'field'=>'advertiser',
        //                 'size'=>0,
        //                 'order'=>[
        //                     'maxdate'=>'desc'
        //                 ]
        //             ],
        //             'aggs'=>[
        //                'maxdate'=>[
        //                     'max'=>[
        //                         'field'=>'date5'
        //                     ]
        //                 ],
        //                 'trackers'=>[
        //                     'terms'=>[
        //                         'field'=>'trackers',
        //                         'size'=>9000
        //                     ],
        //                     'aggs'=>[
        //                         'id'=>[
        //                             'terms'=>[
        //                                 'field'=>'id',
        //                                 'size'=>5000
        //                             ]
        //                         ]
        //                     ]
        //                 ]
        //             ]
        //         ]
        //     ]
        // ];

        

        // $url = config('param.ES_URL').'addata_v15/'.config('param.ES_SEARCH');

        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $rs_parse = json_decode($rs,true);

        // $advertiser = $rs_parse['aggregations']['advertiser']['buckets'];

        // $advertiser_tmp = [];

        // foreach ($advertiser as $key => $value) {
        //     if($value['key'] && !in_array($value['key'], ['com','cn']))
        //     {
        //         $ads_item['advertiser'] = $value['key'];

        //         if(!empty($value['trackers']['buckets']))
        //         {
        //             foreach ($value['trackers']['buckets'] as $tk => $tv) {
        //                $ads_item['trackers'] = $tv['key'];

        //                if(!empty($tv['id']['buckets']))
        //                {
        //                     foreach ($tv['id']['buckets'] as $ik => $iv) {
        //                         $ads_item['id'] = $iv['key'];
        //                         $advertiser_tmp[] = $ads_item;

        //                     }
        //                }else{
        //                     $ads_item['id'] = '';
        //                     $advertiser_tmp[] = $ads_item;
        //                }
        //            }
        //         }else{
        //             $ads_item['trackers']='';
        //             $ads_item['id'] = '';
        //         }
        //         $advertiser_tmp[] = $ads_item;
        //     }
            
        // }


        // $advertiser_tmp_title = [];

        // $advertiser_tmp_filter = [];
        // foreach ($advertiser_tmp as $key => &$value) {
        //     if($value['id'])
        //     {
        //         $curl_param = [
        //             'query'=>[
        //                 'term'=>[
        //                     'id'=>$value['id']
        //                 ]
        //             ]
        //         ];
        //         $url = config('param.ES_URL').'addata_v15/'.config('param.ES_SEARCH').'?_source=id,title,platform,date5,type';

        //         $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        //         $data = DBUtil::get_parse_es_data($rs);


        //         if(!empty($data['data'][0]->title))
        //         {

        //             $md5 = md5($data['data'][0]->title.$value['trackers'].$value['advertiser']);

        //             if(!in_array($md5, $advertiser_tmp_title))
        //             {
        //                 $advertiser_tmp_title[] = $md5;

        //                 $itme_filter['trackers'] = $value['trackers'];
        //                 $itme_filter['advertiser'] = $value['advertiser'];
        //                 $itme_filter['date5'] = empty($data['data'][0]->date5)?'':$data['data'][0]->date5;
        //                 $itme_filter['title'] = empty($data['data'][0]->title)?'':$data['data'][0]->title;
        //                 $itme_filter['platform'] = empty($data['data'][0]->platform)?'':$data['data'][0]->platform;
        //                 $itme_filter['type'] = empty($data['data'][0]->type)?'':$data['data'][0]->type;
        //                 $advertiser_tmp_filter[] = $itme_filter;
        //             }
                    
        //         }else{
        //             unset($advertiser_tmp[$key]);
        //         }

        //     }
        // }
        // 
         $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>'2016-06-01',
                                    'lte'=>'2016-12-31'
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                'type'=>'flv'
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'title'=>[
                    'terms'=>[
                        'field'=>'subject_title',
                        'size'=>0,
                        'order'=>[
                            'maxdate'=>'desc'
                        ]
                    ],
                    'aggs'=>[
                       'maxdate'=>[
                            'max'=>[
                                'field'=>'date5'
                            ]
                        ],
                        'trackers'=>[
                            'terms'=>[
                                'field'=>'trackers',
                                'size'=>0
                            ],
                            'aggs'=>[
                                'id'=>[
                                    'terms'=>[
                                        'field'=>'id',
                                        'size'=>5000
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $url = config('param.ES_URL').'addata_v15/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_parse = json_decode($rs,true);

        $title = $rs_parse['aggregations']['title']['buckets'];

        $title_tmp = [];

        foreach ($title as $key => $value) {
            if($value['key'])
            {
                $ads_item['title'] = $value['key'];

                if(!empty($value['trackers']['buckets']))
                {
                    foreach ($value['trackers']['buckets'] as $tk => $tv) {
                       $ads_item['trackers'] = $tv['key'];
                       
                       if(!empty($tv['id']['buckets']))
                       {
                            foreach ($tv['id']['buckets'] as $ik => $iv) {
                                $ads_item['id'] = $iv['key'];
                                $title_tmp[] = $ads_item;
                            }
                       }else{
                            $ads_item['id'] = '';
                            $title_tmp[] = $ads_item;
                       }
                   }
                }else{
                    $ads_item['title'] = '';
                    $ads_item['trackers']='';
                    $ads_item['id'] = '';
                }
                $title_tmp[] = $ads_item;
            }
            
        }

        $advertiser_tmp_title = [];

        $advertiser_tmp_filter = [];
        foreach ($title_tmp as $key => &$value) {
            if($value['id'])
            {
                $curl_param = [
                    'query'=>[
                        'term'=>[
                            'id'=>$value['id']
                        ]
                    ]
                ];
                $url = config('param.ES_URL').'addata_v15/'.config('param.ES_SEARCH').'?_source=id,title,platform,date5,type';

                $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
                $data = DBUtil::get_parse_es_data($rs);


                if(!empty($data['data'][0]->title))
                {

                    $md5 = md5($data['data'][0]->title.$value['trackers']);

                    if(!in_array($md5, $advertiser_tmp_title))
                    {
                        $advertiser_tmp_title[] = $md5;

                        $itme_filter['trackers'] = $value['trackers'];
                        $itme_filter['date5'] = empty($data['data'][0]->date5)?'':$data['data'][0]->date5;
                        $itme_filter['title'] = $value['title'];
                        $itme_filter['platform'] = empty($data['data'][0]->platform)?'':$data['data'][0]->platform;
                        $itme_filter['type'] = empty($data['data'][0]->type)?'':$data['data'][0]->type;
                        $advertiser_tmp_filter[] = $itme_filter;
                    }
                    
                }else{
                    unset($title_tmp[$key]);
                }

            }
        }
        $cellData = [
            [
                'title','trackers','date','platform','type'
            ]
        ];

        foreach ($advertiser_tmp_filter as $key => $value) {
            // $item['advertiser']=$value['advertiser'];
            $item['title']=$value['title'];
            $item['trackers'] = $value['trackers'];
            $item['date']=$value['date5'];
            $item['platform']=$value['platform'];
           
            $item['type']=$value['type'];
            array_push($cellData, $item);
        }
        Excel::create('Adbug 广告主 跟踪者 详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');

    }

    /**
     * 获取 domain 所有 信息 
     * type 0 返回所有数据 1 简化数据
     * wd 搜索内容
     * @return [type] [description]
     */
    public function getAll(Request $request)
    {

        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'ip'=>'180.173.167.178'
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $url = 'http://121.43.166.146:9200/camp_1001/_search';

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_data = json_decode($rs,true);

        dd($rs_data['hits']['hits']);

        dd($rs);
        
        // $data = DBUtil::get_parse_es_data($rs);

        // dd($data);
        // 

        // echo 'start_time:';
        // echo date('Y-m-d H:i:s');
        
        // $size = empty($request->size)?config('param.SIZE'):$request->size;
        // $sort = empty($request->sort)?'desc':$request->sort;
        // $type = empty($request->type)?'':$request->type;
        // $param = empty($request->wd)?'':$request->wd;
        // $param = urldecode($param);
        // $removeParam = empty($request->rwd)?"":$request->rwd;

        // $page = empty($request->page)?1:$request->page;

        // $from = 0;

        // if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        
        // if(($type && !is_numeric($type)))
        // {
        //     $data['status'] = 206;
        //     $data['data'] = [];
        //     return HttpUtil::retrun_json($data,200);
        // }

        // if($page>1)$from = $size * $page;


        //  $curl_param = [
        //         'from'=>$from,
        //         'size'=>$size,
        //         'query'=>[
        //             'function_score'=>[
        //                 'filter'=>[
        //                     'bool'=>[
        //                         'should'=>[
        //                             'multi_match'=>[
        //                                 'query'=>$param,
        //                                 'fields'=>[
        //                                     'title',
        //                                     'advertiser_name',
        //                                     'advertiser_name_title'
        //                                 ]
        //                             ]
        //                         ]
        //                     ]
        //                 ],
        //                 'functions'=>[
        //                     [
        //                         'filter'=>[
        //                             'match_phrase'=>[
        //                                 'advertiser_name_title'=>[
        //                                   'query'=>$param,
        //                                   'minimum_should_match'=>'100%'
        //                                 ]
        //                             ]
        //                         ],
        //                         "weight"=>'4'
        //                     ],
        //                     [
        //                         'filter'=>[
        //                             'match'=>[
        //                                 'title'=>[
        //                                   'query'=>$param,
        //                                   'minimum_should_match'=>'100%'
        //                                 ]
        //                             ]
        //                         ],
        //                         "weight"=>'2'
        //                     ],
        //                     [
        //                         'filter'=>[
        //                             'match_phrase'=>[
        //                                 'advertiser_name'=>$param
        //                             ]
        //                         ],
        //                         "weight"=>'10'
        //                     ],
        //                     [
        //                         'script_score'=>[
        //                             'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
        //                         ]
        //                     ]
        //                 ],
        //                 'score_mode'=>'sum',
        //                 // 'max_boost'=>'10'
        //             ],
        //         ],
        //          'sort'=>[
        //                 [
        //                     '_score'=>[
        //                         'order'=>'desc'
        //                     ]
        //                 ],
        //                 [
        //                     'date5'=>[
        //                         'order'=>'desc'
        //                     ]
        //                 ]
        //             ]
        //     ];

        // if(empty($param))unset($curl_param['query']);


        // // dd(json_encode($curl_param));


        // $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');


        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        
        // $data = DBUtil::get_parse_es_data($rs);

        // // $data = $this->getadvertiserDomain($data);


        // // 暂无数据 2002
        // if(empty($data['data']))
        // {
        //     $data['status'] = 203;
        //     $data['data'] = [];
        //     return HttpUtil::retrun_json($data,200);
        // }
        // $redis_rs = [];
        // $data['status'] = 200;

        // echo '<br/>';

        // echo 'edn_time:';
        // echo date('Y-m-d H:i:s:u');

        // dd($data);
        // return HttpUtil::retrun_json($data,200);


    }

     public function getadvertiserDomain($data)
    {

        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            
            $value = (array)$value;
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
                                    'query'=>$wd//,
                                    // 'boost'=>3
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

     

        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH_4'),$curl_param);


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

            shuffle($mi_arr);
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
            shuffle($ads_tmp);
            $v['subject_ads'] = $ads_tmp;

        }
        $data['data'] = $sub_data_tmp;
        $data['total'] = count($sub_data_tmp);


        
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


        // 是否随机
        $random = empty($request->random)?'':$request->random;


            
        if(empty($field)||empty($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        $url_query[$field] = $value;
        if($type)$url_query['type'] = $type;

        if($platform)$url_query['platform'] = $platform;

        $size_all = $page * $size;

        $all_random = $size_all * rand(1,4);

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
                        'size'=>$all_random,
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

        if(empty($data[$groupBy]))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200); 

        // if($random==1)shuffle($data_new['data']);

        if($random)shuffle($data[$groupBy]);

       
        $charsLen = count($data[$groupBy]) - 1; 
        // shuffle($chars);   
        $output = []; 
        for ($i=0; $i<$size_all; $i++) 
        { 
            $output[] = $data[$groupBy][mt_rand(0, $charsLen)]; 
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
            $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);
            $rs_de = json_decode($rs,true);

            $data_new['data'][] = $rs_de['hits']['hits'][0]['_source'];
        }

        // 暂无数据 2002
        // if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        if(empty($data_new['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);


        if($random==1)shuffle($data_new['data']);
        
        $data_new['status'] = 200;
        $data_new['total'] = $total;
        // $data['status'] = 200;

        return HttpUtil::retrun_json($data_new,200);
    }


    public function GetRandStr($len) 
    { 


        $file = file_get_contents('allB5.json');

        $chars = json_decode($file,true);

        $charsLen = count($chars) - 1; 
        // shuffle($chars);   
        $output = []; 
        for ($i=0; $i<$len; $i++) 
        { 
            $output[] = $chars[mt_rand(0, $charsLen)]; 
        }  
        return $output;  
    } 



     public function getAdsBySize()
    {
        // 随机 获取 广告品牌 7条数据
        
        $arr = $this->GetRandStr(10);

        $size = empty($request->size)?50:$request->size;
        
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
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
            'aggs'=>[
                'subject_title'=>[
                    'terms'=>[
                        'field'=>'subject_title',
                        'size'=>35,
                        'order'=>[
                            [
                                'max_wharea'=>'desc'
                            ],
                            [
                                'max_score'=>'desc'
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'max_score'=>[
                            'max'=>[
                                'script'=>'_score'
                            ]
                        ],
                        'max_wharea'=>[
                            'max'=>[
                                'field'=>'wharea'
                            ]
                        ]
                    ]
                ]
            ]
        ];


        foreach ($arr as $key => $value) {
            $item = [
                'match'=>[
                    'subject_title'=>[
                        'query'=>$value['name']
                    ]
                ]
            ];
            array_push($curl_param['query']['bool']['should'], $item);
        }


        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);

        $rs_parse = json_decode($rs,true);


        $subject_title_tmp = empty($rs_parse['aggregations']['subject_title']['buckets'])?'':$rs_parse['aggregations']['subject_title']['buckets'];
       

       
        // $edition = [];
        // foreach ($new_data as $k => $v) {

        //   $edition[] = $v['date5'];

        // }
        // array_multisort($edition, SORT_DESC, $new_data);

        

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


   

        
    /**
     * {"query":{"bool":{"must":[{"term":{"domain":"com.hunantv.imgo.activity"}},{"term":{"type":"flv"}},{"term":{"platform":"2"}}],"must_not":[],"should":[]}},"from":0,"size":10,"sort":[],"aggs":{}}
     * @return [type] [description]
     */
    public function deleteJDB(Request $request)
    {
        
        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'doman'=>'com.hunantv.imgo.activity'
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


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);


        foreach ($data as $key => $value) {
            print_r($value);
        }



    }
    /**
     * 最新广告 【实时最新广告】
     * finn
     * 20160705
     * @param  size 显示数据数量
     * @param last_id 最后 一条数据 id 编号
     */
    protected function getRecentAds(Request $request)
    {

        return view('test.ads.newads');

    }

    public function getRecentAdsTest(Request $request)
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

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;
        

        return HttpUtil::retrun_json($data,200);
    }

   
    public function getTopTrackerAds()
    {
        return view('test.ads.topads');
    }

    /**
     * 每周跟踪排行
     * fyj
     * 20160630
     */
    protected function getTopTrackerAdsTest(Request $request)
    {

        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($sort && !DBUtil::sortValied($sort))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);


        $curl_param = [
            'sort'=>[
                'tracker_advertiser'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size
        ];
        


        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
    }

    public function getRandomAds()
    {
        return view('test.ads.random');
    }
    /**
     * 随机 获取 广告
     * fyj
     * 20160630
     */
    protected function getRandomAdsTest(Request $request)
    {
        $rand_id = rand(1, 10000);

        $sort = empty($request->sort)?'desc':$request->sort;

        $size = empty($request->size)?rand(1,1000):$request->size;

        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if($sort && !DBUtil::sortValied($sort))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>$sort
                ]
            ],
            'size'=>$size,
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


        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }

    public function getRecentMobileAds()
    {
        return view('test.ads.newmobile');
    }
    /**
     * 最新 移动 广告
     * platform 1 pc 2 移动 0 所有平台
     */
    protected function getRecentMobileAdsTest(Request $request)
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
                'id'=>[
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

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data'])) return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
        
    }

    public function getRecentVideoAds()
    {
        return view('test.ads.video');
    }

    /**
     * 最新 视频广告
     * type  1 图片 2 视频 3 flush 0 所有
     * limit 100
     * type 　视频　图片　flush
     */
    protected function getRecentVideoAdsTest(Request $request)
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
                'id'=>[
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

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH').'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data'])) return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }



   
}
