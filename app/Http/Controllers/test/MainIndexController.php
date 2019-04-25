<?php

namespace App\Http\Controllers\test;

use Illuminate\Http\Request;

use App\Http\Controllers\TestController as Controller;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use Search;
USE App\Http\Utils\DBUtil;
use Elasticsearch\Client;

/**
 * finn
 * 20170706
 * main index api
 */
class MainIndexController extends Controller
{

    
    public function getAll()
    {
        return view('test.mainindex.all');
    }
    /**
     * 获取 domain 所有 信息 
     * @return [type] [description]
     */
    public function getAllTest(Request $request)
    {
        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($size && !is_numeric($size))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        if(!DBUtil::sortValied($sort) || $size > config('param.MAXSIZE'))return HttpUtil::retrun_json(HttpUtil::returnStatus(208),200);

        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size
        ];

        $url = config('param.ES_URL').'mains_index/main_index/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['last_id'] = $data['data'][0]->id;

        if($sort=='asc')$data['last_id'] = $data['data'][$size-1]->id;

        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);


    }

    public function getInfoInIds()
    {
        return view('test.mainindex.id');
    }

    /**
     * 根据 角色 查询
     * 20170711 修改 数组处理
     * @return [type] [description]
     */
    public function getInfoInIdsTest(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;

        if(empty($ids))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $ads_id = explode('ad_id', $ids);


        foreach ($ads_id as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        }
        $es_data = [];
        foreach ($ads_id as $key => $value) {
            if($value)
            {
                $es = ESUtil::getInfoById(config('param.MAININDEX_SEARCH'),$value);
                if(!empty($es))$es_data[] = $es[0];
            }
        }
        
        $data['data'] = $es_data;
        $data['total'] = count($es_data);
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }


    public function getAllField()
    {
        return view('test.mainindex.field');
    }


    /**
     * 根据 字段
     * 查询
     * 20170712
     * finn
     */
    public function getAllFieldTest(Request $request)
    {
        $field = empty($request->field)?'':$request->field;
        $param = empty($request->value)?'':$request->value;


        if(empty($field) || empty($param))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);


        $es = ESUtil::getFieldInfo(config('param.MAININDEX'),$field,$param,1);
        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['data'] = $es;
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

}
