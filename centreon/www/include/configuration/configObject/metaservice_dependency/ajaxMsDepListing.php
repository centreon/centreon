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
// The endpoint has its own URL, so main.get.php's topology check does not
// cover it: gate it on read access to the page itself.
$helper->requireReadAccess(60411);
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

$conditions = [];
$parameters = [];
$aclClause  = '';

if ($search !== '') {
    $conditions[] = '(dep.dep_name LIKE :search OR dep.dep_description LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

// ACL filtering: a non-admin only sees dependencies built on the meta services it is granted.
if (! $helper->isAdmin()) {
    $acl    = $helper->getAcl();
    $aclIds = $acl !== null
        ? array_values(array_filter(array_map('intval', array_keys($acl->getMetaServices()))))
        : [];

    if ($aclIds === []) {
        $helper->jsonResponse([], 0, $num, $limit);
    }

    $aclPlaceholders = [];
    foreach ($aclIds as $index => $aclId) {
        $placeholder       = 'acl_ms' . $index;
        $aclPlaceholders[] = ':' . $placeholder;
        $parameters[]      = QueryParameter::int($placeholder, $aclId);
    }

    $aclClause = ' AND rel.meta_service_meta_id IN (' . implode(', ', $aclPlaceholders) . ')';
}

// A dependency shows up as soon as it references a granted meta service on either side.
$conditions[] = <<<SQL
    (
        EXISTS (
            SELECT 1 FROM dependency_metaserviceParent_relation rel
            WHERE rel.dependency_dep_id = dep.dep_id{$aclClause}
        )
        OR EXISTS (
            SELECT 1 FROM dependency_metaserviceChild_relation rel
            WHERE rel.dependency_dep_id = dep.dep_id{$aclClause}
        )
    )
    SQL;

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM dependency dep
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT dep.dep_id, dep.dep_name, dep.dep_description
    FROM dependency dep
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
        'AJAX listing: failed to fetch meta service dependencies',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
