<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultRepositoryInterface,
    ReadVaultConfigurationRepositoryInterface
};

final class FindVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        readonly private ReadVaultRepositoryInterface $readVaultRepository,
        readonly private ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param FindVaultConfigurationRequest $findVaultConfigurationRequest
     */
    public function __invoke(
        PresenterInterface $presenter,
        FindVaultConfigurationRequest $findVaultConfigurationRequest
    ): void {
        try {
            if (! $this->user->isAdmin()) {
                $this->error('User is not admin', ['user' => $this->user->getName()]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(VaultConfigurationException::onlyForAdmin()->getMessage())
                );

                return;
            }

            if (! $this->readVaultRepository->exists($findVaultConfigurationRequest->vaultId)) {
                $this->error('Vault provider not found', ['id' => $findVaultConfigurationRequest->vaultId]);
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault provider')
                );

                return;
            }

            if (
                ! $this->readVaultConfigurationRepository->exists(
                    $findVaultConfigurationRequest->vaultConfigurationId
                )
            ) {
                $this->error(
                    'Vault configuration not found',
                    [
                        'id' => $findVaultConfigurationRequest->vaultConfigurationId,
                    ]
                );
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault configuration')
                );

                return;
            }

            /** @var VaultConfiguration $vaultConfiguration */
            $vaultConfiguration = $this->readVaultConfigurationRepository->findById(
                $findVaultConfigurationRequest->vaultConfigurationId
            );

            $presenter->present($this->createResponse($vaultConfiguration));
        } catch (\Throwable $ex) {
            $this->error(
                'An error occurred in while getting vault configuration',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
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
        $findVaultConfigurationResponse->vaultConfiguration['id'] = $vaultConfiguration->getId();
        $findVaultConfigurationResponse->vaultConfiguration['name'] = $vaultConfiguration->getName();
        $findVaultConfigurationResponse->vaultConfiguration['vault_id'] = $vaultConfiguration->getVault()->getId();
        $findVaultConfigurationResponse->vaultConfiguration['url'] = $vaultConfiguration->getAddress();
        $findVaultConfigurationResponse->vaultConfiguration['port'] = $vaultConfiguration->getPort();
        $findVaultConfigurationResponse->vaultConfiguration['root_path'] = $vaultConfiguration->getRootPath();

        return $findVaultConfigurationResponse;
    }
}
