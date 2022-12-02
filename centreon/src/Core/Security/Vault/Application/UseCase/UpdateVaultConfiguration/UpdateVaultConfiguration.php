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

namespace Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration;

use Assert\InvalidArgumentException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse,
    PresenterInterface
};
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Application\Exceptions\VaultException;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class UpdateVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param VaultConfigurationFactory $vaultConfigurationFactory
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository,
        private ReadVaultRepositoryInterface $readVaultRepository,
        private VaultConfigurationFactory $vaultConfigurationFactory,
        private ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param UpdateVaultConfigurationRequest $request
     */
    public function __invoke(
        PresenterInterface $presenter,
        UpdateVaultConfigurationRequest $request
    ): void {
        try {
            if (! $this->user->isAdmin()) {
                $this->error('User is not admin', ['user' => $this->user->getName()]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(VaultConfigurationException::onlyForAdmin()->getMessage())
                );

                return;
            }

            if (! $this->isVaultExists($request->typeId)) {
                $this->error('Vault provider not found', ['id' => $request->typeId]);
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault provider')
                );

                return;
            }
            $vaultConfiguration = $this->readVaultConfigurationRepository->findById($request->vaultConfigurationId);
            if ($vaultConfiguration === null) {
                $this->error(
                    'Vault configuration not found',
                    [
                        'id' => $request->vaultConfigurationId,
                    ]
                );
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault configuration')
                );

                return;
            }

            if ($this->isVaultConfigurationAlreadyExists($vaultConfiguration)) {
                $this->error(
                    'Vault configuration with these properties already exists for same provider',
                    [
                        'address' => $vaultConfiguration->getAddress(),
                        'port' => $vaultConfiguration->getPort(),
                        'storage' => $vaultConfiguration->getStorage(),
                    ]
                );
                $presenter->setResponseStatus(
                    new InvalidArgumentResponse(VaultConfigurationException::configurationExists()->getMessage())
                );

                return;
            }

            $this->updateVaultConfiguration($request, $vaultConfiguration);

            $this->writeVaultConfigurationRepository->update($vaultConfiguration);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (InvalidArgumentException $ex) {
            $this->error('Some parameters are not valid', ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new InvalidArgumentResponse($ex->getMessage())
            );

            return;
        } catch (\Throwable $ex) {
            $this->error(
                'An error occured in while updating vault configuration',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(VaultConfigurationException::impossibleToUpdate()->getMessage())
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

    /**
     * @param VaultConfiguration $vaultConfiguration
     * @return bool
     * @throws \Throwable
     */
    private function isVaultConfigurationAlreadyExists(
        VaultConfiguration $vaultConfiguration
    ): bool {
        $existingVaultConfiguration = $this->readVaultConfigurationRepository->findByAddressAndPortAndStorage(
            $vaultConfiguration->getAddress(),
            $vaultConfiguration->getPort(),
            $vaultConfiguration->getStorage()
        );

        return $existingVaultConfiguration !== null
            && $existingVaultConfiguration->getId() !== $vaultConfiguration->getId();
    }

    private function updateVaultConfiguration(
        UpdateVaultConfigurationRequest $request,
        VaultConfiguration $vaultConfiguration
    ) {
        $vaultConfiguration->setName($request->name);
        $vaultConfiguration->setAddress($request->address);
        $vaultConfiguration->setStorage($request->storage);
        $vaultConfiguration->setPort($request->port);
        $vaultConfiguration->setNewRoleId($request->roleId);
        $vaultConfiguration->setNewSecretId($request->secretId);
    }
}
