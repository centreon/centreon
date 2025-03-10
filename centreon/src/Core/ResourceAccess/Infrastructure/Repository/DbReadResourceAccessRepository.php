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

namespace Core\ResourceAccess\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Contact\Domain\Model\ContactGroup;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterRelation;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\ResourceAccess\Domain\Model\TinyRule;

/**
 * @phpstan-type _TinyRule array{
 *     id: int,
 *     name: string,
 *     description: string|null,
 *     is_enabled: int,
 *     all_contacts: int,
 *     all_contact_groups: int,
 * }
 * @phpstan-type _DatasetFilter array{
 *     dataset_name: string,
 *     dataset_filter_id: int,
 *     dataset_filter_parent_id: int|null,
 *     dataset_filter_type: string,
 *     dataset_filter_resources: string,
 *     dataset_id: int,
 *     rule_id: int
 * }
 */
final class DbReadResourceAccessRepository extends AbstractRepositoryRDB implements ReadResourceAccessRepositoryInterface
{
    use LoggerTrait, SqlMultipleBindTrait;

    /**
     * @param DatabaseConnection $db
     * @param DatasetFilterValidator $datasetValidator
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly DatasetFilterValidator $datasetValidator
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $ruleId): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                    SELECT 1
                    FROM `:db`.acl_groups
                    WHERE acl_group_id = :ruleId
                        AND cloud_specific = 1
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    public function exist(array $ruleIds): array
    {
        if ($ruleIds === []) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($ruleIds, ':rule_id_');

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                    SELECT acl_group_id
                    FROM `:db`.acl_groups
                    WHERE acl_group_id IN ({$bindQuery})
                        AND cloud_specific = 1
                SQL
        ));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function existByContact(array $ruleIds, int $userId): array
    {
        if ($ruleIds === []) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($ruleIds, ':ruleIds');
        $query = <<<SQL
                SELECT acl_group_id
                FROM `:db`.acl_groups
                    WHERE cloud_specific = 1
                    AND (
                        all_contacts = 1
                        OR acl_group_id IN (
                            SELECT acl_group_id
                            FROM `:db`.acl_group_contacts_relations
                            WHERE contact_contact_id = :userId
                        )
                    )
                    AND (
                        acl_group_id IN ({$bindQuery})
                    )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function existByContactGroup(array $ruleIds, array $contactGroups): array
    {
        if ($ruleIds === [] || $contactGroups === []) {
            return [];
        }
        [$bindValueContactGroups, $bindQueryContactGroups] = $this->createMultipleBindQuery(
            array_map(
                fn (ContactGroup $contactGroup) => $contactGroup->getId(),
                $contactGroups
            ),
            ':contactgroup_'
        );

        [$bindValueRules, $bindQueryRules] = $this->createMultipleBindQuery($ruleIds, ':ruleIds');
        $query = <<<SQL
                SELECT acl_group_id
                FROM `:db`.acl_groups
                    WHERE cloud_specific = 1
                    AND (
                        all_contact_groups = 1
                        OR acl_group_id IN (
                            SELECT acl_group_id
                            FROM `:db`.acl_group_contactgroups_relations
                            WHERE cg_cg_id IN ({$bindQueryContactGroups})
                        )
                    )
                    AND (
                        acl_group_id IN ({$bindQueryRules})
                    )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bindValueContactGroups as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        foreach ($bindValueRules as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function findById(int $ruleId): ?Rule
    {
        $basicInformation = $this->findBasicInformation($ruleId);

        if ($basicInformation === null) {
            return null;
        }

        $linkedContactIds = $this->findLinkedContactsToRule($ruleId);
        $linkedContactGroupIds = $this->findLinkedContactGroupsToRule($ruleId);
        $datasets = $this->findDatasetsByRuleId($ruleId);

        // Loop on datasets + exec requests to get names

        if ($datasets === null) {
            return null;
        }

        return DbRuleFactory::createFromRecord(
            $basicInformation,
            $linkedContactIds,
            $linkedContactGroupIds,
            $datasets,
            $this->datasetValidator
        );
    }

    /**
     * @inheritDoc
     */
    public function findDatasetIdsByRuleId(int $ruleId): array
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                        SELECT acl_res_id FROM `:db`.acl_res_group_relations WHERE acl_group_id = :ruleId
                    SQL
            )
        );

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $name): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                    SELECT 1
                    FROM `:db`.acl_groups
                    WHERE acl_group_name = :name
                        AND cloud_specific = 1
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $name, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'acl_groups.acl_group_name',
            'description' => 'acl_groups.cloud_description',
        ]);

        $request = <<<'SQL_WRAP'
                SELECT SQL_CALC_FOUND_ROWS
                    acl_group_id AS `id`,
                    acl_group_name AS `name`,
                    cloud_description AS `description`,
                    acl_group_activate AS `is_enabled`
                FROM `:db`.acl_groups
            SQL_WRAP;

        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND cloud_specific = 1'
            : ' WHERE cloud_specific = 1';

        // handle sort parameter
        $sort = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sort)
            ? $sort
            : ' ORDER BY acl_group_name ASC';

        // handle pagination parameter
        $request .= $sqlTranslator->translatePaginationToSql();

        $request = $this->translateDbName($request);

        $statement = $this->db->prepare($request);

        $rules = [];

        if ($statement === false) {
            return $rules;
        }

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total ACL groups found
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        foreach ($statement as $data) {
            /** @var _TinyRule $data */
            $rules[] = $this->createTinyRuleFromArray($data);
        }

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function findDatasetResourceIdsByHostGroupId(int $hostGroupId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    SELECT id, resource_ids FROM dataset_filters
                    INNER JOIN acl_resources_hg_relations arhr
                        ON arhr.acl_res_id = dataset_filters.acl_resource_id
                    WHERE hg_hg_id = :hostGroupId
                    AND resource_ids != ''
                SQL
        ));

        $statement->bindValue(':hostGroupId', $hostGroupId, \PDO::PARAM_INT);
        $statement->execute();

        $datasetFilters = [];

        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /**
             * @var array{id: int, resource_ids: string} $record
             */
            $datasetFilters[$record['id']] = array_map('intval', explode(',', $record['resource_ids']));
        }

        return $datasetFilters;
    }

    public function findLastLevelDatasetFilterByRuleIdsAndType(array $ruleIds, string $type): array
    {
        if (empty($ruleIds)) {
            return [];
        }
        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($ruleIds, ':rule_id_');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<SQL
                        SELECT
                            id,
                            resource_ids
                        FROM `:db`.dataset_filters
                        INNER JOIN `:db`.acl_resources AS dataset
                            ON dataset.acl_res_id = dataset_filters.acl_resource_id
                        WHERE acl_group_id IN ({$bindQuery})
                            AND type = :type
                            AND parent_id IS NULL
                    SQL
            )
        );

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->bindValue(':type', $type, \PDO::PARAM_STR);
        $statement->execute();

        $datasetFilters = [];

        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /**
             * @var array{id: int, resource_ids: string} $record
             */
            $datasetFilters[$record['id']] = array_map('intval', explode(',', $record['resource_ids']));
        }

        return $datasetFilters;
    }

    /**
     * @inheritDoc
     */
    public function findLastLevelDatasetFilterByRuleIdsAndType(array $ruleIds, string $type): array
    {
        if (empty($ruleIds)) {
            return [];
        }
        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($ruleIds, ':rule_id_');

        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<SQL
                        SELECT
                            id,
                            type,
                            parent_id,
                            acl_resource_id,
                            acl_group_id,
                            resource_ids
                        FROM `:db`.dataset_filters
                        INNER JOIN `:db`.acl_resources AS dataset
                            ON dataset.acl_res_id = dataset_filters.acl_resource_id
                        WHERE acl_group_id IN ({$bindQuery})
                            AND type = :type
                            AND parent_id IS NULL
                    SQL
            )
        );

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->bindValue(':type', $type, \PDO::PARAM_STR);
        $statement->execute();

        $datasetFilterRelations = [];

        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /**
             * @var array{
             *      id: int,
             *      type: string,
             *      parent_id: int|null,
             *      acl_resource_id: int,
             *      acl_group_id: int,
             *      resource_ids: string
             * } $record
             */
            $datasetFilterRelations[] = new DatasetFilterRelation(
                datasetFilterId: $record['id'],
                datasetFilterType: $record['type'],
                parentId: $record['parent_id'],
                resourceAccessGroupId: $record['acl_resource_id'],
                aclGroupId: $record['acl_group_id'],
                resourceIds: array_map('intval', explode(',', $record['resource_ids']))
            );
        }

        return $datasetFilterRelations;
    }

    /**
     * @inheritDoc
     */
    public function existByTypeAndResourceId(string $type, int $resourceId): array
    {
        $resourceIdPattern = '(^|,)' . $resourceId . '(,|$)';
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                        SELECT DISTINCT acl_group_id
                        FROM `:db`.dataset_filters
                        WHERE type = :type
                            AND resource_ids REGEXP(:resourceIdPattern)
                    SQL
            )
        );
        $statement->bindValue(':type', $type, \PDO::PARAM_STR);
        $statement->bindValue(':resourceIdPattern', $resourceIdPattern, \PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param int $ruleId
     *
     * @return int[]
     */
    private function findLinkedContactsToRule(int $ruleId): array
    {
        $statement = $this->db->prepare(
            $this->translatedbname(
                <<<'SQL'
                        SELECT contact_contact_id FROM `:db`.acl_group_contacts_relations WHERE acl_group_id = :ruleId
                    SQL
            )
        );

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param int $ruleId
     *
     * @return int[]
     */
    private function findLinkedContactGroupsToRule(int $ruleId): array
    {
        $statement = $this->db->prepare(
            $this->translatedbname(
                <<<'SQL'
                        SELECT cg_cg_id FROM `:db`.acl_group_contactgroups_relations WHERE acl_group_id = :ruleId
                    SQL
            )
        );

        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param int $ruleId
     *
     * @throws \PDOException
     *
     * @return non-empty-list<_DatasetFilter>|null
     */
    private function findDatasetsByRuleId(int $ruleId): ?array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                    SELECT
                        dataset.acl_res_name AS dataset_name,
                        id AS dataset_filter_id,
                        parent_id AS dataset_filter_parent_id,
                        type AS dataset_filter_type,
                        resource_ids AS dataset_filter_resources,
                        acl_resource_id AS dataset_id,
                        acl_group_id AS rule_id
                    FROM `:db`.dataset_filters
                    INNER JOIN `:db`.acl_resources AS dataset
                        ON dataset.acl_res_id = dataset_filters.acl_resource_id
                    WHERE dataset_filters.acl_group_id = :ruleId
                    ORDER BY dataset_name ASC
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();

        if ($record = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
            /** @var non-empty-list<_DatasetFilter> $record */
            return $record;
        }

        return null;
    }

    /**
     * @param int $ruleId
     *
     * @throws \PDOException
     *
     * @return _TinyRule|null
     */
    private function findBasicInformation(int $ruleId): ?array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                    SELECT
                        acl_groups.acl_group_id AS `id`,
                        acl_group_name AS `name`,
                        cloud_description AS `description`,
                        acl_group_activate AS `is_enabled`,
                        all_contacts,
                        all_contact_groups
                    FROM `:db`.acl_groups
                    WHERE acl_groups.acl_group_id = :ruleId
                        AND cloud_specific = 1
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();

        if ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _TinyRule $record */
            return $record;
        }

        return null;
    }

    /**
     * @param _TinyRule $data
     *
     * @return TinyRule
     */
    private function createTinyRuleFromArray(array $data): TinyRule
    {
        return new TinyRule(
            id: (int) $data['id'],
            name: $data['name'],
            description: (string) $data['description'],
            isEnabled: (bool) $data['is_enabled']
        );
    }
}
