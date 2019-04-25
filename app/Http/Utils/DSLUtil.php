<?php

namespace App\Http\Utils;

use Illuminate\Http\Request;

/**
 * fyj ES 工具类
 * 20160624
 */
class DSLUtil
{

    /**
     * range  数据 查询DSL
     * @param  [type] $start_time [description]
     * @param  [type] $end_time   [description]
     * @param  [type] $width      [description]
     * @param  [type] $height     [description]
     * @param  [type] $min_width  [description]
     * @param  [type] $min_height [description]
     * @param  [type] $curl_param [description]
     * @return [type]             [description]
     */
    public static function rangeDSlQuery($start_time,$end_time,$min_width,$max_width,$min_height,$max_height)
    {

        $curl_param = [];
        if($start_time && $end_time)
        {
            $item = [
                'range'=>[
                    'created_date'=>[
                        'gte'=>$start_time,
                        'lte'=>$end_time
                    ]
                ]
            ];
            $curl_param[] = $item;
        }else if($start_time || $end_time)
        {
            if($start_time)
            {
                $item = [
                    'range'=>[
                        'created_date'=>[
                            'gte'=>$start_time
                        ]
                    ]
                ];
            }
            if($end_time)
            {
                $item = [
                    'range'=>[
                        'created_date'=>[
                            'lte'=>$end_time
                        ]
                    ]
                ];
            }
            $curl_param[] = $item;
        }
        if($min_width && $max_width)
        {
            $item =[
                'range'=>[
                    'width'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_width
                    ]
                ]
                
            ];
            $curl_param[] = $item;
        }else if($max_width){
            $item=[
                'range'=>[
                    'width'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_width
                    ]
                ]
                
            ];
           $curl_param[] = $item;
        }

        if($min_height && $max_height)
        {
             $item =[
                'range'=>[
                    'height'=>[
                        'gte'=>$min_height,
                        'lte'=>$max_height
                    ]
                ]
                
            ];
           $curl_param[] = $item;
        }else if($max_height){
            $item=[
                'range'=>[
                    'height'=>[
                        'gte'=>$min_width,
                        'lte'=>$max_height
                    ]
                ]
                
            ];
            $curl_param[] = $item;
        }
        return $curl_param;
    }
    /**
     * @param $from 分页
     * @param $size 显示条数
     * @return array
     */
    public  static function  baseDSLQuery($from,$size)
    {
        $curl_param = [
            'from'=>$from,
            'size'=>$size
        ];
        return $curl_param;
    }
    /**
     * @param $from 分页
     * @param $size 显示条数
     * @param $sort 默认 id (创建时间 created_date) desc 
     * @return array
     */
    public  static function  baseDSLSortQuery($from,$size,$sort)
    {
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'sort'=>[
                'created_date'=>[
                    'order'=>$sort
                ]
            ]
        ];
        return $curl_param;
    }
    /**
     * term 基础使用
     * @param  [type] $field [description]
     * @param  [type] $value [description]
     * @return [type]        [description]
     */
    public static function baseTerm($field,$value)
    {
        return [
            'term'=>[
                $field=>$value
            ]
        ];
    }
    public static function baseTerms($field,$value)
    {
        return [
            'terms'=>[
                $field=>$value
            ]
        ];
    }
    /**
     * 根据 field 排序
     * desc 排序方式 desc  asc
     * @return [type] [description]
     */
    public static function sortByTime($field,$desc)
    {
        return [
            $field=>[
                'order'=>$desc
            ]
        ];
    }
    public static function baseSort()
    {
        return [
            [
                '_score'=>[
                    'order'=>'desc'
                ]
            ],
             [
                'date5'=>[
                    'order'=>'desc'
                ]
            ]
            
        ];
    }

    public static function baseIdSort()
    {
        return [
            [
                'id'=>[
                    'order'=>'desc'
                ]
            ]
        ];
    }

    /**
     * @param int $from
     * @param int $size
     * @param $param
     * @return array
     * 在所有字段中查询
     */
    public  static function baseDSLQueryAll($from=0,$size=10,$param)
    {
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        'query_string'=>[
                            'default_field'=>'_all',
                            'query'=>$param
                        ]
                    ]
                ]
            ]
        ];
        return $curl_param;
    }

    /**
     * @param int $from
     * @param int $size
     * @param $param
     * @param array $fileds
     * @return array
     * 根据指定多个字段进行查询
     */
    public  static function baseDSLQueryAllField($from=0,$size=10,$param,$fileds=[])
    {
        $curl_param = [
            'from'=>$from,
            'size'=>$size,
            'query'=>[
                'multi_match'=>[
                    'query'=>$param,
                    'fields'=>$fileds
                ]
            ]
        ];
        return $curl_param;
    }
    /**
     * 域名查询 DSL 语句
     * 2017-1-13
     * @param $size
     * @param $param
     * @param int $role
     * @param $type
     * @return array
     */
    public static function  domainDLSQuery($size=10,$param,$role = 0,$type=0)
    {
        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'should'=>[
                        [
                            'match'=>[
                                'cname'=>[
                                    'query'=>$param,
                                    'boost'=>100
                                ]
                            ]
                        ],
                        [
                            'match_phrase_prefix'=>[
                                'host'=>$param
                            ]
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'role'=>0
                        ]
                    ]
                ]

            ]
        ];
        if($type==1)
        {
            unset($curl_param['query']['bool']['must_not']);
            if(!empty($role))array_push($curl_param['query']['bool']['should'],['term'=>['role'=>$role]]);
        }
        return $curl_param;
    }



    /**
     * 查询 
     * @param  integer $size  [description]
     * @param  [type]  $param [description]
     * @param  integer $role  [description]
     * @param  integer $type  [description]
     * @param  [type]  $m_s   [must or should]
     * @param  integer $boost [description]
     * @return [type]         [description]
     */
    public static function  domainDLSQueryParseRole($size=10,$param,$roles = 0,$type=0,$m_s,$boost=50,$prefix,$role)
    {
        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    $m_s=>[
                        [
                            'match'=>[
                                'cname'=>[
                                    'query'=>$param,
                                    'boost'=>$boost
                                ]
                            ]
                        ],
                        [
                            'term'=>[
                                'role'=>$role
                            ]
                        ],
                        [
                            'match_phrase_prefix'=>[
                                $prefix=>$param
                            ]
                        ]
                    ]
                ]

            ]
        ];
        return $curl_param;
    }



    /**
     * 查询 
     * @param  integer $size  [description]
     * @param  [type]  $param [description]
     * @param  integer $role  [description]
     * @param  integer $type  [description]
     * @param  [type]  $m_s   [must or should]
     * @param  integer $boost [description]
     * @return [type]         [description]
     */
    public static function  domainDLSQueryParse($size=10,$param,$role = 0,$type=0,$m_s,$boost=50,$prefix)
    {
        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    $m_s=>[
                        [
                            'match'=>[
                                'cname'=>[
                                    'query'=>$param,
                                    'boost'=>$boost
                                ]
                            ]
                        ],
                        [
                            'match_phrase_prefix'=>[
                                $prefix=>$param
                            ]
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'role'=>0
                        ]
                    ]
                ]

            ]
        ];
        if($type==1)
        {
            unset($curl_param['query']['bool']['must_not']);
            if(!empty($role))array_push($curl_param['query']['bool']['should'],['term'=>['role'=>$role]]);
        }else{
            if(!empty($role))
            {
                array_push($curl_param['query']['bool'][$m_s],['term'=>['role'=>$role]]);
            }
        }

        
        return $curl_param;
    }

    public static function  domainDLSQueryEn($size=10,$param,$role = 0,$type=0)
    {
        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        [
                            'match'=>[
                                'host'=>[
                                    'query'=>$param,
                                    'boost'=>10
                                ]
                            ]
                        ]
                    ],
                    'must_not'=>[
                        'term'=>[
                            'role'=>0
                        ]
                    ]
                ]

            ]
        ];
        if($type==1)
        {
            unset($curl_param['query']['bool']['must_not']);
            if(!empty($role))array_push($curl_param['query']['bool']['should'],['term'=>['role'=>$role]]);
        }
        return $curl_param;
    }

    /**
     * @param int $size
     * @param $param
     * @param int $role
     * @return array
     */
    public static function  domainDLSOrderQuery($size=10,$param,$role = 0)
    {
        $curl_param = [
            // 'sort'=>[
            //     'id'=>[
            //         'order'=>'desc'
            //     ]
            // ],
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'should'=>[
                        [
                            'match'=>[
                                'cname'=>[
                                    'query'=>$param,
                                    'boost'=>10
                                ]
                            ]
                        ],
                        [
                            'match_phrase_prefix'=>[
                                'host'=>$param
                            ]
                        ]
                    ]
                ]

            ]
        ];
        if(!empty($role))
        {
            if(!empty($role))array_push($curl_param['query']['bool']['should'],['term'=>['role'=>$role]]);
        }
        return $curl_param;
    }

    public static function  domainDLSOrderQueryEN($size=10,$param,$role = 0)
    {
        $curl_param = [
            'size'=>$size,
            'query'=>[
                'bool'=>[
                    'must'=>[
                        
                        [
                            'term'=>[
                                'host_no'=>$param
                            ]
                        ]
                    ]
                ]

            ]
        ];
        if(!empty($role))
        {
            if(!empty($role))array_push($curl_param['query']['bool']['must'],['term'=>['role'=>$role]]);
        }
        return $curl_param;
    }
}