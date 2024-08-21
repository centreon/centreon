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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface as RepositoryInterface;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadDashboardPerformanceMetricRepository extends AbstractRepositoryDRB implements RepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     * @param array<
     *  string, array{
     *    request: string,
     *    bindValues: array<mixed>
     *  }
     * > $subRequestsInformation
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly SqlRequestParametersTranslator $sqlRequestTranslator,
        private array $subRequestsInformation = []
    ) {
        $this->db = $db;
        $this->sqlRequestTranslator->setConcordanceArray(['current_value' => 'm.current_value']);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $request = $this->buildQuery($requestParameters);
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement = $this->executeQuery($statement);

        return $this->buildResourceMetrics($requestParameters, $statement);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array {
        $request = $this->buildQuery($requestParameters, $accessGroups);
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement = $this->executeQuery($statement);

        return $this->buildResourceMetrics($requestParameters, $statement);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndMetricName(RequestParametersInterface $requestParameters, string $metricName): array
    {
        $request = $this->buildQuery($requestParameters, [], true);
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement = $this->executeQuery($statement, $metricName);

        return $this->buildResourceMetrics($requestParameters, $statement);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroupsAndMetricName(
        RequestParametersInterface $requestParameters,
        array $accessGroups,
        string $metricName
    ): array {
        $request = $this->buildQuery($requestParameters, $accessGroups, true);
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement = $this->executeQuery($statement, $metricName);

        return $this->buildResourceMetrics($requestParameters, $statement);
    }

    /**
     * build the sub request for service filter.
     *
     * @param non-empty-array<string> $serviceNames
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForServiceFilter(array $serviceNames): array
    {
        foreach ($serviceNames as $key => $serviceName) {
            $bindServiceNames[':service_name' . $key] = [$serviceName => \PDO::PARAM_STR];
        }
        $bindTokens = implode(', ', array_keys($bindServiceNames));

        return [
            'request' => <<<SQL
                    AND r.name IN ({$bindTokens})
                    AND r.type = 0
                SQL,
            'bindValues' => $bindServiceNames,
        ];
    }

    /**
     * build the sub request for host filter.
     *
     * @param non-empty-array<int> $hostIds
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForHostFilter(array $hostIds): array
    {
        foreach ($hostIds as $hostId) {
            $bindHostIds[':host_' . $hostId] = [$hostId => \PDO::PARAM_INT];
        }
        $bindTokens = implode(', ', array_keys($bindHostIds));

        return [
            'request' => <<<SQL
                    AND r.parent_id IN ({$bindTokens})
                SQL,
            'bindValues' => $bindHostIds,
        ];
    }

    /**
     * build the sub request for host group filter.
     *
     * @param non-empty-array<int> $hostGroupIds
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForHostGroupFilter(array $hostGroupIds): array
    {
        $bindValues = [];
        foreach ($hostGroupIds as $hostGroupId) {
            $bindValues[':hostgroup_' . $hostGroupId] = [$hostGroupId => \PDO::PARAM_INT];
        }
        $boundTokens = implode(', ', array_keys($bindValues));

        return [
            'request' => <<<SQL
                    SELECT resources.resource_id
                    FROM `:dbstg`.`resources` resources
                    LEFT JOIN `:dbstg`.`resources` parent_resource
                        ON parent_resource.id = resources.parent_id
                    LEFT JOIN `:dbstg`.resources_tags AS rtags
                    ON rtags.resource_id = parent_resource.resource_id
                    INNER JOIN `:dbstg`.tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 1
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * build the sub request for host category filter.
     *
     * @param non-empty-array<int> $hostCategoryIds
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForHostCategoryFilter(array $hostCategoryIds): array
    {
        $bindValues = [];
        foreach ($hostCategoryIds as $hostCategoryId) {
            $bindValues[':hostcategory_' . $hostCategoryId] = [$hostCategoryId => \PDO::PARAM_INT];
        }
        $boundTokens = implode(', ', array_keys($bindValues));

        return [
            'request' => <<<SQL
                    SELECT resources.resource_id
                    FROM `:dbstg`.`resources` resources
                    LEFT JOIN `:dbstg`.`resources` parent_resource
                        ON parent_resource.id = resources.parent_id
                    LEFT JOIN `:dbstg`.resources_tags AS rtags
                    ON rtags.resource_id = parent_resource.resource_id
                    INNER JOIN `:dbstg`.tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 3
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * build the sub request for service group filter.
     *
     * @param non-empty-array<int> $serviceGroupIds
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForServiceGroupFilter(array $serviceGroupIds): array
    {
        $bindValues = [];
        foreach ($serviceGroupIds as $serviceGroupId) {
            $bindValues[':servicegroup_' . $serviceGroupId] = [$serviceGroupId => \PDO::PARAM_INT];
        }
        $boundTokens = implode(', ', array_keys($bindValues));

        return [
            'request' => <<<SQL
                    SELECT rtags.resource_id
                    FROM `:dbstg`.resources_tags AS rtags
                    INNER JOIN `:dbstg`.tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 0
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * build the sub request for service category filter.
     *
     * @param non-empty-array<int> $serviceCategoryIds
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForServiceCategoryFilter(array $serviceCategoryIds): array
    {
        $bindValues = [];
        foreach ($serviceCategoryIds as $serviceCategoryId) {
            $bindValues[':servicecategory_' . $serviceCategoryId] = [$serviceCategoryId => \PDO::PARAM_INT];
        }
        $boundTokens = implode(', ', array_keys($bindValues));

        return [
            'request' => <<<SQL
                    SELECT rtags.resource_id
                    FROM `:dbstg`.resources_tags AS rtags
                    INNER JOIN `:dbstg`.tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 2
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * Get request and bind values information for each search filter.
     *
     * @phpstan-param array{
     *     '$and': array<
     *         array{
     *                   'service.name'?: array{'$in': non-empty-array<string>},
     *                        'host.id'?: array{'$in': non-empty-array<int>},
     *                   'hostgroup.id'?: array{'$in': non-empty-array<int>},
     *                'servicegroup.id'?: array{'$in': non-empty-array<int>},
     *                'hostcategory.id'?: array{'$in': non-empty-array<int>},
     *             'servicecategory.id'?: array{'$in': non-empty-array<int>},
     *         }
     *     >
     * } $search
     *
     * @param array<mixed> $search
     *
     * @return array<
     *  string, array{
     *    request: string,
     *    bindValues: array<mixed>
     *   }
     * >
     */
    private function getSubRequestsInformation(array $search): array
    {
        $searchParameters = $search['$and'];
        $subRequestsInformation = [];
        foreach ($searchParameters as $searchParameter) {
            if (
                array_key_exists('service.name', $searchParameter)
                && array_key_exists('$in', $searchParameter['service.name'])
            ) {
                $subRequestsInformation['service'] = $this->buildSubRequestForServiceFilter(
                    $searchParameter['service.name']['$in']
                );
            }
            if (
                array_key_exists('host.id', $searchParameter)
                && array_key_exists('$in', $searchParameter['host.id'])
            ) {
                $subRequestsInformation['host'] = $this->buildSubRequestForHostFilter(
                    $searchParameter['host.id']['$in']
                );
            }
            if (
                array_key_exists('hostgroup.id', $searchParameter)
                && array_key_exists('$in', $searchParameter['hostgroup.id'])
            ) {
                $subRequestsInformation['hostgroup'] = $this->buildSubRequestForHostGroupFilter(
                    $searchParameter['hostgroup.id']['$in']
                );
            }
            if (
                array_key_exists('servicegroup.id', $searchParameter)
                && array_key_exists('$in', $searchParameter['servicegroup.id'])
            ) {
                $subRequestsInformation['servicegroup'] = $this->buildSubRequestForServiceGroupFilter(
                    $searchParameter['servicegroup.id']['$in']
                );
            }
            if (
                array_key_exists('hostcategory.id', $searchParameter)
                && array_key_exists('$in', $searchParameter['hostcategory.id'])
            ) {
                $subRequestsInformation['hostcategory'] = $this->buildSubRequestForHostCategoryFilter(
                    $searchParameter['hostcategory.id']['$in']
                );
            }
            if (
                array_key_exists('servicecategory.id', $searchParameter)
                && array_key_exists('$in', $searchParameter['servicecategory.id'])
            ) {
                $subRequestsInformation['servicecategory'] = $this->buildSubRequestForServiceCategoryFilter(
                    $searchParameter['servicecategory.id']['$in']
                );
            }
        }

        return $subRequestsInformation;
    }

    /**
     * Build the subrequest for tags filter.
     *
     * @param array<
     *   string, array{
     *     request: string,
     *     bindValues: array<mixed>
     *   }
     * > $subRequestInformation
     *
     * @return string
     */
    private function buildSubRequestForTags(array $subRequestInformation): string
    {
        $request = '';
        $subRequestForTags = array_reduce(array_keys($subRequestInformation), function ($acc, $item) use (
            $subRequestInformation
        ) {
            if ($item !== 'host' && $item !== 'service' && $item !== 'metric') {
                $acc[] = $subRequestInformation[$item];
            }

            return $acc;
        }, []);

        if (! empty($subRequestForTags)) {
            $subRequests = array_map(fn ($subRequestForTag) => $subRequestForTag['request'], $subRequestForTags);
            $request .= ' INNER JOIN (';
            $request .= implode(' INTERSECT ', $subRequests);
            $request .= ') AS t ON t.resource_id = r.resource_id';
        }

        return $request;
    }

    /**
     * Build the SQL Query.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     * @param bool $hasMetricName
     *
     * @return string
     */
    private function buildQuery(
        RequestParametersInterface $requestParameters,
        array $accessGroups = [],
        bool $hasMetricName = false): string
    {
        $request
            = <<<'SQL'
                    SELECT SQL_CALC_FOUND_ROWS DISTINCT
                    m.metric_id, m.metric_name, m.unit_name, m.warn, m.crit, m.current_value, m.warn_low, m.crit_low, m.min,
                    m.max, r.parent_name, r.name, r.id as service_id, r.parent_id
                    FROM `:dbstg`.`metrics` AS m
                    INNER JOIN `:dbstg`.`index_data` AS id ON id.id = m.index_id
                    INNER JOIN `:dbstg`.`resources` AS r ON r.id = id.service_id
                SQL;

        $accessGroupIds = array_map(
            fn ($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        if ([] !== $accessGroups) {
            $request .= ' INNER JOIN `:dbstg`.`centreon_acl` acl
                ON acl.service_id = r.id
                AND r.type = 0
                AND acl.group_id IN (' . implode(',', $accessGroupIds) . ') ';
        }

        $search = $requestParameters->getSearch();
        if (! empty($search) && array_key_exists('$and', $search)) {
            $this->subRequestsInformation = $this->getSubRequestsInformation($search);
            $request .= $this->buildSubRequestForTags($this->subRequestsInformation);
        }

        $request .= <<<'SQL'
                WHERE r.enabled = 1
            SQL;

        if (! empty($this->subRequestsInformation)) {
            $request .= $this->subRequestsInformation['service']['request'] ?? '';
            $request .= $this->subRequestsInformation['host']['request'] ?? '';
        }

        if ($hasMetricName) {
            $request .= <<<'SQL'
                    AND m.metric_name = :metricName
                SQL;
        }

        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();
        $request .= $sortRequest ?? ' ORDER BY m.metric_id ASC';
        $request .= $this->sqlRequestTranslator->translatePaginationToSql();

        return $request;
    }

    /**
     * Execute the SQL Query.
     *
     * @param \PDOStatement $statement
     * @param string $metricName
     *
     * @throws \Throwable
     *
     * @return \PDOStatement
     */
    private function executeQuery(\PDOStatement $statement, string $metricName = ''): \PDOStatement
    {
        $boundValues = [];
        if (! empty($metricName)) {
            $boundValues[] = [
                ':metricName' => [$metricName => \PDO::PARAM_STR],
            ];
        }
        if (! empty($this->subRequestsInformation)) {
            foreach ($this->subRequestsInformation as $subRequestInformation) {
                $boundValues[] = $subRequestInformation['bindValues'];
            }
        }
        $boundValues = array_merge(...$boundValues);
        foreach ($boundValues as $bindToken => $bindValueInformation){
            foreach ($bindValueInformation as $bindValue => $paramType) {
                $statement->bindValue($bindToken, $bindValue, $paramType);
            }
        }
        $statement->execute();

        return $statement;
    }

    /**
     * Create the ResourceMetrics from result of query.
     *
     * @param RequestParametersInterface $requestParameters
     * @param \PDOStatement $statement
     *
     * @return ResourceMetric[]
     */
    private function buildResourceMetrics(
        RequestParametersInterface $requestParameters,
        \PDOStatement $statement
    ): array {
        $foundRecords = $this->db->query('SELECT FOUND_ROWS()');
        $resourceMetrics = [];
        if ($foundRecords !== false && ($total = $foundRecords->fetchColumn()) !== false) {
            $requestParameters->setTotal((int) $total);
        }

        if (($records = $statement->fetchAll(\PDO::FETCH_ASSOC)) !== false) {
            $metricsInformation = [];
            foreach ($records as $record) {
                if (! array_key_exists($record['service_id'], $metricsInformation)) {
                    $metricsInformation[$record['service_id']] = [
                        'service_id' => $record['service_id'],
                        'name' => $record['name'],
                        'parent_name' => $record['parent_name'],
                        'parent_id' => $record['parent_id'],
                        'metrics' => [],
                    ];
                }
                $metricsInformation[$record['service_id']]['metrics'][] = new PerformanceMetric(
                    $record['metric_id'],
                    $record['metric_name'],
                    $record['unit_name'],
                    $record['warn'],
                    $record['crit'],
                    $record['warn_low'],
                    $record['crit_low'],
                    $record['current_value'],
                    $record['min'],
                    $record['max']
                );
            }
            foreach ($metricsInformation as $information) {
                $resourceMetrics[] = new ResourceMetric(
                    $information['service_id'],
                    $information['name'],
                    $information['parent_name'],
                    $information['parent_id'],
                    $information['metrics']
                );
            }
        }

        return $resourceMetrics;
    }
}
