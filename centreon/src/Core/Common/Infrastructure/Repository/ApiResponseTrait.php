<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Common\Infrastructure\Repository;

use Centreon\Domain\Repository\RepositoryException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait ApiResponseTrait
{
    /**
     * @param ResponseInterface $response
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws RepositoryException
     *
     * @return array<mixed>
     */
    public function getResponseOrFail(ResponseInterface $response): array
    {
        $httpStatusCode = $response->getStatusCode();
        $responseBody = json_decode($response->getContent(false), true);
        if ($httpStatusCode !== Response::HTTP_OK) {
            if (is_array($responseBody) && array_key_exists('message', $responseBody)) {
                $errorMessage = sprintf('%s (%d)', $responseBody['message'], $httpStatusCode);
            } else {
                $errorMessage = $httpStatusCode;
            }
            if (isset($this->logger) && $this->logger instanceof LoggerInterface) {
                $this->logger->debug('API error', [
                    'http_code' => $httpStatusCode,
                    'message' => $errorMessage,
                ]);
            }

            throw new RepositoryException('Request error: ' . $errorMessage, $httpStatusCode);
        }

        if (! is_array($responseBody)) {
            throw new RepositoryException('No body response');
        }

        return $responseBody;
    }
}
