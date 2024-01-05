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
use Core\ResourceAccess\Application\Repository\ReadRuleRepositoryInterface;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\ResourceAccess\Domain\Model\TinyRule;

/**
 * @phpstan-type _TinyRule array{
 *     acl_group_id: int,
 *     acl_group_name: string,
 *     cloud_description: string|null,
 *     acl_group_activate: int
 * }
 * @phpstan-type _Rule array{
 *     id: int,
 *     name: string,
 *     description: string|null,
 *     contact_ids: string,
 *     contact_group_ids: string,
 *     status: int
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
final class DbReadRuleRepository extends AbstractRepositoryRDB implements ReadRuleRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findById(int $ruleId): ?Rule
    {
        $this->info('Find rule information', ['rule_id' => $ruleId]);

        $this->debug('Find basic information for rule', ['rule_id' => $ruleId]);
        $basicInformation = $this->findBasicInformation($ruleId);

        if ($basicInformation === null) {
            $this->error('Failed to retrieve basic rule information');

            return null;
        }

        $this->debug('Find dataset filters linked to the rule', ['rule_id' => $ruleId]);
        $datasets = $this->findDatasetsByRuleId($ruleId);

        if ($datasets === null) {
            $this->error('Failed to retrieve dataset filters linked to the rule');

            return null;
        }

        return DbRuleFactory::createFromRecord($basicInformation, $datasets);
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $name): bool
    {
        $this->info('Check if resource access rule already exists with name: ' . $name);

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
                    acl_group_id,
                    acl_group_name,
                    cloud_description,
                    acl_group_activate
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
     * @return _Rule|null
     */
    private function findBasicInformation(int $ruleId): ?array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                    SELECT
                        acl_groups.acl_group_id AS `id`,
                        acl_group_name AS `name`,
                        cloud_description AS `description`,
                        GROUP_CONCAT(DISTINCT agcr.contact_contact_id) AS contact_ids,
                        GROUP_CONCAT(DISTINCT agcgr.cg_cg_id) AS contact_group_ids,
                        acl_group_activate AS `status`
                    FROM `:db`.acl_groups
                    INNER JOIN `:db`.acl_group_contacts_relations agcr
                        ON agcr.acl_group_id = acl_groups.acl_group_id
                    INNER JOIN `:db`.acl_group_contactgroups_relations agcgr
                        ON agcgr.acl_group_id = acl_groups.acl_group_id
                    WHERE acl_groups.acl_group_id = :ruleId AND cloud_specific = 1
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->execute();

        if ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _Rule $record */
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
            id: (int) $data['acl_group_id'],
            name: $data['acl_group_name'],
            description: (string) $data['cloud_description'],
            isEnabled: (bool) $data['acl_group_activate']
        );
    }
}
