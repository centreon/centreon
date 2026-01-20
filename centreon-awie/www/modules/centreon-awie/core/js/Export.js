/**
 * Copyright 2018 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
