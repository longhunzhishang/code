<?php

namespace App\Http\Controllers\Api3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\ESUtill4;
use App\Http\Utils\HttpUtil;
use Search;

/**
 * finn
 * 20170706
 * main index api
 */
class HostMedaController extends Controller
{
    /**
     * 根据 host 获取信息
     * @return [type] [description]
     */
    public function getInfoByHost(Request $request)
    {
        $host = empty($request->host)?'':$request->host;
        if(empty($host))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        $es = ESUtill4::getFieldInfo(config('param.HOSTMEDA_SEARCH'),'host',$host,1);
        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['data'] = $es[0];
        $data['status'] = 200;

        return HttpUtil::retrun_json($data,200);
    }

    /**
     * 根据 host 获取信息
     * @return [type] [description]
     */
    public function getInfoById(Request $request)
    {
        $id = empty($request->id)?'':$request->id;
        if(empty($host))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        $es = ESUtill4::getFieldInfo(config('param.HOSTMEDA_SEARCH'),'id',$id,1);
        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);
        $data['data'] = $es[0];
        $data['status'] = 200;
        return HttpUtil::retrun_json($data,200);
    }
}
