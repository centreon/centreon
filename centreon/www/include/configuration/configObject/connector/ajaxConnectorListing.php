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
// Connectors carry no per-object ACL, so page access is the only thing standing
// between an authenticated user and the whole list.
$helper->requireReadAccess(60806);
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// Command lines run long; the listing shows a truncated value and the form
// holds the full one.
$commandLineMaxLength = 70;

$conditions = [];
$parameters = [];

if ($search !== '') {
    $conditions[] = '(name LIKE :search OR description LIKE :search OR command_line LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

$whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM connector
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT id, name, description, command_line, enabled
    FROM connector
    {$whereClause}
    ORDER BY name
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $connectors = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($connectors as $connector) {
        $commandLine = $connector['command_line'] ?? '';
        if (mb_strlen($commandLine) > $commandLineMaxLength) {
            $commandLine = mb_substr($commandLine, 0, $commandLineMaxLength) . '...';
        }

        $rows[] = [
            'id'           => (int) $connector['id'],
            'name'         => $connector['name'],
            'description'  => $connector['description'] ?? '',
            'command_line' => $commandLine,
            'activate'     => (int) $connector['enabled'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch connectors',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
