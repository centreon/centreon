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

$helper->requireWriteAccess(60204);

$activate = ($action === 's') ? '1' : '0';

try {
    // Fetch the owning meta service (also acts as the existence check): a metric
    // relation has no name of its own, so the audit entry is filed against the
    // meta service it belongs to.
    $parent = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT ms.meta_id, ms.meta_name
            FROM meta_service_relation msr
            INNER JOIN meta_service ms ON ms.meta_id = msr.meta_id
            WHERE msr.msr_id = :id
            SQL,
        QueryParameters::create([QueryParameter::int('id', $objId)])
    );

    if ($parent === false || $parent === []) {
        AjaxListingHelper::jsonError('Object not found', 404);
    }

    // A metric relation carries no ACL of its own, so the scope check applies to
    // the meta service owning it: checking only the page would let a user toggle
    // any metric by id (IDOR). Same ACL source as ajaxMetaServiceListing.php.
    if (! $helper->isAdmin()) {
        $acl        = $helper->getAcl();
        $grantedIds = $acl !== null
            ? array_values(array_filter(array_map('intval', array_keys($acl->getMetaServices()))))
            : [];

        if (! in_array((int) $parent['meta_id'], $grantedIds, true)) {
            AjaxListingHelper::jsonError('Access denied', 403);
        }
    }

    // Consumed only once the caller is known to be allowed: validateCsrfToken()
    // invalidates the token, so validating it before the ACL checks made a
    // rejected request burn the page's token and break its next legitimate action.
    $newToken = $helper->validateCsrfToken();

    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE meta_service_relation SET activate = :activate WHERE msr_id = :id
            SQL,
        QueryParameters::create([
            QueryParameter::string('activate', $activate),
            QueryParameter::int('id', $objId),
        ])
    );

    // 'meta' is ActionLog::OBJECT_TYPE_META, the value the legacy meta service
    // add/modify path writes — there is no separate type for a metric relation.
    $helper->logToggleAction(
        'meta',
        (int) $parent['meta_id'],
        (string) $parent['meta_name'],
        $action === 's' ? 'enable' : 'disable'
    );

    echo json_encode(['success' => true, 'centreon_token' => $newToken], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error('AJAX toggle: failed to update meta metric activation', ['exception' => $exception]);
    AjaxListingHelper::jsonError('Internal error', 500);
}

exit;
