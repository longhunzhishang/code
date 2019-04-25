@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到 getAppInfo (给定的App 列表) 测试页面 (已测试)</H2>

	<p>功能 说明 ：获取App 详细  信息 每月定期导出Api</p>
   
    <p>请求方式：<small>get</small>
    请求示例 ：  http://testapi.adbug.cn/domain/app/info?start_time=1483228800000&end_time=1485878399000
    </p>
   
    <p>请求参数说明：<br/>
     start_time 开始时间 精确到 毫秒 <br/>
     end_time 开始时间 精确到 毫秒
     </small></p>

     <p>
     	
     	App 列表 ： （完全匹配）"腾讯新闻","腾讯视频","今日头条",
            "爱奇艺视频","天天快报","优酷视频",
            "网易新闻","新浪新闻","快手",
            "搜狐新闻","乐视视频","一点资讯","Flipboard 中国版","搜狐视频","凤凰新闻","暴风影音","土豆视频","百度视频","百度新闻","央视影音","ZAKER","畅读","爱奇艺PPS影音","新浪财经",
            "哔哩哔哩动画","东方财富网","美拍","YY","国务院","秒拍","风行视频","澎湃新闻","球探体育比分","人民日报"<br/>
            (存疑匹配)：'芒果TV'=>'芒果tv',
            '斗鱼'=>'斗鱼TV',//air.tv.douyu.android 斗鱼TV
            '影视大全'=>'影视大全(com.le123)'//com.cinema2345 影视大全(com.le123)<br/>
            (暂无匹配)：'浙江新闻','懂球帝','韩剧TV','冲浪快讯','咪咕视频','Whats New','直播吧','和讯外汇','视吧',
            '花椒直播','虎牙直播','虎扑体育','唔哩','新浪体育','国务院','军事头条','头条视频','百度好看',
            '聚力视频','映客直播','UC头条','乐视体育','腾讯体育','小米视频'
     </p>

    <p>状态码说明：
    <small style="display:block"> 
   
    </small>
    </p>

     <p>
    返回结果说明：
    <p>
    <p>
       
        数据案例：<br/>'query_name','adbug_name','广告创意数','广告主数','营销活动数','top50广告主','广告主链接',
                'top50创意名称','创意链接','创意平台','创意类型','top50营销活动名称','营销活动链接'
    </p>

    <p style="margin-top:10px">
    	
    	api 接口函数说明：<br/>
    		
    </p>
    <table  class="apitable" >
        <tr >
            <td >api 名称</td>
            <td >简介</td>
            <td >请求方式</td>
            <td >参数</td>
            <td >返回数据</td>
        </tr>
    	
    	<tr >
    		<td >getAppInfo</td>
    		<td >获取 app 信息</td>
    		<td >http://<?php echo $_SERVER['SERVER_NAME']; ?>/domain/app/info</td>
            <td >start_time ent_time</td>
            <td > excel 表格</td>
    	</tr>

    </table>
  </div>
@endsection