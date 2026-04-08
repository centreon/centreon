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

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/config/bootstrap.php';

use Doctrine\DBAL\DriverManager;

$projectDir = dirname(__DIR__, 2);

$centreonConnection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => $_ENV['hostCentreon'],
    'port' => (int) $_ENV['port'],
    'user' => $_ENV['user'],
    'password' => $_ENV['password'],
    'dbname' => $_ENV['db'],
]);

$centstorageConnection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => $_ENV['hostCentstorage'],
    'port' => (int) $_ENV['port'],
    'user' => $_ENV['user'],
    'password' => $_ENV['password'],
    'dbname' => $_ENV['dbcstg'],
]);

$centreonTables = $centreonConnection->executeQuery('SHOW TABLES')->fetchFirstColumn();
if ($centreonTables === []) {
    $centreonConnection->executeStatement(file_get_contents($projectDir . '/www/install/createTables.sql'));
    $centreonConnection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    $centreonConnection->executeStatement(file_get_contents($projectDir . '/www/install/insertBaseConf.sql'));
    $centreonConnection->executeStatement(file_get_contents($projectDir . '/www/install/insertTopology.sql'));
    $centreonConnection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
}

$censtorageTables = $centstorageConnection->executeQuery('SHOW TABLES')->fetchFirstColumn();
if ($censtorageTables === []) {
    $centstorageConnection->executeStatement(file_get_contents($projectDir . '/www/install/createTablesCentstorage.sql'));
    $centstorageConnection->executeStatement(file_get_contents($projectDir . '/www/install/installBroker.sql'));
}

$centreonConnection->close();
$centstorageConnection->close();
