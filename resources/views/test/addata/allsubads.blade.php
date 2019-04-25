
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
    <H2>欢迎来到 getAllSubAds 测试页面(已测试)</H2>

    <p>功能 说明 ：
    <small>
          广告创意 过滤 条件 获取 ads 数据
    </small>
    </p>
   
    <p>请求方式：<small>get</small></p>

    <p>请求地址：<small>http://localhost/api/v1/addata/filter/ads</small></p>
   
    <p>请求参数说明：<small style="display:block">
    param 搜索内容<br/>
    type 1 图片 2 flush 3 视频 <br/>shape 形状<br/>
    start_time 最小时间 <br/>end_time 最大时间<br/>
    platform 平台 <br/>sortmode 1 按照时间排序 2 相关性排序<br/>
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
    返回结果说明：<small>data 数据; status 状态码;total 总条数;last_id 当前最大 编号;page 当前页;size 每页显示次数 ; 共 all_page 页
    <br/>{"status":200,"data":[],"total":"xxx","page":"xxx","last_id":"xxx","all_page":"xxx","size":"xxx"}

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
            <br/>"page":"xxxxx"
            <br/>"last_id":"xxxxx"
            <br/>"all_page":"xxxxx"
            <br/>"size":"xxxxx"
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
            <td >getAllSubAds </td>
            <td >获取 addata 信息</td>
            <td >http://localhost/api/v1/addata/filter/ads</td>
            <td >ids</td>
            <td > data 数据 status 状态吗</td>
            <td>http://localhost/addata/test/filter/ads</td>
        </tr>

    </table>

    <p style="margin-top:10px;">测试 getAllSubAds </p>
    <form action="{{url('addata/test/ads')}}" method="get">
        
        <table class="table_test">
            
            <tr >
                <td colspan="2"> param <input type="text" name="param"></td>
                
            </tr>
            <tr>
                <td> shape <input type="text" name="shape"></td>
                <td> platform <input type="text" name="platform"></td>
            </tr>

             <tr>
                <td> start_time <input type="text" name="start_time"></td>
                <td> end_time <input type="text" name="end_time"></td>
            </tr>

            <tr>
                <td> sortmode <input type="text" name="sortmode"></td>
                <td> type <input type="text" name="type"></td>
            </tr>
            <tr>
                <td> page <input type="text" name="page"></td>
                <td> size <input type="text" name="size"></td>
            </tr>
           
        </table>
        
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>


  </div>
@endsection