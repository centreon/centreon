/*
 * CentreonListing - Reusable AJAX listing module
 *
 * Provides AJAX-driven data tables for Centreon configuration pages,
 * replacing legacy Smarty server-side rendered listings.
 *
 * Usage:
 *   var listing = new CentreonListing({ ... });
 *   listing.init();
 *
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 * Licensed under the Apache License, Version 2.0
 */

function CentreonListing(config) {

    // =====================================================================
    // Configuration (with defaults)
    // =====================================================================

    var cfg = {
        // DOM element IDs
        tableBodyId:        config.tableBodyId        || 'clTableBody',
        paginationTopId:    config.paginationTopId    || 'clPaginationTop',
        paginationBottomId: config.paginationBottomId || 'clPaginationBottom',
        searchInputId:      config.searchInputId      || 'clSearchInput',
        searchBtnId:        config.searchBtnId        || 'clSearchBtn',
        limitInputId:       config.limitInputId       || 'limit',

        // AJAX endpoints
        ajaxListUrl:   config.ajaxListUrl   || '',
        ajaxToggleUrl: config.ajaxToggleUrl || '',

        // Page identity
        pageId:      config.pageId      || '',
        writeAccess: config.writeAccess || false,
        storageKey:  config.storageKey  || 'cl_listing_limit',

        // Behaviour
        defaultLimit: config.defaultLimit || 30,
        autoRefresh:  config.autoRefresh !== undefined ? config.autoRefresh : 30000,
        emptyMessage: config.emptyMessage || 'No items found',

        // Column definitions
        // Each column: { id, header, align, render: function(row, listing) }
        columns: config.columns || [],

        // Row identity field (used for checkboxes, toggle, duplication)
        rowIdField: config.rowIdField || 'id',

        // Activate field name in row data (for toggle)
        activateField: config.activateField || 'activate',

        // Edit link builder: function(row) -> URL string
        editLink: config.editLink || null,

        // Options column renderer (toggle + dup input) — null to hide
        // function(row, listing) -> HTML string
        renderOptions: config.renderOptions || null,

        // Callbacks
        onDataLoaded: config.onDataLoaded || null,
    };

    // =====================================================================
    // Internal state
    // =====================================================================

    var self       = this;
    var csrfToken  = '';
    var currentNum = 0;
    var currentLimit = parseInt(localStorage.getItem(cfg.storageKey), 10) || cfg.defaultLimit;
    var currentSearch = '';
    var firstLoad  = true;

    // =====================================================================
    // Public: HTML escape utility
    // =====================================================================

    this.escape = function (str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };

    // =====================================================================
    // Public: get current CSRF token
    // =====================================================================

    this.getCsrfToken = function () {
        return csrfToken;
    };

    this.setCsrfToken = function (token) {
        csrfToken = token;
    };

    // =====================================================================
    // Public: get current state
    // =====================================================================

    this.getState = function () {
        return { num: currentNum, limit: currentLimit, search: currentSearch };
    };

    // =====================================================================
    // Internal: save / restore checked row checkboxes
    // =====================================================================

    function getCheckedIds() {
        var ids = [];
        jQuery('#' + cfg.tableBodyId + ' .cl-col-picker input[type=checkbox]:checked').each(function () {
            var name = jQuery(this).attr('name');
            if (name) ids.push(name);
        });
        return ids;
    }

    function restoreCheckedIds(ids) {
        for (var i = 0; i < ids.length; i++) {
            jQuery('#' + cfg.tableBodyId + ' input[name="' + ids[i] + '"]').prop('checked', true);
        }
    }

    // =====================================================================
    // Public: fetch data from the AJAX listing endpoint
    //   silent = true  → no loading indicator, no fade (used by auto-refresh)
    //   silent = false → fade-in animation on success
    // =====================================================================

    this.fetch = function (num, limit, search, silent) {
        currentNum    = num;
        currentLimit  = limit;
        currentSearch = search;

        var checkedIds = getCheckedIds();

        if (firstLoad) {
            jQuery('#' + cfg.tableBodyId).html(
                '<tr><td colspan="99" class="cl-loading">Loading...</td></tr>'
            );
        }

        jQuery.ajax({
            url: cfg.ajaxListUrl,
            type: 'GET',
            dataType: 'json',
            data: { search: search, num: num, limit: limit },
            success: function (data) {
                csrfToken = data.centreon_token || '';
                var tbody = jQuery('#' + cfg.tableBodyId);
                tbody.removeClass('cl-fade-in');
                self.renderRows(data.rows);
                self.renderPagination(data.total, data.num, data.limit);
                restoreCheckedIds(checkedIds);
                jQuery('#' + cfg.limitInputId).val(data.limit);
                if (!silent) {
                    void tbody[0].offsetWidth;
                    tbody.addClass('cl-fade-in');
                }
                firstLoad = false;
                if (cfg.onDataLoaded) cfg.onDataLoaded(data);
            },
            error: function () {
                if (firstLoad) {
                    jQuery('#' + cfg.tableBodyId).html(
                        '<tr><td colspan="99" style="text-align:center;padding:24px;color:#FF4A4A;">Error loading data</td></tr>'
                    );
                }
            }
        });
    };

    // =====================================================================
    // Public: render table body rows from data
    // =====================================================================

    this.renderRows = function (rows) {
        var tbody = jQuery('#' + cfg.tableBodyId);
        tbody.empty();

        if (!rows || rows.length === 0) {
            tbody.html('<tr><td colspan="99" style="text-align:center;padding:24px;color:#a7a9ac;">' +
                self.escape(cfg.emptyMessage) + '</td></tr>');
            return;
        }

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var rowId = row[cfg.rowIdField];

            // Checkbox cell
            var tr = '<tr>' +
                '<td class="cl-col-picker">' +
                    '<div class="md-checkbox md-checkbox-inline">' +
                        '<input type="checkbox" id="select_' + rowId + '" name="select[' + rowId + ']" />' +
                        '<label class="empty-label" for="select_' + rowId + '"></label>' +
                    '</div>' +
                '</td>';

            // Data columns
            for (var c = 0; c < cfg.columns.length; c++) {
                var col = cfg.columns[c];
                var align = col.align ? ' class="cl-col-' + col.align + '"' : '';
                var cellHtml = col.render ? col.render(row, self) : self.escape(row[col.id] || '');
                tr += '<td' + align + '>' + cellHtml + '</td>';
            }

            // Options column
            if (cfg.writeAccess && cfg.renderOptions) {
                tr += '<td class="cl-col-right"><div class="cl-options-cell">' +
                    cfg.renderOptions(row, self) +
                '</div></td>';
            }

            tr += '</tr>';
            tbody.append(tr);
        }
    };

    // =====================================================================
    // Public: render pagination controls
    // =====================================================================

    this.renderPagination = function (total, num, limit) {
        var totalPages = Math.ceil(total / limit);
        if (totalPages < 1) totalPages = 1;

        var startRow = total > 0 ? num * limit + 1 : 0;
        var endRow   = Math.min((num + 1) * limit, total);
        var info     = startRow + '-' + endRow + ' of ' + total;

        var html = '';

        // First / Previous
        if (num > 0) {
            html += '<a href="#" class="cl-page-nav" onclick="' + instanceName() + '.goToPage(0);return false;">'
                + '<img src="./img/icons/first_rewind.png" title="First page" /></a>';
            html += '<a href="#" class="cl-page-nav" onclick="' + instanceName() + '.goToPage(' + (num - 1) + ');return false;">'
                + '<img src="./img/icons/rewind.png" title="Previous page" /></a>';
        }

        // Page numbers
        var startPage = Math.max(0, num - 5);
        var endPage   = Math.min(totalPages - 1, num + 5);
        for (var i = startPage; i <= endPage; i++) {
            if (i === num) {
                html += '<span class="cl-page-current">' + (i + 1) + '</span>';
            } else {
                html += '<a href="#" class="cl-page-num" onclick="' + instanceName() + '.goToPage(' + i + ');return false;">'
                    + (i + 1) + '</a>';
            }
        }

        // Next / Last
        if (num < totalPages - 1) {
            html += '<a href="#" class="cl-page-nav" onclick="' + instanceName() + '.goToPage(' + (num + 1) + ');return false;">'
                + '<img src="./img/icons/fast_forward.png" title="Next page" /></a>';
            html += '<a href="#" class="cl-page-nav" onclick="' + instanceName() + '.goToPage(' + (totalPages - 1) + ');return false;">'
                + '<img src="./img/icons/end_forward.png" title="Last page" /></a>';
        }

        // Rows-per-page selector
        html += ' <select class="cl-limit-select" onchange="' + instanceName() + '.changeLimit(this.value);">';
        var limits = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        for (var j = 0; j < limits.length; j++) {
            var sel = limits[j] === limit ? ' selected' : '';
            html += '<option value="' + limits[j] + '"' + sel + '>' + limits[j] + '</option>';
        }
        html += '</select>';

        // Row count info
        html += '<span class="cl-page-info">' + info + '</span>';

        jQuery('#' + cfg.paginationTopId).html(html);
        jQuery('#' + cfg.paginationBottomId).html(html);
    };

    // =====================================================================
    // Public: navigation helpers
    // =====================================================================

    this.goToPage = function (num) {
        self.fetch(num, currentLimit, currentSearch);
    };

    this.changeLimit = function (limit) {
        var newLimit = parseInt(limit, 10);
        localStorage.setItem(cfg.storageKey, newLimit);
        self.fetch(0, newLimit, currentSearch);
    };

    // =====================================================================
    // Public: toggle activation (enable/disable via AJAX)
    // =====================================================================

    this.toggleActivation = function (toggle) {
        if (!cfg.ajaxToggleUrl) return;

        var rowId    = jQuery(toggle).data('row-id');
        var isChecked = toggle.checked;
        var action   = isChecked ? 's' : 'u';

        toggle.disabled = true;

        jQuery.ajax({
            url: cfg.ajaxToggleUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                sg_id: rowId,
                action: action,
                centreon_token: csrfToken
            },
            success: function (response) {
                if (response.centreon_token) {
                    csrfToken = response.centreon_token;
                }
                toggle.disabled = false;
            },
            error: function () {
                toggle.checked = !isChecked;
                toggle.disabled = false;
            }
        });
    };

    // =====================================================================
    // Public: select / deselect all row checkboxes
    // =====================================================================

    this.checkUncheckAll = function (masterCheckbox) {
        var table = jQuery(masterCheckbox).closest('table');
        table.find('tbody .cl-col-picker input[type=checkbox]').each(function () {
            jQuery(this).prop('checked', masterCheckbox.checked);
        });
    };

    // =====================================================================
    // Public: initialise (first load, bind events, auto-refresh)
    // =====================================================================

    this.init = function () {
        // Read initial search value
        currentSearch = jQuery('#' + cfg.searchInputId).val() || '';

        // Search button
        jQuery('#' + cfg.searchBtnId).on('click', function () {
            currentSearch = jQuery('#' + cfg.searchInputId).val();
            self.fetch(0, currentLimit, currentSearch);
        });

        // Search on Enter key
        jQuery('#' + cfg.searchInputId).on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                jQuery('#' + cfg.searchBtnId).click();
            }
        });

        // Initial data load
        self.fetch(0, currentLimit, currentSearch);

        // Auto-refresh
        if (cfg.autoRefresh > 0) {
            setInterval(function () {
                self.fetch(currentNum, currentLimit, currentSearch, true);
            }, cfg.autoRefresh);
        }
    };

    // =====================================================================
    // Internal: resolve the global variable name for onclick handlers
    // =====================================================================

    var _instanceName = config.instanceName || null;

    function instanceName() {
        if (_instanceName) return _instanceName;
        // Fallback: search window properties
        for (var key in window) {
            if (window[key] === self) {
                _instanceName = key;
                return key;
            }
        }
        return 'this';
    }
}
