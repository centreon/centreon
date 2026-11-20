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

require_once realpath(__DIR__ . '/../../common/listing/AjaxListingHelper.php');

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();

$userId = filter_var($_POST['user'] ?? null, FILTER_VALIDATE_INT);
if (! $userId) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$newToken = $helper->validateCsrfToken();

// Disconnecting a user is an administrator-only action
if (! $helper->isAdmin()) {
    AjaxListingHelper::jsonError('Forbidden', 403);
}

// A user cannot disconnect their own session
if ($userId === (int) $centreon->user->get_id()) {
    AjaxListingHelper::jsonError('You cannot disconnect your own session', 400);
}

$stmt = $pearDB->prepare('DELETE FROM session WHERE user_id = :userId');
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
