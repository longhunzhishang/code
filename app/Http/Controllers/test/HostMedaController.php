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
class HostMedaController extends Controller
{
    

    public function getInfoByHost()
    {
        return view('test.hostmeda.host');
    }
    /**
     * 根据 host 获取信息
     * @return [type] [description]
     */
    public function getInfoByHostTest(Request $request)
    {
        $host = empty($request->host)?'':$request->host;
        if(empty($host))return HttpUtil::retrun_json(HttpUtil::returnStatus(207),200);
        
        $es = ESUtil::getFieldInfo(config('param.HOSTMEDA'),'host',$host,1);

        if(empty($es))return HttpUtil::retrun_json(config('param.ES_DATA_NULL'),200);

        $data['data'] = $es[0];
        $data['status'] = 200;
        
        return HttpUtil::retrun_json($data,200);
    }

}
