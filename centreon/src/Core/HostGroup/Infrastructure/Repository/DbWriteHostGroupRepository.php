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

namespace Core\HostGroup\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\NewHostGroup;

class DbWriteHostGroupRepository extends AbstractRepositoryDRB implements WriteHostGroupRepositoryInterface
{
    use RepositoryTrait;
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $hostGroupId
     *
     * @throws \PDOException
     */
    public function deleteHostGroup(int $hostGroupId): void
    {
        $this->info('Delete host group', ['id' => $hostGroupId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`hostgroup`
            WHERE hg_id = :hostgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function add(NewHostGroup $newHostGroup): int
    {
        $insert = <<<'SQL'
            INSERT INTO `:db`.`hostgroup`
                (
                    hg_name,
                    hg_alias,
                    hg_notes,
                    hg_notes_url,
                    hg_action_url,
                    hg_icon_image,
                    hg_map_icon_image,
                    hg_rrd_retention,
                    geo_coords,
                    hg_comment,
                    hg_activate
                )
            VALUES
                (
                    :name,
                    :alias,
                    :notes,
                    :notes_url,
                    :action_url,
                    :icon_image,
                    :map_icon_image,
                    :rrd_retention,
                    :geo_coords,
                    :comment,
                    :activate
                )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($insert));
        $this->bindValueOfHostGroup($statement, $newHostGroup);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param \PDOStatement $statement
     * @param HostGroup|NewHostGroup $newHostGroup
     */
    private function bindValueOfHostGroup(\PDOStatement $statement, HostGroup|NewHostGroup $newHostGroup): void
    {
        $statement->bindValue(':name', $newHostGroup->getName());
        $statement->bindValue(':alias', $this->emptyStringAsNull($newHostGroup->getAlias()));
        $statement->bindValue(':notes', $this->emptyStringAsNull($newHostGroup->getNotes()));
        $statement->bindValue(':notes_url', $this->emptyStringAsNull($newHostGroup->getNotesUrl()));
        $statement->bindValue(':action_url', $this->emptyStringAsNull($newHostGroup->getActionUrl()));
        $statement->bindValue(':icon_image', $newHostGroup->getIconId(), \PDO::PARAM_INT);
        $statement->bindValue(':map_icon_image', $newHostGroup->getIconMapId(), \PDO::PARAM_INT);
        $statement->bindValue(':rrd_retention', $newHostGroup->getRrdRetention(), \PDO::PARAM_INT);
        $statement->bindValue(':geo_coords', $newHostGroup->getGeoCoords()?->__toString());
        $statement->bindValue(':comment', $this->emptyStringAsNull($newHostGroup->getComment()));
        $statement->bindValue(':activate', (new BoolToEnumNormalizer())->normalize($newHostGroup->isActivated()));
    }
}
