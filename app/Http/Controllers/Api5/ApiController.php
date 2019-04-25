<?php

namespace App\Http\Controllers\Api5;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Utils\HttpUtil;
use App\AuthSystem;
use DB;
use Illuminate\Http\Response;

/**
 * finn
 * 20171214
 * 爬虫使用 获取token  token 过期时间设置
 */
class ApiController extends Controller
{
    public $private_key = "adbug_1681781234";
    public $init_value     = "168178_adbug_123";

    function __construct(Request $request)
    {
        //$name = empty($request->name)?'':$request->name;

        // $type = empty($request->type)?1:$request->type;
        // $M_R_L = $type==1?MCRYPT_RIJNDAEL_256:MCRYPT_RIJNDAEL_128;
        // $key = $type==1?md5($this->private_key):md5($this->private_key);
        // $iv = $type==1?md5($this->init_value):$this->init_value;

        
    }


    public function getTokenNodeJs(Request $request)
    {
        $name   = empty($request->name)?'':$request->name;//加密数据
        if(empty($name))return HttpUtil::retrun_json(['token'=>'','msg'=>'name must not null','code'=>206],200);

        $name = strtolower($name);
        if(!$this->authName($name))
        {
            return HttpUtil::retrun_json(['token'=>'','msg'=>'name not in white list','code'=>206],200);
        }
        $out_time = strtotime("+5 month");
        $encrypted_time = $this->nodeJsEncode($out_time);
        //加密
        $encrypted = $this->nodeJsEncode($name);
        $token = base64_encode($encrypted).'_TIME_'.base64_encode($encrypted_time);
 
        $auth_system = AuthSystem::where(['auth_name'=>$name,'mcrypt_rijndael_model'=>1120])->first();
        if(empty($auth_system)) $auth_system = new AuthSystem();
        $auth_system->auth_name = $name;
        
        $auth_system->auth_token = $token;
        $auth_system->auth_start_time = date('Y-m-d',time());
        $auth_system->auth_session_time = $out_time;
        $auth_system->type = 1;
        $auth_system->mcrypt_rijndael_model = 1120;
        $auth_system->save();
        return HttpUtil::retrun_json(['token'=>$token,'code'=>200],200);


    }

    private function nodeJsEncode($param)
    {
        $cipher = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        $key256 = "12345678901234561234567890123456";
        $iv = '1234567890123456';
        mcrypt_generic_init($cipher, $key256, $iv);
        // PHP pads with NULL bytes if $plainText is not a multiple of the block size
        $cipherText256 = mcrypt_generic($cipher,$param);
        mcrypt_generic_deinit($cipher);
        $cipherHexText256 =bin2hex($cipherText256);
        return $cipherHexText256;
    }
    /**
     * name 企业名称 唯一性
     * 初始秘钥private_key
     * 初始向量init_value
     * name 加密的数据
     * token 返回数据
     * 加密模式为 MCRYPT_MODE_CBC
     * 加密算法 MCRYPT_RIJNDAEL_256  mcrypt_rijndael
     * base64_encode TOKEN 和 过期时间 字符串拼接 返回
     * 填充模式只有ZeroPadding
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getToken(Request $request)
    {
        $name   = empty($request->name)?'':$request->name;//加密数据
        if(empty($name))return HttpUtil::retrun_json(['token'=>'','msg'=>'name must not null','code'=>206],200);

        $name = strtolower($name);
        if(!$this->authName($name))
        {
            return HttpUtil::retrun_json(['token'=>'','msg'=>'name not in white list','code'=>206],200);
        }
        $name = md5($name);
        // echo $name ,'<br/>';
        // $out_time = date("Ymd",strtotime("+2 month"));
        $out_time = strtotime("+5 month");
        $encrypted_time = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->private_key), $out_time, MCRYPT_MODE_CBC, md5($this->init_value));
        //加密
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->private_key), $name, MCRYPT_MODE_CBC, md5($this->init_value));

        $token = base64_encode($encrypted).'_TIME_'.base64_encode($encrypted_time);
 
        $auth_system = AuthSystem::where(['auth_name'=>$name,'mcrypt_rijndael_model'=>256])->first();
        if(empty($auth_system)) $auth_system = new AuthSystem();
        $auth_system->auth_name = $name;
        // echo $encrypted,'<br/>';
        // echo $token,'<br/>';
        // dd($out_time);
        $auth_system->auth_token = $token;
        $auth_system->auth_start_time = date('Y-m-d',time());
        $auth_system->auth_session_time = $out_time;
        $auth_system->type = 1;
        $auth_system->mcrypt_rijndael_model = 256;
        $auth_system->save();
        return HttpUtil::retrun_json(['token'=>$token,'code'=>200],200);
    }

    public function getToken128(Request $request)
    {
        $name   = empty($request->name)?'':$request->name;//加密数据
        if(empty($name))return HttpUtil::retrun_json(['token'=>'','msg'=>'name must not null','code'=>206],200);

        $name = strtolower($name);
        if(!$this->authName($name))
        {
            return HttpUtil::retrun_json(['token'=>'','msg'=>'name not in white list','code'=>206],200);
        }

        // echo $name ,'<br/>';
        // $out_time = date("Ymd",strtotime("+2 month"));
        $out_time = strtotime("+5 month");

        $encrypted_time = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->private_key, $out_time, MCRYPT_MODE_CBC, $this->init_value);

        //加密
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->private_key, $name, MCRYPT_MODE_CBC, $this->init_value);

        $token = base64_encode($encrypted).'_TIME_'.base64_encode($encrypted_time);
 
        $auth_system = AuthSystem::where(['auth_name'=>$name,'mcrypt_rijndael_model'=>128])->first();
        if(empty($auth_system)) $auth_system = new AuthSystem();
        $auth_system->auth_name = $name;
        // echo $encrypted,'<br/>';
        // echo $token,'<br/>';
        // dd($out_time);
        $auth_system->auth_token = $token;
        $auth_system->auth_start_time = date('Y-m-d',time());
        $auth_system->auth_session_time = $out_time;
        $auth_system->type = 1;
        $auth_system->mcrypt_rijndael_model = 128;
        $auth_system->save();
        return HttpUtil::retrun_json(['token'=>$token,'code'=>200],200);
    }

    public function authNodeJSToken(Request $request)
    {
        // $token = empty($request->token)?'':$request->token;
    
        $name = empty($request->name)?'':$request->name;

        $mi_string = [];

        if(empty($name))
        {
            $mi_string = ['status'=>false,'out_time'=>0,'msg'=>'name or token must not null'];
            return $this->nodeJsEncode($mi_string);
        }
        $name = strtolower($name);
        if(!$this->authName($name))
        {
            return $this->nodeJsEncode(json_encode(['status'=>false,'out_time'=>0,'msg'=>'name is not white list','code'=>208]));
        }

        $auth_system = AuthSystem::where(['auth_name'=>$name,'mcrypt_rijndael_model'=>1120])->first();

        if(empty($auth_system))return $this->nodeJsEncode(json_encode(['status'=>false,'out_time'=>0,'msg'=>'name is not white list','code'=>208]));

        // dd($auth_system);
        $auth_session_time = $auth_system->auth_session_time;
        $now_time = date('Y-m-d',time());

        if(strtotime($now_time)>=$auth_session_time){

            $auth_system->type = 0;
            $auth_system->save();
            return $this->nodeJsEncode(json_encode(['status'=>false,'out_time'=>0,'msg'=>'token is out of time','code'=>204]));
        }

        $auth_token = $auth_system->auth_token;

        $auth_token_tmp = explode('_TIME_', $auth_token);

        $token = base64_decode($auth_token_tmp[0]);

        $out_time = base64_decode($auth_token_tmp[1]);

        $json_string = json_encode(['status'=>true,'out_time'=>1,'msg'=>'token auth is ok','code'=>200,'true_token'=>$token]);

        return $this->nodeJsEncode($json_string);
    }
    /**
     * 验证TOKEN
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function authToken(Request $request)
    {
        // $token = empty($request->token)?'':$request->token;
        $type = empty($request->type)?1:$request->type;

        $M_R_L = $type==1?MCRYPT_RIJNDAEL_256:MCRYPT_RIJNDAEL_128;

        $model_length = $type==1?'256':128;

        $name = empty($request->name)?'':$request->name;

        $key = $type==1?md5($this->private_key):$this->private_key;
        $iv = $type==1?md5($this->init_value):$this->init_value;



        $mi_string = [];

        if(empty($name))
        {
            $mi_string = ['status'=>false,'out_time'=>0,'msg'=>'name or token must not null'];
            $mi_string = $this->mcryptEn($M_R_L,$key,json_encode($mi_string),MCRYPT_MODE_CBC,$iv);
            return $mi_string;
        }
        $name = strtolower($name);
        if(!$this->authName($name))
        {
            $mi_string = $this->mcryptEn($M_R_L,$key,json_encode(['status'=>false,'out_time'=>0,'msg'=>'name is not white list','code'=>208]),MCRYPT_MODE_CBC,$iv);
            return $mi_string;
        }

        $name = $type==1?md5($name):$name;

        $auth_system = AuthSystem::where(['auth_name'=>$name,'mcrypt_rijndael_model'=>$model_length])->first();

        if(empty($auth_system))
        {
            $mi_string = $this->mcryptEn($M_R_L,$key,json_encode(['status'=>false,'out_time'=>0,'msg'=>'name is non-existent; ','code'=>204]),MCRYPT_MODE_CBC,$iv);

            return $mi_string;
        }

        // dd($auth_system);
        $auth_session_time = $auth_system->auth_session_time;
        $now_time = date('Y-m-d',time());

        if(strtotime($now_time)>=$auth_session_time){

            $auth_system->type = 0;
            $auth_system->save();
            $mi_string = $this->mcryptEn($M_R_L,$key,json_encode(['status'=>false,'out_time'=>0,'msg'=>'token is out of time','code'=>204]),MCRYPT_MODE_CBC,$iv);

            return $mi_string;
        }

        $auth_token = $auth_system->auth_token;

        $auth_token_tmp = explode('_TIME_', $auth_token);

        $token = base64_decode($auth_token_tmp[0]);

        $out_time = base64_decode($auth_token_tmp[1]);

        $true_token = '';
        $true_time = '';

        if($type==1)
        {
            $true_token = mcrypt_decrypt($M_R_L, md5($this->private_key), $token,MCRYPT_MODE_CBC, md5($this->init_value));

            $true_time = mcrypt_decrypt($M_R_L, md5($this->private_key), $out_time,MCRYPT_MODE_CBC, md5($this->init_value));
        }else{
            $true_token = mcrypt_decrypt($M_R_L, $this->private_key, $token,MCRYPT_MODE_CBC, $this->init_value);
            $true_time = mcrypt_decrypt($M_R_L, $this->private_key, $out_time,MCRYPT_MODE_CBC, $this->init_value);
        }

        $true_token = str_replace("\x00", '', $true_token);

        $true_token = $type==1?md5($true_token):$true_token;

        $json_string = json_encode(['status'=>true,'out_time'=>1,'msg'=>'token auth is ok','code'=>200,'true_token'=>$true_token]);

        $mi_string = $this->mcryptEn($M_R_L,$key,$json_string,MCRYPT_MODE_CBC,$iv);

        return $mi_string;

        // $nn = '68WA8Mw6OGjJ0hicuUjhaw==';

        // $true_token_2 = mcrypt_decrypt($M_R_L, $key, base64_decode($nn),MCRYPT_MODE_CBC, $iv);
        // // 
        // dd($true_token_2);

        // $true_token_2 = mcrypt_decrypt($M_R_L, $key, base64_decode($mi_string),MCRYPT_MODE_CBC, $iv);

        // dd($true_token_2);

    }


    public function mcryptEn($length,$key,$string,$model,$iv)
    {
        $string = mcrypt_encrypt($length, $key, $string, $model, $iv);
        $base_string = base64_encode($string);

        // $base_de_string = mcrypt_decrypt($length, $key, $string, $model, $iv);

        return $base_string;
    }
    /**
     * 验证 用户名是否是白名单
     * @return [type] [description]
     */
    public function authName($name)
    {
        $auth_name = DB::table('auth_name')->where(['auth_name'=>$name,'type'=>1])->first();

        if(!empty($auth_name) && $auth_name->auth_name!=$name)
        {
            return false;
        }
        return empty($auth_name)?false:true;
    }
    /**
     * f 文件名称
     * v 文件版本 
     * r 角色 1 adbug 2 数治科技
     * @return [type] [description]
     */
    public function getJSContent(Request $request)
    {
        $v = empty($request->v)?'':$request->v;
        $f = empty($request->f)?'':$request->f;
        $r = empty($request->r)?'':$request->r;

        if(empty($r))return HttpUtil::retrun_javascript(['status'=>false,'msg'=>'请输入要查询的企业名称'],200);

        if(empty($f))return HttpUtil::retrun_javascript(['status'=>false,'msg'=>'请输入要返回的JS文件'],200);

        $dir="";
        switch ($r) {
            case '1':
                $dir = './fun/adbug/';
                break;
            case '2':
                $dir = './fun/shuzhi/';
                break;
            case '3':
                $dir = './fun/huibei/';
                break;
        }

        if(empty($v))$v = $this->getVersion($r);

        $dir_v = $dir.$v.'/'.$f.'.js';

        if(!file_exists($dir_v))
        {
            return HttpUtil::retrun_javascript(['status'=>false,'msg'=>'该文件不存在'],200);
        }

        $js_content = file_get_contents($dir_v);

        // return $js_content;

        return HttpUtil::retrun_javascript($js_content,200);
        // dd($js_content);
    }
    /**
     * 获取最细腻版本号
     * r 1 adbug 2 数治
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getJSVersion(Request $request)
    {
        $r = empty($request->r)?'':$request->r;

        if(empty($r))return HttpUtil::retrun_javascript(['status'=>false,'msg'=>'请输入要查询的企业名称'],200);

        $v = $this->getVersion($r);
        return HttpUtil::retrun_javascript(['status'=>true,'v'=>$v],200);
    }

    public function getVersion($r)
    {
        $dir="";
        switch ($r) {
            case '1':
                $dir = './fun/adbug/';
                break;
            case '2':
                $dir = './fun/shuzhi/';
                break;
        }
        $num = [];
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if($file=='.' || $file=='..')continue;
                    $num[] = $file;
                }
                closedir($dh);
            }
        }
        return max($num);
    }
    /**
     * 保存piwik 到 es dict 里面 
     * finn
     * 2018-1-9
     * es 额外词典补充 三天 同起一下 es
     * @return [type] [description]
     */
    public function saveDictPiwik(Request $request)
    {   

        // header("Content-type: text/html; charset=utf-8"); 
        // header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT');
        // header('ETags: ',time());
        //  //         response.setDateHeader("Last-Modified", Long.valueOf(modified));
        //  // response.setHeader("ETags", wordList.size() + "");
        // echo 'java技术',PHP_EOL,'百度X',PHP_EOL;
        // exit;
        $date = date('Y-m-d',strtotime('-1 day'));
        $piwik = 'http://ana.adbug.cn/index.php?module=API&method=Actions.getSiteSearchKeywords&idSite=1&period=day&date='.$date.'&format=JSON&token_auth=3af9e184a5bfc41e5c0287c773b51b74';
        $piwik_data = file_get_contents($piwik);
        $piwik_data = json_decode($piwik_data,true);
        $data = [];
        foreach ($piwik_data as $key => $value) {
    
            $label = $value['label'];
            if(empty($label) || strlen($label)<=4 || strlen($label) > 15)
            {
                unset($piwik_data[$key]);
                continue;
            }
            if(preg_match("/[\'-- , :;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$label)){  //不允许特殊字符
                unset($piwik_data[$key]);
                continue;
            }
            $data[] = $label;
        }
        
        
        $new_word = [];
        $is_new = false;
        foreach ($data as $key => $value) {
            
            $ex_dict = DB::table('ex_dict')->where('wd',$value)->first();
            if(empty($ex_dict))
            {
                $is_new = true;
                $da['wd'] = $value;
                $da['date'] = $date;
                DB::table('ex_dict')->insert($da);
                $new_word[] = $value;
            }
        }

        $path = 'D:\ziliao\elasticsearch-2.3.3\plugins\ik\config\custom\mydict.dic';
        if(!empty($is_new))
        {
            foreach ($new_word as $key => $value) {
                file_put_contents($path, $value.PHP_EOL,FILE_APPEND);
            }
        }
        //县写入
        // foreach ($data as $key => $value) {
        //      file_put_contents("D:/dict.txt",$value.PHP_EOL, FILE_APPEND);
        // }
        //读取
        // $read_file = file_get_contents('D:/dict.txt');
        
        // echo '火山小视频'.PHP_EOL;
        // echo file_get_contents('D:/dict.txt');

    }

      public function getDataAttribute(Request $request)
    {
        $id = $request->id;
        $rs = DB::table("addata_2018")->select("attribute07")->where("id", $id)->first();
        return HttpUtil::retrun_json(['data' => $rs->attribute07, 'code' => 200], 200);

    }
}