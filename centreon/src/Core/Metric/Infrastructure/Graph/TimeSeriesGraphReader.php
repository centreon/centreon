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

namespace Core\Metric\Infrastructure\Graph;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Metric\Application\Exception\MetricsStorageException;
use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\Exception\RpnExpressionException;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\TimeGrid;

/**
 * Graph values read from the time series database (VictoriaMetrics) for the legacy graph classes:
 * the data fetcher of CentreonGraphNg and the rows of CentreonGraphStatus. The step of a graph is
 * never shorter than the check interval of its services.
 *
 * @phpstan-import-type _Definition from RrdtoolGraphDataBuilder
 * @phpstan-import-type _RrdtoolJson from RrdtoolGraphDataBuilder
 */
final class TimeSeriesGraphReader extends AbstractRepositoryRDB
{
    /** Step used when the check interval of the services is unknown, in seconds. */
    public const DEFAULT_CHECK_INTERVAL = 60;

    public function __construct(
        DatabaseConnection $db,
        private readonly ReadMetricTimeSeriesRepositoryInterface $timeSeriesRepository,
        private readonly RrdtoolGraphDataBuilder $graphDataBuilder,
    ) {
        $this->db = $db;
    }

    /**
     * Data fetcher of CentreonGraphNg (setDataFetcher()).
     *
     * @param _Definition $definition
     *
     * @throws MetricsStorageException
     * @throws RpnExpressionException
     * @throws \InvalidArgumentException
     *
     * @return _RrdtoolJson
     */
    public function fetchGraphData(array $definition): array
    {
        $metricIds = array_column($definition['metrics'], 'id');
        [$types, $minimumStep] = $this->findMetricsSettings($metricIds);

        return $this->graphDataBuilder->build($definition, $minimumStep, $types);
    }

    /**
     * Status graph of a service, as the rows of "rrdtool graph --imgformat=JSONTIME".
     *
     * @param int $indexId index_data.id of the service
     * @param int $start
     * @param int $end
     *
     * @throws MetricsStorageException
     * @throws \InvalidArgumentException
     *
     * @return list<array{0: string, 1: float|null}>
     */
    public function findStatusRows(int $indexId, int $start, int $end): array
    {
        $grid = TimeGrid::forPeriod($start, $end, $this->findIndexCheckInterval($indexId));
        $values = $this->timeSeriesRepository->findStatusSeries($indexId, $grid);

        $rows = [];
        foreach ($grid->getTimestamps() as $position => $timestamp) {
            $value = $values[$position] ?? NAN;
            $rows[] = [(string) $timestamp, is_finite($value) ? $value : null];
        }

        return $rows;
    }

    /**
     * @param list<int> $metricIds
     *
     * @return array{array<int, MetricDataSourceType>, int} data source types by metric id and
     *                                                      shortest check interval of their services
     */
    private function findMetricsSettings(array $metricIds): array
    {
        if ($metricIds === []) {
            return [[], self::DEFAULT_CHECK_INTERVAL];
        }
        $placeholders = [];
        foreach (array_values($metricIds) as $position => $metricId) {
            $placeholders[":metric_{$position}"] = $metricId;
        }
        $statement = $this->db->prepare($this->translateDbName(sprintf(
            <<<'SQL'
                SELECT m.metric_id, m.data_source_type, s.check_interval, o.`value` AS interval_length
                FROM `:dbstg`.`metrics` m
                LEFT JOIN `:dbstg`.`index_data` i ON i.id = m.index_id
                LEFT JOIN `:dbstg`.`services` s ON s.host_id = i.host_id AND s.service_id = i.service_id
                LEFT JOIN `:db`.`options` o ON o.`key` = 'interval_length'
                WHERE m.metric_id IN (%s)
                SQL,
            implode(', ', array_keys($placeholders))
        )));
        foreach ($placeholders as $placeholder => $metricId) {
            $statement->bindValue($placeholder, $metricId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $types = [];
        $intervals = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            /** @var array{metric_id: int|string, data_source_type: string|null, check_interval: mixed, interval_length: mixed} $row */
            $types[(int) $row['metric_id']] = MetricDataSourceType::fromColumn($row['data_source_type']);
            $interval = self::toSeconds($row['check_interval'], $row['interval_length']);
            if ($interval !== null) {
                $intervals[] = $interval;
            }
        }

        return [$types, $intervals === [] ? self::DEFAULT_CHECK_INTERVAL : min($intervals)];
    }

    /**
     * Normal check interval of the service of an index, in seconds.
     */
    private function findIndexCheckInterval(int $indexId): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT s.check_interval, o.`value` AS interval_length
                FROM `:dbstg`.`index_data` i
                INNER JOIN `:dbstg`.`services` s ON s.host_id = i.host_id AND s.service_id = i.service_id
                LEFT JOIN `:db`.`options` o ON o.`key` = 'interval_length'
                WHERE i.id = :index_id
                SQL
        ));
        $statement->bindValue(':index_id', $indexId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return (is_array($row) ? self::toSeconds($row['check_interval'] ?? null, $row['interval_length'] ?? null) : null)
            ?? self::DEFAULT_CHECK_INTERVAL;
    }

    /**
     * @param mixed $checkInterval check interval, in interval_length units
     * @param mixed $intervalLength seconds of a unit (60 when unknown)
     */
    private static function toSeconds(mixed $checkInterval, mixed $intervalLength): ?int
    {
        if (! is_numeric($checkInterval)) {
            return null;
        }
        $seconds = (int) round((float) $checkInterval * (is_numeric($intervalLength) ? (float) $intervalLength : 60.0));

        return $seconds > 0 ? $seconds : null;
    }
}
