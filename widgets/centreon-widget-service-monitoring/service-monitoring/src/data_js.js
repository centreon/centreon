/**
 * Copyright 2005-2019 Centreon
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

jQuery(function () {
    if (nbRows > itemsPerPage) {
        jQuery("#pagination").pagination(
            nbRows, {
                items_per_page: itemsPerPage,
                current_page: pageNumber,
                num_edge_entries: _num_edge_entries,
                num_display_entries: _num_display_entries,
                callback: paginationCallback
            }
        ).append("<br/>");
    }

    jQuery(".selection").on('click', function() {
        var curId = jQuery(this).attr('id');
        var state = jQuery(this).prop('checked');
        /**
        key = w_sm_[ID]
        w = widget
        sm = service monitoring
       */
        if( state == true) {
            localStorage.setItem('w_sm_' + curId, '1');
        } else {
            localStorage.removeItem('w_sm_' + curId);
        }
    });

    jQuery(".selection").each(function() {
        var curId = jQuery(this).attr('id');
        if (localStorage.getItem('w_sm_' + curId)) {
            jQuery(this).prop('checked', true);
        }
    });

    function paginationCallback(page_index, jq) {
        if (page_index != pageNumber) {
            pageNumber = page_index;
            clickedCb = new Array();
            loadPage();
        }
    }
});
