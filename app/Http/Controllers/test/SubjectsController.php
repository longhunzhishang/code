<?php

namespace App\Http\Controllers\test;

use App\Http\Controllers\TestController as Controller;


use Illuminate\Http\Request;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
USE App\Http\Utils\DBUtil;
use Search;


/**
 * finn
 * 20170706
 * home test api
 */
class SubjectsController extends Controller
{
    
    public function getAll()
    {
        return view('test.sujects.all');
    }

    /**
     * 根据 编号 获取 信息 
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getInfoById(Request $request)
    {
       return view('test.sujects.id');
    }

    public function getInfoInIds(Request $request)
    {
       return view('test.sujects.inids');
    }


    /**
     * 根据 编号查询
     * @return [type] [description]
     */
    public function getInfoByIdTest(Request $request)
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
    public function getInfoInIdsTest(Request $request)
    {
        $ids = empty($request->ids)?'':$request->ids;
        if(empty($ids))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);

        $ids = explode('sub_id', $ids);


        foreach ($ids as $key => $value) {
            if($value && !is_numeric($value))return HttpUtil::retrun_json(HttpUtil::returnStatus(206),200);
        }
        $size = count($ids)-1;

        $should_arr = [];

        foreach ($ids as $key => $value) {

            if($value)
            {
                $should_arr[] = [
                    'term'=>[
                        'id'=>$value
                    ]
                ];
            }
        }

        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'should'=>$should_arr
                ]
            ]
        ];


        $url = config('param.ES_URL').config('param.SUBJECT_SEARCH').'/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
    
        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);


        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);

    }

     /**
     *  根据 md5  获取 subject info
     *  param  输入参数
     * @return [type] [description]
     */
    public function getInfoByMd5(Request $request)
    {
        return view('test.sujects.md5');
    }

     /**
     *  根据 md5  获取 subject info
     *  param  输入参数
     * @return [type] [description]
     */
    public function getInfoByMd5Test(Request $request)
    {
        $md5 = empty($request->param)?'':$request->param;
        if(empty($md5))return HttpUtil::retrun_json(['data'=>[],'status'=>207],200);

        $se = Search::index(config('param.SUBJECTS'))->where('md5',$md5)->get();
        $data['data'] = empty($se)?'':$se[0];
        $data['status'] = empty($se)?203:200;
        return HttpUtil::retrun_json($data,200);
    }

    /**
     * size 显示数目
     * sort 排序 默认 desc 
     */
    public function getAllTest(Request $request)
    {

        $size = empty($request->size)?config('param.SIZE'):$request->size;
        $sort = empty($request->sort)?'desc':$request->sort;

        if($sort && !in_array($sort, ['desc','asc']))
        {
            return HttpUtil::retrun_json(['data'=>[],'status'=>208],200);
        }
        
        if(!is_numeric($size))return HttpUtil::retrun_json(['data'=>[],'status'=>206],200);
        $curl_param = [
            'sort'=>[
                'id'=>[
                    'order'=>'desc'
                ]
            ],
            'size'=>$size
        ];

        $url = config('param.ES_URL').'subjects/subject_index/'.config('param.ES_SEARCH');


        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        // 暂无数据 2002
        if(empty($data['data']))return HttpUtil::retrun_json(['data'=>[],'status'=>203],200);


        $data['status'] = empty($data['data'])?203:200;
        

        return HttpUtil::retrun_json($data,200);
    }

   
}
