<?php

namespace App\Http\Controllers\test;

use Illuminate\Http\Request;

use App\Http\Controllers\TestController as Controller;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use Search;
USE App\Http\Utils\DBUtil;
use Elasticsearch\Client;
use DB;
use Excel;
use App\Synonym;
use Mail;

/**
 * finn
 * 20170706
 * home test api
 */
class AddataController extends Controller
{


    public function getAllIdsTest(Request $request)
    {
        
        $url = '127.0.0.1:9200/fenci/'.config('param.ES_SEARCH').'?scroll=2m';

        $size = 2;


        $curl_param = [
            'size'=>2,
            '_source'=>[
                'id'
            ]
        ];
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $return_id = [];
        $data = DBUtil::get_parse_es_data_id($rs);


        foreach ($data['data'] as $key => $value) {
            $return_id[] = $value;
        }
        

        $scrollId = $data['scroll_id'];

        if($data['total']>$size)
        {
            if($data['total']%$size==0)
            {
                $j = $data['total']/$size;
            }else{
                $j = intval($data['total']/$size)+1;
            }
            
        }

        for ($i=1; $i < $j; $i++) { 
            $url = '127.0.0.1:9200/'.config('param.ES_SEARCH').'/scroll?scroll=2m';

            $curl_param_scroll = [
                'scroll_id'=>$scrollId
            ];
            $rs = HttpUtil::send_curl_methods($url,$curl_param_scroll,"POST");
            $data = DBUtil::get_parse_es_data_id($rs);
            // $return_id[] = $data['data'];
            $scrollId = $data['scroll_id'];

            foreach ($data['data'] as $key => $value) {
                $return_id[] = $value;
            }
        }

        dd($return_id);
    }

    public function exportDomainApptest(Request $request)
    {

        return view('test.api.40');
    }
    /**
     * 2017-2-15
     * domain app 域名 
     * finn
     * 导出 新增 34 个域名 相关信息
     *
     * finn 2017-2-21 新增  统计对应的创意数
     * @return [type] [description]
     */
    public function exportDomainApp(Request $request)
    {
        $cellData = [
            [
                'App','域名','公司','月活跃MAU(万)','MAU同比增长率','总使用时长(万分钟)','高价值用户比例','链接地址','广告创意数'
            ]
        ];
        //http://www.ocadbug.cn/home/publisher/detail/p/a3aef48b92d9927bcc00b64ec0b65b97&token=49a62c03b4024e2f4a7e4bf730f12328#!subject
       $domains = DB::table('domain_app_desc')->get();

       $url_base = 'http://www.adbug.cn/home/publisher/detail/p/';


       $start_time = empty($request->start_time)?'':$request->start_time;

       $end_time = empty($request->end_time)?'':$request->end_time;


       $token_url = [];

       foreach ($domains as $key => &$value) {

            $url = $url_base.md5($value->host);


            // finn 2017-2-21 新增 

            $ads = 0;
            if($value->host)
            {
                 $curl_param = [
                    'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        'publisher'=>$value->host
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
                        'ads'=>[
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ]
                    ]
                ];

                $url_es = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

                $rs = HttpUtil::send_curl_methods($url_es,$curl_param,"POST");

                $rs_agg = json_decode($rs,true);
                $ads = empty($rs_agg['aggregations']['ads']['value'])?0:$rs_agg['aggregations']['ads']['value'];

                unset($curl_param);
                unset($rs_agg);
            }
           

            $item_ep['app'] = $value->cname;
            $item_ep['host'] = $value->host;
            $item_ep['company'] = $value->company_name;
            $item_ep['mau'] = $value->mau;
            $item_ep['mau_rate'] = $value->mau_rate;
            $item_ep['user_time'] = $value->user_time;
            $item_ep['high_uv_rate'] = $value->high_uv_rate;
            $item_ep['adbug_url'] = '';

            $item_ep['ads'] = $ads;

            $item['token'] = md5($url);
            if($value->host){
                $url = $url.'&token='.md5($url).'#!subject';
                $item_ep['adbug_url'] = $url;
            }else{
                $item_ep['host'] = 'N\A';
                $item_ep['adbug_url'] = 'N\A';
                $url = $url.'#!subject';
            }
            $item['url'] = $url;
            $token_url[] = $item;
            array_push($cellData, $item_ep);
       }

       if(!empty($token_url))
        {
            $table = env('DB_OC_TABLE')?env('DB_OC_TABLE'):'oc_url_token';

            foreach ($token_url as $key => $value) {

                $db = DB::connection('mysql_oc_true')->table($table);
                $is_token = $db->where(['url'=>$value['url'],'token'=>$value['token']])->first();
                if(!$is_token)
                {
                    $db->insert(['url'=>$value]);
                }
            }
        }
        Excel::create('Adbug 40App视频详细',function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('xls');
    }
    /**
     * 暂时使用
     * @return [type] [description]
     */
    public function getAddataTest(Request $request)
    {
        return view('apptype');
    }

    public function saveAppType(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                'type'=>'flv'
                            ]
                        ],
                        [
                            'term'=>[
                                'platform'=>'2'
                            ]
                        ],
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>'2017-01-01',
                                    'lte'=>'2017-01-31'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'aggs'=>[
                'subject'=>[
                    'terms'=>[
                        'field'=>'subject_md5',
                        'size'=>1000
                    ],
                    'aggs'=>[
                        'ads'=>[
                            'terms'=>[
                                'field'=>'id',
                                'size'=>500
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs_agg = json_decode($rs,true);

        $subject_md5 = $rs_agg['aggregations']['subject']['buckets'];



        $subject_tmp = [];
        foreach ($subject_md5 as $key => $value) {
            $ads = $value['ads']['buckets'];
            if(!empty($ads))
            {
                foreach ($ads as $k => $v) {
                   $subject_tmp[] = $v['key'];
                }
            }
        }

        $data = DB::table('addata')->whereIn('id',$subject_tmp)->get();

        // $curl_param_id = [
        //     'size'=>count($subject_tmp),
        //     'query'=>[
        //         'filtered'=>[
        //             'filter'=>[
        //                 'terms'=>[
        //                     'md5'=>$subject_tmp
        //                 ]
        //             ]
        //         ]
        //     ]
        // ];

        // $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

        // $rs = HttpUtil::send_curl_methods($url,$curl_param_id,"POST");

        // $data = DBUtil::get_parse_es_data($rs);


        $cellData = [
            [
                'id','body_height','body_width','url','material',
                'screen','url_md5',
                'advertiser','trackers','publisher','platform','type','date5',
                'shape','domain','attribute04','width','height','size',
                'x','y','target_url','thumbnail','last_seen','md5',
                'thumb_width','thumb_height','original_url','thumb_url','share_url','title'
            ]
        ];


        if(!empty($data))
        {
                foreach ($data as $kk => $vv) {
                 
                    $item['id'] = $vv->id;
                    $item['body_height'] = $vv->body_height;
                    $item['body_width'] = $vv->body_width;
                    $item['url'] = $vv->url;
                    $item['material'] = $vv->material;


                    $item['screen'] = $vv->screen;
                    $item['url_md5'] = $vv->url_md5;

                    $item['advertiser'] = $vv->advertiser;
                    $item['trackers'] = $vv->trackers;
                    $item['publisher'] = $vv->publisher;
                    $item['platform'] = $vv->platform;
                    $item['type'] = $vv->type;
                    $item['date5'] = $vv->created_date;
                    $item['shape'] = $vv->shape;

                    $item['domain'] = $vv->domain;
                    $item['attribute04'] = $vv->attribute04;
                    $item['width'] = $vv->width;
                    $item['hieght'] = $vv->height;
                    $item['size'] = $vv->size;

                    $item['x'] = $vv->x;
                    $item['y'] = $vv->y;
                    $item['target_url'] = $vv->target_url;
                    $item['thumbnail'] = $vv->thumbnail;
                    $item['last_seen'] = $vv->last_seen;
                    $item['md5'] = $vv->md5;

                    $item['thumb_width'] = $vv->thumb_width;
                    $item['thumb_height'] = $vv->thumb_height;
                    $item['original_url'] = $vv->original_url;
                    $item['thumb_url'] = $vv->thumb_url;
                    $item['share_url'] = $vv->share_url;
                    $item['title'] = $vv->title;


                    array_push($cellData, $item);
            }
        }

        Excel::create('Adbug app 视频 详细',function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('xls');

    }

    /**
     * 获取广告趋势
     * field = md5  广告创意 ;field = subject_md5 营销活动 趋势
     * 根据 广告 创意 md5 值
     * type  0 默认  1 一月内 2 半年内 3 一年内 4 自定义
     * start_time 开始时间 end_time 结束时间
     * @return [type] [description]
     */
    public function getAdsTrend(Request $request)
    {
        $type = empty($request->type)?'':$request->type;


        //对应 的字段值
        $md5 = empty($request->md5)?'':$request->md5;

        // 对应的字段
        $field = empty($request->field)?'':$request->field;

        $start_time = empty($request->start_time)?'':$request->start_time;

        $end_time = empty($request->end_time)?'':$request->end_time;


        if(empty($md5))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        if($type==4)
        {
        	if(empty($end_time) || empty($start_time))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }

        $data = [];
        // dd(DBUtil::diffDate('2014-12-21','2014-12-12'));

        // dd(DBUtil::getMonthNum(1478443522839,1479138219014));
        // 默认 显示 查询
        if(empty($type))
        {

            if($start_time && $end_time)
            {
                $curl_param = [
                   'size'=>0,
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'term'=>[
                                        $field=>$md5
                                    ]
                                ],
                                [
                                    'range'=>[
                                        'date'=>[
                                            'gte'=>$start_time,
                                            'lte'=>$end_time
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                   'aggs'=>[
                        'ads_num'=>[
                            'terms'=>[
                               'size'=>0,
                               'field'=>'date_day',
                               'order'=>[
                                   'min_date'=>'asc'
                                ]
                            ],
                            'aggs'=>[
                                'min_date'=>[
                                    'min'=>[
                                        'field'=>'created_date'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
                $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

                $ads_json = json_decode($rs,true);

                $ads_num_tmp = empty($ads_json['aggregations']['ads_num']['buckets'])?[]:$ads_json['aggregations']['ads_num']['buckets'];

                $ads_num_aggs = [];
                if(!empty($ads_num_tmp))
                {
                    foreach ($ads_num_tmp as $key => $value) {

                        $item['date'] = $value['key_as_string'];
                        $item['aggs'] = $value['doc_count'];
                        $ads_num_aggs[] = $item;
                    }
                }

                $data['data'] = $ads_num_aggs;
                $data['status'] = 200;
                return HttpUtil::retrun_json($data,200);
            }



        	// 第一步 统计 开始 和 最后出现 时间
        	$curl_day_param = [
        		'size'=>0,
	            'query'=>[
	                'term'=>[
	                    $field=>$md5
	                ]
	            ],
	            'aggs'=>[
	                'max_day'=>[
	                    'max'=>[
	                    	'field'=>'date_day'
	                    ]
	                ],
	                'min_day'=>[
	                    'min'=>[
	                    	'field'=>'date_day'
	                    ]
	                ]
	            ]
	        ];

            // dd(json_encode($curl_day_param));

	        // $url = config('param.ES_URL').'addata_mid/addata_index/'.config('param.ES_SEARCH');
            $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
	        $rs = HttpUtil::send_curl_methods($url,$curl_day_param,"POST");

	        $data_json = json_decode($rs,true);

	        // // 获取 最大 最小 时间
	        $max_date = empty($data_json['aggregations']['max_day']['value_as_string'])?'':$data_json['aggregations']['max_day']['value_as_string'];
	        $min_date = empty($data_json['aggregations']['min_day']['value_as_string'])?'':$data_json['aggregations']['min_day']['value_as_string'];

            if(empty($max_date) || empty($min_date))
            {
                return HttpUtil::retrun_json(['data'=>[],'status'=>200],200);
            }

           
	      	$data = $this->getAdsAggsData($max_date,$min_date,$md5,0,$field);
        }

        if($type==4)
        {
        	$data = $this->getAdsAggsData($end_time,$start_time,$md5,0,$field);
        }

        // 一月内
        if($type==1)
        {
        	$time_mouth = DBUtil::getNowMoth();
        	$data = $this->getAdsAggsData($time_mouth['end_time'],$time_mouth['start_time'],$md5,1,$field);
        }
        // 半年内
        if($type==2)
        {
        	$time_mouth = DBUtil::getMidYear();
        	$data = $this->getAdsAggsData($time_mouth['end_time'],$time_mouth['start_time'],$md5,2,$field);
        }
        // 一年内
        if($type==3)
        {
        	$time_mouth = DBUtil::getFullYear();
        	$data = $this->getAdsAggsData($time_mouth['end_time'],$time_mouth['start_time'],$md5,3,$field);
        }
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 获取 统计数据 趋势
     * @return [type] [description]
     */
    public function getAdsAggsData($max_date,$min_date,$md5,$type,$field)
    {
    	$diff_time = DBUtil::diffDate($max_date,$min_date);

    	$terms_field = 'date_day';

    	if($type)
    	{
    		switch ($type) {
	    		case '1':
	    			$terms_field = 'date_day';
	    			break;
	    		case '2':
	    			$terms_field = 'date_mouth';
	    			break;
	    		case '3':
	    			$terms_field = 'date_mouth';
	    			break;
	    	}
    	}else{
    		if($diff_time['year'])
		    {
		        $terms_field = 'date_mouth';
		    }else if($diff_time['month'])
		    {
		        if($diff_time['month'] > 3)
		        {
		        	$terms_field = 'date_mouth';
		        }else{
		        	$terms_field = 'date_day';
		        }
		   	}else if($diff_time['day'] > 4){
		      	$terms_field = 'date_day';
		    }else{
		        $terms_field = 'date_day';
		    }
    	}


	    $curl_param = [
	       'size'=>0,
	        'query'=>[
	            'term'=>[
	                $field=>$md5
	            ]
	        ],
	       'aggs'=>[
	            'ads_num'=>[
	                'terms'=>[
	                   'size'=>0,
	                   'field'=>$terms_field,
	                   'order'=>[
	                       'min_date'=>'asc'
	                    ]
	               	],
	               	'aggs'=>[
	                    'min_date'=>[
	                    	'min'=>[
	                    		'field'=>'created_date'
	                    	]
	                    ]
	               	]
	            ]
	       	]
	   	];

	    $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
	    $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

	    $ads_json = json_decode($rs,true);

	    $ads_num_tmp = empty($ads_json['aggregations']['ads_num']['buckets'])?[]:$ads_json['aggregations']['ads_num']['buckets'];

	    // 判断当前 terms 个数 便于 统计 图片 展示
	    
	    // if(count($ads_num_tmp)<10)
	    // {

	    // 	$terms_field_in = 'date_hour';
	    // 	switch ($terms_field) {
	    // 		case 'date_mouth':
	    // 			$terms_field_in = 'date_day';
	    // 			break;
	    // 		case 'date_day':
	    // 			$terms_field_in = 'date_day';
	    // 			break;
     //            case 'date_hour':
     //                $terms_field_in = 'date_day';
     //                break;
	    // 	}
	    // 	$curl_param_in = [
		   //     'size'=>0,
		   //      'query'=>[
		   //          'term'=>[
		   //              $field=>$md5
		   //          ]
		   //      ],
		   //     'aggs'=>[
		   //          'ads_num'=>[
		   //              'terms'=>[
		   //                 'size'=>0,
		   //                 'field'=>$terms_field_in,
		   //                 'order'=>[
		   //                     'min_date'=>'asc'
		   //                  ]
		   //             	],
		   //             	'aggs'=>[
		   //                  'min_date'=>[
		   //                  	'min'=>[
		   //                  		'field'=>'created_date'
		   //                  	]
		   //                  ]
		   //             	]
		   //          ]
		   //     	]
		   // 	];

		   //  $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
		   //  $rs = HttpUtil::send_curl_methods($url,$curl_param_in,"POST");

		   //  $ads_json = json_decode($rs,true);

		   //  $ads_num_tmp_in = empty($ads_json['aggregations']['ads_num']['buckets'])?[]:$ads_json['aggregations']['ads_num']['buckets'];

		   //  $ads_num_aggs_in = [];
		   //  if(!empty($ads_num_tmp_in))
		   //  {
		   //    	foreach ($ads_num_tmp_in as $key => $value) {

		   //      	$item_in['date'] = $value['key_as_string'];
		   //      	$item_in['aggs'] = $value['doc_count'];
		   //      	$ads_num_aggs_in[] = $item_in;
		   //      }
		   //  }

		   //  $data['data'] = $ads_num_aggs_in;
		   //  $data['status'] = 200;
		   //  return $data;
	    // }

	    $ads_num_aggs = [];
	    if(!empty($ads_num_tmp))
	    {
	      	foreach ($ads_num_tmp as $key => $value) {

	        	$item['date'] = $value['key_as_string'];
	        	$item['aggs'] = $value['doc_count'];
	        	$ads_num_aggs[] = $item;
	        }
	    }

	    $data['data'] = $ads_num_aggs;
	    $data['status'] = 200;
	    return $data;
    }

    /**
     * 获取 所有 跟踪者 
     * 过滤条件
     * 广告主最多的 跟踪者 50 条
     * 时间范围 2016-09-01  -- 2016-12-31
     * 
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getTrackerInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;

        $size = empty($request->size)?6000:$request->size;

        if($size>=10000)$size = 6000;

        if(empty($start_time) && empty($end_time))dd('开始时间 start_time 结束时间 end_time 不可为空,请输入');

        $tracker_arr = [
            'optaim.com',
            'adsame.com',
            'xtgreat.com',
            'biddingx.com',
            'vamaker.com',
            'yoyi.com.cn',
            'tencentmind.com',
            'dxpmedia.com',
            'fancyapi.com'
        ];

        $cellData = [
            [
                'id','host','type','shape','screen','platform','date5','size',
                'x','y','width','height','material','role','brand_ads','publisher_ads','tracker_advertiser',
                'brand_subjects','target_url'
            ]
        ];

        foreach ($tracker_arr as $key => $value) {
            $curl_param_in = [
                'size'=>$size,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'date5'=>[
                                        'gte'=>$start_time,
                                        'lte'=>$end_time
                                    ]
                                ]
                            ],
                            [
                                'term'=>[
                                    'trackers'=>$value
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $url = config('param.ES_URL').'addata_v15/addata_index/'.config('param.ES_SEARCH');
            $rs_sub = HttpUtil::send_curl_methods($url,$curl_param_in,"POST");
                        
            $data_sub = DBUtil::get_parse_es_data($rs_sub);

            if(!empty($data_sub['data']))
            {
                foreach ($data_sub['data'] as $kk => $vv) {
                    $item2['id'] = empty($vv->id)?'':$vv->id;
                    $item2['host'] = empty($vv->trackers)?'':$vv->trackers;
                    $item2['type'] = empty($vv->type)?'':$vv->type;
                    $item2['shape'] = empty($vv->shape)?'':$vv->shape;
                    $item2['screen'] = empty($vv->screen)?'':$vv->screen;
                    $item2['platform'] = empty($vv->platform)?'':$vv->platform;
                    $item2['date5'] = empty($vv->date5)?'':$vv->date5;
                    $item2['size'] = empty($vv->size)?'':$vv->size;
                    $item2['x'] = empty($vv->x)?'':$vv->x;
                    $item2['y'] = empty($vv->y)?'':$vv->y;
                    $item2['width'] = empty($vv->width)?'':$vv->width;
                    $item2['height'] = empty($vv->height)?'':$vv->height;
                    $item2['material'] = empty($vv->material)?'':$vv->material;

                    $item2['role'] = empty($vv->role)?'':$vv->role;
                    $item2['brand_ads'] = empty($vv->brand_ads)?'':$vv->brand_ads;
                    $item2['publisher_ads'] = empty($vv->publisher_ads)?'':$vv->publisher_ads;
                    $item2['tracker_advertiser'] = empty($vv->tracker_advertiser)?'':$vv->tracker_advertiser;
                    $item2['brand_subjects'] = empty($vv->brand_subjects)?'':$vv->brand_subjects;
                    $item2['target_url'] = empty($vv->target_url)?'':$vv->target_url;
                    array_push($cellData, $item2);
                }
            }
        }

        Excel::create('Adbug 跟踪者 详细',function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('xls');
    }

    public function getAppInfoTest(Request $request)
    {
        return view('test.api.appinfo');
    }

    /**
     * 获取 app 信息
     * 2017-1-19
     * @return [type] [description]
     */
    public function getAppInfo(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $start_time=empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;

        if(empty($start_time) && empty($end_time))dd('参数不对,请输入开始时间 结束时间 start_time end_time');
        if(!is_numeric($start_time) && !is_numeric($end_time))dd('请输入 时间戳 精确到毫秒 结束时间 1483142400000(2016-12-01) - 1483142400000(2016-12-31)');
        // 1483142400000
        // 第一步 获取域名信息
        $app_cn = [
            "腾讯新闻","腾讯视频","今日头条",
            "爱奇艺视频","天天快报","优酷视频",
            "网易新闻","新浪新闻","快手",
            "搜狐新闻","乐视视频","一点资讯","Flipboard 中国版","搜狐视频","凤凰新闻",
            "暴风影音","土豆视频","百度视频","百度新闻","央视影音","ZAKER","畅读","爱奇艺PPS影音","新浪财经",
            "哔哩哔哩动画","东方财富网","美拍","YY",
            "秒拍","风行视频","澎湃新闻","球探体育比分","人民日报",
            "虎牙直播","军事头条","腾讯体育","小米视频"
        ];
        $is_app_link = [
            '芒果TV'=>'芒果tv',
            '斗鱼'=>'斗鱼TV',//air.tv.douyu.android 斗鱼TV
            '影视大全'=>'影视大全(com.le123)'//com.cinema2345 影视大全(com.le123)
        ];

        $is_app_link_domain = [
            'com.hunantv.imgo.activity',
            'air.tv.douyu.android',//air.tv.douyu.android 斗鱼TV
            'com.cinema2345'//com.cinema2345 影视大全(com.le123)
        ];
        $is_not_app = [
            '浙江新闻','懂球帝','韩剧TV','冲浪快讯','咪咕视频','Whats New','直播吧','和讯外汇','视吧',
            '花椒直播','虎扑体育','唔哩','新浪体育','国务院','头条视频','百度好看',
            '聚力视频','映客直播','UC头条','乐视体育','国务院'
        ];
        
        
        // dd($app_domain);
        // 第四部 导出 excel
        $cellData = [
            [
                'query_name','adbug_name','广告创意数','广告主数','营销活动数','top50广告主','广告主链接',
                'top50创意名称','创意链接','创意平台','创意类型','top50营销活动名称','营销活动链接'
            ]
        ];
        $app_domain = $this->appDomianQuery($app_cn,$start_time,$end_time,'cname');
        $cellData = $this->pushAppData($app_domain,$cellData,$is_app_link);

        $app_domain_link = $this->appDomianQuery($is_app_link_domain,$start_time,$end_time,'host');

        $domain_td_exist = [];
        foreach ($app_domain_link as $key => $value) {
            if(!in_array($value->id, $domain_td_exist))
            {
                $domain_td_exist[] = $value->id;
            }else{
                unset($app_domain_link[$key]);
            }
        }

        $cellData = $this->pushAppData($app_domain_link,$cellData,$is_app_link);


        // 进一步过滤重复的值
        $cellData_tmp = [];
        foreach ($cellData as $ke=>$va)
        {
            if($ke>0)
            {
                $adbug_name = empty($va['adbug_name'])?'':$va['adbug_name'];
                $advertiser_name = empty($va['advertiser_top_name'])?'':$va['advertiser_top_name'];
                $ads_top_name = empty($va['ads_top_name'])?'':$va['ads_top_name'];
                $sub_top_name = empty($va['sub_top_name'])?'':$va['sub_top_name'];
                $md5_tmp = md5($adbug_name.$advertiser_name.$ads_top_name.$sub_top_name);

                if(in_array($md5_tmp,$cellData_tmp) && !empty($md5_tmp))
                {
                    unset($cellData[$ke]);
                }else{
                    $cellData_tmp[] = $md5_tmp;
                }
            }
        }


        foreach ($is_not_app as $key => $value) {
            $item_n['name']=$value;
            $item_n['adbug_name']='';
            $item_n['ads']='\N';
            $item_n['advertiser']='\N';
            $item_n['subject']='\N';
            $item['advertiser_top_name']='\N';
            $item['advertiser_top_link'] = '\N';
            $item['ads_top_name']='\N';
            $item['ads_top_in'] = '\N';
            $item['sub_top_name']='\N';
            $item['sub_top_in'] = '\N';
            array_push($cellData, $item_n);
        }

        $token_url = [];
        foreach ($cellData as $key => $value) {

            if($key>0)
            {
                if(!empty($value['advertiser_top_link']))
                {
                    $token_url[] = $this->addTokenUrl($value['advertiser_top_link']);
                }
                if(!empty($value['ads_top_in']))
                {
                    $token_url[] = $this->addTokenUrl($value['ads_top_in']);
                }
                if(!empty($value['sub_top_in']))
                {
                    $token_url[] = $this->addTokenUrl($value['sub_top_in']);
                }
            }
            
            
        }

        if(!empty($token_url))
        {
            $table = env('DB_OC_TABLE')?env('DB_OC_TABLE'):'oc_url_token';

            foreach ($token_url as $key => $value) {

                $db = DB::connection('mysql_oc_true')->table($table);
                $is_token = $db->where(['url'=>$value['url'],'token'=>$value['token']])->first();
                if(!$is_token)
                {
                    $db->insert(['url'=>$value]);
                }
            }
        }

        // dd($cellData);
        Excel::create('Adbug App 详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');
    }

    public function appDomianQuery($is_app_link_domain,$start_time,$end_time,$type)
    {

        $app_domain_link = DB::table('domains')->whereIn($type,$is_app_link_domain)->where('platform',1)->get();
        
        foreach ($app_domain_link as $key => &$value) {

            if($value)
            {

                $curl_param_nums_tmp = [
                    'size'=>0,
                    'query'=>[
                        'filtered'=>[
                            'filter'=>[
                                'bool'=>[
                                    'must'=>[
                                        [
                                            'term'=>[
                                                'publisher'=>$value->host
                                            ]
                                        ],
                                        [
                                            'term'=>[
                                                'platform'=>2
                                            ]
                                        ],
                                        [
                                            'exists'=>[
                                                'field'=>'title'
                                            ]
                                        ],
                                        [
                                            'exists'=>[
                                                'field'=>'advertiser'
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
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'ads_num'=>[
                            'cardinality'=>[
                                'field'=>'md5'
                            ]
                        ],
                        'sub_num'=>[
                            'cardinality'=>[
                                'field'=>'subject_md5'
                            ]
                        ],
                        'advertiser_num'=>[
                            'cardinality'=>[
                                'field'=>'advertiser'
                            ]
                        ]
                        
                    ]
                ];


                // dd(json_encode($curl_param_nums_tmp));

                $url_tmp = config('param.ES_URL').'addatas/'.config('param.ES_SEARCH');
                // $url = config('param.ES_URL').'addata_mid/addata_index/'.config('param.ES_SEARCH');
                $dd_num_tmp = HttpUtil::send_curl_methods($url_tmp,$curl_param_nums_tmp,"POST");
        
                $data_num_tmp = json_decode($dd_num_tmp,true);

                $ads_num_tmp = empty($data_num_tmp['aggregations']['ads_num']['value'])?0:$data_num_tmp['aggregations']['ads_num']['value'];

                $value->ads_num = $ads_num_tmp;

                $advertiser_num_tmp = empty($data_num_tmp['aggregations']['advertiser_num']['value'])?0:$data_num_tmp['aggregations']['advertiser_num']['value'];


                $value->advertiser_num = $advertiser_num_tmp;

                $sub_num_tmp = empty($data_num_tmp['aggregations']['sub_num']['value'])?0:$data_num_tmp['aggregations']['sub_num']['value'];
                $value->sub_num = $sub_num_tmp;

                // 获取 当月 数据 统计
                $curl_param_nums = [
                    'size'=>0,
                    'query'=>[
                        'filtered'=>[
                            'filter'=>[
                                'bool'=>[
                                    'must'=>[
                                        [
                                            'term'=>[
                                                'publisher'=>$value->host
                                            ]
                                        ],
                                        [
                                            'term'=>[
                                                'platform'=>2
                                            ]
                                        ],
                                        [
                                            'exists'=>[
                                                'field'=>'title'
                                            ]
                                        ],
                                        [
                                            'exists'=>[
                                                'field'=>'advertiser'
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
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'md5'=>[
                            'terms'=>[
                                'field'=>'md5',
                                'size'=>$ads_num_tmp
                            ]
                        ],
                        'subject_md5'=>[
                            'terms'=>[
                                'field'=>'subject_md5',
                                'size'=>$sub_num_tmp
                            ]
                        ]//,
                        // 'advertisers'=>[
                        //     'terms'=>[
                        //         'field'=>'advertiser',
                        //         'size'=>$advertiser_num_tmp
                        //     ]
                        // ]
                    ]
                ];


                // dd(json_encode($curl_param_nums));
                $url = config('param.ES_URL').'addatas/'.config('param.ES_SEARCH');
                // $url = config('param.ES_URL').'addata_mid/addata_index/'.config('param.ES_SEARCH');
                $dd_num = HttpUtil::send_curl_methods($url,$curl_param_nums,"POST");
                $data_num = json_decode($dd_num,true);
                // $advertiser = empty($data_num['aggregations']['advertiser']['buckets'])?[]:($data_num['aggregations']['advertiser']['buckets']);
                $md5 = empty($data_num['aggregations']['md5']['buckets'])?[]:($data_num['aggregations']['md5']['buckets']);
                $subject_md5 = empty($data_num['aggregations']['subject_md5']['buckets'])?[]:($data_num['aggregations']['subject_md5']['buckets']);

                // $advertisers_arr = empty($data_num['aggregations']['advertisers']['buckets'])?[]:($data_num['aggregations']['advertisers']['buckets']);
                //第一步 获取创意 数

                $subject_id = [];
                foreach ($subject_md5 as $k => $d) {
                    $subject_id[] = $d['key'];
                }

                $subject_md5_tmp = [];

                foreach ($subject_id as $sk => $skv) {
                    $curl_param_sub = [
                        'size'=>0,
                        'query'=>[
                            'bool'=>[
                                'must'=>[
                                    [
                                        'term'=>[
                                            'subject_md5'=>$skv
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
                        ]
                       
                    ];

                    $url = config('param.ES_URL').'addata_mid/'.config('param.ES_SEARCH');

                    $rs_sub = HttpUtil::send_curl_methods($url,$curl_param_sub,"POST");

                    $rs_sub_data = json_decode($rs_sub,true);


                    $item_md5_sub['subject_md5'] = $skv;

                    $item_md5_sub['num'] = empty($rs_sub_data['hits']['total'])?0:$rs_sub_data['hits']['total'];

                    $subject_md5_tmp[] = $item_md5_sub;

                }

                if(!empty($subject_md5_tmp))
                {
                    $subject_md5_tmp_true = DBUtil::my_sort($subject_md5_tmp,'num',SORT_DESC,SORT_NUMERIC);

                    $subject_md5_data = [];
                    foreach ($subject_md5_tmp_true as $skey => $svalue) {
                        $curl_param_sub = [
                            'size'=>1,
                            'query'=>[
                                'term'=>[
                                    'md5'=>$svalue['subject_md5']
                                ]
                            ]
                        ];
                        
                        $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH').'?_source=id,title,md5,title_md5';

                        $rs_sub = HttpUtil::send_curl_methods($url,$curl_param_sub,"POST");

                        $data_sub = DBUtil::get_parse_es_data($rs_sub);

                        if(!empty($data_sub['data'][0]))
                        {
                            $subject_md5_data[] = $data_sub['data'][0];
                        }
                    
                    }

                    unset($subject_md5_tmp_true);

                    if(!empty($subject_md5_data))
                    {
                            $data_sub_tmp_1 = [];
                            $data_sub_title_tmp = [];

                            foreach ($subject_md5_data as $ad_k => $ad_v) {
                                if(empty($ad_v->title))
                                {
                                    unset($subject_md5_data[$ad_k]);
                                }else{
                                    if(!in_array($ad_v->title, $data_sub_title_tmp))
                                    {
                                        $data_sub_title_tmp[] = $ad_v->title;
                                        $data_sub_tmp_1[] = $subject_md5_data[$ad_k];
                                    }
                                    
                                }
                            }

                            if(count($data_sub_tmp_1)<=50)
                            {
                                $value->sub_num = count($data_sub_tmp_1);
                            }

                            $value->sub = $data_sub_tmp_1;

                            unset($data_sub_tmp_1);

                            unset($data_sub_title_tmp);
                        }else{
                            $value->sub = [];
                    }
                }else{
                     $value->sub = [];
                }

                unset($subject_id);
                unset($subject_md5_tmp);
            

                $ads_id = [];
                foreach ($md5 as $k => $d) {
                    $ads_id[] = $d['key'];
                }

                $ads_md5_tmp = [];
                $ads_md5_tmp_true = [];
                if(!empty($ads_id))
                {
                    foreach ($ads_id as $akey => $avalue) {
                        $curl_param_ads = [
                            'size'=>0,
                            'query'=>[
                                'bool'=>[
                                    'must'=>[
                                        [
                                            'term'=>[
                                                'md5'=>$avalue
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
                            ]
                           
                        ];

                        $url = config('param.ES_URL').'addata_mid/'.config('param.ES_SEARCH');

                        $rs_ads = HttpUtil::send_curl_methods($url,$curl_param_ads,"POST");

                        $rs_ads_data = json_decode($rs_ads,true);


                        $item_md5_ads['md5'] = $avalue;

                        $item_md5_ads['num'] = empty($rs_ads_data['hits']['total'])?0:$rs_ads_data['hits']['total'];

                        $ads_md5_tmp[] = $item_md5_ads;
                    }
                    
                }

                if(!empty($ads_md5_tmp))
                {
                    $ads_md5_tmp_true = DBUtil::my_sort($ads_md5_tmp,'num',SORT_DESC,SORT_NUMERIC);
                }


                unset($ads_md5_tmp);
                $ads_md5_tmp_data = [];
                if(!empty($ads_md5_tmp_true))
                {
                    foreach ($ads_md5_tmp_true as $aks => $aksv) {
                        $curl_param_ads = [
                            'size'=>1,
                            'query'=>[
                                'bool'=>[
                                    'must'=>[
                                        'term'=>[
                                            'md5'=>$aksv['md5']
                                        ]
                                    ]
                                ]
                            ]
                        ];
                           
                        $url = config('param.ES_URL').'addatas/'.config('param.ES_SEARCH');

                        $rs_ads = HttpUtil::send_curl_methods($url,$curl_param_ads,"POST");

                        $data_ads = DBUtil::get_parse_es_data($rs_ads);

                       if(!empty($data_ads['data'][0]))
                       {
                            $ads_md5_tmp_data[] = $data_ads['data'][0];
                       }
                        
                    }
                }

                if(!empty($ads_md5_tmp_data))
                {
                    if(!empty($ads_md5_tmp_data))
                    {
                        $data_ads_tmp_1 = [];
                        $data_ads_tmp_title = [];

                        foreach ($ads_md5_tmp_data as $ad_k => $ad_v) {
                                if(empty($ad_v->title))
                                {
                                    unset($ads_md5_tmp_data[$ad_k]);
                                }else{
                                    if(!in_array($ad_v->title, $data_ads_tmp_title))
                                    {
                                        $data_ads_tmp_title[] = $ad_v->title;
                                        $data_ads_tmp_1[] = $ads_md5_tmp_data[$ad_k];
                                    }
                                    
                                }
                            }
                            if(count($data_ads_tmp_1)<=50)
                            {
                                $value->ads_num = count($data_ads_tmp_1);
                            }
                            $value->ads = $data_ads_tmp_1;
                            unset($data_ads_tmp_1);
                        }else{
                            $value->ads = [];
                        }

                        unset($data_ads_tmp_title);
                }else{
                    $value->ads = [];
                }
                
                unset($ads_id);
                unset($ads_md5_tmp_data);
                unset($ads_md5_tmp_true);

                // 1480550400000
                // 1483142400000
                $sql = 'SELECT DISTINCT domains.cname,domains.host from main_index JOIN domains on main_index.advertiser=domains.id JOIN subjects on main_index.subject=subjects.id WHERE main_index.publisher='.$value->id.' AND  main_index.created_date>'.$start_time.' AND main_index.created_date<'.$end_time.' AND main_index.platform=2 AND domains.host !="" ORDER BY subjects.ads desc LIMIT '.$advertiser_num_tmp;


                // 获取 广告主 从 mysql 获取
                $advertiser_sql = DB::select($sql);

                if(!empty($advertiser_sql))
                {
                    $data_adverteser_tmp_1 = [];
                    foreach ($advertiser_sql as $ad_k => $ad_v) {
                        if(empty($ad_v->host))
                        {
                            unset($advertiser_sql[$ad_k]);
                        }else if(count($data_adverteser_tmp_1)<=50){
                            $data_adverteser_tmp_1[] = $advertiser_sql[$ad_k];
                        }
                    }
                    if(count($data_adverteser_tmp_1)<=50)
                    {
                        $value->advertiser_num = count($data_adverteser_tmp_1);
                    }
                    
                    $value->advertiser = $data_adverteser_tmp_1;

                    unset($data_adverteser_tmp_1);
                }else{
                    $value->advertiser = [];
                }

            }
        }

        foreach ($app_domain_link as $key => $value) {
            if(!empty($value->sub))
            {
                foreach ($value->sub as $sk => &$sv) {
                    # code...
                    $sv->url = 'http://www.adbug.cn/home/subject/index/p/'.$sv->md5;
                }
            }

            if(!empty($value->ads))
            {
                foreach ($value->ads as $sk => &$sv) {

                    $sv->url = 'http://www.adbug.cn/home/subject/index/p/'.md5($sv->advertiser.$sv->title);
                    
                }
            }

            if(!empty($value->advertiser))
            {
                foreach ($value->advertiser as $sk => &$sv) {

                    $sv->url = 'http://www.adbug.cn/home/advertiser/detail/p/'.md5($sv->cname.$sv->host).'#!subject';

                }
            }
        }


        return $app_domain_link;
    }

    /**
     * 统计 管理 url  token 
     * @param [type] $url [description]
     */
    public function addTokenUrl($url)
    {
        $token_arr = empty(explode('&token=', $url)[1])?'':explode('&token=', $url)[1];

        // c4c5c15356faa67f87557f63beeb867d#!subject
        // 
        $token = empty(explode('#!', $token_arr)[0])?'':explode('#!', $token_arr)[0];

        $item_url['url'] = $url;
        $item_url['token'] = $token;
        $item_url['created_date'] = date('Y-m-d H:i:s',time());
        $item_url['updated_date'] = date('Y-m-d H:i:s',time());
        return $item_url;
    }
    /**
     * 合拼数据
     */
    public function pushAppData($app_domain,$cellData,$is_app_link)
    {
        $is_md_exist_tmp = [];

        foreach ($app_domain as $key => $value) {


            $domain_name = empty($value->cname)?'':$value->cname;
            $item['name']= $domain_name;
            // 判断 搜素 和 mysql 数据 不一致的情况
       
            if(!empty($domain_name))
            {
                foreach ($is_app_link as $is_a_k=>$is_a_v)
                {
                    if($domain_name == $is_a_v)
                    {
                        $item['name'] = $is_a_k;
                    }
                }
            }
            
            

            $ads_num = $value->ads_num;

            $advertiser_num = $value->advertiser_num;

            $sub_num = $value->sub_num;


            $max_num = max($ads_num,$advertiser_num);

            $max_num = max($sub_num,$max_num);

            $index_in = 1;
            $index_max = $max_num>50?$max_num:50;

            if(empty($max_num))
            {
                $md5 = md5($domain_name.$ads_num.$advertiser_num.$sub_num);
                if(!in_array($md5, $is_md_exist_tmp) && $index_max<=50)
                {
                    $index_in = $index_in+1;
                    $is_md_exist_tmp[] = $md5;
                    $item['adbug_name']=$value->cname;
                    $item['ads']=$ads_num;
                    $item['advertiser']=$advertiser_num;
                    $item['subject']=$sub_num;

                    $item['advertiser_top_name']='\N';
                    $item['advertiser_top_link'] = '\N';

                    $item['ads_top_name']='\N';
                    $item['ads_top_in'] = '\N';
                    $item['ads_platfrom'] = '\N'; // 创意平台
                    $item['ads_type'] = '\N';// 创意类型
                    $item['sub_top_name']='\N';
                    $item['sub_top_in'] = '\N';
                    array_push($cellData, $item);
                }

            }else{

                $ads_title_dd = [];
                $sub_title_dd = [];

                for ($i=0; $i < $index_max; $i++) {

                    if($index_in<=50)
                    {
                        if(!empty($value->advertiser[$i]) || !empty($value->sub[$i]) || !empty($value->ads[$i]))
                        {
                            $index_in = $index_in+1;

                            $item['adbug_name']=$value->cname;

                            $item['ads']=$ads_num;
                            $item['advertiser']=$advertiser_num;
                            $item['subject']=$sub_num;

                            $item['advertiser_top_name']='\N';
                            $item['advertiser_top_link'] = '\N';

                            $item['ads_top_name']='\N';
                            $item['ads_top_in'] = '\N';
                            $item['ads_platfrom'] = '\N'; // 创意平台
                            $item['ads_type'] = '\N';// 创意类型
                            $item['sub_top_name']='\N';
                            $item['sub_top_in'] = '\N';

                            // 广告主
                            if(!empty($value->advertiser) && !empty($value->advertiser[$i]) && !empty($value->advertiser[$i]->host))
                            {
                                $item['advertiser_top_name']=$value->advertiser[$i]->host.(empty($value->advertiser[$i]->cname)?'':' -'.$value->advertiser[$i]->cname);
                                $url = 'http://www.adbug.cn/home/advertiser/detail/p/'.md5($value->advertiser[$i]->host);


                                $item['advertiser_top_link']=$url.'&token='.md5($url).'#!subject';
                            }
                            // 营销活动
                            if(!empty($value->sub[$i]) && !empty($value->sub[$i]->title))
                            {
                                    $item['sub_top_name'] = urldecode($value->sub[$i]->title);

                                    $url = $value->sub[$i]->url;

                                    $item['sub_top_in'] = $url.'&token='.md5($url);
                            }
                            // 创意
                            if(!empty($value->ads[$i]) && !empty($value->ads[$i]->title))
                            {


                                 $item['ads_top_name']=urldecode($value->ads[$i]->title);
                                    $url = 'http://www.adbug.cn/home/index/adUrl/id/'.$value->ads[$i]->id;

                                    $item['ads_top_in']=$url.'&token='.md5($url);
                                    $item['ads_platfrom'] = empty($value->ads[$i]->attribute04)?'移动':$value->ads[$i]->attribute04; // 创意平台
                                    $item['ads_type'] = $value->ads[$i]->type;// 创意类型

                            }

                            array_push($cellData, $item);
                        }
                    }


                }
            }
        }
        unset($is_md_exist_tmp);
        unset($sub_title_dd);
        unset($title_md5);
        return $cellData;
    }
    public function getAppDomain()
    {
        set_time_limit(0);
        $not_app_domain = [
            "芒果TV","乐视体育", "UC头条","映客直播","聚力视频","百度好看", "头条视频","军事头条"
            ,"国务院","影视大全","唔哩","影视大全","花椒直播","视吧","和讯外汇","直播吧","Whats New"
            ,"咪咕视频","冲浪快讯","韩剧TV","影视大全","懂球帝","斗鱼" ,"浙江新闻"
        ];

        // $not_app_domain_p = [
        //     "新浪体育","虎扑体育"
        // ];

        $cellData = [
            [
                '名称','cname','host','ename'
            ]
        ];

        $domain_new = [];

        foreach ($not_app_domain as $key => $value) {
            $domain_new[$value] = DB::table('domains')->where('cname','like','%'.$value.'%')->where('platform',1)->get();

        }

        foreach ($domain_new as $key => $value) {
            $item['app'] = $key;
            $item['cname'] = '\N';
            $item['host'] = '\N';
            $item['ename'] = '\N';

            if(count($value))
            {
                foreach ($value as $k => $v) {
                    $item['cname'] = $v->cname;
                    $item['host'] = $v->host;
                    $item['ename'] = $v->ename;
                    array_push($cellData, $item);
                }
            }else{
                array_push($cellData, $item);
            }
        }



        Excel::create('/public/excels/',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');

        dd();
        $app_cn = [
            "腾讯新闻","腾讯视频","今日头条","爱奇艺视频","天天快报","优酷视频","网易新闻","小米视频","新浪新闻","快手",
            "搜狐新闻","乐视视频","一点资讯","芒果TV","Flipboard 中国版","搜狐视频","凤凰新闻","暴风影音","腾讯体育","土豆视频",
            "乐视体育","百度视频","百度新闻","央视影音","UC头条","ZAKER","映客直播","畅读","爱奇艺PPS影音","新浪财经",
            "哔哩哔哩动画","东方财富网","聚力视频","百度好看","头条视频","美拍","军事头条","YY","国务院","影视大全","新浪体育","秒拍",
            "唔哩","影视大全","虎扑体育","风行视频","虎牙直播","澎湃新闻","花椒直播","视吧","和讯外汇","直播吧","球探体育比分",
            "Whats New","咪咕视频","冲浪快讯","韩剧TV","人民日报","影视大全","懂球帝","斗鱼","浙江新闻"
        ];


           // 第四部 导出 excel
        $cellData = [
            [
                'app名称'
            ]
        ];
        $not_app_domain = [];
        $rs = DB::table('domains')->whereIn('cname',$app_cn)->select('cname')->get();

        $is_app_domain = [];
        foreach ($rs as $key => $value) {
            $is_app_domain[] = $value->cname;
        }

        $new_app = array_diff($app_cn,$is_app_domain);




        $rs_plate = DB::table('domains')->whereIn('cname',$app_cn)->where('platform',1)->select('cname')->get();

        
        $is_app_domain_p = [];
        foreach ($rs_plate as $key => $value) {
            $is_app_domain_p[] = $value->cname;
        }

        $new_app_p = array_diff($app_cn,$is_app_domain_p);

        

        var_dump($new_app_p);

        var_dump(array_diff($new_app_p,$new_app));

        dd();

        Excel::create('Adbug not find App 详细',function($excel) use ($cellData){
          $excel->sheet('score', function($sheet) use ($cellData){
            $sheet->rows($cellData);
          });
        })->export('xls');


        
    }
    /**
     * type 1 addata 2 mainindex 3 subject 4 domain
     */
    public function getmaxid(Request $request)
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

        $url = 'http://localhost:9200/test/_search';

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $rs = json_decode($rs);

        return empty($rs->aggregations->maxid->value)?0:$rs->aggregations->maxid->value;

    }
    public function getAll(Request $request)
    {

        echo $_SERVER['SERVER_ADDR'];


        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $type = empty($request->type)?'':$request->type;
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);
        $removeParam = empty($request->rwd)?"":$request->rwd;
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

        if($isclick && $suid)
        {
            $curl_param_subject = [
                'size'=>1,
                'query'=>[
                    'term'=>[
                        'id'=>$suid
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param_subject,"POST");
            $data_sub = DBUtil::get_parse_es_data($rs);
            $subject_md5 = empty($data_sub['data'][0]->md5)?'':$data_sub['data'][0]->md5;
            $curl_param_d = [
                'size'=>$size,
                'sort'=>[
                    'date5'=>[
                        'order'=>'desc'
                    ]
                ],
                'query'=>[
                    'term'=>[
                        'subject_md5'=>$subject_md5
                    ]
                ]
            ];
            // dd(json_encode($curl_param));
            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
            $rs_d = HttpUtil::send_curl_methods($url,$curl_param_d,"POST");

            
            $data_d = DBUtil::get_parse_es_data($rs_d);

            $data_d = $this->getadvertiserDomain($data_d);


            // 暂无数据 2002
            if(empty($data_d['data']))
            {
                $data_d['status'] = 203;
                $data_d['data'] = [];
                return HttpUtil::retrun_json($data_d,200);
            }

            $data_d['status'] = 200;

            return HttpUtil::retrun_json($data_d,200);
            exit;
        }
        $en_ch = empty($request->en_ch)?'':$request->en_ch;
        $synonym= '';
        if(empty($iscampaign))
        {
            if($en_ch==1)
            {
                $synonym = Synonym::where('ename',$param)->get();
            }else if($en_ch==2 || $en_ch==3)
            {
                // 判断是否存有缓存 有的话 直接从缓存中获取
                if($en_ch==2)
                {
                    $cache_data = DBUtil::brandCache($size,$sort,$param,$page,$from);
                    if($cache_data['status'])
                    {
                        return HttpUtil::retrun_json($cache_data['cache'],200);
                    }
                }
                $synonym = Synonym::where('cname',$param)->get();
                $synonym = !count($synonym)?Synonym::where('cname','like','%'.$param.'%')->orwhere('ename','like','%'.$param.'%')->limit(3)->get():$synonym;
            }
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
        $param = empty($c_param)?$param:$c_param;
        // 默认
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
                                            'advertiser_name_title'
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
        // 营销活动
        $curl_param_compan = [
                'from'=>$from,
                'size'=>$size,
                'min_score'=>1,
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
                                            'advertiser_name_title'
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
        if($iscampaign==1)
        {
            $curl_param = $curl_param_compan;
        }
        // 正常
        if(!$iscampaign)
        {
            $filter_range_es = ESUtil::filterESParam($param,$c_host);
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
            // 域名查询
            $campain_ads_name = [
                    'filter'=>[
                        'match_phrase'=>[
                            'advertiser_name'=>$param
                        ]
                    ],
                    "weight"=>'2'
            ];
            $campain_pub_name = [
                    'filter'=>[
                        'match_phrase'=>[
                            'publisher'=>$param
                        ]
                    ],
                    "weight"=>'2'
            ];
            $curl_param['query']['function_score']['score_mode'] = 'max';
            array_push($curl_param['query']['function_score']['functions'], $campain_ads_name);

            array_push($curl_param['query']['function_score']['functions'], $campain_pub_name);

            array_push($curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'], 'publisher');
        }

        // dd(json_encode($curl_param));


        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        
        $data = DBUtil::get_parse_es_data($rs);

        $data = $this->getadvertiserDomain($data);


        // 暂无数据 2002
        // if(empty($data['data']))
        // {
        //     $data['status'] = 203;
        //     $data['data'] = [];
        //     return HttpUtil::retrun_json($data,200);
        // }

        $redis_rs = [];
        $data['status'] = 200;


      


        // dd($data);

       return HttpUtil::retrun_json($data,200);

       

        // return view('test.addata.all');
    }




    public function getadvertiserDomain($data)
    {

        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            
            if(!empty($value->advertiser) && !in_array($value->advertiser, $domain_tmp))
            {
                $domain_tmp[] = $value->advertiser;
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
            $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');
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


    public function getAllFilter()
    {
       
        return view('test.addata.filter');
    }
    public function getAllById()
    {
    	return view('test.addata.index');
    }

    public function getAllByIds()
    {
        return view('test.addata.ids');
    }

    public function getInfoByTitle()
    {
        return view('test.addata.title');
    }


    public function getAllSubFilter()
    {
        return view('test.addata.allsubfilter');
    }



     /**
     * 获取 domain 所有 信息 
     * type 0 返回所有数据 1 简化数据
     * filetype 文件类型 图片 flush 视频
     *
     * platform 平台 1 pc  2 移动
     * wd 搜索内容
     * @return [type] [description]
     */
    public function getAllSubFilterTest(Request $request)
    {
        
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $platform = empty($request->platform)?'':$request->platform;
        $param = empty($request->wd)?'':$request->wd;
        $type = empty($request->type)?'':$request->type;

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        if(!in_array($sort, ['desc','asc','DESC','ASC']) || ($type && !is_numeric($type)) || !is_numeric($size))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
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
                                'type'=>$type
                            ]
                        ],
                        [
                            'term'=>[
                                'platform'=>$platform
                            ]
                        ]
                    ],
                    'should'=>[
                        [
                            'fuzzy'=>[
                                'title'=>[
                                    'value'=>$param
                                ]
                            ]
                        ],
                        [
                            'fuzzy'=>[
                                'domain'=>[
                                    'value'=>$param
                                ]
                            ]
                        ]
                    ]
                    
                ]
            ]
        ];

        if(empty($param))unset($curl_param['query']);
        if(empty($type))unset($curl_param['query']['bool']['must'][0]);
        if(empty($platform))unset($curl_param['query']['bool']['must'][1]);

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');
        if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
    }



    public function getInfoInIds()
    {
        return view('test.addata.inids');
    }

     /**
     * 根据 id 获取 信息
     * ids 数组 形式
     * 20160712
     * finn
     * 数据格式 $ids = '1,2,3,4,5,6'
     * @return [type] [description]
     */
    public function getInfoInIdsTest(Request $request)
    {
        $ids = $request->ids;
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        $ids = explode(',', $ids);

        //参数格式不对
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        foreach ($ids as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }

        if(count($ids)>200)dd("数据量太大哦");
        $es_data = [];

        foreach ($ids as $key => $value) {
            if($value)
            {
                $es = ESUtil::getInfoById(config('param.ADDATA_SEARCH'),$value);
                if(!empty($es))$es_data[] = $es[0];
            }
            
        }
        if(empty($es_data))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['data'] = $es_data;
        $data['status'] = 200;
        $data['total'] = empty($es_data)?'':count($es_data);

        return HttpUtil::retrun_json($data,200);

    }


    public function getAllSubAds()
    {
        return view('test.addata.allsubads');
    }


    /**
     * 广告创意 过滤 条件
     * 获取 ads 数据
     * @param   type 1 图片 2 flush 3 视频
     * @param   shape 形状
     * @param   start_time 最小时间
     * @param   end_time 最大时间
     * @param   platform 平台
     * @param   sortmode 1 按照时间排序 2 安装相关性 排序
     * 20160711
     * fyj
     */
    public function getAllSubAdsTest(Request $request)
    {
        $type = empty($request->type)?'':$request->type;
        $shape = empty($request->shape)?'':$request->shape;
        $min_data = empty($request->start_time)?0:$request->start_time;
        $end_time = empty($request->end_time)?0:$request->end_time;
        $platform = empty($request->platform)?'':$request->platform;
        $sortmode = empty($request->sortmode)?'':$request->sortmode;

        // 每页显示 数据个数
        $size = empty($request->size)?100:$request->size;
        $page = empty($request->page)?1:$request->page;

        //from: 跳过开始的结果数，默认0
        $from = empty($request->from)?10:$request->from;

        if($page>1)$from = $from * $page;


        $sort = empty($request->sort)?'desc':$request->sort;

        // 搜索内容
        $param = empty($request->param)?'':$request->param;

        // 参数不可为空
        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
        if($end_time < $min_data)return HttpUtil::retrun_json(['data'=>[],'status'=>204],200);

        // 验证参数格式
        $valiedParam = [
            'type'=>$type,
            'shape'=>$shape,
            'min_data'=>$min_data,
            'end_time'=>$end_time,
            'platform'=>$platform,
            'sortmode'=>$sortmode,
            'size'=>$size,
            'page'=>$page
        ];

        if(!DBUtil::valiedParam($valiedParam))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);


        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        $valiedParam['type'] = $type;

//         {
//     "query": {
//         "bool": {
//             "must": [
//                 {
//                     "term": {
//                         "shape": "2"
//                     }
//                 },
//                 {
//                     "fuzzy": {
//                         "title": {
//                             "value": "bai"
//                         }
//                     }
//                 }
//             ]
//         }
//     },
//     "from": 0,
//     "size": 10,
//     "sort": {
//         "id": {
//             "order": "desc"
//         }
//     }
// }

        unset($valiedParam['size']);
        unset($valiedParam['min_data']);
        unset($valiedParam['end_time']);
        unset($valiedParam['sortmode']);
        unset($valiedParam['page']);
        unset($valiedParam['size']);


        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                            [
                                'fuzzy'=>[
                                    'title'=>[
                                        'value'=>$param
                                    ]
                                ]
                            ]
                    ]
                    
                ]
            ]
        ];

        $field_arr = [];
        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];
                array_push($curl_param['query']['bool']['must'], $item);
            }
        }


        $range = '';
        if($min_data && $end_time){
            $range = [
                'range'=>[
                    'created_date'=>[
                            'gte'=>$min_data,
                            'lt'=>$end_time
                        ]
                    ]
                ];
        }
        if($min_data)
        {
            $range = [
                'range'=>[
                    'created_date'=>[
                                'gte'=>$min_data
                        ]
                    ]
                ];
        }

        if($range)array_push($curl_param['query']['bool']['must'], $range);


        if($sortmode==1)$curl_param['sort']=['created_date'=>['order'=>'desc']];

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
       
        
        $last_id = $data['data'][0]->id;
        if(!in_array($sort, ['desc','DESC']))
        {
            $last_id = $data['data'][$size-1]->id;
        }
        $data['last_id'] = $last_id;
        $data['page'] = $page;
        $data['size'] = $size;
        $data['all_page'] = intval(ceil($data['total']/$size));
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);


    }



    public function getAllFilterSearch()
    {
        return view('test.addata.allfiltersearch');
    }


    /**
     * 主页 收索 过滤 条件
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAllFilterSearchTest(Request $request)
    {

        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if(($size && $size > 10000) || !is_numeric($size))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }

        $limit = empty($request->limit)?config('param.LIMIT'):$request->limit;

        $last_id = empty($request->last_id)?0:$request->last_id;

        $sort = empty($request->sort)?'desc':$request->sort;

        if(!in_array($sort, ['desc','asc','DESC','ASC']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        $param = empty($request->wd)?'':$request->wd;


        $pages = empty($request->page)?'':$request->page; // searchads

        if($pages)$size = $pages * $limit;

        
        $platform = empty($request->platform)?'':$request->platform; // 所有终端　１　ｐｃ　　２　移动

        
        $startTime = empty($request->start_time)?'':$request->start_time;
        $endTime = empty($request->start_time)?'':$request->end_time;

        // $sortmode = empty($request->sortmode)?'':$request->sortmode;  // 时间 1  相关性 2

        $shape = empty($request->shape)?'':$request->shape;  // 0  所有尺寸  1  ， 2 ，3 ...
        $type = empty($request->type)?'':$request->type; // 1 图片 2 视频 3 flush

        $role = empty($request->role)?'':$request->role; // 角色
        $role_value = empty($request->role_value)?'':$request->role_value; // 角色值



        if($platform>2 || $type > 3 || $shape > 5)
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }


        if(($shape && !is_numeric($shape)) || ($type && !is_numeric($type)))
        {
             return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }

        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        


        if($startTime)
        {
            if($endTime < $startTime)
            {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data,200);
            }
        }


        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);




        // 验证参数格式
        $valiedParam = [
            'type'=>$type,
            'shape'=>$shape,
            'platform'=>$platform
        ];

        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        $valiedParam['type'] = $type;

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
                                'fuzzy'=>[
                                    'title'=>[
                                        'value'=>$param
                                    ]
                                ]
                            ]
                    ]
                    
                ]
            ]
        ];

        $field_arr = [];
        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];
                array_push($curl_param['query']['bool']['must'], $item);
            }
        }


        $range = '';
        if($startTime && $endTime){
            $range = [
                'range'=>[
                    'created_date'=>[
                            'gte'=>$startTime,
                            'lt'=>$endTime
                        ]
                    ]
                ];
        }
        if($startTime)
        {
            $range = [
                'range'=>[
                    'created_date'=>[
                                'gte'=>$startTime
                        ]
                    ]
                ];
        }

        if($range)array_push($curl_param['query']['bool']['must'], $range);


        // if($sortmode==1)$curl_param['sort']=['created_date'=>['order'=>'desc']];

        //////////////

        // $curl_param = [
        //     'sort'=>[
        //         'id'=>[
        //             'order'=>'desc'
        //         ]
        //     ],
        //     'size'=>$size,
        //     'query'=>[
        //         'bool'=>[
        //             'must'=>[
        //                 [
        //                     'fuzzy'=>[
        //                         'title'=>[
        //                             'value'=>$param
        //                         ]
        //                     ]
        //                 ]
        //             ]
                    
        //         ]
        //     ]
        // ];
        // dd(json_encode($curl_param));

        // $curl_must = $curl_param['query']['bool']['must'];

        // if(empty($type))unset($curl_param['query']['bool']['must'][1]);

        // if(empty($platform))unset($curl_param['query']['bool']['must'][2]);
        
        // if(empty($shape))unset($curl_param['query']['bool']['must'][3]);

        // if(empty($startTime))unset($curl_param['query']['bool']['must'][4]);
        // if(empty($last_id) || empty($pages))unset($curl_param['query']['bool']['must'][5]);


        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))
        {
            $data['data'] = [];
            $data['last_id'] = 0;
        }else{
            $data['last_id'] = ($sort=='desc'?$data['data'][0]->id:$data['data'][$size-1]->id);
        }

        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);
    }



    public function getMaxMinfield()
    {
        return view('test.addata.maxinfield');
    }


     /**
     * 获取 摸个字段 最大最小值
     * @return [type] [description]
     */
    public function getMaxMinfieldTest(Request $request)
    {
        //         {
        //     "aggs": {
        //         "max_date": {
        //             "max": {
        //                 "field": "created_date"
        //             }
        //         },
        // "min_date": {
        //             "min": {
        //                 "field": "created_date"
        //             }
        //         }
        //     }
        // }
        $id = empty($request->id)?'':$request->id;
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $curl_param = [
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            'id'=>$id
                        ]
                    ]
                ]
            ],
            'aggs'=>[
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


        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $es_data = json_decode($rs,true);


        if(empty($es_data['hits']['total']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

       
        $item['last_detected'] = empty($es_data['aggregations']['last_detected']['value'])?'':$es_data['aggregations']['last_detected']['value'];

        $item['first_detected'] = empty($es_data['aggregations']['last_detected']['value'])?'':$es_data['aggregations']['first_detected']['value'];
        
        $data['data'] = $item;
        $data['status'] = 200;
        
        return HttpUtil::retrun_json($data,200);


    }


     /**
     * 根据 域名 
     * 获取 媒体 广告主 追踪者等数量统计
     * finn
     * 20160707
     */
    public function getAdsCount(Request $request)
    {
//         {
//     "query": {
//         "term": {
//             "domain": "soufun"
//         }
//     },
//     "aggs": {
//         "group_by_state": {
//             "terms": {
//                 "field": "domain"
//             }
//         }
//     }
// }

        //  $curl_param = [
        //     'query'=>[
        //        'bool'=>[
        //             'must'=>[
        //                 [
        //                     'term'=>[
        //                         $isfield=>1
        //                     ]
        //                 ],
        //                 [
        //                     'term'=>[
        //                         'host'=>$host
        //                     ]
        //                 ]
        //             ]
        //        ]
        //     ],
        //     'aggs'=>[
        //         'group_by_state'=>[
        //             'terms'=>[
        //                 'field'=>$field
        //             ]
        //         ]
        //     ]
        // ];
        // 
        
        return view('test.addata.count');
    }

    /**
     * type 1 trackers  2 advertiser 3 publisher
     * @param  Request $request [description]
     * @return [type]           [description]
     * isbrand  ispublisher istracker
     */
    public function getAdsCountTest(Request $request)
    {
        $host = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;

        if(empty($host) || empty($type) || !is_numeric($type))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);


        $field = ($type==1?"trackers":($type==2?"advertiser":"publisher"));
        
        $isfield = ($type==1?"istracker":($type==2?"isbrand":"ispublisher"));

        $curl_param = [
            'query'=>[
               'bool'=>[
                    'must'=>[
                        [
                            'term'=>[
                                $isfield=>1
                            ]
                        ],
                        [
                            'term'=>[
                                'host'=>$host
                            ]
                        ]
                    ]
               ]
            ]
        ];


        $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
       
        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }




     /**
     * 根据 输入内容 查询
     * $param 输入内容
     * filed 字段
     */
    public function getInfoByTitleTest(Request $request)
    {
        $title = empty($request->title)?'':$request->title;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        if(empty($title))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        //多字段查询
        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'multi_match'=>[
                    'query'=>$title,
                    'type'=>'best_fields',
                    'fields'=>[
                        'title','domain','keywords'
                    ],
                    'tie_breaker'=>0.3,
                    'minimum_should_match'=>'30%'
                ]
                
            ]
        ];

        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
       
        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }






     /**
     * 获取 domain 所有 信息 
     * type 简化返回数据 0 返回所有字段 1 简化字段
     * @return [type] [description]
     */
    public function getAllTest(Request $request)
    {

        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $type = empty($request->type)?'':$request->type;



        
        if(!in_array($sort, ['desc','asc','DESC','ASC']))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size
        ];


        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');
        if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        $data['status'] = empty($data['data'])?203:200;


        dd($data);

        return HttpUtil::retrun_json($data,200);

    }


    /**
     * 根据 编号 获取  addata 信息
     * @return [type] [description]
     */
    public function getAllByIdTest(Request $request)
    {

        $id = empty($request->id)?'':$request->id;

       
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $es = ESUtil::getInfoById(config('param.ADDATA_SEARCH'),$id);
        $data['data'] = $es;
        $data['status'] = empty($es)?203:200;

        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 根据 编号 数组 查询
     * @return [type] [description]
     */
    public function getAllByIdsTest(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;
        $sort = empty($request->sort)?'desc':$request->sort;


        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $id_arr = [];
        foreach ($ids as $key => $value) {
            if($value)$id_arr[$key] = $value;
        }

        if(empty($id_arr))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        if($sort && !in_array($sort, ['desc','asc','DESC','ASC']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }

        sort($id_arr);

        $ids_num = count($id_arr);

        $min_id = $id_arr[0];
        $max_id = $id_arr[$ids_num-1];


        $curl_param = [
            'sort'=>[
                    'id'=>'desc'
            ],
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        'range'=>[
                            'id'=>[
                                'gte'=>$min_id,
                                'lte'=>$max_id
                            ]
                        ]
                    ]
                ]
            ]
        ];

       
        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203]);
       
        $data['status'] = empty($data['data'])?203:200;

        return HttpUtil::retrun_json($data,200);

    }


    /**
     * 过滤 胡获取 domain 所有信息
     * param 搜索 内容
     * page 当前页
     * orderBy 按照什么分组
     * sort 排序 默认 desc
     * platform 平台　 1 pc  2 移动 
     * date  日期 一年 一个月 一周 当天
     * @return [type] [description]
     */
    public function getAllFilterTest(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if(($size && $size > 10000) || !is_numeric($size))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }

        $limit = empty($request->limit)?config('param.LIMIT'):$request->limit;

        $last_id = empty($request->last_id)?0:$request->last_id;

        $sort = empty($request->sort)?'desc':$request->sort;

        if(!in_array($sort, ['desc','asc']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        $param = empty($request->param)?'':$request->param;


        $pages = empty($request->page)?'':$request->page; // searchads

        if($pages)$size = $pages * $limit;

        
        $platform = empty($request->platform)?'':$request->platform; // 所有终端　１　ｐｃ　　２　移动

        $date = empty($request->date)?'':$request->date;  // 1 一年内 2 一月内 3 一周内 4 一天内 0 不限时间


        
        $startTime = empty($request->start_time)?'':$request->start_time;
        $endTime = empty($request->end_time)?'':$request->end_time;

        // $sortmode = empty($request->sortmode)?'':$request->sortmode;  // 时间 1  相关性 2

        $shape = empty($request->shape)?'':$request->shape;  // 0  所有尺寸  1  ， 2 ，3 ...
        $type = empty($request->type)?'':$request->type; // 1 图片 2 视频 3 flush


        if($platform>2 || $date > 4 || $type > 3 || $shape > 5)
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }


        if(($shape && !is_numeric($shape)) || ($type && !is_numeric($type)))
        {
             return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }

        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        

        if(($date && $startTime) || ($date && $endTime))
        {
            $data['status'] = '204';
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        if($startTime)
        {
            if($endTime < $startTime)
            {
                $data['status'] = '205';
                $data['data'] = [];
                return HttpUtil::retrun_json($data,200);
            }
        }

   

        if($startTime)$startTime = strtotime($startTime.' 01:00:00');
        if($endTime)$endTime = strtotime($startTime.' 24:59:59');


        if(!$startTime || !$endTime)
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }
        
        if($date)
        {
            $year = date('Y',time());
            $mouth = date("Y-m",time());
            $mouth_true = date('m',time());
            // 计算 查询 时间
            $parseTime = DBUtil::parseTime($year,$mouth,$mouth_true);

            $prre_date = $parseTime['prre_date'];
            $next_date = $parseTime['next_date'];

            $now = date('Y-m-d',time());


            switch ($date) {
                case '1':
                    # code...一年内
                    $startTime = strtotime($year.'-01-01 01:00:00');
                    $endTime = strtotime($year.'-12-31 24:59:59');
                    break;
                case '2':
                    # code... 一月内
                    $startTime = strtotime($mouth.' 01:00:00');
                    $endTime = strtotime($mouth.' 24:59:59');
                    break;
                case '3':
                    # code...一周内
                    $startTime = strtotime($prre_date.' 01:00:00');
                    $endTime = strtotime($next_date.' 24:59:59');
                    break;
                case '4':
                    $startTime = strtotime($now.' 01:00:00');
                    $endTime = strtotime($now.' 24:59:59');
                    break;

            }
        }

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

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
                            'fuzzy'=>[
                                'title'=>$param
                            ]
                        ],
                        [
                            'term'=>[
                                'type'=>$type
                            ]
                        ],
                        [
                            'term'=>[
                                'platform'=>$platform
                            ]
                        ],
                        [
                            'term'=>[
                                'shape'=>$shape
                            ]
                        ],
                        [
                            'range'=>[
                                'created_date'=>[
                                    'gte'=>$startTime,
                                    'lt'=>$endTime
                                ]
                            ]
                        ],
                         [
                            'range'=>[
                                'id'=>[
                                    'gte'=>$last_id
                                ]
                            ]
                        ]
                        
                    ]
                    
                ]
            ]
        ];



        $curl_must = $curl_param['query']['bool']['must'];

        if(empty($type))unset($curl_param['query']['bool']['must'][1]);

        if(empty($platform))unset($curl_param['query']['bool']['must'][2]);
        
        if(empty($shape))unset($curl_param['query']['bool']['must'][3]);

        if(empty($startTime))unset($curl_param['query']['bool']['must'][4]);
        if(empty($last_id) || empty($pages))unset($curl_param['query']['bool']['must'][5]);


        $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))
        {
            $data['data'] = [];
            $data['last_id'] = 0;
        }else{
            $data['last_id'] = ($sort=='desc'?$data['data'][0]->id:$data['data'][$size-1]->id);
        }

        $data['status'] = empty($data['data'])?203:200;



        return HttpUtil::retrun_json($data,200);
    }

}

      //   type":      "cross_fields", 
      // "operator":   "and",
      // 
      // {
//     "query": {
//         "bool": {
//             "must": [
//                 {
//                     "multi_match": {
//                         "query": "百度",
//                         "type": "cross_fields",
//                         "operator": "or",
//                         "fields": [
//                             "host",
//                             "cname",
//                             "ename"
//                         ]
//                     }
//                 }
//             ],
//             "must_not": [
//                 {
//                     "query_string": {
//                         "default_field": "_all",
//                         "query": "家居 音乐 商桥"
//                     }
//                 }
//             ],
//             "should": []
//         }
//     }
// }

        //多字段查询
        


        //  $curl_param = [
        //     'from'=>$from,
        //     'size'=>$size,
        //     'sort'=>[
        //         'created_date'=>[
        //             'order'=>'desc'
        //         ]
        //     ],
        //     'query'=>[
        //         'bool'=>[
        //             'must'=>[
        //                 'multi_match'=>[
        //                     'query'=>$param,
        //                     'type'=>'cross_fields',
        //                     'operator'=>'and',
        //                     'fields'=>[
        //                         "title","advertiser","metas","publisher"
        //                     ]
        //                 ]
        //             ],
        //             'must_not'=>[
        //                 'query_string'=>[
        //                     'default_field'=>'_all',
        //                     'query'=>$removeParam
        //                 ]
        //             ]
        //         ]
                
        //     ]
        // ];

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
