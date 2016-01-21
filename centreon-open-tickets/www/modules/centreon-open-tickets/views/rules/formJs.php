<?php
/*
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
*/
?>
<script type='text/javascript'>
var ruleId = '<?php echo $ruleId; ?>';

function macro_init() {
    jQuery("input[id^='macroEmpty_']").each(function(id, el) {
        change_macro_input_type(el, true);
    });
    jQuery("#service_template_model_id").attr('onChange', 'loadMacro();');
    // Need to hide the span
    jQuery("input[id^='macroFrom_']").each(function(id, el) {
	jQuery(this).parent().attr('style', 'display: none');
    });
}

function macro_available_init() {
    jQuery("#command_command_id2").attr('onChange', 'macro_available_init();');

    var data = {
        "action": "macro-available",
        "command_id": jQuery("#command_command_id2").val(),
    }

    call_ajax_sync(data, $callback_macro_available);
}

jQuery(
    function() {
        macro_init();
        macro_available_init();
    }
);

var $callback_macro_available = function(res) {
    //jQuery("#rule_debug").html("Full response: " + res);
    var data_ret = JSON.parse(res);
    jQuery("#macro_available").html(data_ret['msg']);
    if (data_ret['code'] == 1) {
        jQuery("#macro_available").attr('style', 'color: #bc0d0d; font-weight:bold;');
    } else {
        jQuery("#macro_available").attr('style', 'color: #00a71f; font-weight:bold;');
    }
};

var $callback_loadmacro = function(res) {
    //jQuery("#rule_debug").html("Full response: " + res);
    var data_ret = JSON.parse(res);
    if (data_ret['code'] == 1) {
        jQuery("#rule_debug").html(data_ret['msg']);
    } else {        
        remove_sheepit('macro', data_ret['clone_count']);
        // Need to put clone data hide
        jQuery("#clone-count-macro").data("clone-count-macro", data_ret['clone_count']);
        jQuery("#clone-values-macro").data("clone-values-macro", JSON.parse(data_ret['clone_values']));
        reload_sheepit('macro');

        jQuery("input[id^='macroEmpty_']").each(function(id, el) {
           change_macro_input_type(el, true);
        });
    }
};

function loadMacro() {
    var array_clone_values = new Array();
    var pos = 0;
    
    // Create values
    jQuery("input[id^='macroEmpty_']").each(function(id, el) {
        var tmp = el.id.split('_');
        var macro_dom_id = tmp[1];
        
        array_clone_values[pos] = new Object();
        array_clone_values[pos]['macroName'] = jQuery("#macroName_" + macro_dom_id).val();
        array_clone_values[pos]['macroValue'] = jQuery("#macroValue_" + macro_dom_id).val();
        array_clone_values[pos]['macroFrom'] = jQuery("#macroFrom_" + macro_dom_id).val();
        if (jQuery("#macroEmpty_" + macro_dom_id + ':checked').val() !== undefined) {
            array_clone_values[pos]['macroEmpty'] = 1;
        } else {
            array_clone_values[pos]['macroEmpty'] = 0;
        }
        pos++;
    });
    
    var data = {
        "action": "macro-list",
        "rule_id": ruleId,
        "service_template_id": jQuery("#service_template_model_id").val(),
        "clone_values": array_clone_values
    }

    call_ajax_sync(data, $callback_loadmacro);
}

function call_ajax_sync(data, call_ok_func) {
    var dataString = JSON.stringify(data);
    jQuery("body").css("cursor", "progress");

    jQuery.ajaxSetup({async:false});
    jQuery.post('./modules/centreon-autodiscovery-server/views/rules/ajax/call.php', {data: dataString}, call_ok_func)
    .success(function() { jQuery("body").css("cursor", "auto"); })
    .error(function() {
            jQuery("body").css("cursor", "auto");
    })
    .complete(function() { jQuery("body").css("cursor", "auto"); });
    jQuery.ajaxSetup({async:true});
}

function change_macro_input_type(box, must_disable) {
    var tmp = box.id.split('_');
    var macro_dom_id = tmp[1];    
    var macro_value = jQuery("#macroValue_" + macro_dom_id).val();
    var macro_empty = jQuery("#macroEmpty_" + macro_dom_id + ':checked').val();
    
    if (must_disable === true) {
        // change backgroud color: when macro_empty not checked and macro_value not values
        if (macro_empty == undefined && (macro_value === undefined || macro_value == '')) {
            jQuery("#macroValue_" + macro_dom_id).removeAttr("style");
        } else {
            jQuery("#macroValue_" + macro_dom_id).attr("style", "background-color: #83ff7d;");
        }
        jQuery("#macroValue_" + macro_dom_id).keyup(function () {
            var value = jQuery(this).val();
            var tmp = jQuery(this).attr('id').split('_');
            var macro_dom_id = tmp[1];
            
            if (value === undefined || value == '') {
                jQuery(this).removeAttr("style");
            } else {
                jQuery("#macroEmpty_" + macro_dom_id).removeProp('checked');
                jQuery(this).attr("style", "background-color: #83ff7d;");
            }
        });
    } else {
        // checkbox had been checked
        if (macro_empty !== undefined) {
            jQuery("#macroValue_" + macro_dom_id).val("");
            jQuery("#macroValue_" + macro_dom_id).attr("style", "background-color: #83ff7d;");
        } else {
            jQuery("#macroValue_" + macro_dom_id).removeAttr("style");
        }
    }
}
</script>
