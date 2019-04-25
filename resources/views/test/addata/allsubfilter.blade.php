
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
    <H2>欢迎来到 getAllSubFilter 测试页面</H2>

	<p>功能 说明 ：获取 addata 所有信息</p>
   
    <p>请求方式：<small>get</small></p>

    <p>请求地址：<small>http://localhost/api/v1/addata/all/sub</small></p>
   
    <p>请求参数说明：<small style="display:block">
    wd 请求 内容;<br/>
    sort 排序 默认 desc; <br/>
    size 显示数据个数 默认10 条数据 ;<br/>
    type 文件类型 1 图片 2 flush 3 视频<br/> 
    platform 平台 1 pc 2 移动; 默认所有平台信息</small></p>


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
                <br/>
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
    		<td >getAllSubFilter</td>
    		<td >获取所有 addata 信息</td>
    		<td >http://localhost/api/v1/addata/all/sub</td>
            <td >wd 请求 内容(必须);<br/>sort  排序 ;<br/>size 显示数目;<br/>platform 平台</td>
            <td > data 数据 status 状态吗</td>
            <td>http://localhost/addata/test/all/sub</td>
    	</tr>

    </table>

    <p style="margin-top:10px;">测试 getAllSubFilter</p>
    <form action="{{url('addata/test/all/sub')}}" method="get">
        
        <table class="table_test">
            
            <tr>
                <td> wd <input type="text" name="wd"></td>
                <td> sort <input type="text" name="sort"></td>
            </tr>
             <tr>
                <td>  type <input type="number" name="type"></td>
                <td>platform <input type="number" name="platform"></td>
            </tr>
            <tr>
                <td colspan="2">size <input type="text" name="size"></td>
            </tr>
        </table>
        
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>


  </div>
@endsection