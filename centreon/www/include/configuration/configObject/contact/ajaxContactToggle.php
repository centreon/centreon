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

if ($objId === (int) $centreon->user->get_id()) {
    AjaxListingHelper::jsonError('Cannot toggle your own account', 403, $newToken);
}

$helper->requireWriteAccess(60301, $newToken);

// ACL: at the resource level, a non-admin user may only toggle contacts covered
// by their access groups. Page-level access alone would allow toggling any
// contact by id (IDOR).
if (! $helper->isAdmin()) {
    try {
        $contactAcl = $helper->getAcl()->getContactAclConf(
            ['fields' => ['contact_id'], 'keys' => ['contact_id']]
        );
    } catch (Throwable $exception) {
        Logger::create(LogChannelEnum::WEB)->error(
            'AJAX toggle: failed to resolve the contact ACL scope',
            ['exception' => $exception]
        );
        AjaxListingHelper::jsonError('Internal error', 500, $newToken);
    }
    if (! isset($contactAcl[$objId])) {
        AjaxListingHelper::jsonError('Access denied', 403, $newToken);
    }
}

$activate = ($action === 's') ? '1' : '0';

try {
    // Fetch the name (also acts as the existence check) then flip the activation flag.
    $objName = $pearDB->fetchOne(
        <<<'SQL'
            SELECT contact_name FROM contact WHERE contact_id = :id AND contact_register = '1'
            SQL,
        QueryParameters::create([QueryParameter::int('id', $objId)])
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404, $newToken);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE contact SET contact_activate = :activate
            WHERE contact_id = :id AND contact_register = '1'
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $objId),
        ])
    );

    $helper->logToggleAction('contact', $objId, (string) $objName, $action === 's' ? 'enable' : 'disable');

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update contact activation',
        ['exception' => $exception]
    );
    AjaxListingHelper::jsonError('Internal error', 500, $newToken);
}

exit;
