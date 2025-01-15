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
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class UpdateVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository
     * @param NewVaultConfigurationFactory $newVaultConfigurationFactory
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private readonly WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository,
        private readonly NewVaultConfigurationFactory $newVaultConfigurationFactory,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
        private readonly ContactInterface $user
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

            if ($this->readVaultConfigurationRepository->exists()) {
                $vaultConfiguration = $this->readVaultConfigurationRepository->find();
                if ($vaultConfiguration === null) {
                    $this->error('Vault configuration not found');
                    $presenter->setResponseStatus(new NotFoundResponse('Vault configuration'));

                    return;
                }

                $this->updateVaultConfiguration($request, $vaultConfiguration);

                if (! $this->readVaultRepository->testVaultConnection($vaultConfiguration)) {
                    $presenter->setResponseStatus(
                        new InvalidArgumentResponse(VaultConfigurationException::invalidConfiguration())
                    );

                    return;
                }

                $this->writeVaultConfigurationRepository->update($vaultConfiguration);

            } else {
                $newVaultConfiguration = $this->newVaultConfigurationFactory->create($request);

                if (! $this->readVaultRepository->testVaultConnection($newVaultConfiguration)) {
                    $presenter->setResponseStatus(
                        new InvalidArgumentResponse(VaultConfigurationException::invalidConfiguration())
                    );

                    return;
                }

                $this->writeVaultConfigurationRepository->create($newVaultConfiguration);
            }

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (InvalidArgumentException|AssertionException $ex) {
            $this->error('Some parameters are not valid', ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new InvalidArgumentResponse($ex->getMessage())
            );

            return;
        } catch (\Throwable $ex) {
            $this->error(
                'An error occurred while updating vault configuration',
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
     * @param VaultConfiguration $vaultConfiguration
     *
     * @throws \Exception
     */
    private function updateVaultConfiguration(
        UpdateVaultConfigurationRequest $request,
        VaultConfiguration $vaultConfiguration
    ): void {
        $vaultConfiguration->setAddress($request->address);
        $vaultConfiguration->setPort($request->port);
        $vaultConfiguration->setRootPath($request->rootPath);
        $vaultConfiguration->setNewRoleId($request->roleId);
        $vaultConfiguration->setNewSecretId($request->secretId);
    }
}
