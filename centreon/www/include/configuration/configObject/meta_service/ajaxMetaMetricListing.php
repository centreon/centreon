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

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$metaId = filter_var($_GET['meta_id'] ?? null, FILTER_VALIDATE_INT);
if (! $metaId) {
    AjaxListingHelper::jsonError('Missing meta_id', 400);
}

$calcType = ['AVE' => 'Average', 'SOM' => 'Sum', 'MIN' => 'Min', 'MAX' => 'Max'];

// Get meta service info
$metaStmt = $pearDB->prepare('SELECT meta_name, calcul_type FROM meta_service WHERE meta_id = :meta_id');
$metaStmt->bindValue(':meta_id', $metaId, PDO::PARAM_INT);
$metaStmt->execute();
$metaInfo = $metaStmt->fetch(PDO::FETCH_ASSOC);
if (! $metaInfo) {
    AjaxListingHelper::jsonError('Meta service not found', 404);
}

// ACL
$aclFrom = '';
$aclCond = '';
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $aclDbName = $acl->getNameDBAcl();
    $aclFrom = ", `{$aclDbName}`.centreon_acl acl ";
    $aclCond = ' AND acl.host_id = msr.host_id AND acl.group_id IN (' . $acl->getAccessGroupsString() . ') ';
}

// Get metric relations
$statement = $pearDB->prepare(
    "SELECT DISTINCT msr.msr_id, msr.metric_id, msr.activate
    FROM meta_service_relation msr {$aclFrom}
    WHERE msr.meta_id = :meta_id {$aclCond}
    ORDER BY msr.host_id"
);
$statement->bindValue(':meta_id', $metaId, PDO::PARAM_INT);
$statement->execute();

$relations = [];
$metricIds = [];
while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $relations[$row['metric_id']][] = [
        'msr_id' => (int) $row['msr_id'],
        'activate' => (int) $row['activate'],
    ];
    $metricIds[] = (int) $row['metric_id'];
}

$rows = [];
if (! empty($metricIds)) {
    // Query centstorage for metric details
    $pearDBO = new CentreonDB('centstorage');
    $inList = implode(',', $metricIds);
    $dbResult = $pearDBO->query(
        "SELECT m.metric_id, m.metric_name, m.unit_name, i.host_name, i.service_description
        FROM metrics m, index_data i
        WHERE m.metric_id IN ({$inList})
        AND m.index_id = i.id
        ORDER BY i.host_name, i.service_description, m.metric_name"
    );

    while ($metric = $dbResult->fetch(PDO::FETCH_ASSOC)) {
        $svcDesc = str_replace(['#S#', '#BS#'], ['/', '\\'], $metric['service_description']);
        foreach ($relations[$metric['metric_id']] as $rel) {
            $rows[] = [
                'id'       => $rel['msr_id'],
                'host'     => $metric['host_name'],
                'service'  => $svcDesc,
                'metric'   => $metric['metric_name'] . ' (' . ($metric['unit_name'] ?? '') . ')',
                'activate' => $rel['activate'],
            ];
        }
    }
}

// Return meta info alongside rows
$centreonToken = createCSRFToken();
echo json_encode([
    'rows'           => $rows,
    'total'          => count($rows),
    'num'            => 0,
    'limit'          => count($rows),
    'centreon_token' => $centreonToken,
    'meta_name'      => $metaInfo['meta_name'],
    'meta_calc_type' => $calcType[$metaInfo['calcul_type']] ?? $metaInfo['calcul_type'],
]);

exit;
