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

// The status filter is a 1-based UI index (see listTraps.php), the traps_status
// column stores the Centreon status enum: -1 Pending, 0 OK, 1 Warning,
// 2 Critical, 3 Unknown.
$statusEnum   = [-1 => 'Pending', 0 => 'OK', 1 => 'Warning', 2 => 'Critical', 3 => 'Unknown'];

// Maximum OID length displayed in the listing, longer values are ellipsized.
$oidDisplayLength = 40;
$statusFilter = filter_var($_GET['status'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$vendorFilter = filter_var($_GET['vendor'] ?? null, FILTER_VALIDATE_INT) ?: 0;

$conditions = [];
$parameters = [];

if ($search !== '') {
    $conditions[] = '(t.traps_oid LIKE :search OR t.traps_name LIKE :search OR v.alias LIKE :search)';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

if ($statusFilter > 0) {
    $conditions[] = 't.traps_status = :status';
    $parameters[] = QueryParameter::int('status', $statusFilter === 5 ? -1 : $statusFilter - 1);
}

if ($vendorFilter > 0) {
    $conditions[] = 't.manufacturer_id = :vendor';
    $parameters[] = QueryParameter::int('vendor', $vendorFilter);
}

$whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM traps t
    LEFT JOIN traps_vendor v ON v.id = t.manufacturer_id
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT
        t.traps_id,
        t.traps_name,
        t.traps_oid,
        t.traps_status,
        t.traps_args,
        v.alias AS vendor_alias
    FROM traps t
    LEFT JOIN traps_vendor v ON v.id = t.manufacturer_id
    {$whereClause}
    ORDER BY t.manufacturer_id, t.traps_name
    LIMIT :offset, :limit
    SQL;

try {
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $traps = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($traps as $trap) {
        $oid = (string) $trap['traps_oid'];
        if (mb_strlen($oid) > $oidDisplayLength) {
            $oid = mb_substr($oid, 0, $oidDisplayLength) . '...';
        }

        $rows[] = [
            'id'          => (int) $trap['traps_id'],
            'name'        => $trap['traps_name'],
            'oid'         => $oid,
            'status'      => $statusEnum[(int) $trap['traps_status']] ?? '',
            'status_code' => (int) $trap['traps_status'],
            'vendor'      => $trap['vendor_alias'] ?? '',
            'output'      => $trap['traps_args'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch SNMP traps',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
