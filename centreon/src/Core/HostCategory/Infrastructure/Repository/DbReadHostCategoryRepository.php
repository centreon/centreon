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


use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Utility\SqlConcatenator;

class DbReadHostCategoryRepository extends AbstractRepositoryDRB implements ReadHostCategoryRepositoryInterface
{
    // TODO : update abstract with AbstractRepositoryRDB (cf. PR Laurent)
    use LoggerTrait;

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
    public function findAll(?RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host categories');

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            'SELECT hc.hc_id, hc.hc_name, hc.hc_alias, hc.hc_activate, hc.hc_comment
            FROM `:db`.hostcategories hc'
        );

        return $this->retrieveHostCategories($concatenator, $requestParameters);
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
            fn ($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if host categories are not filtered in ACLs, then user has access to ALL host categories
        if (! $this->hasRestrictedAccessToHostCategories($accessGroupIds)) {
            $this->info('Host categories access not filtered');

            return $this->findAll($requestParameters);
        }

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            'SELECT hc.hc_id, hc.hc_name, hc.hc_alias, hc.hc_activate, hc.hc_comment
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
        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        return $this->retrieveHostCategories($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function exists(int $hostCategoryId): bool
    {
        $this->info('Check existence of host category with id #' . $hostCategoryId);

        $request = $this->translateDbName(
            'SELECT 1 FROM `:db`.hostcategories hc WHERE hc.hc_id = :hostCategoryId'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostCategoryId', $hostCategoryId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $hostCategoryId, array $accessGroups): bool
    {
        $this->info(
            'Check existence of host category by access groups',
            ['id' => $hostCategoryId, 'accessgroups' => $accessGroups]
        );

        if (empty($accessGroups)) {
            $this->debug('Access groups array empty');
            return false;
        }

        $concat = new SqlConcatenator();

        $accessGroupIds = array_map(
            fn ($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        $request = $this->translateDbName(
            'SELECT 1
            FROM `:db`.hostcategories hc
            INNER JOIN `:db`.acl_resources_hc_relations arhr
                ON hc.hc_id = arhr.hc_id
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id
            WHERE hc.hc_id = :hostCategoryId'
        );

        $concat->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($request . ' ' . $concat));
        foreach ($concat->retrieveBindValues() as $param => [$value, $type]) {
            $statement->bindValue($param, $value, $type);
        }
        $statement->bindValue(':hostCategoryId', $hostCategoryId, \PDO::PARAM_STR);

        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface|null $requestParameters
     * @return HostCategory[]
     */
    private function retrieveHostCategories(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // Exclude severities from the results
        $concatenator->appendWhere('hc.level IS NULL');

        // Settup for search, pagination, order
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'hc.hc_id',
            'name' => 'hc.hc_name',
            'alias' => 'hc.hc_alias',
            'is_activated' => 'hc.hc_activate'
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator?->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator?->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator?->calculateNumberOfRows($this->db);

        $hostCategories = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{hc_id:int,hc_name:string,hc_alias:string,hc_activate:'0'|'1',hc_comment:string|null} $result */
            $hostCategories[] = $this->createHostCategoryFromArray($result);
        }

        return $hostCategories;
    }

    /**
     * @param array{hc_id:int,hc_name:string,hc_alias:string,hc_activate:'0'|'1',hc_comment:string|null} $result
     * @return HostCategory
     */
    private function createHostCategoryFromArray(array $result): HostCategory
    {
        $hostCategory = new HostCategory(
            $result['hc_id'],
            $result['hc_name'],
            $result['hc_alias']
        );
        $hostCategory->setActivated((bool) $result['hc_activate']);
        $hostCategory->setComment($result['hc_comment']);

        return $hostCategory;
    }

    /**
     * Determine if host cateogries are filtered for given access group ids
     * true: accessible host categories are filtered
     * false: accessible host categories are not filtered
     *
     * @param int[] $accessGroupIds
     * @phpstan-param non-empty-array<int> $accessGroupIds
     * @return bool
     */
    private function hasRestrictedAccessToHostCategories(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            'SELECT 1
            FROM `:db`.acl_resources_hc_relations arhr
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id'
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $hostCategoryName): bool
    {
        $this->info('Check existance of host category with name ' . $hostCategoryName);

        $request = $this->translateDbName(
            'SELECT hc.hc_id FROM `:db`.hostcategories hc WHERE hc.hc_name = :hostCategoryName'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostCategoryName', $hostCategoryName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
