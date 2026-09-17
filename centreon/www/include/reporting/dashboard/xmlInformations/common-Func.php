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

/*require_once realpath(dirname(__FILE__) . "/../../../../../config/centreon.config.php");
require_once _CENTREON_PATH_."www/class/centreonDB.class.php";

/* Translation
require_once(_CENTREON_PATH_ . "www/class/centreonSession.class.php");
require_once(_CENTREON_PATH_ . "www/class/centreon.class.php");
require_once(_CENTREON_PATH_ . "www/class/centreonLang.class.php");

/*CentreonSession::start();
$oreon = $_SESSION["centreon"];
$centreonLang = new CentreonLang(_CENTREON_PATH_, $oreon);
$centreonLang->bindLang();*/

// Create a XML node for each day stats (in $row) for a service, a servicegroup, an host or an hostgroup
function fillBuffer($statesTab, $row, $color)
{
    global $buffer;

    $statTab = [];
    $totalTime = 0;
    $sumTime = 0;
    foreach ($statesTab as $key => $value) {
        if (isset($row[$value . 'TimeScheduled'])) {
            $statTab[$value . '_T'] = $row[$value . 'TimeScheduled'];
            $totalTime += $row[$value . 'TimeScheduled'];
        } else {
            $statTab[$value . '_T'] = 0;
        }
        $statTab[$value . '_A'] = $row[$value . 'nbEvent'] ?? 0;
    }
    $date_start = $row['date_start'];
    $date_end = $row['date_end'];
    foreach ($statesTab as $key => $value) {
        $statTab[$value . '_MP'] = $totalTime ? round(($statTab[$value . '_T'] / ($totalTime) * 100), 2) : 0;
    }

    // Popup generation for each day
    $Day = _('Day');
    $Duration = _('Duration');
    $Alert = _('Alert');
    $detailPopup = '{table class=bulleDashtab}';
    $detailPopup .= '{tr}{td class=bulleDashleft colspan=3}' . $Day
        . ': {span class ="isTimestamp isDate"}';
    $detailPopup .= $date_start . '{/span} --  ' . $Duration . ': '
        . CentreonDuration::toString($totalTime) . '{/td}{td class=bulleDashleft }' . $Alert . '{/td}{/tr}';
    foreach ($statesTab as $key => $value) {
        $detailPopup .= '{tr}'
                        . '{td class=bulleDashleft style="background:' . $color[$value] . ';"  }' . _($value) . ':{/td}'
                        . '{td class=bulleDash}' . CentreonDuration::toString($statTab[$value . '_T']) . '{/td}'
                        . '{td class=bulleDash}' . $statTab[$value . '_MP'] . '%{/td}'
                        . '{td class=bulleDash}' . $statTab[$value . '_A'] . '{/td}';
        $detailPopup .= '{/tr}';
    }
    $detailPopup .= '{/table}';

    $t = $totalTime;
    $t = round(($t - ($t * 0.11574074074)), 2);

    foreach ($statesTab as $key => $value) {
        if ($statTab[$value . '_MP'] > 0) {
            $day = date('d', $date_start);
            $year = date('Y', $date_start);
            $month = date('m', $date_start);
            $start = mktime(0, 0, 0, $month, $day, $year);
            $start += ($statTab[$value . '_T'] / 100 * 2);
            $end = $start + ($statTab[$value . '_T'] / 100 * 96);
            $buffer->startElement('event');
            $buffer->writeAttribute('start', createDateTimelineFormat($start) . ' GMT');
            $buffer->writeAttribute('end', createDateTimelineFormat($end) . ' GMT');
            $buffer->writeAttribute('color', $color[$value]);
            $buffer->writeAttribute('isDuration', 'true');
            $buffer->writeAttribute('title', $statTab[$value . '_MP'] . '%');
            $buffer->text($detailPopup, false);
            $buffer->endElement();
        }
    }
}
