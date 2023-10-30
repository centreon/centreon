<?php
/*
 * Copyright 2015-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once './modules/centreon-open-tickets/centreon-open-tickets.conf.php';

$db = new CentreonDBManager();
$request = new CentreonOpenTicketsRequest();
$rule = new Centreon_OpenTickets_Rule($db);

$o = $request->getParam('o');
if (!$o) {
    $o = $request->getParam('o1');
}
if (!$o) {
    $o = $request->getParam('o2');
}
$ruleId = $request->getParam('rule_id');
$select = $request->getParam('select');
$duplicateNb = $request->getParam('duplicateNb');
$p = $request->getParam('p');
$num = $request->getParam('num');
$limit = $request->getParam('limit');
$search = $request->getParam('searchRule');

try {
    switch ($o) {
        case 'a':
            require_once 'form.php';
            break;
        case 'd':
            $rule->delete($select);
            require_once 'list.php';
            break;
        case 'c':
            require_once 'form.php';
            break;
        case 'l':
            require_once 'list.php';
            break;
        case 'dp':
            $rule->duplicate($select, $duplicateNb);
            require_once 'list.php';
            break;
        case 'e':
            $rule->enable($select);
            require_once 'list.php';
            break;
        case 'ds':
            $rule->disable($select);
            require_once 'list.php';
            break;
        default:
            require_once 'list.php';
            break;
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
}
