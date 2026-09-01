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

declare(strict_types=1);

require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';
require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Infrastructure\CentralUrlFactory;
use App\MonitoringConfiguration\Infrastructure\PollerInstallationCommandFactory;
use App\Shared\Infrastructure\FsEngineSecretsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// Keep in sync with engine_context_path (config/services.yaml) and
// upgrade.engine_context_path (config.new/services/upgrade.php): this endpoint
// deliberately does not boot the Symfony kernel, so it cannot read the parameter.
const ENGINE_CONTEXT_PATH = '/etc/centreon-engine/engine-context.json';

// Same reason, and same value as env(TRUSTED_PROXIES) in config/packages/framework.yaml:
// keep both in sync, or the scheme this endpoint resolves drifts from the one the API
// resolves for the very same platform.
const DEFAULT_TRUSTED_PROXIES = '127.0.0.1,REMOTE_ADDR';

header('Content-Type: application/json');
// The response body carries the app secret, the salt and a live poller token.
header('Cache-Control: no-store');

/**
 * Emit a JSON response with the given HTTP status code and stop the script.
 *
 * @param int $statusCode
 * @param array<string, string> $payload
 *
 * @throws JsonException
 */
function sendJsonResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_THROW_ON_ERROR);

    exit();
}

$pearDB = $dependencyInjector['configuration_db'];

CentreonSession::start(1);
if (! CentreonSession::checkSession(session_id(), $pearDB)) {
    sendJsonResponse(403, ['error' => 'Invalid session']);
}

$centreon = $_SESSION['centreon'];
$pollerId = filter_var($_GET['id'] ?? false, FILTER_VALIDATE_INT);
if ($pollerId === false) {
    sendJsonResponse(400, ['error' => 'Invalid poller id']);
}

$userId = (int) $centreon->user->user_id;
$isAdmin = (bool) $centreon->user->admin;

if ($isAdmin === false) {
    $acl = new CentreonACL($userId, $isAdmin);
    if (
        ! $acl->checkAction('create_edit_poller_cfg')
        || ! array_key_exists($pollerId, $acl->getPollers())
    ) {
        sendJsonResponse(403, ['error' => 'Access denied']);
    }
}

try {
    $poller = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT ns.uid, ns.name, ns.poller_type, pt.central_address
            FROM nagios_server ns
            LEFT JOIN platform_topology pt ON pt.server_id = ns.id
            WHERE ns.id = :id
            SQL,
        QueryParameters::create([QueryParameter::int('id', $pollerId)]),
    );

    if ($poller === false) {
        sendJsonResponse(404, ['error' => 'Poller not found']);
    }

    $pollerUid = new PollerUid((int) $poller['uid']);
    $pollerType = PollerTypeEnum::from($poller['poller_type']);
    $isCloudPlatform = filter_var(
        $_ENV['IS_CLOUD_PLATFORM'] ?? $_SERVER['IS_CLOUD_PLATFORM'] ?? getenv('IS_CLOUD_PLATFORM'),
        FILTER_VALIDATE_BOOLEAN,
    );

    if ($poller['central_address'] === null || $poller['central_address'] === '') {
        sendJsonResponse(400, ['error' => 'No central address configured for this poller']);
    }

    try {
        // central_address is written by the remote-server wizard without format validation;
        // CentralAddress enforces the same invariant as the API endpoint, so no value able
        // to alter the generated shell command can get through.
        $centralAddress = new CentralAddress($poller['central_address']);
    } catch (InvalidArgumentException) {
        sendJsonResponse(400, ['error' => 'Invalid central address configured for this poller']);
    }

    // Mirrors DbalPollerTokenRepository::getFirstValidPollerToken(): oldest valid token first,
    // deliberately. Keep both in sync — this endpoint and
    // GET /configuration/pollers/installation-command/{id} must return the same command.
    $tokenRow = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT token_string, token_name, creation_date, expiration_date, is_revoked
            FROM authentication_tokens
            WHERE type = 'poller'
            AND is_revoked = 0
            AND (expiration_date IS NULL OR expiration_date > :nowEpoch)
            ORDER BY creation_date ASC
            LIMIT 1
            SQL,
        QueryParameters::create([QueryParameter::int('nowEpoch', time())]),
    );

    if ($tokenRow === false) {
        sendJsonResponse(404, ['error' => 'No valid poller token found']);
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

    // This endpoint does not boot the Symfony kernel, so the factory is built by hand
    // over the current request: the scheme and the base URI must be resolved the same way
    // as in the API, or the two commands diverge.
    //
    // The trusted proxies come first, because that is what getScheme() reads
    // x-forwarded-proto from. Booting no kernel means nothing has declared them, so a
    // TLS-terminating proxy would be invisible here and the generated command would offer
    // an http:// download piped into a root shell — app secret, salt and poller token
    // travelling in the clear with it. setTrustedProxies() resolves the REMOTE_ADDR
    // keyword itself; the header set mirrors trusted_headers in framework.yaml.
    $trustedProxies = $_ENV['TRUSTED_PROXIES']
        ?? $_SERVER['TRUSTED_PROXIES']
        ?? getenv('TRUSTED_PROXIES');
    if (! is_string($trustedProxies) || trim($trustedProxies) === '') {
        $trustedProxies = DEFAULT_TRUSTED_PROXIES;
    }

    Request::setTrustedProxies(
        array_values(array_filter(array_map('trim', explode(',', $trustedProxies)), 'strlen')),
        Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PORT
    );

    $requestStack = new RequestStack();
    $requestStack->push(Request::createFromGlobals());

    $factory = new PollerInstallationCommandFactory(
        pollerUid: $pollerUid,
        pollerName: new PollerName($poller['name']),
        pollerType: $pollerType,
        pollerToken: $token,
        appSecret: $engineSecrets->getAppSecret(),
        salt: $engineSecrets->getSalt(),
        isCloudPlatform: $isCloudPlatform,
        centralUrl: (new CentralUrlFactory($requestStack, $isCloudPlatform))->create($centralAddress),
    );

    sendJsonResponse(200, ['command' => $factory->generate()]);
} catch (Throwable $exception) {
    Logger::create(LogChannelEnum::WEB)->error(
        'Failed to generate poller installation command',
        ['poller_id' => $pollerId, 'exception' => $exception],
    );
    sendJsonResponse(500, ['error' => 'Failed to generate installation command']);
}
