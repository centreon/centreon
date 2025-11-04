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

namespace Core\Security\Authentication\Infrastructure\Provider;

use Core\Common\Domain\Exception\RepositoryException;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Security\Domain\Authentication\Exceptions\ProviderException;

readonly class ProviderAuthenticationFactory implements ProviderAuthenticationFactoryInterface
{
    public function __construct(
        private Local $local,
        private OpenId $openId,
        private WebSSO $webSSO,
        private SAML $saml,
        private ReadConfigurationRepositoryInterface $readConfigurationRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(string $providerType): ProviderAuthenticationInterface
    {
        $provider = match ($providerType) {
            Provider::LOCAL => $this->local,
            Provider::OPENID => $this->openId,
            Provider::WEB_SSO => $this->webSSO,
            Provider::SAML => $this->saml,
            default => throw ProviderException::providerConfigurationNotFound($providerType),
        };

        try {
            $provider->setConfiguration($this->readConfigurationRepository->getConfigurationByType($providerType));
        } catch (RepositoryException $e) {
            throw ProviderException::findProviderConfiguration($providerType, $e);
        }

        return $provider;
    }
}
