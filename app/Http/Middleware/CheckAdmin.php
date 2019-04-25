<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Utils\UserAccess;
use App\Http\Utils\HttpUtil;

class CheckAdmin
{


    public function handle($request, Closure $next)
    {
        $user = UserAccess::getUserInfo();
        if(empty($user))return HttpUtil::retrun_json(["code" => 215, "message" => "未登录用户禁止使用"], 200);
        if($user["type"] != 1)return HttpUtil::retrun_json(["code" =>"214", "message" => "非管理员禁止使用"], 200);
        return $next($request);
    }



}
