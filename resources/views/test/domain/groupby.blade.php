
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到 domain groupBy  测试页面</H2>

    <p>功能 说明 ：根据栓选条件 获取 domain 信息</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：
    <small style="display:block">sort 排序 默认 desc;<br/> size 显示数据个数 默认10 条数据 <br/>
    param 搜索参数; page 当前页 默认为 1 ;<br/>platform 平台 1 pc 2 移动;<br/>
    date 1 一年内 2 一个月 3 一周内，4 一天;shape 尺寸;<br/> type 类型 查询 1 image 2 图片 3 视频<br/>
    start_time end_time 自定义 开始时间 结束时间
    </small>
    </p>
    <p>
        注意： date 和 自定义时间 只能二选一
    </p>

    <p>状态码说明：
    <small style="display:block"> 
    200 请求成功<br/>
    203 暂无数据<br/>
    204 查询条件错乱(date start_time 或者 date end_time 不可同时存在)<br/>
    205 开始时间不可大于结束时间
    </small>
    </p>

    <p>
    返回结果说明：<small>data 数据; status 状态码;</small>
    <p>

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
            <td >getAllFilter</td>
            <td >根据条件过滤 获取 addata 信息</td>
            <td >http://localhost/api/v1/addata/all/filter</td>
            <td >size,sort,param,platfrom,date,shape,type,start_time,end_time</td>
            <td > data 数据 status 状态吗</td>
        </tr>

    </table>
  </div>
@endsection