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
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceCategory\Domain\Model\ServiceCategoryNamesById;
use Core\ServiceGroup\Infrastructure\Repository\ServiceGroupRepositoryTrait;
use Utility\SqlConcatenator;

/**
 * @phpstan-type _ServiceCategory array{
 *      sc_id: int,
 *      sc_name: string,
 *      sc_description: string,
 *      sc_activate: '0'|'1'
 * }
 */
class DbReadServiceCategoryRepository extends AbstractRepositoryRDB implements ReadServiceCategoryRepositoryInterface
{
    use LoggerTrait, ServiceGroupRepositoryTrait, SqlMultipleBindTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function exist(array $serviceCategoryIds): array
    {
        $this->info('Check existence of service categories', ['service_category_ids' => $serviceCategoryIds]);

        if ($serviceCategoryIds === []) {
            return [];
        }

        $bindValues = [];
        foreach ($serviceCategoryIds as $key => $serviceCategoryId) {
            $bindValues[":service_category_{$key}"] = $serviceCategoryId;
        }

        $serviceCategoryIdList = implode(', ', array_keys($bindValues));

        $request = $this->translateDbName(
            <<<SQL
                    SELECT sc_id FROM `:db`.service_categories WHERE sc_id IN ({$serviceCategoryIdList})
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function findAllExistingIds(array $serviceCategoriesIds): array
    {
        if ($serviceCategoriesIds === []) {
            return [];
        }

        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect(
            $this->translateDbName(<<<'SQL'
                SELECT sc.sc_id
                FROM `:db`.service_categories sc
                WHERE sc.sc_id IN (:service_categories_ids)
                    AND sc.level IS NULL
                SQL
            )
        );
        $sqlConcatenator->storeBindValueMultiple(':service_categories_ids', $serviceCategoriesIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare((string) $sqlConcatenator);
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->execute();

        $serviceCategoriesIdsFound = [];
        while (($id = $statement->fetchColumn()) !== false) {
            $serviceCategoriesIdsFound[] = (int) $id;
        }

        return $serviceCategoriesIdsFound;
    }

    /**
     * @inheritDoc
     */
    public function findAllExistingIdsByAccessGroups(array $serviceCategoriesIds, array $accessGroups): array
    {
        if ($serviceCategoriesIds === [] || $accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        // if service categories are not filtered in ACLs, then user has access to ALL service categories
        if (! $this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            $this->info('Service categories access not filtered');

            return $this->findAllExistingIds($serviceCategoriesIds);
        }

        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect(
            $this->translateDbName(<<<'SQL'
                SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
                FROM `:db`.service_categories sc
                INNER JOIN `:db`.service_categories_relation scr
                    ON scr.sc_id = sc.sc_id
                INNER JOIN `:db`.acl_resources_sc_relations arhr
                    ON sc.sc_id = arhr.sc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                WHERE scr.sc_id IN (:service_categories_ids)
                    AND sc.level IS NULL
                    AND argr.acl_group_id IN (:access_group_ids)
                SQL
            )
        );
        $sqlConcatenator->storeBindValueMultiple(':service_categories_ids', $serviceCategoriesIds, \PDO::PARAM_INT);
        $sqlConcatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare((string) $sqlConcatenator);
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->execute();

        $serviceCategoriesIdsFound = [];
        while (($id = $statement->fetchColumn()) !== false) {
            $serviceCategoriesIdsFound[] = (int) $id;
        }

        return $serviceCategoriesIdsFound;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all service categories');

        $concatenators = $this->findServiceCategoriesRequest($requestParameters);

        return $this->retrieveServiceCategories($concatenators, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameterAndAccessGroups(
        array $accessGroups,
        RequestParametersInterface $requestParameters
    ): array {
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

        $concatenators = $this->findServiceCategoriesRequest($requestParameters, $accessGroupIds);

        foreach ($concatenators as $concatenator) {
            $concatenator->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.acl_resources_sc_relations arscr
                        ON arscr.sc_id = sc.sc_id
                    INNER JOIN `:db`.acl_resources res
                        ON res.acl_res_id = arscr.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON argr.acl_res_id = res.acl_res_id
                    INNER JOIN `:db`.acl_groups ag
                        ON ag.acl_group_id = argr.acl_group_id
                    SQL
            );
            $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
                ->appendWhere(
                    <<<'SQL'
                        ag.acl_group_id IN (:access_group_ids)
                        SQL
                );
        }

        return $this->retrieveServiceCategories($concatenators, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findById(int $serviceCategoryId): ?ServiceCategory
    {
        $this->info('Get a service category with id #' . $serviceCategoryId);

        $request = $this->translateDbName(<<<'SQL'
            SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
            FROM `:db`.service_categories sc
            WHERE sc.sc_id = :serviceCategoryId
                AND sc.level IS NULL
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':serviceCategoryId', $serviceCategoryId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        /** @var _ServiceCategory $result */
        return $this->createServiceCategoryFromArray($result);
    }

    /**
     * @inheritDoc
     */
    public function findNames(array $serviceCategoryIds): ServiceCategoryNamesById
    {
        $concatenator = new SqlConcatenator();

        $serviceCategoryIds = array_unique($serviceCategoryIds);

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT sc.sc_id, sc.sc_name
                FROM `:db`.service_categories sc
                WHERE sc.sc_id IN (:serviceCategoryIds)
                    AND sc.level IS NULL
                SQL
        );
        $concatenator->storeBindValueMultiple(':serviceCategoryIds', $serviceCategoryIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $categoryNames = new ServiceCategoryNamesById();

        foreach ($statement as $result) {
            /** @var array{sc_id:int,sc_name:string} $result */
            $categoryNames->addName(
                $result['sc_id'],
                new TrimmedString($result['sc_name'])
            );
        }

        return $categoryNames;
    }

    /**
     * @inheritDoc
     */
    public function findByService(int $serviceId): array
    {
        $request = $this->translateDbName(<<<'SQL'
            SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
            FROM `:db`.service_categories sc
            INNER JOIN `:db`.service_categories_relation scr
                ON scr.sc_id = sc.sc_id
            WHERE scr.service_service_id = :service_id
                AND sc.level IS NULL
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();
        $serviceCategories = [];

        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _ServiceCategory $result */
            $serviceCategories[] = $this->createServiceCategoryFromArray($result);
        }

        return $serviceCategories;
    }

    /**
     * @inheritDoc
     */
    public function findByServiceAndAccessGroups(int $serviceId, array $accessGroups): array
    {
        if ($accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        // if service categories are not filtered in ACLs, then user has access to ALL service categories
        if (! $this->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            $this->info('Service categories access not filtered');

            return $this->findByService($serviceId);
        }

        $sqlConcatenator = new SqlConcatenator();
        $sqlConcatenator->defineSelect(
            $this->translateDbName(<<<'SQL'
                SELECT sc.sc_id, sc.sc_name, sc.sc_description, sc.sc_activate
                FROM `:db`.service_categories sc
                INNER JOIN `:db`.service_categories_relation scr
                    ON scr.sc_id = sc.sc_id
                INNER JOIN `:db`.acl_resources_sc_relations arhr
                    ON sc.sc_id = arhr.sc_id
                INNER JOIN `:db`.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                WHERE scr.service_service_id = :service_id
                    AND sc.level IS NULL
                    AND argr.acl_group_id IN (:access_group_ids)
                GROUP BY sc.sc_id
                SQL
            )
        );
        $sqlConcatenator->storeBindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $sqlConcatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare((string) $sqlConcatenator);
        $sqlConcatenator->bindValuesToStatement($statement);
        $statement->execute();
        $serviceCategories = [];

        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _ServiceCategory $result */
            $serviceCategories[] = $this->createServiceCategoryFromArray($result);
        }

        return $serviceCategories;
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

        if ($accessGroups === []) {
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
    public function hasRestrictedAccessToServiceCategories(array $accessGroupIds): bool
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

    /**
     * @param _ServiceCategory $result
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
     * @param RequestParametersInterface $requestParameters
     * @param int[] $accessGroupIds
     *
     * @return array<SqlConcatenator>
     */
    private function findServiceCategoriesRequest(RequestParametersInterface $requestParameters, array $accessGroupIds = []): array
    {
        $hostAcls = '';
        $hostGroupAcls = '';
        $hostCategoryAcls = '';
        $serviceGroupAcls = '';
        if ([] !== $accessGroupIds) {
            if (! $this->hasAccessToAllHosts($accessGroupIds)) {
                $hostAcls = <<<'SQL'
                    AND hsr.host_host_id IN (
                        SELECT arhr.host_host_id
                        FROM `:db`.acl_resources_host_relations arhr
                        INNER JOIN `:db`.acl_resources res
                            ON res.acl_res_id = arhr.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON argr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON ag.acl_group_id = argr.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }

            if (! $this->hasAccessToAllHostGroups($accessGroupIds)) {
                $hostGroupAcls = <<<'SQL'
                    AND hr.hostgroup_hg_id IN (
                        SELECT arhgr.hg_hg_id
                        FROM `:db`.acl_resources_hg_relations arhgr
                        INNER JOIN `:db`.acl_resources res
                            ON res.acl_res_id = arhgr.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON argr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON ag.acl_group_id = argr.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }

            if ($this->hasRestrictedAccessToHostCategories($accessGroupIds)) {
                $hostCategoryAcls = <<<'SQL'
                    AND hcr.hostcategories_hc_id IN (
                        SELECT arhcr.hc_id
                        FROM `:db`.acl_resources_hc_relations arhcr
                        INNER JOIN `:db`.acl_resources res
                            ON res.acl_res_id = arhcr.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON argr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON ag.acl_group_id = argr.acl_group_id
                        WHERE ag.acl_group_id IN (:access_group_ids)
                    )
                    SQL;
            }

            if (! $this->hasAccessToAllServiceGroups($accessGroupIds)) {
                $serviceGroupAcls = <<<'SQL'
                        AND sgr.servicegroup_sg_id IN (
                            SELECT arsgr.sg_id
                            FROM `:db`.acl_resources_sg_relations arsgr
                            INNER JOIN `:db`.acl_resources res
                                ON res.acl_res_id = arsgr.acl_res_id
                            INNER JOIN `:db`.acl_res_group_relations argr
                                ON arsgr.acl_res_id = res.acl_res_id
                            INNER JOIN `:db`.acl_groups ag
                                ON ag.acl_group_id = argr.acl_group_id
                            WHERE ag.acl_group_id IN (:access_group_ids)

                        )
                    SQL;
            }
        }

        $searchAsString = $requestParameters->getSearchAsString();
        \preg_match_all('/\{"(?<object>\w+)\.\w+"/', $searchAsString, $matches);

        $findCategoryByServiceConcatenator = new SqlConcatenator();
        $findCategoryByServiceConcatenator->defineSelect(
            <<<'SQL'
                SELECT sc.sc_id,
                    sc.sc_name,
                    sc.sc_description,
                    sc.sc_activate
                FROM `:db`.service_categories sc
                SQL
        );

        $findCategoryByServiceTemplateConcatenator = new SqlConcatenator();
        $findCategoryByServiceTemplateConcatenator->defineSelect(
            <<<'SQL'
                SELECT sc.sc_id,
                    sc.sc_name,
                    sc.sc_description,
                    sc.sc_activate
                FROM `:db`.service_categories sc
                SQL
        );

        if (\in_array('hostcategory', $matches['object'], true)) {
            $findCategoryByServiceConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = scr.service_service_id
                        {$hostAcls}
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                    LEFT JOIN `:db`.hostgroup_relation hr
                        ON hr.host_host_id = host.host_id
                        {$hostGroupAcls}
                    LEFT JOIN `:db`.hostgroup
                        ON hostgroup.hg_id = hr.hostgroup_hg_id
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = host.host_id
                        {$hostCategoryAcls}
                    LEFT JOIN `:db`.hostcategories
                        ON hostcategories.hc_id = hcr.hostcategories_hc_id
                    SQL
            );
            $findCategoryByServiceTemplateConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.service s
                        ON s.service_template_model_stm_id = scr.service_service_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = s.service_id
                        {$hostAcls}
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                    LEFT JOIN `:db`.hostgroup_relation hr
                        ON hr.host_host_id = host.host_id
                        {$hostGroupAcls}
                    LEFT JOIN `:db`.hostgroup
                        ON hostgroup.hg_id = hr.hostgroup_hg_id
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = host.host_id
                        {$hostCategoryAcls}
                    LEFT JOIN `:db`.hostcategories
                        ON hostcategories.hc_id = hcr.hostcategories_hc_id
                    SQL
            );
        } elseif (\in_array('hostgroup', $matches['object'], true)) {
            $findCategoryByServiceConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = scr.service_service_id
                        {$hostAcls}
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                    LEFT JOIN `:db`.hostgroup_relation hr
                        ON hr.host_host_id = host.host_id
                        {$hostGroupAcls}
                    LEFT JOIN `:db`.hostgroup
                        ON hostgroup.hg_id = hr.hostgroup_hg_id
                    SQL
            );
            $findCategoryByServiceTemplateConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.service s
                        ON s.service_template_model_stm_id = scr.service_service_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = s.service_id
                        {$hostAcls}
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                    LEFT JOIN `:db`.hostgroup_relation hr
                        ON hr.host_host_id = host.host_id
                        {$hostGroupAcls}
                    LEFT JOIN `:db`.hostgroup
                        ON hostgroup.hg_id = hr.hostgroup_hg_id
                    SQL
            );
        } elseif (\in_array('host', $matches['object'], true)) {
            $findCategoryByServiceConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = scr.service_service_id
                        {$hostAcls}
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                    SQL
            );
            $findCategoryByServiceTemplateConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.service s
                        ON s.service_template_model_stm_id = scr.service_service_id
                    LEFT JOIN `:db`.host_service_relation hsr
                        ON hsr.service_service_id = s.service_id
                        {$hostAcls}
                    LEFT JOIN `:db`.host
                        ON host.host_id = hsr.host_host_id
                    SQL
            );
        } elseif (\in_array('servicegroup', $matches['object'], true)) {
            $findCategoryByServiceConcatenator->appendJoins(
                <<<SQL
                        LEFT JOIN `:db`.service_categories_relation scr
                            ON scr.sc_id = sc.sc_id
                        LEFT JOIN `:db`.service s
                            ON s.service_id = scr.service_service_id
                        LEFT JOIN `:db`.servicegroup_relation sgr
                            ON sgr.service_service_id = s.service_id
                            {$serviceGroupAcls}
                        LEFT JOIN `:db`.servicegroup
                            ON sgr.servicegroup_sg_id = servicegroup.sg_id
                    SQL
            );
            $findCategoryByServiceTemplateConcatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.sc_id = sc.sc_id
                    LEFT JOIN `:db`.service s
                        ON s.service_template_model_stm_id = scr.service_service_id
                    LEFT JOIN `:db`.servicegroup_relation sgr
                        ON sgr.service_service_id = s.service_id
                        {$serviceGroupAcls}
                    LEFT JOIN `:db`.servicegroup
                        ON sgr.servicegroup_sg_id = servicegroup.sg_id
                    SQL
            );
        }

        return [$findCategoryByServiceConcatenator, $findCategoryByServiceTemplateConcatenator];
    }

    /**
     * @param array<SqlConcatenator> $concatenators
     * @param RequestParametersInterface $requestParameters
     *
     * @return ServiceCategory[]
     */
    private function retrieveServiceCategories(
        array $concatenators,
        RequestParametersInterface $requestParameters
    ): array {
        $concatenatorsAsString = [];
        // Settup for search, pagination, order
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'sc.sc_id',
            'name' => 'sc.sc_name',
            'alias' => 'sc.sc_description',
            'is_activated' => 'sc.sc_activate',
            'host.id' => 'host.host_id',
            'host.name' => 'host.host_name',
            'hostgroup.id' => 'hostgroup.hg_id',
            'hostgroup.name' => 'hostgroup.hg_name',
            'hostcategory.id' => 'hostcategories.hc_id',
            'hostcategory.name' => 'hostcategories.hc_name',
            'servicegroup.id' => 'servicegroup.sg_id',
            'servicegroup.name' => 'servicegroup.sg_name',
        ]);

        foreach ($concatenators as $concatenator) {
            // Exclude severities from the results
            $concatenator->appendWhere('sc.level IS NULL');

            $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());
            $sqlTranslator->translateForConcatenator($concatenator);
            $concatenator->withCalcFoundRows(false);
            $concatenatorsAsString[] = $concatenator->concatForUnion();
        }

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT *
                SQL
        )->defineFrom('(' . \implode(' UNION ', $concatenatorsAsString) . ') AS union_table');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator->bindSearchValues($statement);
        foreach ($concatenators as $concatenator) {
            $concatenator->bindValuesToStatement($statement);
        }
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        $serviceCategories = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _ServiceCategory $result */
            $serviceCategories[] = $this->createServiceCategoryFromArray($result);
        }

        return $serviceCategories;
    }

    /**
     * Determines if access groups give access to all host groups
     * true: all host groups are accessible
     * false: all host groups are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasAccessToAllHostGroups(array $accessGroupIds): bool
    {
        $bindValuesArray = [];
        foreach ($accessGroupIds as $index => $accessGroupId) {
            $bindValuesArray[':acl_group_id_' . $index] = $accessGroupId;
        }
        $bindParamsAsString = \implode(',', \array_keys($bindValuesArray));
        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT res.all_hostgroups
                FROM `:db`.acl_resources res
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON ag.acl_group_id = argr.acl_group_id
                WHERE ag.acl_group_id IN ({$bindParamsAsString})
                SQL
        ));
        foreach ($bindValuesArray as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if access groups give access to all hosts
     * true: all hosts are accessible
     * false: all hosts are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasAccessToAllHosts(array $accessGroupIds): bool
    {
        $bindValuesArray = [];
        foreach ($accessGroupIds as $index => $accessGroupId) {
            $bindValuesArray[':acl_group_id_' . $index] = $accessGroupId;
        }
        $bindParamsAsString = \implode(',', \array_keys($bindValuesArray));
        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT res.all_hosts
                FROM `:db`.acl_resources res
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON ag.acl_group_id = argr.acl_group_id
                WHERE ag.acl_group_id IN ({$bindParamsAsString})
                SQL
        ));
        foreach ($bindValuesArray as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if host categories are filtered for given access group ids
     * true: accessible host categories are filtered (only specified are accessible)
     * false: accessible host categories are NOT filtered (all are accessible).
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasRestrictedAccessToHostCategories(array $accessGroupIds): bool
    {
        $bindValuesArray = [];
        foreach ($accessGroupIds as $index => $accessGroupId) {
            $bindValuesArray[':acl_group_id_' . $index] = $accessGroupId;
        }
        $bindParamsAsString = \implode(',', \array_keys($bindValuesArray));
        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT 1
                FROM `:db`.acl_resources_hc_relations arhcr
                INNER JOIN `:db`.acl_resources res
                    ON res.acl_res_id = arhcr.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON ag.acl_group_id = argr.acl_group_id
                WHERE ag.acl_group_id IN ({$bindParamsAsString})
                SQL
        ));
        foreach ($bindValuesArray as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
