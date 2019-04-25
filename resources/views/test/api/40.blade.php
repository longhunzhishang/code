@extends('_layouts.test')

@section('content')
<style type="text/css">
    .apitable{width:100% ; border:1px #ccc solid;}
    .apitable{border:1px #ccc solid;}
    .apitable tr td{padding:4px; border:1px #ccc solid;}
</style>
  <div>
    <H2>欢迎来到 移动 app 40 媒体 测试页面 (已测试)</H2>

	<p>功能 说明 ：获取 移动 app 40 媒体 信息</p>
   
    <p>请求方式：<small>get</small>

    请求示例： http://testapi.adbug.cn/app/publisher/domain?start_time=1483228800000&end_time=1485878399000

    </p>
   
    <p>请求参数说明：
     start_time 开始时间 精确到 毫秒 <br/>
     end_time 开始时间 精确到 毫秒
     </small>
     <br/>
     App 信息 : 今日头条  com.ss.android.article.news 北京字节跳动科技有限公司 <br/>
腾讯新闻    com.tencent.news    腾讯科技（深圳）有限公司 <br/>
腾讯视频    com.tencent.qqlive  腾讯科技（深圳）有限公司 <br/>
爱奇艺视频   com.qiyi.video  北京爱奇艺科技有限公司 <br/>
优酷视频    com.youku.phone 合一信息技术（北京）有限公司 <br/>
网易新闻    com.netease.newsreader.activity 网之易信息技术（北京）有限公司 <br/>
快手  com.smile.gifmaker  北京一笑科技发展有限公司 <br/>
乐视视频    com.letv.android.client 乐视网信息技术(北京)股份有限公司 <br/>
天天快报    com.tencent.reading 腾讯科技（深圳）有限公司 <br/>
芒果TV    com.hunantv.imgo.activity   湖南快乐阳光互动娱乐传媒有限公司 <br/>
搜狐新闻    com.sohu.newsclient 北京搜狐新媒体信息技术有限公司 <br/>
新浪新闻    com.sina.news   北京新浪互联信息服务有限公司 <br/>
搜狐视频    com.sohu.sohuvideo  飞狐信息技术（天津）有限公司 <br/>
暴风影音    com.storm.smart 北京暴风科技股份有限公司 <br/>
哔哩哔哩动画  tv.danmaku.bili 上海幻电信息科技有限公司 <br/>
凤凰新闻    com.ifeng.news2 北京天盈九州网络技术有限公司 <br/>
爱奇艺PPS影音    tv.pps.mobile   上海众源网络有限公司 <br/>
小米视频        北京小米科技有限责任公司 <br/>
一点资讯    com.hipu.yidian 北京一点网聚科技有限公司 <br/>
聚力视频    COM.PPLIVE.ANDROIDPHONE 上海聚力传媒技术有限公司 <br/>
360影视大全 com.qihoo.video 北京奇虎科技有限公司 <br/>
头条视频        北京字节跳动科技有限公司 <br/>
土豆视频    com.tudou.android   上海全土豆文化传播有限公司 <br/>
百度视频    com.baidu.video 百度在线网络技术（北京）有限公司 <br/>
东方财富网   com.eastmoney.android.berlin    东方财富信息股份有限公司 <br/>
美拍  com.meitu.meipaimv  厦门美图网科技有限公司 <br/>
映客直播        北京蜜莱坞网络科技有限公司 <br/>
YY  com.duowan.mobile   欢聚时代科技（北京）有限公司 <br/>
百度新闻    com.baidu.news  百度在线网络技术（北京）有限公司 <br/>
腾讯体育        腾讯科技（深圳）有限公司 <br/>
秒拍  com.yixia.videoeditor   炫一下科技有限公司 <br/>
ZAKER   com.myzaker.zaker   北京扎客网络科技有限公司 <br/>
懂球帝     北京多格科技有限公司 <br/>
军事头条    com.tiexue.mobile.topnews   北京铁血科技有限责任公司 <br/>
Flipboard 中国版   flipboard.cn    Flipboard <br/>
畅读  viva.reader 北京维旺明信息技术有限公司 <br/>
UC头条    com.uc.infoflow 优视网络 <br/>
百度好看    COM.HAOKANHAOKAN.NEWS   百度在线网络技术（北京）有限公司 <br/>
乐视体育        乐视体育文化产业发展（北京）有限公司 <br/>
新浪财经    cn.com.sina.finance 新浪网技术（中国）有限公司
     </p>

    <p>状态码说明：
    <small style="display:block"> 
   
    </small>
    </p>
     <p>
    返回结果说明：
    <p>
    <p>
       
        数据案例：<br/>'App','域名','公司','月活跃MAU(万)','MAU同比增长率','总使用时长(万分钟)','高价值用户比例','链接地址','广告创意数'
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
    		<td >exportDomainApp</td>
    		<td >获取40 媒体 app 信息</td>
    		<td >http://<?php echo $_SERVER['SERVER_NAME']; ?>/app/publisher/domain</td>
            <td >start_time ent_time</td>
            <td > excel 表格</td>
    	</tr>

    </table>

  </div>
@endsection