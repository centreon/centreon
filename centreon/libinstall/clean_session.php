#!@PHP_BIN@
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

// Error Level
error_reporting(E_ERROR | E_PARSE);

function usage($command)
{
    echo $command . " centreon_etc_path\n";
    echo "\tcentreon_etc_path\tThe path to Centreon configuration default (/etc/centreon)\n";
}

if (count($argv) != 2) {
    fwrite(STDERR, "Incorrect number of arguments\n");
    usage($argv[0]);

    exit(1);
}

$centreon_etc = realpath($argv[1]);

if (! file_exists($centreon_etc . '/centreon.conf.php')) {
    fwrite(STDERR, "Centreon configuration file doesn't exists\n");
    usage($argv[0]);

    exit(1);
}

require_once $centreon_etc . '/centreon.conf.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';

$dbconn = new CentreonDB();
try {
    $queryCleanSession = 'DELETE FROM session';
} catch (PDOException $e) {
    fwrite(STDERR, "Error in purge sessions\n");

    exit(1);
}

exit(0);
