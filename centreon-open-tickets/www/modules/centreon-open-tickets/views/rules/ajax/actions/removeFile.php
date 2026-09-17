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

$uniq_id = $get_information['uniqId'] ?? '';
$filename = basename($get_information['filename'] ?? '');

if (! preg_match('/^[a-f0-9]{13}(\.[0-9]{8})?$/D', $uniq_id) || $filename === '') {
    $resultat = ['code' => 1, 'msg' => 'Invalid parameters.'];

    return;
}

$target_dir = sys_get_temp_dir() . '/opentickets';
$file_path = $target_dir . '/' . $uniq_id . '__' . $filename;

$real_target_dir = realpath($target_dir);
$real_file_path = realpath($file_path);
if (
    $real_target_dir === false
    || $real_file_path === false
    || ! str_starts_with($real_file_path, $real_target_dir . '/')
) {
    $resultat = ['code' => 1, 'msg' => 'File not found.'];

    return;
}

if (! isset($_SESSION['ot_upload_files'][$uniq_id][$real_file_path])
    && ! isset($_SESSION['ot_upload_files'][$uniq_id][$file_path])
) {
    $resultat = ['code' => 1, 'msg' => 'Unauthorized file access.'];

    return;
}

if (! unlink($real_file_path)) {
    $resultat = ['code' => 1, 'msg' => 'Failed to delete file.'];

    return;
}

unset($_SESSION['ot_upload_files'][$uniq_id][$real_file_path], $_SESSION['ot_upload_files'][$uniq_id][$file_path]);

if (empty($_SESSION['ot_upload_files'][$uniq_id])) {
    unset($_SESSION['ot_upload_files'][$uniq_id]);
}
