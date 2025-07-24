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

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 'Off');

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../class/centreon.class.php';
require_once __DIR__ . '/class/webService.class.php';

$pearDB = $dependencyInjector['configuration_db'];

$user = null;
// get user information if a token is provided
if (isset($_SERVER['HTTP_CENTREON_AUTH_TOKEN'])) {
    try {
        $contactStatement = $pearDB->prepare(
            <<<'SQL'
                SELECT c.*
                FROM security_authentication_tokens sat, contact c
                WHERE c.contact_id = sat.user_id
                    AND sat.token = :token
                    AND sat.is_revoked = 0
                SQL
        );
        $contactStatement->bindValue(':token', $_SERVER['HTTP_CENTREON_AUTH_TOKEN'], PDO::PARAM_STR);
        $contactStatement->execute();
        if ($userInfos = $contactStatement->fetch()) {
            $centreon = new Centreon($userInfos);
            $user = $centreon->user;
        }
    } catch (PDOException $e) {
        CentreonWebService::sendResult('Database error', 500);
    }
}

CentreonWebService::router($dependencyInjector, $user, false);
