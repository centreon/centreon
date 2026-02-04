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
include './include/common/autoNumLimit.php';

require_once './class/centreonDuration.class.php';
include_once './include/monitoring/common-Func.php';

// Path to the option dir
$path = './include/Administration/performance/';

// PHP functions
require_once './include/Administration/parameters/DB-Func.php';
require_once './include/common/common-Func.php';
require_once './class/centreonDB.class.php';

$pearDBO = new CentreonDB('centstorage');

switch ($o) {
    case 'msvc':
        require_once $path . 'viewMetrics.php';
        break;
    default:
        require_once $path . 'viewData.php';
        break;
}
