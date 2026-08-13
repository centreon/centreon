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
$searchHG  = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchHG'] ?? '');
$searchS   = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['searchS'] ?? '');
$template  = filter_var($_GET['template'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$status    = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;

try {
    // Interval length from options table
    $intervalLength = (int) ($pearDB->fetchOne(
        <<<'SQL'
            SELECT `value` FROM `options` WHERE `key` = 'interval_length'
            SQL
    ) ?: 60);

    // Service icon cache
    $svcIconCache = [];
    $svcIconQuery = <<<'SQL'
        SELECT esi.service_service_id, CONCAT(vid.dir_alias, '/', vi.img_path) AS icon_path
        FROM extended_service_information esi
        INNER JOIN view_img vi ON esi.esi_icon_image = vi.img_id
        INNER JOIN view_img_dir_relation vidr ON vi.img_id = vidr.img_img_id
        INNER JOIN view_img_dir vid ON vidr.dir_dir_parent_id = vid.dir_id
        WHERE esi.esi_icon_image IS NOT NULL
        SQL;
    foreach ($pearDB->fetchAllAssociative($svcIconQuery) as $row) {
        $svcIconCache[(int) $row['service_service_id']] = './img/media/' . $row['icon_path'];
    }

    // Build query
    $joins = ' FROM service sv'
        . ' INNER JOIN host_service_relation hsr ON hsr.service_service_id = sv.service_id'
        . ' INNER JOIN hostgroup hg ON hg.hg_id = hsr.hostgroup_hg_id';
    $conditions = [];
    $parameters = [];

    $conditions[] = "sv.service_register = '1' AND hsr.host_host_id IS NULL";

    // ACL
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
            // The ACL row must reference a host that actually belongs to the hostgroup
            // being displayed. Matching on service_id alone would list a service shared
            // across hostgroups under a hostgroup the user has no access to (leak).
            $joins .= " INNER JOIN `{$aclDbName}`.centreon_acl acl ON acl.service_id = sv.service_id AND acl.group_id IN ({$aclIn}) "
                . ' INNER JOIN hostgroup_relation hgr_acl ON hgr_acl.hostgroup_hg_id = hg.hg_id AND hgr_acl.host_host_id = acl.host_id ';
        } else {
            $helper->jsonResponse([], 0, 0, $limit);
        }
    }

    // Hostgroup search
    if ($searchHG !== '') {
        $conditions[] = 'hg.hg_name LIKE :searchHG';
        $parameters[] = QueryParameter::string('searchHG', '%' . $searchHG . '%');
    }

    // Service search
    if ($searchS !== '') {
        $conditions[] = '(sv.service_description LIKE :searchS OR sv.service_alias LIKE :searchS)';
        $parameters[] = QueryParameter::string('searchS', '%' . $searchS . '%');
    }

    // Status filter
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

    $countQuery = <<<SQL
        SELECT COUNT({$distinct} sv.service_id) AS total
        {$joins}
        {$whereClause}
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dataQuery = <<<SQL
        SELECT {$distinct}
               sv.service_id, sv.service_description, sv.service_activate,
               sv.service_template_model_stm_id,
               sv.service_normal_check_interval, sv.service_retry_check_interval,
               hg.hg_id, hg.hg_name
        {$joins}
        {$whereClause}
        ORDER BY hg.hg_name, sv.service_description
        LIMIT :offset, :limit
        SQL;

    $results = [];
    foreach (
        $pearDB->fetchAllAssociative(
            $dataQuery,
            QueryParameters::create([
                ...$parameters,
                QueryParameter::int('offset', $num * $limit),
                QueryParameter::int('limit', $limit),
            ])
        ) as $svc
    ) {
        $results[] = $svc;
    }

    // Template chain helper
    function getTemplateChainHG(int $tplId, CentreonDB $db, array &$cache, int $depth = 0): array
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
        if (! $tpl) {
            return [];
        }
        $chain = [['id' => $tplId, 'name' => $tpl['service_description'], 'normal' => $tpl['service_normal_check_interval'], 'retry' => $tpl['service_retry_check_interval']]];
        if ($tpl['service_template_model_stm_id']) {
            $chain = array_merge($chain, getTemplateChainHG((int) $tpl['service_template_model_stm_id'], $db, $cache, $depth + 1));
        }
        $cache[$tplId] = $chain;

        return $chain;
    }

    // Interval columns come straight from the DB, which types them as int or as
    // string depending on the driver's emulation settings — accept both.
    function resolveIntervalHG(int|string|null $direct, array $tplChain, string $field): ?int
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
        $desc = $svc['service_description'];
        if ($desc) {
            $desc = str_replace(['#S#', '#BS#'], ['/', '\\'], $desc);
        }

        $templates = [];
        if ($svc['service_template_model_stm_id']) {
            $templates = getTemplateChainHG((int) $svc['service_template_model_stm_id'], $pearDB, $tplCache);
        }

        $resolvedNormal = resolveIntervalHG($svc['service_normal_check_interval'], $templates, 'normal');
        $resolvedRetry  = resolveIntervalHG($svc['service_retry_check_interval'], $templates, 'retry');
        $normalInterval = ($resolvedNormal !== null) ? $resolvedNormal * $intervalLength : 0;
        $retryInterval  = ($resolvedRetry !== null) ? $resolvedRetry * $intervalLength : 0;
        $scheduling = '';
        if ($normalInterval > 0) {
            $scheduling = ($normalInterval % 60 === 0 ? ($normalInterval / 60) . ' min' : $normalInterval . ' sec');
            if ($retryInterval > 0) {
                $scheduling .= ' / ' . ($retryInterval % 60 === 0 ? ($retryInterval / 60) . ' min' : $retryInterval . ' sec');
            }
        }

        $rows[] = [
            'id'        => $sid,
            'hg_id'     => (int) $svc['hg_id'],
            'hg_name'   => $svc['hg_name'],
            'desc'      => $desc,
            'svc_icon'  => $svcIconCache[$sid] ?? null,
            'scheduling' => $scheduling,
            'templates' => $templates,
            'activate'  => (int) $svc['service_activate'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch services by hostgroup',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
