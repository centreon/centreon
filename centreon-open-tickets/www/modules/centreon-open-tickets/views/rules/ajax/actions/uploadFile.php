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

if (! isset($_SESSION['centreon'])) {
    $resultat = ['code' => 1, 'msg' => 'Unauthorized.'];

    return;
}

$uniq_id = $_REQUEST['uniqId'] ?? '';
if (! preg_match('/^[a-f0-9]{13}(\.[0-9]{8})?$/D', $uniq_id)) {
    $resultat = ['code' => 1, 'msg' => 'Invalid uniqId format.'];

    return;
}

$target_dir = sys_get_temp_dir() . '/opentickets';
// Second is_dir() handles the race where another request created it between the first check and mkdir().
if (! is_dir($target_dir) && ! mkdir($target_dir, 0750) && ! is_dir($target_dir)) {
    $resultat = ['code' => 1, 'msg' => 'Failed to create upload directory.'];

    return;
}

$real_target_dir = realpath($target_dir);

foreach ($_FILES as $file) {
    $safe_filename = basename($file['name']);
    if (
        $safe_filename === ''
        || $safe_filename === '.'
        || $safe_filename === '..'
        || preg_match('/[\x00-\x1f\x7f]/', $safe_filename)
        || strlen($safe_filename) > 200
    ) {
        $resultat = ['code' => 1, 'msg' => 'Invalid filename.'];

        return;
    }

    $file_dst = $target_dir . '/' . $uniq_id . '__' . $safe_filename;

    $real_dst_dir = realpath(dirname($file_dst));
    if ($real_target_dir === false || $real_dst_dir === false || $real_dst_dir !== $real_target_dir) {
        $resultat = ['code' => 1, 'msg' => 'Invalid file path.'];

        return;
    }

    if (! move_uploaded_file($file['tmp_name'], $file_dst)) {
        $resultat = ['code' => 1, 'msg' => 'Failed to save uploaded file.'];

        return;
    }

    if (! isset($_SESSION['ot_upload_files'])) {
        $_SESSION['ot_upload_files'] = [];
    }
    if (! isset($_SESSION['ot_upload_files'][$uniq_id])) {
        $_SESSION['ot_upload_files'][$uniq_id] = [];
    }

    $_SESSION['ot_upload_files'][$uniq_id][$file_dst] = 1;
}
