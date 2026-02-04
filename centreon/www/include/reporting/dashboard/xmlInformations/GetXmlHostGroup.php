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

$stateType = 'host';
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
    $hosts_id = $centreon->user->access->getHostHostGroupAclConf($id, 'broker');
    if (count($hosts_id) > 0) {
        $rq = 'SELECT `date_start`, `date_end`, sum(`UPnbEvent`) as UPnbEvent, sum(`DOWNnbEvent`) as DOWNnbEvent, '
            . 'sum(`UNREACHABLEnbEvent`) as UNREACHABLEnbEvent, '
            . 'avg( `UPTimeScheduled` ) as "UPTimeScheduled", '
            . 'avg( `DOWNTimeScheduled` ) as "DOWNTimeScheduled", '
            . 'avg( `UNREACHABLETimeScheduled` ) as "UNREACHABLETimeScheduled", '
            . 'avg( `UNDETERMINEDTimeScheduled` ) as "UNDETERMINEDTimeScheduled" '
            . 'FROM `log_archive_host` WHERE `host_id` IN ('
            . implode(',', array_keys($hosts_id)) . ') GROUP BY date_end, date_start ORDER BY date_start desc';
        $DBRESULT = $pearDBO->query($rq);
        while ($row = $DBRESULT->fetchRow()) {
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
