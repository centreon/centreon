var timeout;

jQuery(function() {
        loadTop10();
    });

function loadTop10() {
    jQuery.ajax("./index.php", {
            success : function() {
                var h = jQuery("#engine-status").prop("scrollHeight") + 10;
                if(h){
                    parent.iResize(window.name, h);
                }else{
                    parent.iResize(window.name, 280);
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
