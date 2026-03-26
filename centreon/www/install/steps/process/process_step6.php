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
require_once __DIR__ . '/../functions.php';
require __DIR__ . '/../../../include/common/common-Func.php';

use CentreonLegacy\Core\Install\Step\Step6;

define('SQL_ERROR_CODE_ACCESS_DENIED', 1698);

$requiredParameters = [
    'db_configuration',
    'db_storage',
    'db_user',
    'db_password',
    'db_password_confirm',
];

$err = [
    'required' => [],
    'password' => true,
    'connection' => '',
    'use_vault' => false,
    'vault_error' => '',
];

$parameters = filter_input_array(INPUT_POST);

foreach ($parameters as $name => $value) {
    if (in_array($name, $requiredParameters) && trim($value) == '') {
        $err['required'][] = $name;
    }
}

if (array_key_exists('use_vault', $parameters)) {
    $err['use_vault'] = true;
}

if (! in_array('db_password', $err['required']) && ! in_array('db_password_confirm', $err['required'])
    && $parameters['db_password'] != $parameters['db_password_confirm']
) {
    $err['password'] = false;
}

try {
    if ($parameters['address'] == '') {
        $parameters['address'] = 'localhost';
    }
    if ($parameters['port'] == '') {
        $parameters['port'] = '3306';
    }
    if ($parameters['root_user'] == '') {
        $parameters['root_user'] = 'root';
    }
    $link = new PDO(
        'mysql:host=' . $parameters['address'] . ';port=' . $parameters['port'],
        $parameters['root_user'],
        $parameters['root_password']
    );
    checkMariaDBPrerequisite($link);
    $link = null;
} catch (Exception $e) {
    if ($e instanceof PDOException && (int) $e->getCode() === SQL_ERROR_CODE_ACCESS_DENIED) {
        $err['connection']
            = 'Please check the root database username and password. '
            . 'If the problem persists, check that you have properly '
            . '<a target="_blank" href="https://docs.centreon.com/docs/installation'
            . '/installation-of-a-central-server/using-packages/#secure-the-database">secured your DBMS</a>';
    } else {
        $err['connection'] = $e->getMessage();
    }
}

if ($err['required'] === [] && $err['password'] && trim($err['connection']) == '') {
    $step = new Step6($dependencyInjector);
    $step->setDatabaseConfiguration($parameters);
}

echo json_encode($err);
