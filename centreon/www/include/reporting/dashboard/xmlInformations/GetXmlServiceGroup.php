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
            $res = explode('_', $host_service_id);
            $host_ids[$res[0]] = 1;
            $service_ids[$res[1]] = 1;
        }

        $request =  'SELECT '
            . 'date_start, date_end, OKnbEvent, CRITICALnbEvent, WARNINGnbEvent, UNKNOWNnbEvent, '
            . 'avg( `OKTimeScheduled` ) as "OKTimeScheduled", '
            . 'avg( `WARNINGTimeScheduled` ) as "WARNINGTimeScheduled", '
            . 'avg( `UNKNOWNTimeScheduled` ) as "UNKNOWNTimeScheduled", '
            . 'avg( `CRITICALTimeScheduled` ) as "CRITICALTimeScheduled", '
            . 'avg( `UNDETERMINEDTimeScheduled` ) as "UNDETERMINEDTimeScheduled" '
            . 'FROM `log_archive_service` WHERE `host_id` IN ('
                . implode(',', array_keys($host_ids)) . ') AND `service_id` IN ('
                . implode(',', array_keys($service_ids)) . ') group by date_end, date_start order by date_start desc';
        $res = $pearDBO->query($request);
        while ($row = $res->fetchRow()) {
            fillBuffer($statesTab, $row, $color);
        }
        $DBRESULT->closeCursor();
    }
} else {
    $buffer->writeElement('error', 'Bad id format');
}

$buffer->endElement();
header('Content-Type: text/xml');
$buffer->output();
