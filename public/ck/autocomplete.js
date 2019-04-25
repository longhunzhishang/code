var storage = storage || [];
var isFun = isFun==undefined?1:isFun;
function getHistory(){

    console.log(" localStorage isFun",isFun);

    if(isFun)
    {
      if(window.localStorage){

        var data = window.localStorage.keyword;

        if(data==undefined)
        {
          return [];
        }
        console.log("storage:",storage);
        console.log('history data', data.split(','));
        return data.split(',');

        // function wordsStore(key){
        //   var data = localStorage.getItem(key);
        //   this.words = (data && data.split(",")) || [];
        //   this.set = function(word){
        //       word = word.toLowerCase();
        //       var index = this.words.indexOf(word);
        //       if(index > -1){
        //         this.words.splice(index, index+1);
        //       }
        //       this.words.push(word);
        //       localStorage.setItem(key, this.words.join(','));
        //   }

        //   this.get = function(limit){
        //       return this.words.reverse();
        //   }
        // }

        // var words = new wordsStore('WORDS');

        // if(this.key_word){
        //   words.set(this.key_word);
        // }

        // return words.get();
    }
    }
    
}

function emptyHistory(){

    if(isFun)
    {
      if(window.localStorage){
        // finn 修改 2017-9-19
        window.localStorage.clear();
        // localStorage.removeItem('WORDS');
      }
    }
    
}
// finn 表单提交事件监听
// 没有限制 存储的数据 个数 可以优化
$("#sb_form").submit(function(){
  storage = [];
  console.log("storage:",storage);
  var key_word = $('input[name="wd"]').val();
  var data = window.localStorage.keyword;
  var is_exist = false;

  if(data==undefined)
  {
    storage.push(key_word);
    window.localStorage.keyword = storage;
    return true;
  }
  var data_list = data.split(',');
  if(data_list.length>0)
  {
    // 判断 是否 已经存在
    for (var i = data_list.length - 1; i >= 0; i--) {
      if(data_list[i]==key_word)
      {
        is_exist = true;
      }
    }
  }
  // 不存在 就把最新的关键词 放在首位
  if(!is_exist)
  {
    storage.push(key_word);
  }
  // 重新存储数据
  for (var i = data_list.length - 1; i >= 0; i--) {
      storage.push(data_list[i]);
  }


  if(isFun)
  {
    window.localStorage.keyword = storage;
    console.log("window.localStorage.keyword",window.localStorage.keyword);
  }

  
  


});

var autocomplete = function(){

  if(isFun)
  {
    var history = getHistory(), 
      input = $(".search-word"), 
      self = this;
    }else{
      var history = '', 
      input = $(".search-word"), 
      self = this;
    }
    

    if(!input.length) input = $(".sipt");

   
    input.unautocomplete();

    // /home/index/query

    var apiPath = U("index/query");
    input.autocomplete(apiPath, {
        multiple: false,
        dataType: "json",
        width: input.outerWidth() - 2,
        scrollHeight: 300,
        emptyHandler: function(success, select){

          console.log("history:",history.length,history);
          if(history.length){
              var data = [];
              var limit = 10;
              $.each(history, function(i, value){
                value = decodeURIComponent(value);
                if(i < limit){
                  data.push({
                      html : value+'<span class="operate">搜索历史</span>',
                      value : value,
                      select : function(){
                        window.location.href = U('search/ads')+'?wd='+value+'&ref=autocomplete';
                      }
                  });
                }
              })
              data.push({
                  html : '<span style="text-align:right;" class="fr">清空历史记录</span>',
                  value : '',
                  select : function(){
                      self.emptyHistory();
                      history = [];
                      select.emptyList();
                  }
              });
              success("", this.parse(data));
          }
        },
        parse: function(data) {
          return $.map(data, function(row) {
            return {
              data: row,
              value: row,
              result: row.host
            }
          });
        },
        formatItem: function(item) {
          // console.log(item);
          if(!item.type){
            item['html'] = item['html'];
            // 2017-6-28 item['html'] = "<span style='width:70px;color:black;display:inline-block;'></span>"+item['html']+"";
            // item['html'] = "<span style='width:70px;color:black;display:inline-block;'></span>"+item['html']+"";
          }else if(item.type=='2'){
            item['html'] = "<span style='width:70px;color:black;display:inline-block;'></span>"+item['html']+"";
            
          }else{
            item['html'] = "<span style='width:70px;color:black;display:inline-block;'>"+item['type']+"</span>"+item['html']+"</span>";
          }

          

          // window.localStorage.keyword = JSON.stringify(storage); //将storage转变为字符串存储
          // var job = JSON.parse(window.localStorage.keyword);
          // for(var i = 0; i < job.length; i++){
          //     job[i] = JSON.parse(job[i]);
          // }



        // console.log("window.localStorage.getItem",window.localStorage.keyword);

        // return false;


          return item['html'] || decodeURIComponent(item);
        },
        afterSelectedHandler: function(i){

          if(i.select){
            i.select();
          }else{

            if(!IS_LOGIN){
              window.location.href = U('search/ads')+'?wd='+i.name+'&ref=autocomplete';
              return ;
            }
            window.location.href = i.url;
          }
        }
    })
}
getHistory();
autocomplete();
