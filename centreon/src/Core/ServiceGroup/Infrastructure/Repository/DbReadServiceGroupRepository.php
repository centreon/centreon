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

namespace Core\ServiceGroup\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Host\Infrastructure\Repository\HostRepositoryTrait;
use Core\HostCategory\Infrastructure\Repository\HostCategoryRepositoryTrait;
use Core\HostGroup\Infrastructure\Repository\HostGroupRepositoryTrait;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Infrastructure\Repository\ServiceCategoryRepositoryTrait;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroupNamesById;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Utility\SqlConcatenator;

/**
 * @phpstan-type ServiceGroupResultSet array{
 *     sg_id: int,
 *     sg_name: string,
 *     sg_alias: string,
 *     geo_coords: ?string,
 *     sg_comment: ?string,
 *     sg_activate: '0'|'1'
 * }
 */
class DbReadServiceGroupRepository extends AbstractRepositoryDRB implements ReadServiceGroupRepositoryInterface
{
    use SqlMultipleBindTrait,
        HostRepositoryTrait,
        HostGroupRepositoryTrait,
        ServiceCategoryRepositoryTrait,
        HostCategoryRepositoryTrait,
        ServiceGroupRepositoryTrait;

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
    public function findAll(?RequestParametersInterface $requestParameters): \Traversable&\Countable
    {
        $request = <<<'SQL'
                SELECT
                    sg.sg_id,
                    sg.sg_name,
                    sg.sg_alias,
                    sg.geo_coords,
                    sg.sg_comment,
                    sg.sg_activate
                FROM `:db`.servicegroup sg
            SQL;

        // Handle the request parameters if those are set
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;

        $sqlTranslator?->setConcordanceArray([
            'id' => 'sg.sg_id',
            'alias' => 'sg.sg_alias',
            'name' => 'sg.sg_name',
            'is_activated' => 'sg.sg_activate',
            'host.id' => 'h.host_id',
            'host.name' => 'h.host_name',
            'hostgroup.id' => 'hg.hg_id',
            'hostgroup.name' => 'hg.hg_name',
            'hostcategory.id' => 'hc.hc_id',
            'hostcategory.name' => 'hc.hc_name',
            'servicecategory.id' => 'sc.sc_id',
            'servicecategory.name' => 'sc.sc_name',
        ]);

        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $searchRequest = $sqlTranslator?->translateSearchParameterToSql();

        if ($searchRequest !== null) {
            $request .= <<<SQL
                    LEFT JOIN `:db`.servicegroup_relation sgr
                        ON sgr.servicegroup_sg_id = sg.sg_id
                    LEFT JOIN `:db`.host h
                        ON h.host_id = sgr.host_host_id
                    LEFT JOIN `:db`.service s
                        ON s.service_id = sgr.service_service_id
                        OR s.service_template_model_stm_id = sgr.service_service_id
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.service_service_id = s.service_id
                    LEFT JOIN `:db`.service_categories sc
                        ON sc.sc_id = scr.sc_id
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = sgr.host_host_id
                    LEFT JOIN `:db`.hostcategories hc
                        ON hc.hc_id = hcr.hostcategories_hc_id
                    LEFT JOIN `:db`.hostgroup_relation hgr
                        ON hgr.hostgroup_hg_id = sgr.hostgroup_hg_id
                    LEFT JOIN `:db`.hostgroup hg
                        ON hg.hg_id = hgr.hostgroup_hg_id
                    {$searchRequest}
                SQL;
        }

        // handle sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();

        $request .= $sortRequest ?? ' ORDER BY sg.sg_name ASC';

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
        $serviceGroups = [];
        foreach ($statement as $result) {
            /** @var ServiceGroupResultSet $result */
            $serviceGroups[] = ServiceGroupFactory::createFromDb($result);
        }

        return new \ArrayIterator($serviceGroups);
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroupIds(?RequestParametersInterface $requestParameters, array $accessGroupIds): \Traversable&\Countable
    {
        if ([] === $accessGroupIds) {
            return new \ArrayIterator([]);
        }

        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        $request = <<<'SQL'
                SELECT
                    sg.sg_id,
                    sg.sg_name,
                    sg.sg_alias,
                    sg.geo_coords,
                    sg.sg_comment,
                    sg.sg_activate
                FROM `:db`.servicegroup sg
            SQL;

        $sqlTranslator?->setConcordanceArray([
            'id' => 'sg.sg_id',
            'alias' => 'sg.sg_alias',
            'name' => 'sg.sg_name',
            'is_activated' => 'sg.sg_activate',
            'host.id' => 'h.host_id',
            'host.name' => 'h.host_name',
            'hostgroup.id' => 'hg.hg_id',
            'hostgroup.name' => 'hg.hg_name',
            'hostcategory.id' => 'hc.hc_id',
            'hostcategory.name' => 'hc.hc_name',
            'servicecategory.id' => 'sc.sc_id',
            'servicecategory.name' => 'sc.sc_name',
        ]);

        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        $searchRequest = $sqlTranslator?->translateSearchParameterToSql();

        // Do not join tables if no search provided...
        if ($searchRequest !== null) {
            $hostAcl = $this->generateHostAclSubRequest($accessGroupIds);
            $serviceCategoryAcl = $this->generateServiceCategoryAclSubRequest($accessGroupIds);
            $hostGroupAcl = $this->generateHostGroupAclSubRequest($accessGroupIds);
            $hostCategoryAcl = $this->generateHostCategoryAclSubRequest($accessGroupIds);

            $request .= <<<SQL
                    LEFT JOIN `:db`.servicegroup_relation sgr
                        ON sgr.servicegroup_sg_id = sg.sg_id
                    LEFT JOIN `:db`.host h
                        ON h.host_id = sgr.host_host_id
                        AND sgr.host_host_id IN ({$hostAcl})
                    LEFT JOIN `:db`.service_categories_relation scr
                        ON scr.service_service_id = sgr.service_service_id
                    LEFT JOIN `:db`.service_categories sc
                        ON sc.sc_id = scr.sc_id
                        AND scr.sc_id IN ({$serviceCategoryAcl})
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = sgr.host_host_id
                    LEFT JOIN `:db`.hostcategories hc
                        ON hc.hc_id = hcr.hostcategories_hc_id
                        AND hcr.hostcategories_hc_id IN ({$hostCategoryAcl})
                    LEFT JOIN `:db`.hostgroup_relation hgr
                        ON hgr.hostgroup_hg_id = sgr.hostgroup_hg_id
                    LEFT JOIN `:db`.hostgroup hg
                        ON hg.hg_id = hgr.hostgroup_hg_id
                        AND hgr.hostgroup_hg_id IN ({$hostGroupAcl})
                SQL;
        }

        $request .= <<<'SQL'
                INNER JOIN `:db`.acl_resources_sg_relations arsr
                    ON sg.sg_id = arsr.sg_id
                INNER JOIN `:db`.acl_resources res
                    ON arsr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
            SQL;

        $request .= $searchRequest ? $searchRequest . ' AND ' : ' WHERE ';

        $request .= <<<SQL
                ag.acl_group_id IN ({$bindQuery})
            SQL;

        // handle sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();

        $request .= $sortRequest ?? ' ORDER BY sg.sg_name ASC';

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
        $serviceGroups = [];
        foreach ($statement as $result) {
            /** @var ServiceGroupResultSet $result */
            $serviceGroups[] = ServiceGroupFactory::createFromDb($result);
        }

        return new \ArrayIterator($serviceGroups);
    }

    /**
     * @inheritDoc
     */
    public function findOne(int $serviceGroupId): ?ServiceGroup
    {
        $concatenator = $this->getFindServiceGroupConcatenator();

        return $this->retrieveServiceGroup($concatenator, $serviceGroupId);
    }

    /**
     * @inheritDoc
     */
    public function findOneByAccessGroups(int $serviceGroupId, array $accessGroups): ?ServiceGroup
    {
        if ([] === $accessGroups) {
            return null;
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllServiceGroups($accessGroupIds)) {

            return $this->findOne($serviceGroupId);
        }
        $concatenator = $this->getFindServiceGroupConcatenator($accessGroupIds);

        return $this->retrieveServiceGroup($concatenator, $serviceGroupId);
    }

    /**
     * @inheritDoc
     */
    public function existsOne(int $serviceGroupId): bool
    {
        $concatenator = $this->getFindServiceGroupConcatenator();

        return $this->existsServiceGroup($concatenator, $serviceGroupId);
    }

    /**
     * @inheritDoc
     */
    public function existsOneByAccessGroups(int $serviceGroupId, array $accessGroups): bool
    {
        if ([] === $accessGroups) {
            return false;
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllServiceGroups($accessGroupIds)) {

            return $this->existsOne($serviceGroupId);
        }
        $concatenator = $this->getFindServiceGroupConcatenator($accessGroupIds);

        return $this->existsServiceGroup($concatenator, $serviceGroupId);
    }

    /**
     * @inheritDoc
     */
    public function nameAlreadyExists(string $serviceGroupName): bool
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT 1 FROM `:db`.`servicegroup` WHERE sg_name = :name
                    SQL
            )
        );
        $statement->bindValue(':name', $serviceGroupName);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $serviceGroupIds): array
    {
        $concatenator = $this->getFindServiceGroupConcatenator();

        return $this->existServiceGroup($concatenator, $serviceGroupIds);
    }

    /**
     * @inheritDoc
     */
    public function existByAccessGroups(array $serviceGroupIds, array $accessGroups): array
    {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllServiceGroups($accessGroupIds)) {

            return $this->exist($serviceGroupIds);
        }
        $concatenator = $this->getFindServiceGroupConcatenator($accessGroupIds);

        return $this->existServiceGroup($concatenator, $serviceGroupIds);
    }

    /**
     * @inheritDoc
     */
    public function findByService(int $serviceId): array
    {
        $concatenator = $this->getFindServiceGroupConcatenator();

        return $this->retrieveServiceGroupsByService($concatenator, $serviceId);
    }

    /**
     * @inheritDoc
     */
    public function findByServiceAndAccessGroups(int $serviceId, array $accessGroups): array
    {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllServiceGroups($accessGroupIds)) {

            return $this->findByService($serviceId);
        }
        $concatenator = $this->getFindServiceGroupConcatenator($accessGroupIds);

        return $this->retrieveServiceGroupsByService($concatenator, $serviceId);
    }

    /**
     * @inheritDoc
     */
    public function findNames(array $serviceGroupIds): ServiceGroupNamesById
    {
        $concatenator = new SqlConcatenator();

        $serviceGroupIds = array_unique($serviceGroupIds);

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT sg.sg_id, sg.sg_name
                FROM `:db`.servicegroup sg
                WHERE sg.sg_id IN (:serviceGroupIds)
                SQL
        );
        $concatenator->storeBindValueMultiple(':serviceGroupIds', $serviceGroupIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $groupNames = new ServiceGroupNamesById();

        foreach ($statement as $result) {
            /** @var array{sg_id:int,sg_name:string} $result */
            $groupNames->addName(
                $result['sg_id'],
                new TrimmedString($result['sg_name'])
            );
        }

        return $groupNames;
    }

    /**
     * @inheritDoc
     */
    public function findByIds(int ...$serviceGroupIds): array
    {
        if ($serviceGroupIds === []) {
            return [];
        }

        [$bindValues, $serviceGroupIdsQuery] = $this->createMultipleBindQuery($serviceGroupIds, ':id_');
        $request = <<<SQL
            SELECT
                sg_id,
                sg_name,
                sg_alias,
                geo_coords,
                sg_comment,
                sg_activate
            FROM `:db`.`servicegroup`
            WHERE sg_id IN ({$serviceGroupIdsQuery})
            ORDER BY sg_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        foreach ($bindValues as $bindKey => $serviceGroupId) {
            $statement->bindValue($bindKey, $serviceGroupId, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $serviceGroups = [];

        /** @var ServiceGroupResultSet $result */
        foreach ($statement as $result) {
            $serviceGroups[] = ServiceGroupFactory::createFromDb($result);
        }

        return $serviceGroups;
    }

    /**
     * @param list<int> $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getFindServiceGroupConcatenator(array $accessGroupIds = []): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        sg.sg_id,
                        sg.sg_name,
                        sg.sg_alias,
                        sg.geo_coords,
                        sg.sg_comment,
                        sg.sg_activate
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`servicegroup` sg
                    SQL
            )
            ->defineOrderBy(
                <<<'SQL'
                    ORDER BY sg.sg_name ASC
                    SQL
            );

        if ([] !== $accessGroupIds) {
            $concatenator
                ->appendJoins(
                    <<<'SQL'
                        INNER JOIN `:db`.acl_resources_sg_relations arsr
                            ON sg.sg_id = arsr.sg_id
                        INNER JOIN `:db`.acl_resources res
                            ON arsr.acl_res_id = res.acl_res_id
                        INNER JOIN `:db`.acl_res_group_relations argr
                            ON res.acl_res_id = argr.acl_res_id
                        INNER JOIN `:db`.acl_groups ag
                            ON argr.acl_group_id = ag.acl_group_id
                        SQL
                )
                ->appendWhere(
                    <<<'SQL'
                        WHERE ag.acl_group_id IN (:ids)
                        SQL
                )
                ->storeBindValueMultiple(':ids', $accessGroupIds, \PDO::PARAM_INT);
        }

        return $concatenator;
    }

    /**
     * @param non-empty-list<AccessGroup> $accessGroups
     *
     * @return non-empty-list<int>
     */
    private function accessGroupsToIds(array $accessGroups): array
    {
        return array_map(
            static fn (AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $serviceGroupId
     *
     * @throws \PDOException
     *
     * @return bool
     */
    private function existsServiceGroup(SqlConcatenator $concatenator, int $serviceGroupId): bool
    {
        $concatenator
            // We override the select because we just need to get the ID to check the existence.
            ->defineSelect(
                <<<'SQL'
                    SELECT 1
                    SQL
            )
            // We add the filtering by service group id.
            ->appendWhere(
                <<<'SQL'
                    WHERE sg.sg_id = :servicegroup_id
                    SQL
            )
            ->storeBindValue(':servicegroup_id', $serviceGroupId, \PDO::PARAM_INT);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int[] $serviceGroupIds
     *
     * @throws \PDOException
     *
     * @return int[]
     */
    private function existServiceGroup(SqlConcatenator $concatenator, array $serviceGroupIds): array
    {
        $concatenator
            ->defineSelect(
                <<<'SQL'
                    SELECT sg.sg_id
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE sg.sg_id IN (:servicegroup_ids)
                    SQL
            )
            ->storeBindValueMultiple(':servicegroup_ids', $serviceGroupIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $serviceGroupId
     *
     * @throws InvalidGeoCoordException
     * @throws \PDOException
     * @throws AssertionFailedException
     *
     * @return ServiceGroup|null
     */
    private function retrieveServiceGroup(SqlConcatenator $concatenator, int $serviceGroupId): ?ServiceGroup
    {
        // We add the filtering by service group id.
        $concatenator
            ->appendWhere(
                <<<'SQL'
                    WHERE sg.sg_id = :servicegroup_id
                    SQL
            )
            ->storeBindValue(':servicegroup_id', $serviceGroupId, \PDO::PARAM_INT);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        // Retrieve the first row
        /** @var null|false|ServiceGroupResultSet $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? ServiceGroupFactory::createFromDb($data) : null;
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $serviceId
     *
     * @throws InvalidGeoCoordException
     * @throws \PDOException
     * @throws AssertionFailedException
     *
     * @return array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}>
     */
    private function retrieveServiceGroupsByService(SqlConcatenator $concatenator, int $serviceId): array
    {
        $concatenator
            ->appendSelect(
                <<<'SQL'
                    sgr.host_host_id
                    SQL
            )
            ->appendJoins(
                <<<'SQL'
                    INNER JOIN `:db`.servicegroup_relation sgr
                        ON sgr.servicegroup_sg_id = sg.sg_id
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE sgr.service_service_id = :service_id
                    SQL
            )
            ->storeBindValue(':service_id', $serviceId, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();
        $serviceGroups = [];

        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{
             *  sg_id:int,
             *  sg_name:string,
             *  sg_alias:string,
             *  geo_coords:string|null,
             *  sg_comment:string|null,
             *  sg_activate:'0'|'1',
             *  host_host_id:int
             * } $result */
            $serviceGroups[] = [
                'relation' => new ServiceGroupRelation(
                    serviceGroupId: $result['sg_id'],
                    serviceId: $serviceId,
                    hostId: $result['host_host_id'],
                ),
                'serviceGroup' => ServiceGroupFactory::createFromDb($result),
            ];
        }

        return $serviceGroups;
    }
}
