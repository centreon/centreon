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
$helper->requireReadAccess(60103);
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search        = $params['search'];
$num           = $params['num'];
$limit         = $params['limit'];
$displayLocked = filter_var($_GET['displayLocked'] ?? 'off', FILTER_VALIDATE_BOOLEAN);

try {
    // Locked templates are hidden unless explicitly requested. $displayLocked is
    // a validated boolean, so it is inlined rather than bound.
    $conditions = "host_register = '0'" . ($displayLocked ? '' : ' AND host_locked = 0');
    $parameters = [];
    if ($search !== '') {
        $conditions .= ' AND (host_name LIKE :search OR host_alias LIKE :search)';
        $parameters[] = QueryParameter::string(
            'search',
            '%' . AjaxListingHelper::escapeLikeWildcards($search) . '%'
        );
    }

    $countQuery = <<<SQL
        SELECT COUNT(*) AS total FROM host WHERE {$conditions}
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    // host_locked comes along with the row: a second query listing every locked
    // template on the platform was reading rows this page never displays.
    $dataQuery = <<<SQL
        SELECT host_id, host_name, host_alias, host_template_model_htm_id, host_locked
        FROM host
        WHERE {$conditions}
        ORDER BY host_name
        LIMIT :offset, :limit
        SQL;
    $hostRows = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $hostIds = array_map(static fn (array $host): int => (int) $host['host_id'], $hostRows);

    // Service counts, parent templates and icons of the listed templates, all
    // resolved for the whole page rather than per row: with a page size of up to
    // MAX_LIMIT and a 30s auto-refresh, per-row lookups mean thousands of queries
    // per tick. The icon walk takes one query up front plus two per step, a step
    // being one node popped per object rather than one inheritance level.
    $svcCounts       = [];
    $templatesByHost = [];
    $icons           = [];
    $modelNames      = [];
    if ($hostIds !== []) {
        $svcIn = AjaxListingHelper::buildIntInClause($hostIds, 'svc_hid');
        $svcRows = $pearDB->fetchAllAssociative(
            <<<SQL
                SELECT host_host_id, COUNT(*) AS svc_count
                FROM host_service_relation
                WHERE host_host_id IN ({$svcIn['clause']})
                GROUP BY host_host_id
                SQL,
            QueryParameters::create($svcIn['parameters'])
        );
        foreach ($svcRows as $row) {
            $svcCounts[(int) $row['host_host_id']] = (int) $row['svc_count'];
        }

        // Parent templates (first level only for simplicity)
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

        // Legacy "host model" rows carry no host_name of their own and inherit the
        // one of the model they point at — the legacy listing called getMyHostName()
        // per row for this. Without the fallback such a row renders as an empty cell
        // wrapping an invisible but clickable link. Resolved in one query, and only
        // when the page actually holds one.
        $modelIds = [];
        foreach ($hostRows as $host) {
            if (($host['host_name'] ?? '') === '' && (int) $host['host_template_model_htm_id'] !== 0) {
                $modelIds[] = (int) $host['host_template_model_htm_id'];
            }
        }
        if ($modelIds !== []) {
            $modelIn = AjaxListingHelper::buildIntInClause(array_values(array_unique($modelIds)), 'model_hid');
            $modelRows = $pearDB->fetchAllAssociative(
                <<<SQL
                    SELECT host_id, host_name
                    FROM host
                    WHERE host_register = '0' AND host_id IN ({$modelIn['clause']})
                    SQL,
                QueryParameters::create($modelIn['parameters'])
            );
            foreach ($modelRows as $row) {
                $modelNames[(int) $row['host_id']] = (string) $row['host_name'];
            }
        }
    }

    $rows = [];
    foreach ($hostRows as $host) {
        $hid = (int) $host['host_id'];

        $rows[] = [
            'id'        => $hid,
            'name'      => ($host['host_name'] ?? '') !== ''
                ? $host['host_name']
                : ($modelNames[(int) $host['host_template_model_htm_id']] ?? ''),
            'alias'     => $host['host_alias'],
            'svc_count' => $svcCounts[$hid] ?? 0,
            'templates' => $templatesByHost[$hid] ?? [],
            'locked'    => (int) $host['host_locked'] === 1,
            'icon'      => $icons[$hid] ?? null,
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch host templates',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
