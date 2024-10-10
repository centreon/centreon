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

namespace Core\Metric\Infrastructure\Repository;

use Centreon\Domain\Monitoring\Host;
use Centreon\Domain\Monitoring\Service;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadMetricRepository extends AbstractRepositoryDRB implements ReadMetricRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     * @param array<
     *  string, array{
     *    request: string,
     *    bindValues: array<mixed>
     *  }
     * > $subRequestsInformation
     */
    public function __construct(DatabaseConnection $db, private array $subRequestsInformation = [])
    {
        $this->db = $db;
    }

    /**
     * @param int $indexId
     *
     * @return array<Metric>
     */
    public function findMetricsByIndexId(int $indexId): array
    {
        $query = 'SELECT DISTINCT metric_id as id, metric_name as name FROM `:dbstg`.metrics, `:dbstg`.index_data ';
        $query .= ' WHERE metrics.index_id = index_data.id AND id = :index_id ORDER BY metric_id';
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':index_id', $indexId, \PDO::PARAM_INT);
        $statement->execute();

        $records = $statement->fetchAll();
        if (! is_array($records) || $records === []) {
            return [];
        }

        $metrics = [];
        foreach ($records as $record) {
            $metrics[] = new Metric((int) $record['id'], $record['name']);
        }

        return $metrics;
    }

    /**
     * @inheritDoc
     */
    public function findServicesByMetricNamesAndRequestParameters(
        array $metricNames,
        RequestParametersInterface $requestParameters
    ): array {
        if ([] === $metricNames) {
            return [];
        }

        $request = $this->buildQueryForFindServices($requestParameters, [], $metricNames);
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement = $this->executeQueryForFindServices($statement, $metricNames);

        $records = $statement->fetchAll();
        $services = [];
        foreach ($records as $record) {
            $services[] = (new Service())
                ->setId($record['service_id'])
                ->setHost(
                    (new Host())
                        ->setId($record['host_id'])
                        ->setName($record['host_name'])
                );
        }

        return $services;
    }

    /**
     * @inheritDoc
     */
    public function findByHostIdAndServiceId(int $hostId, int $serviceId, RequestParametersInterface $requestParameters): array
    {
        $query = $this->buildQueryForFindMetrics($requestParameters);
        $statement = $this->executeQueryForFindMetrics($query, $hostId, $serviceId);
        $records = $statement->fetchAll();

        return $this->createMetricsFromRecords($records);
    }

    /**
     * @inheritDoc
     */
    public function findByHostIdAndServiceIdAndAccessGroups(int $hostId, int $serviceId, array $accessGroups, RequestParametersInterface $requestParameters): array
    {
        $query = $this->buildQueryForFindMetrics($requestParameters, $accessGroups);
        $statement = $this->executeQueryForFindMetrics($query, $hostId, $serviceId);
        $records = $statement->fetchAll();

        return $this->createMetricsFromRecords($records);
    }

    /**
     * @inheritDoc
     */
    public function findServicesByMetricNamesAndAccessGroupsAndRequestParameters(
        array $metricNames,
        array $accessGroups,
        RequestParametersInterface $requestParameters
    ): array {
        if ([] === $metricNames) {
            return [];
        }

        $request = $this->buildQueryForFindServices($requestParameters, $accessGroups, $metricNames);
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement = $this->executeQueryForFindServices($statement, $metricNames);

        $records = $statement->fetchAll();
        $services = [];
        foreach ($records as $record) {
            $services[] = (new Service())
                ->setId($record['service_id'])
                ->setHost(
                    (new Host())
                        ->setId($record['host_id'])
                        ->setName($record['host_name'])
                );
        }

        return $services;
    }

    /**
     * Execute SQL Query to find Metrics.
     *
     * @param string $query
     * @param int $hostId
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return \PDOStatement
     */
    private function executeQueryForFindMetrics(string $query, int $hostId, int $serviceId): \PDOStatement
    {
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':serviceId', $serviceId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement;
    }

    /**
     * Create Metrics Instances from database records.
     *
     * @param array<
     *  array{
     *    id: int,
     *    name: string,
     *    unit_name: string,
     *    current_value: float|null,
     *    warn: float|null,
     *    warn_low: float|null,
     *    crit: float|null,
     *    crit_low: float|null
     *  }
     * > $records
     *
     * @return Metric[]
     */
    private function createMetricsFromRecords(array $records): array
    {
        if ([] === $records) {
            return [];
        }

        $metrics = [];
        foreach ($records as $record) {
            $metrics[] = DbMetricFactory::createFromRecord($record);
        }

        return $metrics;
    }

    /**
     * Build the SQL Query.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     * @param string[] $metricNames
     *
     * @return string
     */
    private function buildQueryForFindServices(
        RequestParametersInterface $requestParameters,
        array $accessGroups,
        array $metricNames
    ): string {
        $request = <<<'SQL'
            SELECT DISTINCT id.`host_id`,
                id.`host_name`,
                id.`service_id`
            FROM `:dbstg`.`index_data` AS id
                INNER JOIN `:dbstg`.`metrics` AS m ON m.`index_id` = id.`id`
                INNER JOIN `:dbstg`.`resources` AS r on r.`parent_id` = id.`host_id`
            SQL;

        $accessGroupIds = \array_map(
            fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        if ([] !== $accessGroupIds) {
            $accessGroupIdsQuery = \implode(',', $accessGroupIds);
            $request .= <<<SQL
                    INNER JOIN `:dbstg`.`centreon_acl` acl ON acl.`service_id` = id.`service_id`
                    AND acl.`group_id` IN ({$accessGroupIdsQuery})
                SQL;
        }

        $search = $requestParameters->getSearch();
        if ($search !== [] && \array_key_exists('$and', $search)) {
            $this->subRequestsInformation = $this->getSubRequestsInformation($search);
            $request .= $this->buildSubRequestForTags($this->subRequestsInformation);
        }

        if ([] !== $this->subRequestsInformation) {
            $request .= $this->subRequestsInformation['service']['request'] ?? '';
            $request .= $this->subRequestsInformation['metaservice']['request'] ?? '';
            $request .= $this->subRequestsInformation['host']['request'] ?? '';
        }

        $bindValues = [];
        foreach ($metricNames as $index => $metricName) {
            $bindValues[':metric_name_' . $index] = $metricName;
        }

        $metricNamesQuery = implode(', ', \array_keys($bindValues));
        $request .= <<<SQL
                WHERE m.metric_name IN ({$metricNamesQuery})
                AND r.enabled = 1
            SQL;

        return $request;
    }

    /**
     * Build Query For Find Metrics.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return string
     */
    private function buildQueryForFindMetrics(RequestParametersInterface $requestParameters, array $accessGroups = []): string
    {
        $query = <<<'SQL'
            SELECT DISTINCT metric_id as id, metric_name as name, unit_name, current_value, warn,
            warn_low, crit, crit_low
            FROM  `:dbstg`.metrics m
                INNER JOIN  `:dbstg`.index_data ON m.index_id =  `:dbstg`.index_data.id
            SQL;

        $accessGroupIds = \array_map(
            fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        if ([] !== $accessGroupIds) {
            $accessGroupIdsQuery = \implode(',', $accessGroupIds);
            $query .= <<<SQL
                    INNER JOIN `:dbstg`.`centreon_acl` acl ON acl.`service_id` = id.`service_id`
                    AND acl.`group_id` IN ({$accessGroupIdsQuery})
                SQL;
        }

        $query .= <<<'SQL'
             WHERE `:dbstg`.index_data.host_id = :hostId
             AND `:dbstg`.index_data.service_id = :serviceId
            SQL;

        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $query .= $sqlTranslator->translatePaginationToSql();

        return $query;
    }

    /**
     * Execute the SQL Query.
     *
     * @param \PDOStatement $statement
     * @param string[] $metricNames
     *
     * @throws \Throwable
     *
     * @return \PDOStatement
     */
    private function executeQueryForFindServices(\PDOStatement $statement, array $metricNames): \PDOStatement
    {
        $bindValues = [];
        foreach ($metricNames as $index => $metricName) {
            $bindValues[':metric_name_' . $index] = $metricName;
        }

        foreach ($bindValues as $bindToken => $bindValue) {
            $statement->bindValue($bindToken, $bindValue, \PDO::PARAM_STR);
        }

        $boundValues = [];
        if ([] !== $this->subRequestsInformation) {
            foreach ($this->subRequestsInformation as $subRequestInformation) {
                $boundValues[] = $subRequestInformation['bindValues'];
            }
            $boundValues = \array_merge(...$boundValues);
        }
        foreach ($boundValues as $bindToken => $bindValueInformation){
            foreach ($bindValueInformation as $bindValue => $paramType) {
                $statement->bindValue($bindToken, $bindValue, $paramType);
            }
        }

        $statement->execute();

        return $statement;
    }

    /**
     * Get request and bind values information for each search filter.
     *
     * @phpstan-param array{
     *      '$and': array<
     *          array{
     *                    'service.name'?: array{'$in': non-empty-array<string>},
     *                    'metaservice.id'?: array{'$in': non-empty-array<int>},
     *                         'host.id'?: array{'$in': non-empty-array<int>},
     *                    'hostgroup.id'?: array{'$in': non-empty-array<int>},
     *                 'servicegroup.id'?: array{'$in': non-empty-array<int>},
     *                 'hostcategory.id'?: array{'$in': non-empty-array<int>},
     *              'servicecategory.id'?: array{'$in': non-empty-array<int>},
     *          }
     *      >
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
                \array_key_exists('service.name', $searchParameter)
                && \array_key_exists('$in', $searchParameter['service.name'])
            ) {
                $subRequestsInformation['service'] = $this->buildSubRequestForServiceFilter(
                    $searchParameter['service.name']['$in']
                );
            }
            if (
                \array_key_exists('metaservice.id', $searchParameter)
                && \array_key_exists('$in', $searchParameter['metaservice.id'])
            ) {
                $subRequestsInformation['metaservice'] = $this->buildSubRequestForMetaserviceFilter(
                    $searchParameter['metaservice.id']['$in']
                );
            }
            if (
                \array_key_exists('host.id', $searchParameter)
                && \array_key_exists('$in', $searchParameter['host.id'])
            ) {
                $subRequestsInformation['host'] = $this->buildSubRequestForHostFilter(
                    $searchParameter['host.id']['$in']
                );
            }
            if (
                \array_key_exists('hostgroup.id', $searchParameter)
                && \array_key_exists('$in', $searchParameter['hostgroup.id'])
            ) {
                $subRequestsInformation['hostgroup'] = $this->buildSubRequestForHostGroupFilter(
                    $searchParameter['hostgroup.id']['$in']
                );
            }
            if (
                \array_key_exists('servicegroup.id', $searchParameter)
                && \array_key_exists('$in', $searchParameter['servicegroup.id'])
            ) {
                $subRequestsInformation['servicegroup'] = $this->buildSubRequestForServiceGroupFilter(
                    $searchParameter['servicegroup.id']['$in']
                );
            }
            if (
                \array_key_exists('hostcategory.id', $searchParameter)
                && \array_key_exists('$in', $searchParameter['hostcategory.id'])
            ) {
                $subRequestsInformation['hostcategory'] = $this->buildSubRequestForHostCategoryFilter(
                    $searchParameter['hostcategory.id']['$in']
                );
            }
            if (
                \array_key_exists('servicecategory.id', $searchParameter)
                && \array_key_exists('$in', $searchParameter['servicecategory.id'])
            ) {
                $subRequestsInformation['servicecategory'] = $this->buildSubRequestForServiceCategoryFilter(
                    $searchParameter['servicecategory.id']['$in']
                );
            }
        }

        return $subRequestsInformation;
    }

    /**
     * Build the sub request for service filter.
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
                    AND id.`service_description` IN ({$bindTokens})
                SQL,
            'bindValues' => $bindServiceNames,
        ];
    }

    /**
     * Build the sub request for metaservice filter.
     *
     * @param non-empty-array<string> $metaserviceIds
     *
     * @return array{
     *  request: string,
     *  bindValues: array<mixed>
     * }
     */
    private function buildSubRequestForMetaserviceFilter(array $metaserviceIds): array
    {
        foreach ($metaserviceIds as $key => $metaserviceId) {
            $bindMetaserviceNames[':metaservice_name' . $key] = ['meta_'. $metaserviceId => \PDO::PARAM_STR];
        }

        $bindTokens = implode(', ', array_keys($bindMetaserviceNames));

        return [
            'request' => <<<SQL
                    AND id.`service_description` IN ({$bindTokens})
                SQL,
            'bindValues' => $bindMetaserviceNames,
        ];
    }

    /**
     * Build the sub request for host filter.
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
                    AND id.`host_id` IN ({$bindTokens})
                SQL,
            'bindValues' => $bindHostIds,
        ];
    }

    /**
     * Build the sub request for host group filter.
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
                    SELECT r.`resource_id`,
                        r.`parent_id`
                    FROM `:dbstg`.`resources` r
                        LEFT JOIN `:dbstg`.`resources` pr ON pr.`id` = r.`parent_id`
                        LEFT JOIN `:dbstg`.`resources_tags` rtags ON rtags.`resource_id` = pr.`resource_id`
                        INNER JOIN `:dbstg`.tags ON tags.tag_id = rtags.tag_id
                    WHERE tags.id IN ({$boundTokens})
                        AND tags.type = 1
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * Build the sub request for service group filter.
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
                    SELECT rtags.`resource_id`,
                        r.`parent_id`
                    FROM `:dbstg`.`resources_tags` rtags
                        INNER JOIN `:dbstg`.`tags` ON tags.`tag_id` = rtags.`tag_id`
                        INNER JOIN `:dbstg`.`resources` r ON r.`resource_id` = rtags.`resource_id`
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 0
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * Build the sub request for host category filter.
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
                    SELECT r.`resource_id`,
                        r.`parent_id`
                    FROM `:dbstg`.`resources` r
                        LEFT JOIN `:dbstg`.`resources` pr ON pr.`id` = r.`parent_id`
                        LEFT JOIN `:dbstg`.`resources_tags` rtags ON rtags.`resource_id` = pr.`resource_id`
                        INNER JOIN `:dbstg`.`tags` ON tags.`tag_id` = rtags.`tag_id`
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 3
                SQL,
            'bindValues' => $bindValues,
        ];
    }

    /**
     * Build the sub request for service category filter.
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
                    SELECT rtags.`resource_id`,
                        r.`parent_id`
                    FROM `:dbstg`.resources_tags AS rtags
                        INNER JOIN `:dbstg`.tags ON tags.tag_id = rtags.tag_id
                        INNER JOIN `:dbstg`.`resources` r ON r.`resource_id` = rtags.`resource_id`
                    WHERE tags.id IN ({$boundTokens})
                    AND tags.type = 2
                SQL,
            'bindValues' => $bindValues,
        ];
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
        $subRequestForTags = \array_reduce(\array_keys($subRequestInformation), function ($acc, $item) use (
            $subRequestInformation
        ) {
            if ($item !== 'host' && $item !== 'service' && $item !== 'metric' && $item !== 'metaservice') {
                $acc[] = $subRequestInformation[$item];
            }

            return $acc;
        }, []);

        if (! empty($subRequestForTags)) {
            $subRequests = array_map(fn ($subRequestForTag) => $subRequestForTag['request'], $subRequestForTags);
            $request .= ' INNER JOIN (';
            $request .= implode(' INTERSECT ', $subRequests);
            $request .= ') AS t ON t.`parent_id` = id.`host_id`';
        }

        return $request;
    }
}
