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
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractVaultRepository
{
    use LoggerTrait;
    private const DEFAULT_SCHEME = 'https';

    protected ?VaultConfiguration $vaultConfiguration;

    public function __construct(
        protected ReadVaultConfigurationRepositoryInterface $configurationRepository,
        protected HttpClientInterface $httpClient
    ) {
        $this->vaultConfiguration = $configurationRepository->findDefaultVaultConfiguration();
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
            $url = $this->vaultConfiguration->getAddress() . ':'
                . $this->vaultConfiguration->getPort() . '/v1/auth/approle/login';
            $url = sprintf('%s://%s', self::DEFAULT_SCHEME, $url);
            $body = [
                'role_id' => $this->vaultConfiguration->getRoleId(),
                'secret_id' => $this->vaultConfiguration->getSecretId(),
            ];
            $this->info('Authenticating to Vault: ' . $url);
            $loginResponse = $this->httpClient->request('POST', $url, ['json' => $body]);
            $content = json_decode($loginResponse->getContent(), true);
        } catch (\Exception $ex) {
            $this->error($url . ' did not respond with a 2XX status');

            throw $ex;
        }

        if (! isset($content['auth']['client_token'])) {
            $this->error($url . ' Unable to retrieve client token from Vault');

            throw new \Exception('Unable to authenticate to Vault');
        }

        return $content['auth']['client_token'];
    }
}
