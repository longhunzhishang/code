
@extends('_layouts.Api')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到Adbug API 功能分析页面</H2>
   
    <p>状态码说明：
    <small style="display:block"> 
    200 请求成功<br/>
    203 暂无数据<br/>
    204 查询条件错乱(date start_time 或者 date end_time 不可同时存在)<br/>
    205 开始时间不可大于结束时间<br/>
    206 参数格式不对<br/>
    207 搜索参数不可为空,<br/>
    208 参数超出范围
    </small>
    </p>
    <p>
        data 返回数据 status 状态码 
    </p>

    <p style="margin-top:10px">
        
        api 接口函数说明：<br/>
            
    </p>
    <table  class="apitable" >

        <tr >
            <td >接口名称</td>
            <td >接口说明</td>
            <td >参数说明</td>
            <td >调用方式</td>
        </tr>
        
        <tr>
            <td colspan="5">adbug 功能分析</td>
        </tr>
        <tr >
            <td >登录</td>
            <td >用户登录</td>
            <td >account 邮箱 <br/>  pwd 密码  </td>
            <td >/login</td>
        </tr>
        <tr >
            <td >注册</td>
            <td >用户注册</td>
            <td >account 账号; pwd 密码;setpwd 重复密码; username 用户名<br/>

                状态码： code 201 请使用企业邮箱注册 <br/>
     *      202 账号 密码不可为空<br/>
     *      203 两次输入密码不一致<br/>
     *      205 该邮箱已经被注册<br/>
     *      206 用户名已经存在<br/>
            </td>
            <td >/register</td>
        </tr>


        <tr >
            <td >注销</td>
            <td >退出系统 注销</td>
            <td >account 邮箱  </td>
            <td >/destory</td>
        </tr>


        <tr >
            <td >登录状态</td>
            <td >判断用户是否登录 alive 1 登录 0 注销</td>
            <td >account 账号;</td>
            <td >/user/online</td>
        </tr>

        

        <tr >
            <td >邮箱激活</td>
            <td >用户注册后，激活邮箱使用</td>
            <td >account 账号; uid 密码;token: md5($uid.$account) 的md的值</td>
            <td >/valid/email/alive</td>
        </tr>

        <tr >
            <td >重置密码</td>
            <td >用户修改密码</td>
            <td >account 账号; active 激活码;pwd 密码;type 1 获取激活码 2 提交登录</td>
            <td >/reset/pwd</td>
        </tr>

        <tr >
            <td >热门广告(<span style="color:red">***</span>)</td>
            <td >添加或者取消热门广告</td>
            <td >id 创意编号;status 是否热门 1 是 0 不是</td>
            <td >/up/hotad</td>
        </tr>

        <tr >
            <td >热门广告详细页(<span style="color:red">***</span>)</td>
            <td >热门广告详细页</td>
            <td >page 当前页; size 每页显示条数;</td>
            <td >/hotads/list</td>
        </tr>


        <tr >
            <td >首页热门广告(<span style="color:red">***</span>)</td>
            <td >首页展示获取最近3条最热广告</td>
            <td >size 每页显示条数;</td>
            <td >/hot/ads</td>
        </tr>

        <tr >
            <td >首页最新移动广告(<span style="color:red">***</span>)</td>
            <td >首页展示最新广告</td>
            <td >size 每页显示条数;</td>
            <td >/current/ads</td>
        </tr>


        <tr >
            <td >热门域名添加删除(<span style="color:red">***</span>)</td>
            <td >广告主 媒体 跟踪者 添加 删除</td>
            <td >type 1 添加 2 删除 ; wd 对应的域名值</td>
            <td >/hot/roles</td>
        </tr>

        <tr >
            <td >热门域名列表(<span style="color:red">***</span>)</td>
            <td >首页或者详细列表页最热门域名</td>
            <td >type 1 首页 2 详细页列表; size 每页显示数</td>
            <td >/hot/roles/list</td>
        </tr>


        <tr >
            <td >搜索</td>
            <td >根据搜索关键词获取数据</td>
            <td >wd 关键词 urlencode 加密;<br/> page 分页 默认 1 </td>
            <td >/search/ads</td>
        </tr>

        <tr >
            <td >搜索过滤接口</td>
            <td >根据搜索关键词 过滤条件获取数据</td>
            <td >wd 关键词 urlencode 编码
                <br/> width 宽 (最大值)
                <br/> min_width (最小值)
                <br/>  height 高 (最大值)
                <br/>  min_height (最小值)
                <br/>  platform 平台 1 pc ２移动 3 IOS 4 Android 5 Ott
                <br/>  start_time 开始时间 end_time 结束时间 时间查询 时间戳 精确到秒
                <br/>  shape 形状  所有尺寸  1  ， 2 ，3 ...
                <br/>  type 类型 1 图片 2 视频 3 flush 4 html5 5 原生
            </td>
            <td >/search/ads/filter</td>
        </tr>

        <tr >
            <td >相关广告主</td>
            <td >根据搜索关键词 过滤条件获取数据</td>
            <td >wd 关键词 urlencode 编码
                <br/> width 宽 (最大值)
                <br/> min_width (最小值)
                <br/>  height 高 (最大值)
                <br/>  min_height (最小值)
                <br/>  platform 平台 1 pc ２移动 3 IOS 4 Android 5 Ott
                <br/>  start_time 开始时间 end_time 结束时间 时间查询 时间戳 精确到秒
                <br/>  shape 形状  所有尺寸  1  ， 2 ，3 ...
                <br/>  type 类型 1 图片 2 视频 3 flush 4 html5 5 原生
            </td>
            <td >/search/related/subjects</td>
        </tr>


        <tr >
            <td >竞争情报</td>
            <td >根据搜索关键词 获取竞争信息</td>
            <td >wd 关键词 urlencode 编码
                <br/> tab 1 广告主 2 媒体 3 跟踪者 4 营销活动 
            </td>
            <td >/competitive/infos</td>
        </tr>
        

        <tr >
            <td >左侧广告主列表</td>
            <td >获取左侧广告主列表信息</td>
            <td >size 条数</td>
            <td >/adver/left/list</td>
        </tr>
         <tr >
            <td >OTT 广告列表</td>
            <td >获取 ott 信息列表</td>
            <td >size 列表个数 默认 10 条 最大200</td>
            <td >/ott/list</td>
        </tr>

         <tr >
            <td>详情页聚合信息 (<span style="color:red">***</span>) </td>
            <td >获取广告主 媒体 跟踪者具体信息及统计数据</td>
            <td >role 1 广告主 2 媒体 3 跟踪者 4 营销活动; <br/> rv 对应域名或者营销活动对应的md5的值;</td>
            <td >/role/detail</td>
        </tr>

        <tr >
            <td >域名详情页 （<span style="color:red">域名</span>)</td>
            <td >获取广告主 媒体 跟踪者下面点击tab 对应的数据信息</td>
            <td >role 1 广告主 2 媒体 3 跟踪者; <br/> rv 对应域名的值;<br/> tab 1 广告主 2 媒体 3 跟踪者 4 营销活动 5 创意;<br/> irole 点击对应的具体域名角色 1 广告主 2 媒体  3 跟踪者 4 营销活动 5 创意;<br/>ivrole点击对应的具体域名角色 对应的域名</td>
            <td >/role/detail/list</td>
        </tr>

         <tr >
            <td >活动详细（<span style="color:red">营销活动</span>）</td>
            <td >根据营销活动 md5 的值 获取对应的额数据</td>
            <td >md5; 对应的营销活动的md5 的值;<br/> tab 1 广告主 2 媒体 3 跟踪者 4 创意 默认;<br/>irole  点击对应的具体域名角色  1 广告主 2 媒体  3 跟踪者 4 营销活动 5 创意;<br/> ivrole点击对应的具体域名角色 对应的域名</td>
            <td >/subject/detail/list</td>
        </tr>

         <tr >
            <td >左侧广告主域名</td>
            <td >获取左侧广告主对应的创意信息</td>
            <td >role 1 广告主 2 媒体 3 跟踪者; <br/> rv对应的域名值;<br/> page 分页 </td>
            <td >/advertiser/left/search</td>
        </tr>

       
         <tr >
            <td >相关广告主</td>
            <td >根据关键词获取相关广告主信息</td>
            <td >wd 关键词 </td>
            <td >/related/advertiser</td>
        </tr>
         <tr >
            <td >排行榜</td>
            <td >获取排行榜数据</td>
            <td >role 1 媒体 2 广告主 3 跟踪者 4 创意 5 营销活动;<br/> time_type 1 前一天 2 前一周 3 前半月 4 前一月 </td>
            <td >/rank/top/list</td>
        </tr>
        <tr >
            <td >搜索框查询</td>
            <td >搜索框调用接口</td>
            <td >host 关键词;<br/> size 条数</td>
            <td >/query/list</td>
        </tr>
        <tr >
            <td >广告详细</td>
            <td >获取创意具体信息</td>
            <td >id 创意编号</td>
            <td >/ad/detail</td>
        </tr>
        <tr >
            <td >相关创意</td>
            <td >获取和当前创意相关的广告信息</td>
            <td >id 当前创意编号; host 当前创意对应的广告主域名</td>
            <td >/related/ads</td>
        </tr>
        
    </table>
  </div>
@endsection