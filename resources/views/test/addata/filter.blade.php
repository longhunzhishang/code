
@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到addata getAllFilter 测试页面</H2>

    <p>功能 说明 ：根据栓选条件 获取 addata 信息</p>
   
    <p>请求方式：<small>get</small></p>
   
    <p>请求参数说明：
    <small style="display:block">sort 排序 默认 desc;<br/> size 显示数据个数 默认10 条数据 <br/>
    param 搜索参数; page 当前页 默认为 1 ;<br/>platform 平台 1 pc 2 移动;<br/>
    date 1 一年内 2 一个月 3 一周内，4 一天;shape 尺寸;<br/> type 类型 查询 1 image 2 图片 3 视频<br/>
    start_time end_time 自定义 开始时间 结束时间  例如： 2016-07-06
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
    205 开始时间不可大于结束时间<br/>
    206 参数格式不对<br/>
    207 搜索参数不可为空,<br/>
    208 参数超出范围
    </small>
    </p>

    <p>
    返回结果说明：<small>data 数据; status 状态码;total 返回总条数</small>
    <p>

    <p>
        简化数据案例：<br/>
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
            <td >getAllFilter</td>
            <td >根据条件过滤 获取 addata 信息</td>
            <td >http://localhost/api/v1/addata/all/filter</td>
            <td >看上面介绍</td>
            <td > data 数据 status 状态吗</td>
        </tr>

    </table>


    <p style="margin-top:10px;">测试 getAllFilter</p>
    <form action="{{url('addata/test/filer')}}" method="get">
        <table>
            <tr>
                <td>
                    param
                </td>
                
                <td>
                    <input type="text" name="param">
                </td>
                 <td>
                    sort
                </td>
                
                <td>
                    <select name="sort">
                        <option value="desc">desc</option>
                        <option value="desc">asc</option>
                    </select>
                </td>
            </tr>
             <tr>
                <td>
                    page
                </td>
                
                <td>
                    <input type="text" name="page">
                </td>
                <td>
                    size
                </td>
                
                <td>
                    <input type="text" name="size">
                </td>
            </tr>
             <tr>
                <td>
                    date
                </td>
                
                <td>
                    <select name="date">
                        <option value="0"></option>
                        <option value="1">一年内</option>
                        <option value="2">一个月内</option>
                        <option value="3">一周内</option>
                        <option value="4">一天内</option>
                    </select>
                </td>
                <td>
                    platform
                </td>
                
                <td>
                     <select name="platform">
                        <option value="1">pc</option>
                        <option value="2">移动</option>
                    </select>
                </td>
            </tr>
             <tr>
                <td>
                    type
                </td>
                
                <td>
                     <select name="type">
                        <option value="1">图片</option>
                        <option value="2">动画</option>
                        <option value="3">flush</option>
                    </select>
                </td>
                <td>
                    shape
                </td>
                
                <td>
                    <input type="text" name="shape">
                </td>
            </tr>
               <tr>
                <td>
                    start_time
                </td>
                
                <td>
                    <input type="datetime" name="start_time">
                </td>
                 <td>
                    end_time
                </td>
                
                <td>
                    <input type="datetime" name="end_time" format="Y-M-d">
                </td>
            </tr>
            
            
        </table>
        <input style="margin-left:100px;margin-top:10px" type="submit">
    </form>
  </div>
@endsection