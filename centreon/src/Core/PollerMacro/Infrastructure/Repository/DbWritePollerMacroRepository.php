<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\PollerMacro\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;

/**
 * @phpstan-type _Macro array{
 *      resource_id:int,
 *      resource_name:string,
 *      resource_line:string,
 *      resource_comment:string|null,
 *      resource_activate:string,
 *      is_password:int,
 * }
 */
class DbWritePollerMacroRepository extends AbstractRepositoryRDB implements WritePollerMacroRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function update(PollerMacro $macro): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`cfg_resource` SET
                resource_name = :name,
                resource_line = :value,
                resource_comment = :comment,
                resource_activate = :isActive,
                is_password = :isPassword
                WHERE resource_id = :id
                SQL
        ));

        $statement->bindValue(':name', $macro->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':value', $macro->getValue(), \PDO::PARAM_STR);
        $statement->bindValue(':comment', $macro->getComment(), \PDO::PARAM_STR);
        $statement->bindValue(':isActive', $macro->isActive(), \PDO::PARAM_STR);
        $statement->bindValue(':isPassword', $macro->isPassword(), \PDO::PARAM_INT);
        $statement->bindValue(':id', $macro->getId(), \PDO::PARAM_INT);
        $statement->execute();
    }
}
