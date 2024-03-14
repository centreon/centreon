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

declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Infrastructure\Repository;

use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Exception\Http\InvalidContentException;
use Core\Security\ProviderConfiguration\Domain\Exception\Http\InvalidResponseException;
use Core\Security\ProviderConfiguration\Domain\Exception\Http\InvalidStatusCodeException;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\OpenIdCustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\Repository\ReadAttributePathRepositoryInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpReadAttributePathRepository implements ReadAttributePathRepositoryInterface
{
    /**
     * @param HttpClientInterface $client
     */
    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    /**
     * @param string $url
     * @param string $token
     * @param Configuration $configuration
     * @param string $endpointType
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws SSOAuthenticationException
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     *
     * @return array<mixed>
     */
    public function getData(string $url, string $token, Configuration $configuration, string $endpointType): array
    {
        try {
            $response = $this->getResponseOrFail($url, $token, $configuration, $endpointType);
            $this->statusCodeIsValidOrFail($response);

            return $this->getContentOrFail($response);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    /**
     * @param string $url
     * @param string $token
     * @param Configuration $configuration
     * @param string $endpointType
     *
     * @throws SSOAuthenticationException
     * @throws TransportExceptionInterface
     * @throws Exception
     *
     * @return ResponseInterface
     */
    private function getResponseOrFail(
        string $url,
        string $token,
        Configuration $configuration,
        string $endpointType
    ): ResponseInterface {
        $customConfiguration = $configuration->getCustomConfiguration();
        if (! $customConfiguration instanceof OpenIdCustomConfigurationInterface) {
            throw ConfigurationException::unexpectedCustomConfiguration($customConfiguration::class);
        }
        $headers = ['Authorization' => 'Bearer ' . trim($token)];
        $options = ['verify_peer' => $customConfiguration->verifyPeer(), 'headers' => $headers];
        if ($endpointType !== Endpoint::CUSTOM) {
            $body = [
                'token' => $token,
                'client_id' => $customConfiguration->getClientId(),
                'client_secret' => $customConfiguration->getClientSecret(),
            ];
            $options['body'] = $body;
        }

        try {
            $response = $this->client->request($this->getHttpMethodFromEndpointType($endpointType), $url, $options);
        } catch (Exception) {
            throw new InvalidResponseException();
        }

        return $response;
    }

    /**
     * @param ResponseInterface $response
     *
     * @throws TransportExceptionInterface
     */
    private function statusCodeIsValidOrFail(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode !== Response::HTTP_OK) {
            throw new InvalidStatusCodeException('Invalid status code received', $statusCode);
        }
    }

    /**
     * @param ResponseInterface $response
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     *
     * @return array<mixed>
     */
    private function getContentOrFail(ResponseInterface $response): array
    {
        $content = $response->getContent(false);
        $content = json_decode($content, true);
        if (empty($content) || ! is_array($content) || array_key_exists('error', $content)) {
            throw InvalidContentException::createWithContent($content);
        }

        return $content;
    }

    /**
     * Get the HTTP Method from Endpoint Type.
     *
     * @param string $endpointType
     *
     * @return string
     */
    private function getHttpMethodFromEndpointType(string $endpointType): string
    {
        return $endpointType === Endpoint::INTROSPECTION ? 'POST' : 'GET';
    }
}
