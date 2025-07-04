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

if (! isset($centreon)) {
    exit();
}

$contactId = filter_var(
    $_GET['contact_id'] ?? $_POST['contact_id'] ?? 0,
    FILTER_VALIDATE_INT
);
$select = $_GET['select'] ?? $_POST['select'] ?? [];

$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

// Path to the configuration dir
$path = './include/monitoring/comments/';

// PHP functions
require_once './include/common/common-Func.php';
require_once './include/monitoring/comments/common-Func.php';
require_once './include/monitoring/external_cmd/functions.php';

switch ($o) {
    case 'ah':
        require_once $path . 'AddHostComment.php';
        break;
    case 'vh':
        require_once $path . 'listComment.php';
        break;
    case 'a':
        require_once $path . 'AddComment.php';
        break;
    case 'as':
        require_once $path . 'AddSvcComment.php';
        break;
    case 'ds':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! empty($select)) {
                foreach ($select as $key => $value) {
                    $res = explode(';', urldecode($key));
                    DeleteComment($res[0], [$res[1] . ';' . (int) $res[2] => 'on']);
                }
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listComment.php';
        break;
    case 'vs':
        require_once $path . 'listComment.php';
        break;
    default:
        require_once $path . 'listComment.php';
        break;
}
