/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
function loadPage()
{
    jQuery.ajax("./src/index.php?widgetId=" + widgetId + "&page=" + pageNumber, {
        success: function (htmlData) {
            jQuery("#serviceMonitoringTable").empty().append(htmlData).append(function () {
                var horizontalScrollHeight = 0;
                if (jQuery("#serviceMonitoringTable").outerWidth() < jQuery("#serviceMonitoringTable").get(0).scrollWidth) {
                    horizontalScrollHeight = 20;
                }
                var h = jQuery("#serviceMonitoringTable").prop("scrollHeight") + horizontalScrollHeight;
                parent.iResize(window.name, h);
            });
            jQuery('.checkall').on('change', function () {
                var chck = this.checked;
                jQuery(this).parents().find(':checkbox').each(function () {
                    jQuery(this).prop('checked', chck);
                    clickedCb[jQuery(this).attr('id')] = chck;
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

/**
 * Load toolbar
 */
function loadToolBar()
{
    jQuery("#toolBar").load(
        "./src/toolbar.php",
        {
            widgetId: widgetId
        }
    );
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
    // get checked resource list from local storage
    $(".selection").each(function () {
        var itemSaved = 'w_sm_' + $(this).attr('id');
        let toRemove = 'w_sm_selection_'
        // each selected resource is like idHost;idSvc
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
