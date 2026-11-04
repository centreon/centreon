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

    private HttpClientInterface $client;

    /** @var array<int, bool> */
    private array $isContainerizedByPollerId = [];

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

        return $apiCommands === [] ? null : $this->sendToApi($apiCommands);
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
        $isCertificateShouldBeVerify = $this->configuration->isSecureConnectionSelfSigned() === false;
        $options = [
            'json' => $commands,
            'timeout' => max($this->configuration->getCommandTimeout(), self::MIN_TIMEOUT),
            'verify_peer' => $isCertificateShouldBeVerify,
            'verify_host' => $isCertificateShouldBeVerify,
        ];
        if ($this->configuration->getApiUsername() !== null) {
            $options['auth_basic'] = $this->configuration->getApiUsername() . ':'
                . $this->configuration->getApiPassword();
        }
        $uri = sprintf(
            '%s://%s:%d/api/%s',
            $this->configuration->isApiConnectionSecure() ? 'https' : 'http',
            $this->configuration->getApiIpAddress(),
            $this->configuration->getApiPort(),
            self::URI
        );

        try {
            $response = $this->client->request('POST', $uri, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (\Throwable $ex) {
            throw new LegacyCommandException(
                sprintf(_('Could not send command to Gorgone (%s): %s'), $uri, $ex->getMessage()),
                0,
                $ex
            );
        }

        $jsonResponse = json_decode($content, true);
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
