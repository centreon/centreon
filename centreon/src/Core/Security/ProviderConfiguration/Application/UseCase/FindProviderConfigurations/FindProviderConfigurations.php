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

namespace Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;

use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\OpenIdConfigurationException;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration as CustomConfigurationOpenId;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\ProviderResponse\{
    ProviderResponseInterface
};
use Core\Security\ProviderConfiguration\Domain\Model\Provider;

final class FindProviderConfigurations
{
    use LoggerTrait;

    /** @var ProviderResponseInterface[] */
    private array $providerResponses;

    /**
     * @param \Traversable<ProviderResponseInterface> $providerResponses
     * @param ReadConfigurationRepositoryInterface $readConfigurationFactory
     */
    public function __construct(
        \Traversable $providerResponses,
        private ReadConfigurationRepositoryInterface $readConfigurationFactory,
        private readonly ReadVaultRepositoryInterface $readVaultRepository
    ) {
        // iterate on factories instead of responses
        // each factory will return a single response
        // and only one presenter will manage the single response
        $this->providerResponses = iterator_to_array($providerResponses);
    }

    /**
     * @param FindProviderConfigurationsPresenterInterface $presenter
     */
    public function __invoke(FindProviderConfigurationsPresenterInterface $presenter): void
    {
        try {
            $configurations = $this->readConfigurationFactory->findConfigurations();

            /**
             * match configuration type and response type to bind automatically corresponding configuration and response.
             * e.g configuration type 'local' will match response type 'local',
             * LocalProviderResponse::create will take LocalConfiguration.
             */
            $responses = [];
            foreach ($configurations as $configuration) {
                foreach ($this->providerResponses as $providerResponse) {
                    if ($configuration->getType() === $providerResponse->getType()) {
                        $this->manageCredentialsFromVault($configuration);
                        $responses[] = $providerResponse->create($configuration);
                    }
                }
            }

            $presenter->present($responses);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));

            return;
        }
    }

    private function manageCredentialsFromVault(Configuration $configuration): void
    {
        $customConfiguration = match ($configuration->getType()) {
            Provider::OPENID => $this->manageCredentialsForOpenId($configuration),
            default => throw OpenIdConfigurationException::unknownProviderType($configuration->getType())
        };


        $configuration->setCustomConfiguration($customConfiguration);
    }

    private function manageCredentialsForOpenId(Configuration $configuration): CustomConfigurationOpenId
    {
        /** @var CustomConfigurationOpenId $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();
        if (str_starts_with($customConfiguration->getClientId(), 'secret::')) {
            $vaultData = $this->readVaultRepository->findFromPath($customConfiguration->getClientId());
            if (! array_key_exists('_OPENID_CLIENT_ID', $vaultData)) {
                throw OpenIdConfigurationException::unableToRetrieveCredentialsFromVault(
                    ['_OPENID_CLIENT_ID']
                );
            }
            $customConfiguration->setClientId($vaultData['_OPENID_CLIENT_ID']);
        }

        return $customConfiguration;
    }
}
