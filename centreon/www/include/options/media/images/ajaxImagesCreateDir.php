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
$pearDB   = $helper->getDb(); // top-level: global for DB-Func helpers (insertDirectory, testDirectoryExistence)

$newToken = $helper->validateCsrfToken();
$helper->requireWriteAccess(50102);

$respondError = function (string $message, int $code = 400) use ($newToken): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message, 'centreon_token' => $newToken]);
    exit;
};

// Creating a folder is reserved to users allowed to see every folder
// (same restriction as uploading into a brand new directory)
$canCreateFolder = $helper->isAdmin() || ! empty($centreon->user->access->hasAccessToAllImageFolders);
if (! $canCreateFolder) {
    $respondError('You are not allowed to create a directory.', 403);
}

$name    = trim((string) ($_POST['name'] ?? ''));
$comment = (string) ($_POST['comment'] ?? '');

if ($name === '') {
    $respondError('A directory name is required.');
}

// Strict allow-list on the directory name (defends against path traversal such
// as ".." and keeps names display- and filesystem-safe)
if (! preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
    $respondError('Invalid directory name (allowed: letters, digits, "-" and "_").');
}

$oreon = $centreon;
require_once _CENTREON_PATH_ . '/www/include/options/media/images/DB-Func.php';

// DB-Func helpers work with paths relative to the web root
chdir(_CENTREON_PATH_ . '/www');

if (testDirectoryExistence($name)) {
    $respondError('A directory with this name already exists.', 409);
}

$dirId = insertDirectory($name, $comment);
if (! $dirId) {
    $respondError('The directory could not be created.', 409);
}

echo json_encode(['success' => true, 'dir_id' => (int) $dirId, 'centreon_token' => $newToken]);
exit;
