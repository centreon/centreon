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

if (! isset($centreon)) {
    exit();
}

$gopt_id = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['gopt_id'] ?? null);
if ((! isset($cg) || is_null($cg))) {
    $gopt_id = HtmlAnalyzer::sanitizeAndRemoveTags($_POST['gopt_id'] ?? null);
}

// Path to the option dir
$path = './include/Administration/parameters/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

switch ($o) {
    case 'engine':
        require_once $path . 'engine/form.php';
        break;
    case 'snmp':
        require_once $path . 'snmp/form.php';
        break;
    case 'rrdtool':
        require_once $path . 'rrdtool/form.php';
        break;
    case 'ldap':
        require_once $path . 'ldap/ldap.php';
        break;
    case 'debug':
        require_once $path . 'debug/form.php';
        break;
    case 'css':
        require_once $path . 'css/form.php';
        break;
    case 'storage':
        require_once $path . 'centstorage/form.php';
        break;
    case 'gorgone':
        require_once $path . 'gorgone/gorgone.php';
        break;
    case 'knowledgeBase':
        require_once $path . 'knowledgeBase/formKnowledgeBase.php';
        break;
    case 'api':
        require_once $path . 'api/api.php';
        break;
    case 'backup':
        require_once $path . 'backup/formBackup.php';
        break;
    case 'remote':
        require_once $path . 'remote/formRemote.php';
        break;
    case 'general':
    default:
        require_once $path . 'general/form.php';
        break;
}
