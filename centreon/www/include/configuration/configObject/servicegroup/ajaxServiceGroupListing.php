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

$helper  = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB  = $helper->getDb();
$params  = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

try {
    $conditions = [];
    $parameters = [];

    // ACL filtering
    if (! $helper->isAdmin()) {
        $acl = $helper->getAcl();
        $sgs = $acl->getServiceGroupAclConf(null, 'broker');

        if (empty($sgs)) {
            $helper->jsonResponse([], 0, $num, $limit);
        }

        $sgIds = array_keys($sgs);
        $placeholders = [];
        foreach ($sgIds as $index => $sgId) {
            $placeholder = ':sg_' . $index;
            $placeholders[] = $placeholder;
            $parameters[] = QueryParameter::int('sg_' . $index, $sgId);
        }
        $conditions[] = 'sg_id IN (' . implode(', ', $placeholders) . ')';
    }

    // Search filter
    if ($search !== '') {
        $conditions[] = '(sg_name LIKE :search OR sg_alias LIKE :search)';
        $parameters[] = QueryParameter::string('search', '%' . $search . '%');
    }

    $whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

    $countQuery = <<<SQL
        SELECT COUNT(*) AS total FROM servicegroup {$whereClause}
        SQL;
    $total = (int) $pearDB->fetchOne($countQuery, QueryParameters::create($parameters));

    $dataQuery = <<<SQL
        SELECT sg_id, sg_name, sg_alias, sg_activate
        FROM servicegroup
        {$whereClause}
        ORDER BY sg_name
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
        ) as $sg
    ) {
        $rows[] = [
            'id'       => (int) $sg['sg_id'],
            'name'     => $sg['sg_name'],
            'alias'    => $sg['sg_alias'],
            'activate' => (int) $sg['sg_activate'],
        ];
    }

    $helper->jsonResponse($rows, $total, $num, $limit);
} catch (Throwable $e) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX listing: failed to fetch service groups',
        ['exception' => $e]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}
