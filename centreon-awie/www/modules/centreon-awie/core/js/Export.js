function selectFilter(selected) {
    var elem = jQuery('[name = "export_' + selected + '[' + selected + '_filter]"]');
    if (jQuery('[name = "export_' + selected + '[' + selected + ']"').prop('checked')) {
        elem.attr('disabled', 'disabled');
    } else {
        elem.removeAttr('disabled');
    }
}

function submitForm() {
    var data = jQuery("#exportForm").serializeArray();
    jQuery(".loadingWrapper").css('display', 'block');
    jQuery.ajax({
        type: "POST",
        url: "./modules/centreon-awie/core/generateExport.php",
        data: data,
        success: function (data) {
            var errorMsg = '';
            oData = JSON.parse(data);
            jQuery('#pathFile').val(oData.fileGenerate);
            delete oData.fileGenerate;
            errorMsg += oData.error;
            errorMsg = errorMsg.replace(",", "\n");
            if (errorMsg.length !== 0 && errorMsg !== 'undefined') {
                alert(errorMsg);
            }
            jQuery("#downloadForm").submit();
            jQuery(".loadingWrapper").css('display', 'none');
        },
    });
    event.preventDefault();
}
