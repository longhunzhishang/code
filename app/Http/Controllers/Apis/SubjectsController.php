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
 * subjects api
 */
class SubjectsController extends Controller
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
        
        if(!is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $curl_param = [
            // 'sort'=>[
            //     '_score'=>'desc'
            // ],
            'size'=>$size
        ];

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH'),$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;
        

        return HttpUtil::retrun_json($data,200);
    }

  


    /**
     *  根据 md5  获取 subject info
     *  param  输入参数
     * @return [type] [description]
     */
    public function getInfoByMd5(Request $request)
    {
        $md5 = empty($request->param)?'':$request->param;
        if(empty($md5))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $es = ESUtil::getInfoByMd5(config('param.SUBJECT_SEARCH'),$md5);

        $data = DBUtil::pareseESDate($es,1);

        return HttpUtil::retrun_json($data,200);
    }

    

    /**
     * 根据 编号查询
     * @return [type] [description]
     */
    public function getInfoById(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);

        $es = ESUtil::getInfoById(config('param.SUBJECT_SEARCH'),$id);
        if(empty($es)) return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        return HttpUtil::retrun_json(['data'=>$es[0],'status'=>200],200);
    }

     /**
     * 根据 编号查询
     * @return [type] [description]
     */
    public function getInfoInIds(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;
        if(empty($ids))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $ids = explode('sub_id', $ids);


        foreach ($ids as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        }

        $data = [];

        foreach ($ids as $key => $value) {
            if($value)
            {
                $es = ESUtil::getInfoById(config('param.SUBJECT_SEARCH'),$value);

                if(!empty($es[0]))
                {
                    $data[] = empty((array)$es[0])?'':(array)$es[0];
                }
                
            }
        }


        foreach ($data as $key => &$value) {

             $curl_param = [
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                'term'=>[
                                    'subject'=>$value['id']
                                ]
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'addata'=>[
                            'terms'=>[
                                'field'=>'id'
                            ]
                        ]
                    ]
                ];

            $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

            $parse_rs = json_decode($rs);

            $ads = empty($parse_rs->aggregations->addata->buckets)?1:count($parse_rs->aggregations->addata->buckets);


            $value['ads'] = $ads;


        }

        $rdata['data'] = $data;
        $rdata['total'] = count($data);
        $rdata['status'] = 200;
        return HttpUtil::retrun_json($rdata,200);

    }


      /**
     * 根据 编号查询
     * @return [type] [description]
     */
    public function getInfoInMd52(Request $request)
    {
        $ids = empty($request->md5s)?'':$request->md5s;
        if(empty($ids))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $ids = explode('sub_id', $ids);


        // foreach ($ids as $key => $value) {
        //     if($value && !is_numeric($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        // }

        $data = [];

        foreach ($ids as $key => $value) {
            if($value)
            {
                $es = ESUtil::getInfoById2(config('param.SUBJECT_SEARCH'),$value);

                if(!empty($es[0]))
                {
                    $data[] = empty((array)$es[0])?'':(array)$es[0];
                }
                
            }
        }


        foreach ($data as $key => &$value) {

             $curl_param = [
                    'query'=>[
                        'bool'=>[
                            'must'=>[
                                'term'=>[
                                    'subject'=>$value['id']
                                ]
                            ]
                        ]
                    ],
                    'aggs'=>[
                        'addata'=>[
                            'terms'=>[
                                'field'=>'id'
                            ]
                        ]
                    ]
                ];

            $rs = HttpUtil::parse_curl_search(config('param.MAININDEX_SEARCH'),$curl_param);

            $parse_rs = json_decode($rs);

            $ads = empty($parse_rs->aggregations->addata->buckets)?1:count($parse_rs->aggregations->addata->buckets);


            $value['ads'] = $ads;


        }

        $rdata['data'] = $data;
        $rdata['total'] = count($data);
        $rdata['status'] = 200;
        return HttpUtil::retrun_json($rdata,200);

    }




    //最新 版本 api  调用
    /**
     * field 查询字段 
     * param 对应的值
     * $return [] 返回结果
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getInfoById_3(Request $request)
    {
        $param = $request->param;

        if(empty($param))return json_encode(['data'=>[],'status'=>206,'msg'=>'参数不可为空']);

        $curl_param = [
            'query'=>[
                'term'=>[
                    'id'=>$param
                ]
            ]
        ];

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH'),$curl_param);
    
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

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH'),$curl_param);
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;
        
        return HttpUtil::retrun_json($data,200);
    }
}
