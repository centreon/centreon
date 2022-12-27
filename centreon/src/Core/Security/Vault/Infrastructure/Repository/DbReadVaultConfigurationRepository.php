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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface as ReadVaultConfigurationRepository
};
use Core\Security\Vault\Domain\Model\VaultConfiguration;

class DbReadVaultConfigurationRepository extends AbstractRepositoryDRB implements ReadVaultConfigurationRepository
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     * @param DbVaultConfigurationFactory $factory
     */
    public function __construct(DatabaseConnection $db, private DbVaultConfigurationFactory $factory)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findByAddressAndPortAndStorage(
        string $address,
        int $port,
        string $storage
    ): ?VaultConfiguration {
        $this->info('Getting existing vault configuration by address, port and storage');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT conf.*, vault.name as vault_name
                    FROM `:db`.`vault_configuration` conf
                    INNER JOIN `:db`.`vault`
                      ON vault.id = conf.vault_id
                    WHERE `url`=:address AND `port`=:port AND `storage`=:storage
                SQL
            )
        );
        $statement->bindValue(':address', $address, \PDO::PARAM_STR);
        $statement->bindValue(':port', $port, \PDO::PARAM_INT);
        $statement->bindValue(':storage', $storage, \PDO::PARAM_STR);
        $statement->execute();

        if (! ($record = $statement->fetch(\PDO::FETCH_ASSOC))) {
            return null;
        }
        /**
         * @var array{
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
         * } $record
         */
        return $this->factory->createFromRecord($record);
    }

    /**
     * @inheritDoc
     */
    public function exists(int $id): bool
    {
        $this->info('Check if the vault configuration exists', ['id' => $id]);
        $statement = $this->db->prepare(
            $this->translateDbName('SELECT 1 FROM `:db`.`vault_configuration` WHERE `id`=:id')
        );
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return ! empty($statement->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * @param string $address
     * @param integer $port
     * @param string $storage
     *
     * @throws \Throwable
     *
     * @return boolean
     */
    public function existsSameConfiguration(string $address, int $port, string $storage): bool
    {
        $this->info(
            'Check if same vault configuration exists',
            [
                'address' => $address,
                'port' => $port,
                'storage' => $storage
            ]
        );

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT 1, vault.name as vault_name
                    FROM `:db`.`vault_configuration` conf
                    INNER JOIN `:db`.`vault`
                      ON vault.id = conf.vault_id
                    WHERE `url`=:address AND `port`=:port AND `storage`=:storage
                SQL
            )
        );
        $statement->bindValue(':address', $address, \PDO::PARAM_STR);
        $statement->bindValue(':port', $port, \PDO::PARAM_INT);
        $statement->bindValue(':storage', $storage, \PDO::PARAM_STR);
        $statement->execute();

        return ! empty($statement->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?VaultConfiguration
    {
        $this->info('Getting existing vault configuration by id');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT conf.*, vault.name as vault_name
                    FROM `:db`.`vault_configuration` conf
                    INNER JOIN `:db`.`vault`
                      ON vault.id = conf.vault_id
                    WHERE conf.`id`=:id
                SQL
            )
        );
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        if (! ($record = $statement->fetch(\PDO::FETCH_ASSOC))) {
            return null;
        }
        /**
         * @var array{
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
         * } $record
         */
        return $this->factory->createFromRecord($record);
    }

    /**
     * @inheritDoc
     */
    public function findVaultConfigurationsByVault(int $vaultId): array
    {
        $this->info('Getting vault configurations by vault provider id');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT conf.*, vault.name as vault_name
                    FROM `:db`.`vault_configuration` conf
                    INNER JOIN `:db`.`vault`
                      ON vault.id = conf.vault_id
                    WHERE conf.`vault_id`=:vaultId
                SQL
            )
        );
        $statement->bindValue(':vaultId', $vaultId, \PDO::PARAM_INT);
        $statement->execute();

        $vaultConfigurations = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /**
             * @var array{
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
             * } $record
             */
            $vaultConfigurations[] = $this->factory->createFromRecord($record);
        }

        return $vaultConfigurations;
    }

    /**
     * @inheritDoc
     */
    public function findDefaultVaultConfiguration(): ?VaultConfiguration
    {
        $vaultConfigurations = [];
        $statement = $this->db->query(
            $this->translateDbName(
                <<<'SQL'
                    SELECT conf.*, vault.name as vault_name
                    FROM `:db`.`vault_configuration` conf
                    INNER JOIN `:db`.`vault`
                      ON vault.id = conf.vault_id
                    LIMIT 1
                SQL
            )
        );

        if (! ($record = $statement->fetch(\PDO::FETCH_ASSOC))) {
            return null;
        }

        /**
         * @var array{
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
         * } $record
         */
        return $this->factory->createFromRecord($record);
    }
}
