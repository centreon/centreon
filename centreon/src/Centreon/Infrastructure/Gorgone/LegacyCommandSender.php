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

namespace Centreon\Infrastructure\Gorgone;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use App\Kernel;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Infrastructure\Gorgone\Interfaces\ConfigurationLoaderApiInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends legacy centcore commands (EXTERNALCMD, SENDCFGFILE, RELOAD, RELOADBROKER, SYNCTRAP...) to the
 * Gorgone legacycmd module.
 *
 * Commands targeting a containerized poller (poller_type 'docker') go through the Gorgone HTTP API, with
 * the configuration files embedded in the request so that Gorgone does not need to share the web cache
 * directory. Commands targeting any other poller are written into the centcore directory (or centcore.cmd)
 * as before: Gorgone shares the filesystem of the web server on those platforms.
 *
 * Delivery commands (configuration files, trap database, export files, engine reload/restart) sent through
 * the API are not fire-and-forget: the target node must be connected to Gorgone before anything is sent,
 * then the final result logged by legacycmd under the request token is awaited. A failure, or no result
 * within the timeout, raises a LegacyCommandException instead of reporting a delivery that never happened.
 *
 * @package Centreon\Infrastructure\Gorgone
 */
class LegacyCommandSender
{
    public const COMPONENT_ENGINE = 'engine';
    public const COMPONENT_BROKER = 'broker';
    public const COMPONENT_VMWARE = 'vmware';
    public const COMPONENT_TRAP = 'trap';
    public const COMPONENT_EXPORT = 'export';
    private const URI = 'centreon/legacycmd/command';
    private const DEFAULT_CACHE_DIR = '/var/cache/centreon';
    private const DEFAULT_VARLIB_DIR = '/var/lib/centreon';
    private const POLLER_TYPE_DOCKER = 'docker';
    private const CENTCORE_FILE_SUFFIX = '-externalcommand.cmd';

    /** Sending configuration files can take far longer than the default Gorgone command timeout */
    private const MIN_TIMEOUT = 30;

    /** Same parsing as gorgone::modules::centreon::legacycmd::class::handle_file */
    private const LINE_PATTERN = '/^(.*?):([^:]*)(?::(.*))?$/s';

    /** Commands delivering something to a poller: their final result is awaited */
    private const TRACKED_COMMANDS = [
        'SENDCFGFILE',
        'SENDEXPORTFILE',
        'SYNCTRAP',
        'RELOAD',
        'RESTART',
        'ENGINERELOAD',
        'ENGINERESTART',
    ];

    /**
     * Default upper bound, in seconds, to wait for the final result of a delivery. It must stay under the
     * 60 seconds HTTP timeout (curl.timeout) of each internal call made by the REST configuration export.
     */
    private const DEFAULT_RESULT_TIMEOUT = 50;
    private const RESULT_TIMEOUT_ENV = 'CENTREON_GORGONE_RESULT_TIMEOUT';
    private const RESULT_POLL_INTERVAL_US = 500000;

    /**
     * Default number of pollers whose delivery is awaited at the same time by deliverToPollers(): bounds the
     * configuration files Gorgone holds at once.
     */
    private const DEFAULT_DELIVERY_CONCURRENCY = 10;
    private const DELIVERY_CONCURRENCY_ENV = 'CENTREON_GORGONE_DELIVERY_CONCURRENCY';

    /** Same values as gorgone::standard::constants */
    private const GORGONE_ACTION_FINISH_KO = 1;
    private const GORGONE_ACTION_FINISH_OK = 2;

    private HttpClientInterface $client;

    /** @var array<int, bool> */
    private array $isContainerizedByPollerId = [];

    /**
     * Inside deliverToPollers(): tokens of the tracked commands sent, whose final result is awaited later
     * (null otherwise: the final result is awaited before returning).
     *
     * @var list<string>|null
     */
    private ?array $deferredTokens = null;

    /**
     * Inside deliverToPollers(): connection status of the Gorgone nodes, read once for every poller.
     *
     * @var array<mixed>|null
     */
    private ?array $nodeStatuses = null;

    public function __construct(
        private readonly ConfigurationLoaderApiInterface $configuration,
        private readonly ConnectionInterface $connection,
    ) {
        $this->client = new CurlHttpClient();
    }

    /**
     * Helper for the legacy code (www/, CLAPI, cron) which does not benefit from dependency injection.
     */
    public static function create(): self
    {
        $sender = Kernel::createForWeb()->getContainer()->get(self::class);
        \assert($sender instanceof self);

        return $sender;
    }

    /**
     * Tell whether the poller is containerized (poller_type 'docker'), so that its commands go through
     * the Gorgone API instead of the centcore directory.
     */
    public function isContainerized(int|string $pollerId): bool
    {
        if (! is_numeric($pollerId)) {
            return false;
        }
        $pollerId = (int) $pollerId;
        if (! isset($this->isContainerizedByPollerId[$pollerId])) {
            $pollerType = $this->connection->fetchOne(
                'SELECT poller_type FROM nagios_server WHERE id = :pollerId',
                QueryParameters::create([QueryParameter::int('pollerId', $pollerId)])
            );
            $this->isContainerizedByPollerId[$pollerId] = $pollerType === self::POLLER_TYPE_DOCKER;
        }

        return $this->isContainerizedByPollerId[$pollerId];
    }

    /**
     * Deliver to several pollers at once: the commands of each poller are sent without waiting for the
     * previous poller, then the final results are awaited together (at most CENTREON_GORGONE_DELIVERY_CONCURRENCY
     * pollers at a time, 10 by default). The total time is close to the slowest poller, not the sum.
     *
     * A poller which fails (not connected, failure reported by Gorgone, no result in time) does not stop the
     * delivery to the others.
     *
     * @param array<int, callable(): mixed> $deliveries by poller id, what to send to the poller (calls to
     *                                                  sendConfigurationFiles(), sendCommand()...)
     *
     * @return array<int, LegacyCommandException> by poller id, the failure of each poller not delivered
     */
    public function deliverToPollers(array $deliveries): array
    {
        $concurrency = $this->getDeliveryConcurrency();
        $timeout = $this->getResultTimeout();
        $failures = [];
        /** @var array<string, array{pollerId: int, deadline: float}> $pending by token */
        $pending = [];
        try {
            $this->nodeStatuses = null;
            while ($deliveries !== [] || $pending !== []) {
                while ($deliveries !== [] && $this->countPollers($pending) < $concurrency) {
                    $pollerId = (int) array_key_first($deliveries);
                    $deliver = $deliveries[$pollerId];
                    unset($deliveries[$pollerId]);
                    try {
                        foreach ($this->sendWithoutWaiting($deliver) as $token) {
                            $pending[$token] = ['pollerId' => $pollerId, 'deadline' => microtime(true) + $timeout];
                        }
                    } catch (LegacyCommandException $ex) {
                        $failures[$pollerId] = $ex;
                    }
                }

                $isAnyFinished = false;
                foreach ($pending as $token => ['pollerId' => $pollerId, 'deadline' => $deadline]) {
                    try {
                        if (! $this->isFinished((string) $token, $deadline, $timeout)) {
                            continue;
                        }
                    } catch (LegacyCommandException $ex) {
                        $failures[$pollerId] ??= $ex;
                    }
                    unset($pending[$token]);
                    $isAnyFinished = true;
                }
                if ($pending !== [] && ! $isAnyFinished) {
                    usleep(self::RESULT_POLL_INTERVAL_US);
                }
            }
        } finally {
            $this->nodeStatuses = null;
        }

        return $failures;
    }

    /**
     * One error for the pollers not delivered by deliverToPollers(), naming each of them with its reason.
     * Its code is the reason shared by every failure (RESULT_TIMEOUT: the deliveries may still succeed), or
     * DELIVERY_FAILED when the reasons differ.
     *
     * @param non-empty-array<int, LegacyCommandException> $failures by poller id
     * @param array<int, string> $pollerNames by poller id
     * @param int $pollerCount number of pollers of the delivery
     */
    public static function combineFailures(
        array $failures,
        array $pollerNames,
        int $pollerCount,
    ): LegacyCommandException {
        if ($pollerCount === 1 && count($failures) === 1) {
            // Same error as a single delivery
            return reset($failures);
        }
        $reasons = array_values(array_unique(array_map(
            static fn (LegacyCommandException $ex): int => (int) $ex->getCode(),
            $failures
        )));
        $code = count($reasons) === 1 ? $reasons[0] : GorgoneDeliveryException::DELIVERY_FAILED;

        $details = [];
        foreach ($failures as $pollerId => $ex) {
            $details[] = sprintf('%s: %s', $pollerNames[$pollerId] ?? '#' . $pollerId, $ex->getMessage());
        }

        return new LegacyCommandException(
            sprintf(
                _('%d of %d monitoring servers have not been delivered, the others have: %s'),
                count($failures),
                $pollerCount,
                implode(' | ', $details)
            ),
            $code,
            reset($failures)
        );
    }

    /**
     * Send legacy centcore lines, formatted as "COMMAND:target[:param]".
     *
     * @param string|list<string> $lines one line, or several lines (a string may contain several lines)
     * @param string $centcoreFileSuffix suffix of the file written into the centcore directory for the
     *                                   commands which do not target a containerized poller
     *
     * @throws LegacyCommandException
     * @return string|null the Gorgone token, null when there is nothing to send through the API
     */
    public function sendLines(string|array $lines, string $centcoreFileSuffix = self::CENTCORE_FILE_SUFFIX): ?string
    {
        if (is_string($lines)) {
            $lines = preg_split('/\R/', $lines) ?: [];
        }

        $commands = [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            if (preg_match(self::LINE_PATTERN, $line, $matches) !== 1) {
                throw new LegacyCommandException(sprintf(_('Invalid Gorgone command "%s"'), $line));
            }
            $command = ['command' => $matches[1], 'target' => $matches[2]];
            if (isset($matches[3]) && $matches[3] !== '') {
                $command['param'] = $matches[3];
            }
            $commands[] = $command;
        }

        return $commands === [] ? null : $this->send($commands, $centcoreFileSuffix);
    }

    /**
     * Send one legacy command.
     *
     * @param array<string, list<array{name: string, content: string}>> $files files to embed, by component
     *
     * @throws LegacyCommandException
     * @return string|null the Gorgone token, null when the command has been written into the centcore directory
     */
    public function sendCommand(string $command, int|string $target, ?string $param = null, array $files = []): ?string
    {
        $payload = ['command' => $command, 'target' => (string) $target];
        if ($param !== null && $param !== '') {
            $payload['param'] = $param;
        }
        if ($files !== []) {
            $payload['files'] = $files;
        }

        return $this->send([$payload]);
    }

    /**
     * Send the generated engine, broker and VMware configuration files of a poller (SENDCFGFILE).
     *
     * @throws LegacyCommandException
     */
    public function sendConfigurationFiles(int $pollerId): ?string
    {
        if (! $this->isContainerized($pollerId)) {
            return $this->sendCommand('SENDCFGFILE', $pollerId);
        }
        $configPath = $this->getCacheDir() . '/config/';

        return $this->sendCommand('SENDCFGFILE', $pollerId, null, [
            self::COMPONENT_ENGINE => $this->readFiles($configPath . 'engine/' . $pollerId, '*.{json,cfg}'),
            self::COMPONENT_BROKER => $this->readFiles($configPath . 'broker/' . $pollerId, '*.{xml,json,cfg,sql}'),
            self::COMPONENT_VMWARE => $this->readFiles($configPath . 'vmware/' . $pollerId, '*.json'),
        ]);
    }

    /**
     * Send the centreontrapd SQLite database of a poller (SYNCTRAP).
     *
     * @throws LegacyCommandException
     */
    public function sendTrapDatabase(int $pollerId, string $databaseFile): ?string
    {
        if (! $this->isContainerized($pollerId)) {
            return $this->sendCommand('SYNCTRAP', $pollerId);
        }

        return $this->sendCommand('SYNCTRAP', $pollerId, null, [
            self::COMPONENT_TRAP => [$this->readFile($databaseFile, 'centreontrapd.sdb')],
        ]);
    }

    /**
     * Send the export files of a remote server (SENDEXPORTFILE).
     *
     * @throws LegacyCommandException
     */
    public function sendExportFiles(int $remoteServerId, int $taskId): ?string
    {
        if (! $this->isContainerized($remoteServerId)) {
            return $this->sendCommand('SENDEXPORTFILE', $remoteServerId, (string) $taskId);
        }

        return $this->sendCommand('SENDEXPORTFILE', $remoteServerId, (string) $taskId, [
            self::COMPONENT_EXPORT => $this->readFilesRecursively(
                $this->getCacheDir() . '/config/export/' . $remoteServerId
            ),
        ]);
    }

    /**
     * The export directory contains sub-directories (media...): names are paths relative to the directory.
     *
     * @throws LegacyCommandException
     * @return list<array{name: string, content: string}>
     */
    private function readFilesRecursively(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = substr($file->getPathname(), strlen($directory) + 1);
                $files[] = $this->readFile($file->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
            }
        }

        return $files;
    }

    /**
     * @param list<array<string, mixed>> $commands
     *
     * @throws LegacyCommandException
     * @return string|null the Gorgone token, null when every command has been written into the centcore directory
     */
    private function send(array $commands, string $centcoreFileSuffix = self::CENTCORE_FILE_SUFFIX): ?string
    {
        $apiCommands = [];
        $centcoreLines = [];
        foreach ($commands as $command) {
            if ($this->isContainerized($command['target'])) {
                $apiCommands[] = $command;
            } else {
                // Files are not needed: Gorgone reads them from the shared cache directory
                $centcoreLines[] = $command['command'] . ':' . $command['target']
                    . (isset($command['param']) ? ':' . $command['param'] : '');
            }
        }
        if ($centcoreLines !== []) {
            $this->writeCentcoreLines($centcoreLines, $centcoreFileSuffix);
        }
        if ($apiCommands === []) {
            return null;
        }

        $trackedPollerIds = [];
        foreach ($apiCommands as $command) {
            if (in_array($command['command'], self::TRACKED_COMMANDS, true)) {
                $trackedPollerIds[(int) $command['target']] = true;
            }
        }
        foreach (array_keys($trackedPollerIds) as $pollerId) {
            $this->assertNodeConnected($pollerId);
        }

        $token = $this->sendToApi($apiCommands);
        if ($trackedPollerIds !== []) {
            if ($this->deferredTokens !== null) {
                // deliverToPollers() awaits the final results of every poller together
                $this->deferredTokens[] = $token;
            } else {
                $this->waitForFinalResult($token);
            }
        }

        return $token;
    }

    /**
     * Same rule as the config-delivery script of the container images: at least one ping answered.
     * Every containerized poller is a proxied (pullwss) node, the Central poller (localhost = 1) included.
     *
     * @throws LegacyCommandException
     */
    private function assertNodeConnected(int $pollerId): void
    {
        if ($this->deferredTokens !== null) {
            // deliverToPollers(): one status for every poller
            $nodeStatuses = $this->nodeStatuses ??= $this->fetchNodeStatuses();
        } else {
            $nodeStatuses = $this->fetchNodeStatuses();
        }
        $node = $nodeStatuses[(string) $pollerId] ?? null;
        if (
            ! is_array($node)
            || ((int) ($node['ping_ok'] ?? 0) <= 0 && (int) ($node['last_ping_recv'] ?? 0) <= 0)
        ) {
            throw new LegacyCommandException(sprintf(
                _('Gorgone node %d is not connected: nothing has been sent, retry once the poller is connected'),
                $pollerId
            ), GorgoneDeliveryException::NODE_NOT_CONNECTED);
        }
    }

    /**
     * @param callable(): mixed $deliver
     *
     * @throws LegacyCommandException
     * @return list<string> tokens of the tracked commands sent, whose final result is not awaited
     */
    private function sendWithoutWaiting(callable $deliver): array
    {
        $this->deferredTokens = [];
        try {
            $deliver();

            return $this->deferredTokens;
        } finally {
            $this->deferredTokens = null;
        }
    }

    /**
     * @param array<string, array{pollerId: int, deadline: float}> $pending
     */
    private function countPollers(array $pending): int
    {
        return count(array_unique(array_column($pending, 'pollerId')));
    }

    /**
     * @throws LegacyCommandException
     * @return array<mixed> connection status of the Gorgone nodes, by node id
     */
    private function fetchNodeStatuses(): array
    {
        [, $response] = $this->callApi('GET', 'internal/constatus');

        return is_array($response) && is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /**
     * Wait for the final result logged by legacycmd under the request token (legacycmd_final = 1).
     *
     * @throws LegacyCommandException
     */
    private function waitForFinalResult(string $token): void
    {
        $timeout = $this->getResultTimeout();
        $deadline = microtime(true) + $timeout;
        while (! $this->isFinished($token, $deadline, $timeout)) {
            usleep(self::RESULT_POLL_INTERVAL_US);
        }
    }

    /**
     * Tell whether legacycmd logged the final result under the request token (legacycmd_final = 1).
     * Any failure logged under that token, by legacycmd or by one of its sub-actions, is reported at once.
     *
     * @throws LegacyCommandException on failure, or once the deadline is reached without final result
     */
    private function isFinished(string $token, float $deadline, int $timeout): bool
    {
        [, $response] = $this->callApi('GET', 'log/' . rawurlencode($token));
        $entries = is_array($response) && is_array($response['data'] ?? null) ? $response['data'] : [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $data = $entry['data'] ?? null;
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            $code = (int) ($entry['code'] ?? -1);
            if ($code === self::GORGONE_ACTION_FINISH_KO) {
                throw new LegacyCommandException(sprintf(
                    _('Gorgone could not deliver the command: %s'),
                    is_array($data) && is_scalar($data['message'] ?? null) ? (string) $data['message'] : $token
                ), GorgoneDeliveryException::DELIVERY_FAILED);
            }
            if (
                $code === self::GORGONE_ACTION_FINISH_OK
                && is_array($data)
                && (int) ($data['legacycmd_final'] ?? 0) === 1
            ) {
                return true;
            }
        }
        if (microtime(true) >= $deadline) {
            throw new LegacyCommandException(sprintf(
                _('Gorgone did not confirm the delivery within %d seconds (token %s)'),
                $timeout,
                $token
            ), GorgoneDeliveryException::RESULT_TIMEOUT);
        }

        return false;
    }

    private function getResultTimeout(): int
    {
        $timeout = getenv(self::RESULT_TIMEOUT_ENV);

        return is_string($timeout) && ctype_digit($timeout) && (int) $timeout > 0
            ? (int) $timeout
            : self::DEFAULT_RESULT_TIMEOUT;
    }

    private function getDeliveryConcurrency(): int
    {
        $concurrency = getenv(self::DELIVERY_CONCURRENCY_ENV);

        return is_string($concurrency) && ctype_digit($concurrency) && (int) $concurrency > 0
            ? (int) $concurrency
            : self::DEFAULT_DELIVERY_CONCURRENCY;
    }

    /**
     * @param list<string> $lines
     * @param string $fileSuffix
     *
     * @throws LegacyCommandException
     */
    private function writeCentcoreLines(array $lines, string $fileSuffix): void
    {
        $varlib = rtrim(defined('_CENTREON_VARLIB_') ? _CENTREON_VARLIB_ : self::DEFAULT_VARLIB_DIR, '/');
        $centcoreFile = is_dir($varlib . '/centcore')
            ? $varlib . '/centcore/' . hrtime(true) . $fileSuffix
            : $varlib . '/centcore.cmd';

        if (@file_put_contents($centcoreFile, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new LegacyCommandException(_('Could not write into centcore.cmd. Please check file permissions.'));
        }
    }

    /**
     * @param list<array<string, mixed>> $commands
     *
     * @throws LegacyCommandException
     */
    private function sendToApi(array $commands): string
    {
        [$statusCode, $jsonResponse, $content] = $this->callApi('POST', self::URI, $commands);
        if ($statusCode !== 200 || ! is_array($jsonResponse) || ! isset($jsonResponse['token'])) {
            throw new LegacyCommandException(sprintf(
                _('Gorgone rejected the command (HTTP %d): %s'),
                $statusCode,
                is_array($jsonResponse) && isset($jsonResponse['message']) ? $jsonResponse['message'] : $content
            ));
        }

        return (string) $jsonResponse['token'];
    }

    /**
     * @param list<array<string, mixed>>|null $json
     *
     * @throws LegacyCommandException
     * @return array{int, mixed, string} HTTP status code, decoded JSON body, raw body
     */
    private function callApi(string $method, string $path, ?array $json = null): array
    {
        $isCertificateShouldBeVerify = $this->configuration->isSecureConnectionSelfSigned() === false;
        $options = [
            'timeout' => max($this->configuration->getCommandTimeout(), self::MIN_TIMEOUT),
            'verify_peer' => $isCertificateShouldBeVerify,
            'verify_host' => $isCertificateShouldBeVerify,
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }
        if ($this->configuration->getApiUsername() !== null) {
            $options['auth_basic'] = $this->configuration->getApiUsername() . ':'
                . $this->configuration->getApiPassword();
        }
        $uri = sprintf(
            '%s://%s:%d/api/%s',
            $this->configuration->isApiConnectionSecure() ? 'https' : 'http',
            $this->configuration->getApiIpAddress(),
            $this->configuration->getApiPort(),
            $path
        );

        try {
            $response = $this->client->request($method, $uri, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (\Throwable $ex) {
            throw new LegacyCommandException(
                sprintf(_('Could not send command to Gorgone (%s): %s'), $uri, $ex->getMessage()),
                0,
                $ex
            );
        }

        return [$statusCode, json_decode($content, true), $content];
    }

    /**
     * @throws LegacyCommandException
     * @return list<array{name: string, content: string}>
     */
    private function readFiles(string $directory, string $pattern): array
    {
        $files = [];
        foreach (glob($directory . '/' . $pattern, GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                $files[] = $this->readFile($file, basename($file));
            }
        }

        return $files;
    }

    /**
     * @throws LegacyCommandException
     * @return array{name: string, content: string}
     */
    private function readFile(string $file, string $name): array
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            throw new LegacyCommandException(sprintf(_('Could not read file "%s"'), $file));
        }

        return ['name' => $name, 'content' => base64_encode($content)];
    }

    private function getCacheDir(): string
    {
        return rtrim(defined('_CENTREON_CACHEDIR_') ? _CENTREON_CACHEDIR_ : self::DEFAULT_CACHE_DIR, '/');
    }
}
