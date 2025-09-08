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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Assert\AssertionFailedException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class CentreonAclLazy
{
    use SqlMultipleBindTrait;

    private AccessGroupCollection $accessGroups;
    private CentreonDB $database;

    /**
     * This class is specificaly dedicated to non admin users
     *
     * @param int $userId 
     * @param CentreonDB $database
     */
    public function __construct(private readonly int $userId)
    {
        $this->database = new CentreonDB();
        $this->accessGroups = $this->findAccessGroups();
    }

    /** @return AccessGroupCollection  */
    public function getAccessGroups(): AccessGroupCollection
    {
        return $this->accessGroups;
    }

    /**
     * Find all access groups according to a contact.
     *
     * @param int $userId
     *
     * @throws RepositoryException
     * @return AccessGroupCollection
     */
    public function findHostGroups(): array
    {
        try {
            $hostGroups = [];

            if ($this->accessGroups->isEmpty()) {
                return $hostGroups;
            }

            ['parameters' => $queryParameters, 'placeholderList' => $placeHolders] = $this->createMultipleBindParameters(
                values: $this->accessGroups->getIds(),
                prefix: 'access_group_id',
                paramType: QueryParameterTypeEnum::INTEGER
            );

            $request = <<<SQL
                SELECT SQL_CALC_FOUND_ROWS DISTINCT
                    hg.hg_id,
                    hg.hg_name
                FROM `hostgroup` hg
                INNER JOIN acl_resources_hg_relations arhr
                    ON hg.hg_id = arhr.hg_hg_id
                INNER JOIN acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                    AND ag.acl_group_id IN ({$placeHolders})
            SQL;

            return $this->database->fetchAllAssociative($request, new QueryParameters($queryParameters));
        } catch (\PDOException|CollectionException|AssertionFailedException $e) {
            throw new RepositoryException(
                'Error while getting allowed host groups for user',
                ['contact_id' => $this->userId],
                $e
            );
        }
    }

    /**
     * Find all access groups according to a contact.
     *
     * @param int $userId
     *
     * @throws RepositoryException
     * @return AccessGroupCollection
     */
    public function findAccessGroups(): AccessGroupCollection
    {
        try {
            $accessGroups = new AccessGroupCollection();
            /**
             * Retrieve all access group from contact
             * and contact groups linked to contact.
             */
            $query = <<<'SQL'
                SELECT *
                FROM acl_groups
                WHERE acl_group_activate = '1'
                AND (
                    acl_group_id IN (
                        SELECT acl_group_id
                        FROM acl_group_contacts_relations
                        WHERE contact_contact_id = :userId
                    )
                    OR acl_group_id IN (
                        SELECT acl_group_id
                        FROM acl_group_contactgroups_relations agcr
                        INNER JOIN contactgroup_contact_relation cgcr
                            ON cgcr.contactgroup_cg_id = agcr.cg_cg_id
                        WHERE cgcr.contact_contact_id = :userId
                    )
                )
                SQL;

            $statement = $this->database->prepare($query);

            $statement->bindValue(':userId', $this->userId, \PDO::PARAM_INT);
            if ($statement->execute()) {
                /**
                 * @var array{
                 *     acl_group_id: int|string,
                 *     acl_group_name: string,
                 *     acl_group_alias: string,
                 *     acl_group_activate: string,
                 *     acl_group_changed: int,
                 *     claim_value: string,
                 *     priority: int,
                 * } $result
                 */
                while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $accessGroup = new AccessGroup((int) $result['acl_group_id'], $result['acl_group_name'], $result['acl_group_alias']);
                    $accessGroup->setActivate($result['acl_group_activate'] === '1');
                    $accessGroup->setChanged($result['acl_group_changed'] === 1);

                    $accessGroups->add($accessGroup->getId(), $accessGroup);
                }

                return $accessGroups;
            }

            return $accessGroups;
        } catch (\PDOException|CollectionException|AssertionFailedException $e) {
            throw new RepositoryException(
                'Error while getting access groups by contact id',
                ['contact_id' => $this->userId],
                $e
            );
        }
    }
}
