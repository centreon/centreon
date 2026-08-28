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
// Configuration > SNMP Traps > Group
$helper->requireReadAccess(61705);
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

$whereClause = '';
$parameters  = [];

if ($search !== '') {
    $whereClause = 'WHERE traps_group_name LIKE :search';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM traps_group
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT traps_group_id, traps_group_name
    FROM traps_group
    {$whereClause}
    ORDER BY traps_group_name
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $groups = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($groups as $group) {
        $rows[] = [
            'id'   => (int) $group['traps_group_id'],
            'name' => $group['traps_group_name'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch trap groups',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
