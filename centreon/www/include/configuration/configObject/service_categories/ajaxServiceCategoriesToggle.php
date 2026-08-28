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

$scId   = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $scId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$helper->requireWriteAccess(60209);

// On top of the menu ACL, check at the resource level that the category is within
// the caller's scope: checking only the page would let a user toggle any service
// category by id (IDOR). Same ACL source as ajaxServiceCategoriesListing.php.
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if ($acl === null) {
        AjaxListingHelper::jsonError('Access denied', 403);
    }

    // "''" means no acl_resources_sc_relations row at all: categories are then
    // unrestricted for this user. Only scope the check when the ACL names ids.
    $scString = $acl->getServiceCategoriesString('ID');
    if ($scString !== "''") {
        $grantedIds = array_values(array_filter(array_map('intval', explode(',', $scString))));
        if (! in_array($scId, $grantedIds, true)) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }
}

// Consumed only once the caller is known to be allowed: validateCsrfToken()
// invalidates the token, so validating it before the ACL checks made a rejected
// request burn the page's token and break its next legitimate action.
$newToken = $helper->validateCsrfToken();

$activate = ($action === 's') ? '1' : '0';

try {
    // Fetch the name (also acts as the existence check) then flip the activation flag.
    $objName = $pearDB->fetchOne(
        <<<'SQL'
            SELECT sc_name FROM service_categories WHERE sc_id = :id
            SQL,
        QueryParameters::create([QueryParameter::int('id', $scId)])
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE service_categories SET sc_activate = :activate WHERE sc_id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $scId),
        ])
    );

    // 'servicecategories' (no underscore) is ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
    // the value the legacy bulk enable/disable path writes — the audit trail must
    // stay queryable under a single object type.
    $helper->logToggleAction('servicecategories', $scId, (string) $objName, $action === 's' ? 'enable' : 'disable');

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update service category activation',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
