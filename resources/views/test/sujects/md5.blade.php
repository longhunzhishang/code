
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到 sujects getInfoByMd5 测试页面</H2>

	<p>功能 说明 ：获取 sujects 所有信息</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：<small>param 参数 md5 格式; subjects md5 字段 </small></p>

    <p>状态码说明：
    <small style="display:block"> 
    200 请求成功<br/>
    203 暂无数据<br/>
    204 查询条件错乱(date start_time 或者 date end_time 不可同时存在)<br/>
    205 开始时间不可大于结束时间<br/>
    206 参数格式不对<br/>
    207 参数不可为空,<br/>
    208 参数超出范围
    </small>
    </p>

     <p>
    返回结果说明：<small>data 数据; status 状态码;
    <br/>{"status":203,"data":[]}

    </small>
    <p>
    <p>
       
        数据案例：<br/>
        {
            "data":
                {
                    "所有字段":"xxxx"
                },
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
    		<td >getInfoByMd5</td>
    		<td >获取所有 sujects 信息</td>
    		<td >http://localhost/api/v1/subject/md5</td>
            <td ></td>
            <td > </td>
    	</tr>

    </table>

     <p style="margin-top:10px;">测试 getInfoByMd5</p>
    <form action="{{url('subject/test/md5')}}" method="get">
        <table>
            param <input type="text" name="param">
            
        </table>
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>

  </div>
@endsection