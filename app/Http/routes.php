<?php


//最新APi 说明文档
Route::get('/api7/desc','HomeController@getApiDesc7');

Route::get('/home/mouth/data','HomeController@getDateMouth');


Route::match(['get','post'],'integral/task/list','IndexController@integralTaskList');

Route::match(['get','post'],'admin/ads/update','AdminController@adsinforupdate');
Route::match(['get','post'],'admin/add/new/count','AdminController@addnewcount');
Route::match(['get','post'],'admin/cname/update','AdminController@cnameUpdate');
Route::match(['get','post'],'admin/user/deal','AdminController@userdeal');
Route::match(['get','post'],'admin/vip/update','AdminController@vipUpdate');

Route::match(['get','post'],'admin/role/infor/post','AdminController@roleInforPost');
Route::match(['get','post'],'admin/role/infor/get','AdminController@roleInforGet');
Route::match(['get','post'],'admin/role/infor/put','AdminController@roleInforPut');
Route::match(['get','post'],'admin/role/infor/del','AdminController@roleInforDelete');
Route::match(['get','post'],'admin/get/function/list','AdminController@getFunctionList');
Route::match(['get','post'],'admin/integral/task/create','AdminController@integralTaskCreate');
Route::match(['get','post'],'admin/get/brand/list','AdminController@getBrandList');
Route::match(['get','post'],'admin/brand/infor/add','AdminController@brandInforAdd');
Route::match(['get','post'],'admin/brand/infor/update','AdminController@brandInforUpdate');
Route::match(['get','post'],'admin/brand/delete','AdminController@brandDelete');
Route::match(['get','post'],'admin/account/add','AdminController@accountAdd');

Route::match(['get','post'],'user/wechat/infor/create','UserController@userWechatCreate');
Route::match(['get','post'],'user/ads/share/create','UserController@adsShareCreate');
Route::match(['get','post'],'user/integral/check','UserController@userIntegralCheck');
Route::match(['get','post'],'user/order/check','UserController@userOrderCheck');
Route::match(['get','post'],'user/integral/task/check','UserController@userIntegralTask');
Route::match(['get','post'],'user/integral/task/create','UserController@userIntegralTaskCreate');
Route::match(['get','post'],'user/ads/share/create/new','UserController@adsShareCreateNew');




Route::match(['get','post'],'user/get/ip','UserController@getClientIP');


//最新一版adbug api 2018-9-20(会员版收费)
Route::group(['namespace' => 'Api9','prefix'=>'api/v9'], function()
{   
    Route::match(['get', 'post'],'/get/risk/by/search','ApiController@getRiskBysearch');
    Route::match(['get', 'post'],'/get/brand/warm/message','ApiController@getBrandWarmMessage');
    Route::match(['get', 'post'],'/get/brand/by/wd','ApiController@getBrandByWd');
    Route::match(['get', 'post'],'/brand/host/unblind','ApiController@BrandHostUnblind');
    Route::match(['get', 'post'],'/get/brand/related/infor','ApiController@getBrandRelatedInfor');
    Route::match(['get', 'post'],'/get/current/brand/ads','ApiController@getCurrentBrandAds');
    Route::match(['get', 'post'],'/get/brand/message','ApiController@getBrandMessage');
    Route::match(['get', 'post'],'/get/brand/detail','ApiController@getBrandDetail');
    Route::match(['get', 'post'],'/get/query/list','ApiController@getQueryList');
    Route::match(['get', 'post'],'/domain/infor/update','ApiController@domainInforUpdate');
    Route::match(['get', 'post'],'/brand/domain/add','ApiController@brandDomainAdd');
    Route::match(['get', 'post'],'/get/brand/list','ApiController@getBrandList');
    Route::match(['get', 'post'],'/get/tags/data','ApiController@getTagsData');

    Route::match(['get', 'post'],'/get/wechat/ads','ApiController@getWechatAds');
    Route::match(['get', 'post'],'/get/domain/new/time','ApiController@getDomainNewTime');
    Route::match(['get', 'post'],'/ad/detail','ApiController@getAdInfo');
    Route::match(['get', 'post'],'/get/top/search','ApiController@getTopSearch');
    Route::match(['get', 'post'],'/wd/split','ApiController@wdSplit');
    Route::match(['get', 'post'],'/get/top/host','ApiController@getTopHostByWd');
    Route::match(['get', 'post'],'/right/role/list','ApiController@rightRoleList');
    Route::match(['get', 'post'],'/host/chart','ApiController@hostchart');
    Route::match(['get', 'post'],'/host/chart/bar','ApiController@hostchartbar');
    Route::match(['get', 'post'],'/get/ads/search','ApiController@getAdsSearch');
    Route::match(['get', 'post'],'/get/host/message','ApiController@getHostMessage');
    Route::match(['get', 'post'],'/get/ads/detail','ApiController@getadsdetail');
    Route::match(['get', 'post'],'/get/hosts/detail','ApiController@gethostsdetail');
    Route::match(['get', 'post'],'/get/subjects/detail','ApiController@getsubjectsdetail');
    Route::match(['get', 'post'],'/get/domain/detail','ApiController@getDomainDetail');
    Route::match(['get', 'post'],'/get/domain/by/ads','ApiController@getDomainByAdsSearch');
    Route::match(['get', 'post'],'/get/ads/by/domain','ApiController@getAdsByDomain');
    Route::match(['get', 'post'],'/get/ids/search','ApiController@getIdsSearch');//给fun使用
    Route::match(['get', 'post'],'/get/aggs/data','ApiController@getAggsDataBySearch');
    Route::match(['get', 'post'],'/get/aggs/data/new','ApiController@getAggsData');
    Route::match(['get', 'post'],'/download/add','ApiController@downloadAdd');
    Route::match(['get', 'post'],'/store/add','ApiController@storeAdd');
    Route::match(['get', 'post'],'/store/del','ApiController@storeDel');
    Route::match(['get', 'post'],'/store/check','ApiController@storeCheck');
    Route::match(['get', 'post'],'/clear/times','ApiController@clearTimes');
    Route::match(['get', 'post'],'/order/add','ApiController@orderAdd');
    Route::match(['get', 'post'],'/vip/detail','ApiController@vipDetail');
    Route::match(['get', 'post'],'/ads/report','ApiController@adsReport');
    Route::match(['get', 'post'],'/ads/check','ApiController@adsCheck');
    Route::match(['get', 'post'],'/ads/audit','ApiController@adsAudit');
    Route::match(['get', 'post'],'/rank/top/list','ApiController@getRankingList');
    Route::match(['get','post'],'/current/ads','ApiController@getCurrAds');
    Route::match(['get','post'],'/ott/list/new','ApiController@getOttListNew');
    Route::match(['get', 'post'],'/land/add','ApiController@landAdd');
    Route::match(['get','post'],'/get/fp/infor','ApiController@getFpAggsInfor');
    Route::match(['get','post'],'/get/ids/infor','ApiController@getIdsInfor');
    Route::match(['get','post'],'/is/online','ApiController@isOnline');
    Route::match(['get','post'],'/get/related/domain','ApiController@getRelatedDomain');
    Route::match(['get','post'],'/get/warm/message','ApiController@getWarmMessage');
    Route::match(['get','post'],'/get/positive/categories','ApiController@positiveCategories');



    Route::match(['get', 'post'],'test','ApiController@test');
    Route::match(['get', 'post'],'test1','ApiController@test1');
    

});

//最新一版adbug api 2018-8-01
Route::group(['namespace' => 'Api8','prefix'=>'api/v8'], function()
{
    Route::match(['get', 'post'],'/get/top/search','ApiController@getTopSearch');
    Route::match(['get', 'post'],'/wd/split','ApiController@wdSplit');
    Route::match(['get', 'post'],'/get/top/host','ApiController@getTopHostByWd');
    Route::match(['get', 'post'],'/right/role/list','ApiController@rightRoleList');
    Route::match(['get', 'post'],'/host/chart','ApiController@hostchart');
    Route::match(['get', 'post'],'/host/chart/bar','ApiController@hostchartbar');
    Route::match(['get', 'post'],'/get/ads/search','ApiController@getAdsSearch');
    Route::match(['get', 'post'],'/get/host/message','ApiController@getHostMessage');
    Route::match(['get', 'post'],'/get/ads/detail','ApiController@getadsdetail');
    Route::match(['get', 'post'],'/get/hosts/detail','ApiController@gethostsdetail');
    Route::match(['get', 'post'],'/get/subjects/detail','ApiController@getsubjectsdetail');
    Route::match(['get', 'post'],'/get/domain/detail','ApiController@getDomainDetail');
    Route::match(['get', 'post'],'/get/domain/by/ads','ApiController@getDomainByAdsSearch');
    Route::match(['get', 'post'],'/get/ads/by/domain','ApiController@getAdsByDomain');
    Route::match(['get', 'post'],'/get/ids/search','ApiController@getIdsSearch');
    Route::match(['get', 'post'],'/get/aggs/data','ApiController@getAggsDataBySearch');


    

    Route::match(['get', 'post'],'test','ApiController@test');
    Route::match(['get', 'post'],'login','ApiController@login');
    Route::match(['get', 'post'],'register','ApiController@register');
    Route::match(['get', 'post'],'IdVerify','ApiController@IdVerify');
    Route::match(['get', 'post'],'/get/ads/infor','ApiController@getAdsListCount');

});
//最新一版adbug api 2018-3-8
Route::group(['namespace' => 'Api7','prefix'=>'api/v7'], function()
{
    /**
     * 统计最新注册人数以及注册人数详细信息
     */
    Route::match(['get','post'],'add/new/count','ApiController@addnewcount');

    /**
     * 更新host中文名
     */
    Route::match(['get','post'],'cname/update','ApiController@cnameUpdate');

    /**
     * 注册用户处理
     */
    Route::match(['get','post'],'user/deal','ApiController@userdeal');
   
  /**
   * 更改广告创意的信息
   */
  Route::match(['get','post'],'ads/update','ApiController@adsinforupdate');
/**
   * 测试
   */
  Route::match(['get','post'],'test','ApiController@test');

  Route::match(['get','post'],'ott/list/new','ApiController@getOttListNew');

  /**
   * 热门角色
   * 广告主 媒体 跟踪者
   * type 1 添加 2 删除
   * code 600 改域名不存在
   */
  Route::match(['get','post'],'hot/roles','ApiController@hotRoles');

  /**
   * 获取列表
   * type 1 首页 2 详细页列表
   */
  Route::match(['get','post'],'hot/roles/list','ApiController@hotRolesList');
  /**
   * 更新热门状态
   */
  Route::match(['get','post'],'/up/hotad','ApiController@hotAds');
  Route::match(['get','post'],'/hotads/list','ApiController@getHotAds');

  Route::match(['get','post'],'/current/ads','ApiController@getCurrAds');
  Route::match(['get','post'],'/hot/ads','ApiController@getHotsAd');
  /**
   * 登录
   * account 邮箱 pwd 密码
   */
  Route::match(['get', 'post'],'/login','ApiController@login');

  /**
   * 注销
   * account 邮箱
   */
  Route::match(['get','post'],'/destory','ApiController@userDestory');

  /**
   * 判断用户是否登录
   * account 用户邮箱
   */
  Route::match(['get','post'],'/user/online','ApiController@isOnline');

  /**
   * 注册
   */
  Route::match(['get', 'post'],'/register','ApiController@register');

  /**
   * 重置密码
   * code 210 账号密码不可为空
   *      301 用户不存在
   *      302 验证码不存在
   *      305 更新密码失败
   * active 激活码
   * pwd 密码
   * account 账号
   */
  Route::match(['get', 'post'],'/reset/pwd','ApiController@resetPwd');

  //邮箱激活码
  Route::match(['get', 'post'],'/valid/email/alive','ApiController@validEmailAlive');
   /**
     * 搜索结果接口
     * type 0 返回所有数据 1 简化数据
     * sort 排序 默认 desc
     * wd 搜索内容 urlencode
     * page 分页 默认 1
     */
   Route::match(['get', 'post'],'/search/ads','ApiController@getAllAds');
   /**
    * 搜索过滤接口
    * width 宽 
    * min_width 范围
     * wd 关键词 urlencode 编码
     * height 高 min_height 范围
     * platform 平台 默认所有终端　１　ｐｃ　　２　移动 3 IOS 4 Android
     * start_time 开始时间 end_time 结束时间 时间查询 时间戳 精确到秒
     * shape 形状  所有尺寸  1  ， 2 ，3 ...
     * type 类型 1 图片 2 视频 3 flush 4 html5 5 原生
    */
   Route::match(['get', 'post'],'/search/ads/filter','ApiController@getSearchFilterAds');

   /**
    * 相关广告主
    */
   Route::match(['get', 'post'],'/search/related/subjects','ApiController@getRelationSubjects');
   /**
    * competitive intelligence;
    * wd 关键词
    */
   Route::match(['get', 'post'],'/competitive/infos','ApiController@getCompetitiveInfos');
    /**
     * 获取广告主列表
     * size 条数
     * 满足web 页面 左侧功能
     */
   Route::match(['get', 'post'],'/adver/left/list','ApiController@getAdverLeftListTop');
   /**
     * 获取 ott 信息列表
     * 满足首页需求
     * size 列表个数 默认 10 条 最大200
     */
   Route::match(['get', 'post'],'/ott/list','ApiController@getOttList');
   /**
     * role 1 广告主 2 媒体 3 跟踪者
     * rv 对应域名的值
     * size 每页显示条数
     * page 翻页 默认 1
     */
   Route::match(['get', 'post'],'/role/detail','ApiController@getAdsByRoleList');

   /**
    *根据点击左侧域名列表获取对应的创意数
    *role 1 广告主 2 媒体 3 跟踪者
    *rv对应的域名值
    *page 分页
    */
   Route::match(['get', 'post'],'/advertiser/left/search','ApiController@getAdverLeftAds');

   /* 获取对应角色对应的tab 对应的具体数据接口 包括 广告主 媒体 跟踪者 营销活动 创意
     * role 1 广告主 2 媒体 3 跟踪者
     * rv 对应域名的值
     * tab 1 广告主 2 媒体 3 跟踪者 4 营销活动 5 创意
     * last_id 当前页最大的编号 暂时去掉
     * page 分页 默认 1
     */
   Route::match(['get', 'post'],'/role/detail/list','ApiController@getDetailInfoByRoleList');
   /**
     * 根据营销活动 md5 的值 获取对应的额数据
     * tab 1 广告主 2 媒体 3 跟踪者 4 创意 默认
     * last_id 最后一次最大的编号 分页使用
     * page 分页
     * @return [type] [description]
     */
   Route::match(['get', 'post'],'/subject/detail/list','ApiController@getSubjectDetailInfoList');
   /**
     * 根据关键词获取相关广告主信息
     * wd 关键词
     * size 当前页显示的条数
     * sort 排序方式 默认 desc
     */
   Route::match(['get', 'post'],'/related/advertiser','ApiController@getRelationAdvertisers');
   /**
     * 根据角色 获取对应的排名数据
     * role 1 媒体 2 广告主 3 媒体 4 创意 5 营销活动
     * time_type 1 前一天 2 前一周 3 前半月 4 前一月
     */
   Route::match(['get', 'post'],'/rank/top/list','ApiController@getRankingList');
   /**
     * 首页 查询 使用
     * 获取 广告主 媒体　追踪者
     * role 1 广告主 2 媒体 3 追踪者
     * type 0 返回所有字段 1 只返回 role host cname ename 字段
     */
   Route::match(['get', 'post'],'/query/list','ApiController@getQueryList');
   /**
    * 获取创意具体信息
    */
   Route::match(['get', 'post'],'/ad/detail','ApiController@getAdInfo');
   /**
    * 相关创意
    * 同一个广告主下面的创意广告
    * host 广告主域名
    */
   Route::match(['get', 'post'],'/related/ads','ApiController@getRelationAds');

});


Route::group(['namespace' => 'Api5','prefix'=>'api/v5'], function()
{
   // 获取token 
   Route::get('get/token','ApiController@getToken');
   Route::get('get/data/attribute','ApiController@getDataAttribute');

   Route::get('get/token/128','ApiController@getToken128');
   //node js 版本
   Route::get('get/nodejs/token','ApiController@getTokenNodeJs');

   Route::get('auth/nodejs/token','ApiController@authNodeJSToken');
   //认证 TOKEN
   Route::get('auth/token','ApiController@authToken');

   //fun js 控制版本
   Route::get('js/v','ApiController@getJSVersion');
   Route::get('js/v/c','ApiController@getJSContent');
   Route::get('piwik/es/dict','ApiController@saveDictPiwik');
});
/**
 * apiv4  杭州 数治科技 接口
 * @author  finn <[<fyj@adbug.cn>]>finn
 * @time(2017-8-14) 
 */
Route::group(['namespace' => 'Api4','prefix'=>'api/v4'], function()
{
   // 首页 获取 媒体 app 广告联盟 最近5条数据
   Route::get('iad/index/data','ApiController@iadIndexData');

   Route::get('iad/adlm','ApiController@lianMengAds');

   // 导出数据 2017-11-09
   Route::get('iad/export/kai','ApiController@iadExportKaiData');
   //按照 广告主域名 完全匹配 查询 导出数据
   Route::get('iad/export/kaiadv','ApiController@iadExportKaiAdverData');
   //临时使用
   Route::get('iad/export/kaitmp','ApiController@iadExportKaiAdverTmpData');

   //自动搜素 结果 已完成
   Route::get('iad/search/auto','ApiController@iadAutoSeach');
   // 默认 搜索页 调用接口
   Route::get('iad/all/list','ApiController@iadAdsList');
   // 获取 所有 数据 已完成
   Route::get('iad/all/search','ApiController@iadAllSearch');

   Route::get('iad/area/search','ApiController@iadAreaSearch');


   Route::get('iad/aggs/host','ApiController@iadHostAggs');

   //获取排名榜
   Route::get('iad/tags/rank','ApiController@iadTagsRank');
   
   //根据 编号 获取 数据
   Route::get('iad/ad/id','ApiController@getAdInfoById');
   //想干创意
   Route::match(['get', 'post'],'iad/related/ads','ApiController@iadRelatedAdsInfo');
   // domain 基本信息 已完成
   Route::get('iad/domain/info','ApiController@iadDomainInfo');
   Route::get('iad/domain/host','ApiController@iadDomainHost');

   Route::get('iad/domain/list','ApiController@iadDomainList');
   
   Route::get('iad/domain/md5','ApiController@iadDomainInfoByMd5');
   // domain 自动纠错
   Route::get('iad/domain/auto/comple','ApiController@iadDomainAutoCompletion');
   // 投放量排名 已完成
   Route::get('iad/rank/top','ApiController@iadRankTop');
   // 最近一周 很久 创意展现量排序获取数据 已完成
   Route::get('iad/top/ads','ApiController@iadAdsTop');
   // 获取营销活动 详细信息 已完成
   Route::get('iad/subject/info','ApiController@iadSubjectInfo');

   // 场景还原 接口
   Route::get('iad/scene/restore','ApiController@iadSceneRestore');

   // 定时 发送 最新数据给 杭州 数治 已完成
   Route::match(['get', 'post'], 'iad/crond', 'ApiController@iadCrondData');

   // 过滤 条件// 过滤 搜索
   Route::get('iad/all/filter','ApiController@iadAllFilter');

   // 角色 广告投放详细
   Route::get('iad/inner/adorsubject','ApiController@iadInnerSubjectOrAds');

   // 角色详细
   Route::get('iad/ad/inner/detail','ApiController@iadAdInnerDetail');
  
   // 所有营销活动
   Route::get('iad/all/subject','ApiController@iadAllSubject');

   Route::get('iad/addata/ids','ApiController@getInfoInIds');
   Route::get('iad/addata/id','ApiController@getAllById');
   
   
   // 数据 导出
   Route::get('iad/all/export','ApiController@iadExportData');

   // 最近一周 很久 广告展现量排序获取数据
   Route::get('iad/top/ids','ApiController@iadIdsTop');

   Route::get('iad/hot/list','ApiController@iadHotList');

   // 统计汇总 api
   // 广告创意投放量
   Route::get('iad/ads/statistics','ApiController@iadAdStatis');
   // 类型投放
   Route::get('iad/type/statistics','ApiController@iadAdTypeStatis');
   // 平台投放
   Route::get('iad/platform/statistics','ApiController@iadAdPlatformStatis');
   // 地域 投放排名
   Route::get('iad/area/rank/statistics','ApiController@iadAreaRankStatis');

   Route::get('iad/area/publishers','ApiController@getPublishersByMap');
   //提交URL到正在爬取的任务列表
   Route::match(['get','post'],'iad/submit/url','ApiController@submitUrl');
   //获取指定的URl 爬取的数据
   Route::get('iad/find/submit/url','ApiController@getSubmitUrl');
   
});
// api v6 阿里云 版本 最新 adbug.cn 暂时没有上线
Route::group(['namespace' => 'Api6','prefix'=>'api/v6'], function()
{
   Route::get('user/info','ApiController@getUserInfo');
   // 2017-9-6 获取 ott 详细列表 给 阮丹使用
   Route::get('ad/ott/list','ApiController@getOttList');
   Route::get('ad/ott/listinfo','ApiController@getOttListInfo');
   // 根据 字段  或者 一条数据 2017-8-17
   Route::get('domain/find/one','DomainController@getOneByfield');
   //2017-8-11 垃圾代码
   Route::get('data/app/ids','ApiController@getAppPIds');

   Route::get('data/read/search','ApiController@datars');
   // 新增数据
   Route::get('es/bulk','ApiController@bulkEsData');

   Route::get('addata/binlogs/ads','AddataController@getAllNew');
   // 临时
   Route::get('addata/wd/tmp','AddataController@getWHADsTmp');
   Route::get('domain/pc/link','ApiController@getAdvertiserTmpPClink');
   Route::get('domain/pc/eslink','ApiController@getAdvertiserTmpPClinkEs');
   Route::get('addata/att/tmp','ApiController@getAttr07Tmp');
   Route::get('domain/flag/tmp','ApiController@getDomainFlagTmp');
   // 2017-6-23 新增
   // 域名 订阅功能 start
   Route::get('domain/host/isexist','DomainController@getHostIsExist');
   Route::get('addata/binlog/ads','ApiController@getBinLogAds');
   // 域名 订阅功能 end
   // 2017-7-7 finn 自动纠错 
   Route::get('domain/auto/comple','DomainController@getDomainAutoCompletion');

   Route::get('addata/all/search','ApiController@getRankAdvertiser');
   //定时更新 数据 tags 数据
   Route::get('up/rank/tags','ApiController@upTagsInfo');
   Route::get('up/rank/auto/tags','ApiController@upAdTagsInfoAuto');
   Route::get('up/rank/adtags','ApiController@upAdTagsInfo');
   // 更新trackers 二级域名 到 domain falg = 1 表里面
   Route::get('up/rank/domain','ApiController@addDomainInfo');
   // 2017-5-19 手动统计排名数据
   Route::get('addata/ranking/aggs','ApiController@getAdsInfo');
   Route::get('addata/rank/role','ApiController@getRankingList');
   Route::get('addata/ranking/advertiser','ApiController@rankListAdvertiser');
   //统计排行榜 finn 2017-5-9 
   Route::get('addata/ranking/list','ApiController@rankingList');

   Route::get('addata/all/ids','AddataController@getAllIds');
   Route::get('domain/find/ad','ApiController@getDomainByAd');
   //2017-4-19 根据host 获取 数据
   Route::get('domain/find/host','DomainController@getAllInfoByHost');
   // 2017-4-14 修复 最新 视频广告 问题 Finn
   Route::get('api/find/field','ApiController@getAllField');
   Route::get('api/field/type','ApiController@getFieldByType');
   
   // 获取 散点关系图
   Route::get('echarts/find/data','ApiController@findEchartData');
   // 统计 40 app 创意数 曲线图
   Route::get('ads/trand/pub40','ApiController@trandPubAds40');
   // 获取 前 300 创意 对应的媒体 投放趋势 数据
   Route::get('ads/trand/pub','ApiController@trandPubTop');
   
   //根据 角色 查询 内容  时间 进行 过滤
   Route::get('addataagg/chart/view','AddataController@getAdsTrendAggView');
   // 判断 该ads 是否有相关记录 finn 2017-2-17
   Route::get('ads/trend/agg','AddataController@getAdsTrendAgg');
   
   // 探索发现 - 品牌广告 start
   Route::get('find/search/ads','ApiController@getAdsBySize');
   //- 广告创意
   Route::get('back/table/ads/info','ApiController@getAdsMind');
   // - 营销活动
   Route::get('back/table/sub/info','ApiController@getSubjectAds');
   // 探索发现 -  end
   
   //fun 需要接口 1 2016-11-18
   Route::get('find/search/info','ApiController@getSearchRs');
   //fun 需要接口 2 2018-2-10
   Route::get('find/ads/info','ApiController@getSearchAds');
   // 后台 图表 统计
   Route::get('find/role/info','ApiController@geTableInfoByRole');
   // 获取 domain 基本信息
   Route::get('find/domain/info','ApiController@getDomainInfo');
   // 或者 广告主
   Route::get('find/subject/info','ApiController@getTableSubjectInfo');
   // 后台数据
   Route::get('back/table/info','ApiController@getTableInfo');
   // 获取 广告主
   Route::get('back/table/domain/ads','ApiController@getAdsNew');
  
   Route::get('addata/all/subject','AddataController@getAllSubject');
   
   Route::get('cookie/top10','ApiController@getCookieTop10');
   Route::get('getmaxid','ApiController@getMaxId');
   // addata api 
   Route::get('addata/all','AddataController@getAll');

   Route::get('addata/allinfo','AddataController@getAllInfo');
   Route::get('addata/filter/search','AddataController@getAllFilterSearch');
   Route::get('addata/exist','AddataController@isExist');
   Route::get('addata/all/sub','AddataController@getAllSubFilter');
   Route::get('addata/all/sub2','AddataController@getAllSubFilter2');
   Route::get('addata/find/id','AddataController@getAllById');
   
   Route::get('addata/find/md5','AddataController@getInfoByMd5');
   Route::get('addata/filter/ads','AddataController@getAllSubAds');
   Route::get('addata/all/mobile','AddataController@getmobileTop100');
   //domain api  start
   Route::get('domain/host','DomainController@getInfoByHost');
   Route::get('domain/filter','DomainController@getRoleFilter');
   Route::get('domain/ads','AddataController@getAllAds');
   Route::get('domain/md5','DomainController@getInfoByMd5');
   Route::get('domain/query/host','DomainController@getInfoByField');
   Route::get('domain/query/hosts','DomainController@getInfoInHosts');
   Route::get('domain/in/ids','DomainController@getInfoInIds');
   Route::get('domain/id','DomainController@getInfoById');
   //subject api start
   Route::get('subject/md5','SubjectsController@getInfoByMd5');
   Route::get('subject/id','SubjectsController@getInfoById');
   Route::get('subject/ids','SubjectsController@getInfoInIds');
   //mainindex api start
   Route::get('mainindex/id','MainIndexController@getInfoId');
   Route::get('mainindex/find/subject','MainIndexController@getInfoBySubjects');
   Route::get('mainindex/count','MainIndexController@getCountByFiled');
   Route::get('mainindex/count2','MainIndexController@getCountByFiledInfo');
   Route::get('mainindex/groupby','MainIndexController@getInfoGroupByFiled');
   Route::get('mainindex/find/count','MainIndexController@getInfoCountAll');
   Route::get('mainindex/top','MainIndexController@getAllFieldTop6');
   Route::get('mainindex/role/find','MainIndexController@getInfoByDidRole');
   //host_meda
   Route::get('hostmeda/find/host','HostMedaController@getInfoByHost');
   Route::get('hostmeda/find/id','HostMedaController@getInfoById');

   Route::get('find/by/role','ApiController@getInfoByRole');
   // 获取 分词结果 ik
   Route::get('dicts/ik','DictsController@getDictIk');
   // 记录 查询 内容 
   Route::get('redis/add','RedisController@addRedis');

   Route::get('domain/query/field','DomainController@getQueryInfoByField');
   Route::get('domain/query/binlog','DomainController@getQueryInfoByBinlog');
   // 实时最新广告 is ok 
   Route::get('recent/ads','ApiController@getRecentAds');
   //recent-mobile  最新移动广告 is ok
   Route::get('recent/ads/mobile','ApiController@getRecentMobileAds');
   // recent-video 最新视频广告 is ok
   Route::get('recent/ads/video','ApiController@getRecentVideoAds');
   //top-tracker 每周跟踪排行 is ok
   Route::get('top/tracker','ApiController@getTopTrackerAds');
   //random  随机广告 is ok 
   Route::get('random/ads','ApiController@getRandomAds');


   //=================暂未使用Api======================================
   Route::get('addata/between/ids','AddataController@getInfoInIds');
   Route::get('find/user/new','ApiController@getUserInfoNew');
   Route::get('addata/filter','AddataController@getAllFilter');
   Route::get('addata/find/ids','AddataController@getAllByIds');
   Route::get('addata/param/count','AddataController@getCountParam');
   Route::get('domain/query/hosttest','DomainController@getQueryInfoByFieldTest');
   Route::get('deleteJDB','ApiController@deleteJDB');
   Route::get('jisu/adsdsppub','ApiController@getAdsDSPPub');
   Route::get('addata/info/id','ApiController@getAddataById');
   //================ 首页 搜索 接口 暂无改接口
   Route::get('ads/trend/search','AddataController@getAdsTrend');


   //===================临时 api ========================
   Route::get('addata/subject/range','AddataController@getSubjectRange');
   // 分类排名 - 获取 媒体 或者 追踪者
   Route::get('find/pubtrac/info','ApiController@getTablePubTracInfo');
   // 品牌库 对比
   Route::get('ppku','ApiController@ppku');
   // =====识别 图片 中的文字 临时 api==========
   Route::get('ocr/title','ApiController@ocrTitle');
   Route::get('ocr/title1','ApiController@ocrTitle1');
   Route::get('ocr/title2','ApiController@ocrTitle2');
   Route::get('ocr/title3','ApiController@ocrTitle3');
   Route::get('ocr/title4','ApiController@ocrTitle4');
   Route::get('ocr/title5','ApiController@ocrTitle5');
   //=====================adbug 暂无使用===============================
    //广告主
   Route::get('advertiser/index','ApiController@getAdsIndex');
   //追踪者
   Route::get('tracker/index','ApiController@getTrackerIndex');
   // 媒体  //营销活动
   Route::get('publisher/index','ApiController@getPublisherIndex');


   // ==============  下一个 版本丢弃 暂时没有使用 ================
   // finn 2017-2-10 根据 指定 角色 查询想要的对应 角色 的domain 信息
   Route::get('mainindex/role/domain','MainIndexController@getDomianIdBYRole');

   Route::get('addata/find/title','AddataController@getInfoByTitle');
   Route::get('addata/find/count','AddataController@getAdsCount');
   Route::get('addata/filter/maxormin','AddataController@getMaxMinfield');
   Route::get('addata/count','AddataController@getCount');
   // domain api
   Route::get('domain/all','DomainController@getAll');
   // Route::get('domain/filters','DomainController@getRoleFilter2');
   Route::get('domain/role/field','DomainController@getInfoByRole');
   //取消 该方法 和 getInfoByField 功能一样
   Route::get('domain/query/host2','DomainController@getInfoByField2');
   Route::get('domain/query/role','DomainController@getQueryInfoByRole');
   //subjects api
   Route::get('subject/all','SubjectsController@getAll');
   Route::get('mainindex/field','MainIndexController@getAllField');
   Route::get('subject/find/md5','SubjectsController@getInfoInMd52');
   //main index api
   Route::post('mainindex/count/post','MainIndexController@postCount');
   Route::get('mainindex/count/post','MainIndexController@postCount');
   Route::get('mainindex/all','MainIndexController@getAll');
   Route::get('mainindex/find/id','MainIndexController@getInfoInIds');
   Route::get('mainindex/groupby/param','MainIndexController@getInfoGroupByFiledParam');
   Route::get('mainindex/subject','MainIndexController@getAdsBySub');
   Route::get('redis/find','RedisController@findRedis');
   Route::post('redis/cookis','RedisController@addCookis');
   Route::get('redis/cookis','RedisController@addCookis');
   //词典查询
   //Route::get('dicts/query','DictsController@getRelationDicts');
   Route::get('dicts/query/test','DictsController@testDict');
   Route::get('redis/find/cookis','RedisController@getCookis');
   //根据 域名 获取 信息
   Route::get('domain/all/info','DomainController@getAll_3');
   Route::get('domain/field/info','DomainController@getInfoByField_3');
   //获取 营销活动 信息
   Route::get('subject/all/info','SubjectsController@getAll_3');
   Route::get('subject/id/info','SubjectsController@getInfoById_3');
   //===========================start  adbug 暂无使用 api=======================================
   Route::get('domain/all/query','DomainController@getQueryAllInfo');
   Route::get('domain/role','DomainController@getRolesinfo');
   Route::get('domian/query','DomainController@getQueryInfo');
   //收索 营销活动　is ok waiting test
   Route::get('search/subjects','ApiController@getSearchSubject');
   // //收索 广告创意
   Route::get('search/ads','ApiController@getSearchAds');
   // //20170705 广告主 媒体 追踪者 index
   Route::get('pubtacads/index','ApiController@getPubTacAdsIndex');
});

// api v3 版本 最新 adbug.cn 暂时没有上线
Route::group(['namespace' => 'Api3','prefix'=>'api/v3'], function()
{
   Route::get('user/info','ApiController@getUserInfo');
   // 2017-9-6 获取 ott 详细列表 给 阮丹使用
   Route::get('ad/ott/list','ApiController@getOttList');
   // 根据 字段  或者 一条数据 2017-8-17
   Route::get('domain/find/one','DomainController@getOneByfield');
   //2017-8-11 垃圾代码
   Route::get('data/app/ids','ApiController@getAppPIds');

   Route::get('data/read/search','ApiController@datars');
   // 新增数据
   Route::get('es/bulk','ApiController@bulkEsData');

   Route::get('addata/binlogs/ads','AddataController@getAllNew');
   // 临时
   Route::get('addata/wd/tmp','AddataController@getWHADsTmp');
   Route::get('domain/pc/link','ApiController@getAdvertiserTmpPClink');
   Route::get('domain/pc/eslink','ApiController@getAdvertiserTmpPClinkEs');
   Route::get('addata/att/tmp','ApiController@getAttr07Tmp');
   Route::get('domain/flag/tmp','ApiController@getDomainFlagTmp');
   // 2017-6-23 新增
   // 域名 订阅功能 start
   Route::get('domain/host/isexist','DomainController@getHostIsExist');
   Route::get('addata/binlog/ads','ApiController@getBinLogAds');
   // 域名 订阅功能 end
   // 2017-7-7 finn 自动纠错 
   Route::get('domain/auto/comple','DomainController@getDomainAutoCompletion');

   Route::get('addata/all/search','ApiController@getRankAdvertiser');
   //定时更新 数据 tags 数据
   Route::get('up/rank/tags','ApiController@upTagsInfo');
   Route::get('up/rank/auto/tags','ApiController@upAdTagsInfoAuto');
   Route::get('up/rank/adtags','ApiController@upAdTagsInfo');
   // 更新trackers 二级域名 到 domain falg = 1 表里面
   Route::get('up/rank/domain','ApiController@addDomainInfo');
   // 2017-5-19 手动统计排名数据
   Route::get('addata/ranking/aggs','ApiController@getAdsInfo');
   Route::get('addata/rank/role','ApiController@getRankingList');
   Route::get('addata/ranking/advertiser','ApiController@rankListAdvertiser');
   //统计排行榜 finn 2017-5-9 
   Route::get('addata/ranking/list','ApiController@rankingList');

   Route::get('addata/all/ids','AddataController@getAllIds');
   Route::get('domain/find/ad','ApiController@getDomainByAd');
   //2017-4-19 根据host 获取 数据
   Route::get('domain/find/host','DomainController@getAllInfoByHost');
   // 2017-4-14 修复 最新 视频广告 问题 Finn
   Route::get('api/find/field','ApiController@getAllField');
   Route::get('api/field/type','ApiController@getFieldByType');
   
   // 获取 散点关系图
   Route::get('echarts/find/data','ApiController@findEchartData');
   // 统计 40 app 创意数 曲线图
   Route::get('ads/trand/pub40','ApiController@trandPubAds40');
   // 获取 前 300 创意 对应的媒体 投放趋势 数据
   Route::get('ads/trand/pub','ApiController@trandPubTop');
   
   //根据 角色 查询 内容  时间 进行 过滤
   Route::get('addataagg/chart/view','AddataController@getAdsTrendAggView');
   // 判断 该ads 是否有相关记录 finn 2017-2-17
   Route::get('ads/trend/agg','AddataController@getAdsTrendAgg');
   
   // 探索发现 - 品牌广告 start
   Route::get('find/search/ads','ApiController@getAdsBySize');
   //- 广告创意
   Route::get('back/table/ads/info','ApiController@getAdsMind');
   // - 营销活动
   Route::get('back/table/sub/info','ApiController@getSubjectAds');
   // 探索发现 -  end
   
   //fun 需要接口 1 2016-11-18
   Route::get('find/search/info','ApiController@getSearchRs');
   //fun 需要接口 2 2018-2-10
   Route::get('find/ads/info','ApiController@getSearchAds');
   // 后台 图表 统计
   Route::get('find/role/info','ApiController@geTableInfoByRole');
   // 获取 domain 基本信息
   Route::get('find/domain/info','ApiController@getDomainInfo');
   // 或者 广告主
   Route::get('find/subject/info','ApiController@getTableSubjectInfo');
   // 后台数据
   Route::get('back/table/info','ApiController@getTableInfo');
   // 获取 广告主
   Route::get('back/table/domain/ads','ApiController@getAdsNew');
  
   Route::get('addata/all/subject','AddataController@getAllSubject');
   
   Route::get('cookie/top10','ApiController@getCookieTop10');
   Route::get('getmaxid','ApiController@getMaxId');
   // addata api 
   Route::get('addata/all','AddataController@getAll');

   Route::get('addata/allinfo','AddataController@getAllInfo');
   Route::get('addata/filter/search','AddataController@getAllFilterSearch');
   Route::get('addata/exist','AddataController@isExist');
   Route::get('addata/all/sub','AddataController@getAllSubFilter');
   Route::get('addata/all/sub2','AddataController@getAllSubFilter2');
   Route::get('addata/find/id','AddataController@getAllById');
   
   Route::get('addata/find/md5','AddataController@getInfoByMd5');
   Route::get('addata/filter/ads','AddataController@getAllSubAds');
   Route::get('addata/all/mobile','AddataController@getmobileTop100');
   //domain api  start
   Route::get('domain/host','DomainController@getInfoByHost');
   Route::get('domain/filter','DomainController@getRoleFilter');
   Route::get('domain/ads','AddataController@getAllAds');
   Route::get('domain/md5','DomainController@getInfoByMd5');
   Route::get('domain/query/host','DomainController@getInfoByField');
   Route::get('domain/query/hosts','DomainController@getInfoInHosts');
   Route::get('domain/in/ids','DomainController@getInfoInIds');
   Route::get('domain/id','DomainController@getInfoById');
   //subject api start
   Route::get('subject/md5','SubjectsController@getInfoByMd5');
   Route::get('subject/id','SubjectsController@getInfoById');
   Route::get('subject/ids','SubjectsController@getInfoInIds');
   //mainindex api start
   Route::get('mainindex/id','MainIndexController@getInfoId');
   Route::get('mainindex/find/subject','MainIndexController@getInfoBySubjects');
   Route::get('mainindex/count','MainIndexController@getCountByFiled');
   Route::get('mainindex/count2','MainIndexController@getCountByFiledInfo');
   Route::get('mainindex/groupby','MainIndexController@getInfoGroupByFiled');
   Route::get('mainindex/find/count','MainIndexController@getInfoCountAll');
   Route::get('mainindex/top','MainIndexController@getAllFieldTop6');
   Route::get('mainindex/role/find','MainIndexController@getInfoByDidRole');
   //host_meda
   Route::get('hostmeda/find/host','HostMedaController@getInfoByHost');
   Route::get('hostmeda/find/id','HostMedaController@getInfoById');

   Route::get('find/by/role','ApiController@getInfoByRole');
   // 获取 分词结果 ik
   Route::get('dicts/ik','DictsController@getDictIk');
   // 记录 查询 内容 
   Route::get('redis/add','RedisController@addRedis');

   Route::get('domain/query/field','DomainController@getQueryInfoByField');
   Route::get('domain/query/binlog','DomainController@getQueryInfoByBinlog');
   // 实时最新广告 is ok 
   Route::get('recent/ads','ApiController@getRecentAds');
   //recent-mobile  最新移动广告 is ok
   Route::get('recent/ads/mobile','ApiController@getRecentMobileAds');
   // recent-video 最新视频广告 is ok
   Route::get('recent/ads/video','ApiController@getRecentVideoAds');
   //top-tracker 每周跟踪排行 is ok
   Route::get('top/tracker','ApiController@getTopTrackerAds');
   //random  随机广告 is ok 
   Route::get('random/ads','ApiController@getRandomAds');


   //=================暂未使用Api======================================
   Route::get('addata/between/ids','AddataController@getInfoInIds');
   Route::get('find/user/new','ApiController@getUserInfoNew');
   Route::get('addata/filter','AddataController@getAllFilter');
   Route::get('addata/find/ids','AddataController@getAllByIds');
   Route::get('addata/param/count','AddataController@getCountParam');
   Route::get('domain/query/hosttest','DomainController@getQueryInfoByFieldTest');
   Route::get('deleteJDB','ApiController@deleteJDB');
   Route::get('jisu/adsdsppub','ApiController@getAdsDSPPub');
   Route::get('addata/info/id','ApiController@getAddataById');
   //================ 首页 搜索 接口 暂无改接口
   Route::get('ads/trend/search','AddataController@getAdsTrend');


   //===================临时 api ========================
   Route::get('addata/subject/range','AddataController@getSubjectRange');
   // 分类排名 - 获取 媒体 或者 追踪者
   Route::get('find/pubtrac/info','ApiController@getTablePubTracInfo');
   // 品牌库 对比
   Route::get('ppku','ApiController@ppku');
   // =====识别 图片 中的文字 临时 api==========
   Route::get('ocr/title','ApiController@ocrTitle');
   Route::get('ocr/title1','ApiController@ocrTitle1');
   Route::get('ocr/title2','ApiController@ocrTitle2');
   Route::get('ocr/title3','ApiController@ocrTitle3');
   Route::get('ocr/title4','ApiController@ocrTitle4');
   Route::get('ocr/title5','ApiController@ocrTitle5');
   //=====================adbug 暂无使用===============================
    //广告主
   Route::get('advertiser/index','ApiController@getAdsIndex');
   //追踪者
   Route::get('tracker/index','ApiController@getTrackerIndex');
   // 媒体  //营销活动
   Route::get('publisher/index','ApiController@getPublisherIndex');


   // ==============  下一个 版本丢弃 暂时没有使用 ================
   // finn 2017-2-10 根据 指定 角色 查询想要的对应 角色 的domain 信息
   Route::get('mainindex/role/domain','MainIndexController@getDomianIdBYRole');

   Route::get('addata/find/title','AddataController@getInfoByTitle');
   Route::get('addata/find/count','AddataController@getAdsCount');
   Route::get('addata/filter/maxormin','AddataController@getMaxMinfield');
   Route::get('addata/count','AddataController@getCount');
   // domain api
   Route::get('domain/all','DomainController@getAll');
   // Route::get('domain/filters','DomainController@getRoleFilter2');
   Route::get('domain/role/field','DomainController@getInfoByRole');
   //取消 该方法 和 getInfoByField 功能一样
   Route::get('domain/query/host2','DomainController@getInfoByField2');
   Route::get('domain/query/role','DomainController@getQueryInfoByRole');
   //subjects api
   Route::get('subject/all','SubjectsController@getAll');
   Route::get('mainindex/field','MainIndexController@getAllField');
   Route::get('subject/find/md5','SubjectsController@getInfoInMd52');
   //main index api
   Route::post('mainindex/count/post','MainIndexController@postCount');
   Route::get('mainindex/count/post','MainIndexController@postCount');
   Route::get('mainindex/all','MainIndexController@getAll');
   Route::get('mainindex/find/id','MainIndexController@getInfoInIds');
   Route::get('mainindex/groupby/param','MainIndexController@getInfoGroupByFiledParam');
   Route::get('mainindex/subject','MainIndexController@getAdsBySub');
   Route::get('redis/find','RedisController@findRedis');
   Route::post('redis/cookis','RedisController@addCookis');
   Route::get('redis/cookis','RedisController@addCookis');
   //词典查询
   //Route::get('dicts/query','DictsController@getRelationDicts');
   Route::get('dicts/query/test','DictsController@testDict');
   Route::get('redis/find/cookis','RedisController@getCookis');
   //根据 域名 获取 信息
   Route::get('domain/all/info','DomainController@getAll_3');
   Route::get('domain/field/info','DomainController@getInfoByField_3');
   //获取 营销活动 信息
   Route::get('subject/all/info','SubjectsController@getAll_3');
   Route::get('subject/id/info','SubjectsController@getInfoById_3');
   //===========================start  adbug 暂无使用 api=======================================
   Route::get('domain/all/query','DomainController@getQueryAllInfo');
   Route::get('domain/role','DomainController@getRolesinfo');
   Route::get('domian/query','DomainController@getQueryInfo');
   //收索 营销活动　is ok waiting test
   Route::get('search/subjects','ApiController@getSearchSubject');
   // //收索 广告创意
   Route::get('search/ads','ApiController@getSearchAds');
   // //20170705 广告主 媒体 追踪者 index
   Route::get('pubtacads/index','ApiController@getPubTacAdsIndex');
});

Route::auth();
Route::match(['get', 'post'],'/user/login','UserController@login');
Route::match(['get', 'post'],'/user/register','UserController@register');
Route::match(['get', 'post'],'/user/resetPwd','UserController@resetPwd');
Route::match(['get', 'post'],'/user/validEmailAlive','UserController@validEmailAlive');
Route::match(['get', 'post'],'/user/isOnline','UserController@isOnline');
Route::match(['get', 'post'],'/user/logout','UserController@logout');
Route::match(['get', 'post'],'/user/xlogout','UserController@xlogout');
Route::match(['get', 'post'],'/user/check','UserController@userCheck');
Route::match(['get', 'post'],'/user/update','UserController@userUpdate');
Route::match(['get', 'post'],'/user/test','UserController@test');
Route::match(['get', 'post'],'/up/session','UserController@sessionUpdate');
Route::match(['get', 'post'],'/user/reser','UserController@reservation');
Route::match(['get', 'post'],'/user/send/code','UserController@sendMobileVerifyCode');
Route::match(['get', 'post'],'/user/code/verify','UserController@mobileCodeVerify');



Route::match(['get', 'post'],'pay','WxpayController@index');
Route::match(['get', 'post'],'/order/update','WxpayController@OrderUpdate');
Route::match(['get', 'post'],'/order/delete','WxpayController@OrderDelete');
Route::match(['get', 'post'],'/order/query','WxpayController@OrderQuery');
Route::match(['get', 'post'],'/wx/notify','WxpayController@notify');

Route::match(['get', 'post'],'/activity/index','ActivityController@index');

Route::get('/home', 'HomeController@index');
Route::get('','HomeController@index');
Route::get('index','HomeController@index');
Route::get('cominfo','HomeController@getComInfo');
Route::get('/del/mains','HomeController@delMianIndex');
Route::get('/pinpai','HomeController@pinpai');
Route::get('/pinpai/es','HomeController@savePinpaiInfo');

Route::get('show/db','HomeController@showDb');
Route::get('show/db2','HomeController@showDb2');
Route::get('show/table','HomeController@getTableInfo');
Route::get('up/domain/info','HomeController@upUrlDomain');
// 2017-1-13 新增 login register 功能
Route::get('login','HomeController@login');
Route::get('register','HomeController@register');
// 2017-1-13 end
Route::get('file','HomeController@importFile');
Route::get('file2','HomeController@file2');
Route::get('savefile2','HomeController@savefile2');
Route::get('domain/ranking/tracher','HomeController@savefileTrasker');
Route::get('domain/ranking/aiqiyi','HomeController@getRandingAiQiYi');
Route::get('domain/ranking/advertsier','HomeController@getScoreByAdvertiser');
Route::get('domain/ranking/ads','HomeController@getAdsInfo');
Route::get('rank/between/addata','HomeController@getAggsDataRank');

Route::get('rank/tracker','HomeController@addataRankTrackerTmp');
Route::get('mainindex/up','HomeController@mainIndexTmp');

//测试 sql 插件 查询
Route::get('find/domain/sql','HomeController@getEsBySql');

//2017-6-2 获取 微信 广告 从 246 数据库
Route::get('find/wx/ads','HomeController@getWenXinData');
// Route::get('importfile','HomeController@importFile');
Route::post('save/excel','HomeController@saveFile');
Route::get('info','HomeController@info');
Route::get('test','HomeController@testMysql');
Route::get('subject','HomeController@updateSubject');
Route::get('hostmeda','HomeController@updateHostMeda');
Route::get('domain','HomeController@updateDomain');
Route::get('mains','HomeController@updateMains');
Route::get('addata','HomeController@updateAddata');
Route::get('dict/domain','HomeController@domainDict');
Route::get('redis/tweek','HomeController@getRedisTwoWeek');
Route::get('get/send/data','HomeController@getSendResult');
// api  测试使用
Route::group(['namespace' => 'test'], function()
{

   // data/read/search
   Route::get('data/read/search','ApiController@datars');
   Route::get('huazhi/data', 'ApiController@huazhi');
   Route::get('domain/all/sql','ApiController@getDomainBySql');

   Route::get('addata/all/ids','AddataController@getAllIdsTest');
   //=========== 2017-1-5 获取 app 详细  导出数据 api start==========
   Route::get('domain/app/info','AddataController@getAppInfo');
   Route::get('domain/app/info/test','AddataController@getAppInfoTest');
   // 2017-2-15 导出 40 媒体 数据
   Route::get('app/publisher/domain','AddataController@exportDomainApp');
   Route::get('app/publisher/domain/test','AddataController@exportDomainApptest');
   //=========== 2017-1-5 获取 app 详细  导出数据 api end ==========
   //2017-4-13
   Route::get('up/sub/agg','ApiController@getAddataByMd5');

   Route::get('up/subject/title','ApiController@updateSubjectTitle');
   // 读取 xml 文件
   Route::get('read/xml','ApiController@readXmlData');
   //垃圾代码 ，一次使用
   Route::get('test/app/type','AddataController@getAddataTest');
   Route::get('save/app/type','AddataController@saveAppType');

   // 2017-2-6 新增 获取 广告趋势
   Route::get('ads/trend','AddataController@getAdsTrend');
   //2017-1-20
   Route::get('tracher/by/ads','ApiController@getNowTraByAds');
   Route::get('domain/tracher/ads','AddataController@getTrackerInfo');
   Route::get('domain/app/no','AddataController@getAppDomain');
   //测试 doamin  媒体 iOS Android
   Route::get('domain/queryhost','DomainController@getQueryInfoByField2');
   Route::get('find/search/all','AddataController@getAll');
   Route::get('find/search/ads','ApiController@getSearchRs');
   Route::get('mainindex/random','ApiController@getInfoGroupByFiled');
   Route::get('search/ads','ApiController@getAdsBySize');
   Route::get('getmaxid','AddataController@getmaxid');
   Route::get('redisadd','RedisController@addRedis');
   Route::get('redisFind','RedisController@getRedis');
    // addata api 20160706 完成 等待测试
   Route::get('addata/all','AddataController@getAll');
   Route::get('addata/filter','AddataController@getAllFilter');
   Route::get('addata/find/id','AddataController@getAllById');
   Route::get('addata/ids','AddataController@getAllByIds');
   Route::get('addata/title','AddataController@getInfoByTitle');
   // Route::get('addata/count','AddataController@getAdsCount');
   //20160713
   Route::get('addata/sub','AddataController@getAllSubFilter');
   Route::get('addata/between/ids','AddataController@getInfoInIds');
   Route::get('addata/ads','AddataController@getAllSubAds');
   Route::get('addata/search','AddataController@getAllFilterSearch');
   Route::get('addata/maxormin','AddataController@getMaxMinfield');
   Route::get('addata/test/all','AddataController@getAllTest');
   Route::get('addata/test/find/id','AddataController@getAllByIdTest');
   Route::get('addata/test/find/ids','AddataController@getAllByIdsTest');
   Route::get('addata/test/filer','AddataController@getAllFilterTest');
   Route::get('addata/test/title','AddataController@getInfoByTitleTest');
   // Route::get('addata/test/count','AddataController@getAdsCountTest');
   Route::get('addata/test/all/sub','AddataController@getAllSubFilterTest');
   Route::get('addata/test/between/ids','AddataController@getInfoInIdsTest');
   Route::get('addata/test/ads','AddataController@getAllSubAdsTest');
   Route::get('addata/test/search','AddataController@getAllFilterSearchTest');
   Route::get('addata/test/filter/maxormin','AddataController@getMaxMinfieldTest');
   // domain api
   Route::get('domain/all','DomainController@getAll');
   Route::get('domain/host','DomainController@getAllByHost');
   Route::get('domain/groupby','DomainController@getAllGroup');
   //20160712 测试
   //Route::get('domain/filter','DomainController@getRoleFilter');
   Route::get('domain/md5','DomainController@getInfoByMd5');
   Route::get('domain/query/host','DomainController@getInfoByField');
   Route::get('domain/in/hosts','DomainController@getInfoInHosts');
   Route::get('domain/query/field','DomainController@getQueryInfoByField');
   Route::get('domain/test/all','DomainController@getAllTest');
   Route::get('domain/test/host','DomainController@getInfoByHost');
   Route::get('domain/test/filter','DomainController@getRoleFilterTest');
   Route::get('domain/test/md5','DomainController@getInfoByMd5Test');
   Route::get('domain/test/query/host','DomainController@getInfoByFieldTest');
   Route::get('domain/test/query/hosts','DomainController@getInfoInHostsTest');
   Route::get('domain/test/query/field','DomainController@getQueryInfoByFieldTest');
   // subjects api
   Route::get('subject/all','SubjectsController@getAll');
   Route::get('subject/md5','SubjectsController@getInfoByMd5');
   Route::get('subject/id','SubjectsController@getInfoById');
   Route::get('subject/find/ids','SubjectsController@getInfoInIds');
   
   Route::get('subject/test/all','SubjectsController@getAllTest');
   Route::get('subject/test/md5','SubjectsController@getInfoByMd5Test');
   Route::get('subject/test/id','SubjectsController@getInfoByIdTest');
   Route::get('subject/test/ids','SubjectsController@getInfoInIdsTest');
   
   // main index api
   Route::get('mainindex/all','MainIndexController@getAll');
   Route::get('mainindex/find/id','MainIndexController@getInfoInIds');
   Route::get('mainindex/field','MainIndexController@getAllField');
   Route::get('mainindex/test/all','MainIndexController@getAllTest');
   Route::get('mainindex/test/find/id','MainIndexController@getInfoInIdsTest');
   Route::get('mainindex/test/field','MainIndexController@getAllFieldTest');
   //host_meda
   Route::get('hostmeda/find/host','HostMedaController@getInfoByHost');
   Route::get('hostmeda/test/find/host','HostMedaController@getInfoByHostTest');
   // 暂时没有 使用
   // 实时最新广告 is ok 
   Route::get('recent/ads','ApiController@getRecentAds');
   Route::get('test/recent/ads','ApiController@getRecentAdsTest');
   //recent-mobile  最新移动广告 is ok
   Route::get('recent/mobile','ApiController@getRecentMobileAds');
   Route::get('test/recent/ads/mobile','ApiController@getRecentMobileAdsTest');
   // recent-video 最新视频广告 is ok
   Route::get('recent/video','ApiController@getRecentVideoAds');
   Route::get('test/recent/ads/video','ApiController@getRecentVideoAdsTest');
   //top-tracker 每周跟踪排行 is ok
   Route::get('top/tracker','ApiController@getTopTrackerAds');
   // 垃圾代码 临时使用 start
   Route::get('top/tracker/test','ApiController@getTopTrackerAdsTest');
   // end
   Route::get('test/top/tracker','ApiController@getTopTrackerAdsTest');
   //random  随机广告 is ok 
   Route::get('test/random/ads','ApiController@getRandomAdsTest');
   Route::get('random/ads','ApiController@getRandomAds');
   // 测试
   Route::get('test/addata/all','ApiController@getAll');
});

//推荐人订单(wyt)

Route::match(['get', 'post'],'list/all','Order\OrderlistController@getList');
Route::match(['get', 'post'],'list/delList','Order\OrderlistController@delList');
Route::match(['get', 'post'],'list/users','Order\OrderlistController@showOrder');
Route::match(['get'],'newor/newder','Order\OrderlistController@addOrders');
Route::match(['get'],'downLoad','Order\OrderlistController@downLoad');
Route::match(['get'],'integration','Order\IntegrationController@addIntegration');

