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

class PerformanceMetricsDataFactory
{
    public function createFromRecords(array $metricsData, array $metricIds): PerformanceMetricsData
    {
        $metricBases = [];
        $metrics = [];
        $times = [];
        foreach ($metricsData as $metricData) {
            $metricBases[] = $metricData['global']['base'];
            $metrics[] = $metricData['metrics'];
            $times[] = $metricData['times'];
        }
        $base = $this->getHighestBase($metricBases);
        $metricsInfo = $this->removeUnwantedMetrics($metrics, $metricIds);
        $times = $this->getTimes($times);
        return new PerformanceMetricsData($base, $metricsInfo, $times);
    }

    /**
     * Get The highest base of all metrics
     *
     * @param int[] $bases
     * @return int
     */
    private function getHighestBase(array $bases): int
    {
        return max($bases);
    }

    /**
     * Remove useless metrics
     *
     * @param array<string,mixed> $metricsData
     * @return array<string,mixed>
     */
    private function removeUnwantedMetrics(array $metricsData, array $metricIds): array
    {
        $metrics = [];
        foreach($metricsData as $metricData) {
            foreach ($metricData as $metric) {
                if(in_array($metric['metric_id'], $metricIds)) {
                    $metrics[] = $metric;
                }
            }
        }

        return $metrics;
    }

    /**
     * Get the different times of metric.
     *
     * @param array<array<int>> $metricsData
     * @return int[]
     */
    private function getTimes(array $metricsData): array
    {
        return array_unique(array_merge($metricsData));
    }
}