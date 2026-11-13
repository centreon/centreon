<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Gorgone\Application\Repository\ReadGorgoneNodeRepositoryInterface;

final class DbReadGorgoneNodeRepository extends AbstractRepositoryRDB implements ReadGorgoneNodeRepositoryInterface
{
    private const DEFAULT_TRAPS_DIRECTORY = '/etc/snmp/centreon_traps';

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
    public function findTrapsDirectories(int $nodeId): array
    {
        // The traps page generates in the directory of the central server, CLAPI in the one of the poller.
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT snmp_trapd_path_conf
                FROM `:db`.`nagios_server`
                WHERE id = :id OR localhost = '1'
                ORDER BY (id = :id_order) DESC
                SQL
        ));
        $statement->bindValue(':id', $nodeId, \PDO::PARAM_INT);
        $statement->bindValue(':id_order', $nodeId, \PDO::PARAM_INT);
        $statement->execute();

        $directories = [];
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $directory) {
            if (is_string($directory) && $directory !== '') {
                $directories[] = rtrim($directory, '/');
            }
        }
        $directories[] = self::DEFAULT_TRAPS_DIRECTORY;

        return array_values(array_unique($directories));
    }
}
