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

$objId  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $objId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$helper->requireWriteAccess(60104);

// Authorization first: denying before the token is consumed means a read-only
// user cannot burn a valid token on every attempt.
$newToken = $helper->validateCsrfToken();

$activate = ($action === 's') ? '1' : '0';

try {
    // Write access to page 60104 says the caller may toggle categories, not which
    // ones, so the lookup carries the same resource ACL as the listing. Without it
    // a non-admin flips any category by posting its id, including ones its own
    // listing never shows.
    $conditions = 'hc_id = :id';
    $parameters = [QueryParameter::int('id', $objId)];

    if (! $helper->isAdmin()) {
        $acl   = $helper->getAcl();
        $hcIds = $acl !== null
            ? array_values(array_filter(array_map('intval', explode(',', $acl->getHostCategoriesString('ID')))))
            : [];

        if ($hcIds === []) {
            AjaxListingHelper::jsonError('Object not found', 404);
        }

        $hcIn        = AjaxListingHelper::buildIntInClause($hcIds, 'acl_hc');
        $parameters  = [...$parameters, ...$hcIn['parameters']];
        $conditions .= " AND hc_id IN ({$hcIn['clause']})";
    }

    // Fetch the name (also acts as the existence check) then flip the activation flag.
    $objName = $pearDB->fetchOne(
        <<<SQL
            SELECT hc_name FROM hostcategories WHERE {$conditions}
            SQL,
        QueryParameters::create($parameters)
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE hostcategories SET hc_activate = :activate WHERE hc_id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $objId),
        ])
    );

    $helper->logToggleAction('hostcategories', $objId, (string) $objName, $action === 's' ? 'enable' : 'disable');

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update host category activation',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
