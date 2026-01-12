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

if (! isset($oreon)) {
    exit();
}
const IMAGE_ADD = 'a';
const IMAGE_WATCH = 'w';
const IMAGE_MODIFY = 'ci';
const IMAGE_MODIFY_DIRECTORY = 'cd';
const IMAGE_MOVE = 'm';
const IMAGE_DELETE = 'd';
const IMAGE_SYNC_DIR = 'sd';

$imageId = filter_var(
    $_GET['img_id'] ?? $_POST['img_id'] ?? null,
    FILTER_VALIDATE_INT
);

$directoryId = filter_var(
    $_GET['dir_id'] ?? $_POST['dir_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Path to the cities dir
$path = './include/options/media/images/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

switch ($o) {
    case IMAGE_MODIFY:
    case IMAGE_ADD:
        require_once $path . 'formImg.php';
        break;
    case IMAGE_WATCH:
        if (is_int($imageId)) {
            require_once $path . 'formImg.php';
        }
        break;
    case IMAGE_MOVE:
    case IMAGE_MODIFY_DIRECTORY:
        require_once $path . 'formDirectory.php';
        break;
    case IMAGE_DELETE:
        // If one data are not correctly typed in array, it will be set to false
        $selectIds = filter_var_array(
            $_GET['select'] ?? $_POST['select'] ?? [],
            FILTER_VALIDATE_INT
        );
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds)) {
                deleteMultImg($selectIds);
                deleteMultDirectory($selectIds);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listImg.php';
        break;
    case IMAGE_SYNC_DIR:
        require_once $path . 'syncDir.php';
        break;
    default:
        require_once $path . 'listImg.php';
        break;
}
