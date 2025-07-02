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

require_once './class/centreonDuration.class.php';
include_once './include/monitoring/common-Func.php';
include_once './include/monitoring/external_cmd/cmd.php';

// Init Continue Value
$continue = true;

$path = './include/monitoring/status/Hosts/';
$path_hg = './include/monitoring/status/HostGroups/';

$pathRoot = './include/monitoring/';
$pathDetails = './include/monitoring/objectDetails/';
$pathTools = './include/tools/';

$param = ! isset($_GET['cmd']) && isset($_POST['cmd']) ? $_POST : $_GET;

if (isset($param['cmd'])
    && $param['cmd'] == 14
    && isset($param['author'], $param['en'])

    && $param['en'] == 1
) {
    if (! isset($param['sticky'])) {
        $param['sticky'] = 0;
    }
    if (! isset($param['notify'])) {
        $param['notify'] = 0;
    }
    if (! isset($param['persistent'])) {
        $param['persistent'] = 0;
    }
    if (! isset($param['ackhostservice'])) {
        $param['ackhostservice'] = 0;
    }
    acknowledgeHost($param);
} elseif (isset($param['cmd'])
    && $param['cmd'] == 14
    && isset($param['author'], $param['en'])

    && $param['en'] == 0
) {
    acknowledgeHostDisable();
}

if (isset($param['cmd']) && $param['cmd'] == 16 && isset($param['output'])) {
    submitHostPassiveCheck();
}

if ($min) {
    switch ($o) {
        default:
            require_once $pathTools . 'tools.php';
            break;
    }
} elseif ($continue) {
    // Now route to pages or Actions
    switch ($o) {
        case 'h':
            require_once $path . 'host.php';
            break;
        case 'hpb':
            require_once $path . 'host.php';
            break;
        case 'h_unhandled':
            require_once $path . 'host.php';
            break;
        case 'hd':
            require_once $pathDetails . 'hostDetails.php';
            break;
        case 'hpc':
            require_once './include/monitoring/submitPassivResults/hostPassiveCheck.php';
            break;
        case 'hak':
            require_once $pathRoot . 'acknowlegement/hostAcknowledge.php';
            break;
        default:
            require_once $path . 'host.php';
            break;
    }
}
