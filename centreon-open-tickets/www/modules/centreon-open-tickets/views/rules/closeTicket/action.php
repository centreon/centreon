<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../centreon-open-tickets.conf.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/providers/register.php';
require_once $centreon_path . 'www/class/centreonXMLBGRequest.class.php';
$centreon_open_tickets_path = $centreon_path . 'www/modules/centreon-open-tickets/';
require_once $centreon_open_tickets_path . 'providers/Abstract/AbstractProvider.class.php';

session_start();

/**
 * Function that will check the selection payload.
 *
 * @param string $selection
 *
 * @return bool
 */
function isSelectionValid(string $selection): bool
{
    preg_match('/^(\d+;?\d+,?)+$/', $selection, $matches);

    return ! empty($matches);
}

if (isset($_SESSION['centreon'])) {
    /** @var Centreon $centreon */
    $centreon = $_SESSION['centreon'];
} else {
    $resultat = ['code' => 1, 'msg' => 'Invalid session'];
    header('Content-type: text/plain');
    echo json_encode($resultat);

    exit;
}

$centreon_bg = new CentreonXMLBGRequest($dependencyInjector, session_id(), 1, 1, 0, 1);
$db = $dependencyInjector['configuration_db'];
$rule = new Centreon_OpenTickets_Rule($db);

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$payload = json_decode($request->getContent(), true);

$data = $payload['data'] ?? null;

if ($data === null) {
    $resultat = ['code' => 1, 'msg' => 'POST data key missing'];
    header('Content-type: text/plain');
    echo json_encode($resultat);

    exit;
}

if (
    ! isset($data['rule_id'])
    || ! is_int((int) $data['rule_id'])
) {
    $resultat = ['code' => 1, 'msg' => 'Rule ID should be provided as an integer'];
    header('Content-type: text/plain');
    echo json_encode($resultat);

    exit;
}

$ruleInformation = $rule->getAliasAndProviderId($data['rule_id']);

if (
    ! isset($data['selection'])
    || ! isSelectionValid($data['selection'])
) {
    $resultat = ['code' => 1, 'msg' => 'Resource selection not provided or not well formatted'];
    header('Content-type: text/plain');
    echo json_encode($resultat);

    exit;
}

// re-create payload sent from the widget directly from this file
$get_information = [
    'action' => 'close-ticket',
    'rule_id' => $data['rule_id'],
    'provider_id' => $ruleInformation['provider_id'],
    'form' => [
        'rule_id' => $data['rule_id'],
        'provider_id' => $ruleInformation['provider_id'],
        'selection' => $data['selection'],
    ],
];

require_once __DIR__ . '/../ajax/actions/closeTicket.php';

header('Content-type: text/plain');
echo json_encode($resultat);
