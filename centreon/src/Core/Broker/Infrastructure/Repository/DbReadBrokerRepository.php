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

namespace Core\Broker\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Broker\Application\Repository\ReadBrokerRepositoryInterface;
use Core\Broker\Domain\Model\Broker;
use Core\Broker\Domain\Model\Type;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

/**
 * @phpstan-type _Broker array{
 *  id:int,
 *  name:string,
 * }
 */
class DbReadBrokerRepository extends AbstractRepositoryRDB implements ReadBrokerRepositoryInterface
{
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
    public function exists(int $id): bool
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT 1 FROM `:db`.`cfg_centreonbroker`
                WHERE config_id = :brokerId
                SQL
        ));
        $statement->bindValue(':brokerId', $id, \PDO::PARAM_INT);
        $statement->execute();

       return (bool) $statement->fetchColumn();
    }
}
