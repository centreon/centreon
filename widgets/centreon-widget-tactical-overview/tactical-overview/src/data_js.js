var timeout;

jQuery(function() {
        releadWidget();
    });

function releadWidget() {
    jQuery.ajax("./index.php", {
            success : function(htmlData) {
                jQuery("#infoAjax").html("");
                jQuery("#infoAjax").html(htmlData);
                var h = document.getElementById("tactical-overview").scrollHeight + 10;
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
        timeout = setTimeout(releadWidget, (autoRefresh * 1000));
    }
}
