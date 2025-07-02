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

// DB Connect
include_once './class/centreonDB.class.php';

$param = ! isset($_GET['cmd'])
&& isset($_POST['cmd']) ? $_POST : $_GET;

if (
    isset($param['cmd'])
    && $param['cmd'] == 15
    && isset($param['author'], $param['en'])

    && $param['en'] == 1
) {
    if (
        ! isset($param['sticky'])
        || ! in_array($param['sticky'], ['0', '1'])
    ) {
        $param['sticky'] = '0';
    }
    if (
        ! isset($param['notify'])
        || ! in_array($param['notify'], ['0', '1'])
    ) {
        $param['notify'] = '0';
    }
    if (
        ! isset($param['persistent'])
        || ! in_array($param['persistent'], ['0', '1'])
    ) {
        $param['persistent'] = '0';
    }
    acknowledgeService($param);
} elseif (
    isset($param['cmd'])
    && $param['cmd'] == 15
    && isset($param['author'], $param['en'])

    && $param['en'] == 0
) {
    acknowledgeServiceDisable();
}

if (
    isset($param['cmd'])
    && $param['cmd'] == 16
    && isset($param['output'])
) {
    submitPassiveCheck();
}

if ($o == 'svcSch') {
    $param['sort_types'] = 'next_check';
    $param['order'] = 'sort_asc';
}

$path = './include/monitoring/status/';
$metaservicepath = $path . 'service.php';

$pathRoot = './include/monitoring/';
$pathExternal = './include/monitoring/external_cmd/';
$pathDetails = './include/monitoring/objectDetails/';

// Special Paths
$svc_path = $path . 'Services/';
$hg_path = $path . 'ServicesHostGroups/';
$sg_path = $path . 'ServicesServiceGroups/';

if ($continue) {
    switch ($o) {
        // View of Service
        case 'svc':
        case 'svcpb':
        case 'svc_warning':
        case 'svc_critical':
        case 'svc_unknown':
        case 'svc_ok':
        case 'svc_pending':
        case 'svc_unhandled':
            require_once $svc_path . 'service.php';
            break;
            // Special Views
        case 'svcd':
            require_once $pathDetails . 'serviceDetails.php';
            break;
        case 'svcak':
            require_once './include/monitoring/acknowlegement/serviceAcknowledge.php';
            break;
        case 'svcpc':
            require_once './include/monitoring/submitPassivResults/servicePassiveCheck.php';
            break;
        case 'svcgrid':
        case 'svcOV':
        case 'svcOV_pb':
            require_once $svc_path . 'serviceGrid.php';
            break;
        case 'svcSum':
            require_once $svc_path . 'serviceSummary.php';
            break;
            // View by Service Groups
        case 'svcgridSG':
        case 'svcOVSG':
        case 'svcOVSG_pb':
            require_once $sg_path . 'serviceGridBySG.php';
            break;
        case 'svcSumSG':
            require_once $sg_path . 'serviceSummaryBySG.php';
            break;
            // View By hosts groups
        case 'svcgridHG':
        case 'svcOVHG':
        case 'svcOVHG_pb':
            require_once $hg_path . 'serviceGridByHG.php';
            break;
        case 'svcSumHG':
            require_once $hg_path . 'serviceSummaryByHG.php';
            break;
        default:
            require_once $svc_path . 'service.php';
            break;
    }
}
