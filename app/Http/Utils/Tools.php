<?php
namespace App\Http\Utils;

use Symfony\Component\HttpFoundation\Request;
use Dm\Request\V20151123 as Dm;
use Crypt;
use Session;
use Mail;
use Excel;
/**
 *
 */
class Tools
{
    /**
     * 范围查找 过滤条件
     * @return [type] [description]
     */
    public static function rangeParam($range,$curl_param,$iscampaign,$str_type = "1")
    {
        if(empty($range))return $curl_param;
        foreach ($range as $key => $value) {
            if($value)
            {
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $value);
                }else{
                    if($str_type == 1){
                        array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);
                    }else{
                        array_push($curl_param['query']['function_score']['query']['bool']['must'], $value);
                    }
                }
            }
        }
        return $curl_param;
    }

     public static function rangeParamt($range, $curl_param, $iscampaign, $str_type = "1")
    {
        if (empty($range)) return $curl_param;
        foreach ($range as $key => $value) {
            if ($value) {
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $value);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $value);

                }
            }
        }
        return $curl_param;
    }

    /**
     * 范围查找 过滤条件
     * @return [type] [description]
     */
    public static function rangeWHTParam($startTime,$endTime,$is_time,$max_width,$min_width,$is_width,$max_height,$min_height,$is_height)
    {
        $range = [];
        if($startTime && $endTime && empty($is_time)){

            // $range[] = [
            //     'range'=>[
            //         'date5'=>[
            //             'gte'=>date('Y-m-d H:i:s',$startTime),
            //             'lte'=>date('Y-m-d H:i:s',$endTime)
            //         ]
            //     ]
            // ];
             $range[] = [
                'bool'=>[
                    "should"=>[
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>date('Y-m-d H:i:s',$startTime),
                                    'lte'=>date('Y-m-d H:i:s',$endTime)
                                ]
                            ]
                        ],
                        [
                            'range'=>[
                                'v1_trend_days'=>[
                                    'gte'=>$startTime*1000,
                                    'lte'=>$endTime*1000
                                ]
                            ]
                        ]
                    ]
                ]
            ];

        }else if($startTime){

            // $range[] = [
            //     'range'=>[
            //         'date5'=>[
            //             'gte'=>date('Y-m-d H:i:s',$startTime),
            //         ]
            //     ]
            // ];
            $range[] = [
                'bool'=>[
                    "should"=>[
                        [
                            'range'=>[
                                'date5'=>[
                                    'gte'=>date('Y-m-d H:i:s',$startTime),
                                ]
                            ]
                        ],
                        [
                            'range'=>[
                                'v1_trend_days'=>[
                                    'gte'=>$startTime*1000,
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        if($max_width && empty($is_width))
        {
            $range[] = [
                'range'=>[
                    'width'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_width
                    ]
                ]
            ];

        }
        if($max_height && empty($is_height))
        {
            $range[] = [
                'range'=>[
                    'height'=>[
                        'gte'=>$min_height,
                        'lte'=>$max_height
                    ]
                ]
            ];

        }
        return $range;
    }
    /**
     * 过滤条件
     * @return [type] [description]
     */
    public static function valiedParam($valiedParam,$curl_param,$iscampaign,$str_type = '1')
    {
        if(empty($valiedParam))return $curl_param;

        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $term_k = ($key=='attribute04'?'match':'term');

                $item = [
                    $term_k=>[
                        $key=>$value
                    ]
                ];
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                }else{
                    if($str_type == 1)
                    {
                        array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
                    }
                    else{
                        array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                    }
                }
            }
        }
        return $curl_param;
    }

    public static function valiedParamArr($valiedParam,$curl_param,$iscampaign,$str_type = '1')
    {
        if(empty($valiedParam))return $curl_param;

        foreach ($valiedParam as $key => $value) {
            if($value)
            {
                $term_k = ($key=='attribute04'?'match':'terms');

                $item = [
                    $term_k=>[
                        $key=>$value
                    ]
                ];
                if($iscampaign==1)
                {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                }else{
                    if($str_type == 1)
                    {
                        array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);
                    }
                    else{
                        array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                    }
                }
            }
        }
        return $curl_param;
    }

    /**
     * 广告类型处理
     * @param  [type] $type [description]
     * @return [type]       [description]
     */
    public static function parseType($type)
    {
        $material = '';
        switch ($type) {
            case '1':
                $type = 'image';
                break;
            case '2':
                $type = 'swf';
                break;
            case '3':
                $type = 'flv';
                break;
            case '4':
                $type = 'html5';
                break;
            case '5':
                $material = 'native';
                break;
            default:
                $type = '';
                break;
        }
        return [
            'type'=>$type,
            'material'=>$material
        ];
    }

    /**
     * 广告数组类型处理
     * @param  [type] $type [description]
     * @return [type]       [description]
     */
    public static function parseTypeArr($type)
    {
        $material = [];
        $type_arr = [];
        foreach ($type as $key => $value){
            switch ($value) {
                case '1':
                    array_push($type_arr,'image');
                    break;
                case '2':
                    array_push($type_arr,'swf');
                    break;
                case '3':
                    array_push($type_arr,'flv');
                    break;
                case '4':
                    array_push($type_arr,'html5');
                    break;
                case '5':
                    array_push($material,'native');
                    break;
                default:
                    array_push($type_arr,'');
                    break;
            }

        }

        return [
            'type'=>$type_arr,
            'material'=>$material
        ];
    }
    

    /**
     * 发送邮箱 通知
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function sendMailWarn($data)
    {
        Mail::send('emails.'.$data['view'], $data, function ($message) use ($data) {
            $message->from('service@adbug.cn', 'Adbug广告搜索');
            // $message->attach($data['url'], $data);
            $message->to($data['email'])->subject($data['subject']);
            // $message->to($data['email'])->cc('1620683945@qq.com')->subject($data['subject']);
        });
    }

   
    /**
     * 发送邮箱 通知(新版)
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function sendMail($data)
    {
        $sendcloud = new \SendCloud("adbuguseremail", "cD7B94kyHxUZ4M5y", 'v1');
        $send = new \Maill();
        $send->setFrom("service@adbug.cn");
        $send->addTo($data['email']);
        $send->setSubject($data['subject']);
        $send->setRespEmailId(true);
        $url = $data['url'];
        $templateContent = new \TemplateContent();
        $templateContent->addVars("%url%", [$url]);
        $templateContent->setTemplateInvokeName("adbug_mail_activation");
        $send->setTemplateContent($templateContent);
        $sendcloud->sendTemplate($send);

    }



    public static function sendTemplateMail($data)
    {
        $sendcloud = new \SendCloud("adbuguseremail", "cD7B94kyHxUZ4M5y", 'v1');
        $send = new \Maill();
        $send->setFrom("service@adbug.cn");
        $send->setFromName("ADBUG");
        $send->addTo($data['email']);
        $send->setSubject($data['subject']);
        $send->setRespEmailId(true);
        $templateContent = new \TemplateContent();
        $templateContent->addVars("%url%", [$data['url']]);
        if(!empty($data['username'])){
          $templateContent->addVars("%username%", [$data['username']]);  
        }
        $templateContent->setTemplateInvokeName($data["view"]);
        $send->setTemplateContent($templateContent);
        $sendcloud->sendTemplate($send);

    }


    public static function sendTemplateMailNew($data)
    {
        $sendcloud = new \SendCloud("adbuguseremail", "cD7B94kyHxUZ4M5y", 'v1');
        $send = new \Maill();
        $send->setFrom("service@adbug.cn");
        $send->addTo($data['email']);
        $send->setSubject($data['subject']);
        $send->setRespEmailId(true);
        $templateContent = new \TemplateContent();
        foreach ($data["data"] as $key => $value) {
            $templateContent->addVars("%".$key."%", [$value]);
        }
        $templateContent->setTemplateInvokeName($data["view"]);
        $send->setTemplateContent($templateContent);
        $sendcloud->sendTemplate($send);

    }

     public static function sendSms($phonenum, $code)
    {
        $SMS_USER = "adbug_sms";
        $SMS_KEY = "46HaWXJzaIe5HjET0jQQPaejL1BycjmD";
        $smsTemplateId = 23114;
        $sendSms = new \SendCloudSMS($SMS_USER, $SMS_KEY);
        $smsMsg = new \SmsMsg();
        $smsMsg->addPhoneList([$phonenum]);
        $smsMsg->addVars("code", $code);
        $smsMsg->setTemplateId($smsTemplateId);
        $smsMsg->setTimestamp(time());
        $resonse = $sendSms->send($smsMsg);
        return $resonse->body();

    }



    public static function getFileUrl($host)
    {
        if(empty($host))return 0;
        $host_info = explode('.', $host);
        if(is_numeric(substr($host_info[0],0,1)) || in_array(strtolower(substr($host_info[0],0,1)), config('param.F_CHAR')))
        {
            return config('param.IMAGE_FILE_URL_NEW');
        }
        return config('param.IMAGE_FILE_URL');
    }
    /**
     * 重新制定 file.adbug.cn 的路径
     * @param  [type] $img_url [description]
     * @return [type]          [description]
     */
    public static function replaceFileUrl($img_url)
    {
        if(stristr($img_url, 'http://')===false)
        {
            $img_url = 'http://'.$img_url;
        }
        $su_url = explode('/', $img_url);

        if(count($su_url)>1)
        {
            $do_info = explode('.', $su_url[4]);

            $fircht_cahr = substr($do_info[0],0,1);

            if((is_numeric($do_info[0]) || is_numeric($fircht_cahr) || in_array(strtolower($fircht_cahr), config('param.F_CHAR'))))
            {
                if(strstr($img_url,'datasync2'))
                {
                    $img_url = str_replace('datasync2', 'datasync2', $img_url);
                }else{
                    $img_url = str_replace('datasync', 'datasync2', $img_url);
                }
            }
        }
        return $img_url;
    }

    /**
     * 导出数据
     * @param  [type] $title    [description]
     * @param  [type] $cellData [description]
     * @return [type]           [description]
     */
    public static function exportData($title,$cellData)
    {
        Excel::create($title,function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('xls');
    }
    /**
     * flag 1 type 类型 2 platform 平台 3 角色 过滤
     *
     * @param  [type] $param [description]
     * @param  [type] $type  [description]
     * @return [type]        [description]
     */
    public static function filedParse($param,$flag)
    {
        $type_material = '';
        $type_field = $flag==1?'type':'platform';

        if($flag==1)
        {
            switch ($param) {
                case '1':
                    $type_material = 'image';
                    break;
                case '2':
                    $type_material = 'swf';
                    break;
                case '3':
                    $type_material = 'flv';
                    break;
                case '4':
                    $type_material = 'html5';
                    break;
                case '5':
                    $type_material = 'native';
                    $type_field = 'material';
                    break;
            }
        }

        if($flag==2)
        {
            switch ($param) {
                case '1':
                    $type_material = 1;
                    break;
                case '2':
                    $type_material = 2;
                    break;
                case '3':
                    $type_material = 'android';
                    $type_field = 'attribute04';
                    break;
                case '4':
                    $type_material = 'ios';
                    $type_field = 'attribute04';
                    break;
                case '5':
                    $type_material = '5';
                    $type_field = 'platform';
                    break;
            }
        }

        if($flag==3)
        {
            switch ($param) {
                case 'advertiser':
                    # code...
                    $type_field = 'advertiser_na';
                    break;
                case 'publisher':
                    # code...
                    $type_field = 'publisher_na';
                    break;
                case 'subject':
                    # code...
                    $type_field = 'subject_md5';
                    break;
                case 'tracker':
                    # code...
                    $type_field = 'tracker_list';
                    break;
            }
        }

        return [
            'type_field'=>$type_field,
            'type_material'=>$type_material
        ];

    }

    public static function onLine()
    {
        return in_array($_SERVER['SERVER_ADDR'], config('param.ON_LIEN'))?true:false;
    }
    /**
     * 加密
     * @param  [type] $param [description]
     * @return [type]        [description]
     */
    public static function encrypt($param)
    {
        return Crypt::encrypt($param);
    }
    /**
     * 解密
     * @param  [type] $param [description]
     * @return [type]        [description]
     */
    public static function decrypt($param)
    {
        return Crypt::decrypt($param);
    }

    public static function isMethod()
    {
        if($_SERVER['REQUEST_METHOD']=='POST')
        {
            return 1;
        }
        return 0;

    }


    /**
     *  $_FILES["file"] = $file
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public static function fileUpload($file)
    {
        $dirfile = date('Ymd');
        $targetDir = "images/uploads/".$dirfile;
        self::createDir($targetDir);

        $cleanupTargetDir = true; // Remove old files
        $maxFileAge = 5 * 3600; // Temp file age in seconds

        $fileName = $file["name"];

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;


        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;


        if ($cleanupTargetDir) {
            if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
            }

            while (($filed = readdir($dir)) !== false) {
                $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $filed;

                if ($tmpfilePath == "{$filePath}.part") {
                    continue;
                }

                if (preg_match('/\.part$/', $filed) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
                    @unlink($tmpfilePath);
                }
            }
            closedir($dir);
        }

        if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }

        if (!empty($_FILES)) {
            if ($file["error"] || !is_uploaded_file($file["tmp_name"])) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
            }

            if (!$in = @fopen($file["tmp_name"], "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        if (!$chunks || $chunk == $chunks - 1) {
            rename("{$filePath}.part", $filePath);
        }

        $url = $dirfile.'/'.$fileName;

        $info = pathinfo("images/uploads/".$url);
        $newname = date('Ymd').'/'.md5($info['basename']).'.'.$info['extension'];

        rename('images/uploads/'.$url, 'images/uploads/'.$newname);

        return $newname;
    }
    public static function createDir($dir)
    {
        return is_dir($dir) or (self::createDir(dirname($dir)) and mkdir($dir, 0777));
    }

    public static function filecrate()
    {
        $dirfile = date('Ymd');
        $targetDir = "images/uploads/".$dirfile;
        self::createDir($targetDir);

        $cleanupTargetDir = true; // Remove old files
        $maxFileAge = 5 * 3600; // Temp file age in seconds

        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;


        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;


        if ($cleanupTargetDir) {
            if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
            }

            while (($file = readdir($dir)) !== false) {
                $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

                if ($tmpfilePath == "{$filePath}.part") {
                    continue;
                }

                if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
                    @unlink($tmpfilePath);
                }
            }
            closedir($dir);
        }


        if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }

        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
            }

            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        if (!$chunks || $chunk == $chunks - 1) {
            rename("{$filePath}.part", $filePath);
        }

        $url = $dirfile.'/'.$fileName;

        $info = pathinfo("images/uploads/".$url);
        $newname = date('Ymd').'/'.md5($info['basename']).'.'.$info['extension'];

        rename('images/uploads/'.$url, 'images/uploads/'.$newname);

        return $newname;


    }
     public static function valiedParamt($valiedParam, $curl_param, $iscampaign, $str_type = '1')
    {
        if (empty($valiedParam)) return $curl_param;
        foreach ($valiedParam as $key => $value) {
            if ($key == "attribute04" && count($value) != 0) {
                $item = [
                    'terms' => [
                        $key => $value
                    ]
                ];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'][0]["bool"]['should'], $item);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'][0]["bool"]['should'], $item);

                }

            } elseif ($key == "platform" && count($value) != 0) {
                $item = [
                    'terms' => [
                        $key => $value
                    ]
                ];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'][0]["bool"]['should'], $item);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'][0]["bool"]['should'], $item);
                }

            } elseif ($key == "type" && count($value) != 0) {
                $item = [
                    'terms' => [
                        $key => $value
                    ]
                ];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'][1]["bool"]['should'], $item);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'][1]["bool"]['should'], $item);
                }

            } elseif ($key == "material" && count($value) != 0) {
                $item = [
                    'terms' => [
                        $key => $value
                    ]
                ];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'][1]["bool"]['should'], $item);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'][1]["bool"]['should'], $item);
                }

            } elseif ($key == "tracker_list" && is_array($value)){
                $tracker_m = ["bool" => ["should" => [
                    [
                        'terms' => [
                            "tracker_list" => $value
                        ]
                    ],
                    [
                        'terms' => [
                            "tags_list" => $value
                        ]
                    ]
                ]]];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $tracker_m);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $tracker_m);
                }
            }elseif (is_array($value) && count($value) != 0) {
                $item = [
                    'terms' => [
                        $key => $value
                    ]
                ];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);

                }

            } elseif (!is_array($value) && $value != '') {
                $item = [
                    'term' => [
                        $key => $value
                    ]
                ];
                if ($iscampaign == 1) {
                    array_push($curl_param['query']['function_score']['query']['bool']['must'], $item);
                } else {
                    array_push($curl_param['query']['function_score']['filter']['bool']['must'], $item);

                }

            }

        }
        return $curl_param;
    }

}