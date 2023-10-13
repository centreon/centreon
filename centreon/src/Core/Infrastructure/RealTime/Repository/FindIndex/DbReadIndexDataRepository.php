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

namespace Core\Infrastructure\RealTime\Repository\FindIndex;

use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Application\RealTime\Repository\ReadIndexDataRepositoryInterface;
use Core\Domain\RealTime\Model\IndexData;
use PDO;

class DbReadIndexDataRepository extends AbstractRepositoryDRB implements ReadIndexDataRepositoryInterface
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
    public function findIndexByHostIdAndServiceId(int $hostId, int $serviceId): int
    {
        $query = 'SELECT 1 AS REALTIME, id FROM `:dbstg`.index_data WHERE host_id = :hostId AND service_id = :serviceId';
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if (! is_array($row) || ! array_key_exists('id', $row)) {
            throw new \InvalidArgumentException('Resource not found');
        }

        return (int) $row['id'];
    }

    /**
     * @inheritDoc
     */
    public function findHostNameAndServiceDescriptionByIndex(int $index): ?IndexData
    {
        $query = 'SELECT 1 AS REALTIME, host_name as hostName, service_description as serviceDescription ';
        $query .= ' FROM `:dbstg`.index_data WHERE id = :index';
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':index', $index, PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch();

        if (! is_array($record)) {
            return null;
        }

        return new IndexData($record['hostName'], $record['serviceDescription']);
    }
}
