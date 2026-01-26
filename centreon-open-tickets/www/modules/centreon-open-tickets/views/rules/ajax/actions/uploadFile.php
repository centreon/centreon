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

$resultat = ['code' => 0, 'msg' => 'ok'];

$uniq_id = $_REQUEST['uniqId'];
foreach ($_FILES as $file) {
    $dir = dirname($file['tmp_name']);
    $file_dst = $dir . '/opentickets/' . $uniq_id . '__' . $file['name'];
    @mkdir($dir . '/opentickets', 0750);
    if (rename($file['tmp_name'], $file_dst)) {
        if (! isset($_SESSION['ot_upload_files'])) {
            $_SESSION['ot_upload_files'] = [];
        }
        if (! isset($_SESSION['ot_upload_files'][$uniq_id])) {
            $_SESSION['ot_upload_files'][$uniq_id] = [];
        }

        $_SESSION['ot_upload_files'][$uniq_id][$file_dst] = 1;
    }
}
