<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;

class DbReadHostCategoryRepository extends AbstractRepositoryDRB implements ReadHostCategoryRepositoryInterface
{
    // TODO : update abstract with AbstractRepositoryRDB (cf. PR Laurent)
    use LoggerTrait;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    public function __construct(DatabaseConnection $db, SqlRequestParametersTranslator $sqlRequestTranslator)
    {
        $this->db = $db;
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT);
        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 'hc.hc_id',
            'name' => 'hc.hc_name',
            'alias' => 'hc.hc_alias',
            // TODO : add search by is enabled or not ? with default is being enabled
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $this->info('Getting all host categories');

        $request = $this->translateDbName(
            'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias FROM `:db`.hostcategories hc'
        );

        // Search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $request .= "hc.level IS NULL ";

        // Sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $request .= $sortRequest !== null ? $sortRequest : ' ORDER BY hc.hc_id';

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

        $hostCategories = [];
        if ($statement === false) {
            return $hostCategories;
        }
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $hostCategories[] = new HostCategory(
                $result['hc_id'],
                $result['hc_name'],
                $result['hc_alias']
            );
        }

        return $hostCategories;
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroups(array $accessGroups): array
    {
        $this->info('Getting all host categories by access groups');

        if (empty($accessGroups)) {
            $this->debug('No host category ids, return empty');
            return [];
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $request = $this->translateDbName(
            'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias
            FROM `:db`.hostcategories hc
            INNER JOIN `:db`.acl_resources_hc_relations arhr
                ON hc.hc_id = arhr.hc_id
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id'
        );

        // Search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        $request .= $searchRequest !== null
            ? $searchRequest . ' AND '
            : ' WHERE ';

        $request .= "hc.level IS NULL ";
        $request .= 'AND ag.acl_group_id IN (' . implode(', ', $accessGroupIds) . ')';

        // Sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $request .= $sortRequest !== null ? $sortRequest : ' ORDER BY hc.hc_id';

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

        /**
         * TODO :
         *  - in UI :
         *      if acl_resources_hc_relations is empty
         *      then by default access to ALL
         *      else access to only ones listed in acl_resources_hc_relations
         *  - in old API:
         *      if acl_resources_hc_relations is empty
         *      then by default access to NONE
         *      else access to only ones listed in acl_resources_hc_relations
         *  - in clapi :
         *      if acl_resources_hc_relations is empty
         *      then ???
         *      else ???
         *
         *  check if current behavior is OK, compared to UI results
         */

        // If user is not admin AND total result by access groups is zero then ALL categories are accessible
        if ($this->sqlRequestTranslator->getRequestParameters()->getTotal() === 0) {
            return $this->findAll();
        }

        $hostCategories = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $hostCategories[] = new HostCategory(
                $result['hc_id'],
                $result['hc_name'],
                $result['hc_alias']
            );
        }

        return $hostCategories;
    }
}
