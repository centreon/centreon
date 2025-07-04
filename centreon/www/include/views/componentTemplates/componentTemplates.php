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

const ADD_COMPONENT_TEMPLATE = 'a';
const WATCH_COMPONENT_TEMPLATE = 'w';
const MODIFY_COMPONENT_TEMPLATE = 'c';
const DUPLICATE_COMPONENT_TEMPLATE = 'm';
const DELETE_COMPONENT_TEMPLATE = 'd';

$duplicationNumbers = [];
$selectedCurveTemplates = [];

// id of the curve template
$compo_id = filter_var(
    $_GET['compo_id'] ?? $_POST['compo_id'] ?? false,
    FILTER_VALIDATE_INT
);

if (! empty($_POST['select'])) {
    foreach ($_POST['select'] as $curveIdSelected => $dupFactor) {
        if (
            filter_var($dupFactor, FILTER_VALIDATE_INT) !== false
            && filter_var($curveIdSelected, FILTER_VALIDATE_INT) !== false
        ) {
            $selectedCurveTemplates[$curveIdSelected] = (int) $dupFactor;
        }
    }
}

if (! empty($_POST['dupNbr'])) {
    foreach ($_POST['dupNbr'] as $curveId => $dupFactor) {
        if (
            filter_var($dupFactor, FILTER_VALIDATE_INT) !== false
            && filter_var($curveId, FILTER_VALIDATE_INT) !== false
        ) {
            $duplicationNumbers[$curveId] = (int) $dupFactor;
        }
    }
}

// Path to the configuration dir
$path = './include/views/componentTemplates/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

switch ($o) {
    case ADD_COMPONENT_TEMPLATE:
    case WATCH_COMPONENT_TEMPLATE:
    case MODIFY_COMPONENT_TEMPLATE:
        require_once $path . 'formComponentTemplate.php';
        break;
    case DUPLICATE_COMPONENT_TEMPLATE:
        if (isCSRFTokenValid()) {
            multipleComponentTemplateInDB(
                $selectedCurveTemplates ?? [],
                $duplicationNumbers
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listComponentTemplates.php';
        break;
    case DELETE_COMPONENT_TEMPLATE:
        if (isCSRFTokenValid()) {
            deleteComponentTemplateInDB($selectedCurveTemplates ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listComponentTemplates.php';
        break;
    default:
        require_once $path . 'listComponentTemplates.php';
        break;
}
