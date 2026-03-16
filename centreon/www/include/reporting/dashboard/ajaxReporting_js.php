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
require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');

$arg = 'type=' . urlencode($type) . '&';
$arg .= $type == 'Service' ? 'id=' . $serviceId . '&host_id=' . $hostId : 'id=' . $id;

$jsonUrl = './include/reporting/dashboard/xmlInformations/GetJsonData.php?' . $arg;

// Determine if this is a host-type or service-type view
$isHostType = in_array($type, ['Host', 'HostGroup']);

// Pass colors to JS
$colorsJson = json_encode($colors);

?>
<style>
.heatmap-container {
    padding: 10px 0;
    overflow-x: auto;
}
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
/* Default: tooltip above the cell */
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
/* For top rows: tooltip below the cell */
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
var _heatmapData = null;
var _heatmapYear = null;
var _heatmapIsHostType = <?php echo $isHostType ? 'true' : 'false'; ?>;
var _heatmapColors = <?php echo $colorsJson; ?>;
var _rptI18n = <?php echo $tpl->getTemplateVars('jsTranslationsJson'); ?>;

function initTimeline() {
    var url = <?php echo json_encode($jsonUrl); ?>;

    jQuery.getJSON(url, function(data) {
        if (data.error || !Array.isArray(data) || data.length === 0) {
            document.getElementById('availability-heatmap').innerHTML =
                '<p style="color:var(--body-color, #888); opacity:0.6; text-align:center; padding:20px;">' + _rptI18n.noDataAvailable + '</p>';
            document.getElementById('alerts-heatmap').innerHTML = '';
            return;
        }

        _heatmapData = data;

        var today = new Date();
        _heatmapYear = today.getFullYear();

        renderHeatmap();
        renderAlertHeatmap();
    });
}

function heatmapChangeYear(delta) {
    _heatmapYear += delta;
    renderHeatmap();
    renderAlertHeatmap();
}

/**
 * Build the common heatmap grid structure (year nav, months, day labels, cells, legend).
 * @param {string} containerId - DOM element id
 * @param {object} dataMap - date string -> data object lookup
 * @param {object} availableYears - year -> true lookup
 * @param {function} colorFn - function(dateStr, dataEntry) -> color string
 * @param {function} tooltipFn - function(dateStr, dataEntry) -> HTML string
 * @param {string} legendHtml - HTML for the legend row
 * @param {string|null} title - optional title above the heatmap
 */
function buildHeatmapGrid(containerId, dataMap, availableYears, colorFn, tooltipFn, legendHtml, title) {
    var year = _heatmapYear;
    var container = document.getElementById(containerId);
    if (!container) return;

    var currentYear = new Date().getFullYear();
    availableYears[currentYear] = true;

    var yearsList = Object.keys(availableYears).map(Number).sort();
    var minYear = yearsList[0];
    var maxYear = yearsList[yearsList.length - 1];

    if (year < minYear) { year = minYear; _heatmapYear = year; }
    if (year > maxYear) { year = maxYear; _heatmapYear = year; }

    // Date range: Jan 1 -> Dec 31
    var startDate = new Date(year, 0, 1);
    var endDate = new Date(year, 11, 31);

    // Align startDate to previous Sunday
    var dayOfWeek = startDate.getDay();
    if (dayOfWeek > 0) {
        startDate = new Date(startDate);
        startDate.setDate(startDate.getDate() - dayOfWeek);
    }

    // Build weeks array
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

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toDateStr(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    // Year navigation
    var navHtml = '<div class="heatmap-nav">';
    navHtml += '<button class="heatmap-nav-btn" onclick="heatmapChangeYear(-1)"' + (year <= minYear ? ' disabled' : '') + '>◀ ' + (year - 1) + '</button>';
    navHtml += '<span class="heatmap-nav-year">' + year + '</span>';
    navHtml += '<button class="heatmap-nav-btn" onclick="heatmapChangeYear(1)"' + (year >= maxYear ? ' disabled' : '') + '>' + (year + 1) + ' ▶</button>';
    navHtml += '</div>';

    // Month labels
    var monthsHtml = '<div class="heatmap-months">';
    var monthNames = _rptI18n.months;
    var lastMonth = -1;
    weeks.forEach(function(week, i) {
        var refDay = week.length > 3 ? week[3] : week[week.length - 1];
        var m = refDay.getMonth();
        if (m !== lastMonth) {
            var weeksInMonth = 0;
            for (var j = i; j < weeks.length; j++) {
                var ref = weeks[j].length > 3 ? weeks[j][3] : weeks[j][weeks[j].length - 1];
                if (ref.getMonth() === m) {
                    weeksInMonth++;
                } else {
                    break;
                }
            }
            monthsHtml += '<span style="width:' + (weeksInMonth * 15) + 'px">' + monthNames[m] + '</span>';
            lastMonth = m;
        }
    });
    monthsHtml += '</div>';

    // Day labels
    var dayLabels = _rptI18n.days;
    var daysLabelHtml = '<div class="heatmap-days-label">';
    for (var i = 0; i < 7; i++) {
        daysLabelHtml += '<span>' + (i % 2 === 1 ? dayLabels[i] : '') + '</span>';
    }
    daysLabelHtml += '</div>';

    // Grid
    var gridHtml = '<div class="heatmap-grid">';
    weeks.forEach(function(week, idx) {
        gridHtml += '<div class="heatmap-week">';
        if (idx === 0 && week.length < 7) {
            for (var p = 0; p < 7 - week.length; p++) {
                gridHtml += '<div class="heatmap-cell" style="visibility:hidden"></div>';
            }
        }
        week.forEach(function(day) {
            if (day.getFullYear() !== year) {
                gridHtml += '<div class="heatmap-cell" style="visibility:hidden"></div>';
                return;
            }
            var dateStr = toDateStr(day);
            var entry = dataMap[dateStr] || null;
            var bgColor = colorFn(dateStr, entry);
            var tooltip = tooltipFn(dateStr, entry);
            var tipClass = day.getDay() < 3 ? 'heatmap-tip-bottom' : 'heatmap-tip-top';
            gridHtml += '<div class="heatmap-cell" style="background:' + bgColor + '">'
                + '<div class="heatmap-tooltip ' + tipClass + '">' + tooltip + '</div>'
                + '</div>';
        });
        gridHtml += '</div>';
    });
    gridHtml += '</div>';

    // Unique canvas id for the 30-day chart
    var chartCanvasId = containerId + '-chart30';

    // Assemble — wrapped in a ListTable frame like the summary tables above
    var titleRow = title
        ? '<tr class="ListHeader"><td class="FormHeader" colspan="2">&nbsp;' + title + '</td></tr>'
        : '';
    container.innerHTML =
        '<table class="ListTable" style="width:100%;">'
        + titleRow
        + '<tr>'
        + '<td style="padding:10px;">'
        + '<div class="heatmap-container">'
        + navHtml
        + monthsHtml
        + '<div class="heatmap-body">'
        + daysLabelHtml
        + gridHtml
        + '</div>'
        + legendHtml
        + '</div>'
        + '</td>'
        + '<td style="padding:10px; width:480px; vertical-align:top; border-left:1px solid var(--list-table-border-color, #ddd);">'
        + '<div style="padding-top:8px;">'
        + '<div style="font-size:11px; font-weight:600; color:var(--body-color, #666); opacity:0.7; margin-bottom:6px; text-align:left; padding-left:8px;">' + _rptI18n.evolution30days + '</div>'
        + '<div style="position:relative; height:140px;">'
        + '<canvas id="' + chartCanvasId + '"></canvas>'
        + '</div>'
        + '</div>'
        + '</td>'
        + '</tr>'
        + '</table>';
}

/* Detect dark theme for Chart.js colors */
function _rptGetChartTheme() {
    var bodyBg = getComputedStyle(document.body).backgroundColor || '';
    var isDark = bodyBg.indexOf('33, 33, 33') !== -1 || bodyBg.indexOf('21, 21, 21') !== -1 || bodyBg === 'rgb(33, 33, 33)' || bodyBg === 'rgb(21, 21, 21)';
    return {
        isDark: isDark,
        gridColor: isDark ? 'rgba(255,255,255,0.08)' : '#f0f0f0',
        tickColor: isDark ? '#b2aca2' : '#666',
        labelColor: isDark ? '#b2aca2' : '#666'
    };
}

/* ========================================================
 *  AVAILABILITY HEATMAP
 * ======================================================== */
function renderHeatmap() {
    var data = _heatmapData;
    var isHostType = _heatmapIsHostType;
    var colors = _heatmapColors;

    if (!data) return;

    var dataMap = {};
    var availableYears = {};
    data.forEach(function(d) {
        dataMap[d.date] = d;
        availableYears[parseInt(d.date.substring(0, 4))] = true;
    });

    // Color function
    function colorFn(dateStr, d) {
        if (!d) return 'rgba(128,128,128,0.15)';
        var goodPct = isHostType ? (d.up || 0) : (d.ok || 0);
        var badPct = isHostType ? (d.down || 0) + (d.unreachable || 0) : (d.critical || 0);
        var warnPct = isHostType ? 0 : (d.warning || 0);

        if (badPct >= 10) return '#' + (colors.critical || colors.down);
        if (badPct >= 1) return '#e8a0a0';
        if (warnPct >= 10) return '#' + colors.warning;
        if (warnPct >= 1) return '#ffe0b2';
        if (goodPct >= 99.9) return '#216e39';
        if (goodPct >= 99) return '#30a14e';
        if (goodPct >= 95) return '#40c463';
        if (goodPct >= 80) return '#9be9a8';
        if (goodPct > 0) return '#c6e48b';
        return '#' + colors.undetermined;
    }

    // Tooltip function
    function tooltipFn(dateStr, d) {
        var dateObj = new Date(dateStr + 'T00:00:00');
        var dateLabel = dateObj.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });

        if (!d) return '<b>' + dateLabel + '</b><br>' + _rptI18n.noData;

        var lines = '<b>' + dateLabel + '</b><br>';
        if (isHostType) {
            lines += '<span style="color:#' + colors.up + '">●</span> ' + _rptI18n.up + ': ' + d.up + '%<br>';
            lines += '<span style="color:#' + colors.down + '">●</span> ' + _rptI18n.down + ': ' + d.down + '%<br>';
            lines += '<span style="color:#' + colors.unreachable + '">●</span> ' + _rptI18n.unreachable + ': ' + d.unreachable + '%<br>';
            if (d.maintenance > 0) lines += '<span style="color:#' + colors.maintenance + '">●</span> ' + _rptI18n.downtime + ': ' + d.maintenance + '%<br>';
            lines += '<span style="color:#' + colors.undetermined + '">●</span> ' + _rptI18n.undetermined + ': ' + d.undetermined + '%';
        } else {
            lines += '<span style="color:#' + colors.ok + '">●</span> ' + _rptI18n.ok + ': ' + d.ok + '%<br>';
            lines += '<span style="color:#' + colors.warning + '">●</span> ' + _rptI18n.warning + ': ' + d.warning + '%<br>';
            lines += '<span style="color:#' + colors.critical + '">●</span> ' + _rptI18n.critical + ': ' + d.critical + '%<br>';
            lines += '<span style="color:#' + colors.unknown + '">●</span> ' + _rptI18n.unknown + ': ' + d.unknown + '%<br>';
            if (d.maintenance > 0) lines += '<span style="color:#' + colors.maintenance + '">●</span> ' + _rptI18n.downtime + ': ' + d.maintenance + '%<br>';
            lines += '<span style="color:#' + colors.undetermined + '">●</span> ' + _rptI18n.undetermined + ': ' + d.undetermined + '%';
        }

        // Mini status bar
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
        return lines + bar;
    }

    // Legend
    var legendHtml = '<div class="heatmap-legend">';
    legendHtml += '<span>' + _rptI18n.less + '</span>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:rgba(128,128,128,0.15)"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#c6e48b"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#9be9a8"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#40c463"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#30a14e"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#216e39"></div>';
    legendHtml += '<span>' + _rptI18n.moreAvailable + '</span>';
    legendHtml += '<span style="margin-left:15px">|</span>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#ffe0b2;margin-left:8px"></div>';
    legendHtml += '<span>' + _rptI18n.warning + '</span>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#e8a0a0;margin-left:8px"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#' + (colors.critical || colors.down) + '"></div>';
    legendHtml += '<span>' + _rptI18n.critical + '</span>';
    legendHtml += '</div>';

    buildHeatmapGrid('availability-heatmap', dataMap, availableYears, colorFn, tooltipFn, legendHtml, _rptI18n.availability);

    // 30-day availability line chart
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
                    label: isHostType ? _rptI18n.up + ' %' : _rptI18n.ok + ' %',
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
                    y: { min: 0, max: 100, suggestedMax: 102, ticks: { stepSize: 20, callback: function(v) { return v + '%'; }, font: { size: 10 }, color: theme.tickColor }, grid: { color: theme.gridColor }, afterFit: function(axis) { axis.max = 102; } },
                    x: { ticks: { maxTicksLimit: 6, font: { size: 9 }, maxRotation: 0, color: theme.tickColor }, grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return c.parsed.y + '%'; } } }
                }
            }
        });
    }
}

/* ========================================================
 *  ALERTS HEATMAP
 * ======================================================== */
function renderAlertHeatmap() {
    var data = _heatmapData;
    var isHostType = _heatmapIsHostType;
    var colors = _heatmapColors;

    if (!data) return;

    var container = document.getElementById('alerts-heatmap');
    if (!container) return;

    var dataMap = {};
    var availableYears = {};
    data.forEach(function(d) {
        dataMap[d.date] = d;
        availableYears[parseInt(d.date.substring(0, 4))] = true;
    });

    // Alert color scale based on alerts_total
    // Grey = no data, Green = 0 alerts (calm day), then orange->red scale
    function colorFn(dateStr, d) {
        if (!d) return 'rgba(128,128,128,0.15)';
        var total = d.alerts_total || 0;
        if (total === 0) return '#216e39';
        if (total <= 2) return '#c6e48b';
        if (total <= 5) return '#ffe0b2';
        if (total <= 10) return '#ffab91';
        if (total <= 20) return '#e8a0a0';
        return '#' + (colors.critical || colors.down);
    }

    // Alert tooltip
    function tooltipFn(dateStr, d) {
        var dateObj = new Date(dateStr + 'T00:00:00');
        var dateLabel = dateObj.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });

        if (!d) return '<b>' + dateLabel + '</b><br>' + _rptI18n.noData;

        var total = d.alerts_total || 0;
        var lines = '<b>' + dateLabel + '</b><br>';
        lines += '<b>' + total + ' ' + (total !== 1 ? _rptI18n.alertsPlural : _rptI18n.alert) + '</b><br>';

        if (isHostType) {
            lines += '<span style="color:#' + colors.up + '">●</span> ' + _rptI18n.up + ': ' + (d.alerts_up || 0) + '<br>';
            lines += '<span style="color:#' + colors.down + '">●</span> ' + _rptI18n.down + ': ' + (d.alerts_down || 0) + '<br>';
            lines += '<span style="color:#' + (colors.unreachable || 'aaa') + '">●</span> ' + _rptI18n.unreachable + ': ' + (d.alerts_unreachable || 0);
        } else {
            lines += '<span style="color:#' + colors.ok + '">●</span> ' + _rptI18n.ok + ': ' + (d.alerts_ok || 0) + '<br>';
            lines += '<span style="color:#' + colors.warning + '">●</span> ' + _rptI18n.warning + ': ' + (d.alerts_warning || 0) + '<br>';
            lines += '<span style="color:#' + colors.critical + '">●</span> ' + _rptI18n.critical + ': ' + (d.alerts_critical || 0) + '<br>';
            lines += '<span style="color:#' + (colors.unknown || 'aaa') + '">●</span> ' + _rptI18n.unknown + ': ' + (d.alerts_unknown || 0);
        }

        return lines;
    }

    // Legend for alerts
    var legendHtml = '<div class="heatmap-legend">';
    legendHtml += '<span>' + _rptI18n.zeroAlerts + '</span>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#216e39"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#c6e48b"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#ffe0b2"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#ffab91"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#e8a0a0"></div>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:#' + (colors.critical || colors.down) + '"></div>';
    legendHtml += '<span>' + _rptI18n.twentyPlusAlerts + '</span>';
    legendHtml += '<span style="margin-left:15px">|</span>';
    legendHtml += '<div class="heatmap-legend-cell" style="background:rgba(128,128,128,0.15);margin-left:8px"></div>';
    legendHtml += '<span>' + _rptI18n.noData + '</span>';
    legendHtml += '</div>';

    buildHeatmapGrid('alerts-heatmap', dataMap, availableYears, colorFn, tooltipFn, legendHtml, _rptI18n.alerts);

    // 30-day alerts bar chart
    var last30 = getLast30Days(dataMap);
    var ctx30 = document.getElementById('alerts-heatmap-chart30');
    if (ctx30 && last30.labels.length > 0) {
        if (window._alertChart30) { window._alertChart30.destroy(); }
        var vals = last30.entries.map(function(d) { return d ? (d.alerts_total || 0) : 0; });
        var barColors = vals.map(function(v) {
            if (v === 0) return '#30a14e';
            if (v <= 2) return '#c6e48b';
            if (v <= 5) return '#ffe0b2';
            if (v <= 10) return '#ffab91';
            if (v <= 20) return '#e8a0a0';
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
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: theme.tickColor }, grid: { color: theme.gridColor } },
                    x: { ticks: { maxTicksLimit: 6, font: { size: 9 }, maxRotation: 0, color: theme.tickColor }, grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return c.parsed.y + ' ' + _rptI18n.alertsPlural; } } }
                }
            }
        });
    }
}

/**
 * Extract last 30 days of data from a dataMap
 * @param {object} dataMap - date string -> data object
 * @returns {object} { labels: string[], entries: (object|null)[] }
 */
function getLast30Days(dataMap) {
    var labels = [];
    var entries = [];
    var today = new Date();
    for (var i = 29; i >= 0; i--) {
        var d = new Date(today);
        d.setDate(d.getDate() - i);
        var ds = d.getFullYear() + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1) + '-' + (d.getDate() < 10 ? '0' : '') + d.getDate();
        var dayLabel = (d.getMonth() + 1) + '/' + d.getDate();
        labels.push(dayLabel);
        entries.push(dataMap[ds] || null);
    }
    return { labels: labels, entries: entries };
}
</script>
