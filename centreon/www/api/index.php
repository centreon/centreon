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

require_once __DIR__ . '/../../bootstrap.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once __DIR__ . '/class/webService.class.php';
require_once __DIR__ . '/interface/di.interface.php';

use Core\Security\Authentication\Domain\Exception\AuthenticationException;

error_reporting(-1);
ini_set('display_errors', 0);

$pearDB = $dependencyInjector['configuration_db'];

$kernel = App\Kernel::createForWeb();

// Test if the call is for authenticate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] == 'authenticate') {
    if (false === isset($_POST['username']) || false === isset($_POST['password'])) {
        CentreonWebService::sendResult('Bad parameters', 400);
    }

    $credentials = [
        'login' => $_POST['username'],
        'password' => $_POST['password'],
    ];
    $authenticateApiUseCase = $kernel->getContainer()->get(
        Centreon\Domain\Authentication\UseCase\AuthenticateApi::class
    );
    $request = new Centreon\Domain\Authentication\UseCase\AuthenticateApiRequest(
        $credentials['login'],
        $credentials['password']
    );
    $response = new Centreon\Domain\Authentication\UseCase\AuthenticateApiResponse();
    try {
        $authenticateApiUseCase->execute($request, $response);
    } catch (AuthenticationException $ex) {
        CentreonWebService::sendResult('Authentication failed', 401);
    }
    $userAccessesStatement = $pearDB->prepare(
        'SELECT contact_admin, reach_api, reach_api_rt FROM contact WHERE contact_alias = :alias'
    );
    $userAccessesStatement->bindValue(':alias', $credentials['login'], PDO::PARAM_STR);
    $userAccessesStatement->execute();
    if (($userAccess = $userAccessesStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
        if (
            ! (int) $userAccess['contact_admin']
            && (int) $userAccess['reach_api'] === 0
            && (int) $userAccess['reach_api_rt'] === 0
        ) {
            CentreonWebService::sendResult('Unauthorized', 403);
        }
    }

    if (! empty($response->getApiAuthentication()['security']['token'])) {
        CentreonWebService::sendResult(['authToken' => $response->getApiAuthentication()['security']['token']]);
    } else {
        CentreonWebService::sendResult('Invalid credentials', 401);
    }
}

// Test authentication
if (false === isset($_SERVER['HTTP_CENTREON_AUTH_TOKEN'])) {
    CentreonWebService::sendResult('Unauthorized', 403);
}

// Create the default object
try {
    $contactStatement = $pearDB->prepare(
        'SELECT c.*
        FROM security_authentication_tokens sat, contact c
        WHERE c.contact_id = sat.user_id
        AND sat.token = :token'
    );
    $contactStatement->bindValue(':token', $_SERVER['HTTP_CENTREON_AUTH_TOKEN'], PDO::PARAM_STR);
    $contactStatement->execute();
} catch (PDOException $e) {
    CentreonWebService::sendResult('Database error', 500);
}
$userInfos = $contactStatement->fetch();
if (is_null($userInfos)) {
    CentreonWebService::sendResult('Unauthorized', 401);
}

$centreon = new Centreon($userInfos);
$oreon = $centreon;

CentreonWebService::router($dependencyInjector, $centreon->user);
