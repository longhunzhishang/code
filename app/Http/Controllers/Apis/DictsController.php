<?php

namespace App\Http\Controllers\Apis;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use Search;
USE App\Http\Utils\DBUtil;
use Elasticsearch\Client;
use App\Dicts;

/**
 * finn
 * 20160727
 * dicts api
 */
class DictsController extends Controller
{
    /**
     * 获取 所有 词典
     * @return [type] [description]
     */
    public function getAll()
    {

    }


    public function testDict(Request $request)
    {

 echo date('Y-m-d H:i:s',time());

        // 第一种方式
//          echo date('Y-m-d H:i:s',time());

//         echo "<br/>";

//         $param = empty($request->wd)?'':$request->wd;


//         // $param = empty($request->wd)?'':$request->wd;
        
//         $dicts = Dicts::where('title','like','%'.$param.'%')->get();
//         if(!$dicts)return HttpUtil::retrun_json([],200);

//         $dict_info = [];

//         // 第二步 对数据 进行 过滤 如果不存在 剔除
//         foreach ($dicts as $key => $value) {



//                 if($value['title'])
//                 {
//                     $curl_param = [
//                         'size'=>1,
//                         'query'=>[
//                             'multi_match'=>[
//                                 'query'=>$value['title'],
//                                 'type'=>'cross_fields',
//                                 'operator'=>'and',
//                                 'fields'=>[
//                                     'advertiser_name','advertiser_name_title','title'
//                                 ]
//                             ]
//                         ]
//                     ];
                    
//                     $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

//                     $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
//                     $isexist = DBUtil::get_parse_es_data($rs);
//                     if(!empty($isexist['data']))
//                     {
//                         $dict_info[] = $value;
//                     }
//                 }
                
            
//         }

//         $data['status'] = 200;
//         $data['data'] = $dict_info;

// echo date('Y-m-d H:i:s',time());
//         dd($data);

//         return HttpUtil::retrun_json($data,200);




        // 第二种方式



        $param = empty($request->wd)?'':$request->wd;
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
        foreach ($data['data'] as $key => $value) {

                $value = (array)$value;

                if($value['title'] && count($dict_info)<11)
                {
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



        $data['status'] = 200;
        $data['data'] = $dict_info;


        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 收索内容
     * 获取 推荐 词典
     * @return [type] [description]
     */
    public function getRelationDicts(Request $request)
    {


        $param = empty($request->wd)?'':$request->wd;
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
        foreach ($data['data'] as $key => $value) {

                $value = (array)$value;

                if($value['title'] && count($dict_info)<11)
                {
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



        $data['status'] = 200;
        $data['data'] = $dict_info;


        return HttpUtil::retrun_json($data,200);

    }


    /**
     * 获取 关键词 分词结果
     * @return [type] [description]
     */
    public function getDictIk(Request $request)
    {
        $param = empty($request->wd)?'':$request->wd;

        if(empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);


        //_analyze?analyzer=ik
        $curl_param = [
            'text'=>$param
        ];
        $rs = HttpUtil::parse_curl_search('',$curl_param);

        $rs = json_decode($rs,true);

        if(empty($rs['tokens']))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);
       

        $data = [];
        foreach ($rs['tokens'] as $key => $value) {
            $data[] = $value['token'];
        }

        sort($data);

        return HttpUtil::retrun_json(['data'=>$data,'status'=>200],200);

    }

}
