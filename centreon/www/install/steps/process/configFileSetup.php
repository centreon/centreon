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
require_once __DIR__ . '/../../functions.php';

$return = [
    'id' => 'configfile',
    'result' => 1,
    'msg' => '',
];

$step = new CentreonLegacy\Core\Install\Step\Step6($dependencyInjector);
$parameters = $step->getDatabaseConfiguration();
$configuration = $step->getBaseConfiguration();
$engine = $step->getEngineConfiguration();
$gorgonePassword = generatePassword();

$host = $parameters['address'] ?: 'localhost';

// escape double quotes and backslashes
$needle = ['\\', '"'];
$escape = ['\\\\', '\"'];
$password = str_replace($needle, $escape, $parameters['db_password']);

$macroReplacements = [
    '--ADDRESS--' => $host,
    '--DBUSER--' => $parameters['db_user'],
    '--DBPASS--' => $password,
    '--CONFDB--' => $parameters['db_configuration'],
    '--STORAGEDB--' => $parameters['db_storage'],
    '--CENTREONDIR--' => $configuration['centreon_dir'],
    '--CENTREON_CACHEDIR--' => $configuration['centreon_cachedir'],
    '--DBPORT--' => $parameters['port'],
    '--INSTANCEMODE--' => 'central',
    '--CENTREON_VARLIB--' => $configuration['centreon_varlib'],
    // specific additional mandatory parameters used by Gorgone.d in a full ZMQ mode
    '--CENTREON_SPOOL--' => '/var/spool/centreon',
    '--HTTPSERVERADDRESS--' => '0.0.0.0',
    '--HTTPSERVERPORT--' => '8085',
    '--SSLMODE--' => 'false',
    '--CENTREON_TRAPDIR--' => '/etc/snmp/centreon_traps',
    '--GORGONE_VARLIB--' => '/var/lib/centreon-gorgone',
    '--ENGINE_COMMAND--' => $engine['monitoring_var_lib'] . '/rw/centengine.cmd',
    '@GORGONE_USER@' => 'centreon-gorgone',
    '@GORGONE_PASSWORD@' => $gorgonePassword,
];

$centreonEtcPath = rtrim($configuration['centreon_etc'], '/');

/**
 * centreon.conf.php
 */
$centreonConfFile = $centreonEtcPath . '/centreon.conf.php';
$contents = file_get_contents('../../var/configFileTemplate');
$contents = str_replace(array_keys($macroReplacements), array_values($macroReplacements), $contents);
file_put_contents($centreonConfFile, $contents);
chmod($centreonConfFile, 0640);

/**
 * conf.pm
 */
$centreonConfPmFile = $centreonEtcPath . '/conf.pm';
$contents = file_get_contents('../../var/configFilePmTemplate');
$contents = str_replace(array_keys($macroReplacements), array_values($macroReplacements), $contents);
file_put_contents($centreonConfPmFile, $contents);

/**
 * Database configuration file
 */
$gorgoneDatabaseFile = $centreonEtcPath . '/config.d/10-database.yaml';
$contents = file_get_contents('../../var/databaseTemplate.yaml');
$contents = str_replace(array_keys($macroReplacements), array_values($macroReplacements), $contents);
$oldMask = umask(0137);
file_put_contents($gorgoneDatabaseFile, $contents);
umask($oldMask);

/**
 * Gorgone API configuration file
 */
$apiConfigurationFile = $centreonEtcPath . '/../centreon-gorgone/config.d/31-centreon-api.yaml';
if (file_exists($apiConfigurationFile) && is_writable($apiConfigurationFile)) {
    file_put_contents(
        $apiConfigurationFile,
        str_replace(
            array_keys($macroReplacements),
            array_values($macroReplacements),
            file_get_contents($apiConfigurationFile)
        ),
    );
}

/**
 * Gorgone daemon configuration file for a central
 */
$gorgoneCoreFileForCentral = $centreonEtcPath . '/../centreon-gorgone/config.d/40-gorgoned.yaml';
if (is_writable(dirname($gorgoneCoreFileForCentral))) {
    $contents = file_get_contents('../../var/gorgone/gorgoneCentralTemplate.yaml');
    $contents = str_replace(array_keys($macroReplacements), array_values($macroReplacements), $contents);
    file_put_contents($gorgoneCoreFileForCentral, $contents);
}

$return['result'] = 0;
echo json_encode($return);

exit;
