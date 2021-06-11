<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Infrastructure\Monitoring\MonitoringResource\Repository;

use Centreon\Domain\Security\AccessGroup;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Centreon\Domain\Monitoring\MonitoringResource\Interfaces\MonitoringResourceRepositoryInterface;
use Centreon\Infrastructure\Monitoring\MonitoringResource\Repository\Provider\ProviderInterface;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Centreon\Infrastructure\Monitoring\MonitoringResource\Repository\Model\MonitoringResourceFactoryRdb;

/**
 * Database repository for the real time monitoring of services and host.
 *
 * @package Centreon\Infrastructure\Monitoring\MonitoringResource\Repository
 */
final class MonitoringResourceRepositoryRDB extends AbstractRepositoryDRB implements MonitoringResourceRepositoryInterface
{
    /**
     * @var SqlRequestParametersTranslator
     */
    private $sqlRequestTranslator;

    /**
     * @var ProviderInterface[]
     */
    private $providers = [];

    /**
     * @var ContactInterface
     */
    private $contact;

    /**
     * @var AccessGroupRepositoryInterface
     */
    private $accessGroupRepository;

    /**
     * @var ResourceFilter
     */
    private $filter;

    /**
     * @var array Association of resource search parameters
     */
    private $resourceConcordances = [
        'id' => 'resource.id',
        'name' => 'resource.name',
        'alias' => 'resource.alias',
        'fqdn' => 'resource.fqdn',
        'type' => 'resource.type',
        'status_code' => 'resource.status_code',
        'status' => 'resource.status_name',
        'status_severity_code' => 'resource.status_severity_code',
        'action_url' => 'resource.action_url',
        'parent_name' => 'resource.parent_name',
        'parent_alias' => 'resource.parent_alias',
        'parent_status' => 'resource.parent_status_name',
        'severity_level' => 'resource.severity_level',
        'in_downtime' => 'resource.in_downtime',
        'acknowledged' => 'resource.acknowledged',
        'last_status_change' => 'resource.last_status_change',
        'tries' => 'resource.tries',
        'last_check' => 'resource.last_check',
        'monitoring_server_name' => 'resource.monitoring_server_name',
        'information' => 'resource.information',
    ];

    /**
     * @param DatabaseConnection $pdo
     */
    public function __construct(DatabaseConnection $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Initialized by the dependency injector.
     *
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function setSqlRequestTranslator(SqlRequestParametersTranslator $sqlRequestTranslator): void
    {
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT)
            ->setConcordanceErrorMode(RequestParameters::CONCORDANCE_ERRMODE_SILENT);
    }

    /**
     * @param \Traversable $providers
     * @return void
     */
    public function setProviders(\Traversable $providers): void
    {
        if (count($providers) === 0) {
            throw new \InvalidArgumentException(
                _('You must at least add one resource provider')
            );
        }

        $this->providers = iterator_to_array($providers);
    }

    /**
     * @inheritDoc
     */
    public function findAll(ResourceFilter $filter): array
    {
        return $this->findAllRequest($filter, null);
    }

    /**
     * @inheritDoc
     */
    public function findAllByContact(ResourceFilter $filter, ContactInterface $contact): array
    {
        return $this->findAllRequest($filter, $contact->getId());
    }

    /**
     * Find all monitoring resources by contact id
     *
     * @param ResourceFilter $filter
     * @param integer|null $contactId
     * @return array
     * @throws AssertionFailedException
     * @throws \InvalidArgumentException
     */
    private function findAllRequest(ResourceFilter $filter, ?int $contactId): array
    {
        $monitoringResources = [];
        $accessGroups = [];

        if ($contactId !== null) {
            $accessGroups = $this->accessGroup->findAllByContact($this->contact);

            if (count($accessGroups) === 0) {
                return [];
            }
        }

        $collector = new StatementCollector();
        $request = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '
            . 'resource.id, resource.type, resource.name, resource.alias, resource.fqdn, '
            . 'resource.host_id, resource.service_id, '
            . 'resource.status_code, resource.status_name, resource.status_severity_code, ' // status
            . 'resource.icon_name, resource.icon_url, ' // icon
            . 'resource.command_line, resource.timezone, '
            . 'resource.parent_id, resource.parent_name, resource.parent_type, ' // parent
            . 'resource.parent_alias, resource.parent_fqdn, ' // parent
            . 'resource.parent_icon_name, resource.parent_icon_url, ' // parent icon
            . 'resource.action_url, resource.notes_url, resource.notes_label, ' // external urls
            . 'resource.monitoring_server_name, resource.monitoring_server_id, ' // monitoring server
            // parent status
            . 'resource.parent_status_code, resource.parent_status_name, resource.parent_status_severity_code, '
            . 'resource.flapping, resource.percent_state_change, '
            . 'resource.severity_level, ' // severity
            . 'resource.in_downtime, resource.acknowledged, '
            . 'resource.active_checks, resource.passive_checks,'
            . 'resource.last_status_change, '
            . 'resource.last_notification, resource.notification_number, '
            . 'resource.tries, resource.last_check, resource.next_check, '
            . 'resource.information, resource.performance_data, '
            . 'resource.execution_time, resource.latency, '
            . 'resource.notification_enabled '
            . 'FROM (';

        $subRequests = [];
        foreach ($this->providers as $provider) {
            if ($contactId !== null) {
                $accessGroupIds = array_map(
                    function ($accessGroup) {
                        return $accessGroup->getId();
                    },
                    $accessGroups
                );
                $subRequest = $provider->prepareSubQueryWithAcl($filter, $collector, $accessGroupIds);
            } else {
                $subRequest = $provider->prepareSubQueryWithoutAcl($filter, $collector);
            }
            $subRequests[] = '(' . $subRequest . ')';
        }

        if (!$subRequests) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal(0);

            return [];
        }

        $request .= implode('UNION ALL ', $subRequests);
        unset($subRequests);

        $request .= ') AS `resource`';

        // apply the host group filter to SQL query
        if ($filter->getHostgroupIds()) {
            $groupList = [];

            foreach ($filter->getHostgroupIds() as $index => $groupId) {
                $key = ":resourceHostgroupId_{$index}";
                $groupList[] = $key;
                $collector->addValue($key, $groupId, \PDO::PARAM_INT);
            }

            $request .= ' INNER JOIN `:dbstg`.`hosts_hostgroups` AS hhg
                ON hhg.host_id = resource.host_id
                AND hhg.hostgroup_id IN (' . implode(', ', $groupList) . ') ';
        }

        /**
         * If we specify that user only wants resources with available performance datas.
         * Then only resources with existing metrics referencing index_data services will be returned.
         */
        if ($filter->getOnlyWithPerformanceData() === true) {
            $request .= ' INNER JOIN `:dbstg`.index_data AS idata
                  ON idata.host_id = resource.parent_id
                  AND idata.service_id = resource.id
                  AND resource.type = "service"
                INNER JOIN `:dbstg`.metrics AS m
                  ON m.index_id = idata.id
                  AND m.hidden = "0" ';
        }

        // Search
        $this->sqlRequestTranslator->setConcordanceArray($this->resourceConcordances);
        try {
            $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();
        } catch (RequestParametersTranslatorException $ex) {
            throw new RepositoryException($ex->getMessage(), 0, $ex);
        }

        $request .= $searchRequest !== null ? $searchRequest : '';

        foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
            $collector->addValue($key, current($data), key($data));
        }

        // Sort
        $request .= $this->sqlRequestTranslator->translateSortParameterToSql()
            ?: ' ORDER BY resource.status_name DESC, resource.name ASC';

        // Pagination
        $request .= $this->sqlRequestTranslator->translatePaginationToSql();

        $statement = $this->db->prepare(
            $this->translateDbName($request)
        );
        $collector->bind($statement);

        $statement->execute();

        $this->sqlRequestTranslator->getRequestParameters()->setTotal(
            (int)$this->db->query('SELECT FOUND_ROWS()')->fetchColumn()
        );

        while (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $monitoringResources[] = MonitoringResourceFactoryRdb::create($record);
        }
        return $monitoringResources;
    }

    /**
     * {@inheritDoc}
     */
    public function extractResourcesWithGraphData(array $resources): array
    {
        foreach ($this->providers as $provider) {
            $resources = $provider->excludeResourcesWithoutMetrics($resources);
        }

        return $resources;
    }
}
