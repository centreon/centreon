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

$return = ['id' => 'dbstorage', 'result' => 1, 'msg' => ''];

$factory = new CentreonLegacy\Core\Utils\Factory($dependencyInjector);
$utils = $factory->newUtils();
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

try {
    // Check if realtime database exists
    $statementShowDatabase = $db->prepare('SHOW DATABASES LIKE :dbStorage');
    $statementShowDatabase->bindValue(':dbStorage', $parameters['db_storage'], PDO::PARAM_STR);
    $statementShowDatabase->execute();

    // If it doesn't exist, create it
    if ($result = $statementShowDatabase->fetch(PDO::FETCH_ASSOC) === false) {
        $db->exec('CREATE DATABASE `' . $parameters['db_storage'] . '`');
    } else {
        // If it exist, check if database is empty (no tables)
        $statement = $db->prepare(
            'SELECT COUNT(*) as tables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :dbStorage'
        );
        $statement->bindValue(':dbStorage', $parameters['db_storage'], PDO::PARAM_STR);
        $statement->execute();
        if (($resultCount = $statement->fetch(PDO::FETCH_ASSOC)) && (int) $resultCount['tables'] > 0) {
            throw new Exception(
                sprintf('Your database \'%s\' is not empty, please remove all your tables or drop your database '
                    . 'then click on refresh to retry', $parameters['db_storage'])
            );
        }
    }
    $macros = array_merge(
        $step->getBaseConfiguration(),
        $step->getDatabaseConfiguration(),
        $step->getAdminConfiguration(),
        $step->getEngineConfiguration(),
        $step->getBrokerConfiguration()
    );
    $result = $db->query('use `' . $parameters['db_storage'] . '`');
    if (! $result) {
        throw new Exception('Cannot access to "' . $parameters['db_storage'] . '" database');
    }
    $result = splitQueries(
        '../../createTablesCentstorage.sql',
        ';',
        $db,
        '../../tmp/createTablesCentstorage',
        $macros
    );
    if ('0' != $result) {
        $return['msg'] = $result;
        echo json_encode($return);

        exit;
    }
    $result = splitQueries(
        '../../installBroker.sql',
        ';',
        $db,
        '../../tmp/installBroker',
        $macros
    );
    if ('0' != $result) {
        $return['msg'] = $result;
        echo json_encode($return);

        exit;
    }
} catch (Exception $e) {
    if (! is_file('../../tmp/createTablesCentstorage')) {
        $return['msg'] = $e->getMessage();
        echo json_encode($return);

        exit;
    }
}

$return['result'] = 0;
echo json_encode($return);

exit;
