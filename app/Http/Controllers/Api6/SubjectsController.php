<?php

namespace App\Http\Controllers\Api6;

use App\Http\Utils\DSLUtil;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\DBUtil;
use App\Http\Utils\ESUtil;
use App\Addata;
/**
 * finn
 * 20170706
 * subjects api
 */
class SubjectsController extends Controller
{
    
    /**
     *  根据 md5  获取 subject info
     *  param  输入参数
     * @return [type] [description]
     */
    public function getInfoByMd5(Request $request)
    {
        $md5 = empty($request->param)?'':$request->param;
        if(empty($md5))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        $es = ESUtil::getInfoByMd5(config('param.SUBJECT_SEARCH6'),$md5);
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
        $es = ESUtil::getInfoById(config('param.SUBJECT_SEARCH6'),$id);
        if(empty($es)) return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        return HttpUtil::retrun_json(['data'=>$es[0],'status'=>200],200);
    }
     /**
     * 根据 编号查询
     * 2017-01-03 finn
     * 优化查询
     * 暂时没有使用
     *@return [type] [description]
     */
    public function getInfoInIds(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;
        if(empty($ids))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        $ids = explode('sub_id', $ids);
        foreach ($ids as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        }

        $ids_tmp = [];
        foreach ($ids as $key => $value) {
            if(!in_array($value, $ids_tmp))$ids_tmp[] = $value;
        }
        $curl_param = [
            'size'=>count($ids_tmp),
            'sort'=>[
                'last_detected'=>[
                    'order'=>'desc'
                ]
            ],
            'query'=>[
                'filtered'=>[
                    'filter'=>[
                        'terms'=>[
                            'id'=>$ids_tmp
                        ]
                    ]
                ]
            ]
        ];

        // dd(json_encode($curl_param));
        $sub_rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH6'),$curl_param);
        $data_sub = DBUtil::get_parse_es_data($sub_rs);


        // dd($data);

        // 进一步 计算 最早时间
        // print_r($data_sub['data']);

        $ad_id = [];
        foreach ($data_sub['data'] as $key => &$value) {
            $md5 = $value->md5;

            $ad_id[] = $value->ad_id;
            $curl_param_agg = [
                'size'=>0,
                'query'=>[
                    'term'=>[
                        'subject_md5'=>$md5
                    ]
                ],
                'aggs'=>[
                    'max_date'=>[
                        'max'=>[
                            'field'=>'created_date'
                        ]
                    ],
                    'ads'=>[
                        'cardinality'=>[
                            'field'=>'id'
                        ]
                    ]
                ]
            ];
            // $curl_param_agg['_source'] = config('param.ADDATA_FILED_SOURCE');

            $url = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');
            $rs_d = HttpUtil::send_curl_methods($url,$curl_param_agg,"POST");
            $rs_data = json_decode($rs_d,true);

            $last_time = empty($rs_data['aggregations']['max_date']['value'])?'':$rs_data['aggregations']['max_date']['value'];
            $id_data = empty($rs_data['hits']['hits'][0]['_source'])?[]:$rs_data['hits']['hits'][0]['_source'];
            $value->last_detected = intval($last_time);
            $value->ads = empty($rs_data['aggregations']['ads']['value'])?0:$rs_data['aggregations']['ads']['value'];
        }

        $curl_param_ad2 = [
            'size'=>count($ad_id),
            'query'=>DSLUtil::baseTerms('id',$ad_id),
            '_source'=>[
                'id','type','am_url','width','height','attribute08',
                'am_source_url'
            ]
        ];
        // dd(json_encode($curl_param_ad2));

        // $curl_param_ad2['_source'] = config('param.ADDATA_FILED_SOURCE');
        $url2 = config('param.ES_URL').config('param.ADDATA_SEARCH6').'/'.config('param.ES_SEARCH');

        $rs_d2 = HttpUtil::send_curl_methods($url2,$curl_param_ad2,"POST");

        $data = DBUtil::get_base_parse_es_data($rs_d2);

        // dd($sb_data);
        foreach ($data_sub['data'] as $key => &$value) {

            $value = (array)$value;

            foreach ($data['data'] as $k => $v) {
                if($v && $value['ad_id']==$v->id)
                {
                    $value['type'] =  $v->type=='html5'?$v->attribute08:$v->type;
                    $value['am_url'] = empty($v->am_url)?'':$v->am_url;
                    $value['width'] = empty($v->width)?'':$v->width;
                    $value['height'] = empty($v->height)?'':$v->height;
                }
            }
        }
        
        $rdata['data'] = $data_sub['data'];
        $rdata['total'] = count($data_sub['data']);
        $rdata['status'] = 200;
        return HttpUtil::retrun_json($rdata,200);
    }
   
    
   
    //======================暂时没有使用 APi=============================================
    /**
     * 获取 domain 所有 信息 
     * @return [type] [description]
     */
    public function getAll(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($sort && !in_array($sort, ['desc','asc']))return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);

        if(!is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH6'),DSLUtil::baseDSLQuery(0,$size));
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = empty($data['data'])?203:200;
        return HttpUtil::retrun_json($data,200);
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
        $data = [];
        foreach ($ids as $key => $value) {
            if($value)
            {
                $es = ESUtil::getInfoById2(config('param.SUBJECT_SEARCH6'),$value);
                if(!empty($es[0]))$data[] = empty((array)$es[0])?'':(array)$es[0];
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
                            'cardinality'=>[
                                'field'=>'id'
                            ]
                        ]
                    ]
                ];
            $rs = HttpUtil::parse_curl_search(config('param.MIANS_SEARCH6'),$curl_param);
            $parse_rs = json_decode($rs);
            $ads = empty($parse_rs->aggregations->addata->value)?0:$parse_rs->aggregations->addata->value;
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
        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH6'),$curl_param);
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

        $rs = HttpUtil::parse_curl_search(config('param.SUBJECT_SEARCH6'),DSLUtil::baseDSLQuery($page * $size,$size));
        $data = DBUtil::get_parse_es_data($rs);
        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);
        $data['status'] = empty($data['data'])?203:200;
        return HttpUtil::retrun_json($data,200);
    }
}
