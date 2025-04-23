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

namespace Core\Option\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Option\Application\Repository\ReadOptionRepositoryInterface;
use Core\Option\Domain\Option;

class DbReadOptionRepository extends AbstractRepositoryRDB implements ReadOptionRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findByName(string $name): ?Option
    {
        $statement = $this->db->prepare('SELECT * FROM options WHERE `key` = :key LIMIT 1');
        $statement->bindValue(':key', $name, \PDO::PARAM_STR);
        $statement->execute();
        $option = null;
        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /**
             * @var array{key: string, value: ?string} $result
             */
            $option = new Option($result['key'], $result['value']);
        }

        return $option;
    }
}