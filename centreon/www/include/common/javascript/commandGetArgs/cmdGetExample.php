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

// return argument for specific command in txt format
// use by ajax

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';

function myDecodeService($arg)
{
    $arg = str_replace('#BR#', '\\n', $arg ?? '');
    $arg = str_replace('#T#', '\\t', $arg);
    $arg = str_replace('#R#', '\\r', $arg);
    $arg = str_replace('#S#', '/', $arg);
    $arg = str_replace('#BS#', '\\', $arg);

    return html_entity_decode($arg, ENT_QUOTES, 'UTF-8');
}

header('Content-type: text/html; charset=utf-8');

$pearDB = new CentreonDB();

if (isset($_POST['index'])) {
    if (false === is_numeric($_POST['index'])) {
        header('HTTP/1.1 406 Not Acceptable');

        exit();
    }

    $statement = $pearDB->prepare(
        'SELECT `command_example` FROM `command` WHERE `command_id` = :command_id'
    );
    $statement->bindValue(':command_id', (int) $_POST['index'], PDO::PARAM_INT);
    $statement->execute();
    while ($arg = $statement->fetch(PDO::FETCH_ASSOC)) {
        echo myDecodeService($arg['command_example']);
    }
    unset($arg, $statement);
    $pearDB = null;
}
