
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到 domain host 测试页面</H2>

    <p>功能说明 ：根据 编号 获取 domain 信息 （fetch_domain_ads）</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：
    <small style="display:block">sort 排序 默认 desc;(内容范围 : DESC　ASC  desc asc)<br/> 
    size 显示数据个数 默认10 条数据 <br/>
    host 待搜索的域名 不可为空<br/>
    </small>
    </p>

    <p>状态码说明：
    <small style="display:block"> 
    200 请求成功<br/>
    203 暂无数据<br/>
    206 参数格式不对<br/>
    207 搜索参数不可为空,<br/>
    208 参数超出范围
    </small>
    </p>

    <p>
    返回结果说明：<small>data 数据; status 状态码;total 总条数
    <br/>{"status":203,"data":[],"total":10000}

    </small>
    <p>
    <p>
       
        数据案例：<br/>
        {
            "data":[
                {
                    "所有字段":"xxxx"
                },
                {
                    "xxxx":"xxxx"
                }
            ],
            "total":"xxxxx",
            "status":"xxxxx"
        }

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
            <td >getInfoByHost </td>
            <td >根据 编号 获取 domain 信息</td>
            <td >http://localhost/api/v1/domain/host</td>
            <td >sort  排序 size 显示数目</td>
            <td > data 数据 status 状态码</td>
        </tr>

    </table>

    <p style="margin-top:10px;">测试 getInfoByHost</p>
    <form action="{{url('domain/test/host')}}" method="get">
        <table>
            sort <input type="text" name="sort">
            size <input type="text" name="size">
            host <input type="text" name="host">
            
        </table>
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>
  </div>
@endsection