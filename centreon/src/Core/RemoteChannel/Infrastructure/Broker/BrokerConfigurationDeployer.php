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

namespace Core\RemoteChannel\Infrastructure\Broker;

use Core\RemoteChannel\Application\ConfigurationDeployerInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Domain\Model\DeploymentAction;
use Core\RemoteChannel\Domain\Model\DispatchStatus;
use Core\RemoteChannel\Domain\Model\PollerTarget;

/**
 * Deploys the generated configuration through the central broker remote API (POST /v1/deployments, then
 * GET /v1/deployments/{id} until a final status). Every poller is sent first, then their results are awaited
 * together: the total time is close to the slowest poller.
 *
 * Files: engine/<id>/*.{json,cfg} (plugins.json included when a module generated it) and
 * broker/<id>/*.{xml,json,cfg,sql}, same selection as the Gorgone delivery. VMware files are not deployed.
 */
class BrokerConfigurationDeployer implements ConfigurationDeployerInterface
{
    private const DEFAULT_CACHE_DIR = '/var/cache/centreon';

    /**
     * Default upper bound, in seconds, to wait for the final results. Stays under the 60 s HTTP timeout
     * (curl.timeout) of each internal call made by the REST configuration export.
     */
    private const DEFAULT_RESULT_TIMEOUT = 50;
    private const RESULT_TIMEOUT_ENV = 'CENTREON_BROKER_API_RESULT_TIMEOUT';
    private const POLL_INTERVAL_US = 500000;

    /** A deployment POST carries the files: allow more than a status poll */
    private const POST_HTTP_TIMEOUT = 30.0;

    /** @var \Closure(int): void */
    private \Closure $sleep;

    public function __construct(
        private readonly BrokerApiClient $client,
        private readonly ?string $cacheDir = null,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    public function deploy(array $targets, DeploymentAction $action, bool $withFiles): array
    {
        $failures = [];
        /** @var array<string, PollerTarget> $pending by request id */
        $pending = [];
        foreach ($targets as $target) {
            try {
                $requestId = $this->post($target, $action, $withFiles);
                if ($requestId !== null) {
                    $pending[$requestId] = $target;
                }
            } catch (RemoteChannelException $ex) {
                $failures[$target->id] = $ex;
            }
        }

        $deadline = microtime(true) + $this->getResultTimeout();
        while ($pending !== []) {
            foreach ($pending as $requestId => $target) {
                try {
                    if ($this->isFinished((string) $requestId, $target)) {
                        unset($pending[$requestId]);
                    }
                } catch (RemoteChannelException $ex) {
                    $failures[$target->id] = $ex;
                    unset($pending[$requestId]);
                }
            }
            if ($pending === []) {
                break;
            }
            if (microtime(true) >= $deadline) {
                foreach ($pending as $target) {
                    $failures[$target->id] = RemoteChannelException::forPoller(
                        $target->name,
                        DispatchStatus::Timeout,
                        sprintf('no final status within %d seconds', $this->getResultTimeout())
                    );
                }
                break;
            }
            ($this->sleep)(self::POLL_INTERVAL_US);
        }

        return $failures;
    }

    /**
     * @throws RemoteChannelException
     * @return list<array{target: string, path: string, sha256: string, content_b64: string}>
     */
    public function collectFiles(PollerTarget $target): array
    {
        $configDir = rtrim($this->cacheDir ?? (defined('_CENTREON_CACHEDIR_') ? _CENTREON_CACHEDIR_ : self::DEFAULT_CACHE_DIR), '/')
            . '/config/';
        $files = [];
        foreach (
            [
                'engine' => $configDir . 'engine/' . $target->id . '/*.{json,cfg}',
                'broker' => $configDir . 'broker/' . $target->id . '/*.{xml,json,cfg,sql}',
            ] as $component => $pattern
        ) {
            $paths = glob($pattern, GLOB_BRACE) ?: [];
            sort($paths);
            foreach ($paths as $path) {
                if (! is_file($path)) {
                    continue;
                }
                $content = @file_get_contents($path);
                if ($content === false) {
                    throw new RemoteChannelException(sprintf(_('Could not read file "%s"'), $path));
                }
                $files[] = [
                    'target' => $component,
                    'path' => basename($path),
                    'sha256' => hash('sha256', $content),
                    'content_b64' => base64_encode($content),
                ];
            }
        }
        if ($files === []) {
            throw new RemoteChannelException(sprintf(
                _('No generated configuration found for monitoring server "%s": generate it first'),
                $target->name
            ));
        }

        return $files;
    }

    /**
     * @throws RemoteChannelException
     * @return string|null the request id to follow, null when the deployment is already final and successful
     */
    private function post(PollerTarget $target, DeploymentAction $action, bool $withFiles): ?string
    {
        $requestId = BrokerPollerCommandSender::generateRequestId();
        $response = $this->client->call(
            'POST',
            '/v1/deployments',
            [
                'request_id' => $requestId,
                'poller_id' => $target->uid,
                'action' => $action->value,
                'files' => $withFiles ? $this->collectFiles($target) : [],
            ],
            self::POST_HTTP_TIMEOUT
        );
        $status = DispatchStatus::tryFrom((string) ($response['status'] ?? '')) ?? DispatchStatus::Accepted;
        if ($status->isFailure()) {
            throw RemoteChannelException::forPoller($target->name, $status, (string) ($response['message'] ?? ''));
        }

        return (string) ($response['request_id'] ?? $requestId);
    }

    /**
     * @throws RemoteChannelException on a failed final status
     */
    private function isFinished(string $requestId, PollerTarget $target): bool
    {
        $response = $this->client->call('GET', '/v1/deployments/' . rawurlencode($requestId));
        $status = DispatchStatus::tryFrom((string) ($response['status'] ?? '')) ?? DispatchStatus::Accepted;
        if ($status->isFailure()) {
            throw RemoteChannelException::forPoller($target->name, $status, (string) ($response['message'] ?? ''));
        }

        // contracts.md § 5: every action ends at APPLIED (restart: files swapped, engine exit requested)
        return $status === DispatchStatus::Applied;
    }

    private function getResultTimeout(): int
    {
        $timeout = getenv(self::RESULT_TIMEOUT_ENV);

        return is_string($timeout) && ctype_digit($timeout) && (int) $timeout > 0
            ? (int) $timeout
            : self::DEFAULT_RESULT_TIMEOUT;
    }
}
