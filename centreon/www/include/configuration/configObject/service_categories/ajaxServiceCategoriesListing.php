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

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

try {
    // ACL: require at least read access on the service categories page (60209).
    // Row-level scoping below is not a substitute: without this, any
    // authenticated session can query the endpoint directly.
    if (! $helper->isAdmin()) {
        $pageAcl = $helper->getAcl();
        if ($pageAcl === null || $pageAcl->page(60209) === 0) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }

    $conditions = [];
    $parameters = [];

    // Search filter
    if ($search !== '') {
        $conditions[] = '(sc_name LIKE :search OR sc_description LIKE :search)';
        $parameters[] = QueryParameter::string('search', '%' . $search . '%');
    }

    // ACL filtering: a non-admin only sees the service categories granted by its ACL.
    if (! $helper->isAdmin()) {
        $acl   = $helper->getAcl();
        $scIds = $acl !== null
            ? array_values(array_filter(array_map('intval', explode(',', $acl->getServiceCategoriesString('ID')))))
            : [];

        if ($scIds === []) {
            $helper->jsonResponse([], 0, $num, $limit);
        }

        $placeholders = [];
        foreach ($scIds as $index => $scId) {
            $placeholder    = 'sc_id_' . $index;
            $placeholders[] = ':' . $placeholder;
            $parameters[]   = QueryParameter::int($placeholder, $scId);
        }
        $conditions[] = 'sc.sc_id IN (' . implode(', ', $placeholders) . ')';
    }

    $whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

    $countQuery = <<<SQL
        SELECT COUNT(*) AS total FROM service_categories sc {$whereClause}
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dataQuery = <<<SQL
        SELECT
            sc.sc_id,
            sc.sc_name,
            sc.sc_description,
            sc.sc_activate,
            sc.level,
            (SELECT COUNT(*) FROM service_categories_relation scr WHERE scr.sc_id = sc.sc_id) AS svc_count
        FROM service_categories sc
        {$whereClause}
        ORDER BY sc.sc_name
        LIMIT :offset, :limit
        SQL;

    $rows = [];
    foreach (
        $pearDB->fetchAllAssociative(
            $dataQuery,
            QueryParameters::create([
                ...$parameters,
                QueryParameter::int('offset', $num * $limit),
                QueryParameter::int('limit', $limit),
            ])
        ) as $sc
    ) {
        $rows[] = [
            'id'          => (int) $sc['sc_id'],
            'name'        => $sc['sc_name'],
            'description' => $sc['sc_description'],
            'activate'    => (int) $sc['sc_activate'],
            'svc_count'   => (int) $sc['svc_count'],
            'level'       => $sc['level'] ? (int) $sc['level'] : null,
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch service categories',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
