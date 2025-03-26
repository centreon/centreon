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

namespace Core\Common\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

abstract class AbstractVaultRepository
{
    use LoggerTrait;
    public const HOST_VAULT_PATH = 'monitoring/hosts';
    public const SERVICE_VAULT_PATH = 'monitoring/services';
    public const KNOWLEDGE_BASE_PATH = 'configuration/knowledge_base';
    public const POLLER_MACRO_VAULT_PATH = 'monitoring/pollerMacros';
    public const OPEN_ID_CREDENTIALS_VAULT_PATH = 'configuration/openid';
    public const GORGONE_VAULT_PATH = 'configuration/gorgone';
    public const DATABASE_VAULT_PATH = 'database';
    public const BROKER_VAULT_PATH = 'configuration/broker';
    public const ACC_VAULT_PATH = 'configuration/additionalConnectorConfigurations';
    protected const DEFAULT_SCHEME = 'https';

    /** @var string[] */
    protected array $availablePaths = [
        self::HOST_VAULT_PATH,
        self::SERVICE_VAULT_PATH,
        self::KNOWLEDGE_BASE_PATH,
        self::POLLER_MACRO_VAULT_PATH,
        self::OPEN_ID_CREDENTIALS_VAULT_PATH,
        self::DATABASE_VAULT_PATH,
        self::BROKER_VAULT_PATH,
        self::ACC_VAULT_PATH,
        self::GORGONE_VAULT_PATH,
    ];

    protected ?VaultConfiguration $vaultConfiguration;

    protected string $customPath = '';

    public function __construct(
        protected ReadVaultConfigurationRepositoryInterface $configurationRepository,
        protected AmpHttpClient $httpClient
    ) {
        $this->vaultConfiguration = $configurationRepository->find();
    }

    public function isVaultConfigured(): bool
    {
        return $this->vaultConfiguration !== null;
    }

    public function setCustomPath(string $customPath): void
    {
        if (! in_array($customPath, $this->availablePaths, true)) {
            $this->error("Invalid custom vault path '{$customPath}'");

            throw new \LogicException("Invalid custom vault path '{$customPath}'");
        }
        $this->customPath = $customPath;
    }

    public function addAvailablePath(string $path): void
    {
        $this->availablePaths[] = $path;
    }

    /**
     * Connect to vault to get an authenticationToken.
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getAuthenticationToken(): string
    {
        try {
            $vaultConfiguration = $this->vaultConfiguration ?? throw new \LogicException();
        } catch (\LogicException $exception) {
            $this->error('There is a technical problem in ' . static::class . ' about the vaultConfiguration');

            throw $exception;
        }

        try {
            $url = $vaultConfiguration->getAddress() . ':'
                . $vaultConfiguration->getPort() . '/v1/auth/approle/login';
            $url = sprintf('%s://%s', self::DEFAULT_SCHEME, $url);
            $body = [
                'role_id' => $vaultConfiguration->getRoleId(),
                'secret_id' => $vaultConfiguration->getSecretId(),
            ];
            $this->info('Authenticating to Vault: ' . $url);
            $loginResponse = $this->httpClient->request('POST', $url, ['json' => $body]);

            $content = json_decode($loginResponse->getContent(), true);
        } catch (\Exception $ex) {
            $this->error($url . ' did not respond with a 2XX status');

            throw $ex;
        }
        /** @var array{auth?:array{client_token?:string}} $content */
        if (! isset($content['auth']['client_token'])) {
            $this->error($url . ' Unable to retrieve client token from Vault');

            throw new \Exception('Unable to authenticate to Vault');
        }

        return $content['auth']['client_token'];
    }

    protected function buildUrl(string $uuid): string
    {
        if (! $this->vaultConfiguration) {
            $this->error('VaultConfiguration is not defined');

            throw new \LogicException();
        }
        $url = $this->vaultConfiguration->getAddress() . ':' . $this->vaultConfiguration->getPort()
            . '/v1/' . $this->vaultConfiguration->getRootPath() . '/data/' . $this->customPath . '/' . $uuid;

        return sprintf('%s://%s', self::DEFAULT_SCHEME, $url);
    }

    protected function buildPath(string $uuid, string $credentialName): string
    {
        if (! $this->vaultConfiguration) {
            $this->error('VaultConfiguration is not defined');

            throw new \LogicException();
        }

        return 'secret::'. $this->vaultConfiguration->getName() . '::' . $this->vaultConfiguration->getRootPath()
            . '/data/' . $this->customPath . '/' . $uuid . '::' . $credentialName;
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<mixed> $data
     *
     * @throws \Exception
     *
     * @return array<mixed>
     */
    protected function sendRequest(string $method, string $url, ?array $data = null): array
    {
        $clientToken = $this->getAuthenticationToken();

        $this->info(
            'Sending request to vault',
            [
                'method' => $method,
                'url' => $url,
                'data' => $data,
            ]
        );

        $options = [
            'headers' => ['X-Vault-Token' => $clientToken],
        ];
        if ($method === 'POST') {
            $options['json'] = ['data' => $data];
        }

        $response = $this->httpClient->request(
            $method,
            $url,
            $options
        );

        $this->info(
            'Request succesfully send to vault',
            [
                'method' => $method,
                'url' => $url,
                'data' => $data,
            ]
        );

        if (
            $response->getStatusCode() !== Response::HTTP_NO_CONTENT
            && $response->getStatusCode() !== Response::HTTP_OK
        ) {

            throw new \Exception('Error ' . $response->getStatusCode());
        }

        if ($method === 'DELETE') {

            return [];
        }

        return $response->toArray();
    }

    /**
     *
     * @param string $method
     * @param array<string, string> $urls urls indexed by ResourceUUID
     * @param null|array $data
     * @return array<string, array<string,string>> vault content indexed by ResourceUUID
     * @throws \Exception
     * @throws \Error
     * @throws TransportExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function sendMultiplexedRequest(string $method, array $urls, ?array $data = null): array
    {
            $clientToken = $this->getAuthenticationToken();
            $options = [
                'headers' => ['X-Vault-Token' => $clientToken],
            ];
            if ($method === 'POST') {
                $options['json'] = ['data' => $data];
            }

            $responses = [];
            $responseData = [];
            foreach ($urls as $uuid => $url) {
                $responseData[$uuid] = [];
                try {
                    $responses[] = $this->httpClient->request($method, $url, $options);
                } catch (TransportExceptionInterface $ex) {
                    $this->error(
                        'Error while sending multiplexed request to vault, process continue',
                        ['url' => $url, 'exception' => $ex,]
                    );

                    continue;

                }
            }

            foreach ($this->httpClient->stream($responses) as $response => $chunk) {
                try {
                    if ($chunk->isFirst()) {
                        if ($response->getStatusCode() !== Response::HTTP_OK) {
                            $this->error('Error HTTP CODE:' . $response->getStatusCode());
                            continue;
                        }
                    }
                    if ($chunk->isLast()) {
                        foreach (array_keys($responseData) as $uuid) {
                            if (strpos($response->getInfo('url'), $uuid) !== false) {
                                $responseData[$uuid] = $response->toArray();
                            }
                        }
                    }
                } catch (\Exception $ex) {
                    $this->error(
                        'Error while processing multiplexed request to vault, process continue',
                        ['exception' => $ex,]
                    );

                    continue;
                }
            }

            return $responseData;
    }
}
