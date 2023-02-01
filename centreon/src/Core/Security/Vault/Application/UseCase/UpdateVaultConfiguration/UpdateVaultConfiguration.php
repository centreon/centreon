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
use Centreon\Domain\Common\Assertion\AssertionException;
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
use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class UpdateVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        readonly private WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository,
        readonly private ReadVaultRepositoryInterface $readVaultRepository,
        readonly private ContactInterface $user
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

            if (! $this->readVaultRepository->exists($request->typeId)) {
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

            if ($this->isVaultConfigurationAlreadyExists($request, $vaultConfiguration->getRootPath())) {
                $this->error(
                    'Vault configuration with these properties already exists for same provider',
                    [
                        'address' => $request->address,
                        'port' => $request->port,
                        'root_path' => $vaultConfiguration->getRootPath()
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
        } catch (InvalidArgumentException | AssertionException $ex) {
            $this->error('Some parameters are not valid', ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new InvalidArgumentResponse($ex->getMessage())
            );

            return;
        } catch (\Throwable $ex) {
            $this->error(
                'An error occurred in while updating vault configuration',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(VaultConfigurationException::impossibleToUpdate()->getMessage())
            );

            return;
        }
    }

    /**
     * @param UpdateVaultConfigurationRequest $request
     * @param string $rootPath
     * @throws \Throwable
     *
     * @return boolean
     */
    private function isVaultConfigurationAlreadyExists(UpdateVaultConfigurationRequest $request, string $rootPath): bool
    {
        $existingVaultConfiguration = $this->readVaultConfigurationRepository->findByAddressAndPortAndStorage(
            $request->address,
            $request->port,
            $rootPath
        );

        return $existingVaultConfiguration !== null
            && $existingVaultConfiguration->getId() !== $request->vaultConfigurationId;
    }

    /**
     * @param UpdateVaultConfigurationRequest $request
     * @param VaultConfiguration $vaultConfiguration
     * @throws \Exception
     */
    private function updateVaultConfiguration(
        UpdateVaultConfigurationRequest $request,
        VaultConfiguration $vaultConfiguration
    ): void {
        $vaultConfiguration->setAddress($request->address);
        $vaultConfiguration->setPort($request->port);
        $vaultConfiguration->setNewRoleId($request->roleId);
        $vaultConfiguration->setNewSecretId($request->secretId);
    }
}
