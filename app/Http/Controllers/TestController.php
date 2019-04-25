<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use Illuminate\Http\Request;

class TestController extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;
    /**
     * 解决跨域请问问题
     */
    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin:*');

        $_TOKEN = empty($request->TOKEN)?'':$request->TOKEN;

        if(stristr($_SERVER['REDIRECT_URL'], '/test/'))
        {
            if(empty($_TOKEN) || $_TOKEN!==config('param._AUTH_TOKEN_'))dd('用户认证失败');
        }
    }
}
