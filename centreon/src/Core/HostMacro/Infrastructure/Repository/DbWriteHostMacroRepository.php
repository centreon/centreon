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

namespace Core\HostMacro\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\HostMacro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\HostMacro\Domain\Model\HostMacro;

class DbWriteHostMacroRepository extends AbstractRepositoryRDB implements WriteHostMacroRepositoryInterface
{
    use LoggerTrait, RepositoryTrait;

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
    public function add(HostMacro $macro): void
    {
        $this->debug('Add host macro', ['macro' => $macro]);

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`on_demand_macro_host`
                    (`host_macro_name`, `host_macro_value`, `is_password`, `description`, `host_host_id`, `macro_order`)
                VALUES (:macroName, :macroValue, :isPassword, :macroDescription, :hostId, :macroOrder)
                SQL
        ));

        $statement->bindValue(':hostId', $macro->getHostId(), \PDO::PARAM_INT);
        $statement->bindValue(':macroName', '$_HOST' . $macro->getName() . '$', \PDO::PARAM_STR);
        $statement->bindValue(':macroValue', $macro->getValue(), \PDO::PARAM_STR);
        $statement->bindValue(':isPassword', $macro->isPassword() ? '1' : null, \PDO::PARAM_INT);
        $statement->bindValue(
            ':macroDescription',
            $this->emptyStringAsNull($macro->getDescription()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':macroOrder', $macro->getOrder(), \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function update(HostMacro $macro): void
    {
        $this->debug('Update host macro', ['macro' => $macro]);

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`on_demand_macro_host`
                SET
                    `host_macro_value` = :macroValue,
                    `is_password` = :isPassword,
                    `description` = :macroDescription,
                    `macro_order` = :macroOrder
                WHERE `host_host_id` = :hostId
                AND `host_macro_name` = :macroName
                SQL
        ));

        $statement->bindValue(':hostId', $macro->getHostId(), \PDO::PARAM_INT);
        $statement->bindValue(':macroName', '$_HOST' . $macro->getName() . '$', \PDO::PARAM_STR);
        $statement->bindValue(':macroValue', $macro->getValue(), \PDO::PARAM_STR);
        $statement->bindValue(':isPassword', $macro->isPassword() ? '1' : null, \PDO::PARAM_INT);
        $statement->bindValue(
            ':macroDescription',
            $this->emptyStringAsNull($macro->getDescription()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(':macroOrder', $macro->getOrder(), \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function delete(HostMacro $macro): void
    {
        $this->debug('Delete host macro', ['macro' => $macro]);

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE
                    FROM `:db`.`on_demand_macro_host`
                WHERE
                    `host_host_id` = :hostId
                    AND `host_macro_name` = :macroName
                SQL
        ));
        $statement->bindValue(':hostId', $macro->getHostId(), \PDO::PARAM_INT);
        $statement->bindValue(':macroName', '$_HOST' . $macro->getName() . '$', \PDO::PARAM_STR);
        $statement->execute();
    }
}

