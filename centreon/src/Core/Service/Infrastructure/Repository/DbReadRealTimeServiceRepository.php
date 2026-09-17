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

namespace Core\Service\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\Interfaces\NormalizerInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;
use Core\Service\Application\Repository\ReadRealTimeServiceRepositoryInterface;
use Core\Service\Domain\Model\ServiceStatusesCount;

/**
 * @phpstan-type _ServiceStatuses array{
 *     array{
 *       id: int,
 *       name: string,
 *       status: int
 *     }
 * }|array{}
 */
class DbReadRealTimeServiceRepository extends AbstractRepositoryRDB implements ReadRealTimeServiceRepositoryInterface
{
    use SqlMultipleBindTrait;

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
    public function findStatusesByRequestParameters(RequestParametersInterface $requestParameters): ServiceStatusesCount
    {
        $sqlTranslator = $this->prepareSqlRequestParametersTranslator($requestParameters);
        $request = $this->returnBaseQuery();
        try {
            $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        } catch (RequestParametersTranslatorException $exception) {
            throw new RepositoryException(
                'Error translating search parameters for service statuses',
                [
                    'requestParameters' => $requestParameters->toArray(),
                ],
                $exception,
            );
        }
        $request .= $search !== null ? ' AND ' : ' WHERE ';
        $request .= 'services.type = 0 AND services.enabled = 1';
        $request .= $this->getStatesCondition($requestParameters);

        $request .= ' GROUP BY services.id, services.name, services.status ';

        $sort = $sqlTranslator->translateSortParameterToSql();
        $request .= $sort ?? ' ORDER BY services.name ASC';

        try {
            $queryParameters = SearchRequestParametersTransformer::reverseToQueryParameters(
                $sqlTranslator->getSearchValues(),
            );
        } catch (RequestParametersTranslatorException $exception) {
            throw new RepositoryException(
                'Error translating query parameters for service statuses',
                [
                    'requestParameters' => $requestParameters->toArray(),
                ],
                $exception,
            );
        }

        try {
            /** @var _ServiceStatuses $services */
            $services = $this->db->fetchAllAssociative(
                $this->translateDbName($request),
                $queryParameters
            );
        } catch (ConnectionException $exception) {
            throw new RepositoryException(
                'Error fetching service statuses from database',
                [
                    'requestParameters' => $requestParameters->toArray(),
                ],
                $exception,
            );
        }

        return $this->createServiceStatusesCountFromRecord($services);
    }

    /**
     * @inheritDoc
     */
    public function findStatusesByRequestParametersAndAccessGroupIds(
        RequestParametersInterface $requestParameters,
        array $accessGroupIds,
    ): ServiceStatusesCount {
        if ($accessGroupIds === []) {
            return $this->createServiceStatusesCountFromRecord([]);
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group');

        $sqlTranslator = $this->prepareSqlRequestParametersTranslator($requestParameters);

        $request = $this->returnBaseQuery();

        // handle ACLs
        $request .= <<<'SQL'
                INNER JOIN `:dbstg`.centreon_acl acls
                    ON acls.service_id = services.id
                    AND acls.host_id = services.parent_id
            SQL;

        try {
            $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        } catch (RequestParametersTranslatorException $exception) {
            throw new RepositoryException(
                'Error translating search parameters for service statuses with access groups',
                [
                    'accessGroupIds' => $accessGroupIds,
                    'requestParameters' => $requestParameters->toArray(),
                ],
                $exception,
            );
        }

        $request .= $search !== null ? ' AND ' : ' WHERE ';
        $request .= "services.type = 0 AND services.enabled = 1 AND acls.group_id IN ({$bindQuery})";
        $request .= $this->getStatesCondition($requestParameters);

        $request .= ' GROUP BY services.id, services.name, services.status ';

        $sort = $sqlTranslator->translateSortParameterToSql();

        $request .= $sort ?? ' ORDER BY services.name ASC';

        try {
            $queryParameters = SearchRequestParametersTransformer::reverseToQueryParameters(
                $sqlTranslator->getSearchValues(),
            );
        } catch (RequestParametersTranslatorException $exception) {
            throw new RepositoryException(
                'Error translating query parameters for service statuses with access groups',
                [
                    'accessGroupIds' => $accessGroupIds,
                    'requestParameters' => $requestParameters->toArray(),
                ],
                $exception,
            );
        }

        try {
            foreach ($bindValues as $key => $value) {
                $queryParameters->add($key, QueryParameter::int($key, (int) $value));
            }

            /** @var _ServiceStatuses $services */
            $services = $this->db->fetchAllAssociative(
                $this->translateDbName($request),
                $queryParameters,
            );
        } catch (ConnectionException|ValueObjectException $exception) {
            throw new RepositoryException(
                'Error fetching service statuses with access groups from database',
                [
                    'accessGroupIds' => $accessGroupIds,
                    'requestParameters' => $requestParameters->toArray(),
                ],
                $exception,
            );
        }

        return $this->createServiceStatusesCountFromRecord($services);
    }

    /**
     * @inheritDoc
     */
    public function findUniqueServiceNamesByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $selectSqlTranslator = $this->prepareSqlRequestParametersTranslator($requestParameters);
        $countSqlTranslator = $this->prepareSqlRequestParametersTranslator($requestParameters);

        $selectRequest = $this->findServiceNamesQuery($selectSqlTranslator, false);
        $countRequest = $this->findServiceNamesQuery($countSqlTranslator, true);

        $selectStatement = $this->db->prepare($this->translateDbName($selectRequest));
        $selectSqlTranslator->bindSearchValues($selectStatement);
        $selectStatement->execute();

        $countStatement = $this->db->prepare($this->translateDbName($countRequest));
        $countSqlTranslator->bindSearchValues($countStatement);
        $countStatement->execute();

        $serviceNames = $selectStatement->fetchAll(\PDO::FETCH_COLUMN, 0);
        $countResult = $countStatement->fetchAll(\PDO::FETCH_COLUMN, 0);
        $numberOfRows = $countResult ? current($countResult) : 0;

        $countSqlTranslator->setNumberOfRows($numberOfRows);

        return $serviceNames;
    }

    /**
     * @inheritDoc
     */
    public function findUniqueServiceNamesByRequestParametersAndAccessGroupIds(
        RequestParametersInterface $requestParameters,
        array $accessGroupIds,
    ): array {
        if ($accessGroupIds === []) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':acl_group');

        $selectSqlTranslator = $this->prepareSqlRequestParametersTranslator($requestParameters);
        $countSqlTranslator = $this->prepareSqlRequestParametersTranslator($requestParameters);

        $selectRequest = $this->findServiceNamesQuery($selectSqlTranslator, false, $accessGroupIds, $bindQuery);
        $countRequest = $this->findServiceNamesQuery($countSqlTranslator, true, $accessGroupIds, $bindQuery);

        $selectStatement = $this->db->prepare($this->translateDbName($selectRequest));
        $selectSqlTranslator->bindSearchValues($selectStatement);
        foreach ($bindValues as $token => $value) {
            $selectStatement->bindValue($token, $value, \PDO::PARAM_INT);
        }
        $selectStatement->execute();

        $countStatement = $this->db->prepare($this->translateDbName($countRequest));
        $countSqlTranslator->bindSearchValues($countStatement);
        foreach ($bindValues as $token => $value) {
            $countStatement->bindValue($token, $value, \PDO::PARAM_INT);
        }
        $countStatement->execute();

        $serviceNames = $selectStatement->fetchAll(\PDO::FETCH_COLUMN, 0);
        $countResult = $countStatement->fetchAll(\PDO::FETCH_COLUMN, 0);
        $numberOfRows = $countResult ? current($countResult) : 0;

        $countSqlTranslator->setNumberOfRows($numberOfRows);

        return $serviceNames;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $serviceId, int $hostId): bool
    {
        $query = <<<'SQL'
                SELECT 1
                FROM `:dbstg`.services
                WHERE service_id = :serviceId
                    AND host_id = :hostId
            SQL;

        try {
            $raw = $this->db->fetchOne(
                $this->translateDbName($query),
                QueryParameters::create([
                    QueryParameter::int('serviceId', $serviceId),
                    QueryParameter::int('hostId', $hostId),
                ])
            );

            return (bool) $raw;
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                sprintf(
                    'Error checking existence of service %d on host %d',
                    $serviceId,
                    $hostId
                ),
                [
                    'serviceId' => $serviceId,
                    'hostId' => $hostId,
                ],
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function existsByDescription(int $metaServiceId): array|false
    {
        $query = <<<'SQL'
                SELECT service_id, host_id
                FROM `:dbstg`.services s
                WHERE s.description = :metaId
            SQL;

        try {
            return $this->db->fetchAssociative(
                $this->translateDbName($query),
                QueryParameters::create([
                    QueryParameter::string('metaId', "meta_{$metaServiceId}"),
                ])
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                sprintf(
                    'Error checking existence of meta service as service with description: meta_%d',
                    $metaServiceId
                ),
                [
                    'metaServiceId' => $metaServiceId,
                ],
                $e
            );
        }
    }

    /**
     * @param SqlRequestParametersTranslator $sqlTranslator
     * @param bool $calculateNumberOfRows
     * @param int[] $accessGroupIds
     * @param string $aclBindQuery
     *
     * @return string
     */
    private function findServiceNamesQuery(
        SqlRequestParametersTranslator $sqlTranslator,
        bool $calculateNumberOfRows,
        array $accessGroupIds = [],
        string $aclBindQuery = '',
    ): string {
        $search = $sqlTranslator->translateSearchParameterToSql();
        $typeSearch = $search !== null ? ' AND services.type = 0 ' : ' WHERE services.type = 0 ';
        $sort = $sqlTranslator->translateSortParameterToSql() ?? ' ORDER BY services.name ASC';
        $aclJoin = '';
        $aclSearch = '';

        if ($calculateNumberOfRows) {
            $select = ' COUNT(*) OVER(), services.name AS `name`';
            $limit = '';
        } else {
            $select = ' services.name AS `name`';
            $limit = $sqlTranslator->translatePaginationToSql();
        }

        if ($accessGroupIds !== []) {
            $aclJoin = <<<'SQL'
                    INNER JOIN `:dbstg`.centreon_acl acls
                        ON acls.host_id = services.parent_id
                        AND acls.service_id = services.id
                SQL;
            $aclSearch = sprintf(' AND acls.group_id IN (%s) ', $aclBindQuery);
        }

        return sprintf(
            <<<'SQL'
                    SELECT %s
                    FROM `:dbstg`.resources AS services
                    INNER JOIN `:dbstg`.resources AS hosts
                        ON hosts.id = services.parent_id
                    LEFT JOIN `:dbstg`.resources_tags AS rtags_host_groups
                        ON hosts.resource_id = rtags_host_groups.resource_id
                    LEFT JOIN `:dbstg`.tags host_groups
                        ON rtags_host_groups.tag_id = host_groups.tag_id
                        AND host_groups.type = 1
                    LEFT JOIN `:dbstg`.resources_tags AS rtags_host_categories
                        ON hosts.resource_id = rtags_host_categories.resource_id
                    LEFT JOIN `:dbstg`.tags host_categories
                        ON rtags_host_categories.tag_id = host_categories.tag_id
                        AND host_categories.type = 3
                    LEFT JOIN `:dbstg`.resources_tags AS rtags_service_groups
                        ON services.resource_id = rtags_service_groups.resource_id
                    LEFT JOIN `:dbstg`.tags service_groups
                        ON rtags_service_groups.tag_id = service_groups.tag_id
                        AND service_groups.type = 0
                    LEFT JOIN `:dbstg`.resources_tags AS rtags_service_categories
                        ON services.resource_id = rtags_service_categories.resource_id
                    LEFT JOIN `:dbstg`.tags service_categories
                        ON rtags_service_categories.tag_id = service_categories.tag_id
                        AND service_categories.type = 2
                    %s
                    %s
                    %s
                        AND services.enabled = 1
                    GROUP BY services.name
                    %s
                    %s
                SQL,
            $select,
            $aclJoin,
            $search,
            $typeSearch . $aclSearch,
            $sort,
            $limit
        );
    }

    /**
     * @return string
     */
    private function returnBaseQuery(): string
    {
        // tags 0=servicegroup, 1=hostgroup, 2=servicecategory, 3=hostcategory
        return <<<'SQL'
                SELECT
                    services.id AS `id`,
                    services.name AS `name`,
                    services.status AS `status`
                FROM `:dbstg`.resources AS services
                INNER JOIN `:dbstg`.resources AS hosts
                    ON hosts.id = services.parent_id
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_groups
                    ON hosts.resource_id = rtags_host_groups.resource_id
                LEFT JOIN `:dbstg`.tags host_groups
                    ON rtags_host_groups.tag_id = host_groups.tag_id
                    AND host_groups.type = 1
                LEFT JOIN `:dbstg`.resources_tags AS rtags_host_categories
                    ON hosts.resource_id = rtags_host_categories.resource_id
                LEFT JOIN `:dbstg`.tags host_categories
                    ON rtags_host_categories.tag_id = host_categories.tag_id
                    AND host_categories.type = 3
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_groups
                    ON services.resource_id = rtags_service_groups.resource_id
                LEFT JOIN `:dbstg`.tags service_groups
                    ON rtags_service_groups.tag_id = service_groups.tag_id
                    AND service_groups.type = 0
                LEFT JOIN `:dbstg`.resources_tags AS rtags_service_categories
                    ON services.resource_id = rtags_service_categories.resource_id
                LEFT JOIN `:dbstg`.tags service_categories
                    ON rtags_service_categories.tag_id = service_categories.tag_id
                    AND service_categories.type = 2
            SQL;
    }

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return SqlRequestParametersTranslator
     */
    private function prepareSqlRequestParametersTranslator(
        RequestParametersInterface $requestParameters,
    ): SqlRequestParametersTranslator {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'services.name',
            'status' => 'services.status',
            'host.name' => 'hosts.name',
            'host.id' => 'hosts.id',
            'host_category.name' => 'host_categories.name',
            'host_category.id' => 'host_categories.id',
            'host_group.name' => 'host_groups.name',
            'host_group.id' => 'host_groups.id',
            'service_group.name' => 'service_groups.name',
            'service_group.id' => 'service_groups.id',
            'service_category.name' => 'service_categories.name',
            'service_category.id' => 'service_categories.id',
        ]);

        $sqlTranslator->addNormalizer(
            'status',
            new class () implements NormalizerInterface {
                /**
                 * @inheritDoc
                 */
                public function normalize($valueToNormalize)
                {
                    switch (mb_strtoupper((string) $valueToNormalize)) {
                        case 'OK':
                            $code = ServiceStatusesCount::STATUS_OK;
                            break;
                        case 'WARNING':
                            $code = ServiceStatusesCount::STATUS_WARNING;
                            break;
                        case 'CRITICAL':
                            $code = ServiceStatusesCount::STATUS_CRITICAL;
                            break;
                        case 'UNKNOWN':
                            $code = ServiceStatusesCount::STATUS_UNKNOWN;
                            break;
                        case 'PENDING':
                            $code = ServiceStatusesCount::STATUS_PENDING;
                            break;
                        default:
                            throw new RequestParametersTranslatorException('Status provided not handled');
                    }

                    return $code;
                }
            }
        );

        return $sqlTranslator;
    }

    /**
     * @param _ServiceStatuses $record
     *
     * @return ServiceStatusesCount
     */
    private function createServiceStatusesCountFromRecord(array $record): ServiceStatusesCount
    {
        return new ServiceStatusesCount(
            $this->countStatuses($record, ServiceStatusesCount::STATUS_OK),
            $this->countStatuses($record, ServiceStatusesCount::STATUS_WARNING),
            $this->countStatuses($record, ServiceStatusesCount::STATUS_UNKNOWN),
            $this->countStatuses($record, ServiceStatusesCount::STATUS_CRITICAL),
            $this->countStatuses($record, ServiceStatusesCount::STATUS_PENDING)
        );
    }

    /**
     * @param _ServiceStatuses $record
     * @param int $statusCode
     *
     * @return int
     */
    private function countStatuses(array $record, int $statusCode): int
    {
        return count(
            array_filter(
                $record,
                (fn (array $service) => $service['status'] === $statusCode)
            )
        );
    }

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return string
     */
    private function getStatesCondition(RequestParametersInterface $requestParameters): string
    {
        $states = json_decode($requestParameters->getExtraParameter('states') ?? '', true);
        $stateConditions = [];
        if (is_array($states) && $states !== []) {
            $sqlStateCatalog = [
                ResourceFilter::STATE_RESOURCES_PROBLEMS => '(services.status != 0 AND services.status != 4)',
                ResourceFilter::STATE_UNHANDLED_PROBLEMS => '(services.status != 0 AND services.status != 4 AND services.acknowledged = 0 AND services.in_downtime = 0 AND services.status_confirmed = 1)',
                ResourceFilter::STATE_ACKNOWLEDGED => 'services.acknowledged = 1',
                ResourceFilter::STATE_IN_DOWNTIME => 'services.in_downtime = 1',
                ResourceFilter::STATE_IN_FLAPPING => 'services.flapping = 1',
            ];
            // Filter out invalid states to prevent undefined array key errors
            $validStates = array_intersect($states, array_keys($sqlStateCatalog));
            $stateConditions = array_map(
                fn (string $state): string => $sqlStateCatalog[$state],
                $validStates
            );
        }

        return $stateConditions === [] ? '' : ' AND (' . implode(' OR ', $stateConditions) . ') ';
    }
}
