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

namespace Core\Security\Vault\Application\UseCase\DeleteVaultConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    NoContentResponse,
    NotFoundResponse,
    PresenterInterface
};
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};

final class DeleteVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        readonly private WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository,
        readonly private ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter): void {
        try {
            if (! $this->user->isAdmin()) {
                $this->error('User is not admin', ['user' => $this->user->getName()]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(VaultConfigurationException::onlyForAdmin()->getMessage())
                );

                return;
            }

            if (! $this->readVaultConfigurationRepository->exists()) {
                $this->error('Vault configuration not found');
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault configuration')
                );

                return;
            }

            $this->writeVaultConfigurationRepository->delete();
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error(
                'An error occurred in while deleting vault configuration',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(VaultConfigurationException::impossibleToDelete()->getMessage())
            );

            return;
        }
    }
}
