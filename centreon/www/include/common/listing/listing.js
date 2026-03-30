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

        // Live search: fetch as the user types (debounced) instead of requiring
        // a Search button. Standard for the single search field.
        liveSearch:      config.liveSearch !== undefined ? config.liveSearch : false,
        liveSearchDelay: config.liveSearchDelay || 300,

        // Additional search field ids that also trigger a debounced fetch on
        // input (multi-criteria pages, e.g. host + service). Their values are
        // sent through extraParams.
        liveSearchFields: config.liveSearchFields || [],

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

        // Extra GET parameters appended to every fetch request
        extraParams: config.extraParams || {},

        // Show row checkboxes (default true)
        showCheckboxes: config.showCheckboxes !== undefined ? config.showCheckboxes : true,

        // Field name in row data that indicates a locked/non-selectable row (checkbox disabled)
        lockedField: config.lockedField || null,

        // Infinite scroll mode (default false) — appends rows on scroll instead of pagination
        infiniteScroll: config.infiniteScroll || false,
        infiniteScrollBuffer: config.infiniteScrollBuffer || 600, // px from bottom to trigger load
        scrollContainerId: config.scrollContainerId || null, // custom scroll container for infinite scroll

        // Callbacks
        onDataLoaded: config.onDataLoaded || null,
    };

    // =====================================================================
    // Internal state
    // =====================================================================

    var self       = this;
    var csrfToken  = '';
    var firstLoad  = true;

    // Restore state from sessionStorage (survives navigation, cleared on browser close)
    var stateKey   = 'cl_state_' + cfg.storageKey;
    var savedState = null;
    try { savedState = JSON.parse(sessionStorage.getItem(stateKey)); } catch(e) {}

    var currentNum    = (savedState && typeof savedState.num === 'number') ? savedState.num : 0;
    var currentLimit  = parseInt(localStorage.getItem(cfg.storageKey), 10) || (savedState && savedState.limit) || cfg.defaultLimit;
    var currentSearch = (savedState && savedState.search) || '';

    // Infinite scroll state
    var isLoadingMore  = false;
    var allLoaded      = false;
    var totalLoaded    = 0;

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
        var isAppend = cfg.infiniteScroll && num > 0 && !firstLoad;

        currentNum    = num;
        currentLimit  = limit;
        currentSearch = search;

        // Persist state to sessionStorage (including extra filter params + select2 labels)
        try {
            var extra = typeof cfg.extraParams === 'function' ? cfg.extraParams() : cfg.extraParams;
            var labels = {};
            if (extra) {
                jQuery.each(extra, function(key, val) {
                    if (val) {
                        var el = jQuery('#' + key);
                        if (el.length && el.is('select')) {
                            labels[key] = el.find('option:selected').text() || val;
                        }
                    }
                });
            }
            sessionStorage.setItem(stateKey, JSON.stringify({ num: num, limit: limit, search: search, extra: extra || {}, labels: labels }));
        } catch(e) {}

        var checkedIds = getCheckedIds();

        if (firstLoad) {
            jQuery('#' + cfg.tableBodyId).html(
                '<tr><td colspan="99" class="cl-loading">Loading...</td></tr>'
            );
        }

        if (isAppend) {
            isLoadingMore = true;
            // Show loading indicator at bottom
            jQuery('#' + cfg.tableBodyId).find('.cl-infinite-loader').remove();
            jQuery('#' + cfg.tableBodyId).append(
                '<tr class="cl-infinite-loader"><td colspan="99" style="text-align:center;padding:12px;color:#a7a9ac;">Loading more...</td></tr>'
            );
        }

        jQuery.ajax({
            url: cfg.ajaxListUrl,
            type: 'GET',
            dataType: 'json',
            data: jQuery.extend({ search: search, num: num, limit: limit }, typeof cfg.extraParams === 'function' ? cfg.extraParams() : cfg.extraParams),
            success: function (data) {
                csrfToken = data.centreon_token || '';
                var tbody = jQuery('#' + cfg.tableBodyId);

                if (cfg.infiniteScroll && isAppend) {
                    // Remove loader row
                    tbody.find('.cl-infinite-loader').remove();
                    // Append new rows
                    self.appendRows(data.rows);
                    totalLoaded += data.rows.length;
                    allLoaded = totalLoaded >= data.total;
                    isLoadingMore = false;
                    // Update info
                    self.renderScrollInfo(totalLoaded, data.total);
                } else {
                    tbody.removeClass('cl-fade-in');
                    self.renderRows(data.rows);
                    if (cfg.infiniteScroll) {
                        totalLoaded = data.rows.length;
                        allLoaded = totalLoaded >= data.total;
                        isLoadingMore = false;
                        self.renderScrollInfo(totalLoaded, data.total);
                    } else {
                        self.renderPagination(data.total, data.num, data.limit);
                    }
                    restoreCheckedIds(checkedIds);
                    jQuery('#' + cfg.limitInputId).val(data.limit);
                    if (!silent) {
                        void tbody[0].offsetWidth;
                        tbody.addClass('cl-fade-in');
                    }
                }
                firstLoad = false;
                // Reset bulk action dropdowns to default
                jQuery('select[name="o1"], select[name="o2"]').prop('selectedIndex', 0);
                if (cfg.onDataLoaded) cfg.onDataLoaded(data);
            },
            error: function () {
                isLoadingMore = false;
                jQuery('#' + cfg.tableBodyId).find('.cl-infinite-loader').remove();
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
        // Allow custom renderRows from config
        if (typeof cfg.renderRows === 'function') {
            cfg.renderRows.call(self, rows);
            return;
        }

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

            // Checkbox cell (optional)
            var isLocked = cfg.lockedField && row[cfg.lockedField];
            var tr = '<tr>';
            if (cfg.showCheckboxes) {
                var disabledAttr = isLocked ? ' disabled' : '';
                tr += '<td class="cl-col-picker">' +
                    '<div class="md-checkbox md-checkbox-inline">' +
                        '<input type="checkbox" id="select_' + rowId + '" name="select[' + rowId + ']" value="1"' + disabledAttr + ' />' +
                        '<label class="empty-label" for="select_' + rowId + '"></label>' +
                    '</div>' +
                '</td>';
            }

            // Data columns
            for (var c = 0; c < cfg.columns.length; c++) {
                var col = cfg.columns[c];
                var align = col.align ? ' class="cl-col-' + col.align + '"' : '';
                var cellHtml = col.render ? col.render(row, self) : self.escape(row[col.id] || '');
                tr += '<td' + align + '>' + cellHtml + '</td>';
            }

            // Options column (always rendered; if read-only, toggle is disabled and dup input hidden)
            if (cfg.renderOptions) {
                var optHtml = cfg.renderOptions(row, self, cfg.writeAccess);
                if (!cfg.writeAccess) {
                    // Disable toggles and hide dup inputs for read-only users
                    optHtml = optHtml.replace(/onchange="[^"]*"/g, '').replace(/<input[^>]*cl-dup-input[^>]*>/g, '');
                    optHtml = optHtml.replace(/<input type="checkbox"/g, '<input type="checkbox" disabled');
                }
                tr += '<td class="cl-col-right"><div class="cl-options-cell">' + optHtml + '</div></td>';
            }

            tr += '</tr>';
            tbody.append(tr);
        }
    };

    // =====================================================================
    // Public: append rows (infinite scroll mode — adds to existing tbody)
    // =====================================================================

    this.appendRows = function (rows) {
        if (!rows || rows.length === 0) return;

        var tbody = jQuery('#' + cfg.tableBodyId);
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var rowId = row[cfg.rowIdField];
            var isLocked = cfg.lockedField && row[cfg.lockedField];

            var tr = '<tr>';
            if (cfg.showCheckboxes) {
                var disabledAttr = isLocked ? ' disabled' : '';
                tr += '<td class="cl-col-picker">' +
                    '<div class="md-checkbox md-checkbox-inline">' +
                        '<input type="checkbox" id="select_' + rowId + '" name="select[' + rowId + ']" value="1"' + disabledAttr + ' />' +
                        '<label class="empty-label" for="select_' + rowId + '"></label>' +
                    '</div>' +
                '</td>';
            }

            for (var c = 0; c < cfg.columns.length; c++) {
                var col = cfg.columns[c];
                var align = col.align ? ' class="cl-col-' + col.align + '"' : '';
                var cellHtml = col.render ? col.render(row, self) : self.escape(row[col.id] || '');
                tr += '<td' + align + '>' + cellHtml + '</td>';
            }

            if (cfg.renderOptions) {
                var optHtml = cfg.renderOptions(row, self, cfg.writeAccess);
                if (!cfg.writeAccess) {
                    optHtml = optHtml.replace(/onchange="[^"]*"/g, '').replace(/<input[^>]*cl-dup-input[^>]*>/g, '');
                    optHtml = optHtml.replace(/<input type="checkbox"/g, '<input type="checkbox" disabled');
                }
                tr += '<td class="cl-col-right"><div class="cl-options-cell">' + optHtml + '</div></td>';
            }

            tr += '</tr>';
            tbody.append(tr);
        }
    };

    // =====================================================================
    // Public: render scroll info (infinite scroll mode)
    // =====================================================================

    this.renderScrollInfo = function (loaded, total) {
        var html = '<span class="cl-page-info">' + loaded + ' / ' + total + '</span>';
        if (!allLoaded) {
            html += '<span style="color:#a7a9ac;margin-left:8px;font-size:11px;">Scroll for more</span>';
        }
        jQuery('#' + cfg.paginationTopId).html(html);
        jQuery('#' + cfg.paginationBottomId).html(html);
    };

    // =====================================================================
    // Public: reset infinite scroll (used on filter change)
    // =====================================================================

    this.resetScroll = function () {
        totalLoaded = 0;
        allLoaded = false;
        isLoadingMore = false;
        currentNum = 0;
        self.fetch(0, currentLimit, currentSearch);
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
            html += '<a href="#" class="cl-page-nav" title="First page" onclick="' + instanceName() + '.goToPage(0);return false;">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 6 11 12 17 18"/><line x1="7" y1="6" x2="7" y2="18"/></svg></a>';
            html += '<a href="#" class="cl-page-nav" title="Previous page" onclick="' + instanceName() + '.goToPage(' + (num - 1) + ');return false;">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 6 9 12 15 18"/></svg></a>';
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
            html += '<a href="#" class="cl-page-nav" title="Next page" onclick="' + instanceName() + '.goToPage(' + (num + 1) + ');return false;">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg></a>';
            html += '<a href="#" class="cl-page-nav" title="Last page" onclick="' + instanceName() + '.goToPage(' + (totalPages - 1) + ');return false;">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 6 13 12 7 18"/><line x1="17" y1="6" x2="17" y2="18"/></svg></a>';
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
        self.fetch(0, newLimit, currentSearch); // reset to page 0 on limit change
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
                id: rowId,
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
        // Restore search value from session state (overrides Smarty default if different)
        if (currentSearch) {
            jQuery('#' + cfg.searchInputId).val(currentSearch);
        } else {
            currentSearch = jQuery('#' + cfg.searchInputId).val() || '';
        }

        // Restore extra filter params (select2 dropdowns) from session state
        if (savedState && savedState.extra) {
            var labels = savedState.labels || {};
            jQuery.each(savedState.extra, function(key, val) {
                if (val) {
                    var el = jQuery('#' + key);
                    if (el.length && el.is('select')) {
                        var text = labels[key] || val;
                        if (!el.find('option[value="' + val + '"]').length) {
                            el.append(new Option(text, val, true, true));
                        } else {
                            el.val(val);
                        }
                        el.trigger('change');
                    }
                }
            });
        }

        // Apply the current search value (resets to page 0; in infinite scroll
        // mode, resets scroll state). Shared by the Search button and Enter key.
        self.applySearch = function () {
            currentSearch = jQuery('#' + cfg.searchInputId).val();
            if (cfg.infiniteScroll) {
                self.resetScroll();
            } else {
                self.fetch(0, currentLimit, currentSearch);
            }
        };

        // Search button (present on pages with an advanced filters panel)
        jQuery('#' + cfg.searchBtnId).on('click', self.applySearch);

        // Search on Enter key
        jQuery('#' + cfg.searchInputId).on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                self.applySearch();
            }
        });

        // Live search: fetch as the user types (debounced), no Search button needed
        if (cfg.liveSearch) {
            var liveTimer = null;
            jQuery('#' + cfg.searchInputId).on('input', function () {
                var val = this.value;
                clearTimeout(liveTimer);
                liveTimer = setTimeout(function () {
                    currentSearch = val;
                    if (cfg.infiniteScroll) {
                        self.resetScroll();
                    } else {
                        self.fetch(0, currentLimit, currentSearch);
                    }
                }, cfg.liveSearchDelay);
            });
        }

        // Live search on additional named fields (multi-criteria pages).
        // Their values reach the server via extraParams; we just trigger a
        // debounced fetch (or immediate on Enter).
        if (cfg.liveSearchFields && cfg.liveSearchFields.length) {
            var multiTimer = null;
            cfg.liveSearchFields.forEach(function (fid) {
                var el = jQuery('#' + fid);
                el.on('input', function () {
                    clearTimeout(multiTimer);
                    multiTimer = setTimeout(function () {
                        if (cfg.infiniteScroll) { self.resetScroll(); }
                        else { self.fetch(0, currentLimit, currentSearch); }
                    }, cfg.liveSearchDelay);
                });
                el.on('keypress', function (e) {
                    if (e.which === 13) { e.preventDefault(); self.applySearch(); }
                });
            });
        }

        // In infinite scroll mode, always start at page 0 and use a larger batch size
        if (cfg.infiniteScroll) {
            currentNum = 0;
            if (currentLimit < 50) currentLimit = 50;
        }

        // Initial data load (restore page from session)
        self.fetch(currentNum, currentLimit, currentSearch);

        // Infinite scroll: listen for scroll on the specified container or window
        if (cfg.infiniteScroll) {
            var scrollParent;
            if (cfg.scrollContainerId) {
                scrollParent = jQuery('#' + cfg.scrollContainerId);
            } else {
                scrollParent = jQuery('#main-content');
                if (!scrollParent.length) scrollParent = jQuery(window);
            }

            scrollParent.on('scroll', function () {
                if (isLoadingMore || allLoaded) return;

                var scrollTop, scrollHeight, clientHeight;
                if (scrollParent.is(jQuery(window))) {
                    scrollTop = jQuery(window).scrollTop();
                    scrollHeight = jQuery(document).height();
                    clientHeight = jQuery(window).height();
                } else {
                    scrollTop = scrollParent.scrollTop();
                    scrollHeight = scrollParent[0].scrollHeight;
                    clientHeight = scrollParent[0].clientHeight;
                }

                if (scrollTop + clientHeight >= scrollHeight - cfg.infiniteScrollBuffer) {
                    currentNum++;
                    self.fetch(currentNum, currentLimit, currentSearch, true);
                }
            });
        }

        // Auto-refresh (disabled in infinite scroll mode to avoid resetting the list)
        if (cfg.autoRefresh > 0 && !cfg.infiniteScroll) {
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

// ==========================================================================
// Global instant tooltip for [data-cl-tooltip] elements
// Positioned fixed in body — no overflow clipping, no delay
// Usage: <span data-cl-tooltip="PID: 123<br>Uptime: 2d">ⓘ</span>
// ==========================================================================
(function () {
    var tip = null;
    jQuery(document).on('mouseenter', '[data-cl-tooltip]', function () {
        var content = jQuery(this).attr('data-cl-tooltip');
        if (!content) return;
        tip = jQuery('<div class="cl-tooltip-popup">' + content + '</div>');
        jQuery('body').append(tip);
        var rect = this.getBoundingClientRect();
        var top = rect.top - tip.outerHeight() - 6;
        if (top < 0) top = rect.bottom + 6;
        tip.css({
            top: top + 'px',
            left: (rect.left + rect.width / 2 - tip.outerWidth() / 2) + 'px'
        });
    }).on('mouseleave', '[data-cl-tooltip]', function () {
        if (tip) { tip.remove(); tip = null; }
    });
})();

// ==========================================================================
// Advanced filters panel toggle (standard listing toolbar)
// Usage:
//   <button class="cl-adv-btn" data-cl-adv-panel="clAdvPanel"
//           data-cl-label-show="Advanced filters"
//           data-cl-label-hide="Hide filters"
//           onclick="clToggleAdvancedFilters(this)">
//     <svg ...></svg><span class="cl-adv-label">Advanced filters</span>
//   </button>
// ==========================================================================
function clToggleAdvancedFilters(btn) {
    var panel = document.getElementById(btn.getAttribute('data-cl-adv-panel'));
    if (!panel) return;
    var open = panel.classList.toggle('open');
    btn.classList.toggle('active', open);
    var labelEl = btn.querySelector('.cl-adv-label');
    var show = btn.getAttribute('data-cl-label-show');
    var hide = btn.getAttribute('data-cl-label-hide');
    if (labelEl && show && hide) {
        labelEl.textContent = open ? hide : show;
    }
}
