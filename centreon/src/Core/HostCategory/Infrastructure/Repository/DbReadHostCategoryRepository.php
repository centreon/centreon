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
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Utility\SqlConcatenator;

class DbReadHostCategoryRepository extends AbstractRepositoryDRB implements ReadHostCategoryRepositoryInterface
{
    // TODO : update abstract with AbstractRepositoryRDB (cf. PR Laurent)
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host categories');

        return $this->findAllRequest(null, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroups(array $accessGroups, ?RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host categories by access groups');

        if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');
            return [];
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        /**
         * TODO :
         *  - in UI :
         *      if acl_resources_hc_relations is empty
         *      then by default access to ALL
         *      else access to only ones listed in acl_resources_hc_relations
         */

        // if host categories are not filtered, then user has access to ALL host categories
        $accessGroupIds = $this->hasRestrictedAccessToHostCategories($accessGroupIds) ? $accessGroupIds : null;

        return $this->findAllRequest($accessGroupIds, $requestParameters);
    }

    /**
     * @param int[]|null $accessGroupIds
     * @param RequestParametersInterface|null $requestParameters
     * @return HostCategory[]
     */
    private function findAllRequest(?array $accessGroupIds, ?RequestParametersInterface $requestParameters): array
    {
        $concat = new SqlConcatenator();

        if ($accessGroupIds === null) {
            $query = 'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias
                FROM `:db`.hostcategories hc';
        } else {
            $query = 'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias
                FROM `:db`.hostcategories hc
                INNER JOIN `:db`.acl_resources_hc_relations arhr
                    ON hc.hc_id = arhr.hc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id';

            $concat->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
                ->appendWhere('ag.acl_group_id IN (:access_group_ids)');
        }
        $concat->appendWhere('hc.level IS NULL');

        if ( ! $requestParameters?->hasSearchParameter('is_activated')) {
            $concat->appendWhere("hc.hc_activate = '1'");
        }

        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'hc.hc_id',
            'alias' => 'hc.hc_alias',
            'name' => 'hc.hc_name',
            'is_activated' => 'hc.hc_activate'
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator?->translateForConcatenator($concat);

        $statement = $this->db->prepare($this->translateDbName($query . ' ' . $concat));

        $sqlTranslator?->bindSearchValues($statement);
        foreach ($concat->retrieveBindValues() as $param => [$value, $type]) {
            $statement->bindValue($param, $value, $type);
        }
        $statement->execute();

        $sqlTranslator?->calculateNumberOfRows($this->db);

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

    /**
     * Determine if host cateogries are filtered for given access group ids
     * true: accessible host categories are filtered
     * false: accessible host categories are not filtered
     *
     * @param int[] $accessGroupIds
     * @return bool
     */
    private function hasRestrictedAccessToHostCategories(array $accessGroupIds): bool
    {
        $concat = new SqlConcatenator();

        $query = 'SELECT COUNT(arhr.hc_id)
            FROM `:db`.acl_resources_hc_relations arhr
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id';

        $concat->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($query . ' ' . $concat));

        foreach ($concat->retrieveBindValues() as $param => [$value, $type]) {
            $statement->bindValue($param, $value, $type);
        }
        $statement->execute();
        $result = $statement->fetchColumn();

        return ($result === false || $result >= 1);
    }


    private function isActivationStatusSpecified(array $searchParameters): bool
    {

        return false;
    }
}
