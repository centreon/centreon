<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Security\Vault\Application\UseCase\CreateVaultConfiguration;

use Assert\InvalidArgumentException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    CreatedResponse,
    ErrorResponse,
    ForbiddenResponse,
    InvalidArgumentResponse,
    NotFoundResponse
};
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface};

final class CreateVaultConfiguration
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param NewVaultConfigurationFactory $newVaultConfigurationFactory
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        private WriteVaultConfigurationRepositoryInterface $writeVaultConfigurationRepository,
        private ReadVaultRepositoryInterface $readVaultRepository,
        private NewVaultConfigurationFactory $newVaultConfigurationFactory,
        private ContactInterface $user
    ) {
    }

    /**
     * @param CreateVaultConfigurationPresenterInterface $presenter
     * @param CreateVaultConfigurationRequest $createVaultConfigurationRequest
     */
    public function __invoke(
        CreateVaultConfigurationPresenterInterface $presenter,
        CreateVaultConfigurationRequest $createVaultConfigurationRequest
    ): void {
        try {
            if (! $this->user->isAdmin()) {
                $this->error('User is not admin', ['user' => $this->user->getName()]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(VaultConfigurationException::onlyForAdmin()->getMessage())
                );

                return;
            }

            if (
                $this->isSameVaultConfigurationExists(
                    $createVaultConfigurationRequest->address,
                    $createVaultConfigurationRequest->port,
                    $createVaultConfigurationRequest->storage,
                )
            ) {
                $this->error(
                    'Vault configuration with these properties already exists',
                    [
                        'address' => $createVaultConfigurationRequest->address,
                        'port' => $createVaultConfigurationRequest->port,
                        'storage' => $createVaultConfigurationRequest->storage,
                    ]
                );
                $presenter->setResponseStatus(
                    new InvalidArgumentResponse(VaultConfigurationException::configurationExists()->getMessage())
                );

                return;
            }

            if (! $this->isVaultExists($createVaultConfigurationRequest->typeId)) {
                $this->error('Vault provider not found', ['id' => $createVaultConfigurationRequest->typeId]);
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault provider')
                );

                return;
            }

            $newVaultConfiguration = $this->newVaultConfigurationFactory->create($createVaultConfigurationRequest);

            $this->writeVaultConfigurationRepository->create($newVaultConfiguration);
            $presenter->setResponseStatus(new CreatedResponse());
        } catch (InvalidArgumentException $ex) {
            $this->error('Some parameters are not valid', ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new InvalidArgumentResponse($ex->getMessage())
            );

            return;
        } catch (\Throwable $ex) {
            $this->error(
                'An error occured in while creating vault configuration',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(VaultConfigurationException::impossibleToCreate()->getMessage())
            );

            return;
        }
    }

    /**
     * Checks if same vault configuration exists.
     *
     * @param string $address
     * @param int $port
     * @param string $storage
     *
     * @throws \Throwable
     *
     * @return bool
     */
    private function isSameVaultConfigurationExists(
        string $address,
        int $port,
        string $storage,
    ): bool {
        return
            $this->readVaultConfigurationRepository->findByAddressAndPortAndStorage(
                $address,
                $port,
                $storage
            ) !== null;
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
}
