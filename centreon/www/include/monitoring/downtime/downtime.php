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

// External Command Object
$ecObj = new CentreonExternalCommand($centreon);

$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

// Path to the configuration dir
$path = './include/monitoring/downtime/';

// PHP functions
require_once './include/common/common-Func.php';
require_once $path . 'common-Func.php';
require_once './include/monitoring/external_cmd/functions.php';

switch ($o) {
    case 'a':
        require_once $path . 'AddDowntime.php';
        break;
    case 'ds':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (isset($_POST['select'])) {
                foreach ($_POST['select'] as $key => $value) {
                    $res = explode(';', urldecode($key));
                    $ishost = isDownTimeHost($res[2]);
                    if (
                        $oreon->user->access->admin
                        || ($ishost && $oreon->user->access->checkAction('host_schedule_downtime'))
                        || (! $ishost && $oreon->user->access->checkAction('service_schedule_downtime'))
                    ) {
                        $ecObj->deleteDowntime($res[0], [$res[1] . ';' . $res[2] => 'on']);
                        deleteDowntimeInDb($res[2]);
                    }
                }
            }
        } else {
            unvalidFormMessage();
        }
        try {
            require_once $path . 'listDowntime.php';
        } catch (Throwable $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Error while listing downtime: ' . $ex->getMessage(),
                exception: $ex
            );

            throw $ex;
        }
        break;
    case 'cs':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (isset($_POST['select'])) {
                foreach ($_POST['select'] as $key => $value) {
                    $res = explode(';', urldecode($key));
                    $ishost = isDownTimeHost($res[2]);
                    if (
                        $oreon->user->access->admin
                        || ($ishost && $oreon->user->access->checkAction('host_schedule_downtime'))
                        || (! $ishost && $oreon->user->access->checkAction('service_schedule_downtime'))
                    ) {
                        $ecObj->deleteDowntime($res[0], [$res[1] . ';' . $res[2] => 'on']);
                    }
                }
            }
        } else {
            unvalidFormMessage();
        }
        // then, as all the next cases, requiring the listDowntime.php
        // no break
    case 'vs':
    case 'vh':
    default:
        try {
            require_once $path . 'listDowntime.php';
        } catch (Throwable $ex) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: 'Error while listing downtime: ' . $ex->getMessage(),
                exception: $ex
            );

            throw $ex;
        }
        break;
}
