<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\SqlConcatenator;

/**
 * @phpstan-type HostGroupResultSet array{
 *     hg_id: positive-int,
 *     hg_name: string,
 *     hg_alias: ?string,
 *     hg_notes: ?string,
 *     hg_notes_url: ?string,
 *     hg_action_url: ?string,
 *     hg_icon_image: ?positive-int,
 *     hg_map_icon_image: ?positive-int,
 *     hg_rrd_retention: ?int,
 *     geo_coords: ?string,
 *     hg_comment: ?string,
 *     hg_activate: '0'|'1'
 * }
 */
class DbReadHostGroupRepository extends AbstractRepositoryDRB implements ReadHostGroupRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters): array
    {
        $concatenator = $this->getFindHostGroupConcatenator();

        return $this->retrieveHostGroups($concatenator, $requestParameters);
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroups(?RequestParametersInterface $requestParameters, array $accessGroups): array
    {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        $concatenator = $this->getFindHostGroupConcatenator($accessGroupIds);

        return $this->retrieveHostGroups($concatenator, $requestParameters);
    }

    /**
     * @param list<int> $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getFindHostGroupConcatenator(array $accessGroupIds = []): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
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

        if ([] !== $accessGroupIds) {
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
     * @return list<HostGroup>
     */
    private function retrieveHostGroups(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // If we use RequestParameters
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'hg.hg_id',
            'alias' => 'hg.hg_alias',
            'name' => 'hg.hg_name',
            'is_activated' => 'hg.hg_activate',
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
        $hostGroups = [];
        foreach ($statement as $result) {
            /** @var HostGroupResultSet $result */
            $hostGroups[] = $this->createHostGroupFromArray($result);
        }

        return $hostGroups;
    }

    /**
     * @param array $result
     *
     * @phpstan-param HostGroupResultSet $result
     *
     * @throws InvalidGeoCoordException
     * @throws AssertionFailedException
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
}
