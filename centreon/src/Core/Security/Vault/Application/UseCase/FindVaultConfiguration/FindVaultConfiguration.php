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

namespace Core\Security\Vault\Application\UseCase\FindVaultConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, NotFoundResponse, PresenterInterface};
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class FindVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param FindVaultConfigurationPresenterInterface $presenter
     */
    public function __invoke(
        FindVaultConfigurationPresenterInterface $presenter,
    ): void {
        try {
            if (! $this->user->isAdmin()) {
                $this->error('User is not admin', ['user' => $this->user->getName()]);
                $presenter->presentResponse(
                    new ForbiddenResponse(VaultConfigurationException::onlyForAdmin()->getMessage())
                );

                return;
            }

            if (! $this->readVaultConfigurationRepository->exists()) {
                $this->error('Vault configuration not found');
                $presenter->presentResponse(
                    new NotFoundResponse('Vault configuration')
                );

                return;
            }

            /** @var VaultConfiguration $vaultConfiguration */
            $vaultConfiguration = $this->readVaultConfigurationRepository->find();

            $presenter->presentResponse($this->createResponse($vaultConfiguration));
        } catch (\Throwable $ex) {
            $this->error(
                'An error occurred in while getting vault configuration',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->presentResponse(
                new ErrorResponse(VaultConfigurationException::impossibleToFind()->getMessage())
            );
        }
    }

    /**
     * @param VaultConfiguration $vaultConfiguration
     *
     * @return FindVaultConfigurationResponse
     */
    private function createResponse(VaultConfiguration $vaultConfiguration): FindVaultConfigurationResponse
    {
        $findVaultConfigurationResponse = new FindVaultConfigurationResponse();
        $findVaultConfigurationResponse->address = $vaultConfiguration->getAddress();
        $findVaultConfigurationResponse->port = $vaultConfiguration->getPort();
        $findVaultConfigurationResponse->rootPath = $vaultConfiguration->getRootPath();

        return $findVaultConfigurationResponse;
    }}
