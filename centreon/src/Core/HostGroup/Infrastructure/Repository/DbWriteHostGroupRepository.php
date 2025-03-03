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
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\NewHostGroup;
use Utility\SqlConcatenator;

class DbWriteHostGroupRepository extends AbstractRepositoryDRB implements WriteHostGroupRepositoryInterface
{
    use RepositoryTrait, LoggerTrait, SqlMultipleBindTrait;

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

    public function update(HostGroup $hostGroup): void
    {
        $update = <<<'SQL'
            UPDATE `:db`.`hostgroup`
            SET
                hg_name = :name,
                hg_alias = :alias,
                hg_notes = :notes,
                hg_notes_url = :notes_url,
                hg_action_url = :action_url,
                hg_icon_image = :icon_image,
                hg_map_icon_image = :map_icon_image,
                hg_rrd_retention = :rrd_retention,
                geo_coords = :geo_coords,
                hg_comment = :comment,
                hg_activate = :activate
            WHERE
                hg_id = :hostgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($update));
        $statement->bindValue(':hostgroup_id', $hostGroup->getId(), \PDO::PARAM_INT);
        $this->bindValueOfHostGroup($statement, $hostGroup);
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
     * @inheritDoc
     */
    public function linkToHost(int $hostId, array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        $bindValues = [];
        $subQuery = [];
        foreach ($groupIds as $key => $groupId) {
            $bindValues[":group_id_{$key}"] = $groupId;
            $subQuery[] = "(:group_id_{$key}, :host_id)";
        }

        $statement = $this->db->prepare($this->translateDbName(
            'INSERT INTO `:db`.`hostgroup_relation` (hostgroup_hg_id, host_host_id) VALUES '
            . implode(', ', $subQuery)
        ));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addHosts(int $hostGroupId, array $hostIds): void
    {
        if ($hostIds === []) {
            return;
        }

        $bindValues = [];
        $subQuery = [];
        foreach ($hostIds as $key => $hostId) {
            $bindValues[":host_id_{$key}"] = $hostId;
            $subQuery[] = "(:host_id_{$key}, :group_id)";
        }

        $statement = $this->db->prepare($this->translateDbName(
            'INSERT INTO `:db`.`hostgroup_relation` (host_host_id, hostgroup_hg_id) VALUES '
            . implode(', ', $subQuery)
        ));
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':group_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function unlinkFromHost(int $hostId, array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        $concatenator = new SqlConcatenator();
        $concatenator
            ->appendWhere('host_host_id = :host_id')
            ->appendWhere('hostgroup_hg_id in (:group_ids)')
            ->storeBindValue(':host_id', $hostId, \PDO::PARAM_INT)
            ->storeBindValueMultiple(':group_ids', $groupIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName(
            'DELETE FROM `:db`.`hostgroup_relation`'
            . $concatenator->__toString()
        ));

        $concatenator->bindValuesToStatement($statement);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function enableDisableHostGroup(int $hostGroupId, bool $isEnable): void
    {
        $update = <<<'SQL'
            UPDATE `:db`.`hostgroup`
            SET
                hg_activate = :activate
            WHERE
                hg_id = :hostgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($update));
        $statement->bindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':activate', (new BoolToEnumNormalizer())->normalize($isEnable));
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function duplicate(int $hostGroupId, int $duplicateIndex): int
    {
        $this->info('Duplicate host group', ['id' => $hostGroupId]);

        $query = <<<'SQL'
            INSERT INTO `:db`.`hostgroup`
            (
                hg_name,
                hg_alias,
                geo_coords,
                hg_comment,
                hg_activate
            )
            SELECT
                CONCAT(hg_name, '_', :duplicateIndex),
                hg_alias,
                geo_coords,
                hg_comment,
                hg_activate
            FROM `:db`.`hostgroup`
            WHERE hg_id = :hostgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':duplicateIndex', $duplicateIndex, \PDO::PARAM_STR);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    public function deleteHosts(int $hostGroupId, array $hostIds): void
    {
        if ($hostIds === []) {
            return;
        }
        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($hostIds, ':host_id_');
        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                    DELETE FROM `:db`.`hostgroup_relation`
                    WHERE hostgroup_hg_id = :hostgroup_id
                    AND host_host_id IN ({$bindQuery})
                SQL
        ));
        $statement->bindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();
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
