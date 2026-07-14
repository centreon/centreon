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

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();

$newToken = $helper->validateCsrfToken();
$helper->requireWriteAccess(50102);

// Synchronizing the media library from disk (create dirs/images, prune dead
// rows) is reserved to users allowed to see every folder, like folder creation
$canSync = $helper->isAdmin() || ! empty($centreon->user->access->hasAccessToAllImageFolders);
if (! $canSync) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You are not allowed to synchronize the media library.', 'centreon_token' => $newToken]);
    exit;
}

require_once __DIR__ . '/mediaSyncWorker.php';

// The worker uses paths relative to the web root
chdir(_CENTREON_PATH_ . '/www');

$stats = runMediaSync();

echo json_encode(['success' => true, 'stats' => $stats, 'centreon_token' => $newToken]);
exit;
