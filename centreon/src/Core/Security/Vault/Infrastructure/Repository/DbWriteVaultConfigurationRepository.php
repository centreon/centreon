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
    WriteVaultConfigurationRepositoryInterface as WriteVaultConfigurationInterface
};
use Core\Security\Vault\Domain\Model\NewVaultConfiguration;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

class DbWriteVaultConfigurationRepository extends AbstractRepositoryDRB implements WriteVaultConfigurationInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function create(NewVaultConfiguration $vaultConfiguration): void
    {
        $this->info('Adding new vault configuration in database');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.`vault_configuration`
                    (`name`, `vault_id`, `url`, `port`, `storage`, `role_id`, `secret_id`, `salt`)
                    VALUES (:name, :vault_id, :url, :port, :storage, :role_id, :secret_id, :salt)
                    SQL
            )
        );
        $statement->bindValue(':name', $vaultConfiguration->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':vault_id', $vaultConfiguration->getVault()->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':url', $vaultConfiguration->getAddress(), \PDO::PARAM_STR);
        $statement->bindValue(':port', $vaultConfiguration->getPort(), \PDO::PARAM_INT);
        $statement->bindValue(':storage', $vaultConfiguration->getRootPath(), \PDO::PARAM_STR);
        $statement->bindValue(':role_id', $vaultConfiguration->getEncryptedRoleId(), \PDO::PARAM_STR);
        $statement->bindValue(':secret_id', $vaultConfiguration->getEncryptedSecretId(), \PDO::PARAM_STR);
        $statement->bindValue(':salt', $vaultConfiguration->getSalt(), \PDO::PARAM_STR);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function update(VaultConfiguration $vaultConfiguration): void
    {
        $this->info('Updating vault configuration in database');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    UPDATE `:db`.`vault_configuration`
                    SET `name`=:name,
                        `vault_id`=:vault_id,
                        `url`=:url,
                        `port`=:port,
                        `storage`=:storage,
                        `role_id`=:role_id,
                        `secret_id`=:secret_id
                    WHERE `id`=:id
                    SQL
            )
        );

        $statement->bindValue(':id', $vaultConfiguration->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', $vaultConfiguration->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':vault_id', $vaultConfiguration->getVault()->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':url', $vaultConfiguration->getAddress(), \PDO::PARAM_STR);
        $statement->bindValue(':port', $vaultConfiguration->getPort(), \PDO::PARAM_INT);
        $statement->bindValue(':storage', $vaultConfiguration->getRootPath(), \PDO::PARAM_STR);
        $statement->bindValue(':role_id', $vaultConfiguration->getEncryptedRoleId(), \PDO::PARAM_STR);
        $statement->bindValue(':secret_id', $vaultConfiguration->getEncryptedSecretId(), \PDO::PARAM_STR);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function delete(int $vaultConfigurationId): void
    {
        $this->info('Deleting vault configuration');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    DELETE FROM `:db`.`vault_configuration` WHERE `id`=:id
                    SQL
            )
        );

        $statement->bindValue(':id', $vaultConfigurationId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
