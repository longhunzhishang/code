<?php
namespace App\Http\Utils;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis as Redis;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use Bcrypt;
use DB;
/**
*  数据 操作类
*  fyj
*  20160705
*/
class DBUtil
{
    
    public static function get_es_data($es_data)
    {
        if (empty($es_data)) return ['data' => [], 'total' => 0];
        $es_ads = json_decode($es_data);
        if (empty($es_ads->hits->hits)) return ['data' => [], 'total' => 0];
        $es_hits_data = $es_ads->hits->hits;
        $es_ads_data = [];
        if ($es_hits_data) {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }
        $data['data'] = $es_ads_data;
        $data['took'] = $es_ads->took;
        $data['total'] = $es_ads->hits->total;
        return $data;
    }
    
    /**
     * 2017-10-13
     * finn
     * 重新获取数据
     * 如果上一次搜索内容为0 再次发送http请求
     * @return [type] [description]
     */
    public static function resetGetData($curl_param,$index)
    {
        $curl_param['min_score'] = 0;

        $url = config('param.ES_URL').$index.'/'.config('param.ES_SEARCH');
        // if($type)$url = $url.'?_source='.config('param.ADDATA_FILED');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        $data = DBUtil::get_parse_es_data($rs);

        return $data;

    }
    /**
     * 时间戳处理
     * @param  [type] $time [description]
     * @return [type]       [description]
     */
    public static function timeToDate($time)
    {
        return empty($time)?0:date('Y-m-d H:i:s',$time/1000);
    }
     /**
     * 监测 是否 含有 flag 标识
     * flag 0 顶级域名  1 二级以上域名 跟踪者
     * 2017-6-2 
     * @return [type] [description]
     */
    public static function getFlagById($id)
    {
        if(empty($id) || !is_numeric($id))return HttpUtil::retrun_json(HttpUtil::returnStatus(203),200);
        return ESUtil::getInfoById(config('param.DOMAIN_SEARCH'),$id);
    }

    /**
     * 过滤 特殊制度
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    public static function strFilter($str){
        $str = str_replace('`', '', $str);
        $str = str_replace('·', '', $str);
        $str = str_replace('~', '', $str);
        $str = str_replace('!', '', $str);
        $str = str_replace('！', '', $str);
        $str = str_replace('@', '', $str);
        $str = str_replace('#', '', $str);
        $str = str_replace('$', '', $str);
        $str = str_replace('￥', '', $str);
        $str = str_replace('%', '', $str);
        $str = str_replace('^', '', $str);
        $str = str_replace('……', '', $str);
        $str = str_replace('&', '', $str);
        $str = str_replace('*', '', $str);
        $str = str_replace('(', '', $str);
        $str = str_replace(')', '', $str);
        $str = str_replace('（', '', $str);
        $str = str_replace('）', '', $str);
        $str = str_replace('-', '', $str);
        $str = str_replace('_', '', $str);
        $str = str_replace('——', '', $str);
        $str = str_replace('+', '', $str);
        $str = str_replace('=', '', $str);
        $str = str_replace('|', '', $str);
        $str = str_replace('\\', '', $str);
        $str = str_replace('[', '', $str);
        $str = str_replace(']', '', $str);
        $str = str_replace('【', '', $str);
        $str = str_replace('】', '', $str);
        $str = str_replace('{', '', $str);
        $str = str_replace('}', '', $str);
        $str = str_replace(';', '', $str);
        $str = str_replace('；', '', $str);
        $str = str_replace(':', '', $str);
        $str = str_replace('：', '', $str);
        $str = str_replace('\'', '', $str);
        $str = str_replace('"', '', $str);
        $str = str_replace('“', '', $str);
        $str = str_replace('”', '', $str);
        $str = str_replace(',', '', $str);
        $str = str_replace('，', '', $str);
        $str = str_replace('<', '', $str);
        $str = str_replace('>', '', $str);
        $str = str_replace('《', '', $str);
        $str = str_replace('》', '', $str);
        $str = str_replace('.', '', $str);
        $str = str_replace('。', '', $str);
        $str = str_replace('/', '', $str);
        $str = str_replace('、', '', $str);
        $str = str_replace('?', '', $str);
        $str = str_replace('？', '', $str);
        return trim($str);
    }


    /**
     * 删除 数组 多余的值
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function removeLimit($rm_limit,$rm_data)
    {
        foreach ($rm_data as $key => $value) {
            if($key<$rm_limit)
            {
                unset($rm_data[$key]);
            }else{
                break;
            }
        }

        return $rm_data;
    }
    /**
     * 一天 时间 = 86400000 毫秒
     * @return [type] [description]
     */
    public static function explodeTime($start_time,$end_time)
    {
        $day_s = 86400000;// 86400000

        // echo date('Y-m-d H:i:s',$start_time/1000);
        // echo '<br/>';

        // echo date('Y-m-d H:i:s',$end_time/1000);
        // echo '<br/>';


        // 开始 结束 相差 天数
        $cha = ($end_time-$start_time)/$day_s;

        $cha_day = intval($cha/10);


        $cha_day_s = $cha_day * $day_s;


        $date_range = [];

        $mid_time = '';

        $mid_start = '';

        for ($i=1; $i<11 ; $i++) { 
            
        
            if($i==1)
            {
                $item['start_time'] = $start_time;
                $item['end_time'] =   $start_time + $i * $cha_day_s;

                $mid_start = $start_time + $i * $cha_day_s;

                $date_range[] = $item;

            }else{
                
                if($mid_start<$end_time)
                {
                    $item['start_time'] = $mid_start;


                    $mid_time = $mid_start + $i * $cha_day_s;

                    if($mid_time>=$end_time)
                    {
                        $mid_time = $end_time;

                        $item['end_time'] = $end_time;
                        $date_range[] = $item;
                    }else{
                        $mid_time = $mid_time;

                        $item['end_time'] = $end_time;
                        $date_range[] = $item;
                    }
                }
            }

            // $mid_time =  $mid_time + $i * $cha_day_s;
        }

        $date_range_tmp = [];

        foreach ($date_range as $key => $value) {
            $md5 = md5($value['start_time'].$value['end_time']);

            if(in_array($md5, $date_range_tmp))
            {
                unset($date_range[$key]);
            }else{
                $date_range_tmp[] = $md5;
            }
        }
        // foreach ($date_range as $key => $value) {
        //     echo date('Y-m-d H:i:s',$value['start_time']/1000);
        //     echo '<br/>';

        //     echo date('Y-m-d H:i:s',$value['end_time']/1000);
        //     echo '<br/>';

        // }
        return $date_range;
    }

    /**
     * 判断是否是md5 内容
     * @param  [type]  $param [description]
     * @return boolean        [description]
     */
    public static function is_md5($param) {
        return preg_match("/^[a-z0-9]{32}$/", $param);
    }

    /**
     * 重新获取 广告 宽高
     * @return [type] [description]
     */
    public static function getBodyWH($data)
    {
        if(is_array($data))
        {
            foreach ($data as $key => &$value) {
                
                $addta = DB::table('addata_2018')->where('id',$value->id)->first();
                if(!empty($addta->attribute07))
                {
                    $add_json = json_decode($addta->attribute07,true);

                    if(!empty($add_json['page_meta']))
                    {
                        $value->body_width = empty($add_json['page_meta']['bodyW'])?'':$add_json['page_meta']['bodyW'];
                        $value->body_height = empty($add_json['page_meta']['bodyH'])?'':$add_json['page_meta']['bodyH'];
                    }
                    
                }

                $value->attr_url = $value->attribute08;
                $value->attribute07 = empty($addta->attribute07)?'':$addta->attribute07;
                
            }
        }
        return $data;
        
    }

    
    
    /**
     * 获取 最近 7天数据
     * @return [type] [description]
     */
    public static function getOneWeekNew()
    {
        $data['start_time'] = date('Y-m-d', strtotime('-7 days'));

        $data['end_time'] = date('Y-m-d', time());

        return $data;
    }


    /**
   * 根据指定日期获取所在周的起始时间和结束时间
   */
  public static function get_weekinfo_by_date($date) {
    $idx = strftime("%u", strtotime($date));
    $mon_idx = $idx - 1;
    $sun_idx = $idx - 7;
    return array(
      'week_start_day' => strftime('%Y-%m-%d', strtotime($date) - $mon_idx * 86400),
      'week_end_day' => strftime('%Y-%m-%d', strtotime($date) - $sun_idx * 86400),
      );
  }


   /**
   * 根据指定日期获取所在月的起始时间和结束时间
   */
  public static function get_monthinfo_by_date($date){
    $ret = array();
    $timestamp = strtotime($date);
    $mdays = date('t', $timestamp);
    return array(
      'month_start_day' => date('Y-m-1', $timestamp),
      'month_end_day' => date('Y-m-'.$mdays, $timestamp)
      );
  }


  /**
   * 获取指定日期之间的各个周
   */
  public static function get_weeks($sdate, $edate) {
    $range_arr = array();
    // 检查日期有效性
    self::check_date(array($sdate, $edate));
    // 计算各个周的起始时间
    do {
      $weekinfo = self::get_weekinfo_by_date($sdate);
      $end_day = $weekinfo['week_end_day'];
      $start = self::substr_date($weekinfo['week_start_day']);
      $end = self::substr_date($weekinfo['week_end_day']);

      $range['start_time'] = $start;
      $range['end_time'] = $end;


      $range_arr[] = $range;
       $sdate = date('Y-m-d', strtotime($sdate)+7*86400);
    }while($end_day < $edate);
    return $range_arr;
  }
  /**
   * 获取两个时间间 天数
   * @param  [type] $day1 [description]
   * @param  [type] $day2 [description]
   * @return [type]       [description]
   */
  public static function diffBetweenTwoDays($day1,$day2)
  {
     $second1 = strtotime($day1);
     $second2 = strtotime($day2);

     if($second1 < $second2)
     {
        $tmp = $second2;
        $second2 = $second1;
        $second1 = $tmp;
     }
     $day_diff = ($second1-$second2)/86400;

     $date_range[]=[
        'start_time'=>$day1,
        'end_time'=>$day1
     ];

     // dd($day_diff);

     for ($i=1; $i <= $day_diff; $i++) { 
        $day = date('Y-m-d',strtotime($day1.' + '.$i.' day'));
        $item['start_time'] = $day;
        $item['end_time'] = $day;
        $date_range[] = $item;
     }
     return $date_range;
  }
  /**
   * 截取日期中的月份和日
   * @param string $date
   * @return string $date
   */
  public static function substr_date($date) {
    if ( ! $date) return FALSE;
    return date('Y-m-d', strtotime($date));
  }
  /**
  * 检查日期的有效性 YYYY-mm-dd
  * @param array $date_arr
  * @return boolean
  */
  public static function check_date($date_arr) {
    $invalid_date_arr = array();
    foreach ($date_arr as $row) {
      $timestamp = strtotime($row);
      $standard = date('Y-m-d', $timestamp);
      if ($standard != $row) $invalid_date_arr[] = $row;
    }
    if ( ! empty($invalid_date_arr)) {
      die("日期格式不对: ".print_r($invalid_date_arr, TRUE));
    }
  } 


  /**
  * 获取指定日期之间的各个月
  */
  public static function get_months($sdate, $edate) {
    $range_arr = array();
    do {
      $monthinfo = self::get_monthinfo_by_date($sdate);
      $end_day = $monthinfo['month_end_day'];
       $start = self::substr_date($monthinfo['month_start_day']);
      $end = self::substr_date($monthinfo['month_end_day']);
      $range['start_time'] = $start;
      $range['end_time'] = $end;
      $range_arr[] = $range;
       $sdate = date('Y-m-d', strtotime($sdate.'+1 month'));
    }while($end_day < $edate);
    return $range_arr;
  }



    /**
     * 获取 本月 开始时间 结束 时间
     * @return [type] [description]
     */
    public static function getNowMoth()
    {
        $data['start_time'] = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), 1, date("Y")));

        $data['end_time'] = date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("t"), date("Y")));

        return $data;
    }

    public static function getMidYear()
    {
        $now= time();
        $time = strtotime('-5 month', time());  
        $data['start_time'] = date('Y-m-d 00:00:00', mktime(0, 0,0, date('m', $time), 1, date('Y', $time)));  
        $data['end_time'] = date('Y-m-d 23:39:59', mktime(0, 0, 0, date('m', $now), date('t', $now), date('Y', $now)));
        return $data;
    }


    public static function getFullYear()
    {
        $now= time();

        $data['start_time'] = date('Y-m-d 00:00:00', mktime(0, 0,0, 1, 1, date('Y', $now)));  
        $data['end_time'] = date('Y-m-d 23:39:59', mktime(0, 0, 0, 12, 31, date('Y', $now)));  
        return $data;
    }

    /**
     * 获取 本月 开始时间 结束 时间
     * @return [type] [description]
     */
    public static function getNowMothNew()
    {
        $data['start_time'] = date("Y-m-d", strtotime("-1 month"));

        $data['end_time'] = date("Y-m-d", time());

        return $data;
    }

    public static function getOneYearNew()
    {
        $data['start_time'] = date("Y-m-d", strtotime("-1 year")); 
        $data['end_time'] =  date('Y-m-d', time());  
        return $data;
    }

    /**
     * 获取 两个时间相差 年月日
     * @param  [type] $date1 [description]
     * @param  [type] $date2 [description]
     * @return [type]        [description]
     */
    public static function diffDate($date1,$date2){  
        if(strtotime($date1)>strtotime($date2)){  
            $tmp=$date2;  
            $date2=$date1;  
            $date1=$tmp;  
        }  
        list($Y1,$m1,$d1)=explode('-',$date1);  
        list($Y2,$m2,$d2)=explode('-',$date2);  
        $Y=$Y2-$Y1;  
        $m=$m2-$m1;  
        $d=$d2-$d1;  
        if($d<0){  
            $d+=(int)date('t',strtotime("-1 month $date2"));  
            $m--;  
        }  
        if($m<0){  
            $m+=12;  
            $Y--;  
        }  
        return array('year'=>$Y,'month'=>$m,'day'=>$d);  
    }  
    /**
     * 获取两个时间段 月份
     * @return [type] [description]
     */
    public static function getMonthNum($date1_stamp,$date2_stamp)
    {
        list($date_1['y'],$date_1['m'])=explode("-",date('Y-m',$date1_stamp));
        list($date_2['y'],$date_2['m'])=explode("-",date('Y-m',$date2_stamp));

        return abs(($date_2['y']-$date_1['y'])*12 +$date_2['m']-$date_1['m']);    
    }
    /**
     * 判断 redis 里面 是否有数据
     * $key  redis key
     * 是 直接返回
     */
    public  static function isHasRedisData($key)
    {
        // return false;
        try {
            //$redis = Redis::connection('read');
            if(Redis::get($key)) return true;
        } catch (\Exception $e) {

            // dd('false');
            return false;
        }
        return false;
    }
    /**
     * 
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function getRedisData($key)
    {
        // return false;

        try {
           // $redis = Redis::connection('read');
            $data = json_decode(Redis::get($key),true);
            return !empty($data['data'])?$data:[];
        } catch (\Exception $e) {
            return [];
        }
        return [];
    }
    /**
     * @param $key redis key
     * @param $data 缓存数据
     * @param $time 缓存时间
     */
    public  static function  setRedisData($key,$data,$time)
    {
        try{
            // $redis = Redis::connection('default');
            Redis::set($key,json_encode($data));
            Redis::expire($key,$time);
        }catch (\Exception $e){
            // dd($e);
        }
    }
    /**
     * 判断是否 邨英文 1 中文 2 中英混合 3
     * @param  string $str [description]
     * @return [type]      [description]
     */
    public static function check_str_cn_en($str='')
    {
        if(trim($str)=='')return '';
        $m = mb_strlen($str,'utf-8');
        $s = strlen($str);

        if($s==$m)return 1;
        if($s%$m==0 && $s%3==0)return 2;
        return 3;
    }

    /**
     * 品牌 搜索词 进行 缓存
     * $param 搜索词
     * @return [type] [description]
     */
    public static function brandCache($size,$sort,$param,$page,$from)
    {
        $key_k = $size.(empty($sort)?'':$sort).$param.(empty($page)?'':$page).(empty($from)?'':$from);
        $key_cache = md5($key_k);
        $cache_data['status'] = false;
        // 第一步 crul 获取 昨日 信息
        try{
            if (Redis::get($key_cache))
            {
                $cache_json = Redis::get($key_cache);
                $cache_data['cache'] = empty($cache_json)?[]:json_decode($cache_json,true);
                $cache_data['status'] = true;
                return $cache_data;
            }
            // 判断 昨天 搜索 品牌词
            // 缓存起来
            $data_id = date('Ymd',strtotime('-1 day'));
            $search_all = DB::connection('mysql_oc')->table(config('param.OC_SEARCH_TABLE'))->where('date_id',$data_id)->get();

            if($search_all)
            {
                $search_all_title = [];
                foreach ($search_all as $key => $value) {
                    $search_all_title[] = $value->title;
                }

                if(in_array($param, $search_all_title))
                {
                    $param_cache = DBUtil::brandSearch($size,$sort,$param,$page,$from);

                    Redis::set($key_cache,json_encode($param_cache));
                    Redis::expire($key_cache,3000);
                    // Cache::put($key_cache, json_encode($param_cache), $expiresAt);
                    $cache_data['cache'] = $param_cache;
                    $cache_data['status'] = true;
                    return $cache_data;
                }
            }
            // 第二步 过滤 获取 昨日品牌词
            $pinpai_tmp = file_get_contents('allB5.json');
            $pipa_arr = json_decode($pinpai_tmp,true);
            $pipa_arr_title = [];
            foreach ($pipa_arr as $key => $value) {
                // $pipa_arr_title[] = $value['name'];
                if($value['name'] == $param)
                {
                    $param_cache = DBUtil::brandSearch($size,$sort,$param,$page,$from);
                    // Cache::put($key_cache, json_encode($param_cache), $expiresAt);

                    Redis::set($key_cache,json_encode($param_cache));
                    Redis::expire($key_cache,3000);

                    $cache_data['cache'] = $param_cache;
                    $cache_data['status'] = true;
                    return $cache_data;

                }
            }
        }catch(\Exception $e)
        {
            return $cache_data;
        }
    }
    public static function brandSearch($size,$sort,$param,$page,$from)
    {
        if($page>1)$from = $size * $page;
        // 默认
        $curl_param = [
                'from'=>$from,
                'size'=>$size,
                'min_score'=>1,
                'query'=>[
                    'function_score'=>[
                        'filter'=>[
                            'bool'=>[
                                'should'=>[
                                    'multi_match'=>[
                                        'query'=>$param,
                                        'fields'=>[
                                            'title',
                                            'advertiser_name',
                                            'advertiser_name_title'
                                        ]
                                    ]
                                ],
                                'must'=>[]
                            ]
                        ],
                        'functions'=>[
                            
                        ],
                        'score_mode'=>'sum',
                        'max_boost'=>'10'
                    ],
                ],
                'sort'=>[
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
                ]
        ];
   
        $filter_range_es = ESUtil::filterESParam($param,'');
        foreach ($filter_range_es as $key => $value) {
            array_push($curl_param['query']['function_score']['functions'], $value);
        }
        $url = config('param.ES_URL').config('param.ADDATA_SEARCH').'/'.config('param.ES_SEARCH');

        $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");
        $data = DBUtil::get_parse_es_data($rs);

        $data = self::getadvertiserDomain($data);
        // 暂无数据 2002
        if(empty($data['data']))
        {
            $data['status'] = 203;
            $data['data'] = [];
            return $data;
        }

        $data['status'] = 200;

        return $data;
    }


     public static function getadvertiserDomain($data)
    {

        $domain_tmp = [];
        foreach ($data['data'] as $key => $value) {
            
            if(!empty($value->advertiser) && !in_array($value->advertiser, $domain_tmp))
            {
                $domain_tmp[] = $value->advertiser;
            }
        }

        $domain_arr = [];
        foreach ($domain_tmp as $key => $value) {
            $curl_param = [
                'query'=>[
                    'term'=>[
                        'host'=>$value
                    ]
                ]
            ];
            $url = config('param.ES_URL').config('param.DOMAIN_SEARCH').'/'.config('param.ES_SEARCH');
            $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");


            $data_d = DBUtil::get_parse_es_data3($rs);

            if(!empty($data_d['data']))
            {
                $domain_arr[] = (array)$data_d['data'][0];
            }

        }

        $return_data = [];

        foreach ($data['data'] as $key => $value) {
            $return_data[] = (array)$value;
        }


        foreach ($return_data as $key => &$value) {

           if(!empty($value['advertiser']))
           {
                foreach ($domain_arr as $k => $v) {
                   if($v['host']==$value['advertiser'])
                   {
                        $value['advertiser'] = $v;
                   }
               }
           }
           
        }
        $data['data'] = $return_data;

        return $data;
    }

     // $edition = [];
     //    foreach ($new_data as $k => $v) {

     //      $edition[] = $v['date5'];

     //    }
     //    array_multisort($edition, SORT_DESC, $new_data);
    /**
     * 按照 时间 desc 排序
     * data es 返回数据 
     */
    public static function chinaSort($data)
    {
        $rs = $data['data'];
        foreach ($rs as $key => &$value) {
            $value = (array)$value;
        }
        $flag = [];
        foreach ($rs as $key => $value) {
            $flag[] = $value['created_date'];
        }
        array_multisort($rs,SORT_DESC,$flag);
        return $rs;
    }

    /**
     * 对特殊字段进行排序
     * @param  [type] $data  [description]
     * @param  [type] $field [description]
     * @return [type]        [description]
     */
    public static function sortLastTime($rs,$field)
    {
        foreach ($rs as $key => &$value) {
            $value = (array)$value;
        }
        $flag = [];
        foreach ($rs as $key => $value) {
            $flag[] = $value[$field];
        }
        array_multisort($rs,SORT_DESC,$flag);
        return $rs;
    }

    public static  function my_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){   
        $key_arrays = [];
        if(is_array($arrays)){   
            foreach ($arrays as $array){   
                if(is_array($array)){   
                    $key_arrays[] = $array[$sort_key];   
                }else{   
                    return false;   
                }   
            }   
        }else{   
            return false;   
        }  
        array_multisort($key_arrays,$sort_order,$sort_type,$arrays);   
        return $arrays;   
    }  
    /**
     * 同步 mysql es 数据
     * @return [type] [description]
     */
    public static function updateEsMysql($index)
    {
        $curl_param = [
            'aggs'=>[
                'max_id'=>[
                    'max'=>[
                        'field'=>'id'
                    ]
                ]
            ]
        ];
        $url = config('param.ES_URL').$index.'/'.config('param.ES_SEARCH').'?_source=id';
        $data['url'] = $url;
        $data['curl_param'] = $curl_param;
        return $data;
    }
    /**
     * 判断 参数 是否包好 中文
     * type 1 参数 处理 2 返回数据处理
     * @return boolean [description]
     */
    public static function isChOrEn($param,$data,$type)
    {
        if(preg_match_all('/[\x7f-\xff]/', $param) && $type==1)unset($data['sort']);

        if(preg_match_all('/[\x7f-\xff]/', $param) && $type==2)rsort($data['data']);
        return $data;
    }
    public static function addRedis($wd)
    {
//        $redis = new Redis();
//        $redis->connect('127.0.0.1',6379);
//        $key = strtotime(date('YmdHis',time()));
//        Redis::set($key,$wd);
//
//        $now_time = strtotime(date('YmdHis',time()-8400));
//
//        $all_keys = Redis::KEYS('*');
//
//        $tem_da = [];
//        $tmp_all_rs = [];
//        foreach ($all_keys as $key => $value) {
//
//            $re = $redis->SMEMBERS($value);
//            // $tem_da[] = $re[0];
//            if($value>=$now_time && $re[0])
//            {
//                $tmp_all_rs[] = $re[0];
//            }
//        }
//
//        $all_rs = [];
//        $tmp = [];
//        foreach ($tmp_all_rs as $key => $value) {
//
//            if(empty($tmp[$value]))
//            {
//                $tmp[$value] = 1;
//            }else{
//                $tmp[$value]+=1;
//            }
//        }
//
//        $max = array_search(max($tmp), $tmp);
//        $max_rs = $tmp[$max];
//
//        $tmp_count = count($tmp);
//
//        arsort($tmp);
//        $tmp_count= count($tmp);
//        $data = [];
//
//        if(!empty($tmp))
//        {
//            foreach ($tmp as $key => $value) {
//                if(in_array($key, config('param.FILTER_WDS')))unset($tmp[$key]);
//            }
//        }
//
//        if($tmp_count > 10)
//        {
//            foreach ($tmp as $key => $value) {
//                # code..
//                if(count($data) < 10 && strlen($key) < 15)
//                {
//                    $data[] = $key;
//                }
//
//            }
//        }else{
//            foreach ($tmp as $key => $value) {
//                if(strlen($key) < 15)$data[] = $key;
//            }
//        }
//        return $data;
//
        // $rs = $redis->zAdd('queryinfo',strtotime(date('YmdHis',time())),$wd);
    }

    public static function findRedis()
    {
//        $redis = new Redis();
//        $redis->connect('127.0.0.1',6379);
//
//        $now_time = strtotime(date('YmdHis',time()-8400));
//
//        $all_keys = $redis->KEYS('*');
//
//        $tem_da = [];
//        $tmp_all_rs = [];
//        foreach ($all_keys as $key => $value) {
//
//            $re = $redis->SMEMBERS($value);
//            // $tem_da[] = $re[0];
//            if($value>=$now_time && $re[0])
//            {
//                $tmp_all_rs[] = $re[0];
//            }
//        }
//
//        $all_rs = [];
//        $tmp = [];
//        foreach ($tmp_all_rs as $key => $value) {
//
//            if(empty($tmp[$value]))
//            {
//                $tmp[$value] = 1;
//            }else{
//                $tmp[$value]+=1;
//            }
//        }
//
//        $max = array_search(max($tmp), $tmp);
//        $max_rs = $tmp[$max];
//
//        $tmp_count = count($tmp);
//
//        arsort($tmp);
//        $tmp_count= count($tmp);
//        $data = [];
//
//        if(!empty($tmp))
//        {
//            foreach ($tmp as $key => $value) {
//                if(in_array($key, config('param.FILTER_WDS')))unset($tmp[$key]);
//            }
//        }
//
//        if($tmp_count > 10)
//        {
//            foreach ($tmp as $key => $value) {
//                # code..
//                if(count($data) < 10 && strlen($key) < 15)
//                {
//                    $data[] = $key;
//                }
//
//            }
//        }else{
//            foreach ($tmp as $key => $value) {
//                if(strlen($key) < 15)$data[] = $key;
//            }
//        }
//        return $data;
    }

    /**
     * 处理 使用 search 返回的数据
     * type 1 就一个数据
     * @return [type] [description]
     */
    public static function pareseESDate($data,$type)
    {
        $rs = [];
        if(empty($data))
        {
            return ['data'=>[],'status'=>203];
        }

        if($type==1)
        {
            $rs['data'] = $data[0];
        }else{
            foreach ($data as $key => $value) {
                # code...
                $rs[] = $value;
            }
            $rs['data'] = $rs;
        }
        
        $rs['status'] = 200;

        return $rs;
    }


    public function get_pare_es($es_data)
    {
        if(empty($es_data))return ['data'=>[],'total'=>0];
        $es_ads = json_decode($es_data);

        if(empty($es_ads->hits->hits))return ['data'=>[],'total'=>0];

        $es_hits_data = $es_ads->hits->hits;
        // dd($es_ads_data);
        $es_ads_data = [];
        if($es_hits_data)
        {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }
        foreach ($es_ads_data as $key => &$value) {
            $value->decode_id = base64_encode($value->id);
            if(!empty($value->advertiser))$value->decode_advertiser = base64_encode($value->advertiser);
            if(!empty($value->publisher))$value->decode_publisher = base64_encode($value->publisher);
        }
        $data['data'] = $es_ads_data;
        $data['total'] =  $es_ads->hits->total;
        return $data;
    }

    /**
     * 对 es  数据 进行处理
     * @return [type] [description]
     */
    public static function get_parse_es_data($es_data)
    {
        if(empty($es_data))return ['data'=>[],'total'=>0];
        $es_ads = json_decode($es_data);


        // dd($es_ads);

        if(empty($es_ads->hits->hits))return ['data'=>[],'total'=>0];

        $es_hits_data = $es_ads->hits->hits;
        // dd($es_ads_data);
        $es_ads_data = [];
        if($es_hits_data)
        {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }

        foreach ($es_ads_data as $key => &$value) {
            $value->decode_id = base64_encode(isset($value->id)?$value->id:"");
            if(!empty($value->advertiser))$value->decode_advertiser = base64_encode($value->advertiser);
            if(!empty($value->publisher))$value->decode_publisher = base64_encode($value->publisher);
            if(isset($value->attribute06) && $value->attribute06 == "ott")
            {
                $value->platform_type = 3;
            }
            else if(isset($value->platform)){
                $value->platform_type = $value->platform;
            }
        }
        $data['data'] = $es_ads_data;
        $data['took'] = $es_ads->took;
        $data['total'] =  $es_ads->hits->total;
        return $data;
    }

    /**
     * 域名解析
     * @return [type] [description]
     */
    public static function parseHost()
    {
        $data = explode('.', $url);
        $co_ta = count($data);
        //判断是否是双后缀
        $zi_tow = true;
        $host_cn = 'com.cn,net.cn,org.cn,gov.cn';
        $host_cn = explode(',', $host_cn);
        foreach($host_cn as $host){
            if(strpos($url,$host)){
                $zi_tow = false;
            }
        }
        //如果是返回FALSE ，如果不是返回true
        if($zi_tow == true){
            if(empty($data[$co_ta-2]))return '';
            $host = $data[$co_ta-2].'.'.$data[$co_ta-1];
        }else{
            if(empty($data[$co_ta-3]))return '';
            $host = $data[$co_ta-3].'.'.$data[$co_ta-2].'.'.$data[$co_ta-1];
        }
        return $host;
    }

    /**
     * 返回一条数据
     * @param  [type] $es_data [description]
     * @return [type]          [description]
     */
    public static function get_parse_es_data_one($es_data)
    {
        if(empty($es_data))return ['data'=>[],'total'=>0];
        $es_ads = json_decode($es_data);

        if(empty($es_ads->hits->hits))return ['data'=>[],'total'=>0];

        $es_hits_data = $es_ads->hits->hits;
        // dd($es_ads_data);
        $es_ads_data = [];
        if($es_hits_data)
        {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }
        return $es_ads_data;
    }
    /**
     * 对 es  数据 进行处理
     * fun
     * 只返回编号
     * @return [type] [description]
     */
    public static function get_parse_es_data_id($es_data)
    {
        if(empty($es_data))return ['data'=>[],'total'=>0];
        $es_ads = json_decode($es_data);

        if(empty($es_ads->hits->hits))return ['data'=>[],'total'=>0];

        $es_hits_data = $es_ads->hits->hits;

        $_scroll_id = $es_ads->_scroll_id;
        // dd($es_ads_data);
        $es_ads_data = [];
        if($es_hits_data)
        {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }
        $data['data'] = $es_ads_data;
        $data['took'] = $es_ads->took;
        $data['total'] =  $es_ads->hits->total;
        $data['scroll_id'] = $_scroll_id;
        return $data;
    }
    /**
     * 对 es  数据 进行处理
     * @return [type] [description]
     */
    public static function get_parse_es_data3($es_data)
    {
        if(empty($es_data))return ['data'=>[],'total'=>0];
        $es_ads = json_decode($es_data);

        if(empty($es_ads->hits->hits))return ['data'=>[],'total'=>0];

        $es_hits_data = $es_ads->hits->hits;
        
        // dd($es_ads_data);
        $es_ads_data = [];
        if($es_hits_data)
        {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }
        foreach ($es_ads_data as $key => &$value) {
            $value->decode_id = base64_encode($value->id);
            if(!empty($value->host))$value->decode_host = base64_encode($value->host);
        }

        $data['data'] = $es_ads_data;
        $data['total'] =  $es_ads->hits->total;
        return $data;
    }
    /**
     * 获取 数据
     * @param  [type] $es_data [description]
     * @return [type]          [description]
     */
    public static function get_base_parse_es_data($es_data)
    {
        if(empty($es_data))return ['data'=>[],'total'=>0];
        $es_ads = json_decode($es_data);

        if(empty($es_ads->hits->hits))return ['data'=>[],'total'=>0];

        $es_hits_data = $es_ads->hits->hits;
        // dd($es_ads_data);
        $es_ads_data = [];
        if($es_hits_data)
        {
            foreach ($es_hits_data as $key => $value) {
                $es_ads_data[] = $value->_source;
            }
        }
        $data['data'] = $es_ads_data;
        $data['took'] = $es_ads->took;
        $data['total'] =  $es_ads->hits->total;
        return $data;
    }

    public static function object_to_array($es_data)
    {
        $data = [];
        foreach ($es_data as $key => $value) {
            $data[$key] = $value;
        }
        return $data;
    }



    /**
     * 计算查询 时间
     * @return [type] [description]
     */
    public static function parseTime($year,$mouth,$mouth_true)
    {
        // $year = date('Y',time());
  //       $mouth = date("Y-m",time());
  //       $mouth_true = date('m',time());
        // 判断 瑞年 
        $is_runyear = 0;
        if(($year%4==0) && ($year%100!=0))
        {
            $is_runyear = 1;
        }
        $mouth_31 = ['01','03','05','07','08','10','12'];
        $mouth_30 = ['04','06','09','11'];

        if(in_array($mouth_true, $mouth_30))
        {
            $mouth = $mouth.'-30';
        }else if(in_array($mouth_true, $mouth_31))
        {
            $mouth = $mouth.'-31';
        }else{
            $mouth = empty($is_runyear)?$mouth.'-29':$mouth.'-28';
        }
        $week = date('w',time());
        $prre_date = '';
        $next_date = '';
        switch ($week) {
            case '1':
                $prre_date = date("Y-m-d",strtotime("-1 day"));
                $next_date = date("Y-m-d",strtotime("+6 day"));
                break;
            case '2':
                # code...
                $prre_date = date("Y-m-d",strtotime("-2 day"));
                $next_date = date("Y-m-d",strtotime("+5 day"));
                break;
            case '3':
                # code...
                $prre_date = date("Y-m-d",strtotime("-3 day"));
                $next_date = date("Y-m-d",strtotime("+4 day"));
                break;
            case '4':
                # code...
                $prre_date = date("Y-m-d",strtotime("-4 day"));
                $next_date = date("Y-m-d",strtotime("+3 day"));
                break;
            case '5':
                # code...
                $prre_date = date("Y-m-d",strtotime("-5 day"));
                $next_date = date("Y-m-d",strtotime("+2 day"));
                break;
            case '6':
                # code...
                $prre_date = date("Y-m-d",strtotime("-6 day"));
                $next_date = date("Y-m-d",strtotime("+2 day"));
                break;
            case '0':
                # code...
                $prre_date = date("Y-m-d",time());
                $next_date = date("Y-m-d",strtotime("+7 day"));
                break;
        }
        $data['prre_date'] = $prre_date;
        $data['next_date'] = $next_date;
        return $data;
    }
    public static function createDir($dir)
    {
        return is_dir($dir) or (self::createDir(dirname($dir)) and mkdir($dir, 0777));
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
    public static function sortValied($sort)
    {
        if(in_array($sort, ['DESC','desc','ASC','asc']))
        {
            return true;
        }
        return false;
    }

    /**
     * 验证 文件类型
     * type 1 图片 2 flush 3 视频 0 所有
     * @return [type] [description]
     */
    public static function typeValied($type)
    {
        if(!is_numeric($type) || !in_array($type, [0,1,2,3]))
        {
            return false;
        }
        return true;

    }

    /** 
     * 验证 显示数目
     * @return [type] [description]
     */
    public static function sizeValied($size)
    {
        if(!is_numeric($size) || $size>10000)
        {
            return false;
        }
        return true;
    }

    /**
     * 参数 格式 验证
     * 
     * @return [type] [description]
     */
    public static function valiedParam($param)
    {
        $flag = 1;
        foreach ($param as $key => $value) {
            if($value && !is_numeric($value))
            {
                $flag = 0;
                break;
            }
        }
        return $flag;
    }

    /**
     * 验证 size  sort
     * @param  [type] $param [description]
     * @return [type]        [description]
     */
    public static function valiedSizeSort($size,$sort)
    {
        if($size && !is_numeric($size))return false;
        if($sort && !in_array($sort, ['desc','asc','DESC','ASC']))return false;
        return true;
    }
}