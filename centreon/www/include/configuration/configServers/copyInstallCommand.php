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

require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';
require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Infrastructure\PollerInstallationCommandFactory;
use App\Shared\Infrastructure\FsEngineSecretsRepository;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;

const ENGINE_CONTEXT_PATH = '/etc/centreon-engine/engine-context.json';

header('Content-Type: application/json');

$pearDB = $dependencyInjector['configuration_db'];

CentreonSession::start(1);
if (! CentreonSession::checkSession(session_id(), $pearDB)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session']);

    exit();
}

$centreon = $_SESSION['centreon'];
$pollerId = filter_var($_GET['id'] ?? false, FILTER_VALIDATE_INT);
if ($pollerId === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid poller id']);

    exit();
}

$userId = (int) $centreon->user->user_id;
$isAdmin = (bool) $centreon->user->admin;

if ($isAdmin === false) {
    $acl = new CentreonACL($userId, $isAdmin);
    if (
        ! $acl->checkAction('create_edit_poller_cfg')
        || ! array_key_exists($pollerId, $acl->getPollers())
    ) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);

        exit();
    }
}

try {
    $statement = $pearDB->prepare(
        'SELECT ns.uid, ns.name, ns.poller_type, pt.central_address
        FROM nagios_server ns
        LEFT JOIN platform_topology pt ON pt.server_id = ns.id
        WHERE ns.id = :id'
    );
    $statement->bindValue(':id', $pollerId, PDO::PARAM_INT);
    $statement->execute();
    $poller = $statement->fetch(PDO::FETCH_ASSOC);

    if ($poller === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Poller not found']);

        exit();
    }

    $pollerUid = new PollerUid((int) $poller['uid']);
    $pollerType = PollerTypeEnum::from($poller['poller_type']);
    $isCloudPlatform = filter_var($_ENV['IS_CLOUD_PLATFORM'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($poller['central_address'] === null || $poller['central_address'] === '') {
        http_response_code(400);
        echo json_encode(['error' => 'No central address configured for this poller']);

        exit();
    }

    // Mirrors DbalPollerTokenRepository::getFirstValidPollerToken(): oldest valid token first,
    // deliberately. Keep both in sync — this endpoint and
    // GET /configuration/pollers/installation-command/{id} must return the same command.
    $statement = $pearDB->prepare(
        "SELECT token_string, token_name, creation_date, expiration_date, is_revoked
        FROM authentication_tokens
        WHERE type = 'poller'
        AND is_revoked = 0
        AND (expiration_date IS NULL OR expiration_date > :nowEpoch)
        ORDER BY creation_date ASC
        LIMIT 1"
    );
    $statement->bindValue(':nowEpoch', time(), PDO::PARAM_INT);
    $statement->execute();
    $tokenRow = $statement->fetch(PDO::FETCH_ASSOC);

    if ($tokenRow === false) {
        http_response_code(404);
        echo json_encode(['error' => 'No valid poller token found']);

        exit();
    }

    $token = new PollerToken(
        name: $tokenRow['token_name'],
        value: $tokenRow['token_string'],
        creationDate: new DateTimeImmutable('@' . (int) $tokenRow['creation_date']),
        expirationDate: $tokenRow['expiration_date'] !== null
            ? new DateTimeImmutable('@' . (int) $tokenRow['expiration_date'])
            : null,
        isRevoked: (bool) $tokenRow['is_revoked'],
    );

    $engineSecrets = new FsEngineSecretsRepository(ENGINE_CONTEXT_PATH);

    $factory = new PollerInstallationCommandFactory(
        $pollerUid,
        new PollerName($poller['name']),
        $pollerType,
        $token,
        $engineSecrets->getAppSecret(),
        $engineSecrets->getSalt(),
        $isCloudPlatform,
        $poller['central_address'],
    );

    echo json_encode(['command' => $factory->generate()]);
} catch (Throwable $exception) {
    ExceptionLogger::create()->log(
        $exception,
        ['source' => 'copyInstallCommand', 'poller_id' => $pollerId],
    );
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate installation command']);
}
