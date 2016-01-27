
function call_ajax_sync(data, call_ok_func, path_call) {
    var dataString = JSON.stringify(data);
    jQuery("body").css("cursor", "progress");

    jQuery.ajaxSetup({async:false});
    jQuery.post(path_call, {data: dataString}, call_ok_func)
    .success(function() { jQuery("body").css("cursor", "auto"); })
    .error(function() {
        jQuery("body").css("cursor", "auto");
    })
    .complete(function() { jQuery("body").css("cursor", "auto"); });
    jQuery.ajaxSetup({async:true});
}