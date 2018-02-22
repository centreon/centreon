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

/**
 *
 * @param uploadField
 */
function checkSize(uploadField) {
    var messageWrapper = jQuery(".msg-wrapper");
    messageWrapper.hide();
    if (uploadField.files[0].size > 500000) {
        messageWrapper
            .show()
            .html('<span class="error-msg">File is too large (more than 500ko)</span>');
        uploadField.value = "";
    } else if (uploadField.selectedIndex === 0) {
        messageWrapper
            .show()
            .html('<span class="error-msg">File is empty</span>');
        uploadField.value = "";
    } else if (uploadField.files[0].type !== 'application/zip') {
        messageWrapper
            .show()
            .html('<span class="error-msg">Please update a .zip archive</span>');
        uploadField.value = "";
    }
}

/**
 *
 */
function submitForm() {
    jQuery(".loadingWrapper").css('display', 'block');
    var formData = new FormData();
    var importFiles = jQuery('#file')[0].files;
    var messageWrapper = jQuery(".msg-wrapper");
    messageWrapper.hide();
    formData.append('clapiImport', importFiles[0])
    formData.append('action', 'ajax_file_import');
    jQuery.ajax({
        type: "POST",
        url: "./modules/centreon-awie/core/launchImport.php",
        data: formData,
        cache: false,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function (data) {
            jQuery(".loadingWrapper").hide();
            if (data.error) {
                messageWrapper
                    .show()
                    .html('<span class="error-msg">' + data.error + '</span>');
            } else {
                messageWrapper
                    .show()
                    .html('<span class="success-msg">' + data.response + '</span>');
            }
        },
    });
    event.preventDefault();
}
