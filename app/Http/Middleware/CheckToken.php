<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Utils\UserAccess;
use Illuminate\Support\Facades\Auth;
use App\Http\Utils\HttpUtil;
use DB;
use Illuminate\Support\Facades\Redis;

class CheckToken
{

    /**
     * Handle an incoming request.
     * status:214(未登录)
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
     public function handle($request, Closure $next)
    {
        if (UserAccess::isLogin(UserAccess::routeInfo()) == false) return HttpUtil::retrun_json(["code" => 214, "message" => "该用户未登录或者失效"], 200);
        $user = UserAccess::getUserInfo();
        if (count($user) != 0 && !empty($user["single_token"]) && $request->session()->getId() != $user["single_token"]) {
            return HttpUtil::retrun_json(["code" => 215, "message" => "该账户已经被他人登录,请及时修改密码保证账户安全"], 200);
        }
        if (count($user) != 0 && ($this->filterlogin($user["email"]) || empty($user["single_token"]))) {
            return redirect("logout");
        }
        
        $user_agent = $request->header("user-agent");
        if ((strpos($user_agent, "Chrome/70.0.3538.77") == true) && (strpos($user_agent, "Mac OS X 10_13_1") == true)) {
            return HttpUtil::retrun_json(["code" => 214, "message" => "该用户未登录或者失效"], 200);
        }
        return $next($request);
    }


    
     public function filterlogin($email)
    {
        $email_free = ["bjyunrui.com","yunrui.net","yunrui.com","skytech.cn","teamtop.com"];
        $email = strtolower($email);
        $email_is = explode('@', $email);

        if (empty($email_is[1])) {
            return true;
        }
        foreach ($email_free as $key => $value) {
            if ($email_is[1] == $value) {
                return true;
            }
        }
        return false;
    }
}
