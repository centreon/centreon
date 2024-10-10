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

namespace Core\HostGroup\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\HostCategory\Infrastructure\Repository\HostCategoryRepositoryTrait;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostGroupNamesById;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\SqlConcatenator;

/**
 * @phpstan-type HostGroupResultSet array{
 *     hg_id: int,
 *     hg_name: string,
 *     hg_alias: ?string,
 *     hg_notes: ?string,
 *     hg_notes_url: ?string,
 *     hg_action_url: ?string,
 *     hg_icon_image: ?int,
 *     hg_map_icon_image: ?int,
 *     hg_rrd_retention: ?int,
 *     geo_coords: ?string,
 *     hg_comment: ?string,
 *     hg_activate: '0'|'1'
 * }
 */
class DbReadHostGroupRepository extends AbstractRepositoryDRB implements ReadHostGroupRepositoryInterface
{
    use SqlMultipleBindTrait, HostCategoryRepositoryTrait, HostGroupRepositoryTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findNames(array $hostGroupIds): HostGroupNamesById
    {
        $concatenator = new SqlConcatenator();

        $hostGroupIds = array_unique($hostGroupIds);

        $concatenator->defineSelect(
            <<<'SQL'
                    SELECT hg.hg_id, hg.hg_name
                    FROM `:db`.hostgroup hg
                    WHERE hg.hg_id IN (:hostGroupIds)
                SQL
        );

        $concatenator->storeBindValueMultiple(':hostGroupIds', $hostGroupIds, \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $names = new HostGroupNamesById();

        foreach ($statement as $record) {
            /** @var array{hg_id:int,hg_name:string} $record */
            $names->addName(
                $record['hg_id'],
                new TrimmedString($record['hg_name'])
            );
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters = null): \Traversable&\Countable
    {
        $request = <<<'SQL_WRAP'
            SELECT SQL_CALC_FOUND_ROWS DISTINCT
                hg.hg_id,
                hg.hg_name,
                hg.hg_alias,
                hg.hg_notes,
                hg.hg_notes_url,
                hg.hg_action_url,
                hg.hg_icon_image,
                hg.hg_map_icon_image,
                hg.hg_rrd_retention,
                hg.geo_coords,
                hg.hg_comment,
                hg.hg_activate
            FROM `:db`.`hostgroup` hg
            SQL_WRAP;

        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'hg.hg_id',
            'alias' => 'hg.hg_alias',
            'name' => 'hg.hg_name',
            'is_activated' => 'hg.hg_activate',
            'hostcategory.id' => 'hc.hc_id',
            'hostcategory.name' => 'hc.hc_name',
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $searchRequest = $sqlTranslator?->translateSearchParameterToSql();

        // Only JOIN if search request has been provided...
        if ($searchRequest !== null) {
            $request .= <<<'SQL'
                    LEFT JOIN `:db`.hostgroup_relation hgr
                        ON hgr.hostgroup_hg_id = hg.hg_id
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = hgr.host_host_id
                    LEFT JOIN `:db`.hostcategories hc
                        ON hc.hc_id = hcr.hostcategories_hc_id
                        AND hc.level IS NULL
                SQL;
        }

        $request .= $searchRequest;

        // handle sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();

        $request .= $sortRequest ?? ' ORDER BY hg.hg_name ASC';

        // handle pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($request));
        $sqlTranslator?->bindSearchValues($statement);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Calculate the number of rows for the pagination.
        $sqlTranslator?->calculateNumberOfRows($this->db);

        $hostGroups = [];

        foreach ($statement as $record) {
            /** @var HostGroupResultSet $record */
            $hostGroups[] = $this->createHostGroupFromArray($record);
        }

        return new \ArrayIterator($hostGroups);
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroupIds(?RequestParametersInterface $requestParameters, array $accessGroupIds): \Traversable&\Countable
    {
        if ([] === $accessGroupIds) {
            return new \ArrayIterator([]);
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        $request = <<<'SQL_WRAP'
            SELECT SQL_CALC_FOUND_ROWS DISTINCT
                hg.hg_id,
                hg.hg_name,
                hg.hg_alias,
                hg.hg_notes,
                hg.hg_notes_url,
                hg.hg_action_url,
                hg.hg_icon_image,
                hg.hg_map_icon_image,
                hg.hg_rrd_retention,
                hg.geo_coords,
                hg.hg_comment,
                hg.hg_activate
            FROM `:db`.`hostgroup` hg
            INNER JOIN `:db`.acl_resources_hg_relations arhr
                ON hg.hg_id = arhr.hg_hg_id
            INNER JOIN `:db`.acl_resources res
                ON arhr.acl_res_id = res.acl_res_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN `:db`.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id
            SQL_WRAP;

        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'hg.hg_id',
            'alias' => 'hg.hg_alias',
            'name' => 'hg.hg_name',
            'is_activated' => 'hg.hg_activate',
            'hostcategory.id' => 'hc.hc_id',
            'hostcategory.name' => 'hc.hc_name',
        ]);
        $sqlTranslator?->addNormalizer('is_activated', new BoolToEnumNormalizer());

        // Update the SQL string builder with the RequestParameters through SqlRequestParametersTranslator
        $searchRequest = $sqlTranslator?->translateSearchParameterToSql();

        // Only JOIN if search request has been provided...
        if ($searchRequest !== null) {
            $hostCategoryAcls = $this->generateHostCategoryAclSubRequest($accessGroupIds);
            $request .= <<<SQL

                LEFT JOIN `:db`.hostgroup_relation hgr
                    ON hgr.hostgroup_hg_id = hg.hg_id
                LEFT JOIN `:db`.hostcategories_relation hcr
                    ON hcr.host_host_id = hgr.host_host_id
                    AND hcr.hostcategories_hc_id IN ({$hostCategoryAcls})
                LEFT JOIN `:db`.hostcategories hc
                    ON hc.hc_id = hcr.hostcategories_hc_id
                    AND hc.level IS NULL
                SQL;
        }

        $request .= $searchRequest ? $searchRequest . ' AND ' : ' WHERE ';

        $request .= <<<SQL
            ag.acl_group_id IN ({$bindQuery})
            SQL;

        // handle sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();

        $request .= $sortRequest ?? ' ORDER BY hg.hg_name ASC';

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

        $hostGroups = [];

        foreach ($statement as $record) {
            /** @var HostGroupResultSet $record */
            $hostGroups[] = $this->createHostGroupFromArray($record);
        }

        return new \ArrayIterator($hostGroups);
    }

    /**
     * @inheritDoc
     */
    public function findOne(int $hostGroupId): ?HostGroup
    {
        $concatenator = $this->getFindHostGroupConcatenator();

        return $this->retrieveHostgroup($concatenator, $hostGroupId);
    }

    /**
     * @inheritDoc
     */
    public function findOneByAccessGroups(int $hostGroupId, array $accessGroups): ?HostGroup
    {
        if ([] === $accessGroups) {
            return null;
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);

        if ($this->hasAccessToAllHostGroups($accessGroupIds)) {

            return $this->findOne($hostGroupId);
        }
        $concatenator = $this->getFindHostGroupConcatenator(null, $accessGroupIds);

        return $this->retrieveHostgroup($concatenator, $hostGroupId);
    }

    /**
     * @inheritDoc
     */
    public function existsOne(int $hostGroupId): bool
    {
        $concatenator = $this->getFindHostGroupConcatenator();

        return $this->existsHostGroup($concatenator, $hostGroupId);
    }

    /**
     * @inheritDoc
     */
    public function existsOneByAccessGroups(int $hostGroupId, array $accessGroups): bool
    {
        if ([] === $accessGroups) {
            return false;
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllHostGroups($accessGroupIds)) {

            return $this->existsOne($hostGroupId);
        }
        $concatenator = $this->getFindHostGroupConcatenator(null, $accessGroupIds);

        return $this->existsHostGroup($concatenator, $hostGroupId);
    }

    /**
     * @inheritDoc
     */
    public function exist(array $hostGroupIds): array
    {
        $concatenator = $this->getFindHostGroupConcatenator();

        return $this->existHostGroups($concatenator, $hostGroupIds);
    }

    /**
     * @inheritDoc
     */
    public function existByAccessGroups(array $hostGroupIds, array $accessGroups): array
    {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllHostGroups($accessGroupIds)) {

            return $this->exist($hostGroupIds);
        }
        $concatenator = $this->getFindHostGroupConcatenator(null, $accessGroupIds);

        return $this->existHostGroups($concatenator, $hostGroupIds);
    }

    /**
     * @inheritDoc
     */
    public function nameAlreadyExists(string $hostGroupName): bool
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT 1 FROM `:db`.`hostgroup` WHERE hg_name = :name
                    SQL
            )
        );
        $statement->bindValue(':name', $hostGroupName);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findByHost(int $hostId): array
    {
        $concatenator = $this->getFindHostGroupConcatenator();

        return $this->retrieveHostGroupsByHost($concatenator, $hostId);
    }

    /**
     * @inheritDoc
     */
    public function findByHostAndAccessGroups(int $hostId, array $accessGroups): array
    {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        if ($this->hasAccessToAllHostGroups($accessGroupIds)) {

            return $this->findByHost($hostId);
        }
        $concatenator = $this->getFindHostGroupConcatenator(null, $accessGroupIds);

        return $this->retrieveHostGroupsByHost($concatenator, $hostId);
    }

    /**
     * @inheritDoc
     */
    public function findByIds(int ...$hostGroupIds): array
    {
        if ($hostGroupIds === []) {
            return [];
        }

        [$bindValues, $hostGroupIdsQuery] = $this->createMultipleBindQuery($hostGroupIds, ':hg_');

        $request = <<<SQL
            SELECT
                hg.hg_id,
                hg.hg_name,
                hg.hg_alias,
                hg.hg_notes,
                hg.hg_notes_url,
                hg.hg_action_url,
                hg.hg_icon_image,
                hg.hg_map_icon_image,
                hg.hg_rrd_retention,
                hg.geo_coords,
                hg.hg_comment,
                hg.hg_activate
            FROM `:db`.`hostgroup` hg
            WHERE hg.hg_id IN ({$hostGroupIdsQuery})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        foreach ($bindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $hostGroups = [];
        foreach ($statement as $result) {
            /** @var HostGroupResultSet $result */
            $hostGroups[] = $this->createHostGroupFromArray($result);
        }

        return $hostGroups;
    }

    /**
     * @param ?RequestParametersInterface $requestParameters
     * @param list<int> $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getFindHostGroupConcatenator(
        ?RequestParametersInterface $requestParameters = null,
        array $accessGroupIds = []
    ): SqlConcatenator {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT DISTINCT
                        hg.hg_id,
                        hg.hg_name,
                        hg.hg_alias,
                        hg.hg_notes,
                        hg.hg_notes_url,
                        hg.hg_action_url,
                        hg.hg_icon_image,
                        hg.hg_map_icon_image,
                        hg.hg_rrd_retention,
                        hg.geo_coords,
                        hg.hg_comment,
                        hg.hg_activate
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`hostgroup` hg
                    SQL
            )
            ->defineOrderBy(
                <<<'SQL'
                    ORDER BY hg.hg_name ASC
                    SQL
            );

        $hostCategoryAcls = '';
        if ([] !== $accessGroupIds) {
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
                        WHERE ag.acl_group_id IN (:ids)
                    )
                    SQL;
            }

            $concatenator
                ->appendJoins(
                    <<<'SQL'
                        INNER JOIN `:db`.acl_resources_hg_relations arhr
                            ON hg.hg_id = arhr.hg_hg_id
                        INNER JOIN `:db`.acl_resources res
                            ON arhr.acl_res_id = res.acl_res_id
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

        $search = $requestParameters?->getSearchAsString();
        if ($search !== null && \str_contains($search, 'hostcategory')) {
            $concatenator->appendJoins(
                <<<SQL
                    LEFT JOIN `:db`.hostgroup_relation hgr
                        ON hgr.hostgroup_hg_id = hg.hg_id
                    LEFT JOIN `:db`.hostcategories_relation hcr
                        ON hcr.host_host_id = hgr.host_host_id
                        {$hostCategoryAcls}
                    LEFT JOIN `:db`.hostcategories hc
                        ON hc.hc_id = hcr.hostcategories_hc_id
                        AND hc.level IS NULL
                    SQL
            );
        }

        return $concatenator;
    }

    /**
     * @param list<AccessGroup> $accessGroups
     *
     * @return list<int>
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
     * @param int $hostId
     *
     * @throws InvalidGeoCoordException
     * @throws RequestParametersTranslatorException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws AssertionFailedException
     *
     * @return list<HostGroup>
     */
    private function retrieveHostGroupsByHost(
        SqlConcatenator $concatenator,
        int $hostId
    ): array {
        $concatenator
            ->appendJoins(
                <<<'SQL'
                    JOIN `hostgroup_relation` hg_rel
                        ON hg.hg_id = hg_rel.hostgroup_hg_id
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE hg_rel.host_host_id = :hostId
                    SQL
            )
            ->storeBindValue(':hostId', $hostId, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $hostGroups = [];
        foreach ($statement as $result) {
            /** @var HostGroupResultSet $result */
            $hostGroups[] = $this->createHostGroupFromArray($result);
        }

        return $hostGroups;
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $hostGroupId
     *
     * @throws \PDOException
     *
     * @return bool
     */
    private function existsHostGroup(SqlConcatenator $concatenator, int $hostGroupId): bool
    {
        $concatenator
            // We override the select because we just need to get the ID to check the existence.
            ->defineSelect(
                <<<'SQL'
                    SELECT 1
                    SQL
            )
            // We add the filtering by host group id.
            ->appendWhere(
                <<<'SQL'
                    WHERE hg.hg_id = :hostgroup_id
                    SQL
            )
            ->storeBindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int[] $hostGroupIds
     *
     * @throws \PDOException
     *
     * @return int[]
     */
    private function existHostGroups(SqlConcatenator $concatenator, array $hostGroupIds): array
    {
        $concatenator
            ->defineSelect(
                <<<'SQL'
                    SELECT hg.hg_id
                    SQL
            )
            ->appendWhere(
                <<<'SQL'
                    WHERE hg.hg_id IN (:host_group_ids)
                    SQL
            )
            ->storeBindValueMultiple(':host_group_ids', $hostGroupIds, \PDO::PARAM_INT);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $hostGroupId
     *
     * @throws InvalidGeoCoordException
     * @throws \PDOException
     * @throws AssertionFailedException
     *
     * @return HostGroup|null
     */
    private function retrieveHostgroup(SqlConcatenator $concatenator, int $hostGroupId): ?HostGroup
    {
        // We add the filtering by host group id.
        $concatenator
            ->appendWhere(
                <<<'SQL'
                    WHERE hg.hg_id = :hostgroup_id
                    SQL
            )
            ->storeBindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        // Retrieve the first row
        /** @var null|false|HostGroupResultSet $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createHostGroupFromArray($data) : null;
    }

    /**
     * @param array $result
     *
     * @phpstan-param HostGroupResultSet $result
     *
     * @throws AssertionFailedException
     * @throws InvalidGeoCoordException
     *
     * @return HostGroup
     */
    private function createHostGroupFromArray(array $result): HostGroup
    {
        return new HostGroup(
            $result['hg_id'],
            $result['hg_name'],
            (string) $result['hg_alias'],
            (string) $result['hg_notes'],
            (string) $result['hg_notes_url'],
            (string) $result['hg_action_url'],
            $result['hg_icon_image'],
            $result['hg_map_icon_image'],
            $result['hg_rrd_retention'],
            match ($geoCoords = $result['geo_coords']) {
                null, '' => null,
                default => GeoCoords::fromString($geoCoords),
            },
            (string) $result['hg_comment'],
            (bool) $result['hg_activate'],
        );
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
