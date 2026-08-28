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
$helper->requireReadAccess(60306);
$pearDB = $helper->getDb();
$params = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// Only contact templates belong to this listing; registered contacts have their own page.
$conditions = ["contact_register = '0'"];
$parameters = [];

if ($search !== '') {
    $conditions[] = 'contact_name LIKE :search';
    $parameters[] = QueryParameter::string('search', '%' . $search . '%');
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

$timeperiodQuery = <<<'SQL'
    SELECT tp_id, tp_name FROM timeperiod
    SQL;

$countQuery = <<<SQL
    SELECT COUNT(*) AS total
    FROM contact
    {$whereClause}
    SQL;

$dataQuery = <<<SQL
    SELECT
        contact_id,
        contact_name,
        contact_alias,
        timeperiod_tp_id,
        timeperiod_tp_id2,
        contact_activate
    FROM contact
    {$whereClause}
    ORDER BY contact_name
    LIMIT :offset, :limit
    SQL;

try {
    $timeperiods = [];
    foreach ($pearDB->fetchAllAssociative($timeperiodQuery) as $timeperiod) {
        $timeperiods[(int) $timeperiod['tp_id']] = $timeperiod['tp_name'];
    }

    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $contactTemplates = $pearDB->fetchAllAssociative(
        $dataQuery,
        QueryParameters::create([
            ...$parameters,
            QueryParameter::int('offset', $num * $limit),
            QueryParameter::int('limit', $limit),
        ])
    );

    $rows = [];
    foreach ($contactTemplates as $contactTemplate) {
        $rows[] = [
            'id'            => (int) $contactTemplate['contact_id'],
            'name'          => $contactTemplate['contact_name'],
            'alias'         => $contactTemplate['contact_alias'],
            'host_notif_tp' => $timeperiods[(int) $contactTemplate['timeperiod_tp_id']] ?? '',
            'svc_notif_tp'  => $timeperiods[(int) $contactTemplate['timeperiod_tp_id2']] ?? '',
            'activate'      => (int) $contactTemplate['contact_activate'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch contact templates',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
