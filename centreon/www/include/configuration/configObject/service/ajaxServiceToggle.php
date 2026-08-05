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

$newToken = $helper->validateCsrfToken();

$activate = ($action === 's') ? '1' : '0';

try {
    // Write access is granted by either services page (by host or by hostgroup),
    // so requireWriteAccess() — which takes a single page id — cannot express it.
    // On top of the menu ACL, check at the resource level that the user actually
    // has access to this specific service: checking only the page would let a
    // user toggle any service by id (IDOR).
    if (! $helper->isAdmin()) {
        $acl = $helper->getAcl();
        if ($acl === null || ($acl->page(60201) !== 1 && $acl->page(60202) !== 1)) {
            AjaxListingHelper::jsonError('Write access denied', 403);
        }

        $aclGroupIds = array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))));
        if ($aclGroupIds === []) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }

        $aclDbName       = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
        $aclPlaceholders = [];
        $aclParameters   = [QueryParameter::int('sid', $objId)];
        foreach ($aclGroupIds as $index => $groupId) {
            $placeholder       = 'acl_gid' . $index;
            $aclPlaceholders[] = ':' . $placeholder;
            $aclParameters[]   = QueryParameter::int($placeholder, $groupId);
        }
        $aclIn = implode(', ', $aclPlaceholders);

        $isGranted = $pearDB->fetchOne(
            <<<SQL
                SELECT 1 FROM `{$aclDbName}`.centreon_acl
                WHERE service_id = :sid AND group_id IN ({$aclIn})
                LIMIT 1
                SQL,
            QueryParameters::create($aclParameters)
        );

        if ($isGranted === false) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }

    // Fetch the description (also acts as the existence check) then flip the
    // activation flag. service_register = '1' keeps templates out of reach.
    $objName = $pearDB->fetchOne(
        <<<'SQL'
            SELECT service_description FROM service WHERE service_id = :id AND service_register = '1'
            SQL,
        QueryParameters::create([QueryParameter::int('id', $objId)])
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE service SET service_activate = :activate WHERE service_id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $objId),
        ])
    );

    // Flag the impacted pollers as needing an export, exactly like the legacy
    // enable/disableServiceInDB() path: after the UPDATE, and telling it whether
    // the service should now be considered enabled (the disable case must still
    // reach the host of the service it just switched off).
    signalConfigurationChange('service', $objId, [], $action === 's');

    $helper->logToggleAction('service', $objId, (string) $objName, $action === 's' ? 'enable' : 'disable');

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update service activation',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
