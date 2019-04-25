function creativeViewer(conf){

	conf = conf || {};

	var avaibleWidth = 0;

	var arrowContainer;
	var currentElement;

	var iconPath = SITE_URL+'/Public/adbug-new/images';


	var ViewerContainer = $("<div class='viewer-container'></div>");
	var ViewerInnerContainer = $("<div class='viewer-inner'></div>");
	var CloseButton = $("<a href='javascript:void(0)' class='close-button' style='position: absolute; top: 16px;right: 16px; z-index: 8;width: 28px;height: 28px;'><img src='"+iconPath+"/close.png' width='28' height='28'></a>");
	var ViwerViews = $("<div id='viewer-views'></div>");
	var PrevButton = $("<a class='prev-button'></a>");
	var NextButton = $("<a class='next-button'></a>");
	var ViewerBg = $("<div class='viewer-bg'></div>");

	ViewerContainer.hide();

	ViewerContainer.append(ViewerInnerContainer);
	ViewerInnerContainer.append(CloseButton);
	ViewerInnerContainer.append(ViwerViews);
	ViewerInnerContainer.append(PrevButton);
	ViewerInnerContainer.append(NextButton);
	ViewerInnerContainer.append(ViewerBg);


	CloseButton.click(function(event){
		trackerEvent("adViewer", "close", "button");
		openItem(currentElement);
	})

	var CreativeContainer = $("#creatives-container");

	CreativeContainer.after(ViewerContainer);

	function getElements(){
		var elements = $(conf.selector);
		console.log("elements", elements.length)
		return elements;
	}

	function isOpend(element){
		return element.data("opend");
	}

	function setClosed(element){
		element.data("opend", 0);
	}

	function openItem(element){
		// 隐藏高级搜索
		// 2017-01-04 finn 
		$('#advanced').data('adv',1);
        $('.advanced-search').css('display','none');
		// 重置上一个的状态
		if(currentElement && element[0] != currentElement[0]){
			console.log("setClosed")
			setClosed(currentElement);
		}

		if(isOpend(element)){
			console.log("CloseViewer", element);
			return CloseViewer(element);
		}

		trackerEvent("adViewer", "open", element.attr("id"));
		// trackerEvent("trackSiteSearch", "open", element.data("title"));
		// 2016-12-20 finn 记录点击事件 关键词 
		// pushPiwik(element.data("title"));
		// end
	
		// console.log("openItem", element);

		var row = element.data("row");
		var lastRowElement = $("[data-row="+row+"]:last");

		currentElement = element;
		ViwerViews.empty();

		insertArrow(lastRowElement, element, function afterAnimation(){
			createNewView(element);
			element.data("opend", 1);
		});
		
	}

	function CloseViewer(element){
		currentElement = null;
		// ViewerContainer.hide();
		// arrowContainer.hide();
		// element.data("opend", 0);
		setClosed(element);


		trackerEvent("adViewer", "close", element.attr("id"));


		console.log("close video ");

		arrowContainer.stop().animate({
			height: 0
		}, 300, function(){
			arrowContainer.hide();
		})

		ViewerContainer.stop().animate({
			height: 0
		}, 300, function(){

		})

		ViwerViews.empty();
		lastRowElementTemp = null;


	}


	function emptyViewer(element){
		currentElement = null;
		// ViewerContainer.hide();
		// arrowContainer.hide();
		// element.data("opend", 0);
		setClosed(element);
		arrowContainer.height(0);
		ViewerContainer.height(0);
		ViwerViews.empty();
		lastRowElementTemp = null;
	}


	var ajaxRequest;

	function createNewView(element){

		console.log("element:",element);
		var file = element.data("file");
		var type = element.data("type");
		var advertiser = element.data("advertiser");
		var width = element.data("width");
		var height = element.data("height");
		var noMeta = type == "wechat";
		var isWechat = type == "wechat";
		var data = $.parseJSON(element.find(".item_meta").text());

		console.log('data md5_aggs:',data);


		

		var title = data.title;
		var advertiserMetaData = data.a || {}; 
		var md5 = advertiserMetaData.m;
		var dushuomd5 = advertiserMetaData.dsmd5;
		console.log("dushuomd5:",dushuomd5);
		var adsmd5 = data.adsmd5;
		var md5_aggs = data.md5_aggs;

		var videoid = data.eid;

		var advertiserIsEmpty = advertiserMetaData.host == "";

		if(!advertiserMetaData.host){
			advertiserIsEmpty = true;
		}

		var html = "<div class='viewer-view'>";



		if(coverString(file,'mp4'))
		{

			html = html+'<div class="view-left" data-videoId="'+videoid+'">';
			
			console.log("html:",html);

		}else{
			html = html+'<div class="view-left" data-videoId="'+videoid+'">';
		}

		html = html+"</div>"+
						"<div class='split-line' style='height: 560px;'>"+
						"</div>"+
						"<div class='view-right'>"+
   						"<div class='view-metas'><span class='cam-link'></span>"+
   							"<div class='sub-meta'>"+
   								"<span><a href='#'></a></span>"+
   								"<span class='start-prefix'>"+width+" × "+height+"</span>"+
   							"</div><div style='margin-top: 20px;' class='the-actions'></div><div class='related-creatives'></div><div class='advertiser-meta'></div>"+
   							"<div class='actions'>"+
   							"</div>";
		// html = html+"</div>"+
		// 				"<div class='split-line' style='height: 560px;'>"+
		// 				"</div>"+
		// 				"<div class='view-right'>"+
  //  						"<div class='view-metas'><span class='cam-link'></span>"+
  //  							"<div class='sub-meta'>"+
  //  								"<span><a href='#'></a></span>"+
  //  								"<span class='start-prefix'>"+width+" × "+height+"</span>"+
  //  							"</div><div style='margin-top: 20px;' class='the-actions'></div><div class='related-creatives'></div><div class='advertiser-meta'></div>"+
  //  							"<div class='actions'>"+
  //  							"</div><a class='advertiser-pinlun' data-title='"+title+"' data-md5='"+dushuomd5+"'>相关评论</a>";



   		// console.log("after html:",html);
		//md5_aggs
		// var html = "<div class='viewer-view'>"+
		// 				"<div class='view-left'>"+
		// 					""+
		// 				"</div>"+
		// 				"<div class='split-line' style='height: 560px;'>"+
		// 				"</div>"+
		// 				"<div class='view-right'>"+
  //  						"<div class='view-metas'><span class='cam-link'></span>"+
  //  							"<div class='sub-meta'>"+
  //  								"<span><a href='#'></a></span>"+
  //  								"<span class='start-prefix'>"+width+" × "+height+"</span>"+
  //  							"</div><div style='margin-top: 20px;' class='the-actions'></div><div class='related-creatives'></div><div class='advertiser-meta'></div>"+
  //  							"<div class='actions'>"+
  //  							"</div><a class='advertiser-pinlun' data-title='"+title+"' data-md5='"+dushuomd5+"'>相关评论</a>";



   		if(md5_aggs && data.is_admin)
   		{
   			html = html+"<a class='advertiser-trend' data-title='"+title+"' data-md5='"+adsmd5+"'>投放趋势</a>";
   		}
   		// if(data.is_admin)
   		// {
   		// 	console.log("data.id",data.id,data);
   		// 	html = html+"<a class='advertiser-trend' href='"+data.id+"'>编辑</a>";
   		// }
   		html = html+"</div>"+
						"</div>"+
					"</div>";

		var vHtml = $(html);
		ViwerViews.html(vHtml);

		// 投放趋势
		// <a class='advertiser-trend' data-title='"+title+"' data-md5='"+adsmd5+"'>投放趋势</a>
		var advertiserTrend = vHtml.find("a.advertiser-trend");

		advertiserTrend.click(function(){
			layer.open({
			  type: 2,
			  title: title+' - 投放趋势',
			  // maxmin: true,
			  shadeClose: true, //点击遮罩关闭层
			  area : ['800px' , '450px'],
			  content: '/index/getAdsTrend?md5='+$(this).data('md5')+"&title="+$(this).data('title'),
			 });
		});

		// finn 2017-1-9 相关评论
		var advertiserPinlun = vHtml.find("a.advertiser-pinlun");

		advertiserPinlun.click(function(){

			layer.open({
			  type: 2,
			  title: '评论详细',
			  maxmin: true,
			  shadeClose: true, //点击遮罩关闭层
			  area : ['800px' , '520px'],
			  content: '/index/getPinLun?md5='+$(this).data('md5')+"&title="+$(this).data('title'),
			 });
		})
		// finn 2017-1-9 相关评论 end



		var advertiserMeta = vHtml.find(".advertiser-meta");

		advertiserMeta.empty();

		var advertiserName =  advertiserMetaData.name && advertiserMetaData.name != "" ? advertiserMetaData.name +" "+advertiserMetaData.host : advertiserMetaData.host;

		if(!noMeta && !advertiserIsEmpty){
			advertiserMeta.append("<h2>广告主</h2>");

			// console.log("99",U("advertiser/detail"));

			// var Alink = U("advertiser/detail")+"/p/"+md5;
			var Alink = "/advertiser/detail/p/"+md5;

			console.log("Alink:",Alink);
			advertiserMeta.append("<h1><a href='"+Alink+"' data-track='advertiser-link' target='_blank' class='tooltips' data-container='body' data-placement='bottom' data-html='true' title='通过营销活动来推广其产品、服务、品牌价值或企业形象的机构'>"+advertiserName+"</a></h1>");
		}

		var swfHolder = $("<div></div>");

		// var adsLink = U("advertiser/detail")+"/p/"+md5+"#!ads";
		// var publishersLink = U("advertiser/detail")+"/p/"+md5+"#!publisher";
		// var subjectsLink = U("advertiser/detail")+"/p/"+md5+"#!subject";
		

		var adsLink = "/advertiser/detail/p/"+md5+"#!ads";
		var publishersLink = "/advertiser/detail/p/"+md5+"#!publisher";
		var subjectsLink = "/advertiser/detail/p/"+md5+"#!subject";





		console.log("publishersLink:",publishersLink);
		// var actionsHmtl = "<table class='actions-table'><tbody><tr><td><button type='button' class='btn default'>营销活动 "+advertiserMetaData.subjects+"</button></td><td><button type='button' class='btn default'>创意 "+advertiserMetaData.ads+"</button></td><td><button type='button' class='btn default'>媒体 "+advertiserMetaData.publishers+"</button></td></tr></tbody></table>";

		var actionsHmtl = "adbug巡视到该广告主在 <a href='"+publishersLink+"' class='tooltips' target='_blank' data-container='body' data-placement='bottom' title='该创意所投放到的媒体'>"+advertiserMetaData.publishers+"</a> 个媒体上投放 <a href='"+subjectsLink+"' class='tooltips' target='_blank' data-container='body' data-placement='bottom'  title='广告主投放的数字营销活动（Campaign）'>"+advertiserMetaData.subjects+"</a> 个活动 共计 <a href='"+adsLink+"'  class='tooltips' target='_blank' data-container='body' data-placement='bottom' title='该广告主为了营销推广制作的素材（creative)'>"+advertiserMetaData.ads+"</a> 个创意";
		var rightMeta = vHtml.find(".view-right");

		if(!noMeta && !advertiserIsEmpty){
			rightMeta.find('.actions').html(actionsHmtl);
		}




		function renderMeta(data, element){

			// console.log(data);

			var file = data.file;
			var title = data.title;
			var type = data.tp;
			var date = data.d;
			var l_date = data.l_d;
			// var advertiser = element.data("advertiser");
			var width = data.width;
			var height = data.height;
			// finn 2017-4-17 html5 
			var htmlUrl = data.attr_url;



			if(file != '' || file != underline)
			{
				file = changeFileNum(file);
				// var new_file = file.split('/');
				// console.log("new_file",new_file[4]);

				// var new_file_ch = new_file[4].substring(0,1);


				// //http://file.adbug.cn/datasync/d1xz/16a9e439b8b6463cb5fe5cae6f466a61.jpg

				// if(!isNaN(new_file[4]) || new_file_ch=='a' || new_file_ch=='i' || new_file_ch=='b' || new_file_ch=='d' || new_file_ch=='e' || new_file_ch=='f' || new_file_ch=='h')
				// {
				// 	if(file.indexOf('datasync2')>=0)
				// 	{
				// 		file = file.replace(/datasync2/, "datasync2");
				// 	}
				// 	else{
				// 		file = file.replace(/datasync/, "datasync2");
				// 	}
				// }

			}
			console.log('===renderMeta  new file ==',file);




	    	if(coverString(file,'mp4'))type='mp4';

			var adLink = "http://www.adbug.cn/ad/"+data.eid;
			console.log("_paq:",_paq);
			try{
				var dotitle =  IN_OUT_EXCENT+title;
				_paq.push(['trackPageView',dotitle]);

			}catch(e){
				console.log('piwik e:',e.stack);
			}
			IN_OUT_EXCENT = "Expand:";
			var elementType = "";

			if(element){
				elementType = element.data("type");
			}

			var advertiserMetaData = data.a || {}; 

			var rightMeta 			= vHtml.find(".view-right");
			var rightSubMeta 		= rightMeta.find(".sub-meta");
			var contentContiner 	= vHtml.find(".view-left");
			var actionsContainer 	= vHtml.find(".the-actions");
			var headerContainer 	= vHtml.find(".cam-link");


			rightSubMeta.empty();
			contentContiner.empty();
			actionsContainer.empty();
			headerContainer.empty();

			rightSubMeta.css('position','relative');

			// var SubjectURL = U("subject/index")+"/p/"+data.sm;
			var SubjectURL = "/subject/index/p/"+data.sm;

			var downloadLink = "/tools/download/ad/"+data.eid;
			var flashbackLink = "/tools/flashback/ad/"+data.eid;
			// var downloadLink = U('tools/download')+"/ad/"+data.eid;
			// var flashbackLink = U('tools/flashback')+"/ad/"+data.eid;


			var targetURL = data.tg;
			var linkHtml = "";

			if(targetURL){
				linkHtml = "<a href='"+targetURL+"' style='margin-left: 10px;' target='_blank'><i class='fa fa-external-link'></i></a>";
				//<a class='btn grey-mint' href='"+targetURL+"' target='_blank' rel='noreferrer'><span class='btn-inle'>落地页</span></a>
			}

			var headerHTML = "<a href='"+SubjectURL+"' class='campaign-link tooltips' target='_blank' data-container='body' data-placement='left'  data-original-title='"+title+"'>广告活动："+title+"</a>";

			headerContainer.html(headerHTML);


			var flashbackButton = "";

			if(data.pl == "PC" && data.is_login){
				flashbackButton = "<td><a class='btn grey-mint' href='"+flashbackLink+"' target='_blank' rel='noreferrer'><span class='btn-inle'>场景还原</span></a></td>";
			}
			// 2017-6-29 新增 广告主订阅
			if(data.a)
			{
				var binlog = '/index/binlog?host='+data.a.host;
				flashbackButton += "<td><a class='btn grey-mint' href='"+binlog+"' target='_blank' rel='noreferrer' style='position:relative'><img src='/Public/images/new.gif' style='position:absolute;right:0;top:10%;width:22px;' /><span class='btn-inle'>订阅</span></a></td>";
			}

			if(data.is_admin)
	   		{
	   			// console.log("data.id",data.id,data);
	   			// html = html+"<a class='advertiser-trend' href='/index/ottedit?id="+data.eid+"'>编辑</a>";

	   			var otturl = '/index/ottedit?id='+data.eid;

	   			flashbackButton += "<td><a class='btn grey-mint' href='"+otturl+"' target='_blank' rel='noreferrer' style='position:relative'><img src='/Public/images/new.gif' style='position:absolute;right:0;top:10%;width:22px;' /><span class='btn-inle'>编辑</span></a></td>";
	   		}
		
			var theactionsHmtl = "";

			if(data.is_login)
			{
				if(targetURL)
				{
					theactionsHmtl = "<table class='actions-table'><tbody><tr><td><a class='btn grey-mint' href='"+downloadLink+"' target='_blank' rel='noreferrer'><span class='btn-inle'>下载素材</span></a></td>"+flashbackButton+"<td><a class='btn grey-mint' href='"+targetURL+"' target='_blank' rel='noreferrer'><span class='btn-inle'>落地页</span></a></td></tr></tbody></table>";
				}else{
					theactionsHmtl = "<table class='actions-table'><tbody><tr><td><a class='btn grey-mint' href='"+downloadLink+"' target='_blank' rel='noreferrer'><span class='btn-inle'>下载素材</span></a></td>"+flashbackButton+"<td></td></tr></tbody></table>";
				}
				// var theactionsHmtl = "<table class='actions-table'><tbody><tr><td><a class='btn grey-mint' href='"+downloadLink+"' target='_blank' rel='noreferrer'><span class='btn-inle'>下载素材</span></a></td>"+flashbackButton+"<td><a class='btn grey-mint' href='"+targetURL+"' target='_blank' rel='noreferrer'><span class='btn-inle'>落地页</span></a></td></tr></tbody></table>";
				
				
				if(!isWechat){
					actionsContainer.html(theactionsHmtl);
				}
			}
			

			var needPdiv = true;

			if(isWechat){
				rightSubMeta.append("<span title='尺寸'>"+width+" × "+height+"</span>")
				rightSubMeta.append("<span class='tooltips' title='平台'>平台: "+data.pl+"</span>")
			}else{
				rightSubMeta.append("<span class='tooltips' title='尺寸' data-container='body' data-placement='bottom' data-html='true' data-original-title='尺寸'>尺寸: "+width+" × "+height+"</span>")
				rightSubMeta.append("<span class='tooltips' title='平台' data-container='body' data-placement='bottom' data-html='true' data-original-title='平台'>平台: "+data.pl+"</span>")
				

				rightSubMeta.append("<span class='tooltips' title='该创意开始发现的时间' data-container='body' data-placement='bottom' data-html='true' data-original-title='平台'>开始时间: "+date+"</span>")
				// 最后出现时间 判断
				if(l_date){
					rightSubMeta.append("<span class='tooltips' title='该创意最后一次发现的时间' data-container='body' data-placement='bottom' data-html='true'>最近抓到时间: "+l_date+"</span>")
				}
				// rightSubMeta.append("<span class='start-prefix' title='平台'>"+data.pl+"</span>")
			}

			var isPc = true;
			var sUserAgent = navigator.userAgent.toLowerCase();

			var ie_version = 0;
			// IE 浏览器 获取 版本
			if (!!window.ActiveXObject || "ActiveXObject" in window)
			{

				if(sUserAgent.match(/(trident)\/([\w.]+)/))
				{
					uaMatch = sUserAgent.match(/trident\/([\w.]+)/);
					switch (uaMatch[1]) {
						case "4.0":
							ie_version = 4;
							break;
						case "5.0":
							ie_version = 9;
							break;
						case "6.0":
							ie_version = 10;
							break;
						case "7.0":
							ie_version = 11;
							break;
					}
				}
			}

		    var bIsIpad = sUserAgent.match(/ipad/i) == "ipad";
		    var bIsIphoneOs = sUserAgent.match(/iphone os/i) == "iphone os";
		    var bIsMidp = sUserAgent.match(/midp/i) == "midp";
		    var bIsUc7 = sUserAgent.match(/rv:1.2.3.4/i) == "rv:1.2.3.4";
		    var bIsUc = sUserAgent.match(/ucweb/i) == "ucweb";
		    var bIsAndroid = sUserAgent.match(/android/i) == "android";
		    var bIsCE = sUserAgent.match(/windows ce/i) == "windows ce";
		    var bIsWM = sUserAgent.match(/windows mobile/i) == "windows mobile";
		      
		    if (bIsIpad || bIsIphoneOs || bIsMidp || bIsUc7 || bIsUc || bIsAndroid || bIsCE || bIsWM) isPc = false;


		    console.log("isPC",isPc);

			// 2017-3-28 finn 
			if(!data.is_login)
			{

				// opacity:0.4
				var blur_html = '<div id="blur-detail" style="height:84px;line-height:84px;width:364px; background: #555;';


				if(ie_version>=10)
				{
					blur_html = blur_html+'opacity:0.1;';
				}

				blur_html = blur_html+'-webkit-filter: blur(1.8px);-moz-filter: blur(1.8px);-ms-filter: blur(1.8px);-o-filter: blur(1.8px);filter: blur(1.8px);filter: progid:DXImageTransform.Microsoft.Blur(PixelRadius=4, MakeShadow=false);"><div class="sub-meta"><span>媒体: <a  class="tooltips" data-container="body" data-placement="bottom"  data-track="publisher-link">com.adbug.cn</a></span><span class="tooltips" data-container="body" data-placement="bottom" data-html="true" >'
				+' 跟踪者: <a href="" style="white-space: nowrap;text-overflow: ellipsis;display: inline-block;max-width: 100px;">adbug.cn adbugtech.com</a> </span></div>'+
				' <div class="the-actions"><table class="actions-table"><tr>';



				if(data.pl == "PC"){
					blur_html = blur_html+"<td><a class='btn grey-mint' style='max-height:10px' rel='noreferrer'><span class='btn-inle'>场景还原</span></a></td>";
				}
				if(targetURL)
				{
					blur_html = blur_html + "<td><a style='max-height:10px' class='btn grey-mint' rel='noreferrer'><span class='btn-inle'>下载素材</span></a></td>"+flashbackButton+"<td><a style='max-height:10px' class='btn grey-mint' rel='noreferrer'><span class='btn-inle'>落地页</span></a></td>";
				}else{
					blur_html = blur_html + "<td><a style='max-height:10px' class='btn grey-mint' rel='noreferrer'><span class='btn-inle'>下载素材</span></a></td>"+flashbackButton+"<td></td>";
				}

				blur_html = blur_html+'</tr></table></div>';

				if(!isPc)
				{
					rightSubMeta.append(blur_html+' </div><div id="blur-login-detail" style="position: absolute;top: 35%;line-height:84px;left:0;width:364px;text-align:center"><a href="/login" target="_blank">登录查看投放详情</a></div>');
				}else{
					rightSubMeta.append(blur_html +' </div><div id="blur-login-detail" style="position: absolute;top: 20px;line-height:84px;left:0;width:364px;text-align:center"><a href="/login" target="_blank">登录查看投放详情</a></div>');
				}
				
			}else{
				

				if(data.p){
					
					// var Plink = U("publisher/detail")+"/p/"+data.p.m;
					var Plink = "/publisher/detail/p/"+data.p.m;
					var PublisherName = data.p.host;
					var PublisherHost = data.p.host;
					if(data.p.full && data.p.full !== ""){
						PublisherName = data.p.full;
						if(0){
							PublisherName = $.trim(data.p.full.replace(PublisherHost, ""));
							if(PublisherName == ""){
								PublisherName = PublisherHost;
							}
						}
					}

					rightSubMeta.append('<span>媒体: <a href="'+Plink+'" class="tooltips" target="_blank" data-container="body" data-placement="bottom"  data-original-title="该创意所投放到的媒体" data-track="publisher-link">'+PublisherName+'</a></span>')
				}
				
				if(data.t !== "" && data.t){
					// 2017-1-6 finn 跟踪者 超链接
					console.log("data",data);
					if(data.t_url_md5)
					{
						var md5_domain = data.t_url_md5.split("-TS-");
						var TLinks = '';
						for (var i = md5_domain.length - 1; i >= 0; i--) {
							
							var domain_t = md5_domain[i].split('_TX_');

							TLinks += "<a target='_blank' href='/tracker/detail/p/"+domain_t[0]+"#!advertiser'>"+domain_t[1]+"</a> ";
						}
						rightSubMeta.append("<span class='tooltips' data-container='body' data-placement='bottom' data-html='true' data-original-title='测量该创意投放情况的机构'>跟踪者: "+TLinks+"</span>")
					}
					//rightSubMeta.append("<span class='tooltips' data-container='body' data-placement='bottom' data-html='true' data-original-title='测量该创意投放情况的机构'>跟踪者: "+data.t.split(';').join(" ")+"</span>")
				}
			}
			
			
			



			var avaibleWidth  =  contentContiner.width() - 40;
			var avaibleHeight  =  ViewerContainer.height() - 40 || 600 - 40;


			var $pdiv = $("<div></div>");


			window['ckplayer_status'] = function(str){
		        if(str == 'loadComplete'){
		          swfobject.getObjectById('detail_holder').videoPlay();
		        }
		    }

			// console.log(contentContiner);

			if(type == "wechat"){
				var imageHtml = "<img src='http://pan.baidu.com/share/qrcode?w=300&h=300&url="+file+"' class='main-image'>";

				contentContiner.append("<span style=\"margin-bottom: 20px;display: inline-block;\">请使用微信扫一扫查看创意</span></br>");
				contentContiner.append(imageHtml);
				// return;
			}


			var hRatio = height / width;
			var WRatio = width / height;

			console.log(avaibleHeight, avaibleWidth);

			if(avaibleHeight < height){
				height = avaibleHeight;
				width = WRatio * avaibleHeight;
			}

			if(avaibleWidth < width){
				width = avaibleWidth;
				height = avaibleWidth * hRatio;
			}

			console.log("after", height, width);

			console.log("type",type);

			if(type == "image"){

				// console.log("before", height, width);
				var imageHtml = "<img src='"+file+"' width='"+width+"' height='"+height+"' data-title='"+title+"' class='main-image'>";

				if(height >= 600){

					// contentContiner.css({
					// 	height: "92%"
					// })
					// imageHtml = "<img src='"+file+"' style='max-height:100%'>";
				}

				contentContiner.html(imageHtml);
			}
			if(type == "html5"){
				var html5Html = '<iframe frameborder="0" scrolling="no" src="'+htmlUrl+'"  ></iframe><div class="iframe-info" style="position: absolute;height: 100%;width: 100%;top: 0;left: 0;z-index: 1;"></div>';

				contentContiner.html(html5Html);
			}

	    	if(elementType == "native"){


	    		var elementHTML = $(element.prop('outerHTML'));

	    		var width = elementHTML.data("width");
	    		var height = elementHTML.data("height");

	    		elementHTML.attr("style", "");
	    		elementHTML.attr("data-row", null);

	    		elementHTML.css({
	    			width: width,
	    			height: height,
	    			lineHeight: "80%"
	    		});


	    		contentContiner.html(elementHTML);

		    	// console.log(element.prop('outerHTML'))
		    }

		    console.log("file:",file);

			if(type == "swf") {
				needPdiv = false;
				swfHolder.appendTo(contentContiner);
		        swfobject.embedSWF(file, swfHolder[0], width, height, 6, "expressInstall.swf",null,{play:true, wmode:"transparent" },null,function(result){
		           
		            if(!result.success){
		                contentContiner.html('请安装或开启Flash插件 <a target="_blank" href="http://get.adobe.com/cn/flashplayer/"><span style="color:red">安装Flash插件</span></a>');
		            }else{
		                // p_shade.css({ width:width, height:height }).appendTo(self.detailcontent);
		            }
		        });
	    	}


	    	if(type == 'mp4')
	    	{
	    		needPdiv = false;

	    		flashvarsMp4 = { f:file, c:0 ,p:1, v:vflv };
				paramsMp4 = { bgcolor:'#FFF', wmode:'opaque', allowFullScreen:true, allowScriptAccess:'always'};
			    attributesMP4 = { id:'detail_holder', name:'detail_holder', menu:'false'};
			    playerPathMp4 = SITE_URL+'/Public/adbug-new/js/ckplayer/ckplayer.swf';
	
			    cul_videoId = $("#viewer-views .viewer-view .view-left").data("videoid");
	    		var videoElement = $('<video id="'+cul_videoId+'" controls class="video-js" width="700" preload="auto"><source src="'+file+'" type="video/mp4"></video>');
			   	$('#viewer-views .viewer-view .view-left').empty().append(videoElement);


			   	mp4Player(videoElement[0], file,cul_videoId,contentContiner,swfHolder,swfobject);

	    		swfHolderMp4 = swfHolder;
				contentContinerMp4 = contentContiner;
				swfobjectMp4 = swfobject;
				widthMp4 = width;
				heightMp4 = height;

	    		
	    	}
		    
		    window['ckplayer_status'] = function(str){

			    var isType = str.split(':');

			    // console.log("ckplayer_status isType:",isType);
			    if(isType[0]=='volumechange')
			    {
			     	volumnMp4 = isType[1]/100;

			     	vflv = isType[1];

			     	console.log("ckplayer_status volumnMp4:",volumnMp4,vflv);
			    }

			}
			if(type == "flv"){

		    	needPdiv = false;

		    	// swfHolder.attr("id", "detail_holder");
		    	
		    	swfHolder.appendTo(contentContiner);

		    	console.log("type==flv==vflv",vflv);
			    var flashvars = { f:file, c:0 ,p:1, v:vflv };
			    var params = { bgcolor:'#FFF',play:true, wmode:'transparent', allowFullScreen:true, allowScriptAccess:'always'};
			    var attributes = { id:'detail_holder', name:'detail_holder', menu:'false'};
			    var playerPath = SITE_URL+'/Public/adbug-new/js/ckplayer/ckplayer.swf';
			    var video = [file];

			    swfobject.embedSWF(SITE_URL+'/Public/adbug-new/js/ckplayer/ckplayer.swf', swfHolder[0], width, height, '6','expressInstall.swf', flashvars, params, attributes, function(result){
			        if(!result.success){
			            contentContiner.html('请安装或开启Flash插件 <a target="_blank" href="http://get.adobe.com/cn/flashplayer/"><span style="color:red">安装Flash插件</span></a>');
			        }else{
			                // p_shade.css({ width:width, height:height }).appendTo(self.detailcontent);
			        }
			    });
			        // CKobject.embed(playerPath, 'detail_holder', 'detail_holder', '600', '400', false, flashvars, video, params);
			    // swfobject.embedSWF(SITE_URL+'/Public/adbug-new/js/ckplayer/ckplayer.swf', swfHolder[0], width, height, '10.0.0','ckplayer6.1/expressInstall.swf', flashvars, params, attributes, function(result){
			    //     if(!result.success){
			    //         contentContiner.html('请安装或开启Flash插件 <a target="_blank" href="http://get.adobe.com/cn/flashplayer/"><span style="color:red">安装Flash插件</span></a>');
			    //     }else{
			    //             // p_shade.css({ width:width, height:height }).appendTo(self.detailcontent);
			    //     }
			    // });
		    }

		    if(needPdiv){
			    $pdiv.css({
				    "position": "absolute",
					"display": "inline-block",
				    "width": width,
				    "height": height,
				    "left": "50%",
				    "cursor": "pointer",
				    "margin-left": "-"+width/2+"px",
				    "top": "50%",
				    "margin-top": "-"+height/2+"px"
				})
				contentContiner.append($pdiv);
			}



		}

		var relatedCreatives = vHtml.find(".related-creatives");

		//blur-detail
		console.log("relatedCreatives:",relatedCreatives.width());

		$("#blur-detail").css('width',relatedCreatives.width());
		$('#blur-login-detail').css('width',relatedCreatives.width());

		if(noMeta || advertiserIsEmpty){
			relatedCreatives.hide();
		}

		if(!noMeta){

			// advertiserMeta.after(relatedCreatives);

			// console.log()
			// var path = U("search/related_ads");
			var path = "/search/related_ads";

			if(ajaxRequest){
				ajaxRequest.abort();
			}

			ajaxRequest = $.post(path, { p: md5 }, function(body, status){
				// console.log(err, body)
				// console.log(body, status)
				renderRelatedAds(body);

			}, "JSON")


			// console.log(ajaxRequest);

			function renderRelatedAds(body){

				var ads = $(body.data);

				relatedCreatives.append('<div class="name" style="font-size: 13px;"> 相关创意</div>');

				$.each(ads, function(index, val) {

					var ad = ads.eq(index);

					var meta = $.parseJSON(ad.find(".item_meta").text());

					// finn 2016-12-27
					ad.on("mouseenter mouseleave",function(event){

						var $this = $(this);

        				if(event.type == "mouseenter"){
				            $this.data("start-time", (new Date()).getTime());
				        }

				        if(event.type == "mouseleave"){

				            var startTime = $this.data("start-time");

				            if(startTime){

				                var timeLeft = (new Date()).getTime() - startTime;

				                var title = $this.data('title');

				                $this.data("start-time", null);

				                if(timeLeft > 1000){
				                    try{
				                    	var dotitle = "InDwell:"+title;
										_paq.push(['trackPageView',dotitle]);
				                    }catch(e){
				                        // console.log(e.stack);
				                    }
				                }
				            }

				        }
					})
					ad.click(function(event){

						var ad_img = $(this).find('img').attr('src');
						if(type=='html5')
						{
							ad_img = $(this).find('iframe').attr('src');
						}

						if(coverString(ad_img,'mp4'))
						{
							curlVideo = $(this).find('a').data('videoid');
							if(!lastVideo)lastVideo = curlVideo;
							mp4Player(curlVideo,ad_img);
						}


						if(ad.data('inout')=='in')
						{
							IN_OUT_EXCENT = "In"+IN_OUT_EXCENT;
						}else{
							IN_OUT_EXCENT = "Expand:";
						}



						ad.addClass('active').siblings().removeClass('active')
						renderMeta(meta);
						trackerEvent("RelatedAds", "click");
					})

					relatedCreatives.append(ad)
					// console.log(meta);

				})

			}

		}

		renderMeta(data, element);

		$(".tooltips").tooltip();
	}

	var lastRowElementTemp;


	function resizeViewer(element){

		var row = element.data("row");
		var lastRowElement = $("[data-row="+row+"]:last");


		if(arrowContainer){
			arrowContainer.remove();
		}

		arrowContainer = $("<div class='arrow-container'></div>");
		var arrow = $("<div class='arrow-main'></div>");

		// var height = ;

		arrowContainer.css({
			marginBottom: 12
		});



		var width = element.width();
		var outerHeight = element.outerHeight(true);
		var offset = element.offset();
		var offsetTop = offset.top - 12;
		var offsetLeft = offset.left + (width / 2) - 10;

		arrowContainer.append(arrow);
		lastRowElement.after(arrowContainer)

		arrow.css({
			top: -10,
			left: offsetLeft
		})

		ViewerContainer.css({
			top: offset.top + outerHeight,
			left: 0,
			display: "block",
			position: "absolute"
		});

		arrowContainer.height(600);
	}

	function insertArrow(lastRowElement, element, afterAnimation){

		var isSameLine = false;

		if(lastRowElementTemp && lastRowElementTemp == lastRowElement[0]){
			isSameLine = true;
		}


		lastRowElementTemp = lastRowElement[0];

		if(arrowContainer){
			arrowContainer.hide()
			// .remove();
		}

		arrowContainer = $("<div class='arrow-container'></div>");
		var arrow = $("<div class='arrow-main'></div>");

		// var height = ;

		arrowContainer.css({
			marginBottom: 12
		});

		var width = element.width();
		var outerHeight = element.outerHeight(true);
		var offset = element.offset();
		var offsetTop = offset.top - 12;
		var offsetLeft = offset.left + (width / 2) - 10;

		var avaibleHeight = $(window).height();
		var leftHeight = avaibleHeight - 600;

		var tempOffsetTop = offset.top + outerHeight;

		if(leftHeight < outerHeight){
			offsetTop = tempOffsetTop - (leftHeight * 0.8);
		}
		
		// console.log("offsetLeft", offset);
		// console.log("outerHeight", outerHeight);

		// if(leftHeight < 600){
			// offsetTop += leftHeight;
			// console.log("too small", leftHeight)
		// }

		// console.log(avaibleHeight, outerHeight, "left", avaibleHeight - outerHeight)

		// $(window).scrollTop(offsetTop);

		// $('html,body').animate({scrollTop: offsetTop+'px'}, 400, "linear");

		arrowContainer.append(arrow);
		lastRowElement.after(arrowContainer)

		arrow.css({
			top: -10,
			left: offsetLeft
		})


		ViewerContainer.css({
			top: offset.top + outerHeight,
			left: 0,
			display: "block",
			position: "absolute"
		});


		if(!isSameLine){

			$('html,body').stop().animate({scrollTop: offsetTop+'px'}, 300);

			arrowContainer.stop().animate({
				height: 600
			}, 300)

			ViewerContainer.stop().animate({
				height: 600
			}, 300, function(){
				console.log("afterAnimation")
				afterAnimation && afterAnimation();
			})

		}else{

			arrowContainer.css({
				height: 600
			})

			afterAnimation && afterAnimation();
		}

	}

	function handleClick(event){
		var target = $(this);

		// console.log("handleClick target",target);
		openItem(target);
		event.stopPropagation();
	}


	function bindEvent(elements){

		var elements = elements || getElements();

		console.log("elements", elements.length);

		elements.on("click", handleClick);

		elements.find("a").click(function(event){
			console.log("click")
			event.preventDefault();
		})


		elements.on("mouseenter mouseleave", function(event){

			var $this = $(this);

			if(event.type == "mouseenter"){
				$this.data("start-time", (new Date()).getTime());
			}

			if(event.type == "mouseleave"){

				var startTime = $this.data("start-time");

				if(startTime){

					var timeLeft = (new Date()).getTime() - startTime;
					var title = $this.data("title");

					$this.data("start-time", null);

					if(timeLeft > 1000){

						try{
							var dotitle = "Dwell:"+title;
							_paq.push(['trackPageView',dotitle]);
						}catch(e){
							console.log("e.stack:",e.stack);
						}
					}
				}
				
			}

			// console.log(event, this)
		});


		console.log("Viewer bindEvent")
	}

	bindEvent();


	this.beforeResized = function(){
		if(currentElement){
			console.log("beforeResized")
			if(arrowContainer){
				arrowContainer.remove();
			}
		}
	}

	this.afterResized = function(){
		if(currentElement){
			console.log("afterResized")
			resizeViewer(currentElement);
		}
		
	}

	this.empty = function(){
		if(!currentElement) return;

		if(isOpend(currentElement)){
			return emptyViewer(currentElement);
		}
	}

	this.close = function(){
		if(!currentElement) return;
		
		if(isOpend(currentElement)){
			return CloseViewer(currentElement);
		}
	}

	this.bindEvent = bindEvent


	function pushPiwik(title){

		try{
				
			_paq.push(['setDocumentTitle', title]);
			_paq.push(['trackLink', adLink, 'link']);
			_paq.push(['trackPageView']);

		}catch(e){

		}

	}


	// 查询 mp4  是否 存在
	function  coverString(str,filterstr){
	   var reg_str = str.toLowerCase();

	   if(reg_str.indexOf(filterstr)!=-1)
	   {
	   		return true;
	   }
	   return false;
	}
	// video.js 
	volumnMp4 = 0;
	vflv = 80;

	videoJsFalse = false;
	function mp4Player(curlVideo,src)
	{
		
		if(window.videoPlayer){
			window.videoPlayer.pause();
		}
		window.videoPlayer = videojs(curlVideo);
		window.videoPlayer.ready(function(){
			// var curlPlayer = this;
			if(volumnMp4>0)window.videoPlayer.volume(volumnMp4);
			console.log(" ready volumnMp4",volumnMp4);
		    this.play();
		});

		window.videoPlayer.on('volumechange',function(){
			volumnMp4 = window.videoPlayer.volume();

			vflv = volumnMp4*100;
			console.log("volumnMp4：",volumnMp4);
		})
	}

	swfHolderMp4 = '';

	contentContinerMp4 = '';

	swfobjectMp4 = '';
	flashvarsMp4 ='';
	paramsMp4='';
	attributesMP4='';
	playerPathMp4 = '';
	widthMp4 = 0;
	heightMp4 = 0;


	window.videojsError= function(){
		needPdiv = false;
		$('#viewer-views .viewer-view .view-left').empty();
		swfHolderMp4.appendTo(contentContinerMp4);
		swfobjectMp4.embedSWF(playerPathMp4, swfHolderMp4[0], widthMp4, heightMp4, '10.0.0','ckplayer6.1/expressInstall.swf', flashvarsMp4, paramsMp4, attributesMP4, function(result){
			    if(!result.success){
			        contentContinerMp4.html('请安装或开启Flash插件 <a target="_blank" href="http://get.adobe.com/cn/flashplayer/"><span style="color:red">安装Flash插件</span></a>');
		        }else{
			                // p_shade.css({ width:width, height:height }).appendTo(self.detailcontent);
			    }
		});
	}
	
}
