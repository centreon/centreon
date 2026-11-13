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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls the native REST API ("/v1") of Gorgone.
 */
abstract class AbstractApiGorgoneRepository
{
    /**
     * @param HttpClientInterface $httpClient
     * @param ConfigurationLoaderApiInterface $configuration
     * @param int $retryPauseMilliseconds pause before a request is sent again
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationLoaderApiInterface $configuration,
        private readonly int $retryPauseMilliseconds = 500,
    ) {
    }

    /**
     * @return int timeout of the Gorgone commands, in seconds
     */
    protected function getCommandTimeout(): int
    {
        return $this->configuration->getCommandTimeout();
    }

    /**
     * Send a request. With $retries, the same request is sent again after a connection error,
     * a timeout or an HTTP 5xx answer: the caller makes it idempotent.
     *
     * @param string $method
     * @param string $path path under "/v1/"
     * @param array<string, mixed> $options
     * @param string $description what is sent, for the error messages
     * @param int $retries
     *
     * @throws GorgoneCommandException
     *
     * @return array{int, array<mixed>} HTTP status code, decoded body (empty when it is not a JSON object)
     */
    protected function call(string $method, string $path, array $options, string $description, int $retries = 0): array
    {
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
            $url = sprintf(
                '%s://%s:%d/v1/%s',
                $this->configuration->isApiConnectionSecure() ? 'https' : 'http',
                $address,
                $this->configuration->getApiPort() ?? 8085,
                $path
            );
        } catch (GorgoneCommandException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            throw GorgoneCommandException::requestFailed($description, $ex);
        }

        for ($attempt = 0; ; $attempt++) {
            $failure = null;
            $statusCode = 0;
            $content = '';

            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
            } catch (\Throwable $ex) {
                $failure = $ex;
            }

            if ($attempt >= $retries || ($failure === null && $statusCode < 500)) {
                break;
            }
            if ($this->retryPauseMilliseconds > 0) {
                usleep($this->retryPauseMilliseconds * 1000);
            }
        }

        if ($failure !== null) {
            throw GorgoneCommandException::requestFailed($description, $failure);
        }

        $body = json_decode($content, true);

        return [$statusCode, is_array($body) ? $body : []];
    }

    /**
     * @param string $description
     * @param int $statusCode
     * @param array<mixed> $body
     *
     * @return GorgoneCommandException
     */
    protected static function createUnexpectedResponse(
        string $description,
        int $statusCode,
        array $body,
    ): GorgoneCommandException {
        $detail = array_filter(
            [$body['error'] ?? null, $body['message'] ?? null],
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        );

        return GorgoneCommandException::unexpectedResponse($description, $statusCode, implode(': ', $detail));
    }
}
