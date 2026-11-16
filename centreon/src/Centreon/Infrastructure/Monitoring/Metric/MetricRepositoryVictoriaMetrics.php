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
use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\StatusTimeline;
use Core\Metric\Domain\TimeSeries\TimeGrid;
use Core\Metric\Infrastructure\Graph\RrdtoolGraphDataBuilder;

/**
 * Graphs read from VictoriaMetrics (option metrics_data_source = victoria_metrics): the graph
 * definition (metrics, virtual metrics, templates, curve settings, colours, legends) still comes from
 * CentreonGraphNg and the databases, only the values come from VictoriaMetrics, so that the answer
 * has the shape of MetricRepositoryLegacy.
 */
final class MetricRepositoryVictoriaMetrics extends AbstractRepositoryRDB implements MetricRepositoryInterface
{
    /** Step used when the check interval of the service is unknown, in seconds. */
    private const DEFAULT_CHECK_INTERVAL = 60;

    private ?ContactInterface $contact = null;

    /**
     * @param DatabaseConnection $db
     * @param ReadMetricTimeSeriesRepositoryInterface $timeSeriesRepository
     * @param RrdtoolGraphDataBuilder $graphDataBuilder
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly ReadMetricTimeSeriesRepositoryInterface $timeSeriesRepository,
        private readonly RrdtoolGraphDataBuilder $graphDataBuilder,
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
        $minimumStep = $this->findCheckInterval($hostId, $serviceId);

        // CentreonGraphNg reads the configuration database through this global (getMyServiceField)
        global $pearDB;
        $pearDB = new \CentreonDB('centreon', 3);

        $graph = new \CentreonGraphNg($this->contact?->getId());
        $graph->setDataFetcher(
            fn (array $definition): array => $this->graphDataBuilder->build(
                $definition,
                $minimumStep,
                $this->findDataSourceTypes(array_column($definition['metrics'], 'id'))
            )
        );
        $graph->addServiceMetrics($hostId, $serviceId);

        return $graph->getGraph($start->getTimestamp(), $end->getTimestamp());
    }

    /**
     * @inheritDoc
     */
    public function findStatusByService(Service $service, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        [$hostId, $serviceId] = self::extractIds($service);
        $indexId = $this->findIndexId($hostId, $serviceId);
        $grid = TimeGrid::forPeriod(
            $start->getTimestamp(),
            $end->getTimestamp(),
            $this->findCheckInterval($hostId, $serviceId)
        );
        $values = $this->timeSeriesRepository->findStatusSeries($indexId, $grid);

        $rows = [];
        foreach ($grid->getTimestamps() as $index => $timestamp) {
            $value = $values[$index] ?? NAN;
            $rows[] = [(string) $timestamp, is_finite($value) ? $value : null];
        }

        return StatusTimeline::fromRows($rows);
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
     * @param list<int> $metricIds
     *
     * @return array<int, MetricDataSourceType>
     */
    private function findDataSourceTypes(array $metricIds): array
    {
        if ($metricIds === []) {
            return [];
        }
        $placeholders = [];
        foreach (array_values($metricIds) as $position => $metricId) {
            $placeholders[":metric_{$position}"] = $metricId;
        }
        $statement = $this->db->prepare($this->translateDbName(sprintf(
            'SELECT metric_id, data_source_type FROM `:dbstg`.`metrics` WHERE metric_id IN (%s)',
            implode(', ', array_keys($placeholders))
        )));
        foreach ($placeholders as $placeholder => $metricId) {
            $statement->bindValue($placeholder, $metricId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $types = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            /** @var array{metric_id: int|string, data_source_type: string|null} $row */
            $types[(int) $row['metric_id']] = MetricDataSourceType::fromColumn($row['data_source_type']);
        }

        return $types;
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

    /**
     * Normal check interval of the service in seconds (real time value, in interval_length units).
     */
    private function findCheckInterval(int $hostId, int $serviceId): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT s.check_interval, o.`value` AS interval_length
                FROM `:dbstg`.`services` s
                LEFT JOIN `:db`.`options` o ON o.`key` = 'interval_length'
                WHERE s.host_id = :host_id AND s.service_id = :service_id
                SQL
        ));
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (! is_array($row) || ! is_numeric($row['check_interval'] ?? null)) {
            return self::DEFAULT_CHECK_INTERVAL;
        }
        $intervalLength = is_numeric($row['interval_length'] ?? null) ? (float) $row['interval_length'] : 60.0;
        $seconds = (int) round((float) $row['check_interval'] * $intervalLength);

        return $seconds > 0 ? $seconds : self::DEFAULT_CHECK_INTERVAL;
    }
}
