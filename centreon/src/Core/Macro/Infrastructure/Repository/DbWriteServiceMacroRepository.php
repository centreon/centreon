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

namespace Core\Macro\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;

class DbWriteServiceMacroRepository extends AbstractRepositoryRDB implements WriteServiceMacroRepositoryInterface
{
    use RepositoryTrait;

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
    public function add(Macro $macro): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`on_demand_macro_service`
                    (`svc_macro_name`, `svc_macro_value`, `is_password`, `description`, `svc_svc_id`, `macro_order`)
                VALUES (:name, :value, :is_password, :description, :service_id, :order)
                SQL
        ));

        $statement->bindValue(':service_id', $macro->getOwnerId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', '$_SERVICE' . $macro->getName() . '$', \PDO::PARAM_STR);
        $statement->bindValue(':value', $macro->getValue(), \PDO::PARAM_STR);
        $statement->bindValue(':is_password', $macro->isPassword() ? '1' : null, \PDO::PARAM_INT);
        $statement->bindValue(':description', $this->emptyStringAsNull($macro->getDescription()), \PDO::PARAM_STR);
        $statement->bindValue(':order', $macro->getOrder(), \PDO::PARAM_INT);
        $statement->execute();
    }
}
