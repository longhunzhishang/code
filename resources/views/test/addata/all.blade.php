
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到addata all 测试页面</H2>

	<p>功能 说明 ：获取 addata 所有信息</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：<small>sort 排序 默认 desc; size 显示数据个数 默认10 条数据 ;type 0 默认 1 简化字段</small></p>

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
    返回结果说明：<small>data 数据; status 状态码;
    <br/>{"status":203,"data":[]}

    </small>
    <p>
    <p>
        简化数据案例：<br/>
        {
            "data":[
                {
                    "advertiser":"taobao",
                    "trackers":"taobao",
                    "thumb_url":"xxxx",
                    "shape":"2",
                    "target_url":"xxxxx",
                    "type":"xxx",
                    "title":"xxxx",
                    "platform":"xxx",
                    "tags":"xxxxx",
                    "original_url":"xx",
                    "domain":"xxxx",
                    "publisher":"xxxx",
                    "md5":"xxx",
                    "id":"xxxx"
                }
            ],
            "total":"xxxxx",
            "status":"xxxxx"
        }
        <br/>

        未简化数据案例：<br/>
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
            <td>测试地址</td>
        </tr>
    	
    	<tr >
    		<td >getAll</td>
    		<td >获取所有 addata 信息</td>
    		<td >http://localhost/api/v1/addata/all</td>
            <td >sort  排序 size 显示数目</td>
            <td > data 数据 status 状态吗</td>
            <td>http://localhost/addata/test/all</td>
    	</tr>

    </table>

    <p style="margin-top:10px;">测试 getAllTest</p>
    <form action="{{url('addata/test/all')}}" method="get">
        <table>
            sort <input type="text" name="sort">

            size <input type="text" name="size">
            type <input type="number" name="type">
        </table>
        <input style="margin-left:100px;margin-top:10px" type="submit" >
    </form>


  </div>
@endsection