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

declare(strict_types=1);

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper = AjaxListingHelper::boot();
$helper->requireCentreon();
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// Meta service hosts (host_register = '2') are handled by their own dependency page.
$conditions = ["dspr.host_host_id NOT IN (SELECT host_id FROM host WHERE host_register = '2')"];
$parameters = [];
$aclJoin    = '';

if ($search !== '') {
    $conditions[] = '(dep.dep_name LIKE :search OR dep.dep_description LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

// ACL filtering: a non-admin only sees dependencies whose parent services it is granted.
if (! $helper->isAdmin()) {
    $acl         = $helper->getAcl();
    $aclGroupIds = $acl !== null
        ? array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))))
        : [];

    if ($aclGroupIds === []) {
        $helper->jsonResponse([], 0, $num, $limit);
    }

    $aclDbName       = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
    $aclPlaceholders = [];
    foreach ($aclGroupIds as $index => $groupId) {
        $placeholder       = 'acl_gid' . $index;
        $aclPlaceholders[] = ':' . $placeholder;
        $parameters[]      = QueryParameter::int($placeholder, $groupId);
    }

    $aclJoin = "INNER JOIN `{$aclDbName}`.centreon_acl acl "
        . 'ON acl.host_id = dspr.host_host_id '
        . 'AND acl.service_id = dspr.service_service_id '
        . 'AND acl.group_id IN (' . implode(', ', $aclPlaceholders) . ')';
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

$countQuery = <<<SQL
    SELECT COUNT(DISTINCT dep.dep_id) AS total
    FROM dependency dep
    INNER JOIN dependency_serviceParent_relation dspr ON dspr.dependency_dep_id = dep.dep_id
    {$aclJoin}
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT DISTINCT dep.dep_id, dep.dep_name, dep.dep_description
    FROM dependency dep
    INNER JOIN dependency_serviceParent_relation dspr ON dspr.dependency_dep_id = dep.dep_id
    {$aclJoin}
    {$whereClause}
    ORDER BY dep.dep_name, dep.dep_description
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dependencies = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($dependencies as $dependency) {
        $rows[] = [
            'id'   => (int) $dependency['dep_id'],
            'name' => $dependency['dep_name'],
            'desc' => $dependency['dep_description'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch service dependencies',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
