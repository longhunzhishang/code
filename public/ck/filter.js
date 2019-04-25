// Filter

~(function($){

    var filterForm = $('#filter_form');
    var filterItems = filterForm.find("[data-filter]");

    function handleClick(event){
    	var $this = $(this);

        // 隐藏 高级搜索框 finn 2016-12-14
        $('.advanced-search').css('display','none');
        $('#advanced').data('adv',1);

    	var type = $this.attr('type'), 
    		name = $this.attr('name');

        $this.addClass('cur').siblings().removeClass('cur');
        $('#'+type+'_filter_val').val(name);
        // result_div.text(obj.text());
        // finn 2016-12-14
        $('input[name="wd"]').val(query_word);

        filterForm.trigger('submit');
    }

    filterItems.on("click", handleClick)
    // var obj = $(this);
 //      var result_div = obj.parent().parent().find('.filter-item span');
 //      obj.hover(function() {
 //        obj.addClass('hover');
 //      }, function() {
 //        obj.removeClass('hover');
 //      });

 //      obj.click(function(event) {
 //        var type = obj.attr('type'), name = obj.attr('name');
 //          obj.addClass('cur').siblings().removeClass('cur');
 //          $('#'+type+'_filter_val').val(name);
 //          result_div.text(obj.text());
 //          self.filter_form.trigger('submit');
 //      });

})(jQuery);