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

ini_set('display_errors', 'On');
$centreon_path = realpath(__DIR__ . '/../../../../../');
global $etc_centreon;

require_once $centreon_path . '/config/centreon.config.php';

set_include_path(
    get_include_path()
    . PATH_SEPARATOR . $centreon_path . 'www/class/centreon-knowledge/'
    . PATH_SEPARATOR . $centreon_path . 'www/'
);

require_once 'include/common/common-Func.php';
require_once 'class/centreonLog.class.php';
require_once $centreon_path . '/bootstrap.php';
require_once 'class/centreon-knowledge/procedures.class.php';
require_once 'class/centreon-knowledge/ProceduresProxy.class.php';

$modules_path = $centreon_path . 'www/include/configuration/configKnowledge/';
require_once $modules_path . 'functions.php';

// DB connexion
$pearDB = $dependencyInjector['configuration_db'];

try {
    $wikiConf = getWikiConfig($pearDB);
} catch (Exception $e) {
    echo $e->getMessage();

    exit();
}

$wikiURL = $wikiConf['kb_wiki_url'];
$proxy = new ProceduresProxy($pearDB);

// Check if user want host or service procedures
$url = null;

if (isset($_GET['host_name'])) {
    $hostName = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['host_name']);
}
if (isset($_GET['service_description'])) {
    $serviceDescription = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['service_description']);
}

if (! empty($hostName) && ! empty($serviceDescription)) {
    $url = $proxy->getServiceUrl($hostName, $serviceDescription);
} elseif (! empty($hostName)) {
    $url = $proxy->getHostUrl($hostName);
}

if (! empty($url)) {
    header('Location: ' . $url);
} elseif (! empty($hostName) && ! empty($serviceDescription)) {
    header("Location: {$wikiURL}/?title=Service_:_" . $hostName . '_/_' . $serviceDescription);
} elseif (! empty($hostname)) {
    header("Location: {$wikiURL}/?title=Host_:_" . $hostName);
} else {
    header('Location: ' . $wikiURL);
}

exit();
