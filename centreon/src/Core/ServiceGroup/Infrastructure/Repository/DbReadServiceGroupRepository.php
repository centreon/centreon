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
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
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
    use SqlMultipleBindTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters): \Traversable&\Countable
    {
        $concatenator = $this->getFindServiceGroupConcatenator();
        
        $concatenator->appendJoins(
            <<<'SQL'
                    LEFT JOIN `:db`.servicegroup_relation sgr
                        ON sg.sg_id = sgr.servicegroup_sg_id
                    LEFT JOIN `:db`.host h
                        ON sgr.host_host_id = h.host_id
                SQL
        );
        if (mb_strpos($requestParameters?->getSearchAsString() ?? '', 'hostcategory.id')) {
            $concatenator->appendJoins(
                <<<'SQL'
                        LEFT JOIN `:db`.hostcategories_relation hcr
                            ON h.host_id = hcr.host_host_id
                        LEFT JOIN `:db`.hostcategories hc
                            ON hcr.hostcategories_hc_id = hc.hc_id
                            AND hc.level IS NOT NULL
                    SQL
            );
        }
        if (mb_strpos($requestParameters?->getSearchAsString() ?? '', 'hostgroup.id')) {
            $concatenator->appendJoins(
                <<<'SQL'
                        LEFT JOIN `:db`.hostgroup_relation hgr
                            ON  h.host_id = hgr.host_host_id
                        LEFT JOIN `:db`.hostgroup hg
                            ON hgr.hostgroup_hg_id = hg.hg_id
                    SQL
            );
        }

        return new \ArrayIterator($this->retrieveServiceGroups($concatenator, $requestParameters));
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroups(?RequestParametersInterface $requestParameters, array $accessGroups): \Traversable&\Countable
    {
        $hostAcls = '';
        $hostGroupAcls = '';
        $hostCategoryAcls = '';
        if ([] !== $accessGroups) {
            if (! $this->hasAccessToAllHosts($accessGroups)) {
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

            if (! $this->hasAccessToAllHostGroups($accessGroups)) {
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

            if ($this->hasRestrictedAccessToHostCategories($accessGroups)) {
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
        }
        if ([] === $accessGroups) {
            return new \ArrayIterator([]);
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllServiceGroups($accessGroupIds)) {

            return $this->findAll($requestParameters);
        }

        $concatenator = $this->getFindServiceGroupConcatenator($accessGroupIds);

        $concatenator->appendJoins(
            <<<'SQL'
                    LEFT JOIN `:db`.servicegroup_relation sgr
                        ON sg.sg_id = sgr.servicegroup_sg_id
                    LEFT JOIN `:db`.host h
                        ON sgr.host_host_id = h.host_id
                        {$hostAcls}
                SQL
        )
            ->appendJoins(
                <<<'SQL'
                        LEFT JOIN `:db`.hostcategories_relation hcr
                            ON h.host_id = hcr.host_host_id
                        LEFT JOIN `:db`.hostcategories hc
                            ON hcr.hostcategories_hc_id = hc.hc_id
                            AND hc.level IS NOT NULL
                            {$hostCategoryAcls}
                    SQL
            )
            ->appendJoins(
                <<<'SQL'
                        LEFT JOIN `:db`.hostgroup_relation hgr
                            ON  h.host_id = hgr.host_host_id
                        LEFT JOIN `:db`.hostgroup hg
                            ON hgr.hostgroup_hg_id = hg.hg_id
                            {$hostGroupAcls}
                    SQL
            );

        return new \ArrayIterator($this->retrieveServiceGroups($concatenator, $requestParameters));
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
                    SELECT DISTINCT
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

        if (! empty($_GET['search']) && mb_strpos($_GET['search'], 'host_group_id')) {
            $concatenator->appendJoins(
                <<<'SQL'
                    LEFT JOIN `:db`.acl_resources_sg_relations arsr
                        ON sg.sg_id = arsr.sg_id
                    LEFT JOIN `:db`.acl_resources res
                            ON arsr.acl_res_id = res.acl_res_id
                    LEFT JOIN `:db`.acl_resources_hg_relations arhgr
                        ON res.acl_res_id = arhgr.acl_res_id
                    LEFT JOIN `:db`.hostgroup hg
                        ON arhgr.hg_hg_id = hg.hg_id
                    SQL
            );
        } else if (! empty($_GET['search']) && mb_strpos($_GET['search'], 'host_id')) {
            $concatenator->appendJoins(
                <<<'SQL'
                    LEFT JOIN `:db`.acl_resources_sg_relations arsr
                        ON sg.sg_id = arsr.sg_id
                    LEFT JOIN `:db`.acl_resources res
                            ON arsr.acl_res_id = res.acl_res_id
                    LEFT JOIN `:db`.acl_resources_host_relations arhr
                        ON res.acl_res_id = arhr.acl_res_id
                    LEFT JOIN `:db`.host h
                        ON arhr.host_host_id = h.host_id
                    SQL
            );
        } else if (! empty($_GET['search']) && mb_strpos($_GET['search'], 'host_category_id')) {
            $concatenator->appendJoins(
                <<<'SQL'
                    LEFT JOIN `:db`.acl_resources_sg_relations arsr
                        ON sg.sg_id = arsr.sg_id
                    LEFT JOIN `:db`.acl_resources res
                            ON arsr.acl_res_id = res.acl_res_id
                    LEFT JOIN `:db`.acl_resources_hc_relations arhcr
                        ON res.acl_res_id = arhcr.acl_res_id
                    LEFT JOIN `:db`.hostcategories hc
                        ON arhcr.hc_id = hc.hc_id
                        AND hc.level IS NOT NULL
                    SQL
            );
        }

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
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $accessGroups
        );
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws InvalidGeoCoordException
     * @throws RequestParametersTranslatorException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws AssertionFailedException
     *
     * @return list<ServiceGroup>
     */
    private function retrieveServiceGroups(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // If we use RequestParameters
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
        ]);
        
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $sqlTranslator?->translateForConcatenator($concatenator);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $sqlTranslator?->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
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

        return $serviceGroups;
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

    /**
     * Determine if accessGroups give access to all serviceGroups
     * true: all service groups are accessible
     * false: all service groups are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    private function hasAccessToAllServiceGroups(array $accessGroupIds): bool
    {
        $concatenator = new SqlConcatenator();

        $concatenator->defineSelect(
            <<<'SQL'
                SELECT res.all_servicegroups
                FROM `:db`.acl_resources res
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                SQL
        );

        $concatenator->storeBindValueMultiple(':access_group_ids', $accessGroupIds, \PDO::PARAM_INT)
            ->appendWhere('ag.acl_group_id IN (:access_group_ids)');

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        while (false !== ($hasAccessToAll = $statement->fetchColumn())) {
            if (true === (bool) $hasAccessToAll) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if access groups give access to all host groups
     * true: all host groups are accessible
     * false: all host groups are NOT accessible.
     *
     * @param int[] $accessGroups
     *
     * @phpstan-param non-empty-array<int> $accessGroups
     *
     * @return bool
     */
    private function hasAccessToAllHostGroups(array $accessGroups): bool
    {
        $bindValuesArray = [];
        foreach ($accessGroups as $index => $accessGroupId) {
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
     * @param int[] $accessGroups
     *
     * @phpstan-param non-empty-array<int> $accessGroups
     *
     * @return bool
     */
    private function hasAccessToAllHosts(array $accessGroups): bool
    {
        $bindValuesArray = [];
        foreach ($accessGroups as $index => $accessGroupId) {
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
     * @param int[] $accessGroups
     *
     * @phpstan-param non-empty-array<int> $accessGroups
     *
     * @return bool
     */
    private function hasRestrictedAccessToHostCategories(array $accessGroups): bool
    {
        $bindValuesArray = [];
        foreach ($accessGroups as $index => $accessGroupId) {
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
