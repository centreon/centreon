<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$resultat = array(
    "code" => 0,
    "msg" => 'ok',
);

$uniq_id = $_REQUEST['uniqId'];
foreach ($_FILES as $file) {
    $dir = dirname($file['tmp_name']);
    $file_dst = $dir . '/opentickets/' . $uniq_id . '__' . $file['name'];
    @mkdir($dir . '/opentickets', 0750);
    if (rename($file['tmp_name'], $file_dst)) {
        if (!isset($_SESSION['ot_upload_files'])) {
            $_SESSION['ot_upload_files'] = array();
        }
        if (!isset($_SESSION['ot_upload_files'][$uniq_id])) {
            $_SESSION['ot_upload_files'][$uniq_id] = array();
        }

        $_SESSION['ot_upload_files'][$uniq_id][$file_dst] = 1;
    }
}
