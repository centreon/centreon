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
use Core\Common\Infrastructure\RequestParameters\Transformer\RequestParametersTransformer;
use Core\Domain\RealTime\ResourceTypeInterface;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Infrastructure\Repository\ExtraDataProviders\ExtraDataProviderInterface;
use Core\Resources\Infrastructure\Repository\ResourceACLProviders\ResourceACLProviderInterface;
use Core\Severity\RealTime\Domain\Model\Severity;
use Core\Tag\RealTime\Domain\Model\Tag;

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

        $query .= $this->addResourceParentIdSubRequest($filter);

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
            $queryParameters = RequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );

            foreach ($this->connection->iterateAssociative($queryResources, $queryParameters) as $resourceRecord) {
                /** @var array<string,int|string|null> $resourceRecord */
                $this->resources[] = DbResourceFactory::createFromRecord($resourceRecord, $this->resourceTypes);
            }

            // get total without pagination
            $queryTotal = $this->translateDbName('SELECT FOUND_ROWS() AS REALTIME from `:dbstg`.`resources`');
            if (($total = $this->connection->fetchOne($queryTotal)) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }
        } catch (AssertionFailedException|TransformerException|ConnectionException $exception) {
            throw new RepositoryException(
                message: "An error occurred while finding parent resources by id",
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
        $this->resources = [];

        $request = $this->generateFindResourcesRequest($filter);
        $this->fetchResources($request);

        return $this->resources;
    }

    /**
     * @param ResourceFilter $filter
     * @param array $accessGroupIds
     *
     * @throws RepositoryException
     * @return ResourceEntity[]
     */
    public function findResourcesByAccessGroupIds(ResourceFilter $filter, array $accessGroupIds): array
    {
        $this->resources = [];

        $accessGroupRequest = $this->addResourceAclSubRequest($accessGroupIds);
        $request = $this->generateFindResourcesRequest($filter, $accessGroupRequest);
        $this->fetchResources($request);

        return $this->resources;
    }

    /**
     * @param ResourceFilter $filter
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    public function iterateResourcesByMaxResults(ResourceFilter $filter, int $maxResults = 0): \Traversable
    {
        $this->resources = [];

        // if $maxResults is set to 0, we use pagination and limit
        if ($maxResults > 0) {
            // for an export, we can have no pagination, so we limit the number of results in this case
            // page is always 1 and limit is the maxResults
            $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
            $this->sqlRequestTranslator->getRequestParameters()->setLimit($maxResults);
        }

        $request = $this->generateFindResourcesRequest($filter);

        return $this->iterateResources($request, $maxResults);
    }

    /**
     * @param ResourceFilter $filter
     * @param array<int> $accessGroupIds
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    public function iterateResourcesByAccessGroupIdsAndMaxResults(
        ResourceFilter $filter,
        array $accessGroupIds,
        int $maxResults = 0
    ): \Traversable {
        $this->resources = [];

        // if $maxResults is set to 0, we use pagination and limit
        if ($maxResults > 0) {
            // for an export, we can have no pagination, so we limit the number of results in this case
            // page is always 1 and limit is the maxResults
            $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
            $this->sqlRequestTranslator->getRequestParameters()->setLimit($maxResults);
        }

        $accessGroupRequest = $this->addResourceAclSubRequest($accessGroupIds);

        $request = $this->generateFindResourcesRequest($filter, $accessGroupRequest);

        return $this->iterateResources($request, $maxResults);
    }

    /**
     * @param ResourceFilter $filter
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return int
     */
    public function countResourcesByMaxResults(ResourceFilter $filter, int $maxResults = 0): int
    {
        // For a count, there isn't pagination we limit the number of results
        // page is always 1 and limit is the maxResults in case of an export
        $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
        $this->sqlRequestTranslator->getRequestParameters()->setLimit($maxResults);

        $request = $this->generateFindResourcesRequest(
            filter: $filter,
            onlyCount: true
        );

        return $this->countResources($request, $maxResults);
    }

    /**
     * @param ResourceFilter $filter
     * @param array<int> $accessGroupIds
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return int
     */
    public function countResourcesByAccessGroupIdsAndByMaxResults(
        ResourceFilter $filter,
        array $accessGroupIds,
        int $maxResults = 0
    ): int {
        // For a count, there isn't pagination we limit the number of results
        // page is always 1 and limit is the maxResults in case of an export
        $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
        $this->sqlRequestTranslator->getRequestParameters()->setLimit($maxResults);

        $accessGroupRequest = $this->addResourceAclSubRequest($accessGroupIds);

        $request = $this->generateFindResourcesRequest(
            filter: $filter,
            accessGroupRequest: $accessGroupRequest,
            onlyCount: true
        );

        return $this->countResources($request, $maxResults);
    }

    // ------------------------------------- PRIVATE METHODS -------------------------------------

    /**
     * @param ResourceFilter $filter
     * @param string $accessGroupRequest
     * @param bool $onlyCount
     *
     * @throws RepositoryException
     * @return string
     */
    private function generateFindResourcesRequest(
        ResourceFilter $filter,
        string $accessGroupRequest = '',
        bool $onlyCount = false
    ): string {
        $this->sqlRequestTranslator->setConcordanceArray($this->resourceConcordances);

        $request = $this->createQueryHeaders($filter, $collector);

        $resourceType = self::RESOURCE_TYPE_HOST;

        $joinCtes = $request === ''
            ? ''
            : ' INNER JOIN cte ON cte.resource_id = resources.resource_id ';

        if ($onlyCount) {
            $request =
                'SELECT COUNT(DISTINCT resources.resource_id), 1 AS REALTIME';
        } else {
        $request .= <<<SQL
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

        $request .= <<<SQL
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
                message: "An error occurred while generating the request",
                previous: $exception
            );
        }

        $request .= ! empty($searchSubRequest) ? $searchSubRequest . ' AND ' : ' WHERE ';

        $request .= <<<SQL
            resources.name NOT LIKE '\_Module\_%'
                AND resources.parent_name NOT LIKE '\_Module\_BAM%'
                AND resources.enabled = 1
                AND resources.type != 3
            SQL;

        // Apply only_with_performance_data
        if ($filter->getOnlyWithPerformanceData() === true) {
            $request .= ' AND resources.has_graph = 1';
        }

        foreach ($this->extraDataProviders as $provider) {
            $request .= $provider->getSubFilter($filter);
        }

        $request .= $accessGroupRequest;

        $request .= $this->addResourceParentIdSubRequest($filter);

        /**
         * Resource Type filter
         * 'service', 'metaservice', 'host'.
         */
        $request .= $this->addResourceTypeSubRequest($filter);

        /**
         * State filter
         * 'unhandled_problems', 'resource_problems', 'acknowledged', 'in_downtime'.
         */
        $request .= $this->addResourceStateSubRequest($filter);

        /**
         * Status filter
         * 'OK', 'WARNING', 'CRITICAL', 'UNKNOWN', 'UP', 'UNREACHABLE', 'DOWN', 'PENDING'.
         */
        $request .= $this->addResourceStatusSubRequest($filter);

        /**
         * Status type filter
         * 'HARD', 'SOFT'.
         */
        $request .= $this->addStatusTypeSubRequest($filter);

        /**
         * Monitoring Server filter.
         */
        $request .= $this->addMonitoringServerSubRequest($filter);

        /**
         * Severity filter (levels and/or names).
         */
        $request .= $this->addSeveritySubRequest($filter);

        if (! $onlyCount) {
            /**
             * Handle sort parameters.
             */
            $request .= $this->sqlRequestTranslator->translateSortParameterToSql()
                ?: ' ORDER BY resources.status_ordered DESC, resources.name ASC';

            /**
             * Handle pagination.
             */
            $request .= $this->sqlRequestTranslator->translatePaginationToSql();
        }

        return $request;
    }

    /**
     * This adds the subrequest filter for tags (servicegroups, hostgroups).
     *
     * @param ResourceFilter $filter
     *
     * @return string
     */
    private function addResourceTagsSubRequest(ResourceFilter $filter): string
    {
        $subRequest = '';
        $searchedTagNames = [];
        $includeHostResourceType = false;
        $includeServiceResourceType = false;
        $searchedTags = [];

        if (! empty($filter->getHostgroupNames())) {
            $includeHostResourceType = true;
            foreach ($filter->getHostgroupNames() as $hostGroupName) {
                $searchedTags[Tag::HOST_GROUP_TYPE_ID][] = $hostGroupName;
                $searchedTagNames[] = $hostGroupName;
            }
        }

        if (! empty($filter->getServicegroupNames())) {
            $includeServiceResourceType = true;
            foreach ($filter->getServicegroupNames() as $serviceGroupName) {
                $searchedTags[Tag::SERVICE_GROUP_TYPE_ID][] = $serviceGroupName;
                $searchedTagNames[] = $serviceGroupName;
            }
        }

        if (! empty($filter->getServiceCategoryNames())) {
            $includeServiceResourceType = true;
            foreach ($filter->getServiceCategoryNames() as $serviceCategoryName) {
                $searchedTags[Tag::SERVICE_CATEGORY_TYPE_ID][] = $serviceCategoryName;
                $searchedTagNames[] = $serviceCategoryName;
            }
        }

        if (! empty($filter->getHostCategoryNames())) {
            $includeHostResourceType = true;
            foreach ($filter->getHostCategoryNames() as $hostCategoryName) {
                $searchedTags[Tag::HOST_CATEGORY_TYPE_ID][] = $hostCategoryName;
                $searchedTagNames[] = $hostCategoryName;
            }
        }

        if ($searchedTagNames !== []) {
            $subRequest = ' INNER JOIN (';
            $intersectRequest = '';
            $index = 1;
            foreach ($searchedTags as $type => $names) {
                $tagKeys = [];
                foreach ($names as $name) {
                    $key = ":tagName_{$index}";
                    $index++;
                    $tagKeys[] = $key;
                }
                $literalTagKeys = implode(', ', $tagKeys);

                if ($intersectRequest !== '') {
                    $intersectRequest .= ' INTERSECT ';
                }

                if (
                    $type === Tag::HOST_GROUP_TYPE_ID
                    || $type === Tag::HOST_CATEGORY_TYPE_ID
                ) {
                    $intersectRequest .= <<<"SQL"
                            SELECT resources.resource_id
                            FROM `:dbstg`.`resources` resources
                            LEFT JOIN `:dbstg`.`resources` parent_resource
                                ON parent_resource.id = resources.parent_id
                            LEFT JOIN `:dbstg`.resources_tags AS rtags
                              ON rtags.resource_id = resources.resource_id
                              OR rtags.resource_id = parent_resource.resource_id
                            INNER JOIN `:dbstg`.tags
                                ON tags.tag_id = rtags.tag_id
                            WHERE tags.name IN ({$literalTagKeys})
                            AND tags.type = {$type}
                        SQL;
                } else {
                    $intersectRequest .= <<<"SQL"
                            SELECT rtags.resource_id
                            FROM `:dbstg`.resources_tags AS rtags
                            INNER JOIN `:dbstg`.tags
                                ON tags.tag_id = rtags.tag_id
                            WHERE tags.name IN ({$literalTagKeys})
                            AND tags.type = {$type}
                        SQL;
                }
            }

            $subRequest .= $intersectRequest . ') AS tag ON tag.resource_id = resources.resource_id';

            if (
                $includeHostResourceType
                && ! $includeServiceResourceType
            ) {
                $subRequest .= ' OR tag.resource_id = parent_resource.resource_id';
            }
        }

        return $subRequest;
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
     * @param StatementCollector $collector
     *
     * @return string
     */
    private function createQueryHeaders(ResourceFilter $filter, StatementCollector $collector): string
    {
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
                $collector->addValue($key, $hostGroupName, \PDO::PARAM_STR);
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
                $collector->addValue($key, $hostCategoryName, \PDO::PARAM_STR);
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
                $collector->addValue($key, $serviceGroupName, \PDO::PARAM_STR);
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
                $collector->addValue($key, $serviceCategoryName, \PDO::PARAM_STR);
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
     *
     * @throws RepositoryException
     */
    private function fetchResources(string $query): void
    {
        try {
            $queryResources = $this->translateDbName($query);
            $queryParameters = RequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );

            foreach ($this->connection->iterateAssociative($queryResources, $queryParameters) as $resourceRecord) {
                /** @var array<string,int|string|null> $resourceRecord */
                $this->resources[] = DbResourceFactory::createFromRecord($resourceRecord, $this->resourceTypes);
            }

            // get total without pagination
            $queryTotal = $this->translateDbName('SELECT FOUND_ROWS() AS REALTIME from `:dbstg`.`resources`');
            if (($total = $this->connection->fetchOne($queryTotal)) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }
        } catch (AssertionFailedException|TransformerException|ConnectionException $exception) {
            throw new RepositoryException(
                message: "An error occurred while finding resources : {$exception->getMessage()}",
                previous: $exception
            );
        }

        $iconIds = $this->getIconIdsFromResources();
        $icons = $this->getIconsDataForResources($iconIds);
        $this->completeResourcesWithIcons($icons);
    }

    /**
     * @param string $query
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return int
     */
    private function countResources(string $query, int $maxResults = 0): int
    {
        try {
            $queryResources = $this->translateDbName($query);
            $queryParameters = RequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );

            return $this->connection->fetchOne($queryResources, $queryParameters);
        } catch (TransformerException|ConnectionException $exception) {
            throw new RepositoryException(
                message: "An error occurred while counting resources : {$exception->getMessage()}",
                context: ['maxResults' => $maxResults],
                previous: $exception
            );
        }
    }

    /**
     * @param string $query
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    private function iterateResources(string $query, int $maxResults = 0): \Traversable
    {
        try {
            $queryResources = $this->translateDbName($query);
            $queryParameters = RequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );
            foreach ($this->connection->iterateAssociative($queryResources, $queryParameters) as $resource) {
                $this->resources = [DbResourceFactory::createFromRecord($resource, $this->resourceTypes)];
                $iconIds = $this->getIconIdsFromResources();
                $icons = $this->getIconsDataForResources($iconIds);
                $this->completeResourcesWithIcons($icons);

                yield $this->resources[0];
            }
        } catch (AssertionFailedException|TransformerException|ConnectionException $exception) {
            throw new RepositoryException(
                message: "An error occurred while iterating resources : {$exception->getMessage()}",
                context: ['maxResults' => $maxResults, 'resource' => $this->resources],
                previous: $exception
            );
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
     *
     * @return string
     */
    private function addSeveritySubRequest(ResourceFilter $filter): string
    {
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
        }

        foreach ($levels as $index => $level) {
            $key = ":severityLevel_{$index}";
            $filteredLevels[] = $key;
        }

        foreach ($types as $index => $type) {
            $key = ":severityType_{$index}";
            $filteredTypes[] = $key;
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
     *
     * @return string
     */
    private function addResourceParentIdSubRequest(ResourceFilter $filter): string
    {
        $subRequest = '';
        $filteredParentIds = [];

        if (empty($filter->getHostIds())) {
            return $subRequest;
        }

        foreach ($filter->getHostIds() as $index => $hostId) {
            $key = ":parentId_{$index}";
            $filteredParentIds[] = $key;
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
     *
     * @return string
     */
    private function addMonitoringServerSubRequest(ResourceFilter $filter): string
    {
        $subRequest = '';
        if (! empty($filter->getMonitoringServerNames())) {
            $monitoringServerNames = [];

            foreach ($filter->getMonitoringServerNames() as $index => $monitoringServerName) {
                $key = ":monitoringServerName_{$index}";

                $monitoringServerNames[] = $key;
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
                for ($i = 0, $iMax = count($iconIds); $i < $iMax; $i++) {
                    $queryParameter = null;
                    $queryParameterName = "icon_id_{$i}";
                    if (is_null($iconIds[$i])) {
                        $queryParameter = QueryParameter::null($queryParameterName);
                    } elseif (is_int($iconIds[$i])) {
                        $queryParameter = QueryParameter::int($queryParameterName, $iconIds[$i]);
                    } else {
                        $queryParameter = QueryParameter::string($queryParameterName, $iconIds[$i]);
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
                message: "An error occurred while fetching icons data for resources",
                context: ['iconIds' => $iconIds],
                previous: $exception
            );
        }
    }
}
