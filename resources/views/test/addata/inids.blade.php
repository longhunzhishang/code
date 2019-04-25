
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

</style>
  <div>
    <H2>欢迎来到 getInfoInIds 测试页面(已测试)</H2>

    <p>功能 说明 ：获取 addata between ids 所有信息</p>
   
    <p>请求方式：<small>get</small></p>

    <p>请求地址：<small>http://localhost/api/v1/addata/between/ids</small></p>
   
    <p>请求参数说明：<small style="display:block">
    ids 请求内容;addata 编号 <br/>格式为: $ids = '1，2，3，4，5，6，7'
    </small></p>


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
    返回结果说明：<small>data 数据; status 状态码;total 总条数
    <br/>{"status":200,"data":[],"total":"xxx"}

    </small>
    <p>
    <p>
        数据案例：<br/>
        {
            "data":[<br/>
                {
                    "advertiser":"taobao",
                    "trackers":"taobao",
                    "md5":"xxx",
                    "id":"xxxx",
                    "xxxx":"xxxx",
                    ......
                }
                <br/>
                {
                    "advertiser":"taobao",
                    "trackers":"taobao",
                    "md5":"xxx",
                    "id":"xxxx",
                    "xxxx":"xxxx",
                    .....
                }
                <br/>
            ],
            <br/>"total":"xxxxx",
            <br/>"status":"xxxxx"
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
            <td>测试地址</td>
        </tr>
        
        <tr >
            <td >getInfoInIds </td>
            <td >获取 addata 信息</td>
            <td >http://localhost/api/v1/addata/between/ids</td>
            <td >ids</td>
            <td > data 数据 status 状态吗</td>
            <td>http://localhost/addata/test/between/ids</td>
        </tr>

    </table>

    <p style="margin-top:10px;">测试 getInfoInIds </p>
    <form action="{{url('addata/test/between/ids')}}" method="get">
        
        <table class="table_test">
            
            <tr>
                <td> ids <input type="text" name="ids"></td>
            </tr>
           
        </table>
        
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>


  </div>
@endsection