
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到 华治数聚 getAll 接口页面</H2>

    <p>功能说明 ：根据 编号 获取信息</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：<small style="display:block">size 显示数据个数 默认10 条数据 </small></p>

    <p>请求参数说明：<small style="display:block">目前只支持 wd(关键词 必须)  size(每页显示条数) page(页数) 三个参数 token(认证参数 必须 ee01b26cda93612da682b7b703a62217 )</small></p>

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
    返回结果说明：<small>data 数据; status 状态码;</small>

    <p>
    <p>返回格式：{"data":[],"status":206}</p>

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
            <td >getAll </td>
            <td >根据 编号 获取 addata 信息</td>
            <td >http://resource.adbug.cn/api/v4/addata/all/search</td>
            <td >size 每页显示条数 不超过200 page 显示页数 wd 搜索参数 比如: bmw</td>
            <td > data 数据 status 状态码</td>
        </tr>

    </table>

  </div>
@endsection