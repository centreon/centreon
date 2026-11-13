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

namespace Core\Gorgone\Infrastructure\Repository;

use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Application\Repository\WriteGorgoneJobRepositoryInterface;
use Core\Gorgone\Domain\Model\TarArchive;

/**
 * Submits jobs to the native REST API ("/v1") of Gorgone.
 *
 * Each job is submitted with an id chosen here: Gorgone answers with the existing job when it
 * gets the same id twice, which makes sending a request again safe.
 */
final class ApiWriteGorgoneJobRepository extends AbstractApiGorgoneRepository implements WriteGorgoneJobRepositoryInterface
{
    private const RETRIES = 1;

    /**
     * @inheritDoc
     */
    public function submitEngineCommands(int $nodeId, array $commands): string
    {
        return $this->submit(
            'POST',
            $nodeId,
            'jobs',
            ['json' => ['id' => self::createJobId(), 'engine_commands' => ['commands' => $commands]]],
            'engine commands'
        );
    }

    /**
     * @inheritDoc
     */
    public function submitServiceControl(
        int $nodeId,
        string $service,
        string $operation,
        array $requiresLatestConfig = [],
    ): string {
        $job = [
            'id' => self::createJobId(),
            'service_control' => ['service' => $service, 'operation' => $operation],
        ];
        if ($requiresLatestConfig !== []) {
            $job['requires_latest_config'] = $requiresLatestConfig;
        }

        return $this->submit('POST', $nodeId, 'jobs', ['json' => $job], "{$operation} of {$service}");
    }

    /**
     * @inheritDoc
     */
    public function pushConfiguration(int $nodeId, string $target, TarArchive $archive): string
    {
        return $this->submit(
            'PUT',
            $nodeId,
            'config/' . rawurlencode($target),
            [
                'query' => ['id' => self::createJobId()],
                'headers' => ['Content-Type' => 'application/x-tar'],
                'body' => $archive->toString(),
            ],
            "{$target} configuration"
        );
    }

    /**
     * @inheritDoc
     */
    public function validateConfiguration(int $nodeId, string $target, TarArchive $archive): string
    {
        return $this->submit(
            'POST',
            $nodeId,
            'config/' . rawurlencode($target) . '/validation',
            [
                'query' => ['id' => self::createJobId()],
                'headers' => ['Content-Type' => 'application/x-tar'],
                'body' => $archive->toString(),
            ],
            "validation of the {$target} configuration"
        );
    }

    /**
     * @param string $method
     * @param int $nodeId
     * @param string $path path under "/v1/nodes/<node id>/"
     * @param array<string, mixed> $options
     * @param string $description what is sent, for the error messages
     *
     * @throws GorgoneCommandException
     *
     * @return string job id
     */
    private function submit(string $method, int $nodeId, string $path, array $options, string $description): string
    {
        $description = sprintf('%s for poller #%d', $description, $nodeId);

        [$statusCode, $body] = $this->call(
            $method,
            sprintf('nodes/%d/%s', $nodeId, $path),
            $options,
            $description,
            self::RETRIES
        );

        if ($statusCode === 404) {
            throw GorgoneCommandException::unknownNode($nodeId);
        }

        $jobId = $body['id'] ?? null;
        if ($statusCode < 200 || $statusCode >= 300 || ! is_scalar($jobId) || (string) $jobId === '') {
            throw self::createUnexpectedResponse($description, $statusCode, $body);
        }

        return (string) $jobId;
    }

    /**
     * @return string 32 hexadecimal characters
     */
    private static function createJobId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
