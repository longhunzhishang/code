<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;
use App\Http\Utils\HttpUtil;
USE App\Http\Utils\DBUtil;

use Illuminate\Http\Request;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;
    /**
     * 解决跨域请问问题
     */
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Credentials: true');

        $_TOKEN = empty($request->TOKEN)?'':$request->TOKEN;
        //getmaxid
        //
        
        if(stristr($_SERVER['REDIRECT_URL'], 'getmaxid')==FALSE)
        {
          
            if(stristr($_SERVER['REDIRECT_URL'], '/api/'))
            {
                if(empty($_TOKEN) || !in_array($_TOKEN, config('param._TOKEN_ARR_')))
                {

                    if(stristr($_SERVER['REDIRECT_URL'], '/api/v5/')===FALSE)
                    dd('用户认证失败');
                }
                // 直接从缓存读取数据
                // $key_cache = md5(implode('', $request->all()));
                // if(DBUtil::isHasRedisData($key_cache) && DBUtil::getRedisData($key_cache))
                // {
                //     return HttpUtil::retrun_json(DBUtil::getRedisData($key_cache),200);
                // }
            }
        }
        // if(!in_array($_SERVER['REMOTE_ADDR'], config('param.WHITE_HOST')))
        // {
        // 	abort(404);
        // }
    }
}
