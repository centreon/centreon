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

namespace Core\Security\Vault\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Core\Security\Vault\Domain\Model\Vault;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

class DbVaultConfigurationFactory
{
    /**
     * @param EncryptionInterface $encryption
     */
    public function __construct(private EncryptionInterface $encryption)
    {
    }

    /**
     * @param array{
     *  id: int,
     *  name: string,
     *  vault_id: int,
     *  vault_name: string,
     *  url: string,
     *  port: int,
     *  storage: string,
     *  role_id: string,
     *  secret_id: string,
     *  salt: string
     * } $recordData
     *
     * @throws AssertionFailedException
     * @throws \Exception
     *
     * @return VaultConfiguration
     */
    public function createFromRecord(array $recordData): VaultConfiguration
    {
        return new VaultConfiguration(
            $this->encryption,
            (int) $recordData['id'],
            (string) $recordData['name'],
            new Vault($recordData['vault_id'], $recordData['vault_name']),
            (string) $recordData['url'],
            (int) $recordData['port'],
            (string) $recordData['storage'],
            (string) $recordData['salt'],
            (string) $recordData['role_id'],
            (string) $recordData['secret_id']
        );
    }
}
