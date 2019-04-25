<?php
namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/16
 * Time: 14:36
 */

class IntegrationController extends Controller{

    function __construct(Request $request)
    {
        header('Access-Control-Allow-Origin', '*');
    }

    /**
     * 积分添加
     * @param Request $request
     * @return mixed
     */
    public function addIntegration(Request $request)
    {
        if(empty($request->wechat_account))return HttpUtil::retrun_json(['status'=>203,'msg'=>'微信号为空'],200);
        if(empty($request->md5))return HttpUtil::retrun_json(['status'=>203,'msg'=>'广告码为空'],200);
        if(empty($request->md5=="undefined"))return HttpUtil::retrun_json(['status'=>203,'msg'=>'广告码为undefined'],200);
        $Integration = new \App\Models\IntegrationModel();
        $Integration->igCount($request);
        return HttpUtil::retrun_json(['status'=>200,'msg'=>'请求成功'],200);
    }
}