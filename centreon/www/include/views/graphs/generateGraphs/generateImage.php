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

/**
 * Include config file
 */
require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');

require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonGraph.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonBroker.class.php';
require_once _CENTREON_PATH_ . '/www/class/HtmlAnalyzer.php';

$pearDB = new CentreonDB();

session_start();
session_write_close();

$mySessionId = session_id();

// checks for token
if (! empty($_GET['username'])) {
    $userName = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['username'] ?? null);
}

$token = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['token'] ?? $_GET['akey'] ?? null);

if (! empty($userName) && ! empty($token)) {
    $query = "SELECT contact_id FROM `contact`
        WHERE `contact_alias` = :contact_alias
        AND `contact_activate` = '1'
        AND `contact_autologin_key` = :token LIMIT 1";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':contact_alias', $userName, PDO::PARAM_STR);
    $statement->bindValue(':token', $token, PDO::PARAM_STR);

    $statement->execute();

    if ($row = $statement->fetch()) {
        $res = $pearDB->prepare('SELECT session_id FROM session WHERE session_id = :sessionId');
        $res->bindValue(':sessionId', $mySessionId, PDO::PARAM_STR);
        $res->execute();
        if (! $res->rowCount()) {
            // security fix - regenerate the sid to prevent session fixation
            session_start();
            session_regenerate_id(true);
            $mySessionId = session_id();

            $query = 'INSERT INTO `session` (`session_id`, `user_id`, `current_page`, `last_reload`, `ip_address`)
                VALUES (:sessionId, :contactId, NULL, :lastReload, :ipAddress)';

            $statement = $pearDB->prepare($query);
            $statement->bindValue(':contactId', $row['contact_id'], PDO::PARAM_INT);
            $statement->bindValue(':sessionId', $mySessionId, PDO::PARAM_STR);
            $statement->bindValue(':lastReload', time(), PDO::PARAM_INT);
            $statement->bindValue(':ipAddress', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
            $statement->execute();
        }
    } else {
        exit('Invalid token');
    }
}

$indexDataId = filter_var(
    $_GET['index'] ?? 0,
    FILTER_VALIDATE_INT
);

// Checking hostName and service
if (! empty($_GET['hostname'])) {
    $hostName = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['hostname']);
}

if (! empty($_GET['service'])) {
    $serviceDescription = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['service']);
}

$pearDBO = new CentreonDB('centstorage');

if (! empty($hostName) && ! empty($serviceDescription)) {
    $statement = $pearDBO->prepare(
        'SELECT `id`
        FROM index_data
        WHERE host_name = :hostName
        AND service_description = :serviceDescription
        LIMIT 1'
    );

    $statement->bindValue(':hostName', $hostName, PDO::PARAM_STR);
    $statement->bindValue(':serviceDescription', $serviceDescription, PDO::PARAM_STR);
    $statement->execute();
    if ($res = $statement->fetch()) {
        $indexDataId = $res['id'];
    } else {
        exit('Resource not found');
    }
}

$chartId = null;

if (! empty($_GET['chartId'])) {
    $chartId = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['chartId']);
}

if (! empty($chartId)) {
    if (preg_match('/([0-9]+)_([0-9]+)/', $chartId, $matches)) {
        $hostId = (int) $matches[1];
        $serviceId = (int) $matches[2];
    } else {
        throw new InvalidArgumentException('chartId must be a combination of integers');
    }
    $statement = $pearDBO->prepare(
        'SELECT id FROM index_data WHERE host_id = :hostId AND service_id = :serviceId'
    );
    $statement->bindValue(':hostId', $hostId, PDO::PARAM_INT);
    $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);

    $statement->execute();

    if ($row = $statement->fetch()) {
        $indexDataId = $row['id'];
    } else {
        exit('Resource not found');
    }
}

$res = $pearDB->prepare(
    'SELECT c.contact_id, c.contact_admin
    FROM session s, contact c
    WHERE s.session_id = :sessionId
    AND s.user_id = c.contact_id
    LIMIT 1'
);
$res->bindValue(':sessionId', $mySessionId, PDO::PARAM_STR);
$res->execute();

if (! $res->rowCount()) {
    exit('Unknown user');
}

$row = $res->fetch();
$isAdmin = $row['contact_admin'];
$contactId = $row['contact_id'];

if (! $isAdmin) {

    $acl = new CentreonACL($contactId, $isAdmin);

    if (empty($acl->getAccessGroups())) {
        throw new Exception('Access denied');
    }

    $aclGroupIds = array_keys($acl->getAccessGroups());

    try {
        $sql = 'SELECT host_id, service_id FROM index_data WHERE id = :index_data_id';
        $statement = $pearDBO->prepare($sql);
        $statement->bindValue(':index_data_id', (int) $indexDataId, PDO::PARAM_INT);
        $statement->execute();
        if (! $statement->rowCount()) {
            exit('Graph not found');
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        unset($statement);
        $hostId = $row['host_id'];
        $serviceId = $row['service_id'];
        $aclGroupQueryBinds = [];
        foreach ($aclGroupIds as $index => $aclGroupId) {
            $aclGroupQueryBinds[':acl_group_' . $index] = (int) $aclGroupId;
        }
        $aclGroupBinds = implode(',', array_keys($aclGroupQueryBinds));

        $sql = <<<SQL
            SELECT service_id
            FROM centreon_acl
            WHERE host_id = :host_id
                AND service_id = :service_id
                AND group_id IN ({$aclGroupBinds})
            SQL;

        $statement = $pearDBO->prepare($sql);
        $statement->bindValue(':host_id', (int) $hostId, PDO::PARAM_INT);
        $statement->bindValue(':service_id', (int) $serviceId, PDO::PARAM_INT);

        foreach ($aclGroupQueryBinds as $queryBind => $queryValue) {
            $statement->bindValue($queryBind, (int) $queryValue, PDO::PARAM_INT);
        }
        $statement->execute();
        if (! $statement->rowCount()) {
            exit('Access denied');
        }
    } catch (PDOException $e) {
        (new CentreonLog())->insertLog(2, "Error while checking acl to generate graph image : {$e->getMessage()}");

        exit('Access denied');
    }

}

// Check security session
if (! CentreonSession::checkSession($mySessionId, $pearDB)) {
    CentreonGraph::displayError();
}

require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';

/**
 * Create XML Request Objects
 */
$obj = new CentreonGraph($contactId, $indexDataId, 0, 1);

/**
 * Set arguments from GET
 */
$obj->setRRDOption('start', $obj->checkArgument('start', $_GET, time() - (60 * 60 * 48)));
$obj->setRRDOption('end', $obj->checkArgument('end', $_GET, time()));

/**
 * Template Management
 */
if (isset($_GET['template_id'])) {
    $obj->setTemplate($_GET['template_id']);
} else {
    $obj->setTemplate();
}

$obj->init();
if (isset($_GET['flagperiod'])) {
    $obj->setCommandLineTimeLimit($_GET['flagperiod']);
}

/**
 * Init Curve list
 */
if (isset($_GET['metric'])) {
    $obj->setMetricList($_GET['metric']);
}
$obj->initCurveList();

/**
 * Comment time
 */
$obj->setOption('comment_time');

/**
 * Create Legende
 */
$obj->createLegend();

$obj->setColor('BACK', '#FFFFFF');
$obj->setColor('FRAME', '#FFFFFF');
$obj->setColor('SHADEA', '#EFEFEF');
$obj->setColor('SHADEB', '#EFEFEF');
$obj->setColor('ARROW', '#FF0000');

/**
 * Display Images Binary Data
 */
$obj->displayImageFlow();

/**
 * Closing session
 */
if (isset($_GET['akey'])) {
    $dbResult = $pearDB->prepare(
        'DELETE FROM session
        WHERE session_id = ? AND user_id = (SELECT contact_id from contact where contact_autologin_key = ?)'
    );
    $dbResult = $pearDB->execute($dbResult, [$mySessionId, $_GET['akey']]);
}
