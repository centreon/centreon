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
use Core\ActionLog\Domain\Model\ActionLog;

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper = AjaxListingHelper::boot();

$objId       = null;
$action      = null;
$newToken    = null;
$auditName   = null;
$auditAction = null;

try {
    $helper->requireCentreon();
    $pearDB = $helper->getDb();

    $objId  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? null;

    if (! $objId || ! in_array($action, ['s', 'u'], true)) {
        AjaxListingHelper::jsonError('Invalid parameters', 400);
    }

    // The token is single-use, so every answer issued from here on carries its
    // replacement: without it the operator's next click dies on a stale token.
    $newToken = $helper->validateCsrfToken();

    $helper->requireWriteAccess(60806, ['centreon_token' => $newToken]);

    // The name feeds the audit log; the fetch doubles as the existence check.
    $objName = $pearDB->fetchOne(
        <<<'SQL'
            SELECT name FROM connector WHERE id = :id
            SQL,
        QueryParameters::create([QueryParameter::int('id', $objId)])
    );

    if ($objName === false) {
        AjaxListingHelper::jsonError('Object not found', 404, ['centreon_token' => $newToken]);
    }

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE connector SET enabled = :enabled, modified = :modified WHERE id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::int('enabled', $action === 's' ? 1 : 0),
            QueryParameter::int('modified', time()),
            QueryParameter::int('id', $objId),
        ])
    );

    // Answer before auditing: the toggle is already committed, so an audit
    // failure must not be reported to the operator as a failed toggle.
    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);

    $auditName   = (string) $objName;
    $auditAction = $action === 's' ? ActionLog::ACTION_TYPE_ENABLE : ActionLog::ACTION_TYPE_DISABLE;
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'AJAX toggle: failed to update connector activation',
        ['id' => $objId, 'action' => $action, 'exception' => $exception]
    );
    AjaxListingHelper::jsonError(
        'Internal error',
        500,
        $newToken === null ? [] : ['centreon_token' => $newToken]
    );
}

if ($auditName !== null && $auditAction !== null) {
    // Close the response first. A centstorage outage inside the audit path answers
    // with an HTML error page and exit(), which would otherwise be appended to the
    // JSON body above and turn a committed toggle into a client-side parse error.
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $helper->logToggleAction(ActionLog::OBJECT_TYPE_CONNECTOR, (int) $objId, $auditName, $auditAction);
}

exit;
