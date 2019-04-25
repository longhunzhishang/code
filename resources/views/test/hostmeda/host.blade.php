
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
    .table_test{
        width: 100%;
        border:1px #ccc solid;
    }
    .table_test tr td{
        padding: 5px;
    }
    small{
        display: block;
    }
</style>
  <div>
    <H2>欢迎来到 host_meda getInfoByHost 测试页面</H2>

    <p>功能 说明 ：根据host 获取 host_meda 信息</p>
   
    <p>请求方式：<small>get</small></p>
    <p>请求地址 : <small>http://localhost/api/v1/hostmeda/find/host</small></p>
   
    <p>请求参数说明：
    <small style="display:block">host 请求参数 
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
            <td >getInfoByHost</td>
            <td >根据条件过滤 获取 host_meda 信息</td>
            <td >http://localhost/api/v1/hostmeda/find/host</td>
            <td >host</td>
            <td > data 数据 status 状态吗</td>
        </tr>

    </table>

    <p style="margin-top:10px;">测试 getInfoByHost </p>
    <form action="{{url('hostmeda/test/find/host')}}" method="get">
        <table class="table_test">
            
            <tr>
                <td> host <input type="text" name="host"></td>
            </tr>
           
        </table>

        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>
  </div>
@endsection