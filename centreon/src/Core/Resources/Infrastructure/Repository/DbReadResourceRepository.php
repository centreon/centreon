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
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;
use Core\Domain\RealTime\ResourceTypeInterface;
use Core\Resources\Application\Repository\CountResult;
use Core\Resources\Application\Repository\FindResourcesResult;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Infrastructure\Repository\ExtraDataProviders\ExtraDataProviderInterface;
use Core\Severity\RealTime\Domain\Model\Severity;

class DbReadResourceRepository extends DatabaseRepository implements ReadResourceRepositoryInterface
{
    use LoggerTrait;

    /**
     * Maximum number of rows to scan when computing a bounded (approximate) COUNT.
     * When a free-text search spans multiple columns (alias, address, output) the exact COUNT
     * can take 10-15 s because no covering index is available for those columns. For result sets
     * larger than this limit the query stops early and returns this value; the caller signals to
     * the client that the count is approximate via the is_approximate flag.
     */
    public const BOUNDED_COUNT_LIMIT = 1_000;
    private const RESOURCE_TYPE_HOST = 1;

    /**
     * When the number of ACL entries for the user's groups is below this threshold, we pre-compute
     * accessible resource IDs via a WITH acl_accessible CTE and drive the join from that small set.
     * Above the threshold, ACL selectivity is high enough that the correlated EXISTS approach is
     * faster (the sort-index scan early-terminates at LIMIT after seeing few rows).
     */
    private const ACL_CTE_THRESHOLD = 20_000;

    /** @var ResourceEntity[] */
    private array $resources = [];

    /** Tracks whether the last count operation was capped by BOUNDED_COUNT_LIMIT. */
    private bool $isLastCountApproximate = false;

    /** Tracks whether generateCountResourcesQuery() actually applied bounded mode (LIMIT scan). */
    private bool $lastCountWasBounded = false;

    /** @var ResourceTypeInterface[] */
    private array $resourceTypes;

    /** @var SqlRequestParametersTranslator */
    private SqlRequestParametersTranslator $sqlRequestTranslator;

    /** @var ExtraDataProviderInterface[] */
    private array $extraDataProviders;

    /** @var array<string, int> */
    private array $aclCountCache = [];

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
     * @param ConnectionInterface $db
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     * @param \Traversable<ResourceTypeInterface> $resourceTypes
     * @param \Traversable<ExtraDataProviderInterface> $extraDataProviders
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ConnectionInterface $db,
        SqlRequestParametersTranslator $sqlRequestTranslator,
        \Traversable $resourceTypes,
        \Traversable $extraDataProviders,
    ) {
        parent::__construct($db);
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
            SELECT DISTINCT
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
            INNER JOIN `:dbstg`.`instances`
                ON `instances`.instance_id = `resources`.poller_id
            WHERE resources.is_module = 0
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
         * Handle search values.
         * >> To do before count and find resources to prepare the query parameters with the search values for both queries.
         */
        try {
            $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues()
            );
            $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameters);
        } catch (TransformerException|CollectionException $e) {
            throw new RepositoryException(
                message: 'An error occurred while translating search parameters to query parameters while finding parent resources by id',
                context: ['searchValues' => $this->sqlRequestTranslator->getSearchValues()],
                previous: $e
            );
        }

        /**
         * Translate the query with the database name.
         * >> To do before count and find resources to prepare the query with the database name for both queries.
         */
        $query = $this->translateDbName($query);

        /**
         * Handle count resources.
         * >> To do before find resources to not interfere with pagination and sort.
         */
        try {
            $queryTotal = $this->connection->createQueryBuilder()
                ->select('COUNT(*)')
                ->from("({$query})", 'count_resources_by_parent_id')
                ->getQuery();

            if (($total = $this->connection->fetchOne($queryTotal, $queryParameters)) !== false) {
                $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
            }
        } catch (\Exception $totalException) {
            throw new RepositoryException(
                message: 'An error occurred while counting parent resources by id',
                context: ['searchValues' => $this->sqlRequestTranslator->getSearchValues()],
                previous: $totalException
            );
        }

        /**
         * Handle sort parameters.
         */
        $query .= $this->sqlRequestTranslator->translateSortParameterToSql()
            ?: ' ORDER BY resources.status_ordered DESC, resources.last_status_change DESC';

        /**
         * Handle pagination.
         */
        $query .= $this->sqlRequestTranslator->translatePaginationToSql();

        /**
         * Handle find resources.
         */
        try {
            foreach ($this->connection->iterateAssociative($query, $queryParameters) as $resourceRecord) {
                /** @var array<string,int|string|null> $resourceRecord */
                $this->resources[] = DbResourceFactory::createFromRecord($resourceRecord, $this->resourceTypes);
            }
        } catch (AssertionFailedException|\Exception $findException) {
            throw new RepositoryException(
                message: 'An error occurred while finding parent resources by id',
                context: ['filter' => $filter],
                previous: $findException
            );
        }

        /**
         * Handle complete resources.
         */
        $iconIds = $this->getIconIdsFromResources();
        $icons = $this->getIconsDataForResources($iconIds);
        $this->completeResourcesWithIcons($icons);

        return $this->resources;
    }

    /**
     * @param ResourceFilter $filter
     *
     * @throws RepositoryException
     * @return FindResourcesResult
     */
    public function findResources(ResourceFilter $filter): FindResourcesResult
    {
        try {
            $this->resources = [];
            $this->find($filter);

            return new FindResourcesResult(
                resources: $this->resources,
                isApproximate: $this->isLastCountApproximate,
            );
        } catch (AssertionFailedException|\Exception $exception) {
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
     * @return FindResourcesResult
     */
    public function findResourcesByAccessGroupIds(ResourceFilter $filter, array $accessGroupIds): FindResourcesResult
    {
        try {
            $this->resources = [];
            $this->find($filter, $accessGroupIds);

            return new FindResourcesResult(
                resources: $this->resources,
                isApproximate: $this->isLastCountApproximate,
            );
        } catch (AssertionFailedException|\Exception $exception) {
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
            $query = $this->generateFindResourcesQuery(
                filter: $filter,
                queryParametersFromRequestParameter: $queryParametersFromRequestParameter
            );

            return $this->iterate($query, $queryParametersFromRequestParameter);
        } catch (\Exception $exception) {
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
        int $maxResults = 0,
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

            $queryParametersFromRequestParameter = new QueryParameters();
            $query = $this->generateFindResourcesQuery(
                filter: $filter,
                queryParametersFromRequestParameter: $queryParametersFromRequestParameter,
                accessGroupIds: $accessGroupIds,
                useAclCte: $this->shouldUseAclCte($accessGroupIds),
            );

            return $this->iterate($query, $queryParametersFromRequestParameter);
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'An error occurred while iterating resources by access group ids and max results',
                context: ['filter' => $filter, 'accessGroupIds' => $accessGroupIds, 'maxResults' => $maxResults],
                previous: $exception
            );
        }
    }

    /**
     * @param ResourceFilter $filter
     * @param bool $allPages
     *
     * @throws RepositoryException
     * @return CountResult
     */
    public function countResourcesByFilter(ResourceFilter $filter, bool $allPages): CountResult
    {
        try {
            return $this->count($filter, $allPages);
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'An error occurred while counting resources by max results',
                context: ['filter' => $filter, 'allPages' => $allPages],
                previous: $exception
            );
        }
    }

    /**
     * @param ResourceFilter $filter
     * @param bool $allPages
     * @param array<int> $accessGroupIds
     *
     * @throws RepositoryException
     * @return CountResult
     */
    public function countResourcesByFilterAndAccessGroupIds(
        ResourceFilter $filter,
        bool $allPages,
        array $accessGroupIds,
    ): CountResult {
        try {
            return $this->count($filter, $allPages, $accessGroupIds);
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'An error occurred while counting resources by access group ids and max results',
                context: ['filter' => $filter, 'accessGroupIds' => $accessGroupIds, 'allPages' => $allPages],
                previous: $exception
            );
        }
    }

    /**
     * @throws RepositoryException
     * @return int
     */
    public function countAllResources(): int
    {
        try {
            $query = $this->connection->createQueryBuilder()
                ->select('COUNT(DISTINCT resources.resource_id) AS REALTIME')
                ->from('`:dbstg`.`resources`')
                ->getQuery();

            return (int) $this->connection->fetchOne($this->translateDbName($query));
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'An error occurred while counting all resources',
                previous: $exception
            );
        }
    }

    /**
     * @param array<int> $accessGroupIds
     *
     * @throws RepositoryException
     * @return int
     */
    public function countAllResourcesByAccessGroupIds(array $accessGroupIds): int
    {
        try {
            $aclCte = $this->buildAclCte($accessGroupIds);
            $query = $this->translateDbName(<<<SQL
                {$aclCte}
                SELECT COUNT(*) FROM acl_accessible
                SQL);

            return (int) $this->connection->fetchOne($query);
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'An error occurred while counting resources by access group ids and max results',
                context: ['accessGroupIds' => $accessGroupIds],
                previous: $exception
            );
        }
    }

    // ------------------------------------- PRIVATE METHODS -------------------------------------

    /**
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     * @param int[] $accessGroupIds
     * @param bool $withoutSort
     * @param bool $withoutPagination
     * @throws CollectionException
     * @throws RepositoryException
     * @throws ValueObjectException
     * @throws \InvalidArgumentException
     * @return string
     */
    private function generateFindResourcesQuery(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter,
        array $accessGroupIds = [],
        bool $withoutSort = false,
        bool $withoutPagination = false,
        bool $useTagExistsForFilters = false,
        bool $useAclCte = false,
    ): string {
        $this->sqlRequestTranslator->setConcordanceArray($this->resourceConcordances);

        $resourceType = self::RESOURCE_TYPE_HOST;

        $hasStatusOrStateFilter = ($filter->getStatuses() !== [] && ! $this->areAllStatusesSelected($filter->getStatuses()))
            || $filter->getStates() !== []
            || $filter->getStatusTypes() !== [];

        if ($useTagExistsForFilters) {
            // Pre-join approach: for tag filters that require parent propagation (hostgroup, host category)
            // the OR EXISTS pattern causes MariaDB to scan all 960K resources with filesort.
            // Instead, materialize each tag filter as a small derived table and INNER JOIN them in.
            // MariaDB then does PK lookups on the small intersection set (typically 50-1000 rows)
            // rather than a full scan, reducing query time from 10s+ to <100ms.
            // Servicegroup and service category also use pre-join for consistency and the same benefit.
            $tagPreJoinClauses = $this->createTagFilterPreJoinClauses(
                $filter,
                $queryParametersFromRequestParameter,
            );
            $tagExistsConditions = ''; // pre-join replaces EXISTS conditions for all tag filters
            if ($useAclCte) {
                // Pre-compute accessible resource IDs via the acl_accessible CTE and drive the join
                // from that small set. Used when ACL selectivity is very low (few accessible resources
                // out of 900k+) — the EXISTS approach would force a full scan of the sort index.
                $query = $this->buildAclCte($accessGroupIds);
                $joinCtes = ' INNER JOIN acl_accessible ON resources.resource_id = acl_accessible.resource_id '
                    . $tagPreJoinClauses;
                $sortIndexHint = ''; // No sort index: driving from small ACL/tag CTE
            } else {
                $query = '';
                // When status/state filters are active, force the status_filter_idx for tight seek.
                // When only tag pre-join filters are active, no FORCE INDEX needed — MariaDB drives
                // from the small pre-join derived tables via PK lookups.
                $sortIndexHint = $hasStatusOrStateFilter ? 'FORCE INDEX (`resources_status_filter_idx`)' : '';
                $joinCtes = $tagPreJoinClauses;
            }
        } else {
            // CTE approach: tag filters are materialized as CTEs and joined in.
            // Better for large result sets (exports) where early termination is not possible.
            $tagCtes = $this->createQueryHeaders($filter, $queryParametersFromRequestParameter);
            if ($useAclCte) {
                $query = $tagCtes . $this->buildAclCte($accessGroupIds, prependComma: $tagCtes !== '');
                $joinCtes = ($tagCtes !== '' ? ' INNER JOIN cte ON cte.resource_id = resources.resource_id ' : '')
                    . ' INNER JOIN acl_accessible ON resources.resource_id = acl_accessible.resource_id ';
            } else {
                $query = $tagCtes;
                $joinCtes = $tagCtes !== ''
                    ? ' INNER JOIN cte ON cte.resource_id = resources.resource_id '
                    : '';
            }
            $tagExistsConditions = '';
            $sortIndexHint = '';
        }

        $query .= <<<SQL
            SELECT
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
            FROM `:dbstg`.`resources` {$sortIndexHint}
            INNER JOIN `:dbstg`.`instances`
                ON `instances`.instance_id = `resources`.poller_id
            {$joinCtes}
            LEFT JOIN `:dbstg`.`resources` parent_resource
                ON parent_resource.id = resources.parent_id
                AND parent_resource.type = {$resourceType}
            LEFT JOIN `:dbstg`.`severities`
                ON `severities`.severity_id = `resources`.severity_id
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

        $query .= <<<'SQL'
            resources.is_module = 0
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

        if ($accessGroupIds !== [] && ! $useAclCte) {
            $query .= $this->buildAclExistsCondition($accessGroupIds);
        }
        // When $useAclCte is true, ACL filtering is handled via the acl_accessible CTE JOIN
        // prepended in the query header — no additional WHERE clause is needed.

        $query .= $tagExistsConditions;

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

        /**
         * Handle sort parameters.
         */
        if (! $withoutSort) {
            $query .= $this->sqlRequestTranslator->translateSortParameterToSql()
                ?: ' ORDER BY resources.status_ordered DESC, resources.last_status_change DESC';
        }

        /**
         * Handle pagination.
         */
        if (! $withoutPagination) {
            $query .= $this->sqlRequestTranslator->translatePaginationToSql();
        }

        return $query;
    }

    /**
     * Builds a WHERE clause fragment using two correlated EXISTS subqueries: one for host resources
     * (type = 1, matched by host_id) and one for all other types (matched by host_id + service_id).
     * Two separate EXISTS are used intentionally — the optimizer uses a full composite key seek
     * (key_len=13) for each, enabling early termination with LIMIT. A single merged EXISTS with OR
     * would degrade to a full group scan (key_len=4) and be much slower for queries with LIMIT.
     *
     * @param int[] $accessGroupIds
     */
    private function buildAclExistsCondition(array $accessGroupIds): string
    {
        $ids = implode(',', array_map('intval', $accessGroupIds));

        return <<<SQL
             AND (
                EXISTS (
                    SELECT 1 FROM `:dbstg`.`centreon_acl` acl
                    WHERE resources.type = 1
                      AND acl.host_id = resources.id
                      AND acl.group_id IN ({$ids})
                )
                OR
                EXISTS (
                    SELECT 1 FROM `:dbstg`.`centreon_acl` acl
                    WHERE resources.type != 1
                      AND acl.host_id = resources.parent_id
                      AND acl.service_id = resources.id
                      AND acl.group_id IN ({$ids})
                )
            )
            SQL;
    }

    /**
     * Returns true when the CTE-based ACL strategy should be used instead of correlated EXISTS.
     *
     * The decision is based on the number of ACL entries for the given groups:
     * - Few entries  → CTE: pre-compute ~N accessible resource IDs, drive join from that small set.
     *                  Avoids scanning 900k+ rows with EXISTS when ACL selectivity is very low.
     * - Many entries → EXISTS: ACL selectivity is high, the sort-index scan early-terminates at
     *                  LIMIT quickly. Building and sorting a large CTE would be slower.
     *
     * The count is cached per group-id set to avoid querying centreon_acl twice when find() and
     * count() are called with the same groups within the same request.
     *
     * @param int[] $accessGroupIds
     */
    private function shouldUseAclCte(array $accessGroupIds): bool
    {
        if ($accessGroupIds === []) {
            return false;
        }

        $ids = array_map('intval', $accessGroupIds);
        sort($ids);
        $cacheKey = implode(',', $ids);

        if (! isset($this->aclCountCache[$cacheKey])) {
            $placeholders = implode(',', $ids);
            $this->aclCountCache[$cacheKey] = (int) $this->connection->fetchOne(
                $this->translateDbName(
                    "SELECT COUNT(*) FROM `:dbstg`.`centreon_acl` WHERE group_id IN ({$placeholders})"
                )
            );
        }

        return $this->aclCountCache[$cacheKey] < self::ACL_CTE_THRESHOLD;
    }

    /**
     * Builds a WITH acl_accessible CTE that pre-computes accessible resource IDs by joining from
     * centreon_acl to resources (ACL-driven, not resource-driven). This avoids the correlated
     * EXISTS overhead on COUNT queries and is significantly faster for users with partial access.
     * The CTE uses UNION ALL (not UNION) since hosts and non-hosts are disjoint by type.
     *
     * If $prependComma is true, the output starts with a comma for appending to an existing WITH block.
     *
     * @param int[] $accessGroupIds
     */
    private function buildAclCte(array $accessGroupIds, bool $prependComma = false): string
    {
        $ids = implode(',', array_map('intval', $accessGroupIds));
        $prefix = $prependComma ? ',' : 'WITH';

        return <<<SQL
            {$prefix} acl_accessible AS (
                SELECT DISTINCT r.resource_id
                FROM `:dbstg`.`centreon_acl` acl
                INNER JOIN `:dbstg`.`resources` r
                    ON r.type = 1
                    AND r.id = acl.host_id
                    AND r.enabled = 1
                    AND r.is_module = 0
                WHERE acl.service_id IS NULL
                  AND acl.group_id IN ({$ids})

                UNION ALL

                SELECT DISTINCT r.resource_id
                FROM `:dbstg`.`centreon_acl` acl
                INNER JOIN `:dbstg`.`resources` r
                    ON r.id = acl.service_id
                    AND r.parent_id = acl.host_id
                    AND r.type != 3
                    AND r.enabled = 1
                    AND r.is_module = 0
                WHERE acl.service_id IS NOT NULL
                  AND acl.group_id IN ({$ids})
            )
            SQL;
    }

    /**
     * Generates INNER JOIN clauses against pre-materialized derived tables for tag filters that require
     * parent-propagation (hostgroup, host category). Using a pre-join instead of correlated EXISTS avoids
     * scanning the full resources table: MariaDB materializes the small intersection set first, then does
     * primary-key lookups — typically 50-1000 rows instead of 960K+.
     *
     * All four tag types (hostgroup, host category, servicegroup, service category) are handled here,
     * producing one INNER JOIN per active filter type.
     *
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParameters
     *
     * @throws CollectionException
     * @throws ValueObjectException
     * @return string INNER JOIN clauses to append after the main FROM block
     */
    private function createTagFilterPreJoinClauses(
        ResourceFilter $filter,
        QueryParameters $queryParameters,
    ): string {
        $joins = '';

        if ($filter->getHostgroupNames() !== []) {
            $keys = [];
            foreach ($filter->getHostgroupNames() as $index => $name) {
                $key = ":hg_pj_{$index}";
                $queryParameters->add($key, QueryParameter::string($key, $name));
                $keys[] = $key;
            }
            $keysStr = implode(', ', $keys);
            // Derived table: resource_ids that are directly in the hostgroup OR whose parent host is.
            // UNION deduplicates (a host itself may appear in both branches).
            $joins .= <<<SQL

                INNER JOIN (
                    SELECT rt.resource_id
                    FROM `:dbstg`.`resources_tags` rt
                    INNER JOIN `:dbstg`.`tags` t ON t.tag_id = rt.tag_id
                    WHERE t.type = 1 AND t.name IN ({$keysStr})
                    UNION
                    SELECT child.resource_id
                    FROM `:dbstg`.`resources` child
                    INNER JOIN `:dbstg`.`resources` parent
                        ON parent.id = child.parent_id AND parent.type = 1 AND parent.enabled = 1
                    INNER JOIN `:dbstg`.`resources_tags` rt ON rt.resource_id = parent.resource_id
                    INNER JOIN `:dbstg`.`tags` t ON t.tag_id = rt.tag_id
                    WHERE t.type = 1 AND t.name IN ({$keysStr})
                ) hg_pj ON hg_pj.resource_id = resources.resource_id
                SQL;
        }

        if ($filter->getHostCategoryNames() !== []) {
            $keys = [];
            foreach ($filter->getHostCategoryNames() as $index => $name) {
                $key = ":hc_pj_{$index}";
                $queryParameters->add($key, QueryParameter::string($key, $name));
                $keys[] = $key;
            }
            $keysStr = implode(', ', $keys);
            $joins .= <<<SQL

                INNER JOIN (
                    SELECT rt.resource_id
                    FROM `:dbstg`.`resources_tags` rt
                    INNER JOIN `:dbstg`.`tags` t ON t.tag_id = rt.tag_id
                    WHERE t.type = 3 AND t.name IN ({$keysStr})
                    UNION
                    SELECT child.resource_id
                    FROM `:dbstg`.`resources` child
                    INNER JOIN `:dbstg`.`resources` parent
                        ON parent.id = child.parent_id AND parent.type = 1 AND parent.enabled = 1
                    INNER JOIN `:dbstg`.`resources_tags` rt ON rt.resource_id = parent.resource_id
                    INNER JOIN `:dbstg`.`tags` t ON t.tag_id = rt.tag_id
                    WHERE t.type = 3 AND t.name IN ({$keysStr})
                ) hc_pj ON hc_pj.resource_id = resources.resource_id
                SQL;
        }

        if ($filter->getServicegroupNames() !== []) {
            $keys = [];
            foreach ($filter->getServicegroupNames() as $index => $name) {
                $key = ":sg_pj_{$index}";
                $queryParameters->add($key, QueryParameter::string($key, $name));
                $keys[] = $key;
            }
            $keysStr = implode(', ', $keys);
            $joins .= <<<SQL

                INNER JOIN (
                    SELECT DISTINCT rt.resource_id
                    FROM `:dbstg`.`resources_tags` rt
                    INNER JOIN `:dbstg`.`tags` t ON t.tag_id = rt.tag_id
                    WHERE t.type = 0 AND t.name IN ({$keysStr})
                ) sg_pj ON sg_pj.resource_id = resources.resource_id
                SQL;
        }

        if ($filter->getServiceCategoryNames() !== []) {
            $keys = [];
            foreach ($filter->getServiceCategoryNames() as $index => $name) {
                $key = ":sc_pj_{$index}";
                $queryParameters->add($key, QueryParameter::string($key, $name));
                $keys[] = $key;
            }
            $keysStr = implode(', ', $keys);
            $joins .= <<<SQL

                INNER JOIN (
                    SELECT DISTINCT rt.resource_id
                    FROM `:dbstg`.`resources_tags` rt
                    INNER JOIN `:dbstg`.`tags` t ON t.tag_id = rt.tag_id
                    WHERE t.type = 2 AND t.name IN ({$keysStr})
                ) sc_pj ON sc_pj.resource_id = resources.resource_id
                SQL;
        }

        return $joins;
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
        QueryParameters $queryParametersFromRequestParameter,
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
                        AND resources.is_module = 0
                        AND resources.type != 3
                        AND tags.name IN ({$hostGroupPrepareKeys})
                    GROUP BY resources.resource_id
                    UNION ALL
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
                        AND resources.is_module = 0
                        AND resources.type != 3
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
                        AND resources.is_module = 0
                        AND resources.type != 3
                        AND tags.name IN ({$hostCategoryPrepareKeys})
                    GROUP BY resources.resource_id
                    UNION ALL
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
                        AND resources.is_module = 0
                        AND resources.type != 3
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
                    SELECT DISTINCT rtags.resource_id
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
                    SELECT DISTINCT rtags.resource_id
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
     * @param ResourceFilter $filter
     * @param QueryParameters $queryParametersFromRequestParameter
     * @param int[] $accessGroupIds
     * @param bool $useAclCte
     * @param bool $bounded
     * @return string
     */
    private function generateCountResourcesQuery(
        ResourceFilter $filter,
        QueryParameters $queryParametersFromRequestParameter,
        array $accessGroupIds = [],
        bool $useAclCte = false,
        bool $bounded = false,
    ): string {
        // Must be set here because generateCountResourcesQuery() is called independently by the
        // CountResources use case (count endpoint), which never goes through generateFindResourcesQuery().
        $this->sqlRequestTranslator->setConcordanceArray($this->resourceConcordances);

        $tagHeaders = $this->createQueryHeaders($filter, $queryParametersFromRequestParameter);
        if ($useAclCte) {
            // Pre-compute accessible resource IDs via the acl_accessible CTE and drive the COUNT
            // from that small set. Used when ACL selectivity is very low (few accessible resources).
            $queryHeaders = $tagHeaders . $this->buildAclCte($accessGroupIds, prependComma: $tagHeaders !== '');
            $joinCtes = ($tagHeaders !== '' ? ' INNER JOIN cte ON cte.resource_id = resources.resource_id ' : '')
                . ' INNER JOIN acl_accessible ON resources.resource_id = acl_accessible.resource_id ';
        } else {
            $queryHeaders = $tagHeaders;
            $joinCtes = $queryHeaders !== ''
                ? ' INNER JOIN cte ON cte.resource_id = resources.resource_id '
                : '';
        }

        // LEFT JOIN to parent_resource is needed when:
        // - a severity filter is active (addSeveritySubRequest references parent_resource.severity_id), or
        // - the search itself references parent_resource.* columns (h.alias, h.address, parent_alias, parent_status).
        // Translate with accurate concordances; presence of "parent_resource." in the result drives the JOIN decision.
        // When the search uses only resources.* columns the JOIN is skipped, avoiding 960k × parent PK lookups.
        $hasSeverityFilter = $filter->getHostSeverityNames() !== []
            || $filter->getServiceSeverityNames() !== []
            || $filter->getHostSeverityLevels() !== []
            || $filter->getServiceSeverityLevels() !== [];

        $searchSubRequest = null;
        try {
            $searchSubRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        } catch (RequestParametersTranslatorException $exception) {
            throw new RepositoryException(
                message: 'An error occurred while generating the count request',
                previous: $exception
            );
        }

        $needsParentResourceJoin = $hasSeverityFilter
            || ($searchSubRequest !== null && str_contains($searchSubRequest, 'parent_resource.'));

        $resourceTypeHost = self::RESOURCE_TYPE_HOST;

        // When a name search is active without status/state filters, the optimizer
        // picks resources_enabled_type_index (requires row reads for the name column).
        // Force the covering index that includes name to avoid those row reads.
        // With active status/state filters, resources_status_filter_idx is more selective and the optimizer picks it correctly.
        // The index hint is skipped when a CTE join is present: the CTE drives the join and the name index cannot
        // be used for resource_id-based lookups, causing the optimizer to degrade to a full index scan.
        // The index hint is also skipped when a severity filter is active (LEFT JOIN present):
        // the join flips the optimizer's access strategy and FORCE INDEX becomes counterproductive.
        $hasStatusOrStateFilter = ($filter->getStatuses() !== [] && ! $this->areAllStatusesSelected($filter->getStatuses()))
            || $filter->getStates() !== []
            || $filter->getStatusTypes() !== [];

        $hasNameSearch = $searchSubRequest !== null
            && str_contains($searchSubRequest, 'resources.name');

        // FORCE INDEX is only beneficial when the search targets columns actually covered by resources_name_search_idx
        // (enabled, type, is_module, poller_id, name). When the search includes other columns such as alias, address,
        // or output (none of which are in that index), FORCE INDEX forces a secondary-index range scan that still
        // requires a clustered PK lookup for every row — making it slower than letting the optimizer use a ref
        // scan on resources_enabled_type_index. For 8-column OR searches (the search bar) this difference is
        // significant: 27s with FORCE INDEX vs 12s without.
        $isMultiColumnOrSearch = $searchSubRequest !== null
            && (str_contains($searchSubRequest, 'resources.alias')
                || str_contains($searchSubRequest, 'resources.address')
                || str_contains($searchSubRequest, 'resources.output'));

        $indexHint = ($hasNameSearch && ! $isMultiColumnOrSearch && ! $hasStatusOrStateFilter && $accessGroupIds === [] && $queryHeaders === '' && ! $needsParentResourceJoin)
            ? 'FORCE INDEX (`resources_name_search_idx`)'
            : '';

        // When bounded=true, always use the bounded scan: SELECT 1 … LIMIT N stops once N rows match.
        // This applies to all search types — multi-column OR searches need it to avoid 14s+ full scans,
        // and simple searches benefit too (stops at BOUNDED_COUNT_LIMIT instead of scanning all rows).
        // When the returned count equals BOUNDED_COUNT_LIMIT the result is approximate (more rows may exist).
        $useBoundedCount = $bounded;
        $this->lastCountWasBounded = $useBoundedCount;

        $query = $queryHeaders;

        // Open the outer COUNT wrapper for bounded mode (CTEs defined above remain in scope).
        if ($useBoundedCount) {
            $query .= ' SELECT COUNT(*) FROM ( ';
        }

        $innerSelect = $useBoundedCount ? 'SELECT 1' : 'SELECT COUNT(*)';
        $query .= <<<SQL
            {$innerSelect}
            FROM `:dbstg`.`resources` {$indexHint}
            INNER JOIN `:dbstg`.`instances`
                ON `instances`.instance_id = `resources`.poller_id
            {$joinCtes}
            SQL;

        if ($needsParentResourceJoin) {
            $query .= <<<SQL
                LEFT JOIN `:dbstg`.`resources` parent_resource
                    ON parent_resource.id = resources.parent_id
                    AND parent_resource.type = {$resourceTypeHost}
                SQL;
        }

        $query .= ! empty($searchSubRequest) ? $searchSubRequest . ' AND ' : ' WHERE ';

        $query .= <<<'SQL'
            resources.is_module = 0
                AND resources.enabled = 1
                AND resources.type != 3
            SQL;

        if ($filter->getOnlyWithPerformanceData() === true) {
            $query .= ' AND resources.has_graph = 1';
        }

        foreach ($this->extraDataProviders as $provider) {
            $query .= $provider->getSubFilter($filter);
        }

        if ($accessGroupIds !== [] && ! $useAclCte) {
            $query .= $this->buildAclExistsCondition($accessGroupIds);
        }
        // When $useAclCte is true, ACL filtering is handled via the acl_accessible CTE JOIN
        // prepended in the query header — no additional WHERE clause is needed.

        $query .= $this->addResourceParentIdSubRequest($filter, $queryParametersFromRequestParameter);
        $query .= $this->addResourceTypeSubRequest($filter);
        $query .= $this->addResourceStateSubRequest($filter);
        $query .= $this->addResourceStatusSubRequest($filter);
        $query .= $this->addStatusTypeSubRequest($filter);
        $query .= $this->addMonitoringServerSubRequest($filter, $queryParametersFromRequestParameter);
        $query .= $this->addSeveritySubRequest($filter, $queryParametersFromRequestParameter);

        if ($useBoundedCount) {
            // Scan one extra row so we can distinguish "exactly N" from "more than N".
            $limit = self::BOUNDED_COUNT_LIMIT + 1;
            $query .= " LIMIT {$limit} ) AS bounded_count";
        }

        return $query;
    }

    /**
     * @param ResourceFilter $filter
     * @param int[] $accessGroupIds
     *
     * @throws AssertionFailedException
     * @throws CollectionException
     * @throws ConnectionException
     * @throws RepositoryException
     * @throws TransformerException
     * @throws ValueObjectException
     * @throws QueryBuilderException
     * @throws \InvalidArgumentException
     */
    private function find(ResourceFilter $filter, array $accessGroupIds = []): void
    {
        // Decide ACL strategy once; the result is reused by count() via the aclCountCache.
        $useAclCte = $this->shouldUseAclCte($accessGroupIds);

        $queryParametersFromRequestParameter = new QueryParameters();
        $queryFind = $this->generateFindResourcesQuery(
            filter: $filter,
            queryParametersFromRequestParameter: $queryParametersFromRequestParameter,
            accessGroupIds: $accessGroupIds,
            useTagExistsForFilters: true,
            useAclCte: $useAclCte,
        );

        $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
            $this->sqlRequestTranslator->getSearchValues()
        );
        $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameter);

        foreach ($this->connection->iterateAssociative($this->translateDbName($queryFind), $queryParameters) as $record) {
            /** @var array<string, int|string|null> $record */
            $this->resources[] = DbResourceFactory::createFromRecord($record, $this->resourceTypes);
        }

        $iconIds = $this->getIconIdsFromResources();
        $icons = $this->getIconsDataForResources($iconIds);
        $this->completeResourcesWithIcons($icons);

        // Calculate total for pagination meta using the optimised COUNT query.
        // Bounded mode caps the scan at BOUNDED_COUNT_LIMIT rows for multi-column OR searches,
        // preventing 10-15 s full scans when alias/address/output columns are involved.
        $queryParametersForCount = new QueryParameters();
        $queryCount = $this->generateCountResourcesQuery(
            filter: $filter,
            queryParametersFromRequestParameter: $queryParametersForCount,
            accessGroupIds: $accessGroupIds,
            useAclCte: $useAclCte,
            bounded: true,
        );
        $countQueryParameters = SearchRequestParametersTransformer::reverseToQueryParameters(
            $this->sqlRequestTranslator->getSearchValues()
        )->mergeWith($queryParametersForCount);
        $raw = $this->connection->fetchOne($this->translateDbName($queryCount), $countQueryParameters);
        if ($raw === false) {
            throw new RepositoryException('Count query returned no result');
        }
        $total = (int) $raw;
        $this->isLastCountApproximate = $this->lastCountWasBounded && ($total > self::BOUNDED_COUNT_LIMIT);
        $this->sqlRequestTranslator->getRequestParameters()->setTotal(
            $this->isLastCountApproximate ? self::BOUNDED_COUNT_LIMIT : $total
        );
    }

    /**
     * @param ResourceFilter $filter
     * @param bool $allPages
     * @param int[] $accessGroupIds
     * @throws CollectionException
     * @throws ConnectionException
     * @throws RepositoryException
     * @throws TransformerException
     * @throws ValueObjectException
     * @throws QueryBuilderException
     * @throws \InvalidArgumentException
     * @return CountResult
     */
    private function count(
        ResourceFilter $filter,
        bool $allPages = false,
        array $accessGroupIds = [],
    ): CountResult {
        if ($allPages) {
            // For a count, there isn't pagination we limit the number of results
            // page is always 1 and limit is the maxResults in case of an export
            $this->sqlRequestTranslator->getRequestParameters()->setPage(1);
            $this->sqlRequestTranslator->getRequestParameters()->setLimit(0);
        }

        // When exporting all pages (allPages = true) the exact count is required to drive the export loop.
        // For regular paginated count requests, bounded mode caps expensive multi-column OR scans.
        $bounded = ! $allPages;
        $queryParametersFromRequestParameter = new QueryParameters();
        $queryCount = $this->generateCountResourcesQuery(
            filter: $filter,
            queryParametersFromRequestParameter: $queryParametersFromRequestParameter,
            accessGroupIds: $accessGroupIds,
            useAclCte: $this->shouldUseAclCte($accessGroupIds),
            bounded: $bounded,
        );

        $queryParametersFromSearchValues = SearchRequestParametersTransformer::reverseToQueryParameters(
            $this->sqlRequestTranslator->getSearchValues()
        );

        $queryParameters = $queryParametersFromSearchValues->mergeWith($queryParametersFromRequestParameter);

        $raw = $this->connection->fetchOne($this->translateDbName($queryCount), $queryParameters);
        if ($raw === false) {
            throw new RepositoryException('Count query returned no result');
        }
        $result = (int) $raw;
        $isApproximate = $this->lastCountWasBounded && ($result > self::BOUNDED_COUNT_LIMIT);

        return new CountResult(
            count: $isApproximate ? self::BOUNDED_COUNT_LIMIT : $result,
            isApproximate: $isApproximate,
        );
    }

    /**
     * @param string $query
     * @param QueryParameters $queryParametersFromRequestParameters
     *
     * @throws CollectionException
     * @throws ConnectionException
     * @throws RepositoryException
     * @throws TransformerException
     * @return \Traversable<ResourceEntity>
     */
    private function iterate(
        string $query,
        QueryParameters $queryParametersFromRequestParameters,
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
            static fn (ResourceEntity $resource): bool => $resource->getIcon() !== null
        );

        return array_map(
            static fn (ResourceEntity $resource): ?int => $resource->getIcon()?->getId(),
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
            static fn (ResourceEntity $resource): bool => $resource->getSeverity() !== null
        );

        return array_map(
            static fn (ResourceEntity $resource): ?int => $resource->getSeverity()?->getIcon()?->getId(),
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
        QueryParameters $queryParametersFromRequestParameter,
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
            // Use two non-correlated IN subqueries instead of a correlated EXISTS with OR.
            // MariaDB materializes non-correlated subqueries as constant lists, then uses the
            // resources_severities_severity_id_fk index for direct seeks on resources.severity_id —
            // avoiding a full table scan through all enabled resources.
            $typeList = implode(', ', $filteredTypes);
            $innerWhere = 'sev_filter.type IN (' . $typeList . ')';

            if ($filteredNames !== []) {
                $innerWhere .= ' AND sev_filter.name IN (' . implode(', ', $filteredNames) . ')';
            }

            if ($filteredLevels !== []) {
                $innerWhere .= ' AND sev_filter.level IN (' . implode(', ', $filteredLevels) . ')';
            }

            $subRequest = ' AND (
                resources.severity_id IN (
                    SELECT sev_filter.severity_id FROM `:dbstg`.`severities` sev_filter
                    WHERE ' . $innerWhere . '
                )
                OR parent_resource.severity_id IN (
                    SELECT sev_filter.severity_id FROM `:dbstg`.`severities` sev_filter
                    WHERE ' . $innerWhere . '
                )
            )';
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
        QueryParameters $queryParametersFromRequestParameter,
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
     * Returns true when all 8 possible status constants are present in $statuses,
     * which means the filter has zero selectivity (equivalent to no filter).
     *
     * @param array<string> $statuses
     */
    private function areAllStatusesSelected(array $statuses): bool
    {
        $allStatuses = [
            ResourceFilter::STATUS_OK,
            ResourceFilter::STATUS_UP,
            ResourceFilter::STATUS_WARNING,
            ResourceFilter::STATUS_DOWN,
            ResourceFilter::STATUS_CRITICAL,
            ResourceFilter::STATUS_UNREACHABLE,
            ResourceFilter::STATUS_UNKNOWN,
            ResourceFilter::STATUS_PENDING,
        ];

        return \count($statuses) >= \count($allStatuses)
            && empty(array_diff($allStatuses, $statuses));
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
        if (! empty($filter->getStatuses()) && ! $this->areAllStatusesSelected($filter->getStatuses())) {
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
        QueryParameters $queryParametersFromRequestParameter,
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
                foreach ($iconIds as $indexIconIds => $indexIconIdsValue) {
                    $queryParameter = null;
                    $queryParameterName = "icon_id_{$indexIconIds}";
                    $iconId = $indexIconIdsValue;
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
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'An error occurred while fetching icons data for resources',
                context: ['iconIds' => $iconIds],
                previous: $exception
            );
        }
    }
}
