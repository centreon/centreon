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
$num      = $params['num'];
$limit    = $params['limit'];

$metaId = filter_var($_GET['meta_id'] ?? null, FILTER_VALIDATE_INT);
if (! $metaId) {
    AjaxListingHelper::jsonError('Missing meta_id', 400);
}

try {
    // ACL: require at least read access on the meta services page (60204).
    if (! $helper->isAdmin()) {
        $pageAcl = $helper->getAcl();
        if ($pageAcl === null || $pageAcl->page(60204) === 0) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }

    $metaInfo = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT meta_name, calcul_type FROM meta_service WHERE meta_id = :meta_id
            SQL,
        QueryParameters::create([QueryParameter::int('meta_id', $metaId)])
    );
    if (! $metaInfo) {
        AjaxListingHelper::jsonError('Meta service not found', 404);
    }

    // The metric rows are ACL-filtered below, but meta_name is echoed back
    // unconditionally in the response and rendered in the page header. Without
    // this check, iterating meta_id enumerates the names of meta services the
    // caller cannot see. Same ACL source as ajaxMetaMetricToggle.php.
    if (! $helper->isAdmin()) {
        $scopeAcl   = $helper->getAcl();
        $grantedIds = $scopeAcl !== null
            ? array_values(array_filter(array_map('intval', array_keys($scopeAcl->getMetaServices()))))
            : [];

        if (! in_array($metaId, $grantedIds, true)) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }

    $relationParameters = [QueryParameter::int('meta_id', $metaId)];
    $aclFrom = '';
    $aclCond = '';
    if (! $helper->isAdmin()) {
        $acl = $helper->getAcl();
        $aclGroupIds = $acl !== null
            ? array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))))
            : [];

        if ($aclGroupIds === []) {
            $helper->jsonResponse([], 0, $num, $limit);
        }

        $aclPlaceholders = [];
        foreach ($aclGroupIds as $index => $groupId) {
            $placeholder          = 'acl_gid' . $index;
            $aclPlaceholders[]    = ':' . $placeholder;
            $relationParameters[] = QueryParameter::int($placeholder, $groupId);
        }

        // The real-time database name comes from the connection config, not from
        // the legacy getNameDBAcl() (which reads a global that is null here).
        $aclDbName = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
        $aclFrom = ", `{$aclDbName}`.centreon_acl acl ";
        $aclCond = ' AND acl.host_id = msr.host_id AND acl.group_id IN (' . implode(', ', $aclPlaceholders) . ') ';
    }

    $relations = [];
    $metricIds = [];
    foreach (
        $pearDB->fetchAllAssociative(
            // host_id has to be selected, not just ordered on: with DISTINCT, MariaDB
            // rejects an ORDER BY on a column outside the SELECT list (error 3065).
            // Adding it does not change the deduplication — msr_id is the table's
            // primary key, so host_id is functionally determined by it. The DISTINCT
            // is there to collapse the rows duplicated by the ACL join below.
            <<<SQL
                SELECT DISTINCT msr.msr_id, msr.metric_id, msr.activate, msr.host_id
                FROM meta_service_relation msr {$aclFrom}
                WHERE msr.meta_id = :meta_id {$aclCond}
                ORDER BY msr.host_id
                SQL,
            QueryParameters::create($relationParameters)
        ) as $row
    ) {
        $relations[$row['metric_id']][] = [
            'msr_id' => (int) $row['msr_id'],
            'activate' => (int) $row['activate'],
        ];
        $metricIds[] = (int) $row['metric_id'];
    }

    $rows = [];
    if ($metricIds !== []) {
        $pearDBO = new CentreonDB('centstorage');

        $metricPlaceholders = [];
        $metricParameters   = [];
        foreach (array_values(array_unique($metricIds)) as $index => $metricId) {
            $placeholder          = 'metric_id_' . $index;
            $metricPlaceholders[] = ':' . $placeholder;
            $metricParameters[]   = QueryParameter::int($placeholder, $metricId);
        }
        $metricInList = implode(', ', $metricPlaceholders);

        foreach (
            $pearDBO->fetchAllAssociative(
                <<<SQL
                    SELECT m.metric_id, m.metric_name, m.unit_name, i.host_name, i.service_description
                    FROM metrics m, index_data i
                    WHERE m.metric_id IN ({$metricInList})
                    AND m.index_id = i.id
                    ORDER BY i.host_name, i.service_description, m.metric_name
                    SQL,
                QueryParameters::create($metricParameters)
            ) as $metric
        ) {
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

    // The relations are assembled in PHP rather than paginated in SQL, so the
    // page is cut here. Echoing back limit = count($rows) instead made the
    // client compute Math.ceil(0/0) = NaN on an empty meta service, leaving the
    // Next/Last arrows enabled, and never matched one of the rows-per-page
    // options on a populated one.
    $total = count($rows);
    $rows  = array_slice($rows, $num * $limit, $limit);

    // JSON_INVALID_UTF8_SUBSTITUTE mirrors AjaxListingHelper::jsonResponse(), which
    // this endpoint bypasses to carry meta_name/meta_calc_type: without it a single
    // non-UTF-8 byte in a metric or host name turns the whole panel into a 500.
    $centreonToken = createCSRFToken();
    echo json_encode([
        'rows'           => array_values($rows),
        'total'          => $total,
        'num'            => $num,
        'limit'          => $limit,
        'centreon_token' => $centreonToken,
        'meta_name'      => $metaInfo['meta_name'],
        // Raw code (AVE/SOM/MIN/MAX): the template holds the translated labels,
        // so mapping it here would ship an untranslated English string instead.
        'meta_calc_type' => $metaInfo['calcul_type'],
    ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error('AJAX listing: failed to fetch meta metrics', ['exception' => $exception]);
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
