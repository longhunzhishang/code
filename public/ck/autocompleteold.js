function getHistory(){
    if(window.localStorage){
        function wordsStore(key){
          var data = localStorage.getItem(key);
          this.words = (data && data.split(",")) || [];
          this.set = function(word){
              word = word.toLowerCase();
              var index = this.words.indexOf(word);
              if(index > -1){
                this.words.splice(index, index+1);
              }
              this.words.push(word);
              localStorage.setItem(key, this.words.join(','));
          }

          this.get = function(limit){
              return this.words.reverse();
          }
        }

        var words = new wordsStore('WORDS');

        if(this.key_word){
          words.set(this.key_word);
        }

        return words.get();
    }
}

function emptyHistory(){
    if(window.localStorage){
      localStorage.removeItem('WORDS');
    }
}

var autocomplete = function(){
    var history = getHistory(), 
      input = $(".search-word"), 
      self = this;

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

          console.log("history:"+history.length);
          if(history.length){
              var data = [];
              var limit = 10;
              $.each(history, function(i, value){
                value = decodeURIComponent(value);
                if(i < limit){
                  data.push({
                      html : value,
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

autocomplete();