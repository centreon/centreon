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

namespace Core\Service\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Service\Application\Repository\ReadRealTimeServiceRepositoryInterface;

class DbReadRealTimeServiceRepository extends AbstractRepositoryRDB implements ReadRealTimeServiceRepositoryInterface
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
    public function findUniqueServiceNamesByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'services.name',
            'host.name' => 'hosts.name',
            'host.id' => 'hosts.id',
            'host_category.name' => 'host_categories.name',
            'host_category.id' => 'host_categories.id',
            'host_group.name' => 'host_groups.name',
            'host_group.id' => 'host_groups.id',
            'service_group.name' => 'service_groups.name',
            'service_group.id' => 'service_groups.id',
            'service_category.name' => 'service_categories.name',
            'service_category.id' => 'service_categories.id',
        ]);

        // tags 0=servicegroup, 1=hostgroup, 2=servicecategory, 3=hostcategory
        $request = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS
                    services.name AS service_name
                FROM `:dbstg`.resources AS services
                INNER JOIN `:dbstg`.resources AS hosts
                    ON hosts.id = services.parent_id
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_groups
                    ON hosts.resource_id = rtags_host_groups.resource_id
                LEFT JOIN `:dbstg`.tags host_groups
                    ON rtags_host_groups.tag_id = host_groups.tag_id
                    AND host_groups.type = 1
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_categories
                    ON hosts.resource_id = rtags_host_categories.resource_id
                LEFT JOIN `:dbstg`.tags host_categories
                    ON rtags_host_categories.tag_id = host_categories.tag_id
                    AND host_categories.type = 3
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_groups
                    ON services.resource_id = rtags_service_groups.resource_id
                LEFT JOIN `:dbstg`.tags service_groups
                    ON rtags_service_groups.tag_id = service_groups.tag_id
                    AND service_groups.type = 0
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_categories
                    ON services.resource_id = rtags_service_categories.resource_id
                LEFT JOIN `:dbstg`.tags service_categories
                    ON rtags_service_categories.tag_id = service_categories.tag_id
                    AND service_categories.type = 2
            SQL;

        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null ? ' AND services.type = 0 ' : ' WHERE services.type = 0';

        $request .= ' GROUP BY services.name ';

        $sort = $sqlTranslator->translateSortParameterToSql();

        $request .= $sort !== null ? $sort : ' ORDER BY services.name ASC';

        $request .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator->bindSearchValues($statement);

        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function findUniqueServiceNamesByRequestParametersAndAccessGroupIds(
        RequestParametersInterface $requestParameters,
        array $accessGroupIds
    ): array {
        if ($accessGroupIds === []) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':acl_group');

        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'services.name',
            'host.name' => 'hosts.name',
            'host.id' => 'hosts.id',
            'host_category.name' => 'host_categories.name',
            'host_category.id' => 'host_categories.id',
            'host_group.name' => 'host_groups.name',
            'host_group.id' => 'host_groups.id',
            'service_group.name' => 'service_groups.name',
            'service_group.id' => 'service_groups.id',
            'service_category.name' => 'service_categories.name',
            'service_category.id' => 'service_categories.id',
        ]);

        // tags 0=servicegroup, 1=hostgroup, 2=servicecategory, 3=hostcategory
        $request = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS
                    services.name AS service_name
                FROM `:dbstg`.resources AS services
                INNER JOIN `:dbstg`.resources AS hosts
                    ON hosts.id = services.parent_id
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_groups
                    ON hosts.resource_id = rtags_host_groups.resource_id
                LEFT JOIN `:dbstg`.tags host_groups
                    ON rtags_host_groups.tag_id = host_groups.tag_id
                    AND host_groups.type = 1
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_categories
                    ON hosts.resource_id = rtags_host_categories.resource_id
                LEFT JOIN `:dbstg`.tags host_categories
                    ON rtags_host_categories.tag_id = host_categories.tag_id
                    AND host_categories.type = 3
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_groups
                    ON services.resource_id = rtags_service_groups.resource_id
                LEFT JOIN `:dbstg`.tags service_groups
                    ON rtags_service_groups.tag_id = service_groups.tag_id
                    AND service_groups.type = 0
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_categories
                    ON services.resource_id = rtags_service_categories.resource_id
                LEFT JOIN `:dbstg`.tags service_categories
                    ON rtags_service_categories.tag_id = service_categories.tag_id
                    AND service_categories.type = 2
                INNER JOIN `:dbstg`.centreon_acl acls
                    ON acls.host_id = services.parent_id
                    AND acls.service_id = services.id
            SQL;

        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null ? ' AND services.type = 0 ' : ' WHERE services.type = 0 ';
        $request .= " AND acls.group_id IN ({$bindQuery})";

        $request .= ' GROUP BY services.name ';

        $sort = $sqlTranslator->translateSortParameterToSql();

        $request .= $sort !== null ? $sort : ' ORDER BY services.name ASC';

        $request .= $sqlTranslator->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator->bindSearchValues($statement);

        foreach ($bindValues as $token => $value) {
            $statement->bindValue($token, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }
}
