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
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
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
    use LoggerTrait;

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

        $request = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS
                    acl_group_id AS `id`,
                    acl_group_name AS `name`,
                    cloud_description AS `description`,
                    acl_group_activate AS `is_enabled`
                FROM `:db`.acl_groups
            SQL;

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
