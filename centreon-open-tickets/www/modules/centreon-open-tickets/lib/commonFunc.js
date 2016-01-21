
function call_ajax_sync(data, call_ok_func) {
    var dataString = JSON.stringify(data);
    jQuery("body").css("cursor", "progress");

    jQuery.ajaxSetup({async:false});
    jQuery.post('./modules/centreon-open-tickets/views/rules/ajax/call.php', {data: dataString}, call_ok_func)
    .success(function() { jQuery("body").css("cursor", "auto"); })
    .error(function() {
        jQuery("body").css("cursor", "auto");
    })
    .complete(function() { jQuery("body").css("cursor", "auto"); });
    jQuery.ajaxSetup({async:true});
}