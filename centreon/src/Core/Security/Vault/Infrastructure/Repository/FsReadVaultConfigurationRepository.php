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
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface as ReadVaultConfigurationRepository
};
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Symfony\Component\Filesystem\Filesystem;

class FsReadVaultConfigurationRepository implements ReadVaultConfigurationRepository
{
    use LoggerTrait;

    public function __construct(
        private readonly string $configurationFile,
        private readonly Filesystem $filesystem,
        private readonly FsVaultConfigurationFactory $factory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function exists(): bool
    {
        return $this->filesystem->exists($this->configurationFile);
    }

    /**
     * @inheritDoc
     */
    public function find(): ?VaultConfiguration
    {
        if (
            ! file_exists($this->configurationFile)
            || ! $vaultConfiguration = file_get_contents($this->configurationFile, true)
        ) {
            return null;
        }

        $record = json_decode($vaultConfiguration, true)
            ?: throw new \Exception('Invalid vault configuration');

        /**
         * @var array{
         *  name: string,
         *  url: string,
         *  port: int,
         *  root_path: string,
         *  role_id: string,
         *  secret_id: string,
         *  salt: string
         * } $record
         */
        return $this->factory->createFromRecord($record);
    }
}
