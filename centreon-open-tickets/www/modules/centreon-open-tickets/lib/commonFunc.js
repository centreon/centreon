
function call_ajax_sync(data, call_ok_func, path_call) {
    var dataString = JSON.stringify(data);
    jQuery("body").css("cursor", "progress");

    jQuery.ajaxSetup({async:false});
    jQuery.post(path_call, {data: dataString}, call_ok_func)
    .done(function() { jQuery("body").css("cursor", "auto"); })
    .fail(function() {
        jQuery("body").css("cursor", "auto");
    })
    .always(function() { jQuery("body").css("cursor", "auto"); });
    jQuery.ajaxSetup({async:true});
}

function call_ajax_async(data, call_ok_func, path_call) {
    var dataString = JSON.stringify(data);
    jQuery("body").css("cursor", "progress");

    jQuery.ajaxSetup({async:true});
    jQuery.post(path_call, {data: dataString}, call_ok_func)
    .done(function() { jQuery("body").css("cursor", "auto"); })
    .fail(function() {
        jQuery("body").css("cursor", "auto");
    })
    .always(function() { jQuery("body").css("cursor", "auto"); });
}