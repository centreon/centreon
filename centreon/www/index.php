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

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/class/centreonSession.class.php';
require_once __DIR__ . '/class/centreonAuth.class.php';
require_once __DIR__ . '/class/centreonLog.class.php';
require_once __DIR__ . '/class/centreonDB.class.php';

const AUTOLOGIN_FIELDS = ['autologin', 'useralias', 'token'];

updateCentreonBaseUri();
include __DIR__ . '/index.html';

CentreonSession::start();

// Already connected
if (isset($_SESSION['centreon'])) {
    $pearDB = new CentreonDB();
    manageRedirection($_SESSION['centreon'], $pearDB);

    return;
}

/*
 * Check PHP version
 *
 *  Centreon >= 24.10 doesn't support PHP < 8.2
 *
 */
if (version_compare(phpversion(), '8.2') < 0) {
    echo "<div class='msg'> PHP version is < 8.2. Please Upgrade PHP</div>";

    return;
}

if (isset($_GET['autologin']) && $_GET['autologin']) {
    global $pearDB;
    $pearDB = new CentreonDB();

    $dbResult = $pearDB->query("SELECT `value` FROM `options` WHERE `key` = 'enable_autologin'");
    if (($row = $dbResult->fetch()) && $row['value'] !== '1') {
        return;
    }

    // Init log class
    $centreonLog = new CentreonUserLog(-1, $pearDB);

    // Check first for Autologin or Get Authentication
    $autologin = $_GET['autologin'] ?? CentreonAuth::AUTOLOGIN_DISABLE;
    $useralias = $_GET['useralias'] ?? null;
    $password = $passwordG ?? null;

    $token = $_REQUEST['token'] ?? '';

    $centreonAuth = new CentreonAuth(
        $dependencyInjector,
        $useralias,
        $password,
        $autologin,
        $pearDB,
        $centreonLog,
        CentreonAuth::ENCRYPT_MD5,
        $token,
    );
    if ($centreonAuth->passwdOk == 1) {
        $centreon = new Centreon($centreonAuth->userInfos);

        // security fix - regenerate the sid after the login to prevent session fixation
        session_regenerate_id(true);
        $_SESSION['centreon'] = $centreon;
        // saving session data in the DB
        $query = 'INSERT INTO `session` (`session_id` , `user_id` , `current_page` , `last_reload`, `ip_address`) '
            . 'VALUES (?, ?, ?, ?, ?)';
        $dbResult = $pearDB->prepare($query);
        $pearDB->execute(
            $dbResult,
            [session_id(), $centreon->user->user_id, '1', time(), $_SERVER['REMOTE_ADDR']]
        );

        // saving session token in security_token
        $expirationSessionDelay = 120;
        $delayStatement = $pearDB->prepare("SELECT value FROM options WHERE `key` = 'session_expire'");
        $delayStatement->execute();
        if (($result = $delayStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $expirationSessionDelay = $result['value'];
        }
        $securityTokenStatement = $pearDB->prepare(
            'INSERT INTO security_token (`token`, `creation_date`, `expiration_date`) '
            . 'VALUES (:token, :createdAt, :expireAt)'
        );
        $securityTokenStatement->bindValue(':token', session_id(), PDO::PARAM_STR);
        $securityTokenStatement->bindValue(':createdAt', (new DateTime())->getTimestamp(), PDO::PARAM_INT);
        $securityTokenStatement->bindValue(
            ':expireAt',
            (new DateTime())->add(new DateInterval('PT' . $expirationSessionDelay . 'M'))->getTimestamp(),
            PDO::PARAM_INT
        );
        $securityTokenStatement->execute();

        // saving session in security_authentication_tokens
        $providerTokenId = (int) $pearDB->lastInsertId();

        $configurationStatement = $pearDB->query("SELECT id from provider_configuration WHERE name='local'");
        if (($result = $configurationStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $configurationId = (int) $result['id'];
        } else {
            throw new Exception('No local provider found');
        }

        $securityAuthenticationTokenStatement = $pearDB->prepare(
            'INSERT INTO security_authentication_tokens '
            . '(`token`, `provider_token_id`, `provider_configuration_id`, `user_id`) VALUES '
            . '(:token, :providerTokenId, :providerConfigurationId, :userId)'
        );
        $securityAuthenticationTokenStatement->bindValue(':token', session_id(), PDO::PARAM_STR);
        $securityAuthenticationTokenStatement->bindValue(':providerTokenId', $providerTokenId, PDO::PARAM_INT);
        $securityAuthenticationTokenStatement->bindValue(
            ':providerConfigurationId',
            $configurationId,
            PDO::PARAM_INT
        );
        $securityAuthenticationTokenStatement->bindValue(':userId', $centreon->user->user_id, PDO::PARAM_INT);
        $securityAuthenticationTokenStatement->execute();

        manageRedirection($centreon, $pearDB);

        return;
    }
}

/**
 * Update centreon base uri in index.html
 */
function updateCentreonBaseUri(): void
{
    $requestUri = htmlspecialchars($_SERVER['REQUEST_URI']) ?: '/centreon/';
    // Regular expression pattern to match the string between slashes
    $pattern = "/\/([^\/]+)\//";
    preg_match($pattern, $requestUri, $matches);
    $basePath = isset($matches[0]) && $matches[0] !== '' ? str_replace('//', '/', $matches[0]) : '/centreon/';
    $indexHtmlPath = './index.html';
    $indexHtmlContent = file_get_contents($indexHtmlPath);

    // update base path only if it has changed
    if (! preg_match('/.*<base\shref="' . preg_quote($basePath, '/') . '">/', $indexHtmlContent)) {
        $indexHtmlContent = preg_replace(
            '/(^.*<base\shref=")\S+(">.*$)/s',
            '${1}' . $basePath . '${2}',
            $indexHtmlContent
        );

        file_put_contents($indexHtmlPath, $indexHtmlContent, LOCK_EX);
    }
}

/**
 * Redirect to user default page
 *
 * @param Centreon $centreon
 * @param CentreonDB $pearDB
 */
function manageRedirection(Centreon $centreon, CentreonDB $pearDB): void
{
    $headerRedirection = './main.php';
    $argP = filter_var(
        $_POST['p'] ?? $_GET['p'] ?? null,
        FILTER_VALIDATE_INT
    );
    if ($argP !== false) {
        $headerRedirection .= '?p=' . $argP;
        foreach ($_GET as $parameter => $value) {
            if (! in_array($parameter, AUTOLOGIN_FIELDS)) {
                $sanitizeParameter = htmlspecialchars($parameter);
                $sanitizeValue = filter_input(INPUT_GET, $parameter);
                if (! empty($sanitizeParameter) && $sanitizeValue !== false) {
                    $headerRedirection .= '&' . $parameter . '=' . $value;
                }
            }
        }
    } elseif (isset($centreon->user->default_page) && $centreon->user->default_page !== '') {
        // get more details about the default page
        $stmt = $pearDB->prepare(
            'SELECT topology_url, is_react FROM topology WHERE topology_page = ? LIMIT 0, 1'
        );
        $pearDB->execute($stmt, [$centreon->user->default_page]);

        if ($stmt->rowCount() && ($topologyData = $stmt->fetch()) && $topologyData['is_react']) {
            // redirect to the react path
            $headerRedirection = '.' . $topologyData['topology_url'];
        } else {
            $headerRedirection .= '?p=' . $centreon->user->default_page;

            $argMin = $_POST['min'] ?? $_GET['min'] ?? null;
            if ($argMin === '1') {
                $headerRedirection .= '&min=1';
            }
        }
    }
    header('Location: ' . $headerRedirection);
}
