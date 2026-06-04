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
$pearDB   = $helper->getDb(); // top-level: exposed as global for DB-Func.php functions

$newToken = $helper->validateCsrfToken();
$helper->requireWriteAccess(50102);

$selection = $_POST['select'] ?? [];
if (! is_array($selection) || $selection === []) {
    AjaxListingHelper::jsonError('No item selected', 400);
}

// Keep only well-formed selectors: "dirId" (directory) or "dirId-imgId" (image).
// DB-Func keys deletions on these selectors (1 part = directory, 2 parts = image).
$clean = [];
foreach (array_keys($selection) as $selector) {
    $parts = explode('-', (string) $selector);
    if (count($parts) < 1 || count($parts) > 2) {
        continue;
    }
    $valid = true;
    foreach ($parts as $part) {
        if (filter_var($part, FILTER_VALIDATE_INT) === false) {
            $valid = false;
            break;
        }
    }
    if ($valid) {
        $clean[(string) $selector] = 1;
    }
}

if ($clean === []) {
    AjaxListingHelper::jsonError('No valid selection', 400);
}

// DB-Func deletes media files using paths relative to the web root
chdir(_CENTREON_PATH_ . '/www');

// DB-Func guards on $oreon and relies on the global $pearDB
$oreon = $centreon;
require_once _CENTREON_PATH_ . '/www/include/options/media/images/DB-Func.php';

deleteMultImg($clean);
deleteMultDirectory($clean);

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
