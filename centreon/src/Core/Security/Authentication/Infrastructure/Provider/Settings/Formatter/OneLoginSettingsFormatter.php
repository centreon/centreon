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

namespace Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter;

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OneLoginSettingsFormatter implements SettingsFormatterInterface
{
    use HttpUrlTrait, VaultTrait;

    /**
     * @param UrlGeneratorInterface $urlGenerator
     * @param ReadVaultRepositoryInterface $readVaultRepository
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReadVaultRepositoryInterface $readVaultRepository
    ) {

    }

    /**
     * @param CustomConfigurationInterface&CustomConfiguration $customConfiguration
     *
     * @return array<mixed>
     */
    public function format(CustomConfigurationInterface $customConfiguration): array
    {
        $acsUrl = $this->urlGenerator->generate(
            'centreon_application_authentication_saml_acs',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return [
            'strict' => true,
            'debug' => true,
            'sp' => [
                'entityId' => $this->getHost(true),
                'assertionConsumerService' => [
                    'url' => $acsUrl,
                ],
            ],
            'idp' => [
                'entityId' => $customConfiguration->getEntityIDUrl(), // issuer
                'singleSignOnService' => [
                    'url' => $customConfiguration->getRemoteLoginUrl(),
                ],
                'singleLogoutService' => [
                    'url' => $customConfiguration->getLogoutFromUrl(),
                ],
                'x509cert' => $this->retrievePublicCertificate($customConfiguration->getPublicCertificate()),
            ],
            'security' => [
                'requestedAuthnContextComparison' => 'minimum',
            ],
        ];
    }

    /**
     * Retrieve the public certificate from vault if the provided public certificate is a vault path.
     *
     * @param string $publicCertificate the public certificate to retrieve
     *
     * @return string the public certificate
     *
     * @throws \Throwable
     */
    private function retrievePublicCertificate(string $publicCertificate): string
    {
        if (! $this->isAVaultPath($publicCertificate)) {
            return $publicCertificate;
        }

        $vaultData = $this->readVaultRepository->findFromPath($publicCertificate);

        return $vaultData[VaultConfiguration::SAML_PUBLIC_CERTIFICATE_KEY]
            ?? throw new \Exception('Public certificate not found');
    }
}
