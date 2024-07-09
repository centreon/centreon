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

namespace Core\Security\Vault\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Vault\Application\Repository\WriteVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\NewVaultConfiguration;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class FsWriteVaultConfigurationRepository implements WriteVaultConfigurationRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param string $configurationFile
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly string $configurationFile,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(NewVaultConfiguration $vaultConfiguration): void
    {
        $vaultConfigurationAsArray = [
            'name' => $vaultConfiguration->getName(),
            'url' => $vaultConfiguration->getAddress(),
            'port' => $vaultConfiguration->getPort(),
            'root_path' => $vaultConfiguration->getRootPath(),
            'role_id' => $vaultConfiguration->getEncryptedRoleId(),
            'secret_id' => $vaultConfiguration->getEncryptedSecretId(),
            'salt' => $vaultConfiguration->getSalt(),
        ];
        $vaultConfigurationEncoded = json_encode($vaultConfigurationAsArray)
            ?: throw new \Exception('Error encoding vault configuration');
        $this->filesystem->dumpFile($this->configurationFile, $vaultConfigurationEncoded);
        $this->filesystem->chmod($this->configurationFile, 0755);
    }

    /**
     * @inheritDoc
     */
    public function update(VaultConfiguration $vaultConfiguration): void
    {
        /**
         * @var array<string, mixed> $vaultConfigurationAsArray
         */
        $vaultConfigurationAsArray = Yaml::parseFile($this->configurationFile);
        $vaultConfigurationUpdate = [
            'name' => $vaultConfiguration->getName(),
            'url' => $vaultConfiguration->getAddress(),
            'port' => $vaultConfiguration->getPort(),
            'root_path' => $vaultConfiguration->getRootPath(),
            'role_id' => $vaultConfiguration->getEncryptedRoleId(),
            'secret_id' => $vaultConfiguration->getEncryptedSecretId(),
        ];
        $vaultConfigurationUpdated = json_encode(array_merge($vaultConfigurationAsArray, $vaultConfigurationUpdate))
            ?: throw new \Exception('Error encoding vault configuration');
        $this->filesystem->dumpFile($this->configurationFile, $vaultConfigurationUpdated);
    }

    /**
     * @inheritDoc
     */
    public function delete(): void
    {
        $this->filesystem->remove($this->configurationFile);
    }
}
