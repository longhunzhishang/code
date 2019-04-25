<?php

namespace App\Http\Utils;

use Illuminate\Http\Request;

use Search;
use Elasticsearch\Client;

/**
* fyj ES 工具类
* 20160624
*/
class ESUtill4
{

	/**
	 *  查询指定 索引 内容
	 *  index  索引 例如 : domains/domain_index
	 *  id 编号
	 */
	public static function getInfoById($index,$id)
	{

		$curl_param = [
			'query'=>[
				'bool'=>[
					'must'=>[
						'term'=>[
							'id'=>$id
						]
					]
				]
			]
		];
		$rs = HttpUtil::parse_curl_search($index,$curl_param);


        $data = DBUtil::get_parse_es_data($rs);

        return $data['data'];

	}

	/**
	 *  查询指定 索引 内容
	 *  index  索引 例如 : domains/domain_index
	 *  id 编号
	 */
	public static function getInfoById2($index,$md5)
	{

		$curl_param = [
			'query'=>[
				'bool'=>[
					'must'=>[
						'term'=>[
							'md5'=>$md5
						]
					]
				]
			]
		];
		$rs = HttpUtil::parse_curl_search($index,$curl_param);


        $data = DBUtil::get_parse_es_data($rs);

        return $data['data'];

	}

	/**
	 *  根据 编号 获取  详情
	 * @return [type] [description]
	 */
	public static function getInfoByMd5($index,$md5)
	{

		$curl_param = [
			'query'=>[
				'term'=>[
					'md5'=>$md5
				]
			]
		];

		$rs = HttpUtil::parse_curl_search($index,$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        return $data['data'];

	}

	/**
	 * 根据 字段查询
	 * @param  [type] $index [索引]
	 * @param  [type] $field [字段]
	 * @param  [type] $value [字段值]
	 */
	public static function getFieldInfo($index,$field,$value,$size)
	{
		$curl_param = [
			'size'=>$size,
			'sort'=>[
				'id'=>[
					'order'=>'desc'
				]
			],
			'query'=>[
				'term'=>[
					$field=>$value
				]
			]
		];



		if($index=='hostmeda/hostmeda_index')unset($curl_param['sort']);

		$rs = HttpUtil::parse_curl_search($index,$curl_param);

        $data = DBUtil::get_parse_es_data($rs);


        return $data['data'];
		// return Search::index($index)->where($field,$value)->limit($size)->get();
	}
	
	
	/**
	 * fyj
	 * 20160629
	 * @param  [type] $limit   [限制]
	 * @param  [type] $orderBy [排序]
	 * @param  [type] $param   [数组]
	 */
	public static function es_param_parse_search($limit,$orderBy,$param)
	{
		$curl_param = config('esjson.between');


		$curl_param['sort'] = [
	            $orderBy=>[
	                'order'=>'desc'
	            ]
	        ];

        $match_range=[];

        if($param)
        {
        	foreach ($param as $key => $value) {
	        	$match_range[] = ['match'=>[
	        		$key=>$value
	        	]];
	        }
        }
       

        $curl_param['size'] = empty($limit)?100:$limit;
        

        foreach ($curl_param['query']['bool']['must'] as $key => $value) {
        
            foreach ($value as $k => $v) {

                if($k=='range')
                {
                    unset($curl_param['query']['bool']['must'][$key]);
                }
                if($k=='match')
                {
                   
                    $curl_param['query']['bool']['must'][$key] = $match_range;
                }
            }
        }

        return $curl_param;

	}


	public static function getAcountByField($index,$field,$value)
    {
        $curl_param = [
            'query'=>[
                'term'=>[
                    $field=>$value
                ]
            ]
        ];
        $rs = HttpUtil::parse_curl_search($index,$curl_param);

        $data = DBUtil::get_parse_es_data($rs);

        return $data['data'];
    }


	
}