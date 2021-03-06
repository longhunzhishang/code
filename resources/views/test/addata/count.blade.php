
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到addata getAdsCount 测试页面 （已测试）</H2>

    <p>功能说明 ：根据 域名 统计 追踪者 广告主 媒体 等数量</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：<small style="display:block">host 域名; type 1 trackers  2 advertiser 3 publisher </small></p>

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
            <td >getAdsCount </td>
            <td >根据 编号 获取 addata 信息</td>
            <td >http://localhost/api/v1/addata/find/count</td>
            <td > </td>
            <td > data  status </td>
        </tr>

    </table>

    <p style="margin-top:10px;">测试 getAdsCount</p>
    <form action="{{url('addata/test/count')}}" method="get">
        <table>
            host <input type="text" name="host">
            type <input type="text" name="type">
        </table>
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>

  </div>
@endsection