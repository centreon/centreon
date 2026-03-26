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

// TODO Security

// Add paths

$centreon_path = realpath(__DIR__ . '/../../../../');
require_once $centreon_path . '/config/centreon.config.php';

set_include_path(
    get_include_path() . PATH_SEPARATOR . $centreon_path . 'config/' . PATH_SEPARATOR
    . $centreon_path . 'www/class/'
);

require_once 'centreon-knowledge/procedures.class.php';
require_once 'centreonLog.class.php';

$modules_path = $centreon_path . '/www/include/configuration/configKnowledge/';
require_once $modules_path . 'functions.php';
require_once $centreon_path . '/bootstrap.php';

// Connect to centreon DB
$pearDB = $dependencyInjector['configuration_db'];

$conf = getWikiConfig($pearDB);
$WikiURL = $conf['kb_wiki_url'];

header("Location: {$WikiURL}/index.php?title=" . htmlentities($_GET['object'], ENT_QUOTES) . '&action=edit');
