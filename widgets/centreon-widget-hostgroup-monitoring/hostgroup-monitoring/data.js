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
            jQuery("#hgMonitoringTable").html("");
            jQuery("#hgMonitoringTable").html(htmlData);
            var h = document.getElementById("hgMonitoringTable").scrollHeight + 10;
            parent.iResize(window.name, h);
            jQuery("#hgMonitoringTable").find("img, style, script, link").load(function(){
                var h = document.getElementById("hgMonitoringTable").scrollHeight + 10;
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