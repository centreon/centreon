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

require_once realpath(__DIR__ . '/../..') . '/common/listing/AjaxListingHelper.php';

/** Topology page id of Administration > Logs. */
const CHANGELOG_PAGE_ID = 508;

$helper = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();

// CentreonLogAction reads $pearDB / $pearDBO as globals. This file is the entry
// point, so its top-level scope is the global scope.
$pearDB = $helper->getDb();
$pearDBO = new CentreonDB('centstorage');

// ACL: require at least read access on the changelog page.
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if (! $acl || $acl->page(CHANGELOG_PAGE_ID) === 0) {
        AjaxListingHelper::jsonError('Access denied', 403, 'access_denied');
    }
}

$actionLogId = filter_var($_GET['action_log_id'] ?? null, FILTER_VALIDATE_INT);
if ($actionLogId === false || $actionLogId <= 0) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

require_once _CENTREON_PATH_ . '/www/class/centreonLogAction.class.php';
$logAction = $centreon->CentreonLogAction ?? null;
if ($logAction === null) {
    // An incomplete session object is an internal error, not an authorization
    // decision: a 403 would tell the client to reload, which cannot help.
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX changelog detail: CentreonLogAction missing from the session object'
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

try {
    $action = $pearDBO->fetchAssociative(
        <<<'SQL'
            SELECT object_id,
                object_type,
                action_type
            FROM log_action
            WHERE action_log_id = :action_log_id
            SQL,
        QueryParameters::create([
            QueryParameter::int('action_log_id', $actionLogId),
        ])
    );

    if ($action === false) {
        AjaxListingHelper::jsonError('Not found', 404);
    }

    // The before/after values are computed by CentreonLogAction, which replays
    // the whole history of the object to know the previous value of each field
    // (and masks passwords / macro passwords on the way). Recomputing it here
    // would give a second, subtly different diff for the same event.
    $modifications = $logAction->listModification(
        (int) $action['object_id'],
        (string) $action['object_type']
    );
} catch (Throwable $e) {
    Logger::create(LogChannelEnum::WEB)->error(
        sprintf('AJAX changelog detail: could not read the modifications of action #%d', $actionLogId),
        ['exception' => $e]
    );
    AjaxListingHelper::jsonError('Internal error', 500);
}

$diff = [];
foreach ($modifications as $modification) {
    if ((int) $modification['action_log_id'] !== $actionLogId) {
        continue;
    }

    $diff[] = [
        'field' => $modification['field_name'],
        'before' => $modification['field_value_before'],
        'after' => $modification['field_value_after'],
    ];
}

try {
    // JSON_INVALID_UTF8_SUBSTITUTE: a single non-UTF-8 byte in a logged value
    // would otherwise make the whole diff fail to encode.
    echo json_encode(
        [
            'action_type' => $action['action_type'],
            'diff' => $diff,
        ],
        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $e) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX changelog detail: failed to encode response',
        ['exception' => $e]
    );
    AjaxListingHelper::jsonError('Encoding error', 500);
}

exit;
