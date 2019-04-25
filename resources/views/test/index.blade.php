
@extends('_layouts.test')

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
    		<td >数据表</td>
            <td >返回数据</td>
            <td >返回参数说明</td>
    	</tr>
    	
    	<tr>
        <td colspan="5">adbug 功能分析</td>
         </tr>
    	<tr >
    		<td >首页搜索</td>
    		<td >1 根据输入内容 获取 域名 信息 ; 2 进行拼接 返回数据</td>
    		<td >domain 表</td>
            <td >role cname ename host md5 </td>
            <td > role 1 媒体 2 广告主 3 追踪者 [xxxx -媒体 - md5] ; 0 [xxxx - md5]</td>
    	</tr>

        <tr >
            <td >点击所有</td>
            <td >根据 搜索内容 1 获取广告 2 获取 广告主 3 获取 媒体 获取 追踪者</td>
            <td >addata , domain 表</td>
            <td >* </td>
            <td ></td>
        </tr>
         <tr >
            <td >点击所有 过滤</td>
            <td >根据 搜索内容 过滤条件 ajax 请求数据 </td>
            <td >addata , domain 表</td>
            <td >* </td>
            <td ></td>
        </tr>

         <tr >
            <td >广告创意</td>
            <td >根据 搜索内容  获取广告 </td>
            <td >addata , domain 表</td>
            <td >* </td>
            <td ></td>
        </tr>
         <tr >
            <td >广告创意 过滤</td>
            <td >根据 搜索内容 过滤条件 ajax 请求数据 </td>
            <td >addata </td>
            <td >* </td>
            <td ></td>
        </tr>

        <tr >
            <td >营销活动</td>
            <td >根据 搜索内容  获取广告 </td>
            <td >addata , domain 表</td>
            <td >* </td>
            <td ></td>
        </tr>
         <tr >
            <td >营销活动 过滤</td>
            <td >根据 搜索内容 过滤条件 ajax 请求数据 </td>
            <td >addata </td>
            <td >* </td>
            <td ></td>
        </tr>
         <tr >
            <td >营销活动 详情</td>
            <td >根据 搜索内容 广告主 创意 媒体  数量统计; 表格显示 </td>
            <td >addata </td>
            <td >* </td>
            <td ></td>
        </tr>

        <tr >
            <td >广告主 媒体 追踪者 index</td>
            <td >根据 搜索内容 返回 列表 </td>
            <td >addata </td>
            <td >* </td>
            <td ></td>
        </tr>


    </table>
  </div>
@endsection