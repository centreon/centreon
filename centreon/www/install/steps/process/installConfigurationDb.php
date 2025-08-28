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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once '../functions.php';

$return = ['id' => 'dbconf', 'result' => 1, 'msg' => ''];

$step = new CentreonLegacy\Core\Install\Step\Step6($dependencyInjector);
$parameters = $step->getDatabaseConfiguration();

try {
    $db = new PDO(
        'mysql:host=' . $parameters['address'] . ';port=' . $parameters['port'],
        $parameters['root_user'],
        $parameters['root_password']
    );
} catch (PDOException $e) {
    $return['msg'] = $e->getMessage();
    echo json_encode($return);

    exit;
}

// Check if MySQL innodb_file_perf_table is enabled
$innodb_file_per_table = getDatabaseVariable($db, 'innodb_file_per_table');
if (is_null($innodb_file_per_table) || strtolower($innodb_file_per_table) == 'off') {
    $return['msg']
        = _('Add innodb_file_per_table=1 in my.cnf file under the [mysqld] section and restart MySQL Server.');
    echo json_encode($return);

    exit;
}

// Check if MySQL open_files_limit parameter is higher than 32000
$open_files_limit = getDatabaseVariable($db, 'open_files_limit');
if (is_null($open_files_limit)) {
    $open_files_limit = 0;
}
if ($open_files_limit < 32000) {
    $return['msg'] = 'Add LimitNOFILE=32000 value in the service file '
        . '/etc/systemd/system/<database_service_name>.service.d/centreon.conf '
        . '(replace <database_service_name> by mysql, mysqld or mariadb depending on the systemd service name) '
        . 'and reload systemd : systemctl daemon-reload';
    echo json_encode($return);

    exit;
}

try {
    // Check if configuration database exists
    $statementShowDatabase = $db->prepare('SHOW DATABASES LIKE :dbConfiguration');
    $statementShowDatabase->bindValue(':dbConfiguration', $parameters['db_configuration'], PDO::PARAM_STR);
    $statementShowDatabase->execute();

    // If it doesn't exist, create it
    if ($result = $statementShowDatabase->fetch(PDO::FETCH_ASSOC) === false) {
        $db->exec('CREATE DATABASE `' . $parameters['db_configuration'] . '`');

        // Create table
        $db->exec('use `' . $parameters['db_configuration'] . '`');
        $result = splitQueries('../../createTables.sql', ';', $db, '../../tmp/createTables');
        if ('0' != $result) {
            $return['msg'] = $result;
            echo json_encode($return);

            exit;
        }
    } else {
        // If it exist, check if database is empty (no tables)
        $statement = $db->prepare(
            'SELECT COUNT(*) as tables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :dbConfiguration'
        );
        $statement->bindValue(':dbConfiguration', $parameters['db_configuration'], PDO::PARAM_STR);
        $statement->execute();
        // If it is not empty, throw an error
        if (($resultCount = $statement->fetch(PDO::FETCH_ASSOC)) && (int) $resultCount['tables'] > 0) {
            throw new Exception(
                sprintf('Your \'%s\' database is not empty, please remove all your tables or drop your database '
                    . 'then click on refresh to retry', $parameters['db_configuration'])
            );
        }
    }
    // Create table
    $db->exec('use `' . $parameters['db_configuration'] . '`');
    $result = splitQueries('../../createTables.sql', ';', $db, '../../tmp/createTables');
    if ('0' != $result) {
        $return['msg'] = $result;
        echo json_encode($return);

        exit;
    }
} catch (Exception $e) {
    if (! is_file('../../tmp/createTables')) {
        $return['msg'] = $e->getMessage();
        echo json_encode($return);

        exit;
    }
}

$return['result'] = 0;
echo json_encode($return);

exit;
