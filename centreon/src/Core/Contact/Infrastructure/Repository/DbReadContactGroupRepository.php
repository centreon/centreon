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

namespace Core\Contact\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;

/**
 * @phpstan-type _ContactGroup array{
 *       cg_id: int,
 *       cg_name: string,
 *       cg_alias: string,
 *       cg_comment?: string,
 *       cg_activate: string,
 *       cg_type: string
 *   }
 */
class DbReadContactGroupRepository extends AbstractRepositoryDRB implements ReadContactGroupRepositoryInterface
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
            'id' => 'cg_id',
            'name' => 'cg_name',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function exists(int $contactGroupId): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1 FROM `:db`.contactgroup
                WHERE cg_id = :contactGroupId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':contactGroupId', $contactGroupId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    public function existsInAccessGroups(int $contactGroupId, array $accessGroupIds): bool
    {
        $bind = [];
        foreach ($accessGroupIds as $key => $accessGroupId) {
            $bind[':access_group_' . $key] = $accessGroupId;
        }
        if ([] === $bind) {
            return false;
        }

        $accessGroupIdsAsString = implode(',', array_keys($bind));

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT 1
                FROM `:db`.contactgroup cg
                     INNER JOIN `:db`.acl_group_contactgroups_relations gcgr
                               ON cg.cg_id = gcgr.cg_cg_id
                WHERE cg.cg_id = :contactGroupId
                    AND gcgr.acl_group_id IN ({$accessGroupIdsAsString})
                SQL
        ));
        $statement->bindValue(':contactGroupId', $contactGroupId,\PDO::PARAM_INT);
        foreach ($bind as $token => $accessGroupId) {
            $statement->bindValue($token, $accessGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findNamesByIds(int ...$ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $ids = array_unique($ids);

        $fields = '';
        foreach ($ids as $index => $id) {
            $fields .= ('' === $fields ? '' : ', ') . ':id_' . $index;
        }

        $select = <<<SQL
            SELECT
                `cg_id` as `id`,
                `cg_name` as `name`
            FROM
                `:db`.`contactgroup`
            WHERE
                `cg_id` IN ({$fields})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($select));
        foreach ($ids as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $names = [];
        foreach ($statement as $result) {
            /** @var array{ id: int, name: string } $result */
            $names[$result['id']] = $result;
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters = null): array
    {
        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
            FROM `:db`.contactgroup
            SQL;

        $sqlTranslator = $requestParameters !== null
            ? new SqlRequestParametersTranslator($requestParameters)
            : null;

        $sqlTranslator?->setConcordanceArray([
            'id' => 'cg_id',
            'name' => 'cg_name',
            'alias' => 'cg_alias',
            'type' => 'cg_type',
            'comments' => 'cg_comment',
            'is_activated' => 'cg_activate',
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        // Search
        $request .= $sqlTranslator?->translateSearchParameterToSql();
        // Sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY cg_id ASC';

        // Pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        if ($sqlTranslator !== null) {
            foreach ($sqlTranslator->getSearchValues() as $key => $data) {
                $type = (int) key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->execute();
        $sqlTranslator?->calculateNumberOfRows($this->db);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $contactGroups = [];
        foreach ($statement as $result) {
            /** @var _ContactGroup $result */
            $contactGroups[] = DbContactGroupFactory::createFromRecord($result);
        }

        return $contactGroups;
    }

    /**
     * @inheritDoc
     */
    public function findAllByUserId(int $userId): array
    {
        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
            FROM `:db`.contactgroup cg
            INNER JOIN `:db`.contactgroup_contact_relation ccr
                ON ccr.contactgroup_cg_id = cg.cg_id
            SQL;

        // Search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $request .= "ccr.contact_contact_id = :userId AND cg_activate = '1'";

        // Sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $request .= $sortRequest ?? ' ORDER BY cg_id ASC';

        // Pagination
        $request .= $this->sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
            /**
             * @var int
             */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $contactGroups = [];
        while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _ContactGroup $result */
            $contactGroups[] = DbContactGroupFactory::createFromRecord($result);
        }

        return $contactGroups;
    }

    /**
     * @inheritDoc
     */
    public function find(int $contactGroupId): ?ContactGroup
    {
        $this->debug('Getting Contact Group by id', [
            'contact_group_id' => $contactGroupId,
        ]);
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                FROM `:db`.contactgroup
                WHERE cg_id = :contactGroupId
                SQL
            )
        );
        $statement->bindValue(':contactGroupId', $contactGroupId, \PDO::PARAM_INT);
        $statement->execute();
        $contactGroup = null;
        if ($statement !== false && $result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _ContactGroup $result */
            $contactGroup = DbContactGroupFactory::createFromRecord($result);
        }
        $this->debug(
            $contactGroup === null  ? 'No Contact Group found' : 'Contact Group Found',
            [
                'contact_group_id' => $contactGroupId,
            ]
        );

        return $contactGroup;
    }

    /**
     * @inheritDoc
     */
    public function findByIds(array $contactGroupIds): array
    {
        $this->debug('Getting Contact Group by Ids', [
            'ids' => implode(', ', $contactGroupIds),
        ]);
        $queryBindValues = [];
        foreach ($contactGroupIds as $contactGroupId) {
            $queryBindValues[':contact_group_' . $contactGroupId] = $contactGroupId;
        }

        if ($queryBindValues === []) {
            return [];
        }
        $contactGroups = [];
        $boundIds = implode(', ', array_keys($queryBindValues));
        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                FROM `:db`.contactgroup
                WHERE cg_id IN ({$boundIds})
                SQL
            )
        );
        foreach ($queryBindValues as $bindKey => $contactGroupId) {
            $statement->bindValue($bindKey, $contactGroupId, \PDO::PARAM_INT);
        }
        $statement->execute();

        while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _ContactGroup $result */
            $contactGroups[] = DbContactGroupFactory::createFromRecord($result);
        }
        $this->debug('Contact Group found: ' . count($contactGroups));

        return $contactGroups;
    }

    /**
     * @inheritDoc
     */
    public function findByAccessGroupsAndUserAndRequestParameter(
        array $accessGroups,
        ContactInterface $user,
        ?RequestParametersInterface $requestParameters = null,
    ): array
    {
        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        [$bindValues, $subQuery] = $this->createMultipleBindQuery($accessGroupIds, ':id_');

        $request = $this->translateDbName(<<<SQL
            SELECT SQL_CALC_FOUND_ROWS *
            FROM (
                SELECT /* Search for contact groups directly related to the user with ACL groups */
                    cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                FROM `:db`.acl_group_contactgroups_relations gcgr
                INNER JOIN `:db`.contactgroup cg
                    ON cg.cg_id = gcgr.cg_cg_id
                WHERE gcgr.acl_group_id IN ({$subQuery})
                GROUP BY cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                UNION
                SELECT /* Search for contact groups the user belongs to */
                    cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                FROM `:db`.contactgroup cg
                INNER JOIN `:db`.contactgroup_contact_relation ccr
                    ON ccr.contactgroup_cg_id = cg.cg_id
                INNER JOIN `:db`.contact c
                    ON c.contact_id = ccr.contact_contact_id
                WHERE ccr.contact_contact_id = :user_id
                    AND c.contact_register = '1'
            ) AS contact_groups
            SQL
        );
        $sqlTranslator = $requestParameters !== null
            ? new SqlRequestParametersTranslator($requestParameters)
            : null;

        $sqlTranslator?->setConcordanceArray([
            'id' => 'cg_id',
            'name' => 'cg_name',
            'alias' => 'cg_alias',
            'type' => 'cg_type',
            'comments' => 'cg_comment',
            'is_activated' => 'cg_activate',
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        // Search
        if ($search = $sqlTranslator?->translateSearchParameterToSql()) {
            $request .= ' WHERE ' . $search;
        }

        $request .= ' GROUP BY cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type';
        // Sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();
        $request .= $sortRequest ?? ' ORDER BY cg_id ASC';

        // Pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        if ($sqlTranslator !== null) {
            foreach ($sqlTranslator->getSearchValues() as $key => $data) {
                $type = (int) key($data);
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':user_id', $user->getId(), \PDO::PARAM_INT);

        $statement->execute();
        $sqlTranslator?->calculateNumberOfRows($this->db);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $contactGroups = [];
        foreach ($statement as $result) {
            /** @var _ContactGroup $result */
            $contactGroups[] = DbContactGroupFactory::createFromRecord($result);
        }

        return $contactGroups;
    }

    /**
     * @inheritDoc
     */
    public function exist(array $contactGroupIds): array
    {
        $bind = [];
        foreach ($contactGroupIds as $key => $contactGroupId) {
            $bind[":cg_{$key}"] = $contactGroupId;
        }
        if ($bind === []) {
            return [];
        }
        $contactGroupIdsAsString = implode(', ', array_keys($bind));
        $request = $this->translateDbName(
           <<<SQL
               SELECT cg_id FROM `:db`.contactgroup
               WHERE cg_id IN ({$contactGroupIdsAsString})
               SQL
        );
        $statement = $this->db->prepare($request);
        foreach ($bind as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
}
