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

$duplicationNumbers = [];
$selectedGraphTemplates = [];

// id of the graph template
$graph_id = filter_var(
    $_GET['graph_id'] ?? $_POST['graph_id'] ?? false,
    FILTER_VALIDATE_INT
);

/*
 * Corresponding to the lines selected in the listing
 * $_POST['select'] = [
 *     'graphIdSelected' => 'duplicationFactor'
 * ]
 */
if (! empty($_POST['select'])) {
    foreach ($_POST['select'] as $gIdSelected => $dupFactor) {
        if (filter_var($dupFactor, FILTER_VALIDATE_INT) !== false) {
            $selectedGraphTemplates[$gIdSelected] = (int) $dupFactor;
        }
    }
}

/*
 * End of line text fields (duplicationFactor) in the UI for each lines
 * $_POST['dupNbr'] = [
 *     'graphId' => 'duplicationFactor'
 * ]
 */
if (! empty($_POST['dupNbr'])) {
    foreach ($_POST['dupNbr'] as $gId => $dupFactor) {
        if (filter_var($dupFactor, FILTER_VALIDATE_INT) !== false) {
            $duplicationNumbers[$gId] = (int) $dupFactor;
        }
    }
}

// Path to the configuration dir
$path = './include/views/graphTemplates/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

switch ($o) {
    case 'a':
        // Add a graph template
        require_once $path . 'formGraphTemplate.php';
        break;
    case 'w':
        // watch aGraph template
        require_once $path . 'formGraphTemplate.php';
        break;
    case 'c':
        // Modify a graph template
        require_once $path . 'formGraphTemplate.php';
        break;
    case 'm':
        // duplicate n time selected graph template(s)
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleGraphTemplateInDB($selectedGraphTemplates, $duplicationNumbers);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGraphTemplates.php';
        break;
    case 'd':
        // delete selected graph template(s)
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteGraphTemplateInDB($selectedGraphTemplates);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGraphTemplates.php';
        break;
    default:
        require_once $path . 'listGraphTemplates.php';
        break;
}
