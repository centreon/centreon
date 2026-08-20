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
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

$whereClause = '';
$parameters  = [];

if ($search !== '') {
    $whereClause = 'WHERE (name LIKE :search OR alias LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM traps_vendor
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT id, name, alias
    FROM traps_vendor
    {$whereClause}
    ORDER BY name, alias
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $vendors = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($vendors as $vendor) {
        $rows[] = [
            'id'    => (int) $vendor['id'],
            'name'  => $vendor['name'],
            'alias' => $vendor['alias'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch trap manufacturers',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
