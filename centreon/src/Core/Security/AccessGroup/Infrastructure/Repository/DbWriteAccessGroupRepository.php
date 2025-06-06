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

namespace Core\Security\AccessGroup\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbWriteAccessGroupRepository extends AbstractRepositoryDRB implements WriteAccessGroupRepositoryInterface
{
    use SqlMultipleBindTrait;

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
    public function deleteAccessGroupsForUser(ContactInterface $user): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    DELETE FROM `:db`.`acl_group_contacts_relations`
                    WHERE acl_group_contacts_relations.contact_contact_id = :userId
                        AND acl_group_contacts_relations.acl_group_id NOT IN (
                            SELECT acl_group_id FROM `:db`.acl_groups WHERE cloud_specific = 1
                        )
                    SQL
            )
        );

        $statement->bindValue(':userId', $user->getId(), \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function insertAccessGroupsForUser(ContactInterface $user, array $accessGroups): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                'INSERT INTO acl_group_contacts_relations VALUES (:userId, :accessGroupId)'
            )
        );
        $statement->bindValue(':userId', $user->getId(), \PDO::PARAM_INT);
        foreach ($accessGroups as $accessGroup) {
            $statement->bindValue(':accessGroupId', $accessGroup->getId(), \PDO::PARAM_INT);
            $statement->execute();
        }
    }

    /**
     * {@inheritDoc}
     *
     * If the ACLs are not properly set for the contact, it is possible to create
     * a host group in the GUI you cannot see just after creation.
     *
     * This behaviour is kept here if the `$aclResourceIds` is an empty array.
     */
    public function addLinksBetweenHostGroupAndAccessGroups(int $hostGroupId, array $accessGroups): void
    {
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $aclResourceIds = $this->findEnabledAclResourceIdsByAccessGroupIds($accessGroupsIds);
        $this->addLinksBetweenHostGroupAndResourceIds($hostGroupId, $aclResourceIds);
    }

    public function addLinksBetweenHostGroupAndResourceAccessGroup(int $hostGroupId, int $resourceAccessGroup): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.`acl_resources_hg_relations` (acl_res_id, hg_hg_id)
                    VALUES (:acl_group_id, :hg_hg_id)
                    SQL
            )
        );
        $statement->bindValue(':acl_group_id', $resourceAccessGroup, \PDO::PARAM_INT);
        $statement->bindValue(':hg_hg_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->execute();
    }

    public function addLinksBetweenHostGroupAndResourceIds(int $hostGroupId, array $resourceIds): void
    {
        if ([] === $resourceIds) {
            return;
        }

        // We build a multi-values INSERT query.
        $insert = 'INSERT INTO `:db`.`acl_resources_hg_relations` (acl_res_id, hg_hg_id) VALUES';
        foreach ($resourceIds as $index => $resourceId) {
            $insert .= $index === 0
                ? " (:acl_res_id_{$index}, :hg_hg_id)"
                : ", (:acl_res_id_{$index}, :hg_hg_id)";
        }

        // Insert in bulk
        $statement = $this->db->prepare($this->translateDbName($insert));
        $statement->bindValue(':hg_hg_id', $hostGroupId, \PDO::PARAM_INT);
        foreach ($resourceIds as $index => $resourceId) {
            $statement->bindValue(":acl_res_id_{$index}", $resourceId, \PDO::PARAM_INT);
        }
        $statement->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function removeLinksBetweenHostGroupAndAccessGroups(int $hostGroupId, array $accessGroups): void
    {
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        if ([] === $accessGroupsIds) {
            return;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupsIds, ':group_id_');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<SQL
                    DELETE FROM `:db`.`acl_resources_hg_relations`
                    WHERE hg_hg_id = :hg_hg_id
                    AND acl_res_id IN (
                        SELECT acl_res_id
                        FROM `:db`.`acl_res_group_relations`
                        WHERE acl_group_id IN ({$bindQuery})
                    )
                    SQL
            )
        );

        $statement->bindValue(':hg_hg_id', $hostGroupId, \PDO::PARAM_INT);
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * {@inheritDoc}
     *
     * If the ACLs are not properly set for the contact, it is possible to create
     * a service group in the GUI you cannot see just after creation.
     *
     * This behaviour is kept here if the `$aclResourceIds` is an empty array.
     */
    public function addLinksBetweenServiceGroupAndAccessGroups(int $serviceGroupId, array $accessGroups): void
    {
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $aclResourceIds = $this->findEnabledAclResourceIdsByAccessGroupIds($accessGroupsIds);
        if ([] === $aclResourceIds) {
            return;
        }

        // We build a multi-values INSERT query.
        $insert = 'INSERT INTO `:db`.`acl_resources_sg_relations` (acl_res_id, sg_id) VALUES';
        foreach ($aclResourceIds as $index => $aclResourceId) {
            $insert .= $index === 0
                ? " (:acl_res_id_{$index}, :sg_id)"
                : ", (:acl_res_id_{$index}, :sg_id)";
        }

        // Insert in bulk
        $statement = $this->db->prepare($this->translateDbName($insert));
        $statement->bindValue(':sg_id', $serviceGroupId, \PDO::PARAM_INT);
        foreach ($aclResourceIds as $index => $aclResourceId) {
            $statement->bindValue(":acl_res_id_{$index}", $aclResourceId, \PDO::PARAM_INT);
        }
        $statement->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function updateAclGroupsFlag(array $accessGroups): void
    {
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        if ([] === $accessGroupsIds) {
            return;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupsIds, ':group_id_');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<SQL
                    UPDATE `:db`.`acl_groups`
                    SET acl_group_changed = '1'
                    WHERE acl_group_id IN ({$bindQuery})
                    SQL
            )
        );

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function updateAclResourcesFlag(): void
    {
        $this->db->query(
            $this->translateDbName(
                <<<'SQL'
                    UPDATE `:db`.`acl_resources`
                    SET changed = '1'
                    SQL
            )
        );
    }

    /**
     * Find all `acl_resources` from an `acl_groups` list.
     *
     * @param list<int> $accessGroupIds
     *
     * @return list<int>
     */
    private function findEnabledAclResourceIdsByAccessGroupIds(array $accessGroupIds): array
    {
        if ([] === $accessGroupIds) {
            return [];
        }

        $implodedAccessGroupIds = implode(',', $accessGroupIds);

        $statement = $this->db->query(
            $this->translateDbName(
                <<<SQL
                    SELECT
                        acl.acl_res_id
                    FROM
                        `:db`.`acl_res_group_relations` argr
                    INNER JOIN
                        `:db`.`acl_resources` acl
                        ON acl.acl_res_id = argr.acl_res_id
                    WHERE
                        acl.acl_res_activate = '1'
                        AND argr.acl_group_id IN ({$implodedAccessGroupIds})
                    SQL
            )
        );

        $aclResourceIds = [];
        foreach ($statement ?: [] as $result) {
            /** @var array{acl_res_id: int} $result */
            $aclResourceIds[] = (int) $result['acl_res_id'];
        }

        return $aclResourceIds;
    }
}
