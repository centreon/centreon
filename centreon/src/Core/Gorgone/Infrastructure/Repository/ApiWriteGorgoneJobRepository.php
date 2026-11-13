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

use Centreon\Infrastructure\Gorgone\Interfaces\ConfigurationLoaderApiInterface;
use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Application\Repository\WriteGorgoneJobRepositoryInterface;
use Core\Gorgone\Domain\Model\TarArchive;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Submits jobs to the native REST API ("/v1") of Gorgone.
 */
final class ApiWriteGorgoneJobRepository implements WriteGorgoneJobRepositoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationLoaderApiInterface $configuration,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function submitEngineCommands(int $nodeId, array $commands): string
    {
        return $this->request(
            'POST',
            $nodeId,
            'jobs',
            ['json' => ['engine_commands' => ['commands' => $commands]]],
            'engine commands'
        );
    }

    /**
     * @inheritDoc
     */
    public function submitServiceControl(int $nodeId, string $service, string $operation): string
    {
        return $this->request(
            'POST',
            $nodeId,
            'jobs',
            ['json' => ['service_control' => ['service' => $service, 'operation' => $operation]]],
            "{$operation} of {$service}"
        );
    }

    /**
     * @inheritDoc
     */
    public function pushConfiguration(int $nodeId, string $target, TarArchive $archive): string
    {
        return $this->request(
            'PUT',
            $nodeId,
            'config/' . rawurlencode($target),
            ['headers' => ['Content-Type' => 'application/x-tar'], 'body' => $archive->toString()],
            "{$target} configuration"
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
    private function request(string $method, int $nodeId, string $path, array $options, string $description): string
    {
        $description = sprintf('%s for poller #%d', $description, $nodeId);

        try {
            $address = $this->configuration->getApiIpAddress();
            if ($address === null || $address === '') {
                throw GorgoneCommandException::missingApiAddress();
            }

            $isCertificateVerified = $this->configuration->isSecureConnectionSelfSigned() === false;
            $options += [
                'timeout' => $this->configuration->getCommandTimeout(),
                'verify_peer' => $isCertificateVerified,
                'verify_host' => $isCertificateVerified,
            ];
            $username = $this->configuration->getApiUsername();
            if ($username !== null && $username !== '') {
                $options['auth_basic'] = [$username, (string) $this->configuration->getApiPassword()];
            }

            $response = $this->httpClient->request(
                $method,
                sprintf(
                    '%s://%s:%d/v1/nodes/%d/%s',
                    $this->configuration->isApiConnectionSecure() ? 'https' : 'http',
                    $address,
                    $this->configuration->getApiPort() ?? 8085,
                    $nodeId,
                    $path
                ),
                $options
            );
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (GorgoneCommandException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            throw GorgoneCommandException::requestFailed($description, $ex);
        }

        $body = json_decode($content, true);
        $body = is_array($body) ? $body : [];

        if ($statusCode === 404) {
            throw GorgoneCommandException::unknownNode($nodeId);
        }

        $jobId = $body['id'] ?? null;
        if ($statusCode < 200 || $statusCode >= 300 || ! is_scalar($jobId) || (string) $jobId === '') {
            $detail = array_filter(
                [$body['error'] ?? null, $body['message'] ?? null],
                static fn (mixed $value): bool => is_string($value) && $value !== ''
            );

            throw GorgoneCommandException::unexpectedResponse($description, $statusCode, implode(': ', $detail));
        }

        return (string) $jobId;
    }
}
