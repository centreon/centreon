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

namespace Core\Metric\Application\Repository;

use Core\Metric\Application\Exception\MetricsStorageException;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\TimeGrid;

/**
 * Averaged values of the metrics and statuses stored in a time series database.
 */
interface ReadMetricTimeSeriesRepositoryInterface
{
    /**
     * Value of each metric over each step of the grid: the average of a GAUGE, the per-second rate of
     * a COUNTER, DERIVE or ABSOLUTE (stored as raw counters).
     *
     * @param array<int, MetricDataSourceType> $metrics data source type by metrics.metric_id
     * @param TimeGrid $grid
     *
     * @throws MetricsStorageException
     *
     * @return array<int, list<float>> by metric id, one value per timestamp of the grid (NAN when unknown);
     *                                 a metric without any value in the period is absent
     */
    public function findMetricSeries(array $metrics, TimeGrid $grid): array;

    /**
     * Average status value (100 OK, 75 WARNING, 0 CRITICAL) of a service over each step of the grid.
     *
     * @param int $indexId index_data.id of the service
     * @param TimeGrid $grid
     *
     * @throws MetricsStorageException
     *
     * @return list<float> one value per timestamp of the grid (NAN when unknown)
     */
    public function findStatusSeries(int $indexId, TimeGrid $grid): array;
}
