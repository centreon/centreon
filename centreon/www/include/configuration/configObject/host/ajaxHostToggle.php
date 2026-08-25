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

$newToken = $helper->validateCsrfToken();

$helper->requireWriteAccess(60101);

$activate = ($action === 's') ? '1' : '0';

try {
    // Write access to page 60101 says the caller may toggle hosts, not which
    // ones, so the lookup carries the same resource ACL as the listing. Without
    // it a non-admin flips any host by posting its id, including hosts its own
    // listing never shows.
    $joins      = '';
    $parameters = [QueryParameter::int('id', $objId)];

    if (! $helper->isAdmin()) {
        $acl         = $helper->getAcl();
        $aclGroupIds = $acl !== null
            ? array_values(array_filter(array_map('intval', array_keys($acl->getAccessGroups()))))
            : [];

        if ($aclGroupIds === []) {
            AjaxListingHelper::jsonError('Object not found', 404);
        }

        $aclDbName  = $pearDB->getConnectionConfig()->getDatabaseNameRealTime();
        $aclIn      = AjaxListingHelper::buildIntInClause($aclGroupIds, 'acl_gid');
        $parameters = [...$parameters, ...$aclIn['parameters']];
        $joins      = " INNER JOIN `{$aclDbName}`.centreon_acl acl"
            . ' ON acl.host_id = h.host_id AND acl.service_id IS NULL'
            . " AND acl.group_id IN ({$aclIn['clause']}) ";
    }

    // Fetch the name (also acts as the existence check, real hosts only) then
    // flip the activation flag.
    $objName = $pearDB->fetchOne(
        <<<SQL
            SELECT h.host_name FROM host h {$joins}
            WHERE h.host_id = :id AND h.host_register = '1'
            SQL,
        QueryParameters::create($parameters)
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE host SET host_activate = :activate WHERE host_id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $objId),
        ])
    );

    // Flag the pollers as needing an export, as enableHostInDB()/disableHostInDB()
    // do: without it the change never reaches Export configuration and the host
    // keeps being monitored as it was.
    signalConfigurationChange('host', $objId, [], $action === 's');

    $helper->logToggleAction('host', $objId, (string) $objName, $action === 's' ? 'enable' : 'disable');

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update host activation',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
