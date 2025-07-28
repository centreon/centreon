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

jQuery(function () {
    if (nbRows > itemsPerPage) {
        $("#pagination").pagination(nbRows, {
            items_per_page: itemsPerPage,
            current_page: pageNumber,
            num_edge_entries : _num_edge_entries,
            num_display_entries : _num_display_entries,
            callback : paginationCallback
        }).append("<br/>");
    }
    
    $(".selection").each(function() {
        var curId = $(this).attr('id');
        if (localStorage.getItem('w_hm_' + curId)) {
            jQuery(this).prop('checked', true);
        }
    });

    jQuery(".selection").on('click', function() {
        var curId = jQuery(this).attr('id');
        var state = jQuery(this).prop('checked');
        /**
         key = w_hm_[ID]
         w = widget
         hm = host monitoring
         */
        if (state == true) {
            localStorage.setItem('w_hm_' + curId, '1');
        } else {
            localStorage.removeItem('w_hm_' + curId);
        }
    });

    function paginationCallback(page_index, jq)
    {
        if (page_index != pageNumber) {
            pageNumber = page_index;
            clickedCb = new Array();
            loadPage();
        }
    }
});

