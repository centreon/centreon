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
    $conditions = [];
    $parameters = [];

    // Search filter
    if ($search !== '') {
        $conditions[] = 'meta_name LIKE :search';
        $parameters[] = QueryParameter::string('search', '%' . $search . '%');
    }

    // ACL filtering: a non-admin only sees the meta services granted by its ACL.
    if (! $helper->isAdmin()) {
        $acl     = $helper->getAcl();
        $metaIds = $acl !== null
            ? array_values(array_filter(array_map('intval', array_keys($acl->getMetaServices()))))
            : [];

        if ($metaIds === []) {
            $helper->jsonResponse([], 0, $num, $limit);
        }

        $placeholders = [];
        foreach ($metaIds as $index => $metaId) {
            $placeholder    = 'meta_id_' . $index;
            $placeholders[] = ':' . $placeholder;
            $parameters[]   = QueryParameter::int($placeholder, $metaId);
        }
        $conditions[] = 'meta_id IN (' . implode(', ', $placeholders) . ')';
    }

    $whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

    $countQuery = <<<SQL
        SELECT COUNT(*) AS total FROM meta_service {$whereClause}
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dataQuery = <<<SQL
        SELECT meta_id, meta_name, calcul_type, warning, critical, meta_activate, meta_select_mode
        FROM meta_service
        {$whereClause}
        ORDER BY meta_name
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
        ) as $ms
    ) {
        $rows[] = [
            'id'          => (int) $ms['meta_id'],
            'name'        => $ms['meta_name'],
            'calcul_type' => $ms['calcul_type'],
            'warning'     => $ms['warning'],
            'critical'    => $ms['critical'],
            'activate'    => (int) $ms['meta_activate'],
            'select_mode' => (int) $ms['meta_select_mode'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch meta services',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
