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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';

$step = new CentreonLegacy\Core\Install\Step\Step3($dependencyInjector);
$parametersConfiguration = $step->getEngineParameters();

$err = ['required' => [], 'directory_not_found' => [], 'file_not_found' => []];

$parameters = filter_input_array(INPUT_POST);
foreach ($parameters as $name => $value) {
    if (trim($value) == '' && $parametersConfiguration[$name]['required'] == 1) {
        $err['required'][] = $name;
    } elseif (trim($value) != '' && $parametersConfiguration[$name]['type'] == 0) { // is directory
        if (! is_dir($value)) {
            $err['directory_not_found'][] = $name;
        }
    } elseif (trim($value) != '' && $parametersConfiguration[$name]['type'] == 1) { // is file
        if (! is_file($value)) {
            $err['file_not_found'][] = $name;
        }
    }
}

if ($err['file_not_found'] === [] && $err['file_not_found'] === [] && $err['file_not_found'] === []) {
    $step->setEngineConfiguration($parameters);
}

echo json_encode($err);
