<?php
namespace App\Http\Utils;

use Symfony\Component\HttpFoundation\Request;

use Crypt;
use Session;
use Mail;
use Excel;
/**
 *
 */
class Tools2
{

    /**
     * 过滤条件
     * @return [type] [description]
     */
    public static function valiedParam($valiedParam, $curl_param,$tyle="1")
    {
        if (empty($valiedParam)) return $curl_param;

        foreach ($valiedParam as $key => $value) {

            $term_k = is_array($value) ? "terms" : "term";
            $item = [
                $term_k => [
                    $key => $value
                ]
            ];
            switch ($tyle){
                case "1":array_push($curl_param['query']['bool']['must'], $item);break;
            }

        }
        return $curl_param;
    }


    /**
     * json数组类型
     * @return [type] [description]
     */
    public  static function paramStyle($type = "1",$vfield,$aggs_all){
        switch ($vfield){
            case "advertiser":$vfield_value = "advertiser_na";break;
            case "publisher":$vfield_value = "publisher_na";break;
            case "tags":$vfield_value = "tags_list";break;
            case "tracker":$vfield_value = "tracker_list";break;
            case "subject":$vfield_value = "subject_md5";break;
            default: $vfield_value = $vfield;break;
        }
        switch ($type) {
            case "1" :
                $curl_param = [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'must_not' => [],
                            'should_not' => []
                        ]
                    ],
                    "aggs" => [
                        $vfield=>[
                            'terms'=>[
                                'field'=>$vfield_value,
                                'execution_hint'=>"map",
                                'size'=>$aggs_all,
                                'collect_mode'=>"breadth_first"
                            ]
                            ,
                            "aggs" => [
                                $vfield . '_type' => [
                                    'terms' => [
                                        'field' => "type"
                                    ]
                                ],
                                $vfield.'_type_total'=>[
                                    'cardinality'=>[
                                        'field'=>"type"
                                    ]
                                ],
                                $vfield . '_platform' => [
                                    'terms' => [
                                        'script' => [
                                            "inline"=>"doc['platform'].value +','+ doc['attribute04'].value"
                                        ]
                                    ]
                                ],
                                $vfield.'_advertiser_total'=>[
                                    'cardinality'=>[
                                        'field'=>"advertiser_na"
                                    ]
                                ],
                                $vfield.'_publisher_total'=>[
                                    'cardinality'=>[
                                        'field'=>"publisher_na"
                                    ]
                                ],
                                $vfield.'_tracker_total'=>[
                                    'cardinality'=>[
                                        'field'=>"tracker_list"
                                    ]
                                ],
                                $vfield.'_tag_total'=>[
                                    'cardinality'=>[
                                        'field'=>"tags_list"
                                    ]
                                ],
                            ]
                        ],
                        $vfield.'_total'=>[
                            'cardinality'=>[
                                'field'=>$vfield_value
                            ]
                        ]
                    ],
                    'sort' => [
                        [
                            "date5" => [
                                "order" => "desc"
                            ]
                        ]
                    ],
                    'from' => 0,
                    'size' => 0
                ];
                break;
        }
        return $curl_param;
    }


      /**
     * json数组类型
     * @return [type] [description]
     */
    public  static function paramStyle1($type = "1",$vfield,$aggs_all){
        switch ($vfield){
            case "advertiser":$vfield_value = "advertiser_na";break;
            case "publisher":$vfield_value = "publisher_na";break;
            case "tags":$vfield_value = "tags_list";break;
            case "tracker":$vfield_value = "tracker_list";break;
            case "subject":$vfield_value = "subject_md5";break;
            default: $vfield_value = $vfield;break;
        }
        switch ($type) {
            case "1" :
                $curl_param = [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'must_not' => [],
                            'should_not' => []
                        ]
                    ],
                    "aggs" => [
                        $vfield=>[
                            'terms'=>[
                                'field'=>$vfield_value,
                                'execution_hint'=>"map",
                                'size'=>$aggs_all,
                                'collect_mode'=>"breadth_first"
                            ]
                        ],
                        $vfield.'_total'=>[
                            'cardinality'=>[
                                'field'=>$vfield_value
                            ]
                        ]
                    ],
                    'sort' => [
                        [
                            "date5" => [
                                "order" => "desc"
                            ]
                        ]
                    ],
                    'from' => 0,
                    'size' => 0
                ];
                break;
        }
        return $curl_param;
    }

    /**
     * 范围查找 过滤条件
     * @return [type] [description]
     */
    public static function rangeParam($rangeParam, $curl_param, $tyle = "1")
    {
        if (empty($rangeParam)) return $curl_param;
        foreach ($rangeParam as $key => $value) {
            if (count($value) == 2) {
                $min = min($value);
                $max = max($value);
                $range = [
                    'range' => [
                        $key => [
                            'gte' => $min,
                            'lte' => $max
                        ]
                    ]
                ];
                switch ($tyle) {
                    case "1":
                        array_push($curl_param['query']['bool']['must'], $range);
                        break;
                }

            }

        }
        return $curl_param;
    }

    
    /**
     * 加密函数
     */
    public static function encrypt($data, $key)
    {
        $key = md5($key);
        $x = 0;
        $len = strlen($data);
        $l = strlen($key);
        $char = '';
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) {
                $x = 0;
            }
            $char .= $key{$x};
            $x++;
        }
        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord($data{$i}) + (ord($char{$i})) % 256);
        }
        return base64_encode($str);
    }

    

    /**
     * 解密函数
     */
    public static function decrypt($data, $key)
    {
        $key = md5($key);
        $x = 0;
        $data = base64_decode($data);
        $len = strlen($data);
        $l = strlen($key);
        $char = '';
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) {
                $x = 0;
            }
            $char .= substr($key, $x, 1);
            $x++;
        }
        for ($i = 0; $i < $len; $i++) {
            if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
                $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
            } else {
                $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
            }
        }
        return $str;
    }



}
