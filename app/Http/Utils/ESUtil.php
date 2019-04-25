<?php

namespace App\Http\Utils;

use Illuminate\Http\Request;

use Search;
use Elasticsearch\Client;

/**
* fyj ES 工具类
* 20160624
*/
class ESUtil
{
         



    public static function filterESParam4($param,$host,$oldparam)
    {
        //纯 英文
        if(DBUtil::check_str_cn_en($oldparam)==1)
        {
            return [
                [
                    'filter'=>[
                        'match'=>[
                            'advertiser_name_title'=>[
                                'query'=>$param,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                    "weight"=>'4'
                ],
                [
                    'filter'=>[
                        'match'=>[
                            'advertiser_name'=>empty($host)?$param:$host
                        ]
                    ],
                    "weight"=>'10'
                ],
                [
                    'filter'=>[
                        'match'=>[
                            'title'=>[
                                'query'=>$param,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                    "weight"=>'10'
                ]
                // ,
                // [
                //     'script_score'=>[
                //         'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                //     ]
                // ]
            ];
        }else{
            return [
                [
                    'filter'=>[
                        'match'=>[
                            'advertiser_name_title'=>[
                                'query'=>empty($host)?$param:$host,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                    "weight"=>'4'
                ],
                [
                    'filter'=>[
                        'match'=>[
                            'advertiser_name'=>empty($host)?$param:$host
                        ]
                    ],
                    "weight"=>'10'
                ],

                [
                    'filter'=>[
                        'match'=>[
                            'title'=>[
                                'query'=>$oldparam,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                    "weight"=>'10'
                ]
                // ,
                // [
                //     'script_score'=>[
                //         'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                //     ]
                // ]
            ];
        }

    }
	/**
	 * 判断 es 1.47 是否 关掉
	 * @return [type] [description]
	 */
	public static function getEsStatus()
	{
		$rs = HttpUtil::send_orc_curl_get(config('param.ES_STATUS_47'));

        $rs_status = json_decode($rs,true);

        // print($rs_status);exit;

        return empty($rs_status['status'])?false:($rs_status['status']=='red'?false:true);
	}
	/**
	 * 过滤 条件 使用
	 * finn 
	 * 2016-11-30
	 * 
	 * @param  [type] $param [description]
	 * @return [type]        [description]
	 */
	public static function filterESParam3($param,$host,$oldparam)
	{
		//纯 英文
		if(DBUtil::check_str_cn_en($oldparam)==1)
		{
			return [
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name_title'=>[
                            'query'=>$param,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                "weight"=>'4'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'title'=>[
                            // 'query'=>$oldparam,
                            'query'=>$param,
                             'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'publisher_full'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'trackers'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'tags'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'script_score'=>[
                    'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                ]
            ]
        ];
    }else{

    	// print_r($host);
    	// dd($param);
    	return [
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name_title'=>[
                            'query'=>empty($host)?$param:$host,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                "weight"=>'4'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'publisher_full'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'title'=>[
                            'query'=>$oldparam,
                            // 'query'=>$param,
                             'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'script_score'=>[
                    'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                ]
            ]
        ];
    }
		
	}

	/**
	 * 根据 domain 查询 对应的关系
	 * @param  [type] $param    [description]
	 * @param  [type] $host     [description]
	 * @param  [type] $oldparam [description]
	 * @return [type]           [description]
	 */
	public static function filterESParamDomain($param,$host,$oldparam)
	{
		return [
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name_title'=>[
                            'query'=>$param,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                "weight"=>'4'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'title'=>[
                            // 'query'=>$oldparam,
                            'query'=>$param,
                             'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'2'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'trackers'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'publisher'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'script_score'=>[
                    'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                ]
            ]
        ];
	}


	/**
	 * 过滤 条件 使用
	 * finn 
	 * 2016-11-30
	 * 
	 * @param  [type] $param [description]
	 * @return [type]        [description]
	 */
	public static function filterESParam($param,$host)
	{
		return [
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name_title'=>[
                            'query'=>$param,
                                'minimum_should_match'=>'100%'
                            ]
                        ]
                    ],
                "weight"=>'4'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'advertiser_name'=>empty($host)?$param:$host
                    ]
                ],
                "weight"=>'10'
            ],
            [
                'filter'=>[
                    'match'=>[
                        'title'=>[
                            'query'=>$param,
                             'minimum_should_match'=>'100%'
                        ]
                    ]
                ],
                "weight"=>'2'
            ],
            [
                'script_score'=>[
                    'script'=>"width=doc['width'].value;height=doc['height'].value;if(width==height){return -1};return 0"
                ]
            ]
        ];
	}

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

		$filed = 'md5';
		if(!DBUtil::is_md5($md5))$filed = 'host_no';

		$curl_param = [
			'query'=>[
				'bool'=>[
					'must'=>[
						'term'=>[
							$filed=>$md5
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

		$filed = 'md5';
		if(!DBUtil::is_md5($md5))$filed = 'host_no';

		$curl_param = [
			'query'=>[
				'term'=>[
					$filed=>$md5
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

	



	// ==========================adbug api/v1 暂无使用===========================================


	/**
	* index 索引  
	* filed 搜索字段  param 收索内容 
	* range 反问  gt 大于 小雨 或 等于 range_filed 待比较字段
	*/
	public static function  SearchRange($index,$filed,$param,$range_filed,$range_param,$gt)
	{
		return Search::index($index)->search($filed,$param)->where($range_filed,$range_param,$gt)->get();

	}

	public static function SearchLimit($index,$filed,$param,$limit)
	{
		$search = Search::index($index)->search($filed,$param);
		if($limit)
		{
			$search = $search->limit($limit);
		}
		return $search->get();
	}

	public static function search_domains($role, $role_value, $type, $q, $limit = 20){

		
	    $map['filter'] = $role;
	    $map['filter_value'] = $role_value;

	    $map['mode'] = 1;

	    $map['groupby'] = $type;
	    $map['limit']   = $limit;
	    $map['groupbysort'] = $type.' ASC';

	    Search::index('domain')->search('host')->where('ispublisher',1)

	    ->limit($limit)
	    ->get();

	    $result = search('@'.$type.'_name '.$q, "domains", $map);

	    // print_r($result);
	    if(!empty($result['matches'])){

	        $data = array();
	        foreach ($result['matches'] as $key => $value) {
	            $data[] = array(
	                    'host'  => $value['attrs'][$type.'_host'],
	                    'name'  => $value['attrs'][$type.'_name'],
	                    'count' => $value['attrs']['@count']
	                );
	        }

	        import("ORG.Util.Page");

	        $page = new Page($result['total_found'], $limit);

	        return array(
	                'data'          => $data,
	                'total'         => $result['total_found'],
	                'total_pages'   => $page->totalPages,
	                'current_page'  => $page->nowPage
	            );
	    }


	}
	/**
	 * 根据 host 获取 domain　信息
	 * 20160705
	 * 暂勿使用
	 * @return [type] [description]
	 */
	public static function getHostInfo($index,$host)
	{
		return Search::index($index)->where('host',$host)->select(config('param.DOMAIN_FILED_ARR'))->get();
	}

	
	/**
	 * 根据 平台 获取 最新 广告信息
	 * @param  [type] $param [description]
	 * @return [type]        [description]
	 */
	public static function getAdsByPlatform($param)
	{

		return Search::index('addata')->where('platform',$param)->get();
	}
	/**
	 * 返回特定字段的数据
	 * @param  [type] $index [索引]
	 * @param  [type] $field [查询字段]
	 * @param  [type] $value [搜索内容]
	 * @param  [type] $size  [条数]
	 * @return [type]        [description]
	 */
	public static function getSelectFieldInfo($index,$field,$value,$size)
	{
		return Search::index($index)->search($field,$value)->limit($size)->get();
	}
	public static function getFieldInfoCount($index,$field,$value)
	{
		return Search::index($index)->where($field,$value)->get();

	}

	/**
	 * 分页 案例
	 * @param  [type] $from  [从第几个开始查询]
	 * @param  [type] $limit [每次显示数据数 偏移多少]
	 * @param  [type] $param [搜索内容]
	 */
	public static function es_page($from,$limit,$param)
	{

		$param = [
			'from'=>$from,
			'size'=>$limit,
			'query'=>[
				'match'=>[
					'title'=>$param
				]
			]
		];
		return $param;
	}

	/**
	 * group by 
	 * post 请求
	 * 20160704
	 */
	public static function es_order_by($index,$param,$limit)
	{

		//json
		//
		// 方式一
		//{
		//     "aggs" : {
		//         "genres" : {
		//             "terms" : { "field" : "advertiser" }
		//         }
		//     }
		// }
		// 方式二
		// 		{
		//     "aggs" : {
		//         "genres" : {
		//             "terms" : {
		// 	             "field" : "advertiser",
		// 	             "order" : { "_count" : "desc" }
		//             },
		//             "aggs" : {
		// 		        "status" : {
		// 		            "terms" : {
		// 		             "field" : "status"，
		// 		             "order" : { "_count" : "desc" }
		// 		            }
				            
		// 		        }
		// 		    }
		            
		//         }
		//     }
		// }

		// 方式三
		// 		{
		// 	"query":{
		// 		"bool":{
		// 			"must":{
		// 				"match":{
		// 					"title":"c"
		// 				}
		// 			}
		// 		}
		// 	},
		//     "aggs" : {
		//         "genres" : {
		//             "terms" : { "field" : "advertiser" }
		//         }
		//     }
		// }
		// 
		

	}

	/**
	 * @param  index 索引
	 * @param  param 排序 搜索内容
	 */
	public static function es_sort($param)
	{
		$client = new Client();
		return $client->search($param);
	}

	/**
	 * 获取 对应
	 */
	public static function es_query($index,$param,$limit)
	{
		$limit = empty($limit)?20:$limit;
		return Search::index($index)->search(config('esparam.query_param'),$param)
		->limit($limit)->get();
	}

	/**
	 * 查询所有内容
	 * @param  搜索内容
	 * 
	 */
	public static function es_query_all($index,$param)
	{
		$search = Search::index($index);

		return $search->search(null,$param)->get();
	}


	
		// support md5
	public static function es_get_host_id($host, $is_md5 = false){
	   	$es = Search::index('domain');

	    if($is_md5){
	        // $map['md5'] = $host;
	        $es = $es->search('md5',$host)->where('md5',$host);

	    }else{
	        // $map['host'] = $host;
	    	$es = $es->search('md5',$host)->where('host',$host);
	    }
	    return $es->get();
	}



	/**
	 * es 收索 数据
	 * @author [fyj]
	 * @param  [type] $sort    [排序]
	 * @param  [type] $limit   [获取条数]
	 * @param  [type] $orderBy []
	 * @param  [type] $role    [角色] 1 媒体 pushliser 2 广告主 advertisher 3 追踪者
	 * @param  [type] $param   [查询内容]
	 */
	public static function es_param_parse_index($sort,$limit,$orderBy,$role,$param)
	{
		$curl_param = config('esjson.between');

		$curl_param['sort'] = [
	            $orderBy=>[
	                'order'=>$sort
	            ]
	        ];

		$match_range[] =  [
	                        'match'=>[
	                            'role'=>$role
	                         ]
	                       ];
		if(is_array($param))
		{

		}else{
			$match_range[] = [
	                        'match'=>[
	                                'host'=>$param
	                                 ]
	                        ];
		}

        $curl_param['size'] = empty($limit)?20:$limit;
        
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


	

	/**
	 * 最新广告 移动广告 等数据 格式转换
	 */
	public static function es_param_parse_string($limit,$orderBy,$range)
	{
		$curl_param = config('esjson.recentads');

		$curl_param['sort'] = [
	            $orderBy=>[
	                'order'=>'desc'
	            ]
	        ];

        $curl_param['size'] = empty($limit)?100:$limit;
        
        if($range){
        	// 将 stdclass 换成 array
        	$curl_param['query']['bool'] = (array)$curl_param['query']['bool'];
        	$curl_param['query']['bool']['must'][] = $range;
        }
        

        return $curl_param;
	}







	/**
	 * 
	 * @return [type] [description]
	 */
	public static function es_query_filter()
	{


		// 创建 索引 添加数据
		// 		PUT /website/blog/123
		// {
		//   "title": "My first blog entry",
		//   "text":  "Just trying this out...",
		//   "date":  "2014/01/01"
		// }


		// 	{
		//     "query" : {
		//         "filtered" : {
		//             "filter" : {
		//                 "range" : {
		//                     "id" : { "gt" : 9347 }
		//                 }
		//             },
		//             "query" : {
		//                 "match" : {
		//                     "host" : "c"
		//                 }
		//             }
		//         }
		//     }
		// }
		

		// 高亮 显示

		// 		{
		//     "query" : {
		//         "match_phrase" : {
		//             "about" : "rock climbing"
		//         }
		//     },
		//     "highlight": {
		//         "fields" : {
		//             "about" : {}
		//         }
		//     }
		// }
		// 
		

		// 聚合 查询 groupBY
		// 		{
		//   "aggs": {
		//     "all_interests": {
		//       "terms": { "field": "host" }
		//     }
		//   }
		// }
		
		// 聚合 查询 + match 条件 过滤
		// 		{
		//   "query": {
		//     "match": {
		//       "last_name": "smith"
		//     }
		//   },
		//   "aggs": {
		//     "all_interests": {
		//       "terms": {
		//         "field": "interests"
		//       }
		//     }
		//   }
		// }
		// 
		


		// 创建 索引
		// 		PUT /blogs
		// {
		//    "settings" : {
		//       "number_of_shards" : 3, 主分片
		//       "number_of_replicas" : 1 复制分片
		//    }
		// }
	}

	/**
	 * 字段 说明
	 * @return [type] [description]
	 */
	public static function es_query_desc()
	{
		// match_phrase  比配  短语  ： role coding  结果　是　role coding
		// match   全文收索 role　coding　　结果是包括所有　role , coding
		// highlight 高亮显示 字段
		// aggs  all_interests  聚合查询
		// 
		// 集群健康  　green yellow red GET /_cluster/health
		// 分片可以是主分片(primary shard)或者是复制分片(replica shard)。你索引中的每个文档属于一个单独的主分片，所以主分片的数量决定了索引最多能存储多少数据。
		// 理论上主分片能存储的数据大小是没有限制的，限制取决于你实际的使用情况。分片的最大容量完全取决于你的使用状况：硬件存储的大小、文档的大小和复杂度、如何索引和查询你的文档，以及你期望的响应时间。
		// 
		//复制分片只是主分片的一个副本，它可以防止硬件故障导致的数据丢失，同时可以提供读请求，比如搜索或者从别的shard取回文档。
	

		// 元数据
		//  _index 索引 类似 数据库
		// _type   文档   类型 表
		// _id     es 唯一标识 类型 编号
		// _source[查询 返回指定字段 字段之间使用，分割] 
		// http://localhost:9200/domain/domain_index/_search?_source=host,id
		// GET /website/blog/123?_source=title,text
		// 
		// 检查文档是否存在 
		// curl -i -XHEAD http://localhost:9200/website/blog/123
		// 
		// 新增
		// _create
		// PUT /website/blog/123?op_type=create  
		// PUT /website/blog/123/_create
		// 
		// 删除
		// DELETE /website/blog/123
		// 
		// 
		// 更新 
		// 
		// POST /website/blog/1/_update
		// {
		//    "doc" : {
		//       "tags" : [ "testing" ],
		//       "views": 0
		//    }
		// }

	}
}