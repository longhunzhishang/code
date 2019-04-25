// adbug.js
(function($) {
    $.adbug = {
        page_num: 1,
        next_page: 2,
        total_pages: 1,
        key_word: null,
        size: '',
        type:'',
        load: function(args) {
          var self = this;
          $.extend($.adbug, args);
          $.adbug.filter.init();
          // this.go_top();
          // this.more();
          // this.search_op();
          this.autocomplete();
          // if(($.browser.msie == true) && ($.browser.version == 6.0)){
          // }else{
          //   $('.s_h').followDIV();
          // }
        },

        loadPage: function(pages) {
          var self = this;
          if (self.next_page <= self.total_pages) {
            var size = this.size, type = this.type;
            if(size !=="" && type !== ""){
              var a = "?wd=" + this.key_word + "&pages=" + this.next_page + "&ajax=1&size="+size+"&type="+type;
            }else if(size !==""){
              var a = "?wd=" + this.key_word + "&pages=" + this.next_page + "&ajax=1&size="+size;
            }else if(type !== ""){
              var a = "?wd=" + this.key_word + "&pages=" + this.next_page + "&ajax=1&type="+type;
            }else{
              var a = "?wd=" + this.key_word + "&pages=" + this.next_page + "&ajax=1";
            }
            $.ajax({
              url: a,
              beforeSend: function() {

              },
              success: function(d) {
                var c = $(d);

                $("#all").append(c).masonry('appended', c, true);
                $.adbug.layout.render();
                self.next_page++;
              }
            })
          }
        },
        search_op: function(){

          var nav = $('#nav'), nav_a = nav.find('a');
          nav_a.click(function(event) {
            var action = $(this).attr('action');
            $('.s_search').attr('action', action);
            $(this).addClass('cur').siblings().removeClass('cur');
            var s_val = $('.s_ipt').val();
            if(s_val !=="") {
              $(this).attr('href', action+'?wd='+s_val);
            }else{
              return false;
            }
          });
        },
        go_top: function() {
          var settings = {
            min: parseInt(300, 10),
            fade_in: 600,
            fade_out: 400,
            speed: parseInt(1100, 10),
            margin: "20"
          };
          var $tools = $('<div class="ui-tools"></div>'), $gotop = $('<div class="tools-gotop"></div>');//, $feedback = $('<div class="tools-feedback"></div>'), $feedback_btn = $('<a href="http://e.weibo.com/adbugmedia" target="_blank" class="feedback-btn"><span class="item-txt">意见反馈</span></a>'), $weixin_tools = $('<div class="tools-weixin"></div>'), $weixin_btn = $('<a href="#" class="weixin_btn"><span class="item-txt">微信搜索</span><div class="weixin"><div class="arrow"></div></div></a>');
          var $qq_a = $('<a target="_blank" href="http://shang.qq.com/wpa/qunwpa?idkey=bbae8ef6ed0947ffb135603a578efb4deb2b6b14223a74f2dfe076aec89fe9f6"><span class="item-txt">QQ群</span></a><a target="_blank" href="https://t.me/joinchat/HU5ARxL1EtbJ07XstXRzHg"><span class="item-txt">Telegram群</span></a>'), $qq = $('<div class="tools-qq"></div>');
          var $toTop = $('<a href=\"#\" id=\"scrolltop\" title="回到顶部"></a>').html('<span><i class="iconfont">&#xe806;</i></span>');



          $tools.append($gotop.hide().appendTo($tools)).appendTo('body');
          $qq.append($qq_a).appendTo($tools);
          $toTop.appendTo($gotop).click(function() {
            $('html, body').stop().animate({
              scrollTop: 0
            }, settings.speed);
            return false;
          });
          $(window).scroll(function() {
            var sd = jQuery(window).scrollTop();
            if (typeof document.body.style.maxHeight === "undefined") {
              $toTop.css({
                'position': 'absolute',
                'top': sd + $(window).height() - settings.margin
              });
            }
            if (sd > settings.min) {
              $gotop.fadeIn(settings.fade_in);
            } else {
              $gotop.fadeOut(settings.fade_out);
            }
          });
        },
        more: function() {
          var self = this,
            w = $(window);
          w.scroll(function() {
            var d = w.scrollTop(),
              c = $(".ft").offset().top,
              a = w.height(),
              b = c - d - a;
            if (b < 0 && b < 50) {
              self.loadPage();
            }
          });
        }
  }

  // $.adbug.autocomplete();
  $.adbug.align_center();
  $(window).resize(function(event) {
        $.adbug.align_center();
  });
})(jQuery);



(function($) {
  $.adbug.layout.detailbox = {
    timeout: 50,
    closetimer: null,
    detailbox: null,
    detail_holder: null,
    detailcontent: null,
    dont_show:null,
    nodes: $('div.d-load'),
    init: function() {
      this.appendbox();
      this.bindEvent(this.nodes);
    },
    appendbox: function() {
      if (!$('#detail_box').length) {
        this.detailbox = $('<div id="detail_box"></div>').append(this.detailcontent = $('<div id="detail_content"></div>'));
        this.detail_holder = $('<div id="detail_holder"></div>').appendTo(this.detailcontent);
        this.detailbox.hide().appendTo('body');
      } else {
        this.detailbox = $('#detail_box');
        this.detailcontent = $('#detail_content');
        this.detail_holder = $('#detail_holder');
      }
    },
    clearEvent: function() {
      if (this.closetimer) {
        window.clearTimeout(this.closetimer);
        this.closetimer = null;
      }
    },
    bindEvent: function(nodes) {
      var self = this,
        d = $(document);
        
      d.delegate('div.ad', 'mouseenter', function(event) {
        var obj = $(this);
        self.dont_show = setTimeout(function(){
          self.show_box(obj);
        }, 800)
        
      });
      d.delegate('div.ad', 'mouseleave', function(event) {
        if (self.dont_show) {
          window.clearTimeout(self.dont_show);
          self.dont_show = null;
        }
        self.close_box();
      });

      this.detailbox.hover(function() {
        self.clearEvent();
      }, function() {
        self.close_box();
      });
    },
    show_box: function(obj) {
      this.clearEvent();
      this.detailcontent.find('.meta').remove();
      if(obj.attr('type') =="subject"){
        return
      }
      var id = obj.attr('adid'),
        nd = Adbug['data'][id],
        width = parseInt(nd.w),
        self = this,
        height = parseInt(nd.h),
          p_shade = $('<div class="ad_shade"><a href="'+nd.d_l+'" target="_blank" title="查看广告详细信息"></a></div>');
        self.detailcontent.find('.ad_shade').remove();
      if (nd.type == "swf") {
        swfobject.embedSWF(nd.r_url, document.getElementById("detail_holder"), width, height, 6, "expressInstall.swf",null,{wmode:"transparent"},null,function(result){
            if(!result.success){
                $('#detail_holder').html('请安装flash插件 <a target="_blank" href="http://get.adobe.com/cn/flashplayer/"><span style="color:red">安装flash插件</span></a>');
            }else{
                 p_shade.css({width:width,height:height}).appendTo(self.detailcontent);
            }
        });
        
      }

      if (nd.type == "image") {
        this.detailcontent.html('<div id="detail_holder"><a href="'+nd.d_l+'" target="_blank" title="查看广告详细信息"><img src="' + nd.r_url + '" height="' + height + '" width="' + width + '"/></a></div>');
    
      }

      window['ckplayer_status'] = function(str){
        if(str=='loadComplete'){
          swfobject.getObjectById('detail_holder').videoPlay();
        }
      }

      if(nd.type == "flv"){
        var flashvars={f:nd.r_url, c:0 };
        var params={bgcolor:'#FFF',wmode:'opaque',allowFullScreen:true,allowScriptAccess:'always'};
        var attributes={id:'detail_holder',name:'detail_holder',menu:'false'};
        swfobject.embedSWF(SITE_URL+'/Public/themes/adbug/js/ckplayer.swf', 'detail_holder', width, height, '10.0.0','ckplayer6.1/expressInstall.swf', flashvars, params, attributes);
      }

      this.detailcontent.append(nd.meta);

      var flashback;
      this.detailcontent.append(flashback = $(nd.flashback));

      flashback.hover(function(){
        flashback.addClass('hover');
      },function(){
        flashback.removeClass('hover');
      })

      var position = this.position(obj);

      var detailcontent_width = position.width;
      if(width < 213){
        detailcontent_width = 213;
      }
      
      this.detailcontent.css({
        width: detailcontent_width
      });

      typeof _hmt !== "undefined" &&_hmt.push(['_trackPageview', window.location.pathname+'?url='+nd.d_l+'?mouseenter']);
      
      this.detailbox.css({
        position: "absolute",
        left: position.left,
        top: position.top
      }).fadeIn();

    },
    position: function(obj) {
      var id = obj.attr('adid'),
        nd = Adbug['data'][id],
        position = obj.offset(),
        view_width = $(window).width(),
        width = parseInt(nd.w),
        height = parseInt(nd.h),
        left = position.left,
        top = position.top,
        total_width = left + width,
        also_widh = width - left;

      //判断宽度是否大于可视宽度
      if (total_width > view_width) {
        // left = left - width + 213;

        eft = left - width;
      } else if (also_widh < 111 && left > 50) {
        left = left - (width / 2 - 213 / 2);
      }



      if (left < 0) {
        if (view_width <= 1024) {
          if (width >= 1000) {
            left = (view_width - width - 10);
          } else {
            left = position.left - (width / 2 - 213 / 2);
          }
        } else {
          left = position.left - (width / 2 - 213 / 2);
        }

      }

      if (left < 0) {
        if (width < view_width) {
          left = Math.max(left, 3);
        }

      }

      top = top - height - 10 - 8;
      //如果高度是负值
      if (top < 0) {
        top = 0;
          left = left - 243 / 2 - width / 2;
        
      }

      if (left < 0) {

        left = position.left + 233;
      }

      var right =  left +width;
      if( total_width > view_width){
        left = position.left - width;
      }

      return {
        top: top,
        left: left,
        width: width,
        height: height
      }
    },
    close_box: function() {
      var self = this;
      this.closetimer = window.setTimeout(function() {
        self.detailbox.fadeOut();
        self.detailcontent.find('.ad_shade').remove();
        typeof _hmt !== "undefined" && self.nd && _hmt.push(['_trackPageview', window.location.pathname+'?url='+self.nd.d_l+'&mouseleave']);
      }, this.timeout)
    }
  }
})(jQuery);


(function($){
  $.fn.followDIV = function(options){
    var defaults = {}
    var options = $.extend(defaults,options);
    this.each(function(){
      var obj = $(this), t = obj.offset().top, j_l = obj.offset().left,j_t = obj.position().top,j_left = obj.position().left, position = obj.css('position');
      window.onscroll = function(){f(obj,t,j_t,j_l,position,j_left)};
      window.onresize = function(){f(obj,t,j_t,j_l,position,j_left)};
      
      function f(obj,t,j_t,j_l,position,j_left){
        var dst = $(document).scrollTop();
        if(($.browser.msie == true) && ($.browser.version == 6.0)){
          if(dst > t) {
            obj.css({position: "absolute", top: dst - t});
          }
        }else{
          if(dst > t){
            obj.css({position: "fixed", top: "-" & dst + "px", left: j_l})
            obj.addClass('followDIV');
          }
        }
        if(dst <= t) {
          obj.removeClass('followDIV');
          obj.css({position: position, top: j_t, left:j_left});
        }
      }
    });
  }
})(jQuery);

(function($) {
  $.adbug.filter = {
    filter_div: $('.sub_filter'),
    filter_item: $('.sub_filter li'),
    filter_form: $('#filter_form'),
    init: function() {
      var self = this;
      this.bindEvent();
      this.filter_div.each(function(index) {
        var current = self.filter_div.eq(index), type = current.data('event'), item = current.find('.filter-item');
        switch(type){
          case "click":
            console.log("12");

            item.click(function(){
              console.log("1");
              if(current.hasClass('hover')){
                console.log("2");
                current.removeClass('hover');
              }else{
                console.log("3");
                current.addClass('hover');
              }
            })

            $(document).click(function(k) {
              var pp = $(k.target).parents('.sub_filter');
              if(!pp.length){
                current.removeClass('hover');
              }else{
                console.log(pp[0] == current[0]);
              }  
            })
            break;
          default:
            current.hover(function() {
                current.addClass('hover')
              },
              function() {
                current.removeClass('hover')
              }
            );

        }
        
      })
    },
    bindEvent: function(){
      var self = this;
      this.filter_item.each(function(){
          var obj = $(this);
          var result_div = obj.parent().parent().find('.filter-item span');
          obj.hover(function() {
            obj.addClass('hover');
          }, function() {
            obj.removeClass('hover');
          });

          obj.click(function(event) {
            var type = obj.attr('type'), name = obj.attr('name');
              obj.addClass('cur').siblings().removeClass('cur');
              $('#'+type+'_filter_val').val(name);
              result_div.text(obj.text());
              self.filter_form.trigger('submit');
          });
      })
    }
  }
})(jQuery);



(function($){


    var filterForm = $('#filter_form');
    var filterItems = filterForm.find("[data-filter]");

    $filterForm()



})(jQuery);