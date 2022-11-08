/*
 * Copyright 2005-2020 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
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
