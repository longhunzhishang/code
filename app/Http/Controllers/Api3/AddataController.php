<?php

namespace App\Http\Controllers\Api3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
USE App\Http\Utils\DBUtil;
use App\Synonym;
use Illuminate\Support\Facades\Redis as Redis;
use DB;
use App\Addata;
use App\Http\Utils\DSLUtil;
use Excel;

/**
 * finn
 * 20170706
 * domain api
 */
class AddataController extends Controller
{
     /**
      * 2016-12-08
      * finn 
     * 获取 domain 所有 信息 
     * type 0 返回所有数据 1 简化数据
     * wd 搜索内容
     * en_ch 1 英文 2 中文 3 中英混合
     */
    public function getAll(Request $request)
    {
       
        $key_cache = md5(implode('', $request->all()));
        
        $size = empty($request->size)?config('param.SIZE'):$request->size;
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
                'query'=>DSLUtil::baseTerm('id',$suid)
            ];

            // dd(json_encode($curl_param_subject));
            // exit;
            $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param_subject,"POST");

            $data_sub = DBUtil::get_parse_es_data($rs);

            $subject_md5 = empty($data_sub['data'][0]->md5)?'':$data_sub['data'][0]->md5;
            $curl_param_d = [
                'size'=>$size,
                'sort'=>DSLUtil::sortByTime('date5','desc'),
                'query'=>DSLUtil::baseTerm('subject_md5',$subject_md5)
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
            //$data_d = $this->getAtt08($data_d);

            $data_d['status'] = 200;

            DBUtil::setRedisData($key_cache,$data_d,config('param.REDIS_TIME'));

            return HttpUtil::retrun_json($data_d,200);
            exit;
        }
        $en_ch = DBUtil::check_str_cn_en($oldparam);
        $synonym= '';

        if(empty($iscampaign) || $en_ch==1)$synonym = $this->getSynonym($en_ch,$param);


        // $synonym = empty($iscampaign)?'':$synonym = $this->getSynonym($en_ch,$param);

         // dd($synonym);
        //暂时加上
        // if($en_ch==1)
        // {
        //     $synonym = $this->getSynonym($en_ch,$param);
        // }

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

        $param = empty($c_param)?$param:$c_param.' '.$c_host;


        // dd($param);

        // $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));
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
                                        'advertiser_name_title',
                                        'publisher_full'
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ]//,
                            // 'must'=>[
                            //     [
                            //         'range'=>[
                            //             'date5'=>[
                            //                 'gte'=>$min_date
                            //             ]
                            //         ]
                            //     ]
                            // ]
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
                            ]//,
                            // 'must'=>[
                            //     [
                            //         'range'=>[
                            //             'date5'=>[
                            //                 'gte'=>$min_date
                            //             ]
                            //         ]
                            //     ]
                            // ]
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

        if($iscampaign==1)$curl_param = $curl_param_compan;


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }

        // if(DBUtil::check_str_cn_en($oldparam)==1)
        // {
        //     $curl_param['query']['function_score']['query']['bool']['should']['multi_match']['fields'][] = 'trackers';
        //     $curl_param['query']['function_score']['query']['bool']['should']['multi_match']['fields'][] = 'tags';
        // }


        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);


        if(in_array($oldparam, config('param.WHITE_MIN_SCORE')))
        {
            $curl_param['min_score'] = 0;
        }
        // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');

        // dd(json_encode($curl_param));

       // $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');

        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);


        // dd($data);

        if(empty($data['data']) || !count($data['data'])) $data = DBUtil::resetGetData($curl_param,config('param.ADDATA_SEARCH'));

        $data = $this->getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        // 获取 attr08 属性 finn 2017-4-17 解决  html5 问题
        // $data = $this->getAtt08($data);

        $data['status'] = 200;
        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }


    // 获取最近创意 
    // finn 2017-8-4
    public function getAllNew(Request $request)
    {
        
        $size = empty($request->size)?config('param.SIZE'):$request->size;
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
        $synonym= '';
        if(empty($iscampaign))$synonym = $this->getSynonym($en_ch,$param);

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];
        
        $param = empty($c_param)?$param:$c_param;

        // $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));
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
                                        'advertiser_name_title',
                                        'publisher_full',
                                        'trackers',
                                        'tags'
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
                                        'advertiser_name_title',
                                        'publisher_full',
                                        'trackers',
                                        'tags'
                                    ]
                                ]
                            ]//,
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

        if($iscampaign==1)$curl_param = $curl_param_compan;


        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }



        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);

        // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');

        // dd(json_encode($curl_param));

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source=id,domain,title,advertiser,publisher,trackers,thumb_url';
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // $data = $this->getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $data['status'] = 200;

        foreach ($data['data'] as $key => &$value) {
            $value->ads_url = 'http://file.adbug.cn/datasync/'.$value->domain.'/'.$value->thumb_url;
        }



        // http://file.adbug.cn/datasync/com.tencent.news/172bc7dc112a49b1ab6102dfbc56e704.jpg
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 只返回 编号 
     * fun 需要的接口 
     * 2017-4-25
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAllIds(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
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
        
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        // if(($size && $size > 10000) || !is_numeric($size))
        // {
        //     return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        // }


        $sort = empty($request->sort)?'desc':$request->sort;

        if(!in_array($sort, ['desc','asc','DESC','ASC']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        $param = empty($request->wd)?'':$request->wd;
        $oldparam = urldecode($param);
        $param = urldecode($param);

        $en_ch = DBUtil::check_str_cn_en($oldparam);

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
        $page = empty($request->page)?'':$request->page; // searchads

        $from = 0;
        if($page>1)$from = $page * $size;
        
        $platform = empty($request->platform)?'':$request->platform; // 所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android
        
        $startTime = empty($request->start_time)?'':$request->start_time;
        $endTime = empty($request->start_time)?'':$request->end_time;

        $shape = empty($request->shape)?'':$request->shape;  // 0  所有尺寸  1  ， 2 ，3 ...
        $type = empty($request->type)?'':$request->type; // 1 图片 2 视频 3 flush 4 html5 5 原生

        if($platform>4 || $type > 5 || $shape > 5)return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        // 相关性 查询
        $sortmode = empty($request->sortmode)?'':$request->sortmode;


        if(($shape && !is_numeric($shape)) || ($type && !is_numeric($type)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $attribute04 = ''; // Android IOS
        $material = '';//原生字段 native
        if($type)
        {
            switch ($type) {
                case '1':
                    $type = 'image';
                    break;
                case '2':
                    $type = 'swf';
                    break;
                case '3':
                    $type = 'flv';
                    break;
                case '4':
                    $type = 'html5';
                    break;
                case '5':
                    $material = 'native';
                    break;
            }
        }

        if($platform>2)$attribute04 = ($platform==3?'Android':'IOS');

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

        $synonym = '';
        // $synonym = empty($iscampaign)?'':$synonym = $this->getSynonym($en_ch,$param);

        if(empty($iscampaign) || $en_ch==1)$synonym = $this->getSynonym($en_ch,$param);


        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

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
                                        'advertiser_name_title',
                                        'publisher_full'
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
            '_source'=>[
                'id'
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
                                        'advertiser_name_title',
                                        'publisher_full'
                                    ]
                                ]
                            ],
                            'must'=>[
                            ]
                        ]
                    ],
                    'functions'=>[
                        
                    ],
                    'score_mode'=>'max',
                    'max_boost'=>'10'
                ],
            ],
            'sort'=>DSLUtil::baseSort(),
            '_source'=>[
                'id'
            ]
        ];

        if($iscampaign==1)$curl_param = $curl_param_compan;



        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'][] = 'tags';
        }


        

        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);


        if(in_array($oldparam, config('param.WHITE_MIN_SCORE')))
        {
            $curl_param['min_score'] = 0;
        }

        if($iscampaign==2)$curl_param['query']['function_score']['score_mode'] = 'max';



        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $term_k = ($key=='attribute04'?'match':'term');

                $item = [
                    $term_k=>[
                          $key=>$value
                    ]
                ];
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                }else{
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
                }
            }
        }

        $range[] = '';
        if($startTime && $endTime && empty($is_time)){

              $range[] = [
                'range'=>[
                    'date5'=>[
                            'gte'=>date('Y-m-d H:i:s',$startTime),
                            'lte'=>date('Y-m-d H:i:s',$endTime)
                        ]
                    ]
                ];
            
        }else if($startTime){

            $range[] = [
                'range'=>[
                    'date5'=>[
                            'gte'=>date('Y-m-d H:i:s',$startTime),
                        ]
                    ]
                ];
        }
       
        
        if($max_width && empty($is_width))
        {
            $range[] = [
                    'range'=>[
                        'width'=>[
                            'gte'=>$min_width,
                            'lte'=>$max_width
                        ]
                    ]
                ];
            
        }
        if($max_height && empty($is_height))
        {
            $range[] = [
                'range'=>[
                    'height'=>[
                        'gte'=>$min_height,
                        'lte'=>$max_height
                    ]
                ]
            ];
            
        }

        if($range)
        {
            foreach ($range as $key => $value) {
              if($value)
              {
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $value);
                }else{
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);
                }
              }
            }
        }
        // dd(json_encode($curl_param));
    
        $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');


        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?scroll=2m';

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $return_id = [];
        $data = DBUtil::get_parse_es_data_id($rs);


        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }


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

        $data = [];
        
        // 获取 attr08 属性 finn 2017-4-17 解决  html5 问题
        // $data = $this->getAtt08($data);
        $data['status'] = 200;
        $data['total'] = count($return_id);
        foreach ($return_id as $key => $value) {
            $data['data'][] = $value->id;
        }
        // $data['data'] = $return_id;
        
        // DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }
    /**
     * finn 2017-4-17 解决  html5 问题
     * 2017-4-21 取消 att08 属性 已经移到 es 里面
     * @param  [type] $arr [description]
     * @return [type]      [description]
     */
    private function getAtt08($arr)
    {
        foreach ($arr['data'] as $key => &$value) {
            $value = is_array($value)?$value:(array)$value;
            $id = $value['id'];
            $type = $value['type'];
            $attr_url = '';
            if($type=='html5')
            {
                $ad = Addata::find($id);
                $value['attr_url'] = $ad->attribute08;
            }
        }
        return $arr;
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

            $synonym = !count($synonym)?Synonym::where('ename','like','%'.$param.'%')->orwhere('host','like','%'.$param.'%')->limit(3)->get():$synonym;

        }else
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
     * 根据 iscampaign 添加 参数
     * @return boolean [description]
     */
    private function isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param)
    {
        // dd($iscampaign);
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



            array_push($curl_param['query']['function_score']['filter']['bool']['should']['multi_match']['fields'], 'publisher');
        }
        return $curl_param;
    }
    /**
     * 条件 添加 
     * curl_param 待添加 参数 domain_arr 域名 条件参数 param 参数对应的值
     * @return [type] [description]
     */
    private function compainCurl($curl_param,$domain_arr,$param)
    {
        
        foreach ($domain_arr as $key => $value) {
            $item = [
                'filter'=>[
                    'match_phrase'=>[
                        $value=>$param
                    ]
                ],
                "weight"=>'10'
            ];
            array_push($curl_param['query']['function_score']['functions'], $item);
        }
        return $curl_param;
    } 

    /**
     * 获取 广告主
     * hit_advertisers 
     * 2017-3-31
     * 2017-6-13 更改 hit_advertisers  查询
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAllAds(Request $request)
    {
        $key_cache = md5(implode('', $request->all()));
        if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        {
            return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        }
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

        DBUtil::setRedisData($key_cache,$rdata,config('param.REDIS_TIME'));
        
        return HttpUtil::retrun_json($rdata,200);

    }
    /**
     * 根据 收索 内容 获取数据
     * 格式 wd=baidu size=10 height=200 width=100
     * @param Request $request
     * @return $this
     */
    public function getAllInfo(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);
        // dd($request->all());
        $page = empty($request->page)?1:$request->page;
        $from = 0;
        $size = empty($request->size)?config('param.SIZE'):$request->size;

        $start_time = empty($request->start_time)?'':$request->start_time;
        $end_time = empty($request->end_time)?'':$request->end_time;
        $min_width = empty($request->min_width)?'':$request->min_width;
        $max_width = empty($request->max_width)?'':$request->max_width;
        $min_height = empty($request->min_height)?'':$request->min_height;
        $max_height = empty($request->max_height)?'':$request->max_height;

        // 角色关系 must 1 should 2
        $isrolere = empty($request->isrolere)?'':$request->isrolere;
        $ads_role = empty($request->ads_role)?'':$request->ads_role;// 广告主
        $pub_role = empty($request->pub_role)?'':$request->pub_role;// 媒体
        $trac_role = empty($request->trac_role)?'':$request->trac_role;// 追踪者


        if($start_time)$start_time = date('Y-m-d H:i:s',$start_time);
        if($end_time)$end_time = date('Y-m-d H:i:s',$end_time);
        $isshop = empty($request->isshop)?'':$request->isshop;

        $removeParam = empty($request->remove)?"":$request->remove;
        $addParam = empty($request->must)?"":$request->must;
        $orParam = empty($request->or)?'':$request->or;

        $addParam = urldecode($addParam);
        $orParam = urldecode($orParam);
        $removeParam = urldecode($removeParam);


        $shop_arr = config('param.SHOP_DOMAIN');
        // 非电商
        if($isshop==2)
        {
            foreach ($shop_arr as $key => $value) {
                $removeParam = $removeParam .' '.$value;
            }
        }
        // 电商
        if($isshop==1)
        {
            foreach ($shop_arr as $key => $value) {
                $addParam = $addParam .' '.$value;
            }
        }
        $type = empty($request->type)?'':$request->type;
        $platfrom = empty($request->platfrom)?'':$request->platfrom; 
        $shape = empty($request->shape)?'':$request->shape;

        if(!is_numeric($page) || !is_numeric($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if($page > 1)$from = $size * ($page-1);
    
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'min_score'=>2,
            'sort'=>DSLUtil::baseSort(),
            'query'=>[
                'function_score'=>[
                    'filter'=>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'multi_match'=>[
                                        'query'=>$param,
                                        'fields'=>[
                                            'title',
                                            'advertiser_name',
                                            'advertiser_name_title',
                                            'publisher_full',
                                            'publisher',
                                            'trackers',
                                            'tags'
                                        ]
                                    ]
                                ]
                            ],
                            'should'=>[]
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
                            'filter'=>[
                                'match_phrase'=>[
                                    'publisher_full'=>$param
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
            'post_filter'=>[
                 'bool'=>[
                    'must'=>[
                        [
                            'multi_match'=>[
                                'query'=>$addParam,
                                'type'=>'cross_fields',
                                'operator'=>($isshop==1?'or':'and'),
                                'fields'=>[
                                    'advertiser_name','advertiser_name_title','title','publisher','trackers','publisher_full'
                                    
                                ]
                            ]
                       ]
                    ],
                    'must_not'=>[
                        [
                            'multi_match'=>[
                                'query'=>$removeParam,
                                'fields'=>[
                                    'advertiser_name','advertiser_name_title','title','publisher','trackers','publisher_full'
                                ]
                            ]
                       ]
                    ],
                    'should'=>[
                        [
                            'multi_match'=>[
                                'query'=>$orParam,
                                'type'=>'cross_fields',
                                'operator'=>'or',
                                'fields'=>[
                                    'advertiser_name','advertiser_name_title','title','publisher','trackers','publisher_full'
                                ]
                            ]
                       ]
                    ]
                ]
            ]
        ];

        // 文件类型
        if($type)
        {
            $type_tmp = explode(',', $type);
            foreach ($type_tmp as $key => $value) {
                if(empty($value))unset($key);
            }
            if(count($type_tmp)>1)
            {
                foreach ($type_tmp as $key => $value) {
                    if($value)
                    {

                        $type_material = '';
                        switch ($value) {
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

                        $item_key = $value==5?'material':'type';

                        $item = [
                            'term'=>[
                                $item_key=>$type_material
                            ]
                        ];
                        array_push($curl_param['query']['function_score']['filter']['bool']['should'], $item);
                    }
                }
            }else{
                $type_material = '';
                        switch ($value) {
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

                        $item_key = $value==5?'material':'type';

                        $item = [
                            'term'=>[
                                $item_key=>$type_material
                            ]
                        ];
                array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
            }
        }

        // 形状
        if($shape)
        {
            $shape_tmp = explode(',', $shape);

            foreach ($shape_tmp as $key => $value) {
                if(empty($value))unset($key);
            }

            if(count($shape_tmp)>1)
            {
                foreach ($shape_tmp as $key => $value) {
                    if($value)
                    {
                        $item = [
                            'term'=>[
                                'shape'=>$value
                            ]
                        ];
                        array_push($curl_param['query']['function_score']['filter']['bool']['should'], $item);
                    }
                }
            }else{
                $item = [
                    'term'=>[
                        'shape'=>$value
                    ]
                ];
                array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
            }
        }
        // 平台
        if($platfrom)
        {
            $platform_tmp = explode(',', $platfrom);

            foreach ($platform_tmp as $key => $value) {
                if(empty($value))unset($key);
            }
            $term_field = 'term';
            if(count($platform_tmp)>1)
            {
                foreach ($platform_tmp as $key => $value) {
                    if($value)
                    {
                        $item_key = '';
                        $item_value = '';
                        
                        if($value>2)
                        {
                            $item_key = 'attribute04';
                            $item_value = ($value==3?'Android':'IOS');
                            $term_field = 'match';

                        }else{
                            $item_key = 'platform';
                            $item_value = $value;
                        }


                        $item = [
                            $term_field=>[
                                $item_key=>$item_value
                            ]
                        ];
                        array_push($curl_param['query']['function_score']['filter']['bool']['should'], $item);
                    }
                }
            }else{
                $item_key = ($value>2?'attribute04':'platform');
                $item_value = $value;
                if($value>2)
                {
                    $term_field = 'match';
                    $item_value = ($value==3?'Android':'IOS');
                }
                $item = [
                    $term_field=>[
                        $item_key=>$item_value
                    ]
                ];
                array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
            }
        }

        if(empty($addParam) && empty($removeParam) && empty($orParam))
        {
            unset($curl_param['post_filter']);
        }else{
            if(empty($addParam))unset($curl_param['post_filter']['bool']['must']);
            if(empty($removeParam))unset($curl_param['post_filter']['bool']['must_not']);
            if(empty($orParam))unset($curl_param['post_filter']['bool']['should']);
        }
        $min_width = min($min_width,$max_width);
        $max_width = max($min_width,$max_width);
        $min_height = min($min_height,$max_height);
        $max_height = max($min_height,$max_height);
        // 时间
        if($start_time && $end_time)
        {
            $item = [
                'range'=>[
                    'date5'=>[
                        'gte'=>$start_time,
                        'lte'=>$end_time
                    ]
                ]
            ];
            array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
        }else if($start_time || $end_time)
        {
            if($start_time)
            {
                $item = [
                    'range'=>[
                        'date5'=>[
                            'gte'=>$start_time
                        ]
                    ]
                ];
            }
            if($end_time)
            {
                $item = [
                    'range'=>[
                        'date5'=>[
                            'lte'=>$end_time
                        ]
                    ]
                ];
            }
            array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
        }

        if($min_width && $max_width)
        {
            $item =[
                'range'=>[
                    'width'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_width
                    ]
                ]
                
            ];
            array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
        }else if($max_width){
            $item=[
                'range'=>[
                    'width'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_width
                    ]
                ]
                
            ];
            array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
        }

        if($min_height && $max_height)
        {
             $item =[
                'range'=>[
                    'width'=>[
                        'gte'=>$min_height,
                        'lte'=>$max_height
                    ]
                ]
                
            ];
            array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
        }else if($max_height){
            $item=[
                'range'=>[
                    'width'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_height
                    ]
                ]
                
            ];
            array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
        }

        if($max_height)
        {
             $curl_param['query']['bool']['must'][]=[
                'range'=>[
                    'height'=>[
                        'gte'=>$min_height,
                        'lte'=>$max_height
                    ]
                ]
                
            ];
        }

        $role_filter_arr = [];
        if($ads_role)
        {
            $role_filter_arr[] = [
                'match_phrase'=>[
                    'advertiser'=>[
                        'query'=>$ads_role,
                        'minimum_should_match'=>'100%'
                    ]
                ]
            ];
        }

        if($pub_role)
        {
            $role_filter_arr[] = [
                'match_phrase'=>[
                    'publisher'=>[
                        'query'=>$pub_role,
                        'minimum_should_match'=>'100%'
                    ]
                ]
            ];

            $pub_filter_match = [
                'filter'=>[
                    'match_phrase'=>[
                        'publisher'=>[
                          'query'=>$pub_role,
                          'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'4'
            ];

            array_push($curl_param['query']['function_score']['functions'], $pub_filter_match);
            unset($pub_filter_match);
        }

        if($trac_role)
        {
            $role_filter_arr[] = [
                'match_phrase'=>[
                    'trackers'=>[
                        'query'=>$trac_role,
                        'minimum_should_match'=>'100%'
                    ]
                ]
            ];
            unset($curl_param['min_score']);

            $trac_filter_match = [
                'filter'=>[
                    'match_phrase'=>[
                        'publisher'=>[
                          'query'=>$trac_role,
                          'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'4'
            ];

            array_push($curl_param['query']['function_score']['functions'], $trac_filter_match);
            unset($trac_filter_match);


            $tags_filter_match = [
                'filter'=>[
                    'match_phrase'=>[
                        'tags'=>[
                          'query'=>$trac_role,
                          'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'4'
            ];

            array_push($curl_param['query']['function_score']['functions'], $tags_filter_match);
            unset($tags_filter_match);
        }

        if($isrolere==1)
        {
            foreach ($role_filter_arr as $key => $value) {
                array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);
            }

        }elseif($isrolere==2)
        {
            foreach ($role_filter_arr as $key => $value) {
                array_push($curl_param['query']['function_score']['filter']['bool']['should'], $value);
            }
        }
        // dd(json_encode($curl_param));
        $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        //非电商
        if($isshop==2)
        {
            foreach ($data['data'] as $key => $value) {

                foreach ($shop_arr as $k => $v) {
                    if(strpos($value->target_url, $v)!==false)
                    {
                        unset($data['data'][$key]);
                        break;
                    }
                }
            }
        }
        $data = $this->getadvertiserDomain($data);

       // $data = $this->getAtt08($data);
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 主页 收索 过滤 条件
     * 2016-12-13 新增 IOS  Android 
     * html5 原生广告 过滤
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getAllFilterSearch(Request $request)
    {
        $key_cache = md5(implode('', $request->all()));
        // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        // {
        //     return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        // }

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
        $en_ch = empty($request->en_ch)?'':$request->en_ch;

        $size = empty($request->size)?config('param.SIZE'):$request->size;

        if(($size && $size > 10000) || !is_numeric($size))
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
        $page = empty($request->page)?'':$request->page; // searchads

        $from = 0;
        if($page>1)$from = $page * $size;
        
        // 2017-8-28 新增 ott 过滤条件 5
        $platform = empty($request->platform)?'':$request->platform; // 所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android
        
        $startTime = empty($request->start_time)?'':$request->start_time;
        $endTime = empty($request->start_time)?'':$request->end_time;

        $shape = empty($request->shape)?'':$request->shape;  // 0  所有尺寸  1  ， 2 ，3 ...
        $type = empty($request->type)?'':$request->type; // 1 图片 2 视频 3 flush 4 html5 5 原生

        if($platform>5 || $type > 5 || $shape > 5)return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        // 相关性 查询
        $sortmode = empty($request->sortmode)?'':$request->sortmode;


        if(($shape && !is_numeric($shape)) || ($type && !is_numeric($type)))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $attribute04 = ''; // Android IOS
        $material = '';//原生字段 native
        if($type)
        {
            switch ($type) {
                case '1':
                    $type = 'image';
                    break;
                case '2':
                    $type = 'swf';
                    break;
                case '3':
                    $type = 'flv';
                    break;
                case '4':
                    $type = 'html5';
                    break;
                case '5':
                    $material = 'native';
                    break;
            }
        }

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

        $synonym = '';
        // $synonym = empty($iscampaign)?'':$synonym = $this->getSynonym($en_ch,$param);



        //  // dd($synonym);
        // //暂时加上
        // if($en_ch==1)
        // {
        //     $synonym = $this->getSynonym($en_ch,$param);
        // }

        if(empty($iscampaign) || $en_ch==1)$synonym = $this->getSynonym($en_ch,$param);

        $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
        $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];

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
                                        'advertiser_name_title',
                                        'publisher_full'//,
                                        //'trackers',
                                        //'tags'
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
                                        'advertiser_name_title',
                                        'publisher_full'//,
                                        // 'trackers',
                                        // 'tags'
                                    ]
                                ]
                            ],
                            'must'=>[
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

        if($iscampaign==1)$curl_param = $curl_param_compan;
        // 正常
        $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);

        if($iscampaign==2)$curl_param['query']['function_score']['score_mode'] = 'max';



        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $term_k = ($key=='attribute04'?'match':'term');

                $item = [
                    $term_k=>[
                          $key=>$value
                    ]
                ];
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                }else{
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
                }
            }
        }

        $range[] = '';
        if($startTime && $endTime && empty($is_time)){

              $range[] = [
                'range'=>[
                    'date5'=>[
                            'gte'=>date('Y-m-d H:i:s',$startTime),
                            'lte'=>date('Y-m-d H:i:s',$endTime)
                        ]
                    ]
                ];
            
        }else if($startTime){

            $range[] = [
                'range'=>[
                    'date5'=>[
                            'gte'=>date('Y-m-d H:i:s',$startTime),
                        ]
                    ]
                ];
        }
       
        
        if($max_width && empty($is_width))
        {
            $range[] = [
                    'range'=>[
                        'width'=>[
                            'gte'=>$min_width,
                            'lte'=>$max_width
                        ]
                    ]
                ];
            
        }
        if($max_height && empty($is_height))
        {
            $range[] = [
                'range'=>[
                    'height'=>[
                        'gte'=>$min_height,
                        'lte'=>$max_height
                    ]
                ]
            ];
            
        }

        if($range)
        {
            foreach ($range as $key => $value) {
              if($value)
              {
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $value);
                }else{
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);
                }
              }
            }
        }
        // dd(json_encode($curl_param));
        // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);
        $data['last_id'] = 0;
        if(empty($data))$data['data'] = [];


        $data = $this->getadvertiserDomain($data);

        // 按照 时间 进行过滤
        if($sortmode==1)$data['data'] = DBUtil::my_sort($data['data'],'created_date',SORT_DESC,SORT_NUMERIC);
        

        // if(!empty($data['data']))
        // {
        //     $data = $this->getAtt08($data);
        // }

        $data['status'] = empty($data['data'])?203:200;


        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return HttpUtil::retrun_json($data,200);
    }

        /**
     * 获取 domain 所有 信息 
     * type 0 返回所有数据 1 简化数据
     * wd 搜索内容
     * @return [type] [description]
     */
    public function getAllSubject(Request $request)
    {
        set_time_limit(0);
        $key_cache = md5(implode('', $request->all()).'allsubject');
        if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        {
            return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        }

         $size = empty($request->size)?config('param.SIZE'):$request->size;
         $sort = empty($request->sort)?'desc':$request->sort;
         $type = empty($request->type)?'':$request->type;
         $param = empty($request->wd)?'':$request->wd;
         $param = urldecode($param);
         $oldparam = $param;

         $iscampaign = empty($request->iscampaign)?'':$request->iscampaign;
         $en_ch = empty($request->en_ch)?'':$request->en_ch;

         $page = empty($request->page)?1:$request->page;

         if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

         if(($type && !is_numeric($type)))
         {
             $data['status'] = 206;
             $data['data'] = [];
             return HttpUtil::retrun_json($data,200);
         }

         $synonym= '';
         if(empty($iscampaign))$synonym = $this->getSynonym($en_ch,$param);

         $c_param = empty($synonym['c_param'])?'':$synonym['c_param'];
         $c_host = empty($synonym['c_host'])?'':$synonym['c_host'];


         $param = empty($c_param)?$param:$c_param;


         $min_date = date("Y-m-d H:i:s", strtotime("-1 year"));


         // echo date('Y-m-d H:i:s',time()),'<br/>';
         // 营销活动
         // if($page>1)$from = $size * $page;
         $size_all = $page * $size;
         $curl_param = [
             'size'=>0,
             'min_score'=>2,
             'query'=>[
                 'function_score'=>[
                     'filter'=>[
                         'bool'=>[
                            'must'=>[
                                'range'=>[
                                    'date5'=>[
                                        'gte'=>$min_date
                                    ]
                                ]
                            ],
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
                             ]
                         ]
                     ],
                     'functions'=>[
                        
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
                                 'max_time'=>'desc'
                             ]
                            
                         ]
                     ],
                     'aggs'=>[
                         'max_score'=>[
                             'max'=>[
                                 // 'field'=>'created_date'
                                 'script'=>'_score'
                             ]
                         ],
                         'max_time'=>[
                             'max'=>[
                                 'field'=>'created_date'
                             ]
                         ]
                     ]
                 ]
             ]
         ];

         // 营销活动
         $curl_param_compan = [
             'size'=>0,
             'min_score'=>1,
             'query'=>[
                 'function_score'=>[
                     'query'=>[
                         'bool'=>[
                            'must'=>[
                                'range'=>[
                                    'date5'=>[
                                        'gte'=>$min_date
                                    ]
                                ]
                            ],
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
                             ]
                         ]
                     ],
                     'functions'=>[
                        
                     ],
                     'score_mode'=>'max',
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
                                 'max_time'=>'desc'
                             ]
                            
                         ]
                     ],
                     'aggs'=>[
                         'max_score'=>[
                             'max'=>[
                                 'script'=>'_score'
                             ]
                         ],
                         'max_time'=>[
                             'max'=>[
                                 'field'=>'created_date'
                             ]
                         ]
                     ]
                 ]
             ]
         ];

         if($iscampaign==1)$curl_param = $curl_param_compan;


         // dd($curl_param);


         if($en_ch==1)
         {
            $function_score_next = $iscampaign==1?'query':'filter';

            $curl_param['query']['function_score'][$function_score_next]['bool']['should']['multi_match']['fields'][] = 'trackers';
            $curl_param['query']['function_score'][$function_score_next]['bool']['should']['multi_match']['fields'][] = 'tags';
         }


         $curl_param = $this->isCurlCampaignMerge($curl_param,$iscampaign,$c_host,$oldparam,$param);
         // $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');

         // dd(json_encode($curl_param));
         $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');


         // dd(json_encode($curl_param));
         $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
         $rs_des = json_decode($rs,true);

         // dd($rs_des);
         $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];

         $filed_tmp = empty($rs_des['aggregations']['groub']['buckets'])?[]:$rs_des['aggregations']['groub']['buckets'];

         if($page>1)$filed_tmp = DBUtil::removeLimit($size * ($page-1),$filed_tmp);

         // 获取 每条subject 最后出现的时间
         $data = $this->getSubLastDate($filed_tmp);
         $data['total'] = $total;
         // 暂无数据 2002
         if(empty($data['subject']))
         {
             $data['status'] = 203;
             $data['data'] = [];
             return HttpUtil::retrun_json($data,200);
         }
         //根据 ad_id  获取 对应的 创意 编号
         $advertiser_domain = [];
         foreach ($data['subject'] as $key => &$value) {

            $value['attr_url'] = '';
            $value['type'] = '';
            $value['advertiser_host'] = '';
            if(!empty($value['ad_id']) && !in_array($value['ad_id'], $advertiser_domain))
            {
                $advertiser_domain[] = $value['ad_id'];
            }
         }

         $ad_host_curl = [
            'size'=>count($advertiser_domain),
            'query'=>DSLUtil::baseTerms('id',$advertiser_domain)
         ];

         $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?_source=id,advertiser,type,attribute08';

         $rs_ad_domain = HttpUtil::send_curl_methods($url,$ad_host_curl,"POST");
         $domain_advertiser = DBUtil::get_parse_es_data($rs_ad_domain);

   
         foreach ($data['subject'] as $key => $value) {
            if(empty($value['title']))unset($data['subject'][$key]);
         }
         if(!empty($domain_advertiser['data']))
         {
            foreach ($data['subject'] as $key => &$value) {

                foreach ($domain_advertiser['data'] as $k => $v) {

                    $v = (array)$v;
                    if($v['id']==$value['ad_id'] && !empty($v['advertiser']))
                    {
                        $value['advertiser_host'] = $v['advertiser'];

                        $value['type'] = $v['type'];

                        $value['attr_url'] = $v['attribute08'];

                        break;
                    }
                }
            }
         }
        

        $data['status'] = 200;

         DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
         
         return HttpUtil::retrun_json($data,200);
    }
    /**
     * 获取每条数据 对应的域名
     * @param  [type] $data [原始数据]
     * @return [type]       [description]
     */
    public function getadvertiserDomain($data)
    {
        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            
            if(!empty($value->advertiser) && !in_array($value->advertiser, $domain_tmp))
            {
                $domain_tmp[] = $value->advertiser;
            }
        }

        $curl_param = [
            'size'=>count($domain_tmp),
            'query'=>DSLUtil::baseTerms('host_no',$domain_tmp)
        ];

        $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');

        $rs_ad_domain = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $domain_arr = DBUtil::get_parse_es_data($rs_ad_domain);

        $return_data = [];
        foreach ($data['data'] as $key => $value) {
            $return_data[] = (array)$value;
        }
        foreach ($return_data as $key => &$value) {

           if(!empty($value['advertiser']))
           {
                foreach ($domain_arr['data'] as $k => $v) {

                   if($v->host==$value['advertiser'])
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
     * 判断相关创意 log 日志
     * 投放趋势
     * 2017-2-17
     * finn
     * @return [type] [description]
     */
    public function getAdsTrendAgg(Request $request)
    {
        $key_cache = md5(implode('', $request->all()));
        if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
        {
            return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
        }

        $md5 = empty($request->md5)?'':$request->md5;

        if(empty($md5))return HttpUtil::retrun_json(['data'=>[],'206'],200);


        $md5_tmp = explode('_ADS_MD', $md5);


        $return_md5 = [];

        foreach ($md5_tmp as $key => $value) {
            $curl_param = [
                'size'=>0,
                'query'=>DSLUtil::baseTerm('md5',$value),
                'aggs'=>[
                    'ads_num'=>[
                        'terms'=>[
                           'field'=>'date_hour'
                        ]
                    ]
                ]
            ];

            $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $ads_json = json_decode($rs,true);

            $ads_num_tmp = empty($ads_json['aggregations']['ads_num']['buckets'])?[]:$ads_json['aggregations']['ads_num']['buckets'];

            $item['md5'] = $value;
            $item['num'] = count($ads_num_tmp)>1?count($ads_num_tmp):0;
            $return_md5[] = $item;
        }
        
        $data['data'] = $return_md5;
        $data['status'] = 200;
        
        DBUtil::setRedisData($key_cache,$data,config('param.REDIS_TIME'));
        return $data;
    }


    /**
     * 获取 统计数据 趋势
     * @return [type] [description]
     */
    public function getAdsAggsData($max_date,$min_date,$md5,$field)
    {
        $diff_time = DBUtil::diffDate($max_date,$min_date);

        $terms_field = 'date_day';

        if($diff_time['year'])
        {
            $terms_field = 'date_mouth';
        }else if($diff_time['month'])
        {
            if($diff_time['month'] > 1)
            {
                    $terms_field = 'date_mouth';
            }else{
                    $terms_field = 'date_day';
            }
        }else if($diff_time['day'] && $diff_time['day']>7){
                $terms_field = 'date_day';
        }else{
            $terms_field = 'date_day';
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
        
        if(count($ads_num_tmp)<10)
        {

            $terms_field_in = 'date_hour';
            switch ($terms_field) {
                case 'date_mouth':
                    $terms_field_in = 'date_day';
                    break;
                case 'date_day':
                    $terms_field_in = 'date_hour';
                    break;
                case 'date_hour':
                    $terms_field_in = 'date_hour';
                    break;
            }
            $curl_param_in = [
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
                           'field'=>$terms_field_in,
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
            $rs = HttpUtil::send_curl_methods($url,$curl_param_in,"POST");

            $ads_json = json_decode($rs,true);

            $ads_num_tmp_in = empty($ads_json['aggregations']['ads_num']['buckets'])?[]:$ads_json['aggregations']['ads_num']['buckets'];

            $ads_num_aggs_in = [];
            if(!empty($ads_num_tmp_in))
            {
                foreach ($ads_num_tmp_in as $key => $value) {

                    $item_in['date'] = $value['key_as_string'];
                    $item_in['aggs'] = $value['doc_count'];
                    $ads_num_aggs_in[] = $item_in;
                }
            }

            $data['data'] = $ads_num_aggs_in;
            $data['status'] = 200;
            return $data;
        }

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
     * 根据 已知 角色  publisher_na advertiser_na trackers_na
     *  查询 角色 
     *  时间 过滤
     * @return [type] [description]
     */
    public function getAdsTrendAggView(Request $request)
    {
        $cur_role = empty($request->crole)?'':$request->crole;
        $cur_value = empty($request->cvalue)?'':$request->cvalue;
        $qur_role = empty($request->qrole)?'':$request->qrole;
        $qur_value = empty($request->qvalue)?'':$request->qvalue;

        $cur_field = '';

        switch ($cur_role) {
            case 'advertiser':
                $cur_field = 'advertiser_na';
                break;
            case 'publisher':
                $cur_field = 'publisher_na';
                break;
            case 'trackers':
                $cur_field = 'trackers_na';
                break;
            case 'subject_md5':
                $cur_field = 'subject_md5';
                break;
        }

        $start_time = empty($request->start_time)?'':urldecode($request->start_time);
        $end_time = empty($request->end_time)?'':urldecode($request->end_time);

        $terms_field = 'date_day';

        $field_param = [];


        if($cur_field && $cur_value)$field_param[$cur_field] = $cur_value;
        if($qur_role && $qur_value)$field_param[$qur_role] = $qur_value;

        $max_min = '';
        if(!empty($start_time) && !empty($end_time))
        {
            // 获取当前最大时间 最小时间
            $max_min = $this->getMaxMinDate($field_param);

            $diff_time = '';
            // 获取 分割字段
            if($max_min['max'])
            {
                $diff_time = DBUtil::diffDate(date('Y-m-d',$max_min['max']/1000),date('Y-m-d',$max_min['min']));
               
            }else if($start_time && $end_time){
                $diff_time = DBUtil::diffDate($start_time,$end_time);
            }


            if($diff_time && $diff_time['year'])
            {
                $terms_field = 'date_mouth';
            }else if($diff_time && $diff_time['month'])
            {
                if($diff_time['month'] > 1)
                {
                    $terms_field = 'date_mouth';
                }else{
                    $terms_field = 'date_day';
                }
            }else if($diff_time && $diff_time['day'] && $diff_time['day']>7){
                $terms_field = 'date_day';
            }else{
                $terms_field = 'date_day';
            }
        }
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                       
                    ]
                ]
            ],
            'aggs'=>[
                'ads_num'=>[
                    'terms'=>[
                        'field'=>$terms_field,
                        'size'=>0,
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

        if($field_param)
        {
            foreach ($field_param as $key => $value) {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];
                array_push($curl_param['query']['bool']['must'], $item);
            }
        }

        if(!empty($start_time) && !empty($end_time))
        {
                $range = [
                    'range'=>[
                        'date'=>[
                            'gte'=>$start_time,
                            'lte'=>$end_time
                        ]
                    ]
                ];
            array_push($curl_param['query']['bool']['must'], $range);
        }

        if(empty($start_time) && empty($end_time))
        {
            
            //$star_time = mktime(0,0,0,date('m')-3,1,date('y'));
            $star_time= date("Y-m-d H:i:s", strtotime("-1 year"));  

            // $star_time = date('Y-m-d 00:00:00', mktime(0, 0,0, 1, 1, date('Y', $now = time())));  
            // $endTime = date('Y-m-d 23:39:59', mktime(0, 0, 0, 12, 31, date('Y', $now)));  
            $range = [
                'range'=>[
                    'date'=>[
                        'gte'=>$star_time
                    ]
                ]
            ];
            array_push($curl_param['query']['bool']['must'], $range);
        }


        $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');



        // dd(json_encode($curl_param));
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $ads_json = json_decode($rs,true);


        $ads_num_tmp_in = empty($ads_json['aggregations']['ads_num']['buckets'])?[]:$ads_json['aggregations']['ads_num']['buckets'];

        $ads_num_aggs_in = [];
        if(!empty($ads_num_tmp_in))
        {


            foreach ($ads_num_tmp_in as $key => $value) {

                // $item_in[strtotime($value['key_as_string'])] = $value['doc_count'];
                // $item_in['day'] = $value['key_as_string'];
                // $item_in['fatalities'] = $value['doc_count'];
                $ads_num_aggs_in[strtotime($value['key_as_string'])] = $value['doc_count'];
                // unset($item_in);
            }
        }

        $data['data'] = $ads_num_aggs_in;
        $data['status'] = 200;
        return $data;
    }


    /**
     * 根据 时间范围 获取 统计数据
     * @return [type] [description]
     */
    public function getAggByDateRange($time_range,$field_param,$terms_field)
    {

        $data = [];

        foreach ($time_range as $key => $value) {

            $item['aggs'] = date('Y-m-d',$value['start_time']/1000);


            $curl_param = [
                'size'=>0,
                'query'=>[
                    'bool'=>[
                        'must'=>[
                            [
                                'range'=>[
                                    'created_date'=>[
                                        'gte'=>$value['start_time'],
                                        'lte'=>$value['end_time']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs'=>[
                    'ads_num'=>[
                        'cardinality'=>[
                            'field'=>$terms_field
                        ]
                    ]
                ]
            ];

            if($field_param)
            {
                foreach ($field_param as $k => $v) {
                    $items = [
                        'term'=>[
                            $k=>$v
                        ]
                    ];
                    array_push($curl_param['query']['bool']['must'], $items);
                }
            }
            $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

            $ads_json = json_decode($rs,true);

            if($key>0)
            {
                $item['aggs'] = date('Y-m-d',$value['end_time']/1000);
            }
            $item['num'] = empty($ads_json['aggregations']['ads_num']['value'])?0:$ads_json['aggregations']['ads_num']['value'];

            $data[] = $item;
           
        }

        return $data;
    }

    public function getMaxMinDate($field_param)
    {
        $curl_param = [
            'size'=>0,
            'query'=>[
                'bool'=>[
                    'must'=>[
                       
                    ]
                ]
            ],
            'aggs'=>[
                'max_date'=>[
                    'max'=>[
                        'field'=>'created_date'
                    ]
                ],
                'min_date'=>[
                    'min'=>[
                        'field'=>'created_date'
                    ]
                ]
            ]
        ];

        if($field_param)
        {
            foreach ($field_param as $key => $value) {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];
                array_push($curl_param['query']['bool']['must'], $item);
            }
        }
        $url = config('param.ES_URL').'addata_aggs/addata_index/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $ads_json = json_decode($rs,true);

        $data['max'] = empty($ads_json['aggregations']['max_date']['value'])?'':$ads_json['aggregations']['max_date']['value'];
        $data['min'] = empty($ads_json['aggregations']['min_date']['value'])?'':$ads_json['aggregations']['min_date']['value'];
        return $data;

    }
     /**
     * 获取 addata 所有 信息 
     * type 0 返回所有数据 1 简化数据
     * filetype 文件类型 图片 flush 视频
     *
     * platform 平台 1 pc  2 移动
     * wd 搜索内容
     * @return [type] [description]
     */
    public function getAllSubFilter(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $platform = empty($request->platform)?'':$request->platform;
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);
        $type = empty($request->type)?'':$request->type;

        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(($type && !is_numeric($type)))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        //多字段查询

        $curl_param = [
            'sort'=>DSLUtil::baseSort(),
            'size'=>$size,
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
                                'match'=>[
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
                    'score_mode'=>'sum',
                    'max_boost'=>'10'
                ],
            ]
        ];

        if(empty($param))unset($curl_param['query']);
        $valiedParam = [];
        if($type)$valiedParam['type'] = $type;
        if($platform)$valiedParam['platform'] = $platform;
        if($valiedParam)
        {
          foreach ($valiedParam as $key => $value) {
              $item[] = [
                'term'=>[
                  $key=>$value
                ]
              ];
              array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
          }
        }
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
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

        $data['data'] = DBUtil::chinaSort($data);
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }
    public function getAllSubFilter2(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;
        $platform = empty($request->platform)?'':$request->platform;
        $param = empty($request->wd)?'':$request->wd;
        $param = urldecode($param);
        $type = empty($request->type)?'':$request->type;
        $page = empty($request->page)?1:$request->page;
        if(empty($param))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        if(!DBUtil::valiedSizeSort($size,$sort))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        if(($type && !is_numeric($type)))
        {
            $data['status'] = 206;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        if($type)$type = ($type==1?'image':($type==2?'swf':'flv'));
        //多字段查询
        $size_all = $size * $page;
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
                                ],
                                'must'=>[]
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
                                    'match'=>[
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
                                    // 'field'=>'created_date'
                                    'script'=>'_score'
                                ]
                            ],
                            'max_date'=>[
                                'max'=>[
                                    'field'=>'date5'
                                    // 'script'=>'_score'
                                ]
                            ],
                            'max_time'=>[
                                'max'=>[
                                    'field'=>'created_date'
                                    // 'script'=>'_score'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

        if(empty($param))unset($curl_param['query']);
        $valiedParam = [];
        if($type)$valiedParam['type'] = $type;
        if($platform)$valiedParam['platform'] = $platform;

        if($valiedParam)
        {
          foreach ($valiedParam as $key => $value) {
              $item[] = [
                'term'=>[
                  $key=>$value
                ]
              ];
              array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
          }
        }
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $rs_des = json_decode($rs,true);
        $total = empty($rs_des['aggregations']['count']['value'])?0:$rs_des['aggregations']['count']['value'];
        $filed_tmp = empty($rs_des['aggregations']['groub']['buckets'])?[]:$rs_des['aggregations']['groub']['buckets'];
        
        // 聚合 实现  分页功能
       
        if($page>1)$filed_tmp = DBUtil::removeLimit($size * ($page-1),$filed_tmp);
        //获取 subject  最后出现的时间
        $data = $this->getSubLastDate($filed_tmp);
        
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
     * 拼接 数组 获取 每条 subejct 最后出现的时间
     * @param  [type] $filed_tmp [description]
     * @return [type]            [description]
     */
    private function getSubLastDate($filed_tmp)
    {
        $data = [];
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

            $subject_tmp['ads'] = $value['doc_count'];

            $subject_tmp['last_detected'] = $value['max_time']['value'];
            $data['subject'][] = $subject_tmp;
        }
        return $data;
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
     * @return $this
     */
    public function getAllSubAds(Request $request)
    {
        $type = empty($request->type)?'':$request->type;
        $shape = empty($request->shape)?'':$request->shape;
        $min_data = empty($request->start_time)?0:$request->start_time;
        $end_time = empty($request->end_time)?0:$request->end_time;
        $platform = empty($request->platform)?'':$request->platform;
        $width = empty($request->width)?0:$request->width;
        $min_width = empty($request->min_width)?0:$request->min_width;
        $min_height = empty($request->min_height)?0:$request->min_height;
        $height = empty($request->height)?0:$request->height;
        $max_width = max($width,$min_width);
        $min_width = min($width,$min_width);
        $max_height = max($height,$min_height);
        $min_height = min($height,$min_height);
        $sortmode = empty($request->sortmode)?'':$request->sortmode;
        // 每页显示 数据个数
        $size = empty($request->size)?10:$request->size;
        $page = empty($request->page)?1:$request->page;
        //from: 跳过开始的结果数，默认0
        $from = empty($request->from)?0:$request->from;
        if($page>1)$from = $size * $page;
        // 搜索内容
        $param = empty($request->param)?'':$request->param;
        $param = urldecode($param);
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
        unset($valiedParam['size']);
        unset($valiedParam['min_data']);
        unset($valiedParam['end_time']);
        unset($valiedParam['sortmode']);
        unset($valiedParam['page']);
        unset($valiedParam['size']);
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
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
                                'match'=>[
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
            'sort'=>DSLUtil::baseSort()
        ];
        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $item = [
                    'term'=>[
                        $key=>$value
                    ]
                ];
                array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
            }
        }
        $range = [];
        if($min_data && $end_time){
            $range[] = [
                'range'=>[
                    'created_date'=>[
                            'gte'=>$min_data*1000,
                            'lte'=>$end_time*1000
                        ]
                    ]
            ];
        }else if($min_data)
        {
             $range[] = [
                'range'=>[
                    'created_date'=>[
                        'gte'=>$min_data
                    ]
                ]
             ];
        }
        if($max_width)
        {
            $range[] = [
                'range'=>[
                    'width'=>[
                                'gte'=>$min_width,
                                'lte'=>$max_width
                        ]
                    ]
                ];
        }
        if($max_height)
        {
            $range[] = [
                'range'=>[
                    'height'=>[
                                'gte'=>$min_height,
                                'lte'=>$max_height
                        ]
                    ]
                ];
        }
        if($range)
        {
            foreach ($range as $key => $value) {
                 array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);
            }
        }
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['page'] = $page;
        $data['size'] = $size;
        $data['all_page'] = intval(ceil($data['total']/$size));
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }



    /**
     *  判断 参数 是否存在
     * @return boolean [description]
     */
    public function isExist(Request $request)
    {
        $wd = empty($request->wd)?"":$request->wd;
        $param = urldecode($wd);
        $curl_param = [
            'size'=>1,
            'query'=>[
                'multi_match'=>[
                    'query'=>$param,
                    'type'=>'cross_fields',
                    'operator'=>'and',
                    'fields'=>[
                        'advertiser_name','advertiser_name_title','title'
                    ]
                ]
            ]

        ];
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
        $url = $url.'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);

        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);


    }
    public function getmobileTop100(Request $request)
    {
        $size = empty($request->size)?10:$request->size;
        $curl_param = [
            'sort'=>DSLUtil::sortByTime('created_date','desc'),
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'term'=>[
                            'platform'=>2
                        ]
                    ]
                ]
            ],
            'size'=>$size
        ];
        $curl_param['_source'] = config('param.ADDATA_FILED_SOURCE');
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');
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

        return HttpUtil::retrun_json($data,200);

    }
     /**
     * 
     * @return [type] [description]
     */
    public function getCount(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $curl_param = [
            'query'=>[
                'bool'=>[
                    'should'=>[
                        [
                             'fuzzy'=>[
                                'advertiser'=>[
                                    'value'=>$param
                                ]
                            ]
                        ]
                    ]
                    
                ]
            ]
        ];
        if(empty($param))unset($curl_param['query']);
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?search_type=count';
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $es_da = json_decode($rs,true);

        $data['data']=[];
        $data['total'] = empty($es_da['hits']['total'])?0:$es_da['hits']['total'];
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }
    
    /**
     * 根据 编号 获取  addata 信息
     */
    public function getAllById(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $es = ESUtil::getInfoById(config('param.ADDATA_SEARCH'),$id);

        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $es = DBUtil::getBodyWH($es);


        $data['data'] = $es;
        $data['status'] = empty($es)?203:200;
        return HttpUtil::retrun_json($data,200);
    }

     /**
     * 根据 编号 获取  addata 信息
     */
    public function getInfoByMd5(Request $request)
    {
        $md5 = empty($request->md5)?'':$request->md5;
        if(empty($md5))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $es = ESUtil::getInfoByMd5(config('param.ADDATA_SEARCH'),$md5);
        $data['data'] = $es;
        $data['status'] = empty($es)?203:200;
        return HttpUtil::retrun_json($data,200);
    }


    /**
     * 获取 摸个字段 最大最小值
     * @return [type] [description]
     */
    public function getMaxMinfield(Request $request)
    {
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
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $es_data = json_decode($rs,true);
        if(empty($es_data['hits']['total']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        $item['last_detected'] = empty($es_data['aggregations']['last_detected']['value'])?'':$es_data['aggregations']['last_detected']['value'];
        $item['first_detected'] = empty($es_data['aggregations']['last_detected']['value'])?'':$es_data['aggregations']['first_detected']['value'];
        $data['data'] = $item;
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
    

    /**
     * 根据 id 获取 信息
     * ids 数组 形式
     * 20160712
     * finn
     * 数据格式 $ids = '1,2,3,4,5,6'
     * @return [type] [description]
     */
    public function getInfoInIds(Request $request)
    {
        $ids = $request->ids;
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
        $ids = explode(',', $ids);
        //参数格式不对
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        foreach ($ids as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        }
        if(count($ids)>100)return HttpUtil::retrun_json(['data'=>[],'status'=>207,'smg'=>'请求id 参数个数过多'],200);
        $curl_param = [
            'size'=>count($ids),
            'sort'=>DSLUtil::sortByTime('date5','desc'),
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        'terms'=>[
                            'id'=>$ids
                        ]
                    ]
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);

        //$data = $this->getAtt08($data);


        // $es_data = [];
        // foreach ($ids as $key => $value) {
        //     if($value)
        //     {
        //         $es = ESUtil::getInfoById(config('param.ADDATA_SEARCH'),$value);
        //         if(!empty($es))$es_data[] = $es[0];
        //     }
        // }
        // if(empty($es_data))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        // $data['data'] = $es_data;
        $data['status'] = 200;
        // $data['total'] = empty($es_data)?'':count($es_data);
        return HttpUtil::retrun_json($data,200);

    }

    /**
     * 根据 输入内容 查询
     * title 输入内容
     * filed 字段
     * size 显示条数
     */
    public function getInfoByTitle(Request $request)
    {
        $title = empty($request->title)?'':$request->title;
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        if($size && !DBUtil::sizeValied($size))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        if(empty($title))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
        //多字段查询
        $curl_param = [
            'sort'=>DSLUtil::sortByTime('date5','desc'),
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

        // dd(json_encode($curl_param));
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = empty($data['data'])?203:200;
        return HttpUtil::retrun_json($data,200);
    }

    //=================================临时 APi===================================================
    
    /**
     * 根据 ids 数组 获取  addata 信息
     * sort 排序 默认 根据 id desc 排序
     * 20160707 is ok
     */
    public function getAllByIds(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;
        $sort = empty($request->sort)?'desc':$request->sort;
        if(empty($ids))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $id_arr = [];
        foreach ($ids as $key => $value) {
            if($value)$id_arr[$key] = $value;
        }
        if(empty($id_arr))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        if($sort && !in_array($sort, ['desc','asc','DESC','ASC']))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        sort($id_arr);
        $ids_num = count($id_arr);
        $min_id = $id_arr[0];
        $max_id = $id_arr[$ids_num-1];
        $curl_param = [
            'sort'=>DSLUtil::sortByTime('ad_rand','desc'),
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
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203]);
        $data['status'] = empty($data['data'])?203:200;
        return HttpUtil::retrun_json($data,200);

    }
     /**
     * 根据 指定 营销活动 或者 创意 名称 
     * 获取 所有时间段的数据
     * finn 2017-1-20
     */
    public function getSubjectRange(Request $request)
    {
        $tmp = [
            1,2,3,4,5,6,7,8,9,10
        ];

        $tmp_true = [];

        $index = 0;
        $len = 2;

        $count = count($tmp);

        if($index < $count)
        {
            $tmp_true[] = array_slice($tmp,$index,$len);
            $index = $index+$len;
        }

        foreach ($variable as $key => $value) {
            # code...
        }
        foreach ($tmp as $key => $value) {

            array_slice($tmp,$index,$len);

        }

        // print_r()


        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $page = empty($request->page)?0:$request->page;
        $from = $size * $page;

        $wd = empty($request->wd)?'':$request->wd;

        if(empty($wd))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        $param = urldecode($wd);


        // 第一步 判断是否是 营销活动
        
        $curl_param = [
            'query'=>DSLUtil::baseTerm('md5',$param)
        ];
        $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);

        if(!empty($data['data']))
        {
            $md5 = $data['data'][0]->md5;

            // 判断当前 subject title 总的数量
            $curl_param = [
                'size'=>0,
                'query'=>DSLUtil::baseTerm('subject_md5',$md5)
            ];

            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data_total = DBUtil::get_parse_es_data($rs);


            $total = $data_total['total']>10000?9000:$data_total['total'];

            // 获取 该营销活动 下所有 投放情况
            
            $curl_param = [
                'size'=>0,
                'query'=>DSLUtil::baseTerm('subject_md5',$md5),
                'aggs'=>[
                    'min_date'=>[
                        'max'=>[
                            'field'=>'date5'
                        ]
                    ],
                    'max_date'=>[
                        'max'=>[
                            'field'=>'date5'
                        ]
                    ]
                ]
            ];

            // 获取最大 z时间断 

            $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
            $data_total = DBUtil::get_parse_es_data($rs);

        }



        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'sort'=>DSLUtil::sortByTime('date5','desc'),
            'query'=>DSLUtil::baseTerm('subject_title',$param),
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }

        $data['data'] = DBUtil::chinaSort($data);
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

    //============================adbug api/v1 暂无使用====================================
    
    public function getCountParam(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;
        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $curl_param = [
            'query'=>[
                'multi_match'=>[
                    'query'=>$param,
                    'fields'=>[
                        // "title",
                        'advertiser_name_title','advertiser','publisher'
                        // 'advertiser','advertiser_name_title','keywords','trackers','publisher','advertiser_name','advertiser_full','publisher_full'
                    ]
                ]
                
            ]
        ];

        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?search_type=count';
        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $es_da = json_decode($rs,true);
        $data['data']=[];
        $data['total'] = empty($es_da['hits']['total'])?0:$es_da['hits']['total'];
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
       /**
     * type 1 trackers  2 advertiser 3 publisher
     * @param  Request $request [description]
     * @return [type]           [description]
     * isbrand  ispublisher istracker
     */
    public function getAdsCount(Request $request)
    {
        $host = empty($request->host)?'':$request->host;
        $type = empty($request->type)?'':$request->type;
        if(empty($host) || empty($type) || !is_numeric($type))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);
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
        $rs = HttpUtil::parse_curl_search(config('param.DOMAIN_SEARCH'),$curl_param);
        $data = DBUtil::get_parse_es_data($rs);
        if(empty($data))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
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
    public function getAllFilter(Request $request)
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
        if(!$startTime || !$endTime) return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        
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
            'sort'=>DSLUtil::sortByTime('date5','desc'),
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
        if(empty($type))unset($curl_param['query']['bool']['must'][1]);
        if(empty($platform))unset($curl_param['query']['bool']['must'][2]);
        if(empty($shape))unset($curl_param['query']['bool']['must'][3]);
        if(empty($startTime))unset($curl_param['query']['bool']['must'][4]);
        if(empty($last_id) || empty($pages))unset($curl_param['query']['bool']['must'][5]);
        $rs = HttpUtil::parse_curl_search(config('param.ADDATA_SEARCH'),$curl_param);
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


    /**
     * 临时 
     */
    public function getWHADsTmp(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        file_put_contents('w_h_info.txt', '');
        // $header = 'id,publisher,width,height,domain';
        // file_put_contents('w_h_info.txt', $header.PHP_EOL,FILE_APPEND);

        // for ($i=0; $i <10 ; $i++) { 


        //     file_put_contents('w_h_info.txt', 'cdd+'.$i.PHP_EOL,FILE_APPEND);
        // }

        // dd();

        // file_put_contents('w_h_info.txt', 'cddcd');

        // exit;
        $size = 5000;

        // 默认
        $curl_param = [
            'size'=>$size,
            'sort'=>[
                'created_date'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'term'=>[
                    'platform'=>2
                ]
            ],
            '_source'=>[
                'id','width','height','domain','publisher'
            ]
        ];

        // dd(json_encode($curl_param));
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH').'?scroll=3m';

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $data = DBUtil::get_parse_es_data($rs);


        $return_id = [];
        $data = DBUtil::get_parse_es_data_id($rs);


        // dd($data);

        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return HttpUtil::retrun_json($data,200);
        }


        foreach ($data['data'] as $key => $value) {
            $return_id[] = $value;
        }

        $scrollId = $data['scroll_id'];


       // $$data['total'] = 20000;

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

        // $j = 1000;

        for ($i=0; $i < $j; $i++) { 
            $url = config('param.ES_URL').'/'.config('param.ES_SEARCH').'/scroll?scroll=3m';

            $curl_param_scroll = [
                'scroll_id'=>$scrollId
            ];
            $rs = HttpUtil::send_curl_methods($url,$curl_param_scroll,"POST");

            $datas = DBUtil::get_parse_es_data_id($rs);
            
            if(!empty($datas['scroll_id']))
            {
                $scrollId = $datas['scroll_id'];
            }
            if(empty($datas['data']))break;

            foreach ($datas['data'] as $key => $value) {
                $return_id[] = $value;
            }
           
        }

        $header = 'id,publisher,width,height,domain';
        file_put_contents('w_h_info.txt', $header.PHP_EOL,FILE_APPEND);
        foreach ($return_id as $key => $value) {

            $value = (array)$value;
            $info = $value['id'].','.$value['publisher'].','.$value['width'].','.$value['height'].','.$value['domain'];
            file_put_contents('w_h_info.txt', $info.PHP_EOL,FILE_APPEND);
        }

        // dd(88);
        // dd($return_id);
        // $cellData = [
        //     ['id','publisher','width','height','domain']
        // ];

        // $data = [];
        // foreach ($return_id as $key => $value) {

        //     $value = (array)$value;

        //     $item['id'] = $value['id'];
        //     $item['publisher'] = $value['publisher'];
        //     $item['width'] = $value['width'];
        //     $item['height'] = $value['height'];

        //     $item['domain'] = $value['domain'];
        //     array_push($cellData, $item);
        // }

        //  Excel::create('Addata 宽 高 信息',function($excel) use ($cellData){
        //             $excel->sheet('score', function($sheet) use ($cellData){
        //                 $sheet->rows($cellData);
        //             });
        //     })->export('xls');    

         dd('ok');
        // dd($data);
    }
}