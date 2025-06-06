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

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

/**
 * Database repository for the access groups.
 *
 * @phpstan-import-type _AccessGroupRecord from DbAccessGroupFactory
 */
final class DbReadAccessGroupRepository extends AbstractRepositoryDRB implements ReadAccessGroupRepositoryInterface
{
    use LoggerTrait;
    use SqlMultipleBindTrait;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    /**
     * @param DatabaseConnection $db
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function __construct(DatabaseConnection $db, SqlRequestParametersTranslator $sqlRequestTranslator)
    {
        $this->db = $db;
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);

        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 'acl_group_id',
            'name' => 'acl_group_name',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findAllWithFilter(): array
    {
        try {
            $request = 'SELECT SQL_CALC_FOUND_ROWS * FROM acl_groups';
            $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
            $request .= $searchRequest !== null
                ? $searchRequest . ' AND '
                : ' WHERE ';

            $request .= "acl_group_activate = '1'";

            // Sort
            $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
            $request .= $sortRequest ?? ' ORDER BY acl_group_id ASC';

            // Pagination
            $request .= $this->sqlRequestTranslator->translatePaginationToSql();

            $statement = $this->db->prepare($request);

            foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
                /** @var int */
                $type = key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }

            $statement->execute();

            // Set total
            $result = $this->db->query('SELECT FOUND_ROWS()');
            if ($result !== false && ($total = $result->fetchColumn()) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }

            $accessGroups = [];
            while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
                /** @var _AccessGroupRecord $result */
                $accessGroups[] = DbAccessGroupFactory::createFromRecord($result);
            }

            return $accessGroups;
        } catch (\Throwable $e) {
            throw new RepositoryException(
                'Error while getting all access groups with filter',
                ['filter' => $this->sqlRequestTranslator->getSearchValues()],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findByContact(ContactInterface $contact): array
    {
        try {
            $accessGroupsCollection = $this->findByContactId($contact->getId());

            return $accessGroupsCollection->values();
        } catch (RepositoryException $e) {
            throw new RepositoryException(
                'Error while getting access groups by contact',
                ['contact_id' => $contact->getId()],
                $e
            );
        }
    }

    /**
     * Find all access groups according to a contact.
     *
     * @param int $contactId
     *
     * @throws RepositoryException
     * @return AccessGroupCollection
     */
    public function findByContactId(int $contactId): AccessGroupCollection
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
                        WHERE contact_contact_id = :contact_id
                    )
                    OR acl_group_id IN (
                        SELECT acl_group_id
                        FROM acl_group_contactgroups_relations agcr
                        INNER JOIN contactgroup_contact_relation cgcr
                            ON cgcr.contactgroup_cg_id = agcr.cg_cg_id
                        WHERE cgcr.contact_contact_id = :contact_id
                    )
                )
                SQL;

            $statement = $this->db->prepare($query);

            $statement->bindValue(':contact_id', $contactId, \PDO::PARAM_INT);
            if ($statement->execute()) {
                while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    /** @var _AccessGroupRecord $result */
                    $accessGroup = DbAccessGroupFactory::createFromRecord($result);
                    $accessGroups->add($accessGroup->getId(), $accessGroup);
                }

                return $accessGroups;
            }

            return $accessGroups;
        } catch (\PDOException|CollectionException|AssertionFailedException $e) {
            throw new RepositoryException(
                'Error while getting access groups by contact id',
                ['contact_id' => $contactId],
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findByContactWithFilter(ContactInterface $contact): array
    {
        try {
            $request = 'SELECT SQL_CALC_FOUND_ROWS * FROM acl_groups';
            $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
            $request .= $searchRequest !== null
                ? $searchRequest . ' AND '
                : ' WHERE ';

            $request .= "acl_group_activate = '1'
                AND (
                    acl_group_id IN (
                    SELECT acl_group_id FROM acl_group_contacts_relations
                    WHERE contact_contact_id = :contact_id
                    )
                    OR acl_group_id IN (
                    SELECT acl_group_id FROM acl_group_contactgroups_relations agcr
                    INNER JOIN contactgroup_contact_relation cgcr
                        ON cgcr.contactgroup_cg_id = agcr.cg_cg_id
                    WHERE cgcr.contact_contact_id = :contact_id
                    )
                )";

            // Sort
            $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
            $request .= $sortRequest ?? ' ORDER BY acl_group_id ASC';

            // Pagination
            $request .= $this->sqlRequestTranslator->translatePaginationToSql();

            $statement = $this->db->prepare($request);

            foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
                /**
                 * @var int
                 */
                $type = key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
            $statement->bindValue(':contact_id', $contact->getId(), \PDO::PARAM_INT);

            $statement->execute();

            // Set total
            $result = $this->db->query('SELECT FOUND_ROWS()');
            if ($result !== false && ($total = $result->fetchColumn()) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }

            $accessGroups = [];
            while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
                /** @var _AccessGroupRecord $result */
                $accessGroups[] = DbAccessGroupFactory::createFromRecord($result);
            }

            return $accessGroups;
        } catch (\Throwable $e) {
            throw new RepositoryException(
                'Error while getting access groups by contact with filter',
                ['contact_id' => $contact->getId(), 'filter' => $this->sqlRequestTranslator->getSearchValues()],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findByIds(array $accessGroupIds): array
    {
        try {
            $queryBindValues = [];
            foreach ($accessGroupIds as $accessGroupId) {
                $queryBindValues[':access_group_' . $accessGroupId] = $accessGroupId;
            }

            if ($queryBindValues === []) {
                return [];
            }
            $accessGroups = [];
            $boundIds = implode(', ', array_keys($queryBindValues));
            $statement = $this->db->prepare(
                "SELECT * FROM acl_groups WHERE acl_group_id IN ({$boundIds})"
            );
            foreach ($queryBindValues as $bindKey => $accessGroupId) {
                $statement->bindValue($bindKey, $accessGroupId, \PDO::PARAM_INT);
            }
            $statement->execute();

            while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
                /** @var _AccessGroupRecord $result */
                $accessGroups[] = DbAccessGroupFactory::createFromRecord($result);
            }

            return $accessGroups;
        } catch (\Throwable $e) {
            throw new RepositoryException(
                'Error while getting access groups by ids',
                ['ids' => $accessGroupIds],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToResources(array $accessGroupIds): bool
    {
        try {
            if ([] === $accessGroupIds) {
                return false;
            }

            [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':accessGroupIds');
            $statement = $this->db->prepare(
                <<<SQL
                    SELECT 1 FROM acl_res_group_relations
                    WHERE acl_group_id IN ({$bindQuery})
                    SQL
            );

            foreach ($bindValues as $key => $value) {
                $statement->bindValue($key, $value, \PDO::PARAM_INT);
            }

            $statement->execute();

            return (bool) $statement->fetchColumn();
        } catch (\Throwable $e) {
            throw new RepositoryException(
                'Error while checking access to resources',
                ['ids' => $accessGroupIds],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findAclResourcesByHostGroupId(int $hostGroupId): array
    {
        try {
            $statement = $this->db->prepare(
                <<<'SQL'
                    SELECT DISTINCT acl_res_id
                    FROM acl_resources_hg_relations
                    WHERE hg_hg_id = :hostGroupId
                    SQL
            );

            $statement->bindValue(':hostGroupId', $hostGroupId, \PDO::PARAM_INT);
            $statement->execute();

            return $statement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            throw new RepositoryException(
                'Error while getting acl resources by host group id',
                ['hostGroupId' => $hostGroupId],
                previous: $e
            );
        }
    }
}
