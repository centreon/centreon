<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Security\Authentication\Infrastructure\Provider;

use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Security\Domain\Authentication\Exceptions\ProviderException;

class ProviderAuthenticationFactory implements ProviderAuthenticationFactoryInterface
{
    /**
     * @param Local $local
     * @param OpenId $openId
     * @param WebSSO $webSSO
     * @param SAML $saml
     * @param ReadConfigurationRepositoryInterface $readConfigurationRepository
     */
    public function __construct(
        private readonly Local $local,
        private readonly OpenId $openId,
        private readonly WebSSO $webSSO,
        private readonly SAML $saml,
        private ReadConfigurationRepositoryInterface $readConfigurationRepository
    ) {
    }

    /**
     * @param string $providerName
     * @return ProviderAuthenticationInterface
     * @throws ProviderException
     */
    public function create(string $providerType): ProviderAuthenticationInterface
    {
        $provider = match ($providerType) {
            Provider::LOCAL => $this->local,
            Provider::OPENID => $this->openId,
            Provider::WEB_SSO => $this->webSSO,
            Provider::SAML => $this->saml,
            default => throw ProviderException::providerConfigurationNotFound($providerType)
        };

        $provider->setConfiguration($this->readConfigurationRepository->getConfigurationByType($providerType));

        return $provider;
    }
}
