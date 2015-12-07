var widgetId = '$widgetId';
var autoRefresh = '$autoRefresh';
var timeout;

jQuery(function() {
	console.log("jQuery function");
        loadTop10();
    });

function loadTop10() {
    jQuery.ajax("./ajax.php", {
	    success : function(htmlData) {
		jQuery("#infoAjax").html("");
		jQuery("#infoAjax").html(htmlData);
		var h = document.getElementById("top-10-cpu").scrollHeight + 10;
		if(h){
		    parent.iResize(window.name, h);
		}else{
		    parent.iResize(window.name, 200);
		}
	    }
	});
    if (autoRefresh) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadTop10, (autoRefresh * 1000));
    }
}