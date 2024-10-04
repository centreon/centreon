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

namespace Core\Service\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Repository\WriteRealTimeServiceRepositoryInterface;

class DbWriteRealTimeServiceRepository extends AbstractRepositoryRDB implements WriteRealTimeServiceRepositoryInterface
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
    public function addServiceToResourceAcls(int $hostId, int $serviceId, array $accessGroups): void
    {
        $accessGroupIds = array_map(
            fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $request = <<<'SQL'
            INSERT INTO `:dbstg`.`centreon_acl`(`group_id`, `host_id`, `service_id`)
            VALUES
            SQL;

        foreach ($accessGroupIds as $accessGroupId) {
            $request .= <<<SQL
                (:group_{$accessGroupId}, :host_id, :service_id),
                SQL;
        }

        $statement = $this->db->prepare($this->translateDbName(trim($request, ',')));

        foreach ($accessGroupIds as $accessGroupId) {
            $statement->bindValue(':group_' . $accessGroupId, $accessGroupId, \PDO::PARAM_INT);
        }
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);

        $statement->execute();
    }
}
