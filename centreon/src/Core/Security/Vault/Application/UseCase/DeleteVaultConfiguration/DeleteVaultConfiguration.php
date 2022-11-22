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

namespace Core\Security\Vault\Application\UseCase\DeleteVaultConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    NoContentResponse,
    NotFoundResponse
};
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Domain\Exceptions\VaultConfigurationException;

final class DeleteVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository,
        private ReadVaultRepositoryInterface $readVaultRepository,
        private ContactInterface $user
    ) {
    }

    /**
     * @param DeleteVaultConfigurationPresenterInterface $presenter
     * @param DeleteVaultConfigurationRequest $deleteVaultConfigurationRequest
     */
    public function __invoke(
        DeleteVaultConfigurationPresenterInterface $presenter,
        DeleteVaultConfigurationRequest $deleteVaultConfigurationRequest
    ): void {
        try {
            if (! $this->user->isAdmin()) {
                $presenter->setResponseStatus(
                    new ForbiddenResponse('Only admin user can create vault configuration')
                );

                return;
            }

            if (! $this->isVaultExists($deleteVaultConfigurationRequest->typeId)) {
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault provider id')
                );

                return;
            }

            if (! $this->isVaultConfigurationExists($deleteVaultConfigurationRequest->vaultConfigurationId)) {
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault configuration id')
                );

                return;
            }

            $this->writeVaultConfigurationRepository->delete($deleteVaultConfigurationRequest->vaultConfigurationId);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error(
                'An error occured in while updating vault configuration',
                ['trace' => (string) $ex]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(VaultConfigurationException::impossibleToCreate()->getMessage())
            );

            return;
        }
    }

    /**
     * Checks if vault provider exists.
     *
     * @param int $id
     *
     * @return bool
     */
    private function isVaultExists(int $id): bool
    {
        return $this->readVaultRepository->findById($id) !== null;
    }

    /**
     * Checks if vault configuration exists.
     *
     * @param int $id
     *
     * @return bool
     */
    private function isVaultConfigurationExists(int $id): bool
    {
        return $this->readVaultConfigurationRepository->findById($id) !== null;
    }
}
