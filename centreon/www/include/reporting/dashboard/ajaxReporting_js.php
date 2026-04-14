<?php
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
 * Inline JavaScript & CSS for the reporting heatmap visualizations.
 *
 * This file is included (require) by the 4 reporting view pages:
 *   - viewHostLog.php         ($type = 'Host')
 *   - viewServicesLog.php     ($type = 'Service')
 *   - viewHostGroupLog.php    ($type = 'HostGroup')
 *   - viewServicesGroupLog.php ($type = 'ServiceGroup')
 *
 * It generates two GitHub-style heatmaps:
 *   1. Availability heatmap — color-coded by up/ok percentage
 *   2. Alerts heatmap — color-coded by number of state change events
 *
 * Each heatmap includes:
 *   - Year calendar grid (52 weeks x 7 days) with navigation
 *   - A 30-day trend chart (line for availability, bar for alerts) using Chart.js
 *   - Interactive tooltips with status breakdown
 *
 * Expected PHP variables (set by the including view page):
 *   - $type       (string) Entity type — determines host/service display mode
 *   - $id         (int)    Entity ID
 *   - $serviceId  (int)    Service ID (only when $type === 'Service')
 *   - $hostId     (int)    Host ID (only when $type === 'Service')
 *   - $colors     (array)  Status colors from initReport.php (hex without #)
 *   - $tpl        (Smarty) Template object with jsTranslationsJson variable
 */

require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');

// Build the URL to the JSON data endpoint
$arg = 'type=' . urlencode($type) . '&';
$arg .= $type == 'Service' ? 'id=' . $serviceId . '&host_id=' . $hostId : 'id=' . $id;
$jsonUrl = './include/reporting/dashboard/xmlInformations/GetJsonData.php?' . $arg;

// Host or service view? Determines which status keys to use (up/down vs ok/critical)
$isHostType = in_array($type, ['Host', 'HostGroup']);

// Encode colors array for safe JS injection
$colorsJson = json_encode($colors);

?>
<style>
/* ─── Heatmap container ─── */
.heatmap-container {
    padding: 10px 0;
    overflow-x: auto;
}

/* ─── Year navigation ─── */
.heatmap-nav {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    padding-left: 32px;
}
.heatmap-nav-btn {
    background: none;
    border: 1px solid var(--list-table-border-color, #d0d7de);
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 12px;
    color: var(--body-color, #24292f);
    cursor: pointer;
    line-height: 20px;
    transition: background 0.15s;
}
.heatmap-nav-btn:hover {
    background: var(--list-lvl-1-background-color, #f3f4f6);
}
.heatmap-nav-btn:disabled {
    opacity: 0.4;
    cursor: default;
}
.heatmap-nav-year {
    font-size: 14px;
    font-weight: 600;
    color: var(--body-color, #24292f);
    min-width: 40px;
    text-align: center;
}

/* ─── Month labels row ─── */
.heatmap-months {
    display: flex;
    padding-left: 32px;
    margin-bottom: 4px;
    font-size: 11px;
    color: var(--body-color, #666);
    opacity: 0.7;
}
.heatmap-months span {
    flex: none;
}

/* ─── Grid layout (days + weeks) ─── */
.heatmap-body {
    display: flex;
    gap: 0;
}
.heatmap-days-label {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding-right: 6px;
    font-size: 10px;
    color: var(--body-color, #888);
    opacity: 0.6;
    width: 26px;
    flex-shrink: 0;
}
.heatmap-days-label span {
    height: 13px;
    line-height: 13px;
}
.heatmap-grid {
    display: flex;
    gap: 2px;
}
.heatmap-week {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

/* ─── Individual day cell ─── */
.heatmap-cell {
    width: 13px;
    height: 13px;
    border-radius: 2px;
    background: rgba(128, 128, 128, 0.15);
    cursor: pointer;
    position: relative;
    transition: outline 0.1s;
}
.heatmap-cell:hover {
    outline: 2px solid var(--body-color, #333);
    outline-offset: -1px;
    z-index: 2;
}

/* ─── Tooltip (positioned above or below cell) ─── */
.heatmap-tooltip {
    display: none;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    background: #24292f;
    color: #fff;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    pointer-events: none;
    line-height: 1.5;
}
.heatmap-tooltip.heatmap-tip-top {
    bottom: 20px;
}
.heatmap-tooltip.heatmap-tip-top::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #24292f;
}
.heatmap-tooltip.heatmap-tip-bottom {
    top: 20px;
}
.heatmap-tooltip.heatmap-tip-bottom::after {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-bottom-color: #24292f;
}
.heatmap-cell:hover .heatmap-tooltip {
    display: block;
}

/* ─── Legend row ─── */
.heatmap-legend {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 10px;
    padding-left: 32px;
    font-size: 11px;
    color: var(--body-color, #666);
    opacity: 0.7;
}
.heatmap-legend-cell {
    width: 13px;
    height: 13px;
    border-radius: 2px;
}

/* ─── Mini status bar in tooltips ─── */
.heatmap-status-bar {
    display: flex;
    height: 6px;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 3px;
    min-width: 60px;
}
</style>

<script type="text/javascript">
/*
 * ══════════════════════════════════════════════════════════════
 *  Centreon Reporting — Heatmap Visualizations
 * ══════════════════════════════════════════════════════════════
 *
 *  Architecture:
 *    initTimeline()         → fetches JSON data, triggers rendering
 *    renderHeatmap()        → availability heatmap + 30-day line chart
 *    renderAlertHeatmap()   → alerts heatmap + 30-day bar chart
 *    buildHeatmapGrid()     → shared grid builder (calendar, nav, tooltips)
 *
 *  Data flow:
 *    GetJsonData.php → _heatmapData (global) → dataMap lookup → render
 *
 *  Dependencies:
 *    - jQuery (for $.getJSON)
 *    - Chart.js 4 (loaded globally in Centreon header)
 */

/* ── Global state ────────────────────────────── */
var _heatmapData = null;       // Raw JSON array from GetJsonData.php
var _heatmapYear = null;       // Currently displayed year
var _heatmapIsHostType = <?php echo $isHostType ? 'true' : 'false'; ?>;
var _heatmapColors = <?php echo $colorsJson; ?>;
var _rptI18n = <?php echo $tpl->getTemplateVars('jsTranslationsJson'); ?>;

/* ── Constants ───────────────────────────────── */
var _HEATMAP_NO_DATA_COLOR = 'rgba(128,128,128,0.15)';

/*
 * ──────────────────────────────────────────────
 *  Entry point — load data and render both heatmaps
 * ──────────────────────────────────────────────
 */
function initTimeline() {
    var url = <?php echo json_encode($jsonUrl); ?>;

    jQuery.getJSON(url, function(data) {
        if (data.error || !Array.isArray(data) || data.length === 0) {
            // No data available — show placeholder message
            document.getElementById('availability-heatmap').innerHTML =
                '<p style="color:var(--body-color, #888); opacity:0.6; text-align:center; padding:20px;">'
                + _rptI18n.noDataAvailable + '</p>';
            document.getElementById('alerts-heatmap').innerHTML = '';
            return;
        }

        _heatmapData = data;
        _heatmapYear = new Date().getFullYear();

        renderHeatmap();
        renderAlertHeatmap();
    });
}

/**
 * Navigate to a different year.
 * Called by the ◀/▶ buttons in the heatmap navigation bar.
 *
 * @param {number} delta - Year offset (+1 or -1)
 */
function heatmapChangeYear(delta) {
    _heatmapYear += delta;
    renderHeatmap();
    renderAlertHeatmap();
}

/*
 * ──────────────────────────────────────────────
 *  Utility functions
 * ──────────────────────────────────────────────
 */

/**
 * Build a date-indexed lookup map from the raw data array.
 * Also collects which years have data (for navigation bounds).
 *
 * @param {Array} data - Raw JSON array from GetJsonData.php
 * @returns {{ dataMap: Object, availableYears: Object }}
 */
function _buildDataMap(data) {
    var dataMap = {};
    var availableYears = {};
    data.forEach(function(d) {
        dataMap[d.date] = d;
        availableYears[parseInt(d.date.substring(0, 4))] = true;
    });
    return { dataMap: dataMap, availableYears: availableYears };
}

/**
 * Extract the last 30 days of data from a date-indexed map.
 * Used for the trend charts displayed alongside each heatmap.
 *
 * @param {Object} dataMap - Date string → data object lookup
 * @returns {{ labels: string[], entries: (Object|null)[] }}
 */
function getLast30Days(dataMap) {
    var labels = [];
    var entries = [];
    var today = new Date();

    for (var i = 29; i >= 0; i--) {
        var d = new Date(today);
        d.setDate(d.getDate() - i);

        // Build ISO date string for lookup (YYYY-MM-DD)
        var ds = d.getFullYear()
            + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1)
            + '-' + (d.getDate() < 10 ? '0' : '') + d.getDate();

        // Short label for chart X axis (M/D)
        labels.push((d.getMonth() + 1) + '/' + d.getDate());
        entries.push(dataMap[ds] || null);
    }

    return { labels: labels, entries: entries };
}

/**
 * Detect dark/light theme by inspecting body background color.
 * Returns colors adapted for Chart.js grid, ticks and labels.
 *
 * @returns {{ isDark: boolean, gridColor: string, tickColor: string, labelColor: string }}
 */
function _rptGetChartTheme() {
    var bodyBg = getComputedStyle(document.body).backgroundColor || '';
    var isDark = bodyBg.indexOf('33, 33, 33') !== -1
        || bodyBg.indexOf('21, 21, 21') !== -1
        || bodyBg === 'rgb(33, 33, 33)'
        || bodyBg === 'rgb(21, 21, 21)';

    return {
        isDark: isDark,
        gridColor: isDark ? 'rgba(255,255,255,0.08)' : '#f0f0f0',
        tickColor: isDark ? '#b2aca2' : '#666',
        labelColor: isDark ? '#b2aca2' : '#666'
    };
}

/*
 * ══════════════════════════════════════════════════════════════
 *  Shared Grid Builder
 * ══════════════════════════════════════════════════════════════
 *
 *  Builds the calendar grid structure used by both heatmaps:
 *    ┌──────────────────────────────┬──────────────────┐
 *    │  ◀ 2024  [2025]  2026 ▶     │                  │
 *    │  Jan  Feb  Mar  ...  Dec     │  30-day trend    │
 *    │  Mo                          │  chart (canvas)  │
 *    │  Tu  ■ ■ ■ ■ ■ ...          │                  │
 *    │  We                          │                  │
 *    │  ...                         │                  │
 *    │  Legend: □ □ □ □ □           │                  │
 *    └──────────────────────────────┴──────────────────┘
 */

/**
 * Build and render the heatmap grid into a container element.
 *
 * @param {string}   containerId   - DOM element id to render into
 * @param {Object}   dataMap       - Date string → data object lookup
 * @param {Object}   availableYears - Year → true lookup (navigation bounds)
 * @param {Function} colorFn       - (dateStr, entry) → CSS color string
 * @param {Function} tooltipFn     - (dateStr, entry) → tooltip HTML string
 * @param {string}   legendHtml    - HTML for the legend row
 * @param {string|null} title      - Optional section title
 */
function buildHeatmapGrid(containerId, dataMap, availableYears, colorFn, tooltipFn, legendHtml, title) {
    var year = _heatmapYear;
    var container = document.getElementById(containerId);
    if (!container) return;

    // Ensure current year is always navigable
    var currentYear = new Date().getFullYear();
    availableYears[currentYear] = true;

    // Compute navigation bounds
    var yearsList = Object.keys(availableYears).map(Number).sort();
    var minYear = yearsList[0];
    var maxYear = yearsList[yearsList.length - 1];
    if (year < minYear) { year = minYear; _heatmapYear = year; }
    if (year > maxYear) { year = maxYear; _heatmapYear = year; }

    // ── Build calendar weeks ──
    // Start from Jan 1, aligned to the previous Sunday
    var startDate = new Date(year, 0, 1);
    var endDate = new Date(year, 11, 31);
    var dayOfWeek = startDate.getDay();
    if (dayOfWeek > 0) {
        startDate = new Date(startDate);
        startDate.setDate(startDate.getDate() - dayOfWeek);
    }

    var weeks = [];
    var currentWeek = [];
    var cursor = new Date(startDate);
    while (cursor <= endDate) {
        currentWeek.push(new Date(cursor));
        if (currentWeek.length === 7) {
            weeks.push(currentWeek);
            currentWeek = [];
        }
        cursor.setDate(cursor.getDate() + 1);
    }
    if (currentWeek.length > 0) {
        weeks.push(currentWeek);
    }

    // Date formatting helpers
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toDateStr(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    // ── Year navigation buttons ──
    var navHtml = '<div class="heatmap-nav">'
        + '<button class="heatmap-nav-btn" onclick="heatmapChangeYear(-1)"'
        + (year <= minYear ? ' disabled' : '') + '>'
        + '&#9664; ' + (year - 1) + '</button>'
        + '<span class="heatmap-nav-year">' + year + '</span>'
        + '<button class="heatmap-nav-btn" onclick="heatmapChangeYear(1)"'
        + (year >= maxYear ? ' disabled' : '') + '>'
        + (year + 1) + ' &#9654;</button>'
        + '</div>';

    // ── Month labels (positioned above grid columns) ──
    var monthsHtml = '<div class="heatmap-months">';
    var monthNames = _rptI18n.months;
    var lastMonth = -1;
    weeks.forEach(function(week, i) {
        // Use mid-week day as reference to determine which month this column belongs to
        var refDay = week.length > 3 ? week[3] : week[week.length - 1];
        var m = refDay.getMonth();
        if (m !== lastMonth) {
            // Count consecutive weeks in this month for width calculation
            var weeksInMonth = 0;
            for (var j = i; j < weeks.length; j++) {
                var ref = weeks[j].length > 3 ? weeks[j][3] : weeks[j][weeks[j].length - 1];
                if (ref.getMonth() === m) {
                    weeksInMonth++;
                } else {
                    break;
                }
            }
            // 15px = cell width (13px) + gap (2px)
            monthsHtml += '<span style="width:' + (weeksInMonth * 15) + 'px">'
                + monthNames[m] + '</span>';
            lastMonth = m;
        }
    });
    monthsHtml += '</div>';

    // ── Day-of-week labels (left column: Mon, Wed, Fri) ──
    var dayLabels = _rptI18n.days;
    var daysLabelHtml = '<div class="heatmap-days-label">';
    for (var i = 0; i < 7; i++) {
        // Show label only for odd rows (Mon=1, Wed=3, Fri=5)
        daysLabelHtml += '<span>' + (i % 2 === 1 ? dayLabels[i] : '') + '</span>';
    }
    daysLabelHtml += '</div>';

    // ── Grid cells ──
    var gridHtml = '<div class="heatmap-grid">';
    weeks.forEach(function(week, idx) {
        gridHtml += '<div class="heatmap-week">';

        // Pad first week with invisible cells if it starts mid-week
        if (idx === 0 && week.length < 7) {
            for (var p = 0; p < 7 - week.length; p++) {
                gridHtml += '<div class="heatmap-cell" style="visibility:hidden"></div>';
            }
        }

        week.forEach(function(day) {
            // Hide days that belong to adjacent years (from Sunday alignment)
            if (day.getFullYear() !== year) {
                gridHtml += '<div class="heatmap-cell" style="visibility:hidden"></div>';
                return;
            }

            var dateStr = toDateStr(day);
            var entry = dataMap[dateStr] || null;
            var bgColor = colorFn(dateStr, entry);
            var tooltip = tooltipFn(dateStr, entry);

            // Tooltip position: below for Sun-Tue (top rows), above for Wed-Sat
            var tipClass = day.getDay() < 3 ? 'heatmap-tip-bottom' : 'heatmap-tip-top';

            gridHtml += '<div class="heatmap-cell" style="background:' + bgColor + '">'
                + '<div class="heatmap-tooltip ' + tipClass + '">' + tooltip + '</div>'
                + '</div>';
        });

        gridHtml += '</div>';
    });
    gridHtml += '</div>';

    // ── Canvas ID for the 30-day trend chart ──
    var chartCanvasId = containerId + '-chart30';

    // ── Assemble final HTML ──
    // Wrapped in a ListTable to match the Centreon reporting UI style
    var titleRow = title
        ? '<tr class="ListHeader"><td class="FormHeader" colspan="2">&nbsp;' + title + '</td></tr>'
        : '';

    container.innerHTML =
        '<table class="ListTable" style="width:100%;">'
        + titleRow
        + '<tr>'
        // Left column: heatmap grid
        + '<td style="padding:10px;">'
        + '<div class="heatmap-container">'
        + navHtml + monthsHtml
        + '<div class="heatmap-body">' + daysLabelHtml + gridHtml + '</div>'
        + legendHtml
        + '</div>'
        + '</td>'
        // Right column: 30-day trend chart
        + '<td style="padding:10px; width:480px; vertical-align:top;'
        + ' border-left:1px solid var(--list-table-border-color, #ddd);">'
        + '<div style="padding-top:8px;">'
        + '<div style="font-size:11px; font-weight:600; color:var(--body-color, #666);'
        + ' opacity:0.7; margin-bottom:6px; text-align:left; padding-left:8px;">'
        + _rptI18n.evolution30days + '</div>'
        + '<div style="position:relative; height:140px;">'
        + '<canvas id="' + chartCanvasId + '"></canvas>'
        + '</div>'
        + '</div>'
        + '</td>'
        + '</tr>'
        + '</table>';
}

/*
 * ══════════════════════════════════════════════════════════════
 *  Availability Heatmap
 * ══════════════════════════════════════════════════════════════
 *
 *  Color scale (green = good, red = bad):
 *    >=99.9% up/ok  → dark green (#216e39)
 *    >=99%          → medium green (#30a14e)
 *    >=95%          → green (#40c463)
 *    >=80%          → light green (#9be9a8)
 *    >0%           → pale green (#c6e48b)
 *    warning >=1%   → light orange (#ffe0b2)
 *    critical >=1%  → light red (#e8a0a0)
 *    critical >=10% → red (from colors config)
 *    no data        → grey
 */
function renderHeatmap() {
    var data = _heatmapData;
    var isHostType = _heatmapIsHostType;
    var colors = _heatmapColors;

    if (!data) return;

    var parsed = _buildDataMap(data);
    var dataMap = parsed.dataMap;
    var availableYears = parsed.availableYears;

    /**
     * Determine cell color based on availability percentage.
     * Priority: critical > warning > good (green scale) > undetermined
     */
    function colorFn(dateStr, d) {
        if (!d) return _HEATMAP_NO_DATA_COLOR;

        var goodPct = isHostType ? (d.up || 0) : (d.ok || 0);
        var badPct = isHostType ? (d.down || 0) + (d.unreachable || 0) : (d.critical || 0);
        var warnPct = isHostType ? 0 : (d.warning || 0);

        // Bad states take priority
        if (badPct >= 10) return '#' + (colors.critical || colors.down);
        if (badPct >= 1)  return '#e8a0a0';
        if (warnPct >= 10) return '#' + colors.warning;
        if (warnPct >= 1)  return '#ffe0b2';

        // Green scale for good availability
        if (goodPct >= 99.9) return '#216e39';
        if (goodPct >= 99)   return '#30a14e';
        if (goodPct >= 95)   return '#40c463';
        if (goodPct >= 80)   return '#9be9a8';
        if (goodPct > 0)     return '#c6e48b';

        return '#' + colors.undetermined;
    }

    /**
     * Build tooltip HTML with status breakdown and mini status bar.
     */
    function tooltipFn(dateStr, d) {
        var dateObj = new Date(dateStr + 'T00:00:00');
        var dateLabel = dateObj.toLocaleDateString(undefined, {
            weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
        });

        if (!d) return '<b>' + dateLabel + '</b><br>' + _rptI18n.noData;

        // Status percentage lines
        var lines = '<b>' + dateLabel + '</b><br>';
        if (isHostType) {
            lines += _tooltipLine(colors.up, _rptI18n.up, d.up + '%');
            lines += _tooltipLine(colors.down, _rptI18n.down, d.down + '%');
            lines += _tooltipLine(colors.unreachable, _rptI18n.unreachable, d.unreachable + '%');
            if (d.maintenance > 0) {
                lines += _tooltipLine(colors.maintenance, _rptI18n.downtime, d.maintenance + '%');
            }
            lines += _tooltipLine(colors.undetermined, _rptI18n.undetermined, d.undetermined + '%', true);
        } else {
            lines += _tooltipLine(colors.ok, _rptI18n.ok, d.ok + '%');
            lines += _tooltipLine(colors.warning, _rptI18n.warning, d.warning + '%');
            lines += _tooltipLine(colors.critical, _rptI18n.critical, d.critical + '%');
            lines += _tooltipLine(colors.unknown, _rptI18n.unknown, d.unknown + '%');
            if (d.maintenance > 0) {
                lines += _tooltipLine(colors.maintenance, _rptI18n.downtime, d.maintenance + '%');
            }
            lines += _tooltipLine(colors.undetermined, _rptI18n.undetermined, d.undetermined + '%', true);
        }

        // Mini stacked status bar
        lines += _buildStatusBar(d, isHostType, colors);
        return lines;
    }

    // ── Legend ──
    var legendHtml = '<div class="heatmap-legend">'
        + '<span>' + _rptI18n.less + '</span>'
        + _legendCell(_HEATMAP_NO_DATA_COLOR)
        + _legendCell('#c6e48b')
        + _legendCell('#9be9a8')
        + _legendCell('#40c463')
        + _legendCell('#30a14e')
        + _legendCell('#216e39')
        + '<span>' + _rptI18n.moreAvailable + '</span>'
        + '<span style="margin-left:15px">|</span>'
        + _legendCell('#ffe0b2', 8)
        + '<span>' + _rptI18n.warning + '</span>'
        + _legendCell('#e8a0a0', 8)
        + _legendCell('#' + (colors.critical || colors.down))
        + '<span>' + _rptI18n.critical + '</span>'
        + '</div>';

    // ── Render grid ──
    buildHeatmapGrid('availability-heatmap', dataMap, availableYears, colorFn, tooltipFn, legendHtml, _rptI18n.availability);

    // ── 30-day availability trend (line chart) ──
    var last30 = getLast30Days(dataMap);
    var ctx30 = document.getElementById('availability-heatmap-chart30');
    if (ctx30 && last30.labels.length > 0) {
        if (window._availChart30) { window._availChart30.destroy(); }

        var goodKey = isHostType ? 'up' : 'ok';
        var vals = last30.entries.map(function(d) { return d ? (d[goodKey] || 0) : null; });
        var theme = _rptGetChartTheme();

        window._availChart30 = new Chart(ctx30, {
            type: 'line',
            data: {
                labels: last30.labels,
                datasets: [{
                    label: (isHostType ? _rptI18n.up : _rptI18n.ok) + ' %',
                    data: vals,
                    borderColor: '#30a14e',
                    backgroundColor: 'rgba(48,161,78,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 1,
                    pointHoverRadius: 4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            callback: function(v) { return v + '%'; },
                            font: { size: 10 },
                            color: theme.tickColor
                        },
                        grid: { color: theme.gridColor }
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 6,
                            font: { size: 9 },
                            maxRotation: 0,
                            color: theme.tickColor
                        },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c) { return c.parsed.y + '%'; }
                        }
                    }
                }
            }
        });
    }
}

/*
 * ══════════════════════════════════════════════════════════════
 *  Alerts Heatmap
 * ══════════════════════════════════════════════════════════════
 *
 *  Color scale (green = calm, red = noisy):
 *    0 alerts     → dark green (#216e39)
 *    1-2 alerts   → pale green (#c6e48b)
 *    3-5 alerts   → light orange (#ffe0b2)
 *    6-10 alerts  → orange (#ffab91)
 *    11-20 alerts → light red (#e8a0a0)
 *    20+ alerts   → red (from colors config)
 *    no data      → grey
 */
function renderAlertHeatmap() {
    var data = _heatmapData;
    var isHostType = _heatmapIsHostType;
    var colors = _heatmapColors;

    if (!data) return;

    var container = document.getElementById('alerts-heatmap');
    if (!container) return;

    var parsed = _buildDataMap(data);
    var dataMap = parsed.dataMap;
    var availableYears = parsed.availableYears;

    /**
     * Determine cell color based on total alert count for the day.
     */
    function colorFn(dateStr, d) {
        if (!d) return _HEATMAP_NO_DATA_COLOR;
        var total = d.alerts_total || 0;
        if (total === 0)  return '#216e39';
        if (total <= 2)   return '#c6e48b';
        if (total <= 5)   return '#ffe0b2';
        if (total <= 10)  return '#ffab91';
        if (total <= 20)  return '#e8a0a0';
        return '#' + (colors.critical || colors.down);
    }

    /**
     * Build tooltip HTML with alert count breakdown by status.
     */
    function tooltipFn(dateStr, d) {
        var dateObj = new Date(dateStr + 'T00:00:00');
        var dateLabel = dateObj.toLocaleDateString(undefined, {
            weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
        });

        if (!d) return '<b>' + dateLabel + '</b><br>' + _rptI18n.noData;

        var total = d.alerts_total || 0;
        var lines = '<b>' + dateLabel + '</b><br>'
            + '<b>' + total + ' ' + (total !== 1 ? _rptI18n.alertsPlural : _rptI18n.alert) + '</b><br>';

        if (isHostType) {
            lines += _tooltipLine(colors.up, _rptI18n.up, d.alerts_up || 0);
            lines += _tooltipLine(colors.down, _rptI18n.down, d.alerts_down || 0);
            lines += _tooltipLine(colors.unreachable || 'aaa', _rptI18n.unreachable, d.alerts_unreachable || 0, true);
        } else {
            lines += _tooltipLine(colors.ok, _rptI18n.ok, d.alerts_ok || 0);
            lines += _tooltipLine(colors.warning, _rptI18n.warning, d.alerts_warning || 0);
            lines += _tooltipLine(colors.critical, _rptI18n.critical, d.alerts_critical || 0);
            lines += _tooltipLine(colors.unknown || 'aaa', _rptI18n.unknown, d.alerts_unknown || 0, true);
        }

        return lines;
    }

    // ── Legend ──
    var legendHtml = '<div class="heatmap-legend">'
        + '<span>' + _rptI18n.zeroAlerts + '</span>'
        + _legendCell('#216e39')
        + _legendCell('#c6e48b')
        + _legendCell('#ffe0b2')
        + _legendCell('#ffab91')
        + _legendCell('#e8a0a0')
        + _legendCell('#' + (colors.critical || colors.down))
        + '<span>' + _rptI18n.twentyPlusAlerts + '</span>'
        + '<span style="margin-left:15px">|</span>'
        + _legendCell(_HEATMAP_NO_DATA_COLOR, 8)
        + '<span>' + _rptI18n.noData + '</span>'
        + '</div>';

    // ── Render grid ──
    buildHeatmapGrid('alerts-heatmap', dataMap, availableYears, colorFn, tooltipFn, legendHtml, _rptI18n.alerts);

    // ── 30-day alerts trend (bar chart) ──
    var last30 = getLast30Days(dataMap);
    var ctx30 = document.getElementById('alerts-heatmap-chart30');
    if (ctx30 && last30.labels.length > 0) {
        if (window._alertChart30) { window._alertChart30.destroy(); }

        var vals = last30.entries.map(function(d) { return d ? (d.alerts_total || 0) : 0; });

        // Color each bar based on alert severity
        var barColors = vals.map(function(v) {
            if (v === 0)  return '#30a14e';
            if (v <= 2)   return '#c6e48b';
            if (v <= 5)   return '#ffe0b2';
            if (v <= 10)  return '#ffab91';
            if (v <= 20)  return '#e8a0a0';
            return '#' + (colors.critical || colors.down);
        });

        var theme = _rptGetChartTheme();

        window._alertChart30 = new Chart(ctx30, {
            type: 'bar',
            data: {
                labels: last30.labels,
                datasets: [{
                    label: _rptI18n.alerts,
                    data: vals,
                    backgroundColor: barColors,
                    borderRadius: 2,
                    barPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 10 },
                            color: theme.tickColor
                        },
                        grid: { color: theme.gridColor }
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 6,
                            font: { size: 9 },
                            maxRotation: 0,
                            color: theme.tickColor
                        },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c) { return c.parsed.y + ' ' + _rptI18n.alertsPlural; }
                        }
                    }
                }
            }
        });
    }
}

/*
 * ──────────────────────────────────────────────
 *  Private helpers for tooltip & legend HTML
 * ──────────────────────────────────────────────
 */

/**
 * Build a single tooltip line: colored dot + label + value.
 *
 * @param {string}  color  - Hex color without # prefix
 * @param {string}  label  - Status label (e.g. "Up", "Critical")
 * @param {string}  value  - Display value (e.g. "99.5%", "3")
 * @param {boolean} isLast - If true, omit trailing <br>
 * @returns {string} HTML string
 */
function _tooltipLine(color, label, value, isLast) {
    return '<span style="color:#' + color + '">&#9679;</span> '
        + label + ': ' + value + (isLast ? '' : '<br>');
}

/**
 * Build a legend cell (colored square).
 *
 * @param {string} color      - CSS color value
 * @param {number} marginLeft - Optional left margin in px
 * @returns {string} HTML string
 */
function _legendCell(color, marginLeft) {
    var style = 'background:' + color;
    if (marginLeft) style += ';margin-left:' + marginLeft + 'px';
    return '<div class="heatmap-legend-cell" style="' + style + '"></div>';
}

/**
 * Build a mini stacked status bar for the tooltip.
 * Shows proportional widths of each status as colored segments.
 *
 * @param {Object}  d          - Data entry with percentage values
 * @param {boolean} isHostType - True for host statuses, false for service
 * @param {Object}  colors     - Color map from Centreon config
 * @returns {string} HTML string
 */
function _buildStatusBar(d, isHostType, colors) {
    var bar = '<div class="heatmap-status-bar">';
    if (isHostType) {
        if (d.up > 0) bar += '<div style="width:' + d.up + '%;background:#' + colors.up + '"></div>';
        if (d.down > 0) bar += '<div style="width:' + d.down + '%;background:#' + colors.down + '"></div>';
        if (d.unreachable > 0) bar += '<div style="width:' + d.unreachable + '%;background:#' + colors.unreachable + '"></div>';
        if (d.undetermined > 0) bar += '<div style="width:' + d.undetermined + '%;background:#' + colors.undetermined + '"></div>';
    } else {
        if (d.ok > 0) bar += '<div style="width:' + d.ok + '%;background:#' + colors.ok + '"></div>';
        if (d.warning > 0) bar += '<div style="width:' + d.warning + '%;background:#' + colors.warning + '"></div>';
        if (d.critical > 0) bar += '<div style="width:' + d.critical + '%;background:#' + colors.critical + '"></div>';
        if (d.unknown > 0) bar += '<div style="width:' + d.unknown + '%;background:#' + colors.unknown + '"></div>';
        if (d.undetermined > 0) bar += '<div style="width:' + d.undetermined + '%;background:#' + colors.undetermined + '"></div>';
    }
    bar += '</div>';
    return bar;
}
</script>
