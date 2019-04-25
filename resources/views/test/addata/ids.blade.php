
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到addata getAllByIds 测试页面</H2>

    <p>功能说明 ：根据 编号 获取 addata 信息</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：<small style="display:block">sort 排序 默认 desc;<br/> ids : addata编号数组 格式：  [2,3,4,5] </small></p>

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
            <td >getAllByIds </td>
            <td >根据 编号 获取 addata 信息</td>
            <td >http://localhost/api/v1/addata/find/ids</td>
            <td >sort  排序 size 显示数目</td>
            <td > data 数据 status 状态码</td>
        </tr>

    </table>

    <p style="margin-top:10px;">测试 getAllById</p>
    <form action="{{url('addata/test/find/ids')}}" method="get">
        <table>
            id1 <input type="text" name="ids[]">
            id2 <input type="text" name="ids[]">
            id3 <input type="text" name="ids[]">
        </table>
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>

  </div>
@endsection