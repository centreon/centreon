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

$conditions         = [];
$parameters         = [];
$countAclClause     = '';
$countAclParameters = [];

if ($search !== '') {
    $conditions[] = '(hc.hc_name LIKE :search OR hc.hc_alias LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

// ACL filtering: a non-admin only sees the host categories granted by its ACL.
if (! $helper->isAdmin()) {
    $acl   = $helper->getAcl();
    $hcIds = $acl !== null
        ? array_values(array_filter(array_map('intval', explode(',', $acl->getHostCategoriesString('ID')))))
        : [];

    if ($hcIds === []) {
        $helper->jsonResponse([], 0, $num, $limit);
    }

    $placeholders = [];
    foreach ($hcIds as $index => $hcId) {
        $placeholder    = 'hc_id_' . $index;
        $placeholders[] = ':' . $placeholder;
        $parameters[]   = QueryParameter::int($placeholder, $hcId);
    }
    $conditions[] = 'hc.hc_id IN (' . implode(', ', $placeholders) . ')';

    // Scope the enabled/disabled host counts to the hosts granted by the user's ACL.
    $aclGroupIds = $acl !== null
        ? array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))))
        : [];

    if ($aclGroupIds !== []) {
        $aclDbName       = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
        $aclPlaceholders = [];
        foreach ($aclGroupIds as $index => $groupId) {
            $placeholder          = 'acl_gid' . $index;
            $aclPlaceholders[]    = ':' . $placeholder;
            $countAclParameters[] = QueryParameter::int($placeholder, $groupId);
        }
        $countAclClause = ' AND EXISTS ('
            . "SELECT 1 FROM `{$aclDbName}`.centreon_acl acl "
            . 'WHERE acl.host_id = h.host_id '
            . 'AND acl.group_id IN (' . implode(', ', $aclPlaceholders) . ')'
            . ')';
    }
}

$whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM hostcategories hc
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT
        hc.hc_id,
        hc.hc_name,
        hc.hc_alias,
        hc.hc_activate,
        hc.level,
        (
            SELECT COUNT(*)
            FROM hostcategories_relation hr
            INNER JOIN host h ON h.host_id = hr.host_host_id
            WHERE hr.hostcategories_hc_id = hc.hc_id AND h.host_activate = '1' AND h.host_register = '1'{$countAclClause}
        ) AS enabled_hosts,
        (
            SELECT COUNT(*)
            FROM hostcategories_relation hr
            INNER JOIN host h ON h.host_id = hr.host_host_id
            WHERE hr.hostcategories_hc_id = hc.hc_id AND h.host_activate = '0' AND h.host_register = '1'{$countAclClause}
        ) AS disabled_hosts
    FROM hostcategories hc
    {$whereClause}
    ORDER BY hc.hc_name
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $hostCategories = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            ...$countAclParameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($hostCategories as $hc) {
        $rows[] = [
            'id'             => (int) $hc['hc_id'],
            'name'           => $hc['hc_name'],
            'alias'          => $hc['hc_alias'],
            'enabled_hosts'  => (int) $hc['enabled_hosts'],
            'disabled_hosts' => (int) $hc['disabled_hosts'],
            'level'          => $hc['level'] ? (int) $hc['level'] : null,
            'activate'       => (int) $hc['hc_activate'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch host categories',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
