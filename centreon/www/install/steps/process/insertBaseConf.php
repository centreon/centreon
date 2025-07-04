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

use CentreonModule\ServiceProvider;

$return = ['id' => 'baseconf', 'result' => 1, 'msg' => ''];

$factory = new CentreonLegacy\Core\Utils\Factory($dependencyInjector);
$utils = $factory->newUtils();
$step = new CentreonLegacy\Core\Install\Step\Step6($dependencyInjector);
$parameters = $step->getDatabaseConfiguration();

try {
    $link = new PDO(
        'mysql:host=' . $parameters['address'] . ';port=' . $parameters['port'],
        $parameters['root_user'],
        $parameters['root_password']
    );
} catch (PDOException $e) {
    $return['msg'] = $e->getMessage();
    echo json_encode($return);

    exit;
}

/**
 * Create tables
 */
try {
    $result = $link->query('use `' . $parameters['db_configuration'] . '`');
    if (! $result) {
        throw new Exception('Cannot access to "' . $parameters['db_configuration'] . '" database');
    }

    $macros = array_merge(
        $step->getBaseConfiguration(),
        $step->getDatabaseConfiguration(),
        $step->getAdminConfiguration(),
        $step->getEngineConfiguration(),
        $step->getBrokerConfiguration(),
        getGorgoneApiCredentialMacros(_CENTREON_ETC_ . '/../centreon-gorgone'),
    );

    $utils->executeSqlFile(__DIR__ . '/../../insertMacros.sql', $macros);
    $utils->executeSqlFile(__DIR__ . '/../../insertCommands.sql', $macros);
    $utils->executeSqlFile(__DIR__ . '/../../insertTimeperiods.sql', $macros);
    $utils->executeSqlFile(__DIR__ . '/../../var/baseconf/centreon-engine.sql', $macros);
    $utils->executeSqlFile(__DIR__ . '/../../var/baseconf/centreon-broker.sql', $macros);
    $utils->executeSqlFile(__DIR__ . '/../../insertTopology.sql', $macros);
    $utils->executeSqlFile(__DIR__ . '/../../insertBaseConf.sql', $macros);

    /**
     * @var CentreonModuleService
     */
    $moduleService = Centreon\LegacyContainer::getInstance()[ServiceProvider::CENTREON_MODULE];
    $widgets = $moduleService->getList(null, false, null, ['widget']);
    foreach ($widgets['widget'] as $widget) {
        if ($widget->isInternal()) {
            $moduleService->install($widget->getId(), 'widget');
        }
    }
} catch (Exception $e) {
    $return['msg'] = $e->getMessage();
    echo json_encode($return);

    exit;
}

$hostName = gethostname() ?: null;
// Insert Central to 'platform_topology' table, as first server and parent of all others.
$centralServerQuery = $link->query("SELECT `id`, `name` FROM nagios_server WHERE localhost = '1'");
if ($row = $centralServerQuery->fetch()) {
    $stmt = $link->prepare("
        INSERT INTO `platform_topology` (
            `address`,
            `hostname`,
            `name`,
            `type`,
            `parent_id`,
            `server_id`,
            `pending`
        ) VALUES (
            :centralAddress,
            :hostname,
            :name,
            'central',
            NULL,
            :id,
            '0'
        )
    ");
    $stmt->bindValue(':centralAddress', $_SERVER['SERVER_ADDR'], PDO::PARAM_STR);
    $stmt->bindValue(':hostname', $hostName, PDO::PARAM_STR);
    $stmt->bindValue(':name', $row['name'], PDO::PARAM_STR);
    $stmt->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
    $stmt->execute();
}

// Manage timezone
$timezone = date_default_timezone_get();
$statement = $link->prepare('SELECT timezone_id FROM timezone WHERE timezone_name= :timezone_name');
$statement->bindValue(':timezone_name', $timezone, PDO::PARAM_STR);
if (! $statement->execute()) {
    $return['msg'] = _('Cannot get timezone information');
    echo json_encode($return);

    exit;
}
if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $timezoneId = $row['timezone_id'];
} else {
    $timezoneId = '334'; // Europe/London timezone
}
$statement = $link->prepare("INSERT INTO `options` (`key`, `value`) VALUES ('gmt', :value)");
$statement->bindValue(':value', $timezoneId, PDO::PARAM_STR);
$statement->execute();

// Generate random key for this instance and set it to be not central and not remote
$informationsTableInsert = "INSERT INTO `informations` (`key`,`value`) VALUES
    ('isRemote', 'no'),
    ('isCentral', 'yes')";

$link->exec($informationsTableInsert);

splitQueries('../../insertACL.sql', ';', $link, '../../tmp/insertACL');

// Get Centreon version
$res = $link->query("SELECT `value` FROM informations WHERE `key` = 'version'");
if (! $res) {
    $return['msg'] = _('Cannot get Centreon version');
    echo json_encode($return);

    exit;
}
$row = $res->fetch();
$step->setVersion($row['value']);

$return['result'] = 0;
echo json_encode($return);

exit;
