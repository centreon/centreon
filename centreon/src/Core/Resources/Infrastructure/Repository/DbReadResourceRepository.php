<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;
use Core\Domain\RealTime\ResourceTypeInterface;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Infrastructure\Repository\ExtraDataProviders\ExtraDataProviderInterface;
use Core\Resources\Infrastructure\Repository\ResourceACLProviders\ResourceACLProviderInterface;
use Core\Severity\RealTime\Domain\Model\Severity;

class DbReadResourceRepository extends DatabaseRepository implements ReadResourceRepositoryInterface
{
    use LoggerTrait;
    private const RESOURCE_TYPE_HOST = 1;

    /** @var ResourceEntity[] */
    private array $resources = [];

    /** @var ResourceTypeInterface[] */
    private array $resourceTypes;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    /** @var ExtraDataProviderInterface[] */
    private array $extraDataProviders;

    /** @var array<string, string> */
    private array $resourceConcordances = [
        'id' => 'resources.id',
        'name' => 'resources.name',
        'alias' => 'resources.alias',
        'fqdn' => 'resources.address',
        'type' => 'resources.type',
        'h.name' => 'CASE WHEN resources.type = 1 THEN resources.name ELSE resources.parent_name END',
        'h.alias' => 'CASE WHEN resources.type = 1 THEN resources.alias ELSE parent_resource.alias END',
        'h.address' => 'parent_resource.address',
        's.description' => 'resources.type IN (0,2,4) AND resources.name',
        'status_code' => 'resources.status',
        'status_severity_code' => 'resources.status_ordered',
        'action_url' => 'resources.action_url',
        'parent_id' => 'resources.parent_id',
        'parent_name' => 'resources.parent_name',
        'parent_alias' => 'parent_resource.alias',
        'parent_status' => 'parent_resource.status',
        'severity_level' => 'severity_level',
        'in_downtime' => 'resources.in_downtime',
        'acknowledged' => 'resources.acknowledged',
        'last_status_change' => 'resources.last_status_change',
        'tries' => 'resources.check_attempts',
        'last_check' => 'resources.last_check',
        'monitoring_server_name' => 'monitoring_server_name',
        'information' => 'resources.output',
    ];

    /**
     * DbReadResourceRepository constructor
     *
     * @param DatabaseConnection $db
     * @param QueryBuilderInterface $queryBuilder
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     * @param \Traversable<ResourceTypeInterface> $resourceTypes
     * @param \Traversable<ResourceACLProviderInterface> $resourceACLProviders
     * @param \Traversable<ExtraDataProviderInterface> $extraDataProviders
     */
    public function __construct(
        ConnectionInterface $db,
        QueryBuilderInterface $queryBuilder,
        SqlRequestParametersTranslator $sqlRequestTranslator,
        \Traversable $resourceTypes,
        private readonly \Traversable $resourceACLProviders,
        \Traversable $extraDataProviders
    ) {
        parent::__construct($db, $queryBuilder);
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT)
            ->setConcordanceErrorMode(RequestParameters::CONCORDANCE_ERRMODE_SILENT);

        if ($resourceTypes instanceof \Countable && count($resourceTypes) === 0) {
            throw new \InvalidArgumentException(
                _('You must add at least one resource provider')
            );
        }

        $this->resourceTypes = iterator_to_array($resourceTypes);
        $this->extraDataProviders = iterator_to_array($extraDataProviders);
    }

    public function findParentResourcesById(ResourceFilter $filter): array
    {
        $this->resources = [];
        $queryParametersFromRequestParameters = new QueryParameters();
        $this->sqlRequestTranslator->setConcordanceArray($this->resourceConcordances);

        $resourceTypeHost = self::RESOURCE_TYPE_HOST;

        $query = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS DISTINCT
                1 AS REALTIME,
                resources.resource_id,
                resources.name,
                resources.alias,
                resources.address,
                resources.id,
                resources.internal_id,
                resources.parent_id,
                resources.parent_name,
                parent_resource.status AS `parent_status`,
                parent_resource.alias AS `parent_alias`,
                parent_resource.status_ordered AS `parent_status_ordered`,
                parent_resource.address AS `parent_fqdn`,
                severities.id AS `severity_id`,
                severities.level AS `severity_level`,
                severities.name AS `severity_name`,
                severities.type AS `severity_type`,
                severities.icon_id AS `severity_icon_id`,
                resources.type,
                resources.status,
                resources.status_ordered,
                resources.status_confirmed,
                resources.in_downtime,
                resources.acknowledged,
                resources.flapping,
                resources.percent_state_change,
                resources.passive_checks_enabled,
                resources.active_checks_enabled,
                resources.notifications_enabled,
                resources.last_check,
                resources.last_status_change,
                resources.check_attempts,
                resources.max_check_attempts,
                resources.notes,
                resources.notes_url,
                resources.action_url,
                resources.output,
                resources.poller_id,
                resources.has_graph,
                instances.name AS `monitoring_server_name`,
                resources.enabled,
                resources.icon_id,
                resources.severity_id
            FROM `:dbstg`.`resources`
            LEFT JOIN `:dbstg`.`resources` parent_resource
                ON parent_resource.id = resources.parent_id
                AND parent_resource.type = {$resourceTypeHost}
            LEFT JOIN `:dbstg`.`severities`
                ON `severities`.severity_id = `resources`.severity_id
            LEFT JOIN `:dbstg`.`resources_tags` AS rtags
                ON `rtags`.resource_id = `resources`.resource_id
            INNER JOIN `:dbstg`.`instances`
                ON `instances`.instance_id = `resources`.poller_id
            WHERE resources.name NOT LIKE '\_Module\_%'
                AND resources.parent_name NOT LIKE '\_Module\_BAM%'
                AND resources.enabled = 1
                AND resources.type != 3

            SQL;

        try {
            $query .= $this->addResourceParentIdSubRequest($filter, $queryParametersFromRequestParameters);
        } catch (ValueObjectException|CollectionException $exception) {
            throw new RepositoryException(
                message: 'An error occurred while adding the parent id subrequest',
                previous: $exception
            );
        }

        /**
         * Resource Type filter
         * 'service', 'metaservice', 'host'.
         */
        $query .= $this->addResourceTypeSubRequest($filter);

        foreach ($this->extraDataProviders as $provider) {
            if ($provider->supportsExtraData($filter)) {
                $query .= $provider->getSubFilter($filter);
            }
        }

        /**
         * Handle sort parameters.
         */
        $query .= $this->sqlRequestTranslator->translateSortParameterToSql()
            ?: ' ORDER BY resources.status_ordered DESC, resources.name ASC';

        /**
         * Handle pagination.
         */
        $query .= $this->sqlRequestTranslator->translatePaginationToSql();

        try {
            $queryResources = $this->translateDbName($query);
            $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );
            $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameters);
            foreach ($this->connection->iterateAssociative($queryResources, $queryParameters) as $resourceRecord) {
                /** @var array<string,int|string|null> $resourceRecord */
                $this->resources[] = DbResourceFactory::createFromRecord($resourceRecord, $this->resourceTypes);
            }

            // get total without pagination
            $queryTotal = $this->translateDbName('SELECT FOUND_ROWS() AS REALTIME from `:dbstg`.`resources`');
            if (($total = $this->connection->fetchOne($queryTotal)) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }
        } catch (AssertionFailedException|TransformerException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: 'An error occurred while finding parent resources by id',
                context: ['filter' => $filter],
                previous: $exception
            );
        }

        $iconIds = $this->getIconIdsFromResources();
        $icons = $this->getIconsDataForResources($iconIds);
        $this->completeResourcesWithIcons($icons);

        return $this->resources;
    }

    /**
     * @param ResourceFilter $filter
     *
     * @throws RepositoryException
     * @return ResourceEntity[]
     */
    public function findResources(ResourceFilter $filter): array
    {
        try {
            $this->resources = [];
            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesRequest($filter, $queryParametersFromRequestParameter);
            $this->find($query, $queryParametersFromRequestParameter);

            return $this->resources;
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occurred while finding resources',
                context: ['filter' => $filter],
                previous: $exception
            );
        }
    }

    /**
     * @param ResourceFilter $filter
     * @param array<int> $accessGroupIds
     *
     * @throws RepositoryException
     * @return ResourceEntity[]
     */
    public function findResourcesByAccessGroupIds(ResourceFilter $filter, array $accessGroupIds): array
    {
        try {
            $this->resources = [];
            $accessGroupRequest = $this->addResourceAclSubRequest($accessGroupIds);
            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesRequest(
                $filter,
                $queryParametersFromRequestParameter,
                $accessGroupRequest
            );
            $this->find($query, $queryParametersFromRequestParameter);

            return $this->resources;
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occurred while finding resources by access group ids',
                context: ['filter' => $filter, 'accessGroupIds' => $accessGroupIds],
                previous: $exception
            );
        }
    }

    /**
     * @param ResourceFilter $filter
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    public function iterateResources(ResourceFilter $filter, int $maxResults = 0): \Traversable
    {
        try {
            $this->resources = [];

            // if $maxResults is set to 0, we use pagination and limit
            if ($maxResults > 0) {
                // for an export, we can have no pagination, so we limit the number of results in this case
                // page is always 1 and limit is the maxResults
                $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
                $this->sqlRequestTranslator->getRequestParameters()->setLimit($maxResults);
            }

            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesRequest($filter, $queryParametersFromRequestParameter);

            return $this->iterate($query, $queryParametersFromRequestParameter);
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occurred while iterating resources by max results',
                context: ['filter' => $filter, 'maxResults' => $maxResults],
                previous: $exception
            );
        }
    }

    /**
     * @param ResourceFilter $filter
     * @param array<int> $accessGroupIds
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    public function iterateResourcesByAccessGroupIds(
        ResourceFilter $filter,
        array $accessGroupIds,
        int $maxResults = 0
    ): \Traversable {
        try {
            $this->resources = [];

            // if $maxResults is set to 0, we use pagination and limit
            if ($maxResults > 0) {
                // for an export, we can have no pagination, so we limit the number of results in this case
                // page is always 1 and limit is the maxResults
                $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
                $this->sqlRequestTranslator->getRequestParameters()->setLimit($maxResults);
            }

            $accessGroupRequest = $this->addResourceAclSubRequest($accessGroupIds);

            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesRequest(
                $filter,
                $queryParametersFromRequestParameter,
                $accessGroupRequest
            );

            return $this->iterate($query, $queryParametersFromRequestParameter);
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occurred while iterating resources by access group ids and max results',
                context: ['filter' => $filter, 'accessGroupIds' => $accessGroupIds, 'maxResults' => $maxResults],
                previous: $exception
            );
        }
    }

    /**
     * @param ResourceFilter|null $filter
     *
     * @throws RepositoryException
     * @return int
     */
    public function countResources(?ResourceFilter $filter = null): int
    {
        try {
            // For a count, there isn't pagination we limit the number of results
            // page is always 1 and limit is the maxResults in case of an export
            $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
            $this->sqlRequestTranslator->getRequestParameters()->setLimit(0);

            // If no filter is provided, we create an empty one to avoid errors and to have total resources
            // The same is done with the search values of sqlRequestTranslator and search of request parameters
            if ($filter === null) {
                $this->sqlRequestTranslator->setSearchValues([]);
                $this->sqlRequestTranslator->getRequestParameters()->setSearch('');
            }

            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesRequest(
                filter: $filter ?? new ResourceFilter(),
                queryParametersFromRequestParameter: $queryParametersFromRequestParameter,
                onlyCount: true
            );

            return $this->count($query, $queryParametersFromRequestParameter, ! is_null($filter));
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occurred while counting resources by max results',
                context: ['filter' => $filter],
                previous: $exception
            );
        }
    }

    /**
     * @param array<int> $accessGroupIds
     *
     * @param ResourceFilter|null $filter
     *
     * @throws RepositoryException
     * @return int
     */
    public function countResourcesByAccessGroupIds(array $accessGroupIds, ?ResourceFilter $filter = null): int
    {
        try {
            // For a count, there isn't pagination we limit the number of results
            // page is always 1 and limit is the maxResults in case of an export
            $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
            $this->sqlRequestTranslator->getRequestParameters()->setLimit(0);

            // If no filter is provided, we create an empty one to avoid errors and to have total resources
            // The same is done with the search values of sqlRequestTranslator and search of request parameters
            if ($filter === null) {
                $this->sqlRequestTranslator->setSearchValues([]);
                $this->sqlRequestTranslator->getRequestParameters()->setSearch('');
            }

            $accessGroupRequest = $this->addResourceAclSubRequest($accessGroupIds);

            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesRequest(
                filter: $filter ?? new ResourceFilter(),
                queryParametersFromRequestParameter: $queryParametersFromRequestParameter,
                accessGroupRequest: $accessGroupRequest,
                onlyCount: true
            );

            return $this->count($query, $queryParametersFromRequestParameter, ! is_null($filter));
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'An error occurred while counting resources by access group ids and max results',
                context: ['filter' => $filter, 'accessGroupIds' => $accessGroupIds],
                previous: $exception
            );
        }
    }

    // ------------------------------------- PRIVATE METHODS -------------------------------------

    /**
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     * @param string $accessGroupRequest
     * @param bool $onlyCount
     *
     * @throws CollectionException
     * @throws RepositoryException
     * @throws ValueObjectException
     * @return string
     */
    private function generateFindResourcesRequest(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter,
        string $accessGroupRequest = '',
        bool $onlyCount = false
    ): string {
        $this->sqlRequestTranslator->setConcordanceArray($this->resourceConcordances);

        $query = $this->createQueryHeaders($filter, $queryParametersFromRequestParameter);

        $resourceType = self::RESOURCE_TYPE_HOST;

        $joinCtes = $query === ''
            ? ''
            : ' INNER JOIN cte ON cte.resource_id = resources.resource_id ';

        if ($onlyCount) {
            $query = <<<'SQL'
                SELECT COUNT(DISTINCT resources.resource_id), 1 AS REALTIME
                SQL;
        } else {
            $query .= <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS DISTINCT
                    1 AS REALTIME,
                    resources.resource_id,
                    resources.name,
                    resources.alias,
                    resources.address,
                    resources.id,
                    resources.internal_id,
                    resources.parent_id,
                    resources.parent_name,
                    parent_resource.resource_id AS `parent_resource_id`,
                    parent_resource.status AS `parent_status`,
                    parent_resource.alias AS `parent_alias`,
                    parent_resource.status_ordered AS `parent_status_ordered`,
                    parent_resource.address AS `parent_fqdn`,
                    severities.id AS `severity_id`,
                    severities.level AS `severity_level`,
                    severities.name AS `severity_name`,
                    severities.type AS `severity_type`,
                    severities.icon_id AS `severity_icon_id`,
                    resources.type,
                    resources.status,
                    resources.status_ordered,
                    resources.status_confirmed,
                    resources.in_downtime,
                    resources.acknowledged,
                    resources.passive_checks_enabled,
                    resources.active_checks_enabled,
                    resources.notifications_enabled,
                    resources.last_check,
                    resources.last_status_change,
                    resources.check_attempts,
                    resources.max_check_attempts,
                    resources.notes,
                    resources.notes_url,
                    resources.action_url,
                    resources.output,
                    resources.poller_id,
                    resources.has_graph,
                    instances.name AS `monitoring_server_name`,
                    resources.enabled,
                    resources.icon_id,
                    resources.severity_id,
                    resources.flapping,
                    resources.percent_state_change
                SQL;
        }

        $query .= ' ';
        $query .= <<<SQL
            FROM `:dbstg`.`resources`
            INNER JOIN `:dbstg`.`instances`
                ON `instances`.instance_id = `resources`.poller_id
            {$joinCtes}
            LEFT JOIN `:dbstg`.`resources` parent_resource
                ON parent_resource.id = resources.parent_id
                AND parent_resource.type = {$resourceType}
            LEFT JOIN `:dbstg`.`severities`
                ON `severities`.severity_id = `resources`.severity_id
            LEFT JOIN `:dbstg`.`resources_tags` AS rtags
                ON `rtags`.resource_id = `resources`.resource_id
            SQL;

        /**
         * Handle search values.
         */
        $searchSubRequest = null;

        try {
            $searchSubRequest .= $this->sqlRequestTranslator->translateSearchParameterToSql();
        } catch (RequestParametersTranslatorException $exception) {
            throw new RepositoryException(
                message: 'An error occurred while generating the request',
                previous: $exception
            );
        }

        $query .= ! empty($searchSubRequest) ? $searchSubRequest . ' AND ' : ' WHERE ';

        $query .= <<<SQL
            resources.name NOT LIKE '\_Module\_%'
                AND resources.parent_name NOT LIKE '\_Module\_BAM%'
                AND resources.enabled = 1
                AND resources.type != 3
            SQL;

        // Apply only_with_performance_data
        if ($filter->getOnlyWithPerformanceData() === true) {
            $query .= ' AND resources.has_graph = 1';
        }

        foreach ($this->extraDataProviders as $provider) {
            $query .= $provider->getSubFilter($filter);
        }

        $query .= $accessGroupRequest;

        $query .= $this->addResourceParentIdSubRequest($filter, $queryParametersFromRequestParameter);

        /**
         * Resource Type filter
         * 'service', 'metaservice', 'host'.
         */
        $query .= $this->addResourceTypeSubRequest($filter);

        /**
         * State filter
         * 'unhandled_problems', 'resource_problems', 'acknowledged', 'in_downtime'.
         */
        $query .= $this->addResourceStateSubRequest($filter);

        /**
         * Status filter
         * 'OK', 'WARNING', 'CRITICAL', 'UNKNOWN', 'UP', 'UNREACHABLE', 'DOWN', 'PENDING'.
         */
        $query .= $this->addResourceStatusSubRequest($filter);

        /**
         * Status type filter
         * 'HARD', 'SOFT'.
         */
        $query .= $this->addStatusTypeSubRequest($filter);

        /**
         * Monitoring Server filter.
         */
        $query .= $this->addMonitoringServerSubRequest($filter, $queryParametersFromRequestParameter);

        /**
         * Severity filter (levels and/or names).
         */
        $query .= $this->addSeveritySubRequest($filter, $queryParametersFromRequestParameter);

        if (! $onlyCount) {
            /**
             * Handle sort parameters.
             */
            $query .= $this->sqlRequestTranslator->translateSortParameterToSql()
                ?: ' ORDER BY resources.status_ordered DESC, resources.name ASC';

            /**
             * Handle pagination.
             */
            $query .= $this->sqlRequestTranslator->translatePaginationToSql();
        }

        return $query;
    }

    /**
     * @param int[] $accessGroupIds
     *
     * @throws \InvalidArgumentException
     */
    private function addResourceAclSubRequest(array $accessGroupIds): string
    {
        $orConditions = array_map(
            static fn(ResourceACLProviderInterface $provider): string => $provider->buildACLSubRequest($accessGroupIds),
            iterator_to_array($this->resourceACLProviders)
        );

        if ($orConditions === []) {
            throw new \InvalidArgumentException(_('You must provide at least one ACL provider'));
        }

        return sprintf(' AND (%s)', implode(' OR ', $orConditions));
    }

    /**
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     *
     * @throws CollectionException
     * @throws ValueObjectException
     * @return string
     */
    private function createQueryHeaders(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter
    ): string {
        $headers = '';
        $nextHeaders = function () use (&$headers): void {
            $headers .= $headers !== '' ? ",\n" : 'WITH ';
        };
        $cteToIntersect = [];

        // Create CTE for each tag type
        if ($filter->getHostgroupNames() !== []) {
            $cteToIntersect[] = 'host_groups';

            $hostGroupKeys = [];
            foreach ($filter->getHostgroupNames() as $index => $hostGroupName) {
                $key = ":host_group_{$index}";
                $queryParametersFromRequestParameter->add($key, QueryParameter::string($key, $hostGroupName));
                $hostGroupKeys[] = $key;
            }
            $hostGroupPrepareKeys = implode(', ', $hostGroupKeys);
            $headers = <<<SQL
                WITH host_groups AS (
                    SELECT resources.resource_id
                    FROM `:dbstg`.`resources` AS resources
                    INNER JOIN `:dbstg`.`resources_tags` AS rtags
                        ON rtags.resource_id = resources.resource_id
                    INNER JOIN `:dbstg`.`tags` AS tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.type = 1
                        AND resources.enabled = 1
                        AND tags.name IN ({$hostGroupPrepareKeys})
                    GROUP BY resources.resource_id
                    UNION
                    SELECT resources.resource_id
                    FROM `:dbstg`.`resources` AS resources
                    INNER JOIN `:dbstg`.`resources` AS parent_resource
                        ON parent_resource.id = resources.parent_id
                    INNER JOIN `:dbstg`.`resources_tags` AS rtags
                        ON rtags.resource_id = parent_resource.resource_id
                    INNER JOIN `:dbstg`.`tags` AS tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.type = 1
                        AND tags.name IN ({$hostGroupPrepareKeys})
                        AND resources.enabled = 1
                        AND parent_resource.enabled = 1
                        AND parent_resource.type = 1
                    GROUP BY resources.resource_id
                )
                SQL;
        }
        if ($filter->getHostCategoryNames() !== []) {
            $cteToIntersect[] = 'host_categories';

            $hostCategoriesKeys = [];
            foreach ($filter->getHostCategoryNames() as $index => $hostCategoryName) {
                $key = ":host_category_{$index}";
                $queryParametersFromRequestParameter->add($key, QueryParameter::string($key, $hostCategoryName));
                $hostCategoriesKeys[] = $key;
            }
            $hostCategoryPrepareKeys = implode(', ', $hostCategoriesKeys);

            $nextHeaders();
            $headers .= <<<SQL
                host_categories AS (
                    SELECT resources.resource_id
                    FROM `:dbstg`.`resources` AS resources
                    INNER JOIN `:dbstg`.`resources_tags` AS rtags
                        ON rtags.resource_id = resources.resource_id
                    INNER JOIN `:dbstg`.`tags` AS tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.type = 3
                        AND resources.enabled = 1
                        AND tags.name IN ({$hostCategoryPrepareKeys})
                    GROUP BY resources.resource_id
                    UNION
                    SELECT resources.resource_id
                    FROM `:dbstg`.`resources` AS resources
                    INNER JOIN `:dbstg`.`resources` AS parent_resource
                        ON parent_resource.id = resources.parent_id
                    INNER JOIN `:dbstg`.`resources_tags` AS rtags
                        ON rtags.resource_id = parent_resource.resource_id
                    INNER JOIN `:dbstg`.`tags` AS tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.type = 3
                        AND tags.name IN ({$hostCategoryPrepareKeys})
                        AND resources.enabled = 1
                        AND parent_resource.enabled = 1
                        AND parent_resource.type = 1
                    GROUP BY resources.resource_id
                )
                SQL;
        }
        if ($filter->getServicegroupNames() !== []) {
            $cteToIntersect[] = 'service_groups';

            $serviceGroupKeys = [];
            foreach ($filter->getServicegroupNames() as $index => $serviceGroupName) {
                $key = ":service_group_{$index}";
                $queryParametersFromRequestParameter->add($key, QueryParameter::string($key, $serviceGroupName));
                $serviceGroupKeys[] = $key;
            }
            $serviceGroupPrepareKeys = implode(', ', $serviceGroupKeys);
            $nextHeaders();
            $headers .= <<<SQL
                service_groups AS (
                    SELECT rtags.resource_id
                    FROM `:dbstg`.resources_tags AS rtags
                    INNER JOIN `:dbstg`.tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.name IN ({$serviceGroupPrepareKeys})
                        AND tags.type = 0
                )
                SQL;
        }
        if ($filter->getServiceCategoryNames() !== []) {
            $cteToIntersect[] = 'service_categories';

            $serviceCategoryKeys = [];
            foreach ($filter->getServiceCategoryNames() as $index => $serviceCategoryName) {
                $key = ":service_category_{$index}";
                $queryParametersFromRequestParameter->add($key, QueryParameter::string($key, $serviceCategoryName));
                $serviceCategoryKeys[] = $key;
            }
            $serviceCategoryPrepareKeys = implode(', ', $serviceCategoryKeys);
            $nextHeaders();
            $headers .= <<<SQL
                service_categories AS (
                    SELECT rtags.resource_id
                    FROM `:dbstg`.resources_tags AS rtags
                    INNER JOIN `:dbstg`.tags
                        ON tags.tag_id = rtags.tag_id
                    WHERE tags.name IN ({$serviceCategoryPrepareKeys})
                        AND tags.type = 2
                )
                SQL;
        }

        // Regroup all CTEs
        if ($cteToIntersect !== []) {
            $headers .= ",\ncte AS (\n";
            foreach ($cteToIntersect as $index => $cte) {
                $headers .= $index === 0 ? '' : "\n\tINTERSECT\n";
                $headers .= "\tSELECT * FROM {$cte}";
            }
            $headers .= "\n)";
        }

        return $headers;
    }

    /**
     * @param string $query
     * @param QueryParameters $queryParametersFromRequestParameters
     *
     * @throws AssertionFailedException
     * @throws CollectionException
     * @throws ConnectionException
     * @throws RepositoryException
     * @throws TransformerException
     */
    private function find(string $query, QueryParameters $queryParametersFromRequestParameters): void
    {
        $queryResources = $this->translateDbName($query);
        $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
            $this->sqlRequestTranslator->getSearchValues()
        );
        $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameters);

        foreach ($this->connection->iterateAssociative($queryResources, $queryParameters) as $resourceRecord) {
            /** @var array<string,int|string|null> $resourceRecord */
            $this->resources[] = DbResourceFactory::createFromRecord($resourceRecord, $this->resourceTypes);
        }

        // get total without pagination
        $queryTotal = $this->translateDbName('SELECT FOUND_ROWS() AS REALTIME from `:dbstg`.`resources`');
        if (($total = $this->connection->fetchOne($queryTotal)) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $iconIds = $this->getIconIdsFromResources();
        $icons = $this->getIconsDataForResources($iconIds);
        $this->completeResourcesWithIcons($icons);
    }

    /**
     * @param string $query
     * @param QueryParameters $queryParametersFromRequestParameters
     * @param bool $withFilter
     *
     * @throws CollectionException
     * @throws ConnectionException
     * @throws TransformerException
     * @return int
     */
    private function count(
        string $query,
        QueryParameters $queryParametersFromRequestParameters,
        bool $withFilter = true
    ): int {
        $queryResources = $this->translateDbName($query);

        if ($withFilter) {
            $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );
            $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameters);
        } else {
            $queryParameters = $queryParametersFromRequestParameters;
        }

        return $this->connection->fetchOne($queryResources, $queryParameters);
    }

    /**
     * @param string $query
     * @param QueryParameters $queryParametersFromRequestParameters
     *
     * @throws AssertionFailedException
     * @throws CollectionException
     * @throws ConnectionException
     * @throws RepositoryException
     * @throws TransformerException
     * @return \Traversable<ResourceEntity>
     */
    private function iterate(
        string $query,
        QueryParameters $queryParametersFromRequestParameters
    ): \Traversable {
        $queryResources = $this->translateDbName($query);
        $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
            $this->sqlRequestTranslator->getSearchValues()
        );
        $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameters);
        foreach ($this->connection->iterateAssociative($queryResources, $queryParameters) as $resource) {
            $this->resources = [DbResourceFactory::createFromRecord($resource, $this->resourceTypes)];
            $iconIds = $this->getIconIdsFromResources();
            $icons = $this->getIconsDataForResources($iconIds);
            $this->completeResourcesWithIcons($icons);

            yield $this->resources[0];
        }
    }

    /**
     * @param array<int, array<string, string>> $icons
     */
    private function completeResourcesWithIcons(array $icons): void
    {
        foreach ($this->resources as $resource) {
            if ($resource->getIcon() !== null) {
                $resourceIconId = $resource->getIcon()->getId();
                $resource->getIcon()
                    ->setName($icons[$resourceIconId]['name'])
                    ->setUrl($icons[$resourceIconId]['url']);
            }

            if ($resource->getSeverity() !== null) {
                $resourceSeverityIconId = $resource->getSeverity()->getIcon()->getId();
                $resource->getSeverity()->getIcon()
                    ->setName($icons[$resourceSeverityIconId]['name'])
                    ->setUrl($icons[$resourceSeverityIconId]['url']);
            }
        }
    }

    /**
     * @return array<int, int|null>
     */
    private function getIconIdsFromResources(): array
    {
        $resourceIconIds = $this->getResourceIconIdsFromResources();
        $severityIconIds = $this->getSeverityIconIdsFromResources();

        return array_unique(array_merge($resourceIconIds, $severityIconIds));
    }

    /**
     * @return array<int, int|null>
     */
    private function getResourceIconIdsFromResources(): array
    {
        $resourcesWithIcons = array_filter(
            $this->resources,
            static fn(ResourceEntity $resource): bool => null !== $resource->getIcon()
        );

        return array_map(
            static fn(ResourceEntity $resource): ?int => $resource->getIcon()?->getId(),
            $resourcesWithIcons
        );
    }

    /**
     * @return array<int, int|null>
     */
    private function getSeverityIconIdsFromResources(): array
    {
        $resourcesWithSeverities = array_filter(
            $this->resources,
            static fn(ResourceEntity $resource): bool => null !== $resource->getSeverity()
        );

        return array_map(
            static fn(ResourceEntity $resource): ?int => $resource->getSeverity()?->getIcon()?->getId(),
            $resourcesWithSeverities
        );
    }

    /**
     * @param ResourceFilter $filter
     *
     * @return int[]
     */
    private function getSeverityLevelsFromFilter(ResourceFilter $filter): array
    {
        $levels = [];
        if (! empty($filter->getHostSeverityLevels())) {
            foreach ($filter->getHostSeverityLevels() as $level) {
                $levels[] = $level;
            }
        }

        if (! empty($filter->getServiceSeverityLevels())) {
            foreach ($filter->getServiceSeverityLevels() as $level) {
                $levels[] = $level;
            }
        }

        return array_unique($levels);
    }

    /**
     * @param ResourceFilter $filter
     *
     * @return int[]
     */
    private function getSeverityTypesFromFilter(ResourceFilter $filter): array
    {
        $types = [];
        if (
            ! empty($filter->getHostSeverityLevels())
            || ! empty($filter->getHostSeverityNames())
        ) {
            $types[] = Severity::HOST_SEVERITY_TYPE_ID;
        }

        if (
            ! empty($filter->getServiceSeverityLevels())
            || ! empty($filter->getServiceSeverityNames())
        ) {
            $types[] = Severity::SERVICE_SEVERITY_TYPE_ID;
        }

        return $types;
    }

    /**
     * @param ResourceFilter $filter
     *
     * @return string[]
     */
    private function getSeverityNamesFromFilter(ResourceFilter $filter): array
    {
        $names = [];
        if (! empty($filter->getHostSeverityNames())) {
            foreach ($filter->getHostSeverityNames() as $hostSeverityName) {
                $names[] = $hostSeverityName;
            }
        }

        if (! empty($filter->getServiceSeverityNames())) {
            foreach ($filter->getServiceSeverityNames() as $serviceSeverityName) {
                $names[] = $serviceSeverityName;
            }
        }

        return array_unique($names);
    }

    /**
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     *
     * @throws ValueObjectException
     * @throws CollectionException
     * @return string
     */
    private function addSeveritySubRequest(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter
    ): string {
        $subRequest = '';
        $filteredNames = [];
        $filteredTypes = [];
        $filteredLevels = [];

        $names = $this->getSeverityNamesFromFilter($filter);
        $levels = $this->getSeverityLevelsFromFilter($filter);
        $types = $this->getSeverityTypesFromFilter($filter);

        foreach ($names as $index => $name) {
            $key = ":severityName_{$index}";
            $filteredNames[] = $key;
            $queryParametersFromRequestParameter->add($key, QueryParameter::string($key, $name));
        }

        foreach ($levels as $index => $level) {
            $key = ":severityLevel_{$index}";
            $filteredLevels[] = $key;
            $queryParametersFromRequestParameter->add($key, QueryParameter::int($key, $level));
        }

        foreach ($types as $index => $type) {
            $key = ":severityType_{$index}";
            $filteredTypes[] = $key;
            $queryParametersFromRequestParameter->add($key, QueryParameter::int($key, $type));
        }

        if (
            $filteredNames !== []
            || $filteredLevels !== []
        ) {
            $subRequest = ' AND EXISTS (
                SELECT 1 FROM `:dbstg`.severities
                WHERE severities.severity_id = resources.severity_id
                    AND severities.type IN (' . implode(', ', $filteredTypes) . ')';

            $subRequest .= $filteredNames !== []
                ? ' AND severities.name IN (' . implode(', ', $filteredNames) . ')'
                : '';

            $subRequest .= $filteredLevels !== []
                ? ' AND severities.level IN (' . implode(', ', $filteredLevels) . ')'
                : '';

            $subRequest .= ' LIMIT 1)';
        }

        return $subRequest;
    }

    /**
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     *
     * @throws CollectionException
     * @throws ValueObjectException
     * @return string
     */
    private function addResourceParentIdSubRequest(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter
    ): string {
        $subRequest = '';
        $filteredParentIds = [];

        if (empty($filter->getHostIds())) {
            return $subRequest;
        }

        foreach ($filter->getHostIds() as $index => $hostId) {
            $key = ":parentId_{$index}";
            $filteredParentIds[] = $key;
            $queryParametersFromRequestParameter->add($key, QueryParameter::int($key, $hostId));
        }

        $subRequestFilterParentIds = implode(', ', $filteredParentIds);

        return <<<SQL
            AND (
                resources.parent_id IN ({$subRequestFilterParentIds})
                OR resources.id IN ({$subRequestFilterParentIds})
            )
            SQL;
    }

    /**
     * This adds the sub request filter on resource types.
     *
     * @param ResourceFilter $filter
     *
     * @return string
     */
    private function addResourceTypeSubRequest(ResourceFilter $filter): string
    {
        /**
         * @var int[] $resourceTypes
         */
        $resourceTypes = [];
        $subRequest = '';
        foreach ($filter->getTypes() as $filterType) {
            foreach ($this->resourceTypes as $resourceType) {
                if ($resourceType->isValidForTypeName($filterType)) {
                    $resourceTypes[] = $resourceType->getId();
                    break;
                }
            }
        }

        if (! empty($resourceTypes)) {
            $subRequest = ' AND resources.type IN (' . implode(', ', $resourceTypes) . ')';
        }

        return $subRequest;
    }

    /**
     * This adds the sub request filter on resource state.
     *
     * @param ResourceFilter $filter
     *
     * @return string
     */
    private function addResourceStateSubRequest(ResourceFilter $filter): string
    {
        $subRequest = '';
        if (
            ! empty($filter->getStates())
            && ! $filter->hasState(ResourceFilter::STATE_ALL)
        ) {
            $sqlState = [];
            $sqlStateCatalog = [
                ResourceFilter::STATE_RESOURCES_PROBLEMS => '(resources.status != 0 AND resources.status != 4)',
                ResourceFilter::STATE_UNHANDLED_PROBLEMS => <<<'SQL'

                    (
                        resources.status != 0
                        AND resources.status != 4
                        AND resources.acknowledged = 0
                        AND resources.in_downtime = 0
                        AND resources.status_confirmed = 1
                    )
                    SQL,
                ResourceFilter::STATE_ACKNOWLEDGED => 'resources.acknowledged = 1',
                ResourceFilter::STATE_IN_DOWNTIME => 'resources.in_downtime = 1',
                ResourceFilter::STATE_IN_FLAPPING => 'resources.flapping = 1',
            ];

            foreach ($filter->getStates() as $state) {
                $sqlState[] = $sqlStateCatalog[$state];
            }

            $subRequest .= ' AND (' . implode(' OR ', $sqlState) . ')';
        }

        return $subRequest;
    }

    /**
     * This adds the sub request filter on resource status.
     *
     * @param ResourceFilter $filter
     *
     * @return string
     */
    private function addResourceStatusSubRequest(ResourceFilter $filter): string
    {
        $subRequest = '';
        $sqlStatuses = [];
        if (! empty($filter->getStatuses())) {
            foreach ($filter->getStatuses() as $status) {
                switch ($status) {
                    case ResourceFilter::STATUS_PENDING:
                        $sqlStatuses[] = 'resources.status = ' . ResourceFilter::MAP_STATUS_SERVICE[$status];
                        break;
                    case ResourceFilter::STATUS_OK:
                    case ResourceFilter::STATUS_WARNING:
                    case ResourceFilter::STATUS_UNKNOWN:
                    case ResourceFilter::STATUS_CRITICAL:
                        $sqlStatuses[] = '(resources.type != ' . self::RESOURCE_TYPE_HOST
                            . ' AND resources.status = ' . ResourceFilter::MAP_STATUS_SERVICE[$status] . ')';
                        break;
                    case ResourceFilter::STATUS_UP:
                    case ResourceFilter::STATUS_DOWN:
                    case ResourceFilter::STATUS_UNREACHABLE:
                        $sqlStatuses[] = '(resources.type = ' . self::RESOURCE_TYPE_HOST
                            . ' AND resources.status = ' . ResourceFilter::MAP_STATUS_HOST[$status] . ')';
                        break;
                }
            }

            $subRequest = ' AND (' . implode(' OR ', $sqlStatuses) . ')';
        }

        return $subRequest;
    }

    /**
     * This adds the sub request filter on resource status type.
     *
     * @param ResourceFilter $filter
     *
     * @return string
     */
    private function addStatusTypeSubRequest(ResourceFilter $filter): string
    {
        $subRequest = '';
        $sqlStatusTypes = [];

        if (! empty($filter->getStatusTypes())) {
            foreach ($filter->getStatusTypes() as $statusType) {
                if (\array_key_exists($statusType, ResourceFilter::MAP_STATUS_TYPES)) {
                    $sqlStatusTypes[] = 'resources.status_confirmed = ' . ResourceFilter::MAP_STATUS_TYPES[$statusType];
                }
            }

            $subRequest = ' AND (' . implode(' OR ', $sqlStatusTypes) . ')';
        }

        return $subRequest;
    }

    /**
     * This adds the subrequest filter for Monitoring Server.
     *
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     *
     * @throws CollectionException
     * @throws ValueObjectException
     * @return string
     */
    private function addMonitoringServerSubRequest(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter
    ): string {
        $subRequest = '';
        if (! empty($filter->getMonitoringServerNames())) {
            $monitoringServerNames = [];

            foreach ($filter->getMonitoringServerNames() as $index => $monitoringServerName) {
                $key = ":monitoringServerName_{$index}";

                $monitoringServerNames[] = $key;
                $queryParametersFromRequestParameter->add($key, QueryParameter::string($key, $monitoringServerName));
            }

            $subRequest .= ' AND instances.name IN (' . implode(', ', $monitoringServerNames) . ')';
        }

        return $subRequest;
    }

    /**
     * Get icons for resources.
     *
     * @param array<int, int|null> $iconIds
     *
     * @throws RepositoryException
     * @return array<int, array<string, string>>
     */
    private function getIconsDataForResources(array $iconIds): array
    {
        try {
            $icons = [];

            if ($iconIds !== []) {
                $iconIds = array_values($iconIds);

                $queryParameters = new QueryParameters();
                for ($indexIconIds = 0, $iMax = count($iconIds); $indexIconIds < $iMax; $indexIconIds++) {
                    $queryParameter = null;
                    $queryParameterName = "icon_id_{$indexIconIds}";
                    $iconId = $iconIds[$indexIconIds];
                    if (is_null($iconId)) {
                        $queryParameter = QueryParameter::null($queryParameterName);
                    } else {
                        $queryParameter = QueryParameter::int($queryParameterName, $iconId);
                    }
                    $queryParameters->add($queryParameter->getName(), $queryParameter);
                }

                $query = 'SELECT
                            img_id AS `icon_id`,
                            img_name AS `icon_name`,
                            img_path AS `icon_path`,
                            imgd.dir_name AS `icon_directory`
                        FROM `:db`.view_img img
                        LEFT JOIN `:db`.view_img_dir_relation imgdr
                            ON imgdr.img_img_id = img.img_id
                        INNER JOIN `:db`.view_img_dir imgd
                            ON imgd.dir_id = imgdr.dir_dir_parent_id
                        WHERE img.img_id IN (:' . implode(',:', $queryParameters->keys()) . ')';

                foreach (
                    $this->connection->iterateAssociative(
                        $this->translateDbName($query),
                        $queryParameters
                    ) as $record
                ) {
                    /** @var array{
                     *     icon_id: int,
                     *     icon_name: string,
                     *     icon_path: string,
                     *     icon_directory: string
                     * } $record
                     */
                    $icons[(int) $record['icon_id']] = [
                        'name' => $record['icon_name'],
                        'url' => $record['icon_directory'] . DIRECTORY_SEPARATOR . $record['icon_path'],
                    ];
                }
            }

            return $icons;
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: 'An error occurred while fetching icons data for resources',
                context: ['iconIds' => $iconIds],
                previous: $exception
            );
        }
    }
}
