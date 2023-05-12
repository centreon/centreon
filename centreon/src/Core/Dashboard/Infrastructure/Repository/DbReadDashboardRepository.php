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

namespace Core\Dashboard\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\SqlConcatenator;

/**
 * @phpstan-type DashboardResultSet array{
 *     id: int,
 *     name: string,
 *     description: ?string,
 *     created_at: int,
 *     updated_at: int
 * }
 */
class DbReadDashboardRepository extends AbstractRepositoryRDB implements ReadDashboardRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findByRequestParameter(?RequestParametersInterface $requestParameters): array
    {
        $concatenator = $this->getFindDashboardConcatenator();

        return $this->retrieveDashboards($concatenator, $requestParameters);
    }

    public function findByRequestParameterAndAccessGroups(
        array $accessGroups,
        ?RequestParametersInterface $requestParameters
    ): array {
        if ([] === $accessGroups) {
            return [];
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        $concatenator = $this->getFindDashboardConcatenator($accessGroupIds);

        return $this->retrieveDashboards($concatenator, $requestParameters);
    }

    public function findOne(int $dashboardId): ?Dashboard
    {
        $concatenator = $this->getFindDashboardConcatenator();

        return $this->retrieveDashboard($concatenator, $dashboardId);
    }

    public function findOneByAccessGroups(int $dashboardId, array $accessGroups): ?Dashboard
    {
        if ([] === $accessGroups) {
            return null;
        }

        $accessGroupIds = $this->accessGroupsToIds($accessGroups);
        $concatenator = $this->getFindDashboardConcatenator($accessGroupIds);

        return $this->retrieveDashboard($concatenator, $dashboardId);
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws AssertionFailedException
     * @throws RequestParametersTranslatorException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     *
     * @return list<Dashboard>
     */
    private function retrieveDashboards(
        SqlConcatenator $concatenator,
        ?RequestParametersInterface $requestParameters
    ): array {
        // If we use RequestParameters
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $sqlTranslator?->setConcordanceArray([
            'id' => 'd.id',
            'name' => 'd.name',
        ]);

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
        $dashboards = [];
        foreach ($statement as $result) {
            /** @var DashboardResultSet $result */
            $dashboards[] = $this->createDashboardFromArray($result);
        }

        return $dashboards;
    }

    /**
     * @param list<int> $accessGroupIds
     *
     * @return SqlConcatenator
     */
    private function getFindDashboardConcatenator(array $accessGroupIds = []): SqlConcatenator
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        d.id,
                        d.name,
                        d.description,
                        d.created_at,
                        d.updated_at
                    SQL
            )
            ->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.`dashboard` d
                    SQL
            )
            ->defineOrderBy(
                <<<'SQL'
                    ORDER BY d.name ASC
                    SQL
            );

        if ([] !== $accessGroupIds) {
            // At this stage of the development, we consider everybody can see all dashboards.
            // The ACL schema + filtering will be done in a future feature.
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
     * @param array $result
     *
     * @phpstan-param DashboardResultSet $result
     *
     * @throws AssertionFailedException
     * @throws \ValueError
     *
     * @return Dashboard
     */
    private function createDashboardFromArray(array $result): Dashboard
    {
        return new Dashboard(
            $result['id'],
            $result['name'],
            (string) $result['description'],
            $this->timestampToDateTimeImmutable($result['created_at']),
            $this->timestampToDateTimeImmutable($result['updated_at'])
        );
    }

    /**
     * @param SqlConcatenator $concatenator
     * @param int $dashboardId
     *
     * @throws AssertionFailedException
     * @throws \PDOException
     *
     * @return Dashboard|null
     */
    private function retrieveDashboard(SqlConcatenator $concatenator, int $dashboardId): ?Dashboard
    {
        // We add the filtering by dashboard id.
        $concatenator
            ->appendWhere(
                <<<'SQL'
                    WHERE d.id = :dashboard_id
                    SQL
            )
            ->storeBindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        // Retrieve the first row
        /** @var null|false|DashboardResultSet $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createDashboardFromArray($data) : null;
    }
}
