var timeout;

jQuery(function() {
    reloadWidget();
});

function reloadWidget() {
    jQuery.ajax("./index.php", {
            success : function(htmlData) {
                jQuery("#infoAjax").html("");
                jQuery("#infoAjax").html(htmlData);
                var h = jQuery("#tactical-overview").prop("scrollHeight") + 10;
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
        timeout = setTimeout(reloadWidget, (autoRefresh * 1000));
    }
}
