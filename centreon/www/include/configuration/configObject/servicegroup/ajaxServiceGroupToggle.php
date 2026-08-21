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

$objId  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $objId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$helper->requireWriteAccess(60203);

// On top of the menu ACL, check at the resource level that the group is within
// the caller's scope: checking only the page would let a user toggle any service
// group by id (IDOR). Same ACL source as ajaxServiceGroupListing.php.
if (! $helper->isAdmin()) {
    $acl        = $helper->getAcl();
    $grantedIds = $acl === null
        ? []
        : array_map('intval', array_keys($acl->getServiceGroupAclConf(null, 'broker')));

    if (! in_array($objId, $grantedIds, true)) {
        AjaxListingHelper::jsonError('Access denied', 403);
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
            SELECT sg_name FROM servicegroup WHERE sg_id = :id
            SQL,
        QueryParameters::create([QueryParameter::int('id', $objId)])
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE servicegroup SET sg_activate = :activate WHERE sg_id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $objId),
        ])
    );

    // Flag the impacted pollers as needing an export, exactly like the legacy
    // enable/disableServiceGroupInDB() path: after the UPDATE, and telling it
    // whether the group should now be considered enabled (the disable case must
    // still reach the services of the group it just switched off).
    signalConfigurationChange('servicegroup', $objId, [], $action === 's');

    $helper->logToggleAction('servicegroup', $objId, (string) $objName, $action === 's' ? 'enable' : 'disable');

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update service group activation',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
