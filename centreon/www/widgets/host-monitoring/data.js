/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * Load Page
 */
function loadPage() {
    jQuery.ajax("./src/index.php?widgetId=" + widgetId + "&page=" + pageNumber, {
        success: function (htmlData) {
            jQuery("#hostMonitoringTable").empty().append(htmlData).append(function () {
                var h = jQuery("#hostMonitoringTable").prop("scrollHeight");
                parent.iResize(window.name, h);
            });
            jQuery('.checkall').on('change', function () {
                var chck = this.checked;
                $(this).parents().find(':checkbox').each(function () {
                    $(this).prop('checked', chck);
                    clickedCb[$(this).attr('id')] = chck;
                    try {
                        localStorage.setItem('w_hm_' + $(this).attr('id'), chck ? '1' : '0');
                    } catch (e) {
                        console.warn('Failed to save checkbox state:', e);
                    }
                });
            });
        }
    });
    if (autoRefresh) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadPage, (autoRefresh * 1000));
    }
}

/*
 * Load toolbar
 */
function loadToolBar() {
    jQuery("#toolBar").load("./src/toolbar.php", { widgetId: widgetId });
}

jQuery(function () {
    loadToolBar();
    loadPage();
});

/**
 * retrieve selected resources
 */
function exportChecked() {
    let exportList = '';
    let selection = jQuery('.selection');
    // get checked resource list from local storage
    $(".selection").each(function () {
        let itemSaved = 'w_hm_' + $(this).attr('id');
        let toRemove = 'w_hm_selection_'
        if (localStorage.getItem(itemSaved)) {
            exportList += itemSaved.substring(toRemove.length, itemSaved.length) + ',';
        }
    });
    // remove last comma
    exportList = exportList.substring(0, exportList.length - 1);

    // if at least one resource is found, redirect to the export.php
    if (0 < exportList.length) {
        window.location.href = './src/export.php?widgetId=' + widgetId + '&list=' + exportList;
    } else {
        alert('Please select at least one resource');
    }
}
