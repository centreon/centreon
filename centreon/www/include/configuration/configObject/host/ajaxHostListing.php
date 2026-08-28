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
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';
require_once realpath(__DIR__ . '/../../..') . '/common/listing/HostIconResolver.php';

$helper = AjaxListingHelper::boot();
$helper->requireCentreon();
$helper->requireReadAccess(60101);
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search    = $params['search'];
$num       = $params['num'];
$limit     = $params['limit'];
// Absent or empty means "no filter" (0) — a cleared select2 sends an empty value.
// Anything else must be a non-negative integer: collapsing an unparseable value
// into 0 answered an unfiltered list to `poller=abc`, presenting every host as
// though the filter had been applied.
$filters = [];
foreach (['hostgroup', 'poller', 'template', 'status'] as $filter) {
    if (! isset($_GET[$filter]) || $_GET[$filter] === '') {
        $filters[$filter] = 0;

        continue;
    }

    $value = filter_var($_GET[$filter], FILTER_VALIDATE_INT);
    if ($value === false || $value < 0) {
        AjaxListingHelper::jsonError('Invalid parameters', 400);
    }

    $filters[$filter] = $value;
}

$hostgroup = $filters['hostgroup'];
$poller    = $filters['poller'];
$template  = $filters['template'];
$status    = $filters['status'];

try {
    $joins      = '';
    $conditions = "h.host_register = '1'";
    $parameters = [];

    // A non-admin only sees the hosts its ACL groups grant, through the same
    // centreon_acl join the toggle endpoint uses.
    $aclPollerIds = [];
    if (! $helper->isAdmin()) {
        $acl         = $helper->getAcl();
        $aclGroupIds = $acl !== null
            ? array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))))
            : [];

        if ($aclGroupIds === []) {
            // Indistinguishable from a platform with no hosts, so the operator
            // reading "0 host" needs this line to tell a misconfigured ACL from
            // an empty inventory.
            Logger::create(LogChannelEnum::WEB)->warning(
                'AJAX listing: user has read access to the hosts page but no ACL group, listing is empty',
                ['pageId' => 60101]
            );
            $helper->jsonResponse([], 0, $num, $limit);
        }

        // Poller names are ACL-filtered too, as the legacy listing did: a host may
        // be granted while the poller running it is not.
        $aclPollerIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $acl?->getPollerString('ID'))
        )));

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
    // whole page rather than per row: with a page size of up to MAX_LIMIT and a
    // 15s auto-refresh, a per-row lookup means thousands of queries per tick.
    // Pollers and templates take one query each; the icon walk takes one query
    // up front plus two per step, a step being one node popped per object rather
    // than one inheritance level (see HostIconResolver).
    $hostPollers     = [];
    $templatesByHost = [];
    $icons           = [];
    if ($hostIds !== []) {
        $pollerIn = AjaxListingHelper::buildIntInClause($hostIds, 'poller_hid');
        $pollerParameters = $pollerIn['parameters'];
        $pollerCondition  = '';
        if ($aclPollerIds !== []) {
            $aclPollerIn      = AjaxListingHelper::buildIntInClause($aclPollerIds, 'acl_pid');
            $pollerParameters = [...$pollerParameters, ...$aclPollerIn['parameters']];
            $pollerCondition  = " AND nshr.nagios_server_id IN ({$aclPollerIn['clause']})";
        }
        $pollerRows = $pearDB->fetchAllAssociative(
            <<<SQL
                SELECT nshr.host_host_id, ns.name
                FROM ns_host_relation nshr
                INNER JOIN nagios_server ns ON ns.id = nshr.nagios_server_id
                WHERE nshr.host_host_id IN ({$pollerIn['clause']}){$pollerCondition}
                SQL,
            QueryParameters::create($pollerParameters)
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
    // null until a lookup was actually attempted: a page with no rows must not
    // claim the monitoring source is healthy, or the client clears its warning
    // on an empty search in the middle of an outage.
    $rtmStatus      = [];
    $rtmUnavailable = null;
    if ($hostIds !== []) {
        $rtmUnavailable = false;
        try {
            $rtIn = AjaxListingHelper::buildIntInClause($hostIds, 'rid');
            $rtRows = AjaxListingHelper::realtimeConnection()->fetchAllAssociative(
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
        } catch (ConnectionException $exception) {
            // Narrow on purpose: a TypeError from our own code must not hide behind
            // a "centstorage unavailable" label and regress silently — it belongs in
            // the endpoint's 500 handler.
            //
            // Covers both a reachable centstorage answering badly — `resources`
            // missing without unified_sql, a denied grant, a schema drift — and an
            // unreachable one, since realtimeConnection() goes through the factory
            // that wraps the failure instead of `new CentreonDB('centstorage')`,
            // whose constructor prints an HTML error page and exits under a web
            // SAPI, leaving nothing to catch and HTML under our JSON content type.
            Logger::create(LogChannelEnum::WEB)->error(
                'AJAX listing: real-time host status unavailable, listing rendered without it',
                ['exception' => $exception]
            );
            $rtmStatus     = [];
            $rtmUnavailable = true;
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

    // Without this the client cannot tell "this host is not monitored" from "we
    // could not read the monitoring data": both render the same dimmed dash.
    // Absent when no lookup was attempted, so the client leaves its state alone.
    $helper->jsonResponse(
        $rows,
        $total,
        $num,
        $limit,
        $rtmUnavailable === null ? [] : ['rtm_available' => ! $rtmUnavailable]
    );
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch hosts',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
