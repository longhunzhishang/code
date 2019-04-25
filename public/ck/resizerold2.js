function Resizer(conf) {

    conf = conf || {};

    var container = conf.container || $("#creatives-container");

    var avaibleWidth = container.width();
    var tempWidth = 0,
        tempItem = [];
    var row = 0;


    function getItems() {
        var items = conf.items || $(conf.selector);
        return items;
    }

    function caluateItem(tempItem) {
        // console.log(tempItem);
        var maxHeight = 0,
            totalHeight = 0,
            totalWidth = 0;
        $.each(tempItem, function(index, val) {
            var item = tempItem[index];

            if (item.height > maxHeight) {
                maxHeight = item.height;
            }
            totalWidth += item.width;
            totalHeight += item.height;
        })

        var rowWidthRaito = totalWidth / avaibleWidth;

        if (rowWidthRaito < 0.1 || tempItem.length < 3) {
            // return;
        }

        maxHeight = totalHeight / tempItem.length;
        // maxHeight = maxHeight > 400 ? 400 : maxHeight;
        var newWidthTotal = 0;

        $.each(tempItem, function(index, val) {
            var item = tempItem[index];
            var padding = Math.floor((maxHeight - item.height) / 2);
            var wRatio = item.width / item.height;

            var newD = {
                width: item.height > maxHeight ? Math.floor(wRatio * maxHeight) : item.width,
                height: item.height > maxHeight ? maxHeight : item.height,
                paddingTop: padding,
                paddingBottom: padding
            }

            newWidthTotal += newD.width;

            if (index > 0) {
                newWidthTotal += 12;
            }

            item.element.attr("data-row", row);

            // item.element.css(newD)

            item.elementCss.width = newD.width;
            item.elementCss.height = newD.height;
            item.elementCss.paddingTop = newD.paddingTop;
            item.elementCss.paddingBottom = newD.paddingBottom;

            // item.img.css({
            //    width: newD.width,
            //    height: newD.height,
            // })

            item.imgCss.width = newD.width;
            item.imgCss.height = newD.width;


            item.width = newD.width;
            item.height = newD.height;
            // totalWidth += item.width;
            // totalHeight += item.height;
        })


        var leftWidth = Math.abs(avaibleWidth - newWidthTotal);

        console.log("leftWidth",leftWidth);

        // 剩余宽度的平均值
        var averageWidth = Math.floor(leftWidth / tempItem.length);

        var tempLeftWidth = leftWidth;

        // console.log("row="+row, "newWidthTotal="+newWidthTotal, "avaibleWidth="+avaibleWidth, "leftWidth="+leftWidth, "averageWidth="+averageWidth);

        var totalItem = tempItem.length - 1;
        var totalHeightI = 0,
            isSameHeight = true,
            tempHeight;



        var needResizeItems = [];

        $.each(tempItem, function(index, val) {

            var item = tempItem[index];
            var width = item.width;
            var height = item.height;

            var hRatio = height / width;
            var wRatio = width / height;

            var newWidth = width + averageWidth;

            if (newWidth > item.originalWidth) {
                newWidth = item.originalWidth;
                // console.log("expand too much")
            } else {
                tempLeftWidth -= averageWidth;
            }

            var newHeight = Math.floor(hRatio * newWidth);

            // 高还没盛满
            if (newHeight < maxHeight) {
                item.canExpandWidth = Math.floor(wRatio * maxHeight) - newWidth;
                needResizeItems.push(item);
            }else{
                item.elementCss.height = maxHeight;
            }

            // item.element.css({
            //    width: newWidth
            // })

            item.elementCss.width = newWidth;

            var leftHeight = height > newHeight ? height - newHeight : newHeight - height;
            var marginTop = height < newHeight ? 0 : leftHeight > 0 ? Math.floor(leftHeight / 2) : 0;

            item.imgCss.width = newWidth;
            item.imgCss.height = newHeight;
            item.imgCss.marginTop = marginTop;
            // item.img.css({
            //    width: newWidth,
            //    height: newHeight,
            //    marginTop: marginTop
            // })
            if (tempHeight && tempHeight != newHeight) {
                isSameHeight = false;
            }

            tempHeight = newHeight;
            // console.log("img new", newHeight, height, "row="+row, "index="+index)
            if (totalItem == index) {
                item.elementCss.marginRight = 0;
                // item.element.css({
                //    marginRight: 0
                // })
            }
            // totalWidth += item.width;
            // totalHeight += item.height;
        })


        console.log("still left", tempLeftWidth, row);

        var apc = Math.floor(tempLeftWidth / tempItem.length);

        console.log(apc * tempItem.length, tempLeftWidth);


        var tempLength = tempItem.length - 1;


        var tWidth = 0;

        $.each(tempItem, function(index, val) {
            var item = tempItem[index];

            // console.log(index < tempLength, index, tempLength)
            // if(index < tempLength){
            // 
                
                console.log("before", item.elementCss.width, apc, tempLeftWidth)

                console.log("row:",row);
                item.elementCss.width = item.elementCss.width + apc;

                tWidth += item.elementCss.width;
                tWidth += item.elementCss.marginRight;

                var leftWidth = avaibleWidth - tWidth;


                console.log("leftWidth:",leftWidth);

                if(leftWidth < 0){
                    item.elementCss.width += leftWidth;
                }

                item.elementCss.textAlign = "center";

                console.log("afert", item.elementCss.width, apc, tempLeftWidth)
            // }
            
        })


        if (isSameHeight) {

            $.each(tempItem, function(index, val) {
                var item = tempItem[index];
                item.elementCss.height = tempHeight;
                // item.element.css({
                //    height: tempHeight
                // })
                item.imgCss.marginTop = 0;
                // item.img.css({
                //    marginTop: 0
                // })
            })
        }

        // 应用CSS

        $.each(tempItem, function(index, val) {

            var item = tempItem[index];
            var isNative = item.type == "native";
            // console.log("row="+row, "col="+index, item.elementCss.paddingTop, item.elementCss.paddingBottom);
            item.element.attr("style", "");
            if (!isNative) {
                item.img.attr("style", "");
            }

            item.element.css(item.elementCss);
            // console.log(item.type);
            if (!isNative) {
                item.icons.css({
                    top: item.imgCss.marginTop
                });
                item.img.css(item.imgCss);
            }
        })
        row++;
    }


    function makeSizer() {
        row = 0;

        var items = getItems();

        $.each(items, function(index, el) {

            var item = items.eq(index);
            var width = item.data("thumb-width");
            var height = item.data("thumb-height");
            var type = item.data("type");
            var originalWidth = item.data("width");
            var originalHeight = item.data("height");

            // console.log(height, width);
            tempWidth += width + 12;

            tempItem.push({
                width: width,
                height: height,
                originalWidth: originalWidth,
                originalHeight: originalHeight,
                element: item,
                type: type,
                img: item.find("img"),
                icons: item.find(".icons"),
                elementCss: {
                    marginRight: 12,
                    paddingTop: 0,
                    paddingBottom: 0
                },
                imgCss: {
                    marginTop: 0
                }
            });

            if (tempWidth > avaibleWidth || index == (items.length - 1)) {
                // var date = Date.now();
                caluateItem(tempItem);
                // console.log("spend", Date.now() - date);
                tempWidth = 0;
                tempItem = [];
            }
        });
    }


    makeSizer();

    this.reload = function() {


    }

    this.resize = function() {
        avaibleWidth = container.width();
        console.log("resized")
        makeSizer();
    }
}