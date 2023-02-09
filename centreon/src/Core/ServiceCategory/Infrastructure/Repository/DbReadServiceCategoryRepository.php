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

namespace Core\ServiceCategory\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Utility\SqlConcatenator;

class DbReadServiceCategoryRepository extends AbstractRepositoryRDB implements ReadServiceCategoryRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all service categories');

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            'SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
            FROM `:db`.service_categories sc'
        );

        return $this->retrieveServiceCategories($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameterAndAccessGroups(array $accessGroups, RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all service categories by access groups');

        if ($accessGroups === []) {
            $this->debug('No access group for this user, return empty');

            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if service categories are not filtered in ACLs, then user has access to ALL service categories
        if (! $this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            $this->info('Service categories access not filtered');

            return $this->findByRequestParameter($requestParameters);
        }

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            'SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
            FROM `:db`.service_categories sc
            INNER JOIN `:db`.acl_resources_sc_relations arhr
                ON sc.sc_id = arhr.sc_id
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id'
        );
        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        return $this->retrieveServiceCategories($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findById(int $serviceCategoryId): ?ServiceCategory
    {
        $this->info('Get a service category with id #' . $serviceCategoryId);

        $request = $this->translateDbName(
            'SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
            FROM `:db`.service_categories sc
            WHERE sc.sc_id = :serviceCategoryId'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':serviceCategoryId', $serviceCategoryId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        /** @var array{sc_id:int,sc_name:string,sc_description:string,sc_activate:'0'|'1'} $result */
        return $this->createServiceCategoryFromArray($result);
    }

    /**
     * @inheritDoc
     */
    public function exists(int $serviceCategoryId): bool
    {
        $this->info('Check existence of service category with id #' . $serviceCategoryId);

        $request = $this->translateDbName(
            'SELECT 1 FROM `:db`.service_categories sc WHERE sc.sc_id = :serviceCategoryId AND sc.level IS NULL'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':serviceCategoryId', $serviceCategoryId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $serviceCategoryId, array $accessGroups): bool
    {
        $this->info(
            'Check existence of service category by access groups',
            ['id' => $serviceCategoryId, 'accessgroups' => $accessGroups]
        );

        if (empty($accessGroups)) {
            $this->debug('Access groups array empty');

            return false;
        }

        $concat = new SqlConcatenator();

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if service categories are not filtered in ACLs, then user has access to ALL service categories
        if (! $this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            $this->info('Service categories access not filtered');

            return $this->exists($serviceCategoryId);
        }

        $request = $this->translateDbName(
            'SELECT 1
            FROM `:db`.service_categories sc
            INNER JOIN `:db`.acl_resources_sc_relations arhr
                ON sc.sc_id = arhr.sc_id
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id'
        );
        $concat->appendWhere('sc.sc_id = :serviceCategoryId');
        $concat->appendWhere('sc.level IS NULL');

        $concat->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($request . ' ' . $concat));
        foreach ($concat->retrieveBindValues() as $param => [$value, $type]) {
            $statement->bindValue($param, $value, $type);
        }
        $statement->bindValue(':serviceCategoryId', $serviceCategoryId, \PDO::PARAM_INT);

        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByName(TrimmedString $serviceCategoryName): bool
    {
        $this->info('Check existence of service category with name ' . $serviceCategoryName);

        $request = $this->translateDbName(
            'SELECT 1 FROM `:db`.service_categories sc WHERE sc.sc_name = :serviceCategoryName'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':serviceCategoryName', $serviceCategoryName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array{sc_id:int,sc_name:string,sc_description:string,sc_activate:'0'|'1'} $result
     *
     * @return ServiceCategory
     */
    private function createServiceCategoryFromArray(array $result): ServiceCategory
    {
        $serviceCategory = new ServiceCategory(
            $result['sc_id'],
            $result['sc_name'],
            $result['sc_description']
        );
        $serviceCategory->setActivated((bool) $result['sc_activate']);

        return $serviceCategory;
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface $requestParameters
     *
     * @return ServiceCategory[]
     */
    private function retrieveServiceCategories(
        SqlConcatenator $concatenator,
        RequestParametersInterface $requestParameters
    ): array {
        // Exclude severities from the results
        $concatenator->appendWhere('sc.level IS NULL');

        // Settup for search, pagination, order
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'sc.sc_id',
            'name' => 'sc.sc_name',
            'alias' => 'sc.sc_description',
            'is_activated' => 'sc.sc_activate',
        ]);
        $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        $serviceCategories = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{sc_id:int,sc_name:string,sc_description:string,sc_activate:'0'|'1'} $result */
            $serviceCategories[] = $this->createServiceCategoryFromArray($result);
        }

        return $serviceCategories;
    }

    /**
     * Determine if service categories are filtered for given access group ids
     * true: accessible service categories are filtered (only specified are accessible)
     * false: accessible service categories are not filtered (all are accessible).
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToServiceCategories(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            'SELECT 1
            FROM `:db`.acl_resources_sc_relations arhr
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
}
