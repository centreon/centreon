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

use Assert\AssertionFailedException;
use Core\Security\Vault\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Vault\Domain\Exceptions\VaultException;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

class VaultConfigurationFactory
{
    /**
     * @param EncryptionInterface $encryption
     * @param ReadVaultRepositoryInterface $readVaultRepository
     */
    public function __construct(
        private EncryptionInterface $encryption,
        private ReadVaultRepositoryInterface $readVaultRepository
    ) {
    }

    /**
     * @param UpdateVaultConfigurationRequest $request
     * @return VaultConfiguration
     * @throws VaultException
     * @throws \Exception
     * @throws AssertionFailedException
     */
    public function create(UpdateVaultConfigurationRequest $request): VaultConfiguration
    {
        $salt = $this->encryption->generateRandomString(VaultConfiguration::SALT_LENGTH);
        $roleId = $this->encryption
            ->setSecondKey($salt)
            ->crypt($request->roleId);
        $secretId = $this->encryption
            ->setSecondKey($salt)
            ->crypt($request->secretId);

        $vault = $this->readVaultRepository->findById($request->typeId);

        if ($vault === null) {
            throw VaultException::providerDoesNotExist();
        }

        return new VaultConfiguration(
            $request->vaultConfigurationId,
            $request->name,
            $vault,
            $request->address,
            $request->port,
            $request->storage,
            $roleId,
            $secretId,
            $salt
        );
    }
}
