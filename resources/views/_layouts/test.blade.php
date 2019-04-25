
<!DOCTYPE html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 上述3个meta标签*必须*放在最前面，任何其他内容都*必须*跟随其后！ -->
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Api test文档</title>
     <link href="/api/dashboard.css" rel="stylesheet">
    <!-- Bootstrap core CSS -->
    <link href="//cdn.bootcss.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
 <style type="text/css">
   .active{
    background: #ccc;
   }

 </style>
  </head>
  <?php
  
  function active($path)
  {
     $url = $_SERVER['REQUEST_URI'];
      if(stristr($url,$path)){
          return 'active';
      }
      return '';
  }
  ?>
  <body>
 
<nav class="navbar navbar-default" role="navigation">
    <div class="navbar-header">
        <a class="navbar-brand" href="#">接口Api</a>
    </div>
    <div>
      
    </div>
</nav>
    <div class="container">
      <div class="row" >
        <div class="col-md-2 sidebar">
          <h4>华治数聚接口说明</h4>
          <ul class="nav nav-sidebar">
            <li class="<?php echo active('huazhi/data'); ?>"><a href="{{URL('huazhi/data')}}">getAll</a></li>
           
          </ul>
          <h4>特殊接口说明</h4>
          <ul class="nav nav-sidebar">
            <li class="<?php echo active('app/publisher/domain/test'); ?>"><a href="{{URL('app/publisher/domain/test')}}">40 App 媒体接口</a></li>
            <li class="<?php echo active('domain/app/info/test'); ?>" ><a href="{{URL('domain/app/info/test')}}">获取App详细</a></li>
           
          </ul>
           <h4>正式接口说明</h4>
          <ul class="nav nav-sidebar">
            <li ><a href="">接口详细</a></li>
          </ul>
           <h3>已下为测试接口</h3>
           <h4>addata</h4>
          <ul class="nav nav-sidebar">
            <li class="<?php echo active('addata/all'); ?>"><a href="{{URL('addata/all')}}">getAll</a></li>
            <li class="<?php echo active('addata/filter'); ?>" ><a href="{{URL('addata/filter')}}">getAllFilter</a></li>
            <li class="<?php echo active('addata/find/id'); ?>" ><a href="{{URL('addata/find/id')}}">getAllById</a></li>
            <li class="<?php echo active('addata/ids'); ?>" ><a href="{{URL('addata/ids')}}">getAllByIds</a></li>
            <li class="<?php echo active('addata/title'); ?>" ><a href="{{URL('addata/title')}}">getInfoByTitle</a></li>
           <!--  <li class="<?php echo active('addata/count'); ?>" ><a href="{{URL('addata/count')}}">getAdsCount</a></li> -->

            <li class="<?php echo active('addata/sub'); ?>" ><a href="{{URL('addata/sub')}}">getAllSubFilter</a></li>
            <li class="<?php echo active('addata/between/ids'); ?>" ><a href="{{URL('addata/between/ids')}}">getInfoInIds</a></li>
            <li class="<?php echo active('addata/ads'); ?>" ><a href="{{URL('addata/ads')}}">getAllSubAds</a></li>
            <li class="<?php echo active('addata/search'); ?>" ><a href="{{URL('addata/search')}}">getAllFilterSearch</a></li>
            <li class="<?php echo active('addata/maxormin'); ?>" ><a href="{{URL('addata/maxormin')}}">getMaxMinfield</a></li>
            

            
          </ul>
         
           
        </div>
        <div class="col-sm-9 ">

        @yield('content')
        </div>
      </div>
    </div>

    <script src="//cdn.bootcss.com/jquery/1.11.3/jquery.min.js"></script>
    <script src="//cdn.bootcss.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script type="text/javascript">
    $(function(){
      $('.TestButton').click(function(){

        var TAKEN = $('meta[name="csrf-token"]').attr('content');

      
        $.ajax({
        type:'post',
        url:"articleorselect",
        data:{
          dataselect:[{id:1,articlepart:1,articleall:1},
          {id:2,articlepart:0,articleall:1},
          {id:3,articlepart:1,articleall:0}],
          
          token:'575064eb0dcdd9ed8764186d5524781c'
          
        
        },
        success:function(data){
         //alert(1);
        
        },
        dataType: "json"
      });

      })
    })
    </script>
  </body>
</html>

