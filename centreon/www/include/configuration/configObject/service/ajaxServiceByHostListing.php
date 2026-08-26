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
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search    = $params['search'];
$num       = $params['num'];
$limit     = $params['limit'];
$searchH   = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchH'] ?? '');
$searchS   = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchS'] ?? '');
$template  = filter_var($_GET['template'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$status    = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$hostStatus = filter_var($_GET['hostStatus'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$hostId    = filter_var($_GET['hostId'] ?? null, FILTER_VALIDATE_INT) ?: 0;

try {
    // ACL: require at least read access on the services by host page (60201).
    // Row-level scoping below is not a substitute: without this, any
    // authenticated session can query the endpoint directly.
    if (! $helper->isAdmin()) {
        $pageAcl = $helper->getAcl();
        if ($pageAcl === null || $pageAcl->page(60201) === 0) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }

    // Interval length from options table
    $intervalLength = (int) ($pearDB->fetchOne(
        <<<'SQL'
            SELECT `value` FROM `options` WHERE `key` = 'interval_length'
            SQL
    ) ?: 60);

    // Icons are resolved per page (only for the rows actually displayed) and follow
    // the template chain when an object carries no icon of its own; see the icon
    // resolvers further down. These caches are shared across the current page rows.
    $imgPathCache    = []; // view_img id -> media path
    $svcIconIdCache  = []; // service_id  -> own icon image id (null if none)
    $hostIconIdCache = []; // host_id     -> own icon image id (null if none)
    $hostTplCache    = []; // host_id     -> ordered parent template host ids

    // Build query
    $joins = ' FROM service sv'
        . ' INNER JOIN host_service_relation hsr ON hsr.service_service_id = sv.service_id'
        . ' INNER JOIN host ON host.host_id = hsr.host_host_id';
    $conditions = [];
    $parameters = [];

    $conditions[] = "host.host_register = '1' AND sv.service_register = '1'";

    // ACL
    $aclJoin = '';
    if (! $helper->isAdmin()) {
        $acl = $helper->getAcl();
        $aclGroupIds = array_keys($acl->getAccessGroups());
        if ($aclGroupIds !== []) {
            $aclDbName = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
            $aclPlaceholders = [];
            foreach ($aclGroupIds as $idx => $gid) {
                $key = ':acl_g' . $idx;
                $aclPlaceholders[] = $key;
                $parameters[] = QueryParameter::int($key, (int) $gid);
            }
            $aclIn = implode(',', $aclPlaceholders);
            $aclJoin = " INNER JOIN `{$aclDbName}`.centreon_acl acl ON acl.host_id = host.host_id AND acl.service_id = sv.service_id AND acl.group_id IN ({$aclIn}) ";
        } else {
            $helper->jsonResponse([], 0, $num, $limit);
        }
    }
    $joins .= $aclJoin;

    // Exact host filter (e.g. when coming from the host listing)
    if ($hostId > 0) {
        $conditions[] = 'host.host_id = :hostId';
        $parameters[] = QueryParameter::int('hostId', $hostId);
    }

    // Host search
    if ($searchH !== '') {
        $conditions[] = '(host.host_name LIKE :searchH OR host.host_alias LIKE :searchH OR host.host_address LIKE :searchH)';
        $parameters[] = QueryParameter::string('searchH', '%' . $searchH . '%');
    }

    // Service search
    if ($searchS !== '') {
        $conditions[] = '(sv.service_description LIKE :searchS OR sv.service_alias LIKE :searchS)';
        $parameters[] = QueryParameter::string('searchS', '%' . $searchS . '%');
    }

    // Host status (0 = only enabled hosts, 1 = include disabled)
    if (! $hostStatus) {
        $conditions[] = "host.host_activate = '1'";
    }

    // Service status filter
    if ($status === 2) {
        $conditions[] = "sv.service_activate = '1'";
    } elseif ($status === 1) {
        $conditions[] = "sv.service_activate = '0'";
    }

    // Template filter
    if ($template > 0) {
        $conditions[] = 'sv.service_template_model_stm_id = :tpl_id';
        $parameters[] = QueryParameter::int('tpl_id', $template);
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    $distinct = $helper->isAdmin() ? '' : 'DISTINCT';

    // Count the pairs the listing actually renders, not the services behind them.
    // Each data row is one (service, host) pair, and for a non-admin the
    // centreon_acl join repeats a pair once per access group — which is why the
    // data query is DISTINCT. Counting distinct sv.service_id instead collapsed
    // a service shared across several hosts into one, so total came out below
    // the number of rows and the last page went unreachable.
    $countQuery = <<<SQL
        SELECT COUNT(*) AS total FROM (
            SELECT {$distinct} sv.service_id, host.host_id
            {$joins}
            {$whereClause}
        ) AS counted
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dataQuery = <<<SQL
        SELECT {$distinct}
               sv.service_id, sv.service_description, sv.service_activate,
               sv.service_template_model_stm_id,
               sv.service_normal_check_interval, sv.service_retry_check_interval,
               host.host_id, host.host_name
        {$joins}
        {$whereClause}
        ORDER BY host.host_name, sv.service_description
        LIMIT :offset, :limit
        SQL;

    $results = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    // Real-time service status (only for the services on this page), read from the
    // modern unified `resources` table rather than the legacy broker `services`
    // table: it is better indexed and is the source of truth in unified_sql mode.
    // (Host monitoring status is intentionally not fetched here: this is a service
    // configuration listing, so a host's live state is not relevant.)
    $pearDBO = new CentreonDB('centstorage');
    $rtmStatus = [];
    if (! empty($results)) {
        // The current page's host ids scope the real-time lookup
        $hostIds = array_unique(array_map(static function ($r) { return (int) $r['host_id']; }, $results));
        if ($hostIds !== []) {
            $rtmPlaceholders = [];
            $rtmParameters = [];
            foreach (array_values($hostIds) as $index => $hostIdValue) {
                $placeholder = 'rtm_host' . $index;
                $rtmPlaceholders[] = ':' . $placeholder;
                $rtmParameters[] = QueryParameter::int($placeholder, $hostIdValue);
            }
            $rtmIn = implode(', ', $rtmPlaceholders);
            // Services: type = 0, id = service_id, parent_id = host_id
            $rtmQuery = <<<SQL
                SELECT parent_id AS host_id, id AS service_id, status AS state, output, last_check
                FROM resources WHERE type = 0 AND enabled = 1 AND parent_id IN ({$rtmIn})
                SQL;
            foreach ($pearDBO->fetchAllAssociative($rtmQuery, QueryParameters::create($rtmParameters)) as $row) {
                $rtmStatus[(int) $row['host_id'] . '_' . (int) $row['service_id']] = [
                    'state'  => (int) $row['state'],
                    'output' => $row['output'],
                    'last_check' => $row['last_check'] ? (int) $row['last_check'] : null,
                ];
            }
        }
    }

    // Template chain helper
    function getTemplateChain(int $tplId, CentreonDB $db, array &$cache, int $depth = 0): array
    {
        if ($depth > 5 || ! $tplId) {
            return [];
        }
        if (isset($cache[$tplId])) {
            return $cache[$tplId];
        }
        $tpl = $db->fetchAssociative(
            <<<'SQL'
                SELECT service_description, service_template_model_stm_id,
                       service_normal_check_interval, service_retry_check_interval
                FROM service
                WHERE service_id = :id
                SQL,
            QueryParameters::create([QueryParameter::int('id', $tplId)])
        );
        if ($tpl === false) {
            return [];
        }
        $chain = [['id' => $tplId, 'name' => $tpl['service_description'], 'normal' => $tpl['service_normal_check_interval'], 'retry' => $tpl['service_retry_check_interval']]];
        if ($tpl['service_template_model_stm_id']) {
            $chain = array_merge($chain, getTemplateChain((int) $tpl['service_template_model_stm_id'], $db, $cache, $depth + 1));
        }
        $cache[$tplId] = $chain;

        return $chain;
    }

    // --- Icon resolution (page-scoped, template-inherited) --------------------

    // Resolve a view_img id to its media path, lazily (only for icons we display).
    function imgMediaPath(CentreonDB $db, ?int $imgId, array &$cache): ?string
    {
        if (! $imgId) {
            return null;
        }
        if (array_key_exists($imgId, $cache)) {
            return $cache[$imgId];
        }
        $p = $db->fetchOne(
            <<<'SQL'
                SELECT CONCAT(vid.dir_alias, '/', vi.img_path) AS p
                FROM view_img vi
                INNER JOIN view_img_dir_relation vidr ON vidr.img_img_id = vi.img_id
                INNER JOIN view_img_dir vid ON vid.dir_id = vidr.dir_dir_parent_id
                WHERE vi.img_id = :id
                LIMIT 1
                SQL,
            QueryParameters::create([QueryParameter::int('id', $imgId)])
        );

        return $cache[$imgId] = $p ? ('./img/media/' . $p) : null;
    }

    // A service's own icon image id (extended_service_information), null if none.
    function serviceOwnIconId(CentreonDB $db, int $serviceId, array &$cache): ?int
    {
        if (array_key_exists($serviceId, $cache)) {
            return $cache[$serviceId];
        }
        $v = $db->fetchOne(
            <<<'SQL'
                SELECT esi_icon_image FROM extended_service_information WHERE service_service_id = :id LIMIT 1
                SQL,
            QueryParameters::create([QueryParameter::int('id', $serviceId)])
        );

        return $cache[$serviceId] = $v ? (int) $v : null;
    }

    // A host's own icon image id (extended_host_information), null if none.
    function hostOwnIconId(CentreonDB $db, int $hostId, array &$cache): ?int
    {
        if (array_key_exists($hostId, $cache)) {
            return $cache[$hostId];
        }
        $v = $db->fetchOne(
            <<<'SQL'
                SELECT ehi_icon_image FROM extended_host_information WHERE host_host_id = :id LIMIT 1
                SQL,
            QueryParameters::create([QueryParameter::int('id', $hostId)])
        );

        return $cache[$hostId] = $v ? (int) $v : null;
    }

    // A host's parent templates (host_template_relation), most significant first.
    function hostParentTemplates(CentreonDB $db, int $hostId, array &$cache): array
    {
        if (isset($cache[$hostId])) {
            return $cache[$hostId];
        }
        $ids = [];
        foreach (
            $db->fetchAllAssociative(
                <<<'SQL'
                    SELECT host_tpl_id FROM host_template_relation WHERE host_host_id = :id ORDER BY `order` ASC
                    SQL,
                QueryParameters::create([QueryParameter::int('id', $hostId)])
            ) as $r
        ) {
            $ids[] = (int) $r['host_tpl_id'];
        }

        return $cache[$hostId] = $ids;
    }

    // A host's icon image id, following the host template chain when it has none.
    function resolveHostIconId(CentreonDB $db, int $hostId, array &$iconCache, array &$tplCache, int $depth = 0): ?int
    {
        if ($depth > 10 || ! $hostId) {
            return null;
        }
        $own = hostOwnIconId($db, $hostId, $iconCache);
        if ($own) {
            return $own;
        }
        foreach (hostParentTemplates($db, $hostId, $tplCache) as $tplId) {
            $inherited = resolveHostIconId($db, $tplId, $iconCache, $tplCache, $depth + 1);
            if ($inherited) {
                return $inherited;
            }
        }

        return null;
    }

    // Resolve inherited interval by walking template chain.
    // Interval columns come straight from the DB, which types them as int or as
    // string depending on the driver's emulation settings — accept both.
    function resolveInterval(int|string|null $direct, array $tplChain, string $field): ?int
    {
        if ($direct !== null && $direct !== '') {
            return (int) $direct;
        }
        foreach ($tplChain as $tpl) {
            if (isset($tpl[$field]) && $tpl[$field] !== null && $tpl[$field] !== '') {
                return (int) $tpl[$field];
            }
        }

        return null;
    }

    $tplCache = [];
    $rows = [];
    foreach ($results as $svc) {
        $sid = (int) $svc['service_id'];
        $hid = (int) $svc['host_id'];

        // Service description
        $desc = $svc['service_description'];
        if ($desc) {
            $desc = str_replace(['#S#', '#BS#'], ['/', '\\'], $desc);
        }

        // Template chain (must be before scheduling for inheritance)
        $templates = [];
        if ($svc['service_template_model_stm_id']) {
            $templates = getTemplateChain((int) $svc['service_template_model_stm_id'], $pearDB, $tplCache);
        }

        // Scheduling (with template inheritance)
        $resolvedNormal = resolveInterval($svc['service_normal_check_interval'], $templates, 'normal');
        $resolvedRetry  = resolveInterval($svc['service_retry_check_interval'], $templates, 'retry');
        $normalInterval = ($resolvedNormal !== null) ? $resolvedNormal * $intervalLength : 0;
        $retryInterval  = ($resolvedRetry !== null) ? $resolvedRetry * $intervalLength : 0;
        $scheduling = '';
        if ($normalInterval > 0) {
            $scheduling = ($normalInterval % 60 === 0 ? ($normalInterval / 60) . ' min' : $normalInterval . ' sec');
            if ($retryInterval > 0) {
                $scheduling .= ' / ' . ($retryInterval % 60 === 0 ? ($retryInterval / 60) . ' min' : $retryInterval . ' sec');
            }
        }

        // RTM
        $rtmKey = $hid . '_' . $sid;
        $mon = $rtmStatus[$rtmKey] ?? null;

        // Service icon: its own, else the first one found up the template chain
        $svcIconId = serviceOwnIconId($pearDB, $sid, $svcIconIdCache);
        if (! $svcIconId) {
            foreach ($templates as $tpl) {
                $svcIconId = serviceOwnIconId($pearDB, (int) $tpl['id'], $svcIconIdCache);
                if ($svcIconId) {
                    break;
                }
            }
        }
        // Host icon: its own, else inherited from the host template chain
        $hostIconId = resolveHostIconId($pearDB, $hid, $hostIconIdCache, $hostTplCache);

        $rows[] = [
            'id'         => $sid,
            'key'        => $hid . '_' . $sid,
            'host_id'    => $hid,
            'host_name'  => $svc['host_name'],
            'host_icon'  => imgMediaPath($pearDB, $hostIconId, $imgPathCache),
            'desc'       => $desc,
            'svc_icon'   => imgMediaPath($pearDB, $svcIconId, $imgPathCache),
            'scheduling' => $scheduling,
            'templates'  => $templates,
            'activate'   => (int) $svc['service_activate'],
            'mon_state'      => $mon ? $mon['state'] : null,
            'mon_output'     => $mon ? $mon['output'] : null,
            'mon_last'       => $mon ? $mon['last_check'] : null,
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch services by host',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
