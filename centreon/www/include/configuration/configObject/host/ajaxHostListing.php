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
require_once realpath(__DIR__ . '/../../..') . '/common/listing/HostIconResolver.php';

$helper = AjaxListingHelper::boot();
$helper->requireCentreon();
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search    = $params['search'];
$num       = $params['num'];
$limit     = $params['limit'];
$hostgroup = filter_var($_GET['hostgroup'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$poller    = filter_var($_GET['poller'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$template  = filter_var($_GET['template'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$status    = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;

try {
    // Build the JOIN / WHERE fragments and their bound parameters.
    $joins      = '';
    $conditions = "h.host_register = '1'";
    $parameters = [];

    // ACL filtering for non-admin users.
    if (! $helper->isAdmin()) {
        $acl         = $helper->getAcl();
        $aclGroupIds = $acl !== null
            ? array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))))
            : [];

        if ($aclGroupIds === []) {
            $helper->jsonResponse([], 0, $num, $limit);
        }

        $aclDbName = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
        $aclIn     = AjaxListingHelper::buildIntInClause($aclGroupIds, 'acl_gid');
        $parameters = [...$parameters, ...$aclIn['parameters']];
        $joins .= " INNER JOIN `{$aclDbName}`.centreon_acl acl"
            . ' ON acl.host_id = h.host_id AND acl.service_id IS NULL'
            . " AND acl.group_id IN ({$aclIn['clause']}) ";
    }

    if ($search !== '') {
        $conditions .= ' AND (h.host_name LIKE :search OR h.host_alias LIKE :search OR h.host_address LIKE :search)';
        $parameters[] = QueryParameter::string(
            'search',
            '%' . AjaxListingHelper::escapeLikeWildcards($search) . '%'
        );
    }

    if ($status === 2) {
        $conditions .= " AND h.host_activate = '1'";
    } elseif ($status === 1) {
        $conditions .= " AND h.host_activate = '0'";
    }

    if ($hostgroup > 0) {
        $joins .= ' INNER JOIN hostgroup_relation hr ON hr.host_host_id = h.host_id ';
        $conditions .= ' AND hr.hostgroup_hg_id = :hg_id';
        $parameters[] = QueryParameter::int('hg_id', $hostgroup);
    }

    if ($poller > 0) {
        $joins .= ' INNER JOIN ns_host_relation nshr ON nshr.host_host_id = h.host_id ';
        $conditions .= ' AND nshr.nagios_server_id = :poller_id';
        $parameters[] = QueryParameter::int('poller_id', $poller);
    }

    if ($template > 0) {
        $joins .= ' INNER JOIN host_template_relation htr_filter ON htr_filter.host_host_id = h.host_id ';
        $conditions .= ' AND htr_filter.host_tpl_id = :tpl_id';
        $parameters[] = QueryParameter::int('tpl_id', $template);
    }

    $countQuery = <<<SQL
        SELECT COUNT(DISTINCT h.host_id) AS total
        FROM host h {$joins}
        WHERE {$conditions}
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dataQuery = <<<SQL
        SELECT DISTINCT h.host_id, h.host_name, h.host_alias, h.host_address, h.host_activate
        FROM host h {$joins}
        WHERE {$conditions}
        ORDER BY h.host_name
        LIMIT :offset, :limit
        SQL;
    $hostResults = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $hostIds = array_map(static fn (array $host): int => (int) $host['host_id'], $hostResults);

    // Pollers, parent templates and icons of the listed hosts, resolved for the
    // whole page (one query each, or one per inheritance level for the icons)
    // rather than per row: with a page size of up to MAX_LIMIT and a 15s
    // auto-refresh, a per-row lookup means thousands of queries per refresh tick.
    // Scoped to the page's ids, never the whole relation table — that read grows
    // with the platform while the page never shows more than $limit rows.
    $hostPollers     = [];
    $templatesByHost = [];
    $icons           = [];
    if ($hostIds !== []) {
        $pollerIn = AjaxListingHelper::buildIntInClause($hostIds, 'poller_hid');
        $pollerRows = $pearDB->fetchAllAssociative(
            <<<SQL
                SELECT nshr.host_host_id, ns.name
                FROM ns_host_relation nshr
                INNER JOIN nagios_server ns ON ns.id = nshr.nagios_server_id
                WHERE nshr.host_host_id IN ({$pollerIn['clause']})
                SQL,
            QueryParameters::create($pollerIn['parameters'])
        );
        foreach ($pollerRows as $row) {
            $hostPollers[(int) $row['host_host_id']] = $row['name'];
        }

        $tplIn = AjaxListingHelper::buildIntInClause($hostIds, 'tpl_hid');
        $tplRows = $pearDB->fetchAllAssociative(
            <<<SQL
                SELECT htr.host_host_id, h.host_id, h.host_name
                FROM host_template_relation htr
                INNER JOIN host h ON htr.host_tpl_id = h.host_id
                WHERE htr.host_host_id IN ({$tplIn['clause']})
                ORDER BY htr.host_host_id, htr.`order`
                SQL,
            QueryParameters::create($tplIn['parameters'])
        );
        foreach ($tplRows as $row) {
            $templatesByHost[(int) $row['host_host_id']][] = [
                'id'   => (int) $row['host_id'],
                'name' => $row['host_name'],
            ];
        }

        $icons = HostIconResolver::resolve($pearDB, $hostIds);
    }

    // Real-time status (only for the listed hosts), read from the modern unified
    // `resources` table (type = 1 = host) rather than the legacy broker `hosts`
    // table: it is better indexed and is the source of truth under unified_sql.
    //
    // Kept on its own connection and its own try/catch: this is decorative
    // monitoring data on a *configuration* page, so a centstorage outage must
    // degrade the live badge, not 500 the whole listing whose config rows have
    // already been read successfully from pearDB.
    $rtmStatus = [];
    if ($hostIds !== []) {
        try {
            $rtIn = AjaxListingHelper::buildIntInClause($hostIds, 'rid');
            $rtRows = (new CentreonDB('centstorage'))->fetchAllAssociative(
                <<<SQL
                    SELECT id AS host_id, status AS state, acknowledged, in_downtime, last_check, output, last_status_change
                    FROM resources
                    WHERE type = 1 AND enabled = 1 AND id IN ({$rtIn['clause']})
                    SQL,
                QueryParameters::create($rtIn['parameters'])
            );
            foreach ($rtRows as $row) {
                $rtmStatus[(int) $row['host_id']] = [
                    'state'      => (int) $row['state'],
                    'ack'        => (int) $row['acknowledged'],
                    'dt'         => (int) $row['in_downtime'],
                    'last_check' => $row['last_check'] ? (int) $row['last_check'] : null,
                    'output'     => $row['output'],
                    'since'      => $row['last_status_change'] ? (int) $row['last_status_change'] : null,
                ];
            }
        } catch (Throwable $exception) {
            Logger::create(LogChannelEnum::WEB)->warning(
                'AJAX listing: real-time host status unavailable, listing rendered without it',
                ['exception' => $exception]
            );
            $rtmStatus = [];
        }
    }

    $rows = [];
    foreach ($hostResults as $host) {
        $hid = (int) $host['host_id'];

        // Monitoring status (0=UP, 1=DOWN, 2=UNREACHABLE, 4=PENDING)
        $monStatus = $rtmStatus[$hid] ?? null;

        $rows[] = [
            'id'         => $hid,
            'name'       => $host['host_name'],
            'alias'      => $host['host_alias'],
            'address'    => $host['host_address'],
            'poller'     => $hostPollers[$hid] ?? '',
            'templates'  => $templatesByHost[$hid] ?? [],
            'activate'   => (int) $host['host_activate'],
            'icon'       => $icons[$hid] ?? null,
            'mon_state'  => $monStatus ? $monStatus['state'] : null,
            'mon_ack'    => $monStatus ? $monStatus['ack'] : 0,
            'mon_dt'     => $monStatus ? $monStatus['dt'] : 0,
            'mon_last'   => $monStatus ? $monStatus['last_check'] : null,
            'mon_output' => $monStatus ? $monStatus['output'] : null,
            'mon_since'  => $monStatus ? $monStatus['since'] : null,
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch hosts',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
