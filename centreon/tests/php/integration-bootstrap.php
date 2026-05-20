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

if (! defined('INTEGRATION_BOOTSTRAP') || INTEGRATION_BOOTSTRAP !== true) {
    return;
}

$projectDir = dirname(__DIR__, 2);

$rootUser = $_ENV['rootUser'] ?? 'root';
$rootPassword = $_ENV['rootPassword'] ?? $_ENV['password'];

$rootPdo = new PDO(
    sprintf('mysql:host=%s;port=%s', $_ENV['hostCentreon'], $_ENV['port']),
    $rootUser,
    $rootPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$rootPdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $_ENV['db']));
$rootPdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $_ENV['dbcstg']));
$rootPdo->exec(sprintf(
    "GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'",
    $_ENV['db'],
    $_ENV['user']
));
$rootPdo->exec(sprintf(
    "GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'",
    $_ENV['dbcstg'],
    $_ENV['user']
));
unset($rootPdo);

$centreonPdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s', $_ENV['hostCentreon'], $_ENV['port'], $_ENV['db']),
    $_ENV['user'],
    $_ENV['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$censtoragePdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s', $_ENV['hostCentstorage'], $_ENV['port'], $_ENV['dbcstg']),
    $_ENV['user'],
    $_ENV['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$centreonTables = $centreonPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($centreonTables === []) {
    $centreonPdo->exec(file_get_contents($projectDir . '/www/install/createTables.sql'));
    $centreonPdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $centreonPdo->exec(file_get_contents($projectDir . '/www/install/insertBaseConf.sql'));
    $centreonPdo->exec(file_get_contents($projectDir . '/www/install/insertTopology.sql'));
    $centreonPdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

$censtorageTables = $censtoragePdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($censtorageTables === []) {
    $censtoragePdo->exec(file_get_contents($projectDir . '/www/install/createTablesCentstorage.sql'));
    $censtoragePdo->exec(file_get_contents($projectDir . '/www/install/installBroker.sql'));
}

unset($centreonPdo, $censtoragePdo);
