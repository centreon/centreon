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
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;

final class FindProviderConfigurations
{
    use LoggerTrait;

    /** @var ProviderConfigurationDtoFactoryInterface[] */
    private array $providerResponseFactories;

    /**
     * @param \Traversable<ProviderConfigurationDtoFactoryInterface> $providerDtoFactories
     * @param ReadConfigurationRepositoryInterface $readConfigurationRepository
     */
    public function __construct(
        \Traversable $providerDtoFactories,
        private readonly ReadConfigurationRepositoryInterface $readConfigurationRepository,
    ) {
        $this->providerResponseFactories = iterator_to_array($providerDtoFactories);
    }

    /**
     * @param FindProviderConfigurationsPresenterInterface $presenter
     */
    public function __invoke(FindProviderConfigurationsPresenterInterface $presenter): void
    {
        try {
            $configurations = $this->readConfigurationRepository->findConfigurations();

            /**
             * match configuration type and factory supporting type to bind automatically corresponding
             * configuration and Factory.
             * e.g configuration type 'local' will match LocalProviderDtoFactory,
             * ProviderConfigurationDtoFactoryInterface::createResponse will take LocalConfiguration.
             */
            $responses = [];
            foreach ($configurations as $configuration) {
                foreach ($this->providerResponseFactories as $providerFactory) {
                    if ($providerFactory->supports($configuration->getType())) {
                        $responses[] = $providerFactory->createResponse($configuration);
                    }
                }
            }

            $response = new FindProviderConfigurationsResponse();
            $response->providerConfigurations = $responses;

            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));

            return;
        }
    }
}
