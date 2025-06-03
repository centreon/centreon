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

namespace Core\HostCategory\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\HostCategory\Application\Repository\ReadRealTimeHostCategoryRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Tag\RealTime\Domain\Model\Tag;

/**
 *  @phpstan-type _HostCategoryResultSet array{
 *      id: int,
 *      name: string,
 *      type: int
 * }
 */
class DbReadRealTimeHostCategoryRepository extends AbstractRepositoryRDB implements ReadRealTimeHostCategoryRepositoryInterface
{
    use SqlMultipleBindTrait;

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'host_categories.id',
            'name' => 'host_categories.name',
            'host_group.id' => 'host_groups.id',
            'host_group.name' => 'host_groups.name',
        ]);

        /**
         * Tag types:
         * 0 = service groups,
         * 1 = host groups,
         * 2 = service categories.
         * 3 = host categories.
         */
        $request = <<<'SQL'
                SELECT
                    1 AS REALTIME,
                    host_categories.id AS id,
                    host_categories.name AS name,
                    host_categories.type AS `type`
                FROM `:dbstg`.resources
                INNER JOIN `:dbstg`.resources_tags rtags
                    ON rtags.resource_id = resources.resource_id
                INNER JOIN `:dbstg`.tags AS host_categories
                    ON host_categories.tag_id  = rtags.tag_id
                    AND host_categories.`type` = 3

            SQL;

        $searchRequest = $sqlTranslator?->translateSearchParameterToSql();

        if ($searchRequest !== null) {
            $request .= <<<'SQL'
                LEFT JOIN `:dbstg`.resources_tags rtags_host_groups
                    ON rtags_host_groups.resource_id = resources.resource_id
                LEFT JOIN `:dbstg`.tags AS host_groups
                    ON rtags_host_groups.tag_id = host_groups.tag_id
                    AND host_groups.`type` = 1
                SQL;

            $request .= $searchRequest;
        }

        $request .= ' GROUP BY host_categories.id, host_categories.name, host_categories.type';

        $sortRequest = $sqlTranslator?->translateSortParameterToSql();

        $request .= $sortRequest ?? ' ORDER BY host_categories.name ASC';

        // handle pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator?->bindSearchValues($statement);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Calculate the number of rows for the pagination.
        $sqlTranslator?->calculateNumberOfRows($this->db);

        // Retrieve data
        $hostCategories = [];
        foreach ($statement as $record) {
            /** @var _HostCategoryResultSet $record */
            $hostCategories[] = $this->createFromRecord($record);
        }

        return $hostCategories;
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroupIds(?RequestParametersInterface $requestParameters, array $accessGroupIds): array
    {
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'host_categories.id',
            'name' => 'host_categories.name',
            'host_group.id' => 'host_groups.id',
            'host_group.name' => 'host_groups.name',
        ]);

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        /**
         * Tag types:
         * 0 = service groups,
         * 1 = host groups,
         * 2 = service categories.
         * 3 = host categories.
         */
        $request = <<<'SQL'
                SELECT
                    1 AS REALTIME,
                    host_categories.id AS id,
                    host_categories.name AS name,
                    host_categories.type AS `type`
                FROM `:dbstg`.resources
                INNER JOIN `:dbstg`.resources_tags rtags
                    ON rtags.resource_id = resources.resource_id
                INNER JOIN `:dbstg`.tags AS host_categories
                    ON host_categories.tag_id  = rtags.tag_id
                    AND host_categories.`type` = 3
                INNER JOIN `:db`.acl_resources_hc_relations arhr
                    ON host_categories.id = arhr.hc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
            SQL;

        $searchRequest = $sqlTranslator?->translateSearchParameterToSql();

        if ($searchRequest !== null) {
            $request .= <<<'SQL'
                LEFT JOIN `:dbstg`.resources_tags rtags_host_groups
                    ON rtags_host_groups.resource_id = resources.resource_id
                LEFT JOIN `:dbstg`.tags AS host_groups
                    ON rtags_host_groups.tag_id = host_groups.tag_id
                    AND host_groups.`type` = 1
                SQL;

        }

        $request .= $searchRequest !== null ? $searchRequest . ' AND ' : ' WHERE ';

        $request .= " ag.acl_group_id IN ({$bindQuery})";

        $request .= ' GROUP BY host_categories.id, host_categories.name, host_categories.type';

        $sortRequest = $sqlTranslator?->translateSortParameterToSql();

        $request .= $sortRequest ?? ' ORDER BY host_categories.name ASC';

        // handle pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator?->bindSearchValues($statement);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Calculate the number of rows for the pagination.
        $sqlTranslator?->calculateNumberOfRows($this->db);

        // Retrieve data
        $hostCategories = [];
        foreach ($statement as $record) {
            /** @var _HostCategoryResultSet $record */
            $hostCategories[] = $this->createFromRecord($record);
        }

        return $hostCategories;
    }

    /**
     * @inheritDoc
     */
    public function existByName(array $names): array
    {
        try {
            if ([] === $names) {
                return [];
            }

            [$bindValues, $bindQuery] = $this->createMultipleBindQuery($names, ':hc_');

            $query = $this->translateDbName(
                <<<SQL
                    SELECT
                        `id`
                    FROM `:dbstg`.tags
                    WHERE
                        type = 3
                        AND `name` IN ({$bindQuery})
                    SQL
            );

            $statement = $this->db->prepare($query);

            foreach ($bindValues as $key => $value) {
                $statement->bindValue($key, $value, \PDO::PARAM_STR);
            }

            return $statement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occured while retrieving host categories',
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function existByNameAndAccessGroups(array $names, array $accessGroups): array
    {
        try {
            if ([] === $names || [] === $accessGroups) {
                return [];
            }

            $accessGroupIds = array_map(
                static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
                $accessGroups
            );

            [$bindValuesNames, $bindQueryNames] = $this->createMultipleBindQuery($names, ':hc_');
            [$bindValuesAccessGroups, $bindQueryAccessGroups] = $this->createMultipleBindQuery($accessGroupIds, ':ag_');

            $query = $this->translateDbName(
                <<<SQL
                    SELECT
                        `name`
                    FROM `:dbstg`.tags
                    INNER JOIN `:db`.acl_resources_hc_relations arhr
                        ON tags.id = arhr.hc_id
                    INNER JOIN `:db`.acl_resources res
                        ON arhr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON res.acl_res_id = argr.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON argr.acl_group_id = ag.acl_group_id
                    WHERE
                        type = 3
                        AND `name` IN ({$bindQueryNames})
                        AND ag.acl_group_id IN ({$bindQueryAccessGroups})
                    SQL
            );

            $statement = $this->db->prepare($query);

            foreach ($bindValuesNames as $nameKey => $name) {
                $statement->bindValue($nameKey, $name, \PDO::PARAM_STR);
            }

            foreach ($bindValuesAccessGroups as $accessGroupKey => $accessGroupId) {
                $statement->bindValue($accessGroupKey, $accessGroupId, \PDO::PARAM_INT);
            }

            return $statement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occured while retrieving host categories by access groups',
                previous: $exception
            );
        }
    }

    /**
     * @param _HostCategoryResultSet $data
     *
     * @return Tag
     */
    private function createFromRecord(array $data): Tag
    {
        return new Tag(id: $data['id'], name: $data['name'], type: $data['type']);
    }
}

