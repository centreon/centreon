jQuery(function() {
    loadTop10();
});

function loadTop10() {
    jQuery.ajax("./src/table.php?widgetId=" + widgetId, {
        success : function(htmlData) {
            jQuery("#Grid-map").empty().append(htmlData).append(function() {
                var h = document.getElementById("Grid-map").scrollHeight;
                parent.iResize(window.name, h);
            });
        }
    });
    if (autoRefresh) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadTop10, (autoRefresh * 1000));
    }
}
