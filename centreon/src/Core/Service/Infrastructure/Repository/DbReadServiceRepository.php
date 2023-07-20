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

namespace Core\Service\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Utility\SqlConcatenator;

class DbReadServiceRepository extends AbstractRepositoryRDB implements ReadServiceRepositoryInterface
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
    public function exists(int $serviceId): bool
    {
        $concatenator = $this->getServiceRequestConcatenator();

        return $this->existsService($concatenator, $serviceId);
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $serviceId, array $accessGroups): bool
    {
        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $concatenator = $this->getServiceRequestConcatenator($accessGroupIds);

        return $this->existsService($concatenator, $serviceId);
    }

    public function findMonitoringServerId(int $serviceId): int
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT id
            FROM `:db`.`nagios_server` ns
            INNER JOIN `:db`.`ns_host_relation` nshr
                ON nshr.nagios_server_id = ns.id
            INNER JOIN `:db`.`host_service_relation` hsr
                ON hsr.host_host_id = nshr.host_host_id
            WHERE hsr.service_service_id = :service_id
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @param int[] $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getServiceRequestConcatenator(array $accessGroupIds = []): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`service` s
                    SQL
            );

        if ([] !== $accessGroupIds) {
            // TODO: will be handled later
        }

        return $concatenator;
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $serviceId
     *
     * @throws \PDOException
     *
     * @return bool
     */
    private function existsService(SqlConcatenator $concatenator, int $serviceId): bool
    {
        $concatenator
            ->defineSelect(
                <<<'SQL'
                    SELECT 1
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE s.service_id = :service_id
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE s.service_register = '1'
                    SQL
            )
            ->storeBindValue(':service_id', $serviceId, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
