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

$conditions = [];
$parameters = [];

if ($search !== '') {
    $conditions[] = '(cg.cg_name LIKE :search OR cg.cg_alias LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

// The configuration page lists local contact groups only; LDAP-imported ones are
// not editable here, and the legacy page scoped them the same way.
$conditions[] = "cg.cg_type = 'local'";

// ACL filtering: a non-admin only sees the contact groups granted by its access
// groups, and the member count is scoped to the contacts they may see.
$countAclClause      = '';
$countAclParameters  = [];

if (! $helper->isAdmin()) {
    $acl   = $helper->getAcl();
    $cgAcl = $acl->getContactGroupAclConf(['fields' => ['cg_id'], 'keys' => ['cg_id']]);

    if ($cgAcl === []) {
        $helper->jsonResponse([], 0, $num, $limit);
    }

    $placeholders = [];
    foreach (array_keys($cgAcl) as $index => $cgId) {
        $placeholder    = 'acl_cg' . $index;
        $placeholders[] = ':' . $placeholder;
        $parameters[]   = QueryParameter::int($placeholder, (int) $cgId);
    }
    $conditions[] = 'cg.cg_id IN (' . implode(', ', $placeholders) . ')';

    // The visible contacts are resolved in SQL rather than materialized in PHP:
    // binding one parameter per contact grows with the ACL scope, and a wide
    // scope would send thousands of them on every listing request. The subquery
    // mirrors CentreonACL::getContactAclConf() for a non-admin user, so only the
    // access group ids are bound.
    $aclGroupIds = array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))));

    if ($aclGroupIds === []) {
        // No access group: every group counts zero rather than its full membership.
        $countAclClause = ' AND 1 = 0';
    } else {
        // Two sets of placeholders for the same ids: a named placeholder cannot
        // be reused across both branches of the UNION.
        $directPlaceholders = [];
        $throughGroupPlaceholders = [];
        foreach ($aclGroupIds as $index => $aclGroupId) {
            $directPlaceholders[] = ':acl_ga' . $index;
            $throughGroupPlaceholders[] = ':acl_gb' . $index;
            $countAclParameters[] = QueryParameter::int('acl_ga' . $index, $aclGroupId);
            $countAclParameters[] = QueryParameter::int('acl_gb' . $index, $aclGroupId);
        }
        $directList = implode(', ', $directPlaceholders);
        $throughGroupList = implode(', ', $throughGroupPlaceholders);

        $visibleContactsQuery = <<<SQL
            SELECT agcr.contact_contact_id
            FROM acl_group_contacts_relations agcr
            INNER JOIN contact c ON c.contact_id = agcr.contact_contact_id
            WHERE c.contact_register = '1' AND agcr.acl_group_id IN ({$directList})
            UNION
            SELECT aclCcr.contact_contact_id
            FROM acl_group_contactgroups_relations agccgr
            INNER JOIN contactgroup_contact_relation aclCcr ON aclCcr.contactgroup_cg_id = agccgr.cg_cg_id
            INNER JOIN contact aclC ON aclC.contact_id = aclCcr.contact_contact_id
            WHERE aclC.contact_register = '1' AND agccgr.acl_group_id IN ({$throughGroupList})
            SQL;

        $countAclClause = ' AND ccr.contact_contact_id IN (' . $visibleContactsQuery . ')';
    }
}

$whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM contactgroup cg
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT
        cg.cg_id,
        cg.cg_name,
        cg.cg_alias,
        cg.cg_activate,
        (
            SELECT COUNT(DISTINCT ccr.contact_contact_id)
            FROM contactgroup_contact_relation ccr
            WHERE ccr.contactgroup_cg_id = cg.cg_id{$countAclClause}
        ) AS contact_count
    FROM contactgroup cg
    {$whereClause}
    ORDER BY cg.cg_name
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $contactGroups = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            ...$countAclParameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($contactGroups as $contactGroup) {
        $rows[] = [
            'id'            => (int) $contactGroup['cg_id'],
            'name'          => $contactGroup['cg_name'],
            'alias'         => $contactGroup['cg_alias'],
            'activate'      => (int) $contactGroup['cg_activate'],
            'contact_count' => (int) $contactGroup['contact_count'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch contact groups',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
