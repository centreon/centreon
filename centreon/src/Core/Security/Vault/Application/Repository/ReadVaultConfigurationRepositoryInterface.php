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

namespace Core\Security\Vault\Application\Repository;

use Core\Security\Vault\Domain\Model\VaultConfiguration;

interface ReadVaultConfigurationRepositoryInterface
{
    /**
     * @param string $address
     * @param int $port
     * @param string $storage
     *
     * @throws \Throwable
     *
     * @return VaultConfiguration|null
     */
    public function findByAddressAndPortAndStorage(
        string $address,
        int $port,
        string $storage
    ): ?VaultConfiguration;

    /**
     * @param int $id
     *
     * @throws \Throwable
     *
     * @return VaultConfiguration|null
     */
    public function findById(int $id): ?VaultConfiguration;

    /**
     * @param int $vaultId
     *
     * @throws \Throwable
     *
     * @return VaultConfiguration[]
     */
    public function getVaultConfigurationsByVault(int $vaultId): array;
}
