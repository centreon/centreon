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
require_once __DIR__ . '/../../../class/centreonAuth.class.php';

$err = ['required' => [], 'email' => true, 'password' => true, 'password_security_policy' => true];

$emailRegexp = "/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?"
    . "(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/";

$parameters = filter_input_array(INPUT_POST);
foreach ($parameters as $name => $value) {
    if (trim($value) == '') {
        $err['required'][] = $name;
    }
}

if (! in_array('email', $err['required']) && ! preg_match($emailRegexp, $parameters['email'])) {
    $err['email'] = false;
}

if (
    ! in_array('admin_password', $err['required'])
    && ! in_array('confirm_password', $err['required'])
    && $parameters['admin_password'] !== $parameters['confirm_password']
) {
    $err['password'] = false;
}

if (
    ! preg_match(
        '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/',
        $parameters['admin_password']
    )
) {
    $err['password_security_policy'] = false;
} else {
    $parameters['admin_password'] = password_hash(
        $parameters['admin_password'],
        CentreonAuth::PASSWORD_HASH_ALGORITHM
    );
}

if ($err['required'] === [] && $err['password'] && $err['email'] && $err['password_security_policy']) {
    $step = new CentreonLegacy\Core\Install\Step\Step5($dependencyInjector);
    $step->setAdminConfiguration($parameters);
}

echo json_encode($err);
