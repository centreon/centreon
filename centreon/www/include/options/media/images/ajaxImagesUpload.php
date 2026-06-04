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
$pearDB   = $helper->getDb(); // top-level: global for DB-Func helpers (isCorrectMIMEType, getListDirectory)

$newToken = $helper->validateCsrfToken();
$helper->requireWriteAccess(50102);

// Error responses still carry a fresh token so the client can keep uploading the queue
$respondError = function (string $message, int $code = 400) use ($newToken): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message, 'centreon_token' => $newToken]);
    exit;
};

// Dependency injector (configuration_db) used by CentreonImageManager
require_once _CENTREON_PATH_ . '/bootstrap.php';
require_once _CENTREON_PATH_ . '/www/class/centreonImageManager.php';
$oreon = $centreon;
require_once _CENTREON_PATH_ . '/www/include/options/media/images/DB-Func.php';

$isCloudPlatform = function_exists('isCloudPlatform') ? isCloudPlatform() : false;

$directory = trim((string) ($_POST['directory'] ?? ''));
$comment   = (string) ($_POST['comment'] ?? '');
$overwrite = ! empty($_POST['overwrite']);

if ($directory === '') {
    $respondError('A target directory is required.');
}

// Strict allow-list on the directory name (defends against path traversal such
// as ".." which secureName() does not strip, and keeps names display-safe)
if (! preg_match('/^[A-Za-z0-9_-]+$/', $directory)) {
    $respondError('Invalid directory name.', 400);
}

// Non-privileged users can only upload into an existing folder they can access
$userCanSeeAllFolders = $helper->isAdmin() || ! empty($centreon->user->access->hasAccessToAllImageFolders);
if (! $userCanSeeAllFolders && ! in_array($directory, getListDirectory(), true)) {
    $respondError('You are not allowed to use this directory.', 403);
}

if (! isset($_FILES['filename']) || ($_FILES['filename']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $respondError('Upload failed.');
}

// Hard cap of 2 MB per file, enforced on every platform (on-premise would
// otherwise allow up to 5 MB through CentreonImageManager)
if ((int) ($_FILES['filename']['size'] ?? 0) > 2000000) {
    $respondError('File too large (maximum 2 MB).', 413);
}

$fileName  = (string) $_FILES['filename']['name'];
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'], true)) {
    $respondError('Unsupported file format.', 415);
}

// Deep MIME validation (also sanitizes SVG content in place)
if (! isCorrectMIMEType($_FILES['filename'])) {
    $respondError('Invalid image file.', 415);
}

// CentreonImageManager works with paths relative to the web root
chdir(_CENTREON_PATH_ . '/www');

$uploader = new CentreonImageManager(
    $dependencyInjector,
    $_FILES,
    './img/media/',
    $directory,
    $comment,
    $isCloudPlatform
);

// Resolve the stored file name the way CentreonImageManager will (mirrors
// centreonFileManager::secureName(); the directory is already restricted to
// [A-Za-z0-9_-] above so it needs no further sanitizing)
$storedName = uploadSecureName(pathinfo($fileName, PATHINFO_FILENAME)) . '.' . $extension;
$targetPath = './img/media/' . $directory . '/' . $storedName;

// Is there already an image with that name in this directory?
$lookup = $pearDB->prepare(
    'SELECT i.img_id FROM view_img i'
    . ' INNER JOIN view_img_dir_relation r ON r.img_img_id = i.img_id'
    . ' INNER JOIN view_img_dir d ON d.dir_id = r.dir_dir_parent_id'
    . ' WHERE d.dir_name = :dir AND i.img_path = :path LIMIT 1'
);
$lookup->bindValue(':dir', $directory, PDO::PARAM_STR);
$lookup->bindValue(':path', $storedName, PDO::PARAM_STR);
$lookup->execute();
$existingId = $lookup->fetchColumn();
$alreadyExists = ($existingId !== false) || file_exists($targetPath);

// Not overwriting: let the client ask the user what to do
if ($alreadyExists && ! $overwrite) {
    echo json_encode(['success' => false, 'exists' => true, 'name' => $fileName, 'centreon_token' => $newToken]);
    exit;
}

// Overwrite an existing, DB-tracked image in place: keeps the same img_id so
// every host/service/etc. that references it keeps its icon
if ($overwrite && $existingId !== false) {
    if (! $uploader->update((int) $existingId, pathinfo($fileName, PATHINFO_FILENAME))) {
        $respondError('The image could not be replaced.', 409);
    }
    echo json_encode(['success' => true, 'name' => $fileName, 'centreon_token' => $newToken]);
    exit;
}

// Overwrite of an orphan file (present on disk but with no DB row)
if ($overwrite && $existingId === false && file_exists($targetPath)) {
    @unlink($targetPath);
}

$result = $uploader->upload();
if ($result === false || $result === null) {
    $respondError('The image could not be saved (it may already exist or be too large).', 409);
}

echo json_encode(['success' => true, 'name' => $fileName, 'centreon_token' => $newToken]);
exit;

/**
 * Mirror of centreonFileManager::secureName(): strips accents, spaces, slashes
 * and quotes so we can predict the on-disk / stored image name.
 */
function uploadSecureName(string $text): string
{
    $map = [
        '/[áàâãªä]/u' => 'a', '/[ÁÀÂÃÄ]/u' => 'A',
        '/[ÍÌÎÏ]/u' => 'I', '/[íìîï]/u' => 'i',
        '/[éèêë]/u' => 'e', '/[ÉÈÊË]/u' => 'E',
        '/[óòôõºö]/u' => 'o', '/[ÓÒÔÕÖ]/u' => 'O',
        '/[úùûü]/u' => 'u', '/[ÚÙÛÜ]/u' => 'U',
        '/ç/' => 'c', '/Ç/' => 'C', '/ñ/' => 'n', '/Ñ/' => 'N',
        '/–/' => '-', '/[“”«»„"’‘‹›‚]/u' => '',
        '/ /' => '', '/\//' => '', '/\'/' => '',
    ];

    return preg_replace(array_keys($map), array_values($map), $text);
}
