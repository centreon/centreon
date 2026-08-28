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

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$helper->requireReadAccess(60301);
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search       = $params['search'];
$num          = $params['num'];
$limit        = $params['limit'];
$contactGroup = 0;
if (isset($_GET['contactGroup']) && $_GET['contactGroup'] !== '') {
    $contactGroup = filter_var($_GET['contactGroup'], FILTER_VALIDATE_INT);
    // Falling back to "no filter" would keep the chip on screen while the whole
    // platform comes back in the rows.
    if ($contactGroup === false || $contactGroup <= 0) {
        AjaxListingHelper::jsonError('Invalid contact group', 400);
    }
}

// Only registered contacts belong to this listing; contact templates have their own page.
$conditions = ["c.contact_register = '1'"];
$parameters = [];

if ($search !== '') {
    $conditions[] = '(c.contact_name LIKE :search OR c.contact_alias LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

if ($contactGroup > 0) {
    $conditions[] = 'c.contact_id IN ('
        . 'SELECT contact_contact_id FROM contactgroup_contact_relation WHERE contactgroup_cg_id = :cg_id'
        . ')';
    $parameters[] = QueryParameter::int('cg_id', $contactGroup);
}

// ACL filtering: a non-admin only sees the contacts granted by its access groups.
// Resolved in SQL rather than materialized in PHP: binding one parameter per
// visible contact grows with the ACL scope, and a wide one would send thousands
// of them on every refresh tick. The subquery mirrors the non-admin branch of
// CentreonACL::getContactAclConf(), so only the access group ids are bound.
if (! $helper->isAdmin()) {
    // Resolving the scope hits the database: without this the failure escapes
    // as a fatal, after the JSON header has already been sent.
    try {
        $aclGroupIds = array_values(
            array_filter(array_map('intval', array_keys($helper->getAcl()->getAccessGroups())))
        );
    } catch (Throwable $exception) {
        Logger::create(LogChannelEnum::WEB)->error(
            'AJAX listing: failed to resolve the contact ACL scope',
            ['exception' => $exception]
        );
        AjaxListingHelper::jsonError('Internal error', 500);
    }

    if ($aclGroupIds === []) {
        $helper->jsonResponse([], 0, $num, $limit);
    }

    $aclGroupPlaceholders = [];
    foreach ($aclGroupIds as $index => $aclGroupId) {
        $aclGroupPlaceholders[] = ':acl_g' . $index;
        $parameters[]           = QueryParameter::int('acl_g' . $index, $aclGroupId);
    }
    $aclGroupList = implode(', ', $aclGroupPlaceholders);

    $conditions[] = <<<SQL
        c.contact_id IN (
            SELECT aclAgcr.contact_contact_id
            FROM acl_group_contacts_relations aclAgcr
            INNER JOIN contact aclDirect ON aclDirect.contact_id = aclAgcr.contact_contact_id
            WHERE aclDirect.contact_register = '1'
                AND aclAgcr.acl_group_id IN ({$aclGroupList})
            UNION
            SELECT aclCcr.contact_contact_id
            FROM acl_group_contactgroups_relations aclAgccgr
            INNER JOIN contactgroup_contact_relation aclCcr
                ON aclCcr.contactgroup_cg_id = aclAgccgr.cg_cg_id
            INNER JOIN contact aclThroughGroup ON aclThroughGroup.contact_id = aclCcr.contact_contact_id
            WHERE aclThroughGroup.contact_register = '1'
                AND aclAgccgr.acl_group_id IN ({$aclGroupList})
        )
        SQL;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

$timeperiodQuery = <<<'SQL'
    SELECT tp_id, tp_name FROM timeperiod
    SQL;

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM contact c
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT
        c.contact_id,
        c.contact_name,
        c.contact_alias,
        c.contact_email,
        c.timeperiod_tp_id,
        c.timeperiod_tp_id2,
        c.contact_host_notification_options,
        c.contact_service_notification_options,
        c.contact_lang,
        c.contact_oreon,
        c.contact_admin,
        c.contact_activate,
        c.contact_register,
        c.contact_auth_type,
        c.contact_ldap_required_sync,
        c.blocking_time
    FROM contact c
    {$whereClause}
    ORDER BY c.contact_name
    LIMIT :offset, :limit
    SQL;

try {
    $timeperiods = [];
    foreach ($pearDB->fetchAllAssociative($timeperiodQuery) as $timeperiod) {
        $timeperiods[(string) $timeperiod['tp_id']] = $timeperiod['tp_name'];
    }

    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $contacts = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $currentUserId = (int) $centreon->user->get_id();
    $isAdmin       = (bool) $centreon->user->admin;

    $rows = [];
    foreach ($contacts as $contact) {
        $hostTimeperiod    = $timeperiods[(string) ($contact['timeperiod_tp_id'] ?? '')] ?? '';
        $serviceTimeperiod = $timeperiods[(string) ($contact['timeperiod_tp_id2'] ?? '')] ?? '';
        $hostNotifOptions  = $contact['contact_host_notification_options'] ?? '';
        $svcNotifOptions   = $contact['contact_service_notification_options'] ?? '';

        $rows[] = [
            'id'              => (int) $contact['contact_id'],
            'name'            => $contact['contact_name'],
            'alias'           => $contact['contact_alias'],
            'email'           => $contact['contact_email'],
            'host_notif'      => $hostTimeperiod !== '' ? $hostTimeperiod . ' (' . $hostNotifOptions . ')' : '',
            'svc_notif'       => $serviceTimeperiod !== '' ? $serviceTimeperiod . ' (' . $svcNotifOptions . ')' : '',
            'lang'            => $contact['contact_lang'],
            'access'          => (int) $contact['contact_oreon'],
            'admin'           => (int) $contact['contact_admin'],
            'activate'        => (int) $contact['contact_activate'],
            'is_current_user' => ((int) $contact['contact_id'] === $currentUserId),
            'auth_type'       => $contact['contact_auth_type'],
            'ldap_sync'       => (int) $contact['contact_ldap_required_sync'],
            'blocked'         => $isAdmin && $contact['blocking_time'] !== null,
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch contacts',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
