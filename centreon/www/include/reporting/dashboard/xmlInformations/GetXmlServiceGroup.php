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

$stateType = 'service';
require_once realpath(__DIR__ . '/initXmlFeed.php');

$color = array_filter($_GET['color'] ?? [], function ($oneColor) {
    return filter_var($oneColor, FILTER_VALIDATE_REGEXP, [
        'options' => [
            'regexp' => '/^#[[:xdigit:]]{6}$/',
        ],
    ]);
});
if (empty($color) || count($_GET['color']) !== count($color)) {
    $buffer->writeElement('error', 'Bad color format');
    $buffer->endElement();
    header('Content-Type: text/xml');
    $buffer->output();

    exit;
}

if (($id = filter_var($_GET['id'] ?? false, FILTER_VALIDATE_INT)) !== false) {
    $services = getServiceGroupActivateServices($id);
    if (count($services) > 0) {
        $host_ids = [];
        $service_ids = [];
        foreach ($services as $host_service_id => $host_service_name) {
            $parts = explode('_', $host_service_id);
            $host_ids[(int) $parts[0]] = 1;
            $service_ids[(int) $parts[1]] = 1;
        }

        $hostIdList = array_map('intval', array_keys($host_ids));
        $serviceIdList = array_map('intval', array_keys($service_ids));
        $hostPlaceholders = implode(',', array_fill(0, count($hostIdList), '?'));
        $servicePlaceholders = implode(',', array_fill(0, count($serviceIdList), '?'));

        $request = 'SELECT '
            . 'date_start, date_end, OKnbEvent, CRITICALnbEvent, WARNINGnbEvent, UNKNOWNnbEvent, '
            . 'avg( `OKTimeScheduled` ) as "OKTimeScheduled", '
            . 'avg( `WARNINGTimeScheduled` ) as "WARNINGTimeScheduled", '
            . 'avg( `UNKNOWNTimeScheduled` ) as "UNKNOWNTimeScheduled", '
            . 'avg( `CRITICALTimeScheduled` ) as "CRITICALTimeScheduled", '
            . 'avg( `UNDETERMINEDTimeScheduled` ) as "UNDETERMINEDTimeScheduled" '
            . 'FROM `log_archive_service` WHERE `host_id` IN (' . $hostPlaceholders . ') '
            . 'AND `service_id` IN (' . $servicePlaceholders . ') '
            . 'GROUP BY date_end, date_start ORDER BY date_start desc';
        $stmt = $pearDBO->prepare($request);
        $paramIndex = 1;
        foreach ($hostIdList as $hId) {
            $stmt->bindValue($paramIndex++, $hId, PDO::PARAM_INT);
        }
        foreach ($serviceIdList as $sId) {
            $stmt->bindValue($paramIndex++, $sId, PDO::PARAM_INT);
        }
        $stmt->execute();
        while ($row = $stmt->fetchRow()) {
            fillBuffer($statesTab, $row, $color);
        }
        $stmt->closeCursor();
    }
} else {
    $buffer->writeElement('error', 'Bad id format');
}

$buffer->endElement();
header('Content-Type: text/xml');
$buffer->output();
