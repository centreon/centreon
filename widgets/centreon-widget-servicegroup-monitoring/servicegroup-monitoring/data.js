jQuery(function() {
        loadPage();
    });

/**                                                                                                                                                                                                          
 * Load page                                                                                                                                                                                                 
 */
function loadPage()
{
    jQuery.ajax("./src/index.php?widgetId="+widgetId+"&page="+pageNumber, {
	    success : function(htmlData) {
		jQuery("#sgMonitoringTable").html("");
		jQuery("#sgMonitoringTable").html(htmlData);
		var h = document.getElementById("sgMonitoringTable").scrollHeight + 10;
		parent.iResize(window.name, h);
		jQuery("#sgMonitoringTable").find("img, style, script, link").load(function(){
			var h = document.getElementById("sgMonitoringTable").scrollHeight + 10; 
			parent.iResize(window.name, h);
		    });

	    }
	});
    if (autoRefresh) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadPage, (autoRefresh * 1000));
    }
}
