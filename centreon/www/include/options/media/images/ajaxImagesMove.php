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
$pearDB   = $helper->getDb();

$newToken = $helper->validateCsrfToken();
$helper->requireWriteAccess(50102);

$respondError = function (string $message, int $code = 400) use ($newToken): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message, 'centreon_token' => $newToken]);
    exit;
};

// Dependency injector (configuration_db) used by CentreonImageManager + getListDirectory ACL helper
require_once _CENTREON_PATH_ . '/bootstrap.php';
require_once _CENTREON_PATH_ . '/www/class/centreonImageManager.php';
$oreon = $centreon;
require_once _CENTREON_PATH_ . '/www/include/options/media/images/DB-Func.php';

$isCloudPlatform = function_exists('isCloudPlatform') ? isCloudPlatform() : false;

$imgId = filter_var($_POST['img_id'] ?? null, FILTER_VALIDATE_INT);
$dirId = filter_var($_POST['dir_id'] ?? null, FILTER_VALIDATE_INT);
if ($imgId === false || $imgId === null || $dirId === false || $dirId === null) {
    $respondError('Invalid parameters.', 400);
}

// Current image (name and comment are preserved; only the directory changes).
// The source directory is fetched too, so we can enforce ACL on it below.
$imgStmt = $pearDB->prepare(
    'SELECT i.img_name, i.img_comment, d.dir_name AS current_dir'
    . ' FROM view_img i'
    . ' INNER JOIN view_img_dir_relation r ON r.img_img_id = i.img_id'
    . ' INNER JOIN view_img_dir d ON d.dir_id = r.dir_dir_parent_id'
    . ' WHERE i.img_id = :imgId LIMIT 1'
);
$imgStmt->bindValue(':imgId', $imgId, PDO::PARAM_INT);
$imgStmt->execute();
$img = $imgStmt->fetch();
if ($img === false) {
    $respondError('Image not found.', 404);
}

// Target directory
$dirStmt = $pearDB->prepare('SELECT dir_name FROM view_img_dir WHERE dir_id = :dirId LIMIT 1');
$dirStmt->bindValue(':dirId', $dirId, PDO::PARAM_INT);
$dirStmt->execute();
$dir = $dirStmt->fetch();
if ($dir === false) {
    $respondError('Directory not found.', 404);
}
$directory = (string) $dir['dir_name'];

// Non-privileged users can only move an image between folders they are allowed
// to see: both the source (current) and the target directory must be visible.
$userCanSeeAllFolders = $helper->isAdmin() || ! empty($centreon->user->access->hasAccessToAllImageFolders);
if (! $userCanSeeAllFolders) {
    $allowedFolders = getListDirectory();
    if (! in_array((string) $img['current_dir'], $allowedFolders, true)
        || ! in_array($directory, $allowedFolders, true)
    ) {
        $respondError('You are not allowed to move this image.', 403);
    }
}

// CentreonImageManager works with paths relative to the web root
chdir(_CENTREON_PATH_ . '/www');

// No uploaded file: update() only moves the image to the target directory and
// keeps its name / comment, so the img_id (and every reference to it) is preserved
$manager = new CentreonImageManager(
    $dependencyInjector,
    [],
    './img/media/',
    $directory,
    (string) $img['img_comment'],
    $isCloudPlatform
);

if (! $manager->update($imgId, (string) $img['img_name'])) {
    $respondError('The image could not be moved.', 409);
}

echo json_encode(['success' => true, 'centreon_token' => $newToken]);
exit;
