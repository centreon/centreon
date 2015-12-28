jQuery(function () {
    loadPage();
});

/**                                                                                                                                                                                                          
 * Load page                                                                                                                                                                                                 
 */
function loadPage()
{
    jQuery.ajax("./src/index.php?widgetId=" + widgetId + "&page=" + pageNumber, {
        success: function (htmlData) {
            jQuery("#hgMonitoringTable").html("");
            jQuery("#hgMonitoringTable").html(htmlData);
            var h = document.getElementById("hgMonitoringTable").scrollHeight + 10;
            ResizeFrame(window.name, h);
            jQuery("#hgMonitoringTable").find("img, style, script, link").load(function () {
                var h = document.getElementById("hgMonitoringTable").scrollHeight + 10;
                ResizeFrame(window.name, h);
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

function ResizeFrame(ifrm, height)
{
    if (height < 150) {
            height = 150;
    }
    jQuery("[name="+ifrm+"]").height(height);
}

