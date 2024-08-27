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

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations\Factory;

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderConfigurationDto;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderConfigurationDtoFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\OpenIdConfigurationException;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OpenIdProviderDtoFactory implements ProviderConfigurationDtoFactoryInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository
    ) {

    }

    /**
     * @inheritDoc
     */
    public function supports(string $type): bool
    {
        return $type === Provider::OPENID;
    }

    /**
     * @inheritDoc
     */
    public function createResponse(Configuration $configuration): ProviderConfigurationDto
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();
        $dto = new ProviderConfigurationDto();
        $dto->id = $configuration->getId();
        $dto->type = $configuration->getType();
        $dto->name = $configuration->getName();
        $dto->authenticationUri = $this->buildAuthenticationUri($customConfiguration);
        $dto->isActive = $configuration->isActive();
        $dto->isForced = $configuration->isForced();

        return $dto;
    }

    /**
     * @param CustomConfiguration $customConfiguration
     *
     * @throws OpenIdConfigurationException
     * @throws \Throwable
     *
     * @return string
     */
    private function buildAuthenticationUri(CustomConfiguration $customConfiguration): string
    {
        $redirectUri = $customConfiguration->getRedirectUrl() !== null
            ? $customConfiguration->getRedirectUrl() . $this->urlGenerator->generate(
                'centreon_security_authentication_login_openid',
                [],
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            : $this->urlGenerator->generate(
                'centreon_security_authentication_login_openid',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        if (
            $customConfiguration->getBaseUrl() === null
            || $customConfiguration->getClientId() === null
            || $customConfiguration->getAuthorizationEndpoint() === null
        ) {
            return '';
        }

        if (
            $this->readVaultConfigurationRepository->exists()
            && str_starts_with($customConfiguration->getClientId(), VaultConfiguration::VAULT_PATH_PATTERN)
        ) {
            $openIDCredentialsFromVault = $this->readVaultRepository->findFromPath($customConfiguration->getClientId());
            if (! array_key_exists(VaultConfiguration::OPENID_CLIENT_ID_KEY, $openIDCredentialsFromVault)) {
                throw OpenIdConfigurationException::unableToRetrieveCredentialFromVault(
                    VaultConfiguration::OPENID_CLIENT_ID_KEY
                );
            }
            $customConfiguration->setClientId($openIDCredentialsFromVault[VaultConfiguration::OPENID_CLIENT_ID_KEY]);
        }

        $authenticationUriParts = [
            'client_id' => $customConfiguration->getClientId(),
            'response_type' => 'code',
            'redirect_uri' => rtrim($redirectUri, '/'),
            'state' => uniqid(),
        ];

        $authorizationEndpointBase = parse_url(
            $customConfiguration->getAuthorizationEndpoint(),
            PHP_URL_PATH
        );
        $authorizationEndpointParts = parse_url(
            $customConfiguration->getAuthorizationEndpoint(),
            PHP_URL_QUERY
        );
        if ($authorizationEndpointBase === false || $authorizationEndpointParts === false) {
            throw new \ValueError(_('Unable to parse authorization url'));
        }

        $queryParams = http_build_query($authenticationUriParts);
        if ($authorizationEndpointParts !== null) {
            $queryParams .= '&' . $authorizationEndpointParts;
        }

        return $customConfiguration->getBaseUrl() . '/'
            . ltrim($authorizationEndpointBase ?? '', '/')
            . '?' . $queryParams
            . (! empty($customConfiguration->getConnectionScopes())
                ? '&scope=' . implode('%20', $customConfiguration->getConnectionScopes())
                : ''
            );
    }
}
