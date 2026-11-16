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

namespace Centreon\Infrastructure\Monitoring\Metric;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Centreon\Domain\Monitoring\Service;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Metric\Domain\TimeSeries\StatusTimeline;
use Core\Metric\Infrastructure\Graph\TimeSeriesGraphReader;

/**
 * Graphs read from VictoriaMetrics (option metrics_data_source = victoria_metrics): the graph
 * definition (metrics, virtual metrics, templates, curve settings, colours, legends) still comes from
 * CentreonGraphNg and the databases, only the values come from VictoriaMetrics, so that the answer
 * has the shape of MetricRepositoryLegacy.
 */
final class MetricRepositoryVictoriaMetrics extends AbstractRepositoryRDB implements MetricRepositoryInterface
{
    private ?ContactInterface $contact = null;

    /**
     * @param DatabaseConnection $db
     * @param TimeSeriesGraphReader $graphReader
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly TimeSeriesGraphReader $graphReader,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function setContact(ContactInterface $contact): MetricRepositoryInterface
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function findMetricsByService(Service $service, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        [$hostId, $serviceId] = self::extractIds($service);

        // CentreonGraphNg reads the configuration database through this global (getMyServiceField)
        global $pearDB;
        $pearDB = new \CentreonDB('centreon', 3);

        $graph = new \CentreonGraphNg($this->contact?->getId());
        $graph->setDataFetcher($this->graphReader->fetchGraphData(...));
        $graph->addServiceMetrics($hostId, $serviceId);

        return $graph->getGraph($start->getTimestamp(), $end->getTimestamp());
    }

    /**
     * @inheritDoc
     */
    public function findStatusByService(Service $service, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        [$hostId, $serviceId] = self::extractIds($service);

        return StatusTimeline::fromRows($this->graphReader->findStatusRows(
            $this->findIndexId($hostId, $serviceId),
            $start->getTimestamp(),
            $end->getTimestamp()
        ));
    }

    /**
     * @param Service $service
     *
     * @throws \InvalidArgumentException
     *
     * @return array{int, int}
     */
    private static function extractIds(Service $service): array
    {
        $hostId = $service->getHost()?->getId();
        $serviceId = $service->getId();
        if ($hostId === null || $serviceId === null) {
            throw new \InvalidArgumentException('The service and its host must be identified');
        }

        return [$hostId, $serviceId];
    }

    /**
     * Same error as CentreonGraphStatus::getIndexId when the service has no graph.
     *
     * @throws \OutOfRangeException
     */
    private function findIndexId(int $hostId, int $serviceId): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT id FROM `:dbstg`.`index_data`
                WHERE host_id = :host_id AND service_id = :service_id
                SQL
        ));
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();
        $indexId = $statement->fetchColumn();
        if ($indexId === false) {
            throw new \OutOfRangeException();
        }

        return (int) $indexId;
    }
}
