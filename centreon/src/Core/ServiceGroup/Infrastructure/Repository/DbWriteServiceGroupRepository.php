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

namespace Core\ServiceGroup\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\NewServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroup;

class DbWriteServiceGroupRepository extends AbstractRepositoryRDB implements WriteServiceGroupRepositoryInterface
{
    use RepositoryTrait;
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $serviceGroupId
     *
     * @throws \PDOException
     */
    public function deleteServiceGroup(int $serviceGroupId): void
    {
        $this->info('Delete service group', ['id' => $serviceGroupId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`servicegroup`
            WHERE sg_id = :servicegroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':servicegroup_id', $serviceGroupId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewServiceGroup $newServiceGroup): int
    {
        $insert = <<<'SQL'
            INSERT INTO `:db`.`servicegroup`
                (
                    sg_name,
                    sg_alias,
                    geo_coords,
                    sg_comment,
                    sg_activate
                )
            VALUES
                (
                    :name,
                    :alias,
                    :geo_coords,
                    :comment,
                    :activate
                )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($insert));
        $this->bindValueOfServiceGroup($statement, $newServiceGroup);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param \PDOStatement $statement
     * @param ServiceGroup|NewServiceGroup $newServiceGroup
     */
    private function bindValueOfServiceGroup(\PDOStatement $statement, ServiceGroup|NewServiceGroup $newServiceGroup): void
    {
        $statement->bindValue(':name', $newServiceGroup->getName());
        $statement->bindValue(':alias', $this->emptyStringAsNull($newServiceGroup->getAlias()));
        $statement->bindValue(':geo_coords', $newServiceGroup->getGeoCoords()?->__toString());
        $statement->bindValue(':comment', $this->emptyStringAsNull($newServiceGroup->getComment()));
        $statement->bindValue(':activate', (new BoolToEnumNormalizer())->normalize($newServiceGroup->isActivated()));
    }
}
