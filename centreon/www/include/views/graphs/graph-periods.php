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
    exit();
}

// Path to the configuration dir
$path = './include/views/graphs/';

// Include Pear Lib

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$chartId = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['chartId'] ?? null);

if (preg_match('/([0-9]+)_([0-9]+)/', $chartId, $matches)) {
    $hostId = (int) $matches[1];
    $serviceId = (int) $matches[2];
} else {
    throw new InvalidArgumentException('chartId must be a combination of integers');
}

// Get host and service name
$serviceName = '';

$query = 'SELECT h.name, s.description FROM hosts h, services s
    WHERE h.host_id = :hostId AND s.service_id = :serviceId AND h.host_id = s.host_id';

$stmt = $pearDBO->prepare($query);
$stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
$stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $serviceName = $row['name'] . ' - ' . $row['description'];
}

$periods = [
    [
        'short' => '1d',
        'long' => _('last day'),
    ],
    [
        'short' => '7d',
        'long' => _('last week'),
    ],
    [
        'short' => '31d',
        'long' => _('last month'),
    ],
    [
        'short' => '1y',
        'long' => _('last year'),
    ],
];

$tpl->assign('periods', $periods);
$tpl->assign('svc_id', $chartId);
$tpl->assign('srv_name', $serviceName);

$tpl->display('graph-periods.html');
