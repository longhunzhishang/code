<?php

return [
    // 'ES_URL' => 'http://localhost:9200/',
    'ES_URL'=>env('ES_URL','http://192.168.1.58:9200/'),
    'ES_URL_SQL'=>'http://192.168.1.58:9200/_sql',
    'ES_URL_58'=>env('ES_URL','http://192.168.1.58:9200/'),
    'ES_URL_13'=>'http://192.168.1.13:9200/',  
    '_AUTH_TOKEN_'=>'9a1323ac97bbd7fb3d9c7a9570538d6f',//md5('adbug_168_178')
    '_AUTH_TOKEN_API_'=>'ee01b26cda93612da682b7b703a62217',//md5('adbugapi')
    '_AUTH_GATHER_TOKEN'=>'b04b86c026eb5a81f846b0f4af168b5b',//md5('data_adbug_gather')
    '_Authorization_'=>'elastic:Y5djQBVpHPWYH9tZ',
    '_AUTH_HEADER_'=>[
        "Content-type: text/xml;charset=\"utf-8\"", 
        "Accept: text/xml", 
        "Authorization: Basic " . base64_encode('elastic:Y5djQBVpHPWYH9tZ') 
    ],
    '_AUTH_SPIDER_'=>'adbug168178',
    '_START_TIME'=>' 00:00:00',
    '_END_TIME'=>' 23:59:59',
    '_TOKEN_ARR_'=>[
        '9a1323ac97bbd7fb3d9c7a9570538d6f',
        'ee01b26cda93612da682b7b703a62217',
        'b04b86c026eb5a81f846b0f4af168b5b'
    ],
    'ES_STATUS_47'=>env('ES_STATUS_47','http://192.168.1.58:9200/_cluster/health/'),
    'ES_STATUS_58'=>env('ES_STATUS_58','http://192.168.1.58:9200/_cluster/health/'),
    'ES_STATUS_DEBUG'=>'http://127.0.0.1:9200/_cluster/health/',
    'SH_AREA'=>[
        '全部','静安','黄浦','徐汇','长宁','普陀',
        '虹口','闵行','杨浦','宝山','浦东'
    ],
    // 'ES_URL'=>'http://121.196.1.30:9200/',
    // 'ES_URL'=>'http://120.27.234.111:9400',
    'ES_URL_online'=>'http://120.27.234.111:9400',
    'ES_HOST_IN'=>[
        '192.168.1.30','192.168.1.53','192.168.1.29'
    ],
    'WHITE_HOST'=>[
        '101.81.19.53','116.226.110.175'
    ],
    'ES_SEARCH' => '_search',
     'ADS_ATR' => [
        'subjects'    => '营销活动',
        'ads'         => '活动创意',
        'publishers'  => '媒体',
        'trackers'    => '活动监测'
     ],
     'PUB_ATR'=>[
            'advertisers' => '广告主',
            'subjects'    => '营销活动',
            'ads' => '活动创意',
            'trackers'    => '活动监测'
     ],
     'TRAC_ATR'=>[
            'advertisers'        => '广告主',
            'subjects'          => '营销活动',
            'ads'                => '活动创意',
            'publishers'         => '媒体'
     ],
    'API_URL'=>'api/v1',

    'PARAM_NULL'=>'参数不可为空',
    'IS_OK' => '请求成功',
    'ADDATA_FILED'=>'id,domain,title,trackers,tags,platform,type,original_url,thumb_url,target_url,md5,advertiser,publisher,shape',
    'DOMAIN_FILED'=>'id,cname,ename,host,md5',
    'ADDATA_FILED_SOURCE'=>[
        'id','risk','tags',
        'whdivided',
        'attribute08',
        'wharea',
        'subject',
        'volume',
        'material',
        'screen',
        'url_md5','role',
        'subject_md5',
        'ad_rank',
        'publisher_advertiser',
        "brand_ads",
        "publisher_ads",
        "tracker_advertiser",
        "brand_subjects",
        "tracker_ads",
        "advertiser",
        "trackers",
        "publisher",
        "platform",
        "type",
        "created_date",
        "date5",
        "shape",
        "domain",
        "attribute04",
        "width",
        "height",
        "size",
        "x",
        "y",
        "target_url",
        "thumbnail",
        "last_seen",
        "md5",
        "thumb_width",
        "thumb_height",
        "original_url",
        "thumb_url",
        "share_url",
        "title",
        "advertiser_name",
        "advertiser_name_title",
        "advertiser_full",
        "publisher_name",
        "publisher_full",
        "tracker_name",
        "tracker_full",
        "subject_rand",
        "am_url",
        "am_source_url"
    ],

    'DOMAIN_FILED_ARR'=>['id','cname','ename','host','md5'],
    'SIZE'=>10, //每页显示 数目
    'MAXSIZE'=>9999,
    'LIMIT'=>10, // 偏移量
    'DATA_FAILED'=>'4001 日期不对，date 和 范围不可同时存在',
    'QUERY_TIME_FALIED'=>'4002 开始时间不可小于结束时间',
    'SUBJECTS'=>'subject',
    'SUBJECT_SEARCH'=>'subject/subject_index',
    'SUBJECT_SEARCH6'=>'subject_v5/subject_index',

    'DOMAINS_2'=>'domain',
    'DOMAIN_2_SEARCH'=>'domain/domainindex',
    'DOMAINS'=>'domain',
    'DOMAIN_SEARCH'=>'domain/domain_index',
    // 'DOMAIN_SEARCH'=>'domain_v6/domain_index',
    'DOMAINS_4'=>'domain',
    'DOMAIN_SEARCH_4'=>'domain/domain_index',
    'ADDATA_CHART_SEARCH'=>'addata_chart/addata_index',
    'DOMAIN_AUTO'=>'domain_auto/domain_index',
    
    'ADDATA'=>'addatas',
    'ADDATA_SEARCH'=>'addatas/addata_index',//addatas
        
    'ADDATA_SEARCH6'=>'addata_v29/addata_index',//addatas

    'ADDATA_V11_S'=>'addatas/addata_index',


    'ADDATA_AGG_SEARCH'=>'addata_aggs/addata_index',
    'ADDATA_OTT_SEARCH'=>'otts/addata_index',
    'MAININDEX'=>'mains',
    'RANKROLE'=>'ranking_role',
    'RANKROLE_SEARCH'=>'ranking_role/addata_index',
    'MAININDEX_SEARCH'=>'mains/main_index',

    'MIANS_SEARCH6'=>'main_index_v7/main_index',
    
    'HOSTMEDA'=>'hostmeda',
    'HOSTMEDA_SEARCH'=>'hostmeda/hostmeda_index',
    'DICTS'=>'dict',
    'DICTS_SEARCH'=>'dict/dicts_index',
    'DICTDOMAIN'=>'dictdomain',
    'DICTDOMAIN_SEARCH'=>'dictdomain/dict_domain_index',
    'COOKIE_ES'=>'cookie',
    'COOKIE_SEARCH'=>'cookie/cookie_index',
    'ADDATA_MID'=>'addatamid',
    'ADDATA_ATTR'=>'adattr7',
    'ADDATA_ATTR_SEARCH'=>'adattr7/addata_index',
    
    'ADDATA_MID_SEARCH'=>'',
    'FILED_EX'=>'_field_',
    'ES_DATA_NULL'=>[
        'data'=>[],
        'status'=>200
    ],
    'FILTER_WDS'=>[
        '18av','美女秀场','第一坊'
    ],
    'APP_ID_KEY'=>'-adbug.cn',
    'SEARCH_ALL_URL'=>'http://ana.adbug.cn/index.php?module=API&method=Actions.getSiteSearchKeywords&idSite=1&period=day&date={$date}&format=JSON&token_auth=3af9e184a5bfc41e5c0287c773b51b74',
    'OC_SEARCH_TABLE'=>env('OC_SEARCH_TABLE','oc_search_all'),
    'REDIS_TIME'=>10000,
    'SHOP_DOMAIN'=>['taobao.com', 'jd.com', 'tmall.com', 'suning.com', 'dianping.com', 'meituan.com', '1688.com', 'vip.com', 'amazon.cn', 'gome.com.cn', 'jumei.com', 'meilishuo.com',
        'mogujie.com', 'nuomi.com', 'yhd.com', 'ele.me', 'hc360.com', 'dangdang.com', 'mi.com', 'vmall.com',
        'mia.com', 'fanli.com', 'lemall.com', 'beibei.com', 'yaofang.cn', 'sfbest.com', '111.com.cn', 'womai.com', 'to8to.com', 'made-in-china.com', 'ehaier.com', '55tuan.com', 'cheyipai.com',
        'jiuxian.com', 'benlai.com', 'gou.com', 'lamall.com', 'moonbasa.com', '360.com', 'guazi.com', 'lefeng.com', 'etao.com', 'gome.com.cn', 'weitainet.com', 'dhgate.com',
        'zbj.com', 'ymatou.com', 'mysteel.com', 'jqw.com', 'yintai.com', 'fangdd.com', 'juanpi.com'
    ],

    'APP_DESC_DOMAIN'=> [
            'com.ss.android.article.news','com.tencent.news','com.tencent.qqlive',
            'com.qiyi.video','com.youku.phone','com.netease.newsreader.activity',
            'com.smile.gifmaker','com.letv.android.client','com.tencent.reading',
            'com.hunantv.imgo.activity',
            'com.sohu.newsclient','com.sina.news',
            'com.sohu.sohuvideo','tv.danmaku.bili','com.ifeng.news2','tv.pps.mobile',
            'com.hipu.yidian','COM.PPLIVE.ANDROIDPHONE','com.qihoo.video',
            'com.tudou.android','com.baidu.video','com.eastmoney.android.berlin',
            'com.meitu.meipaimv','com.duowan.mobile','com.baidu.news','com.yixia.videoeditor',
            'com.myzaker.zaker','com.tiexue.mobile.topnews','flipboard.cn','viva.reader','com.uc.infoflow',
            'COM.HAOKANHAOKAN.NEWS','cn.com.sina.finance'
    ],
    'APP_DESC_DOMAIN_NULL'=>[
        '小米视频',
        '头条视频',
        '映客直播',
        '腾讯体育',
        '懂球帝',
        '乐视体育'
    ],
    'DOMAIN_SEARCH_HOST'=>[
        'advertiser_name',
        'publisher',
        'trackers',
        'tags'
    ],
    'WHITE_MIN_SCORE'=>[
        '茅台','茅台商城','emaotai.cn','emaotai'
    ],
    'IAD_SIZE'=>100,
    'IAD_PAGE'=>1,
    'IAD_SORT'=>'desc',
    'IAD_AREA'=>'上海',
    'AREA_JIAN'=>[
        "天津"=>"津",
        "上海"=>"沪",
        "重庆"=>"渝",
        "河北"=>"冀",
        "河南"=>"豫",
        "湖北"=>"鄂",
        "湖南"=>"湘",
        "江苏"=>"苏",
        "江西"=>"赣",
        "辽宁"=>"辽",
        "吉林"=>"吉",
        "黑龙江"=>"黑",
        "陕西"=>"秦",
        "陕西"=>"陕",
        "山西"=>"晋",
        "山东"=>"鲁",
        "四川"=>"川",
        "四川"=>"蜀",
        "青海"=>"青",
        "安徽"=>"皖",
        "海南"=>"琼",
        "广东"=>"粤",
        "贵州"=>"黔",
        "贵州"=>"贵",
        "浙江"=>"浙",
        "福建"=>"闽",
        "台湾"=>"台",
        "甘肃"=>"甘",
        "甘肃"=>"陇",
        "云南"=>"云",
        "云南"=>"滇",
        "西藏"=>"藏",
        "宁夏"=>"宁",
        "广西"=>"桂",
        "新疆"=>"新",
        "内蒙古"=>"内蒙古",
        "香港"=>"港",
        "澳门"=>"澳",
        "北京"=>"京"
    ],
    'F_CHAR'=>[
        'a','b','d','e','f','g','h'
    ],
    'IMAGE_FILE_URL'=>'http://file.adlife.com.cn/datasync/',
    'IMAGE_FILE_URL_NEW'=>'http://file.adlife.com.cn/datasync2/',
      'WARNINGS'=>'warnings/warning',
    'NAGATIVE_CATEGORY' => [
        "1" => "酒精",
        "2" => "反日",
        "3" => "疫情",
        "4" => "群体事件",
        "5" => "军事冲突",
        "6" => "自然灾害",
        "7" => "讣告",
        "8" => "安全事故",
        "9" => "色情",
        "10" => "恐怖活动",
        "11" => "交通事故",
        "12" => "暴力",
        "13" => "未知",
        "16" => "毒品",
        "17" => "赌博",
    ],
];