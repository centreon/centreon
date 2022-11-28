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

namespace Core\Security\Vault\Application\UseCase\FindVaultConfigurations;

use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class FindVaultConfigurationsResponse
{
    /**
     * @var array<array{
     *  id: int,
     *  name: string,
     *  vault_provider: string,
     *  url: string,
     *  port: int,
     *  storage: string,
     *  role_id: string
     * }>
     */
    public array $vaultConfigurations;

    /**
     * @param VaultConfiguration[] $vaultConfigurations
     */
    public function __construct(array $vaultConfigurations)
    {
        $this->vaultConfigurations = $this->vaultConfigurationsToArray($vaultConfigurations);
    }

    /**
     * @param VaultConfiguration[] $vaultConfigurations
     *
     * @return array<array{
     *  id: int,
     *  name: string,
     *  vault_provider: string,
     *  url: string,
     *  port: int,
     *  storage: string,
     *  role_id: string
     * }>
     */
    private function vaultConfigurationsToArray(array $vaultConfigurations): array
    {
        return array_map(
            fn (VaultConfiguration $vaultConfiguration) => [
                'id' => $vaultConfiguration->getId(),
                'name' => $vaultConfiguration->getName(),
                'vault_provider' =>$vaultConfiguration->getVault()->getName(),
                'url' => $vaultConfiguration->getAddress(),
                'port' => $vaultConfiguration->getPort(),
                'storage' => $vaultConfiguration->getStorage(),
                'role_id' => $vaultConfiguration->getRoleId()
            ],
            $vaultConfigurations
        );
    }
}
