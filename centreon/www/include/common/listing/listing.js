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

// Registered first, before anything else in this file — a later, unrelated
// script-level statement further down (see the tooltip IIFE) can throw at
// load time and abort the rest of this file's top-level execution; a
// registration placed after that point would then silently never happen.
// The callbacks below only run later (on DOMContentLoaded / keydown), by
// which time every function in this file is defined regardless (function
// declarations are hoisted before any code runs), so calling
// clUpdateAdvBadge / clStopAdvPoll here — defined further down — is safe.
document.addEventListener('DOMContentLoaded', function () {
    // Popover variant advanced-filters panel: Escape closes it too (outside
    // click is handled by the backdrop created in clSetAdvBackdrop).
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.cl-adv-panel--popover.open').forEach(function (panel) {
            panel.classList.remove('open');
            var toggleBtn = document.querySelector('[data-cl-adv-panel="' + panel.id + '"]');
            if (toggleBtn) toggleBtn.classList.remove('active');
            var backdrop = document.getElementById('clAdvBackdrop_' + panel.id);
            if (backdrop) backdrop.remove();
            clStopAdvPoll(panel);
        });
    });

    document.querySelectorAll('.cl-adv-panel--popover').forEach(function (panel) {
        panel.addEventListener('change', function () { clUpdateAdvBadge(panel); });
        panel.addEventListener('input', function () { clUpdateAdvBadge(panel); });
        // Deferred: select2 fields can populate default values asynchronously
        // (defaultDatasetRoute) shortly after the page loads.
        setTimeout(function () { clUpdateAdvBadge(panel); }, 500);
    });

    // Focus-driven chevron/eraser on single-select advanced filters (same model
    // as the form). select2 renders asynchronously (some fields load options via
    // a slow route), so re-run over a short bounded window instead of a single
    // retry — clInitAdvSelectClear is idempotent (data-cl-adv-clear guard).
    clInitAdvSelectClear();
    var advTries = 0;
    var advTimer = setInterval(function () {
        clInitAdvSelectClear();
        if (++advTries >= 8) { clearInterval(advTimer); } // ~4s safety window
    }, 500);
});

// Give single-select advanced-filter fields the show-on-interaction behaviour:
// a value/placeholder at rest, chevron while active, chevron + eraser while
// active AND filled. Visibility is driven by CSS through the classes toggled
// here; the eraser itself is centreon-select2's own (already positioned).
function clInitAdvSelectClear() {
    document.querySelectorAll('.cl-adv-field').forEach(function (field) {
        if (field.getAttribute('data-cl-adv-clear')) return;

        var selectEl = field.querySelector('select');
        if (!selectEl || selectEl.multiple) return;

        // Recognize a select2 field even before its container is painted, via
        // the inline centreonSelect2 init script, and defer until it exists.
        var isSelect2 = !!field.querySelector('.select2-container')
            || Array.prototype.some.call(
                field.querySelectorAll('script'),
                function (s) { return s.textContent.indexOf('centreonSelect2') !== -1; }
            );
        if (isSelect2 && !field.querySelector('.select2-selection--single')) return;

        field.setAttribute('data-cl-adv-clear', '1');
        field.classList.add('cl-adv-clearable');

        var $ = window.jQuery;
        var isOpen = false;
        function syncFilled() {
            field.classList.toggle('cl-adv-filled', selectEl.value !== '' && selectEl.value != null);
        }
        function setActive(on) { field.classList.toggle('cl-adv-active', on); }

        syncFilled();
        if ($) {
            $(selectEl).on('change', syncFilled);
            $(selectEl).on('select2:open', function () { isOpen = true; setActive(true); });
            $(selectEl).on('select2:close', function () {
                isOpen = false;
                setTimeout(function () { setActive(field.contains(document.activeElement)); }, 0);
            });
        } else {
            selectEl.addEventListener('change', syncFilled);
        }
        // While the dropdown is open select2 moves focus to a search field in
        // <body>, so focusout must not deactivate then.
        field.addEventListener('focusin', function () { setActive(true); });
        field.addEventListener('focusout', function () {
            setTimeout(function () {
                setActive(isOpen || field.contains(document.activeElement));
            }, 0);
        });
        // The eraser is centreon-select2's own <span> (its click handler clears
        // the field). A mousedown on it would blur the select → focusout removes
        // .cl-adv-active → the eraser is hidden (display:none) BEFORE the click
        // lands, so the clear never fires. Prevent that default focus shift
        // (delegated so it also covers a late-appended eraser); the eraser's own
        // click handler then still runs while the field stays active.
        field.addEventListener('mousedown', function (e) {
            if (e.target && e.target.closest && e.target.closest('.clearAllSelect2')) {
                e.preventDefault();
            }
        });
    });
}

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

    // Auto-refresh timer handle — stored so init() can clear it before re-arming
    // (init() may run more than once on combo pages; otherwise timers stack).
    var autoRefreshTimer = null;

    // =====================================================================
    // Public: HTML escape utility
    // =====================================================================

    this.escape = function (str) {
        return clEscape(str);
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
        if (!ids || !ids.length) return;
        // Match by comparing the name in JS rather than interpolating the id into
        // a jQuery selector (a name with a quote/bracket would break the selector).
        var wanted = {};
        for (var i = 0; i < ids.length; i++) { wanted[ids[i]] = true; }
        jQuery('#' + cfg.tableBodyId + ' .cl-col-picker input[type=checkbox]').each(function () {
            var name = this.getAttribute('name');
            if (name && wanted[name]) { this.checked = true; }
        });
    }

    // =====================================================================
    // Internal: "nothing here yet" empty state — a big, centered "+Add"
    // prompt shown in place of the table body when the listing has zero
    // rows AND no search/filter is currently narrowing it down (i.e. the
    // object type genuinely has nothing configured yet, not just "no match
    // for this search"). Reuses the page's own "+Add" button and subtitle
    // verbatim so nothing needs to be configured per listing.
    // =====================================================================

    function hasActiveFiltersOrSearch() {
        if (currentSearch && currentSearch.trim() !== '') return true;
        var panel = document.querySelector('.cl-adv-panel');
        if (panel && typeof clCountActiveFilters === 'function' && clCountActiveFilters(panel) > 0) return true;
        return false;
    }

    function renderEmptyState() {
        var tbody = jQuery('#' + cfg.tableBodyId);
        var wrap = document.createElement('div');
        wrap.className = 'cl-empty-state';

        // Translated framing strings (fall back to English), with the page
        // title interpolated into the %s placeholder. See window.clI18n in
        // htmlHeader.php — listing.js is a static file and can't use {t}.
        var i18n = (window.clI18n && window.clI18n.emptyState) || {};
        var title = document.querySelector('.cl-page-title');
        var titleText = title ? title.textContent.trim() : '';
        if (title) {
            var h2 = document.createElement('h2');
            h2.className = 'cl-empty-state-title';
            h2.textContent = (i18n.title || 'Welcome to the %s page').replace('%s', titleText);
            wrap.appendChild(h2);
        }

        var p = document.createElement('p');
        p.className = 'cl-empty-state-text';
        p.textContent = titleText
            ? (i18n.text || 'You haven\'t configured any %s yet. Add your first one below and Centreon will start monitoring it right away.').replace('%s', titleText.toLowerCase())
            : (i18n.textFallback || 'Nothing configured yet. Add your first entry below to get started.');
        wrap.appendChild(p);

        var addBtn = document.querySelector('.cl-actions-left .cl-btn-add');
        if (addBtn) {
            var btn = document.createElement('a');
            btn.className = 'cl-btn-add cl-btn-add--lg';
            btn.href = addBtn.getAttribute('href') || '#';
            var onclickAttr = addBtn.getAttribute('onclick');
            if (onclickAttr) btn.setAttribute('onclick', onclickAttr);
            // Explicit inline styles on top of the CSS classes: the icon/text
            // were rendering invisible inside the generic <td> (see the
            // white-space/overflow reset below) — belt-and-suspenders so the
            // button's own content can never be swallowed by an ancestor rule.
            btn.setAttribute('style', 'display:inline-flex !important;align-items:center !important;justify-content:center !important;gap:10px;color:#fff !important;text-decoration:none;white-space:nowrap;');
            var svgNS = 'http://www.w3.org/2000/svg';
            var svg = document.createElementNS(svgNS, 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('width', '18');
            svg.setAttribute('height', '18');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', '#fff');
            svg.setAttribute('stroke-width', '2.5');
            svg.setAttribute('stroke-linecap', 'round');
            svg.setAttribute('stroke-linejoin', 'round');
            svg.style.flexShrink = '0';
            var line1 = document.createElementNS(svgNS, 'line');
            line1.setAttribute('x1', '12'); line1.setAttribute('y1', '5');
            line1.setAttribute('x2', '12'); line1.setAttribute('y2', '19');
            var line2 = document.createElementNS(svgNS, 'line');
            line2.setAttribute('x1', '5'); line2.setAttribute('y1', '12');
            line2.setAttribute('x2', '19'); line2.setAttribute('y2', '12');
            svg.appendChild(line1);
            svg.appendChild(line2);
            btn.appendChild(svg);
            var span = document.createElement('span');
            span.style.color = '#fff';
            span.textContent = addBtn.textContent.trim();
            btn.appendChild(span);
            wrap.appendChild(btn);
        }

        var tr = document.createElement('tr');
        var td = document.createElement('td');
        td.colSpan = 99;
        // The generic row-cell rule (white-space:nowrap, overflow:hidden,
        // max-width:300px) is meant for single-line data cells and would
        // otherwise clip/collapse this multi-line empty state.
        td.setAttribute('style', 'white-space:normal;overflow:visible;max-width:none;');
        td.appendChild(wrap);
        tr.appendChild(td);
        tbody.empty().append(tr);
    }

    // Bulk action selects ("More actions...") stay disabled until at least
    // one row is checked.
    function updateBulkActionState() {
        var anyChecked = jQuery('#' + cfg.tableBodyId).closest('table')
            .find('.cl-col-picker input[type=checkbox]:checked').length > 0;
        jQuery('select[name="o1"], select[name="o2"]').each(function () {
            jQuery(this).prop('disabled', !anyChecked);
            var wrapper = jQuery(this).data('clWrapper');
            if (wrapper) wrapper.toggleClass('cl-more-actions--disabled', !anyChecked);
        });
    }

    // Header "check all" checkbox: nothing to select when the table is
    // empty, so grey it out instead of leaving it clickable.
    function updateHeaderCheckboxState() {
        var table = jQuery('#' + cfg.tableBodyId).closest('table');
        var hasRows = table.find('tbody .cl-col-picker input[type=checkbox]').length > 0;
        var headerCheckbox = table.find('thead .cl-col-picker input[type=checkbox]');
        headerCheckbox.prop('disabled', !hasRows);
        if (!hasRows) headerCheckbox.prop('checked', false);
    }

    // =====================================================================
    // "More actions" custom dropdown — replaces the plain <select>'s look
    // (native <option> elements can't really be styled) while leaving the
    // original <select> in the DOM, hidden, so its existing value/onchange
    // behavior (confirm dialogs, form submit, etc.) keeps working untouched.
    // =====================================================================

    function enhanceBulkActionSelect(select) {
        var $select = jQuery(select);
        if ($select.data('clWrapper')) return;

        var wrapper = jQuery('<div class="cl-more-actions"></div>');
        var btn = jQuery(
            '<button type="button" class="cl-more-actions-btn">' +
                '<span class="cl-more-actions-icon" aria-hidden="true">&#x22EF;</span>' +
                '<span class="cl-more-actions-label"></span>' +
            '</button>'
        );
        var menu = jQuery('<div class="cl-more-actions-menu" role="menu"></div>');

        $select.find('option').each(function () {
            var $opt = jQuery(this);
            var value = $opt.attr('value');
            if (!value) {
                btn.find('.cl-more-actions-label').text($opt.text());
                return;
            }
            var item = jQuery('<div class="cl-more-actions-item" role="menuitem" tabindex="0"></div>')
                .text($opt.text())
                .attr('data-value', value);
            // Key the danger (red) style off the option VALUE ('d' = delete),
            // not its translated label, so it applies in every locale.
            if (value === 'd') {
                item.addClass('cl-more-actions-item--danger');
            }
            menu.append(item);
        });

        wrapper.append(btn).append(menu);
        $select.hide().after(wrapper);
        $select.data('clWrapper', wrapper);

        function closeMenu() {
            wrapper.removeClass('open');
        }

        // Actually flip the underlying <select>'s value and invoke its
        // existing onchange (confirm dialogs, form submit, ...) directly —
        // a direct function call, not jQuery's trigger('change'), so it's
        // guaranteed to run synchronously in this exact call stack (needed
        // below, where a temporary window.confirm override must still be in
        // place when the handler's own confirm() call executes).
        function applyValue(value) {
            select.value = value;
            if (typeof select.onchange === 'function') {
                select.onchange.call(select, { type: 'change', target: select, currentTarget: select });
            } else {
                $select.trigger('change');
            }
        }

        // Mass Change opens as a full page reload by default (the shared
        // onchange handler's setO(value); submit()) — reuse the page's own
        // "+Add" button URL (same cfOpenPanel side panel already used for
        // Add/Edit) to open it there instead, carrying the checked rows'
        // ids along as a GET param the same way the old full-page submit
        // carried them via POST.
        function openMassChangePanel(label) {
            var addBtn = document.querySelector('.cl-actions-left .cl-btn-add');
            var onclickAttr = (addBtn && addBtn.getAttribute('onclick')) || '';
            var urlMatch = /cfOpenPanel\(\s*'([^']*)'/.exec(onclickAttr);
            if (!urlMatch || typeof window.cfOpenPanel !== 'function') return false;

            var ids = [];
            jQuery('#' + cfg.tableBodyId + ' .cl-col-picker input[type=checkbox]:checked').each(function () {
                var name = jQuery(this).attr('name');
                var idMatch = name && /\[(.+)\]$/.exec(name);
                if (idMatch) ids.push(idMatch[1]);
            });
            if (!ids.length) return false;

            var url = urlMatch[1].replace(/([?&]o=)[^&]*/, '$1mc') + '&select=' + ids.map(encodeURIComponent).join(',');
            window.cfOpenPanel(url, label);
            return true;
        }

        function selectValue(value, label) {
            closeMenu();
            if (value === 'mc' && openMassChangePanel(label)) {
                return;
            }
            // Everything else is delegated to the <select>'s own onchange
            // (clMoreAction), which drives the styled, value-based confirmation
            // — no native confirm()/alert(), and locale-independent (keyed on
            // the option value, not its translated label).
            applyValue(value);
        }

        btn.on('click', function (e) {
            e.stopPropagation();
            if ($select.prop('disabled')) return;
            jQuery('.cl-more-actions.open').not(wrapper).removeClass('open');
            wrapper.toggleClass('open');
        });

        menu.on('click', '.cl-more-actions-item', function () {
            selectValue(jQuery(this).attr('data-value'), jQuery(this).text());
        });

        menu.on('keydown', '.cl-more-actions-item', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectValue(jQuery(this).attr('data-value'), jQuery(this).text());
            }
        });

        jQuery(document).on('click', function (e) {
            if (!wrapper[0].contains(e.target)) closeMenu();
        });
        jQuery(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });

        wrapper.toggleClass('cl-more-actions--disabled', $select.prop('disabled'));
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
                    if (data.total === 0 && !hasActiveFiltersOrSearch()) {
                        renderEmptyState();
                    }
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
                updateBulkActionState();
                updateHeaderCheckboxState();
                if (cfg.onDataLoaded) cfg.onDataLoaded(data);
            },
            error: function (xhr, status, err) {
                isLoadingMore = false;
                jQuery('#' + cfg.tableBodyId).find('.cl-infinite-loader').remove();

                // Always leave a diagnostic trace — this callback used to discard
                // xhr/status/err, so a "the list is stuck" report had nothing to go on.
                if (window.console) {
                    console.error('[CentreonListing] fetch failed', (xhr && xhr.status) || '', status, err);
                }

                var httpStatus = xhr && xhr.status;
                if (httpStatus === 401 || httpStatus === 403) {
                    // Session expired / access lost mid-view: stop the auto-refresh
                    // so we don't hammer a dead session, and tell the user.
                    if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
                    clToast(clListingLabel('sessionExpired', 'Your session has expired — please reload the page.'), 'error');
                    return;
                }

                if (firstLoad) {
                    jQuery('#' + cfg.tableBodyId).html(
                        '<tr><td colspan="99" style="text-align:center;padding:24px;color:#FF4A4A;">' +
                        clEscape(clListingLabel('loadError', 'Error loading data')) + '</td></tr>'
                    );
                } else if (!silent) {
                    // Surface later failures (page change, search) instead of silently
                    // leaving stale rows on screen as if they matched the new request.
                    clToast(clListingLabel('loadError', 'Error loading data'), 'error');
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
                self.escape(clListingLabel('noResults', 'No results found')) + '</td></tr>');
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
                        '<input type="checkbox" id="select_' + clEscapeAttr(rowId) + '" name="select[' + clEscapeAttr(rowId) + ']" value="1"' + disabledAttr + ' />' +
                        '<label class="empty-label" for="select_' + clEscapeAttr(rowId) + '"></label>' +
                    '</div>' +
                '</td>';
            }

            // Data columns
            for (var c = 0; c < cfg.columns.length; c++) {
                var col = cfg.columns[c];
                var align = col.align ? ' class="cl-col-' + col.align + '"' : '';
                var cellHtml = col.render ? col.render(row, self) : self.escape(row[col.id] == null ? '' : row[col.id]);
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
                        '<input type="checkbox" id="select_' + clEscapeAttr(rowId) + '" name="select[' + clEscapeAttr(rowId) + ']" value="1"' + disabledAttr + ' />' +
                        '<label class="empty-label" for="select_' + clEscapeAttr(rowId) + '"></label>' +
                    '</div>' +
                '</td>';
            }

            for (var c = 0; c < cfg.columns.length; c++) {
                var col = cfg.columns[c];
                var align = col.align ? ' class="cl-col-' + col.align + '"' : '';
                var cellHtml = col.render ? col.render(row, self) : self.escape(row[col.id] == null ? '' : row[col.id]);
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
        var info     = startRow + '-' + endRow + ' ' + clListingLabel('of', 'of') + ' ' + total;

        // React-style layout: [rows-per-page ▾] [count] [first][prev][next][last]
        // The four nav arrows are always shown and greyed (disabled) at the ends.
        var nav = function (title, disabled, page, inner) {
            var svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
            if (disabled) {
                return '<span class="cl-page-nav cl-page-nav--disabled" title="' + title + '">' + svg + '</span>';
            }
            return '<a href="#" class="cl-page-nav" title="' + title + '" onclick="' + instanceName() + '.goToPage(' + page + ');return false;">' + svg + '</a>';
        };

        var html = '';

        // Rows-per-page selector
        html += '<select class="cl-limit-select" onchange="' + instanceName() + '.changeLimit(this.value);">';
        var limits = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        for (var j = 0; j < limits.length; j++) {
            var sel = limits[j] === limit ? ' selected' : '';
            html += '<option value="' + limits[j] + '"' + sel + '>' + limits[j] + '</option>';
        }
        html += '</select>';

        // Row count info
        html += '<span class="cl-page-info">' + info + '</span>';

        // Navigation arrows (first / previous / next / last)
        var isFirst = num <= 0;
        var isLast = num >= totalPages - 1;
        html += nav(clListingLabel('firstPage', 'First page'), isFirst, 0, '<polyline points="17 6 11 12 17 18"/><line x1="7" y1="6" x2="7" y2="18"/>');
        html += nav(clListingLabel('previousPage', 'Previous page'), isFirst, num - 1, '<polyline points="15 6 9 12 15 18"/>');
        html += nav(clListingLabel('nextPage', 'Next page'), isLast, num + 1, '<polyline points="9 6 15 12 9 18"/>');
        html += nav(clListingLabel('lastPage', 'Last page'), isLast, totalPages - 1, '<polyline points="7 6 13 12 7 18"/><line x1="17" y1="6" x2="17" y2="18"/>');

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
            error: function (xhr, status, err) {
                // Revert the optimistic switch and re-enable it (kept), but tell
                // the user instead of leaving a silently-reverted toggle.
                toggle.checked = !isChecked;
                toggle.disabled = false;
                if (window.console) {
                    console.error('[CentreonListing] toggle failed', (xhr && xhr.status) || '', status, err);
                }
                clToast(clListingLabel('toggleError', 'Could not change status'), 'error');
                // A stale CSRF token (403) would make every subsequent toggle fail
                // too; a silent list refresh pulls a fresh token so the next works.
                if (xhr && xhr.status === 403) {
                    self.fetch(currentNum, currentLimit, currentSearch, true);
                }
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
                        // Compare values in JS rather than interpolating into a
                        // selector (a value with a quote would break el.find()).
                        var hasOption = el.find('option').filter(function () {
                            return this.value === String(val);
                        }).length > 0;
                        if (!hasOption) {
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

        // Bulk action selects ("More actions...") only become usable once a
        // row is checked — keep them in sync with the master/row checkboxes.
        jQuery('select[name="o1"], select[name="o2"]').each(function () {
            enhanceBulkActionSelect(this);
        });
        updateBulkActionState();
        updateHeaderCheckboxState();
        jQuery(document).on('change', '.cl-col-picker input[type=checkbox]', updateBulkActionState);

        // Ask for confirmation before discarding an open side panel's
        // unsaved changes on an outside click.
        wireSidePanelDirtyGuard();

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

        // Auto-refresh (disabled in infinite scroll mode to avoid resetting the
        // list). Clear any previous timer first so repeated init() calls don't
        // stack duplicate periodic fetches.
        if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
        if (cfg.autoRefresh > 0 && !cfg.infiniteScroll) {
            autoRefreshTimer = setInterval(function () {
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
// Usage: <span data-cl-tooltip="PID: 123 — Uptime: 2d">ⓘ</span>
// Content is rendered as plain text (textContent), so it can never inject HTML.
// ==========================================================================
// Vanilla JS (delegated on document) so it never depends on jQuery being
// loaded before this file — the IIFE runs at parse time.
(function () {
    var tip = null;

    function removeTip() {
        if (tip) {
            tip.parentNode && tip.parentNode.removeChild(tip);
            tip = null;
        }
    }

    document.addEventListener('mouseover', function (e) {
        var el = e.target.closest ? e.target.closest('[data-cl-tooltip]') : null;
        if (!el || (tip && tip._owner === el)) return;
        var content = el.getAttribute('data-cl-tooltip');
        if (!content) return;
        removeTip();
        tip = document.createElement('div');
        tip.className = 'cl-tooltip-popup';
        tip.textContent = content;
        tip._owner = el;
        document.body.appendChild(tip);
        var rect = el.getBoundingClientRect();
        var top = rect.top - tip.offsetHeight - 6;
        if (top < 0) top = rect.bottom + 6;
        tip.style.top = top + 'px';
        tip.style.left = (rect.left + rect.width / 2 - tip.offsetWidth / 2) + 'px';
    });

    document.addEventListener('mouseout', function (e) {
        var el = e.target.closest ? e.target.closest('[data-cl-tooltip]') : null;
        if (!el) return;
        // Ignore moves that stay within the same trigger element.
        if (e.relatedTarget && el.contains(e.relatedTarget)) return;
        removeTip();
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
    if (panel.classList.contains('cl-adv-panel--popover')) {
        clSetAdvBackdrop(panel, btn, open);
    }
}

// Popover variant: close on outside click via a dedicated full-page backdrop
// rather than a document click listener — a listener depending on event
// bubbling/capturing can be swallowed by unrelated handlers elsewhere on the
// page; a backdrop is a direct click target and can't be intercepted that way.
function clSetAdvBackdrop(panel, btn, open) {
    var backdropId = 'clAdvBackdrop_' + panel.id;
    var backdrop = document.getElementById(backdropId);
    if (open) {
        clStartAdvPoll(panel);
        if (backdrop) return;
        backdrop = document.createElement('div');
        backdrop.id = backdropId;
        backdrop.className = 'cl-adv-backdrop';
        backdrop.addEventListener('click', function () {
            panel.classList.remove('open');
            btn.classList.remove('active');
            backdrop.remove();
            clStopAdvPoll(panel);
        });
        document.body.appendChild(backdrop);
    } else {
        clStopAdvPoll(panel);
        if (backdrop) backdrop.remove();
    }
}

// Active-filter badge: poll while the popover is open instead of relying on
// change/input bubbling — select2 fields update their backing <select> and
// dispatch through jQuery's own event path, which isn't guaranteed to reach a
// plain addEventListener delegate the same way on every field/version, so
// polling for the 300ms the panel is open is the only fully reliable option.
// Lazily initialized on window (not a top-level `var` here) — an unrelated
// earlier script on this page can throw before a top-level statement in this
// file gets a chance to run, which would leave a plain top-level `var` stuck
// at undefined forever. Reading/creating it inside the functions that use it
// makes this immune to that.
function clStartAdvPoll(panel) {
    window.clAdvPollTimers = window.clAdvPollTimers || {};
    if (window.clAdvPollTimers[panel.id]) return;
    window.clAdvPollTimers[panel.id] = setInterval(function () { clUpdateAdvBadge(panel); }, 300);
}

function clStopAdvPoll(panel) {
    window.clAdvPollTimers = window.clAdvPollTimers || {};
    if (window.clAdvPollTimers[panel.id]) {
        clearInterval(window.clAdvPollTimers[panel.id]);
        delete window.clAdvPollTimers[panel.id];
    }
    clUpdateAdvBadge(panel);
}

// Active-filter count badge on the toggle button (top-right corner), so users
// can tell filters are applied even when the popover is closed. Counts one
// per .cl-adv-field that currently holds a value (select/select2, checkbox,
// or text input), delegated so it also reacts to select2 selections.
function clCountActiveFilters(panel) {
    var count = 0;
    panel.querySelectorAll('.cl-adv-field').forEach(function (field) {
        var active = false;
        field.querySelectorAll('select').forEach(function (sel) {
            if (sel.multiple) {
                if (Array.prototype.some.call(sel.selectedOptions, function (o) { return o.value !== ''; })) active = true;
            } else if (sel.value !== '') {
                active = true;
            }
        });
        if (field.querySelector('input[type="checkbox"]:checked')) active = true;
        field.querySelectorAll('input[type="text"], input[type="search"]').forEach(function (inp) {
            if (inp.value.trim() !== '') active = true;
        });
        if (active) count++;
    });
    // Standalone toggle filters (e.g. "Locked", "Disabled hosts") aren't
    // wrapped in .cl-adv-field, so count them separately.
    panel.querySelectorAll('.cl-filter-toggle').forEach(function (toggle) {
        if (toggle.querySelector('input[type="checkbox"]:checked')) count++;
    });
    return count;
}

function clUpdateAdvBadge(panel) {
    var count = clCountActiveFilters(panel);
    // Enable "Clear" only when at least one filter is active — mirrors the React
    // advanced-filters behaviour (Clear is disabled when no filter is set).
    var clearBtn = panel.querySelector('.cl-adv-clear');
    if (clearBtn) clearBtn.disabled = count === 0;
    var toggleBtn = document.querySelector('[data-cl-adv-panel="' + panel.id + '"]');
    if (!toggleBtn) return;
    var badge = toggleBtn.querySelector('.cl-adv-badge');
    if (!badge) return;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
}

// ==========================================================================
//  Toast — lightweight bottom-right notification for listing actions
//    clToast('Message', 'success' | 'error' | 'info')
// ==========================================================================
function clToast(message, type) {
    type = type || 'info';
    var wrap = document.getElementById('clToastWrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'clToastWrap';
        wrap.className = 'cl-toast-wrap';
        document.body.appendChild(wrap);
    }
    var toast = document.createElement('div');
    toast.className = 'cl-toast ' + type;
    toast.textContent = message;
    wrap.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () {
        toast.classList.remove('show');
        setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 300);
    }, 4500);
}

// ==========================================================================
//  Escaping helpers — standalone equivalents of CentreonListing's own
//  this.escape(), usable on pages that render dynamic strings into HTML
//  without instantiating a CentreonListing table.
// ==========================================================================

// Translated framework label for the shared listing lib's JS-built UI
// (pagination, empty message, modal close/OK). Falls back to English.
// See window.clI18n.listing in htmlHeader.php — listing.js is a static file
// and can't use Smarty's {t}...{/t} tags directly.
function clListingLabel(key, fallback) {
    var l = window.clI18n && window.clI18n.listing;
    return (l && l[key]) || fallback;
}

function clEscape(str) {
    // Only null/undefined become empty — a legitimate 0 or false must still render.
    if (str == null) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

// clEscape() alone is unsafe inside an HTML attribute (e.g. onclick="...");
// this additionally escapes quotes.
function clEscapeAttr(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Fill `el` from a translated template: the plain text becomes text nodes and
// each {{ key }} substitution is rendered as a <strong> built via textContent. The
// emphasis therefore needs NO <strong> markup in the (translated) template, and
// the modal never touches innerHTML — so there is no XSS sink and a substituted
// value can never inject HTML. Any stray <strong> tags left in a template are
// stripped, so both the new plain-text templates and old inline-markup ones work.
function clFillTemplateInto(el, template, replacements) {
    replacements = replacements || {};
    // split() with a capturing group yields [text, key, text, key, …] — keys at odd indexes.
    String(template == null ? '' : template).split(/\{\{\s*(\w+)\s*\}\}/).forEach(function (part, i) {
        if (i % 2 === 1) {
            if (Object.prototype.hasOwnProperty.call(replacements, part)) {
                var strong = document.createElement('strong');
                strong.textContent = replacements[part];
                el.appendChild(strong);
            } else {
                el.appendChild(document.createTextNode('{{' + part + '}}'));
            }
        } else if (part) {
            var text = part.replace(/<\/?strong>/gi, '');
            if (text) { el.appendChild(document.createTextNode(text)); }
        }
    });
}

// Read the display name of a selected row from its selection checkbox: the
// first data cell (the .cl-col-picker column is skipped), which by convention
// holds the object's name.
function clSelectedRowName(checkbox) {
    var row = checkbox && checkbox.closest ? checkbox.closest('tr') : null;
    if (!row) return '';
    var cell = row.querySelector('td:not(.cl-col-picker)');
    return cell ? cell.textContent.trim() : '';
}

// ==========================================================================
//  Confirmation modal — generic building block shared by the bulk-action
//  confirmation (Delete/Disable/Duplicate) and the "discard unsaved changes"
//  prompt below.
//    clShowConfirmModal({title, message, cancelLabel, confirmLabel, danger},
//                        function (confirmed) { ... })
// ==========================================================================
function clShowConfirmModal(opts, callback) {
    var overlay = document.createElement('div');
    overlay.className = 'cl-confirm-overlay';
    var modal = document.createElement('div');
    modal.className = 'cl-confirm-modal';
    // Dialog semantics for screen readers + a unique id to label it by title.
    var titleId = 'cl-confirm-title-' + (clShowConfirmModal._seq = (clShowConfirmModal._seq || 0) + 1);
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', titleId);
    modal.innerHTML =
        '<div class="cl-confirm-header">' +
            '<h3 class="cl-confirm-title" id="' + titleId + '"></h3>' +
            '<button type="button" class="cl-confirm-close" aria-label="' + clListingLabel('close', 'Close') + '">&times;</button>' +
        '</div>' +
        '<div class="cl-confirm-body"></div>' +
        '<div class="cl-confirm-actions">' +
            '<button type="button" class="cl-confirm-cancel"></button>' +
            '<button type="button" class="cl-confirm-confirm-btn"></button>' +
        '</div>';
    modal.querySelector('.cl-confirm-title').textContent = opts.title;
    // Body is built as DOM — never innerHTML. A template with {{ key }} placeholders
    // renders each substitution in bold via clFillTemplateInto; otherwise the plain
    // message is set as text.
    var body = modal.querySelector('.cl-confirm-body');
    if (opts.messageTemplate) {
        clFillTemplateInto(body, opts.messageTemplate, opts.messageReplacements);
    } else {
        body.textContent = opts.message;
    }
    var cancelBtn = modal.querySelector('.cl-confirm-cancel');
    var confirmBtn = modal.querySelector('.cl-confirm-confirm-btn');
    confirmBtn.textContent = opts.confirmLabel || clListingLabel('ok', 'OK');
    confirmBtn.classList.add(opts.danger ? 'cl-confirm-confirm-btn--danger' : 'cl-confirm-confirm-btn--primary');
    // Alert mode: a single acknowledge button, no cancel.
    if (opts.alert) {
        cancelBtn.parentNode.removeChild(cancelBtn);
        cancelBtn = null;
    } else {
        cancelBtn.textContent = opts.cancelLabel || (window.clI18n && window.clI18n.cancel) || 'Cancel';
    }
    overlay.appendChild(modal);
    // Remember the trigger so focus can be restored when the dialog closes.
    var previouslyFocused = document.activeElement;
    document.body.appendChild(overlay);
    requestAnimationFrame(function () {
        overlay.classList.add('open');
        // Move focus into the dialog (confirm is the primary action).
        try { confirmBtn.focus(); } catch (e) {}
    });

    function close(result) {
        overlay.classList.remove('open');
        document.removeEventListener('keydown', onKeydown, true);
        setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 150);
        // Restore focus to whatever triggered the dialog.
        try { if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus(); } catch (e) {}
        if (typeof callback === 'function') callback(result);
    }
    function onKeydown(e) {
        if (e.key === 'Escape') {
            // Eat the key so lower layers (side panel / popover Escape handlers)
            // don't also react while this dialog is up.
            e.preventDefault(); e.stopPropagation();
            close(false);
        } else if (e.key === 'Enter') {
            e.preventDefault(); e.stopPropagation();
            close(true);
        } else if (e.key === 'Tab') {
            // Trap focus within the dialog.
            var f = Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled])'));
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1], active = document.activeElement;
            if (e.shiftKey && active === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && active === last) { e.preventDefault(); first.focus(); }
            else if (f.indexOf(active) === -1) { e.preventDefault(); first.focus(); }
        }
    }
    // Capture phase so this runs BEFORE bubble-phase document Escape handlers
    // (side panel / popover); stopPropagation above then keeps them from firing.
    document.addEventListener('keydown', onKeydown, true);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close(false);
    });
    modal.querySelector('.cl-confirm-close').addEventListener('click', function () { close(false); });
    if (cancelBtn) cancelBtn.addEventListener('click', function () { close(false); });
    confirmBtn.addEventListener('click', function () { close(true); });
}

// Handle a "More actions" (o1/o2) <select> change with the styled, secure
// confirmation modal instead of the native confirm()/alert(). Keyed off the
// option value ('d', 'm', ...), NOT its translated label, so it behaves the
// same in every locale. Titles/messages/labels come from the select's data-*
// attributes (translated in PHP), with English fallbacks.
function clMoreAction(select) {
    if (!select || select.selectedIndex <= 0) {
        return;
    }
    var value = select.value;
    var form = select.form;
    var attr = function (name, fallback) {
        return select.getAttribute(name) || fallback;
    };
    var reset = function () { select.selectedIndex = 0; };
    var doAction = function () {
        if (typeof window.setO === 'function') {
            window.setO(value);
        }
        // Use the prototype in case a field named "submit" shadows form.submit.
        if (form) {
            HTMLFormElement.prototype.submit.call(form);
        }
    };
    var onResult = function (confirmed) {
        if (confirmed) {
            doAction();
        } else {
            reset();
        }
    };

    // Actually-selected rows: the per-row selection checkboxes (name="select[<id>]"),
    // NOT the activation toggles (no name), the dup inputs (name="dupNbr[...]"), nor
    // the header "check all" box (name="checkall").
    var scope = form || document;
    var checkedRows = scope.querySelectorAll('.cl-col-picker input[type="checkbox"][name^="select["]:checked');
    var selectedCount = checkedRows.length;

    // Row-based actions require at least one selected row.
    if (selectedCount === 0) {
        clShowConfirmModal({
            alert: true,
            title: '',
            message: attr('data-msg-select', 'Please select one or more items')
        });
        reset();
        return;
    }

    if (value === 'd') { // Delete
        var isMany = selectedCount > 1;
        var deleteOpts = {
            danger: true,
            title: attr(isMany ? 'data-title-delete-many' : 'data-title-delete-one', attr('data-title-delete', 'Delete')),
            confirmLabel: attr('data-label-delete', 'Delete'),
            cancelLabel: attr('data-label-cancel', 'Cancel')
        };
        // Per-count message templates carry a {{ name }} (single selection) or
        // {{ count }} (several) placeholder; the interpolated value is rendered
        // bold by the modal (no inline markup needed). Fall back to the old plain message.
        var template = attr(isMany ? 'data-msg-delete-many' : 'data-msg-delete-one', '');
        if (template) {
            deleteOpts.messageTemplate = template;
            deleteOpts.messageReplacements = isMany
                ? { count: String(selectedCount) }
                : { name: clSelectedRowName(checkedRows[0]) };
        } else {
            deleteOpts.message = attr('data-msg-delete', 'You are about to delete the selected object(s). This action cannot be undone. Do you want to delete?');
        }
        clShowConfirmModal(deleteOpts, onResult);
    } else if (value === 'm') { // Duplicate
        var isManyDup = selectedCount > 1;
        var dupOpts = {
            title: attr(isManyDup ? 'data-title-duplicate-many' : 'data-title-duplicate-one', attr('data-title-duplicate', 'Duplicate')),
            confirmLabel: attr('data-label-duplicate', 'Duplicate'),
            cancelLabel: attr('data-label-cancel', 'Cancel')
        };
        // Per-count templates: {{ name }} (single selection) or {{ count }}
        // (several); the interpolated value is rendered bold by the modal. Fall
        // back to the old plain message.
        var dupTemplate = attr(isManyDup ? 'data-msg-duplicate-many' : 'data-msg-duplicate-one', '');
        if (dupTemplate) {
            dupOpts.messageTemplate = dupTemplate;
            dupOpts.messageReplacements = isManyDup
                ? { count: String(selectedCount) }
                : { name: clSelectedRowName(checkedRows[0]) };
        } else {
            dupOpts.message = attr('data-msg-duplicate', 'Do you want to duplicate the selected object(s)?');
        }
        clShowConfirmModal(dupOpts, onResult);
    } else { // Enable / Disable / Mass Change — no confirmation
        doAction();
    }
}

// "Discard unsaved changes?" prompt shown when the user clicks outside an
// open side panel (the overlay) while the form inside has been edited (see
// window.cfFormDirty, set by CentreonForm.initFormPage's dirty tracking) —
// see wireSidePanelDirtyGuard below.
//   clConfirmDiscardChanges(function (confirmed) { ... })
function clConfirmDiscardChanges(callback) {
    var i18n = window.clI18n && window.clI18n.confirmDiscard;
    clShowConfirmModal({
        title: (i18n && i18n.title) || 'Do you want to leave this page?',
        message: (i18n && i18n.message)
            || 'You have unsaved changes. Are you sure you want to close this panel without saving?',
        cancelLabel: (i18n && i18n.cancel) || 'Stay',
        confirmLabel: (i18n && i18n.confirm) || 'Leave'
    }, callback);
}

// Guards the side panel's close path: there are actually three ways to
// close it (the overlay backdrop's onclick, the panel's own [x] button, and
// the Escape key — see cfOpenPanel/cfClosePanel, duplicated as-is across
// each listing's own .ihtml) and all three just call the shared
// cfClosePanel() function. Wrapping that one function (instead of any one
// of its three triggers) catches all of them uniformly: if the form loaded
// in the panel's iframe has unsaved changes (window.cfFormDirty, set inside
// the iframe by htmlHeader.php's dirty-tracking script — not every form
// page calls CentreonForm.initFormPage(), some predate it and still have
// their own hand-rolled JS, so this has to run unconditionally on every
// page to be reliable regardless of which pattern a given form uses), ask
// for confirmation instead of silently discarding them. Wired once per page
// regardless of how many listing instances call init() (e.g. host+service
// combo toolbars).
function wireSidePanelDirtyGuard() {
    if (typeof window.cfClosePanel !== 'function' || window.cfClosePanel.__clDirtyGuarded) return;

    var originalClosePanel = window.cfClosePanel;
    function guardedClosePanel() {
        var frame = document.getElementById('cfSidePanelFrame');
        var isDirty = false;
        try {
            isDirty = !!(frame && frame.contentWindow && frame.contentWindow.cfFormDirty);
        } catch (err) {
            isDirty = false;
        }
        if (!isDirty) {
            originalClosePanel();
            return;
        }
        clConfirmDiscardChanges(function (confirmed) {
            if (confirmed) originalClosePanel();
        });
    }
    guardedClosePanel.__clDirtyGuarded = true;
    window.cfClosePanel = guardedClosePanel;
}
