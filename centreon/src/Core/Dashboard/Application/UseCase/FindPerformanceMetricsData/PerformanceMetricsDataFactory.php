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

namespace Core\Dashboard\Application\UseCase\FindPerformanceMetricsData;

use Core\Dashboard\Domain\Model\Metric\PerformanceMetricsData;
use Core\Metric\Application\Exception\MetricException;

class PerformanceMetricsDataFactory
{
    /**
     * @param array<int<0, max>, array> $metricsData
     * @param int[] $metricIds
     *
     * @return PerformanceMetricsData
     */
    public function createFromRecords(array $metricsData, array $metricIds): PerformanceMetricsData
    {
        $metricBases = [];
        $metrics = [];
        $times = [];
        foreach ($metricsData as $metricData) {
            $this->validateRrdFormat($metricData);
            $metricBases[] = $metricData['global']['base'];
            $metrics[] = $metricData['metrics'];
            $times[] = $metricData['times'];
        }
        $base = $this->getHighestBase($metricBases);
        $metricsInfo = $this->filterMetricsByMetricId($metrics, $metricIds);
        $times = $this->getTimes($times);

        return new PerformanceMetricsData($base, $metricsInfo, $times);
    }

    /**
     * Get The highest base of all metrics.
     *
     * @param int[] $bases
     *
     * @return int
     */
    private function getHighestBase(array $bases): int
    {
        return max($bases);
    }

    /**
     * Filter the metrics to keep only the needed metrics.
     *
     * @param array<
     *      array{
     *        metric_id: int,
     *        mixed
     *      }
     *    > $metricsData
     * @param int[] $metricIds
     *
     * @return array<int<0, max>, mixed>
     */
    private function filterMetricsByMetricId(array $metricsData, array $metricIds): array
    {
        $metrics = [];
        foreach ($metricsData as $metricData) {
            foreach ($metricData as $metric) {
                if (! array_key_exists('metric_id', $metric)) {
                    MetricException::missingPropertyInMetricInformation('metric_id');
                }
                if (in_array($metric['metric_id'], $metricIds, true)) {
                    $metrics[] = $metric;
                }
            }
        }

        return $metrics;
    }

    /**
     * Get the different times of metric.
     *
     * @param array<array<string>> $times
     *
     * @return string[]
     */
    private function getTimes(array $times): array
    {
        return $times[0];
    }

    /**
     * Validate that the Rrd is correctly formatted.
     *
     * @param array<string,mixed> $metricData
     *
     * @throws MetricException
     */
    private function validateRrdFormat(array $metricData): void
    {
        if (! array_key_exists('global', $metricData) || ! array_key_exists('base', $metricData['global'])) {
            throw MetricException::missingPropertyInMetricInformation('base');
        }
        if (! array_key_exists('metrics', $metricData)) {
            throw MetricException::missingPropertyInMetricInformation('metrics');
        }
        if (! array_key_exists('times', $metricData)) {
            throw MetricException::missingPropertyInMetricInformation('times');
        }
    }
}