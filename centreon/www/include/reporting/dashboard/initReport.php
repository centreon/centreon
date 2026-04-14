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

if (! isset($centreon)) {
    exit;
}

$path = './include/reporting/dashboard';

// Require Centreon Class
require_once './class/centreonDuration.class.php';
require_once './class/centreonDB.class.php';

// Require centreon common lib
require_once './include/reporting/dashboard/common-Func.php';
require_once './include/reporting/dashboard/DB-Func.php';
require_once './include/common/common-Func.php';

// Create DB connexion
$pearDBO = new CentreonDB('centstorage');

$debug = 0;

// QuickForm templates
$attrsTextI        = ['size' => '3'];
$attrsText        = ['size' => '30'];
$attrsTextarea    = ['rows' => '5', 'cols' => '40'];

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path, '');

$tpl->assign('o', $o);

// Assign centreon path
$tpl->assign('centreon_path', _CENTREON_PATH_);

// Status colors
$colors = ['up' => '88b917', 'down' => 'e00b3d', 'unreachable' => '818285', 'maintenance' => 'cc99ff', 'downtime' => 'cc99ff', 'ok' => '88b917', 'warning' => 'ff9a13', 'critical' => 'e00b3d', 'unknown' => 'bcbdc0', 'undetermined' => 'd1d2d4'];
$tpl->assign('colors', $colors);

// Convert hex colors to RGB triplets for rgba() usage in CSS
$colorsRgb = [];
foreach ($colors as $key => $hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $colorsRgb[$key] = "$r, $g, $b";
}
$tpl->assign('colors_rgb', $colorsRgb);

$color = [];
$color['UNKNOWN'] = $colors['unknown'];
$color['UP'] = $colors['up'];
$color['DOWN'] = $colors['down'];
$color['UNREACHABLE'] = $colors['unreachable'];
$color['UNDETERMINED'] = $colors['undetermined'];
$color['OK'] = $colors['ok'];
$color['WARNING'] = $colors['warning'];
$color['CRITICAL'] = $colors['critical'];
$tpl->assign('color', $color);

$startDate = 0;
$endDate = 0;

// Translations and styles

// Modern styles: neutral background, colored indicator dot instead of full background
$tpl->assign('style_ok', "class='ListColCenter reporting-cell' style='padding:5px;'");
$tpl->assign('style_ok_top', " style='color:#" . $colors['ok'] . "'");
$tpl->assign('style_ok_alert', "class='ListColCenter reporting-cell-alert' style='padding:5px;'");
$tpl->assign('style_warning', "class='ListColCenter reporting-cell' style='padding:5px;'");
$tpl->assign('style_warning_top', "style='color:#" . $colors['warning'] . "'");
$tpl->assign('style_warning_alert', "class='ListColCenter reporting-cell-alert' style='padding:5px;'");
$tpl->assign('style_critical', "class='ListColCenter reporting-cell' style='padding:5px;'");
$tpl->assign('style_critical_top', "style='color:#" . $colors['critical'] . "'");
$tpl->assign('style_critical_alert', "class='ListColCenter reporting-cell-alert' style='padding:5px;'");
$tpl->assign('style_unknown', "class='ListColCenter reporting-cell' style='padding:5px;'");
$tpl->assign('style_unknown_top', '');
$tpl->assign('style_unknown_alert', "class='ListColCenter reporting-cell-alert' style='padding:5px;'");
$tpl->assign('style_pending', "class='ListColCenter reporting-cell' style='padding:5px;'");
$tpl->assign('style_pending_top', '');
$tpl->assign('style_pending_alert', "class='ListColCenter reporting-cell-alert' style='padding:5px;'");
$tpl->assign('style_maintenance', "class='ListColCenter reporting-cell' style='padding:5px;'");
$tpl->assign('style_maintenance_top', "style='color:#" . $colors['maintenance'] . "'");

$tpl->assign('badge_UP', "class='ListColCenter state_badge host_up'");
$tpl->assign('badge_DOWN', "class='ListColCenter state_badge host_down'");
$tpl->assign('badge_UNREACHABLE', "class='ListColCenter state_badge host_unreachable'");
$tpl->assign('badge_UNDETERMINED', "class='ListColCenter state_badge badge_undetermined'");
$tpl->assign('badge_MAINTENANCE', "class='ListColCenter state_badge badge_downtime'");

$tpl->assign('badge_ok', "class='ListColCenter state_badge service_ok'");
$tpl->assign('badge_warning', "class='ListColCenter state_badge service_warning'");
$tpl->assign('badge_critical', "class='ListColCenter state_badge service_critical'");
$tpl->assign('badge_unknown', "class='ListColCenter state_badge service_unknown'");
$tpl->assign('badge_pending', "class='ListColCenter state_badge badge_undetermined'");
$tpl->assign('badge_maintenance', "class='ListColCenter state_badge badge_downtime'");

$tpl->assign('actualTitle', _('Actual'));

$tpl->assign('serviceTitle', _('Services'));
$tpl->assign('hostTitle', _('Host name'));
$tpl->assign('allTilte', _('All'));
$tpl->assign('averageTilte', _('Average'));

$tpl->assign('OKTitle', _('OK'));
$tpl->assign('WarningTitle', _('Warning'));
$tpl->assign('UnknownTitle', _('Unknown'));
$tpl->assign('CriticalTitle', _('Critical'));
$tpl->assign('PendingTitle', _('Undetermined'));
$tpl->assign('MaintenanceTitle', _('Scheduled downtime'));

$tpl->assign('stateLabel', _('State'));
$tpl->assign('totalLabel', _('Total'));
$tpl->assign('durationLabel', _('Duration'));
$tpl->assign('totalTimeLabel', _('Total Time'));
$tpl->assign('meanTimeLabel', _('Mean Time'));
$tpl->assign('alertsLabel', _('Alerts'));

$tpl->assign('DateTitle', _('Date'));
$tpl->assign('EventTitle', _('Event'));
$tpl->assign('InformationsTitle', _('Info'));

$tpl->assign('periodTitle', _('Reporting Period'));
$tpl->assign('periodORlabel', _('or'));
$tpl->assign('logTitle', _("Today's Host log"));
$tpl->assign('svcTitle', _('State Breakdowns For Host Services'));

// Additional translatable labels for reporting UI
$tpl->assign('availabilityReportTitle', _('Availability Report'));
$tpl->assign('hostLabel', _('Host'));
$tpl->assign('backToHostReport', _('Back to Host Report'));
$tpl->assign('statusLabel', _('Status'));
$tpl->assign('mtTtLabel', 'MT / TT');
$tpl->assign('ttLabel', 'TT');

// Labels for donut chart (passed to JS via Smarty)
$tpl->assign('upLabel', _('Up'));
$tpl->assign('downLabel', _('Down'));
$tpl->assign('unreachableLabel', _('Unreachable'));
$tpl->assign('downtimeLabel', _('Downtime'));
$tpl->assign('undeterminedLabel', _('Undetermined'));
$tpl->assign('okLabel', _('Ok'));
$tpl->assign('warningLabel', _('Warning'));
$tpl->assign('criticalLabel', _('Critical'));
$tpl->assign('unknownLabel', _('Unknown'));

// Page-level ACL: show nav pills only for pages the user can access (no extra query, read from session)
$tpl->assign('canAccessHostReport', $centreon->user->admin || $centreon->user->access->page(30701));
$tpl->assign('canAccessHostGroupReport', $centreon->user->admin || $centreon->user->access->page(30703));
$tpl->assign('canAccessServiceGroupReport', $centreon->user->admin || $centreon->user->access->page(30704));

// Tooltip descriptions
$tpl->assign('downtimeTooltip', _('Scheduled downtime — Planned maintenance periods during which the resource is intentionally taken offline.'));
$tpl->assign('undeterminedTooltip', _('Undetermined time — Periods with no monitoring data available, typically due to missing collection or incomplete history.'));

// Translatable strings for JS heatmap/charts (JSON-encoded for safe embedding)
$jsTranslations = [
    'noDataAvailable' => _('No data available'),
    'noData' => _('No data'),
    'evolution30days' => _('Evolution on the last 30 days'),
    'less' => _('Less'),
    'moreAvailable' => _('More available'),
    'alerts' => _('Alerts'),
    'alert' => _('alert'),
    'alertsPlural' => _('alerts'),
    'zeroAlerts' => _('0 alerts'),
    'twentyPlusAlerts' => _('20+ alerts'),
    'availability' => _('Availability'),
    'up' => _('Up'),
    'down' => _('Down'),
    'unreachable' => _('Unreachable'),
    'downtime' => _('Downtime'),
    'undetermined' => _('Undetermined'),
    'ok' => _('Ok'),
    'warning' => _('Warning'),
    'critical' => _('Critical'),
    'unknown' => _('Unknown'),
    'months' => [
        _('Jan'), _('Feb'), _('Mar'), _('Apr'), _('May'), _('Jun'),
        _('Jul'), _('Aug'), _('Sep'), _('Oct'), _('Nov'), _('Dec')
    ],
    'days' => [_('Sun'), _('Mon'), _('Tue'), _('Wed'), _('Thu'), _('Fri'), _('Sat')],
];
$tpl->assign('jsTranslationsJson', json_encode($jsTranslations));

// Definition of status
$state['UP'] = _('UP');
$state['DOWN'] = _('DOWN');
$state['UNREACHABLE'] = _('UNREACHABLE');
$state['UNDETERMINED'] = _('UNDETERMINED');
$state['MAINTENANCE'] = _('SCHEDULED DOWNTIME');
$tpl->assign('states', $state);

// CSS Definition for status colors
$style['UP'] = "style='padding:5px;color:#" . $colors['up'] . "'";
$style['UP_BOTTOM'] = "style='padding:5px;'";
$style['DOWN'] = "style='padding:5px;color:#" . $colors['down'] . "'";
$style['DOWN_BOTTOM'] = "style='padding:5px;'";
$style['UNREACHABLE'] = "style='padding:5px'";
$style['UNREACHABLE_BOTTOM'] = "style='padding:5px;'";
$style['UNDETERMINED'] = "style='padding:5px'";
$style['UNDETERMINED_BOTTOM'] = "style='padding:5px;'";
$style['MAINTENANCE'] = "style='padding:5px;color:#" . $colors['maintenance'] . "'";
$style['MAINTENANCE_BOTTOM'] = "style='padding:5px;'";
$tpl->assign('style', $style);

// Init Timeperiod List

// Getting period table list to make the form period selection (today, this week etc.)
$periodList = getPeriodList();

// Getting timeperiod by day (example : 9:30 to 19:30 on monday,tue,wed,thu,fri)
$reportingTimePeriod = getreportingTimePeriod();

// CSV export parameters
$var_url_export_csv = '';

// LCA
$lcaHoststr = $centreon->user->access->getHostsString('ID', $pearDBO);
$lcaHostGroupstr = $centreon->user->access->getHostGroupsString();
$lcaSvcstr    = $centreon->user->access->getServicesString('ID', $pearDBO);

// setting variables for link with services
$period_choice = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['period_choice'] ?? $_GET['period_choice'] ?? 'preset'
);

$period = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['period'] ?? $_GET['period'] ?? ''
);

$get_date_start = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_GET['start'] ?? $_POST['StartDate'] ?? $_GET['StartDate'] ?? ''
);

$get_date_end = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_GET['end'] ?? $_POST['EndDate'] ?? $_GET['EndDate'] ?? ''
);

if ($get_date_start == '' && $get_date_end == '' && $period == '') {
    $period = 'yesterday';
}
$tpl->assign('get_date_start', $get_date_start);
$tpl->assign('get_date_end', $get_date_end);
$tpl->assign('get_period', $period);
$tpl->assign('link_csv_url', null);
$tpl->assign('infosTitle', null);
// Settings default variables for all state.
$tpl->assign('name', null);
$tpl->assign('totalAlert', null);
$tpl->assign('totalTime', null);
$initialStates = [
    'OK_TP' => null,
    'OK_MP' => null,
    'OK_A' => null,
    'WARNING_TP' => null,
    'WARNING_MP' => null,
    'WARNING_A' => null,
    'CRITICAL_TP' => null,
    'CRITICAL_MP' => null,
    'CRITICAL_A' => null,
    'UNKNOWN_TP' => null,
    'UNKNOWN_MP' => null,
    'UNKNOWN_A' => null,
    'UP_TF' => null,
    'UP_TP' => null,
    'UP_MP' => null,
    'UP_A' => null,
    'DOWN_TF' => null,
    'DOWN_TP' => null,
    'DOWN_MP' => null,
    'DOWN_A' => null,
    'UNREACHABLE_TF' => null,
    'UNREACHABLE_TP' => null,
    'UNREACHABLE_MP' => null,
    'UNREACHABLE_A' => null,
    'UNDETERMINED_TF' => null,
    'UNDETERMINED_TP' => null,
    'MAINTENANCE_TF' => null,
    'MAINTENANCE_TP' => null,
    'NAME' => null,
    'ID' => null,
    'DESCRIPTION' => null,
    'HOST_ID' => null,
    'HOST_NAME' => null,
    'SERVICE_ID' => null,
    'SERVICE_DESC' => null,
];
$tpl->assign('summary', $initialStates);
$tpl->assign('components_avg', $initialStates);
$tpl->assign('components', ['tb' => $initialStates]);
$tpl->assign('period_name', _('From'));
$tpl->assign('date_start', null);
$tpl->assign('to', _('to'));
$tpl->assign('date_end', null);
$tpl->assign('period', null);
$tpl->assign('host_id', null);
$tpl->assign('hostgroup_id', null);
$tpl->assign('servicegroup_id', null);
$tpl->assign('Alert', _('Alert'));

$tpl->assign('host_up', null);
$tpl->assign('host_down', null);
$tpl->assign('host_unreachable', null);
$tpl->assign('host_undetermined', null);
$tpl->assign('host_maintenance', null);
$tpl->assign('service_ok', null);
$tpl->assign('service_warning', null);
$tpl->assign('service_critical', null);
$tpl->assign('service_unknown', null);
$tpl->assign('service_undetermined', null);
$tpl->assign('service_maintenance', null);
$tpl->assign('hostgroup_up', null);
$tpl->assign('hostgroup_down', null);
$tpl->assign('hostgroup_unreachable', null);
$tpl->assign('hostgroup_undetermined', null);
$tpl->assign('hostgroup_maintenance', null);
$tpl->assign('servicegroup_ok', null);
$tpl->assign('servicegroup_warning', null);
$tpl->assign('servicegroup_critical', null);
$tpl->assign('servicegroup_unknown', null);
$tpl->assign('servicegroup_undetermined', null);
$tpl->assign('servicegroup_maintenance', null);

$tpl->assign('period_choice', $period_choice);
// Period Selection form
$formPeriod = new HTML_QuickFormCustom('FormPeriod', 'post', '?p=' . $p);
$formPeriod->addElement('select', 'period', '', $periodList, ['id' => 'presetPeriod']);
$formPeriod->addElement('hidden', 'timeline', '1');
$formPeriod->addElement(
    'text',
    'StartDate',
    _('From'),
    ['id' => 'StartDate', 'size' => 10, 'class' => 'datepicker', 'onClick' => 'javascript: togglePeriodType();']
);
$formPeriod->addElement(
    'text',
    'EndDate',
    _('to'),
    ['id' => 'EndDate', 'size' => 10, 'class' => 'datepicker', 'onClick' => 'javascript: togglePeriodType();']
);
// adding hidden fields to get the result of datepicker in an unlocalized format
$formPeriod->addElement(
    'hidden',
    'alternativeDateStartDate',
    '',
    ['size' => 10, 'class' => 'alternativeDate']
);
$formPeriod->addElement(
    'hidden',
    'alternativeDateEndDate',
    'test',
    ['size' => 10, 'class' => 'alternativeDate']
);
$formPeriod->addElement('submit', 'button', _('Apply period'), ['class' => 'btc bt_success ml-2']);
$formPeriod->setDefaults(
    [
        'period' => $period,
        'StartDate' => $get_date_start,
        'EndDate' => $get_date_end,
    ]
);

?>
<style>
/* === Shared reporting dark-theme-aware styles === */
.rpt-period-group { display:inline; }
.rpt-period-group.rpt-disabled { opacity:0.35; pointer-events:none; }
.rpt-period-group.rpt-disabled select,
.rpt-period-group.rpt-disabled input[type="text"] { background: var(--list-lvl-1-background-color, #f0f0f0); }
/* Keep radio buttons always clickable even in disabled group */
.rpt-period-group.rpt-disabled input[type="radio"],
.rpt-period-group.rpt-disabled label,
.rpt-period-group.rpt-disabled .md-radio-modified { pointer-events:auto; opacity:1; }

/* Report title */
.rpt-title {
    margin: 0 0 8px 0; padding: 0;
    font-size: 18px; font-weight: 600;
    color: var(--body-color, #2d3436);
}
/* Title bar with nav pills */
.rpt-title-bar {
    display: flex; align-items: center;
    justify-content: space-between;
    margin: 0 0 8px 0; max-width: 1400px;
}
.rpt-title-bar .rpt-title { margin: 0; }
.rpt-nav-pills { display: flex; gap: 8px; }
.rpt-nav-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: transparent;
    border: 1px solid #2e7dba;
    border-radius: 4px; padding: 4px 12px;
    font-size: 12px; color: #2e7dba;
    text-decoration: none; transition: all 0.2s;
}
.rpt-nav-pill:hover {
    background: #2e7dba;
    color: #fff;
}
.rpt-nav-pill img { vertical-align: middle; filter: var(--icons-filter, none); }
.rpt-nav-pill:hover img { filter: none; }
/* Date row */
.rpt-date-row td {
    padding: 6px 10px;
    background: var(--list-lvl-1-background-color, #f5f5f0);
    color: var(--body-color, #333);
    font-weight: 600; font-size: 12px;
    border-bottom: 1px solid var(--list-table-border-color, #ddd);
}
/* Dot indicator */
.rpt-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; vertical-align:middle; }
/* Summary header/footer (donut table) */
.reporting-summary .rpt-summary-header {
    background: var(--list-lvl-1-background-color, #f5f5f0);
}
.reporting-summary .rpt-summary-header td {
    color: var(--body-color, #333);
    font-weight: 600;
    border-bottom: 1px solid var(--list-table-border-color, #ddd);
}
.reporting-summary .rpt-summary-footer {
    background: var(--list-lvl-1-background-color, #f5f5f0);
    border-top: 2px solid var(--list-table-border-color, #ddd);
}
.reporting-summary .rpt-summary-footer td {
    font-weight: 600;
    color: var(--body-color, #333);
}
/* Alert badges — semi-transparent for dark theme compatibility */
.rpt-alert-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.rpt-alert-up, .rpt-alert-ok, .rpt-alert-none {
    background: rgba(46, 125, 50, 0.15);
    color: #4caf50;
}
.rpt-alert-down, .rpt-alert-crit {
    background: rgba(198, 40, 40, 0.15);
    color: #ef5350;
}
.rpt-alert-unreach, .rpt-alert-unk {
    background: rgba(106, 27, 154, 0.15);
    color: #ab47bc;
}
.rpt-alert-warn {
    background: rgba(230, 81, 0, 0.15);
    color: #ff9800;
}
.rpt-alert-total {
    background: rgba(21, 101, 192, 0.15);
    color: #42a5f5;
}
/* Tooltip overlay for column headers */
.rpt-tooltip-wrap {
    position: relative;
    cursor: help;
    border-bottom: 1px dotted var(--body-color, #999);
}
.rpt-tooltip {
    visibility: hidden; opacity: 0;
    position: absolute; bottom: calc(100% + 8px); left: 50%;
    transform: translateX(-50%); width: 220px;
    padding: 8px 10px; background: #24292f; color: #fff;
    font-size: 11px; font-weight: 400; line-height: 1.4;
    border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    text-align: left; white-space: normal; z-index: 100;
    transition: opacity 0.2s ease, visibility 0.2s ease;
    pointer-events: none;
}
.rpt-tooltip::after {
    content: ''; position: absolute; top: 100%; left: 50%;
    transform: translateX(-50%); border: 6px solid transparent;
    border-top-color: #24292f;
}
.rpt-tooltip-wrap:hover .rpt-tooltip { visibility: visible; opacity: 1; }
/* Modern services/hostgroup table */
.reporting-modern .rpt-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; vertical-align:middle; }
.reporting-modern .rpt-val { white-space: nowrap; }
.reporting-modern .rpt-val .rpt-mp { font-weight: 600; color: var(--body-color, #24292f); }
.reporting-modern .rpt-val .rpt-sep { color: var(--body-color, #bbb); opacity: 0.5; margin: 0 2px; }
.reporting-modern .rpt-val .rpt-tp { font-size: 11px; color: var(--body-color, #999); opacity: 0.6; }
.reporting-modern .rpt-alerts { white-space: nowrap; }
.reporting-modern tr.list_lvl_1 { background: var(--list-lvl-1-background-color, #f5f5f0) !important; }
.reporting-modern tr.list_lvl_1 td { color: var(--body-color, #333) !important; font-weight: 600; }
.reporting-modern .rpt-avg { background: var(--list-lvl-1-background-color, #f5f5f0) !important; border-top: 2px solid var(--list-table-border-color, #ddd); }
.reporting-modern .rpt-avg td { font-weight: 600; color: var(--body-color, #333); }
/* Subtle colored column backgrounds — transparent for dark theme compatibility */
.reporting-modern .rpt-bg-up { background: rgba(<?php echo $colorsRgb['up']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-down { background: rgba(<?php echo $colorsRgb['down']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-unreachable { background: rgba(<?php echo $colorsRgb['unreachable']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-ok { background: rgba(<?php echo $colorsRgb['ok']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-warning { background: rgba(<?php echo $colorsRgb['warning']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-critical { background: rgba(<?php echo $colorsRgb['critical']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-unknown { background: rgba(<?php echo $colorsRgb['unknown']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-maintenance { background: rgba(<?php echo $colorsRgb['maintenance']; ?>, 0.05) !important; }
.reporting-modern .rpt-bg-undetermined { background: rgba(<?php echo $colorsRgb['undetermined']; ?>, 0.05) !important; }
/* Status bar track */
.status-bar { background: var(--list-lvl-1-background-color, #f0f0f0) !important; }
</style>
<script type='text/javascript'>
function togglePeriodType()
{
    document.getElementById("presetPeriod").selectedIndex = 0;
}
function updatePeriodToggle() {
    var isPreset = document.getElementById('preset').checked;
    var presetGroup = document.getElementById('rpt-preset-group');
    var customGroup = document.getElementById('rpt-custom-group');
    if (presetGroup && customGroup) {
        if (isPreset) {
            presetGroup.className = 'rpt-period-group';
            customGroup.className = 'rpt-period-group rpt-disabled';
        } else {
            presetGroup.className = 'rpt-period-group rpt-disabled';
            customGroup.className = 'rpt-period-group';
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodToggle();
    var presetRadio = document.getElementById('preset');
    var customRadio = document.getElementById('custom');
    if (presetRadio) {
        presetRadio.addEventListener('change', updatePeriodToggle);
    }
    if (customRadio) {
        customRadio.addEventListener('change', updatePeriodToggle);
    }
    // Also toggle when clicking on the period select
    var periodSelect = document.getElementById('presetPeriod');
    if (periodSelect) {
        periodSelect.addEventListener('click', function() {
            presetRadio.checked = true;
            updatePeriodToggle();
        });
    }
    // Toggle when clicking on date fields
    var startDate = document.getElementById('StartDate');
    var endDate = document.getElementById('EndDate');
    if (startDate) {
        startDate.addEventListener('click', function() {
            customRadio.checked = true;
            updatePeriodToggle();
        });
    }
    if (endDate) {
        endDate.addEventListener('click', function() {
            customRadio.checked = true;
            updatePeriodToggle();
        });
    }
});
</script>
