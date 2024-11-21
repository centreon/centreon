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

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Log\LoggerTrait;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetricsData;
use Core\Metric\Application\Exception\MetricException;
use Core\Metric\Domain\Model\MetricInformation\DataSource;
use Core\Metric\Domain\Model\MetricInformation\GeneralInformation;
use Core\Metric\Domain\Model\MetricInformation\MetricInformation;
use Core\Metric\Domain\Model\MetricInformation\RealTimeDataInformation;
use Core\Metric\Domain\Model\MetricInformation\ThresholdInformation;

/**
 * @phpstan-type _Metrics array{
 *      index_id: int,
 *      metric_id: int,
 *      metric: string,
 *      metric_legend: string,
 *      unit: string,
 *      hidden: int,
 *      legend: string,
 *      virtual: int,
 *      stack: int,
 *      ds_order: int,
 *      ds_data: array{
 *        ds_min: ?string,
 *        ds_max: ?string,
 *        ds_minmax_int: ?string,
 *        ds_last: ?string,
 *        ds_average: ?string,
 *        ds_color_area_warn?: string,
 *        ds_color_area_crit?: string,
 *        ds_total: ?string,
 *        ds_tickness: int,
 *        ds_color_line_mode: string,
 *        ds_color_line: string
 *      },
 *      warn: ?float,
 *      warn_low: ?float,
 *      crit: ?float,
 *      crit_low: ?float,
 *      ds_color_area_warn?: string,
 *      ds_color_area_crit?: string,
 *      data: array<float|null>,
 *      prints: array<array<string>>,
 *      min: ?float,
 *      max: ?float,
 *      last_value: ?float,
 *      minimum_value: ?float,
 *      maximum_value: ?float,
 *      average_value: ?float
 *  }
 * @phpstan-type _MetricData array{
 *     global: array{
 *         base: int,
 *         title: string,
 *         host_name: string
 *     },
 *     metrics: array<_Metrics>,
 *     times: string[]
 * }
 * @phpstan-type _DataSourceData array{
 *     ds_min: ?string,
 *     ds_max: ?string,
 *     ds_minmax_int: ?string,
 *     ds_last: ?string,
 *     ds_average: ?string,
 *     ds_total: ?string,
 *     ds_tickness: int,
 *     ds_color_line_mode: int,
 *     ds_color_line: string,
 *     ds_color_area_warn?: string,
 *     ds_color_area_crit?: string,
 *     ds_transparency: ?float,
 *     ds_color_area: ?string,
 *     legend: ?string,
 *     ds_filled: ?int,
 *     ds_invert: ?int,
 *     ds_stack: ?int,
 *     ds_order: ?int
 * }
 */
class PerformanceMetricsDataFactory
{
    use LoggerTrait;

    /**
     * @param array<_MetricData> $metricsData
     * @param string[] $metricNames
     *
     * @return PerformanceMetricsData
     */
    public function createFromRecords(array $metricsData, array $metricNames): PerformanceMetricsData
    {
        $metricBases = [];
        $metrics = [];
        $times = [];
        foreach ($metricsData as $index => $metricData) {
            $metricBases[] = $metricData['global']['base'];
            $metrics['index:' . $index . ';host_name:' . $metricData['global']['host_name']] = $metricData['metrics'];
            $times[] = $metricData['times'];
        }
        $base = $this->getHighestBase($metricBases);
        $metricsInfo = $this->createMetricInformations($metrics, $metricNames);
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
     * @param array<string,array<_Metrics>> $metricsData
     * @param string[] $metricNames
     *
     * @return array<_Metrics>
     */
    private function filterMetricsByMetricName(array $metricsData, array $metricNames): array
    {
        $metrics = [];
        foreach ($metricsData as $hostName => $metricData) {
            \preg_match('/^index:\d+;host_name:([[:ascii:]]+)$/', $hostName, $matches);
            $hostName = $matches[1];
            foreach ($metricData as $metric) {
                if (in_array($metric['metric'], $metricNames, true)) {
                    $metric['metric'] = $hostName . ': ' . $metric['metric'];
                    $metric['metric_legend'] = $hostName . ': ' . $metric['metric_legend'];
                    $metric['legend'] = $hostName . ': ' . $metric['legend'];
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
     * @return \DateTimeImmutable[]
     */
    private function getTimes(array $times): array
    {
        return array_map(fn (string $time): \DateTimeImmutable => (new \DateTimeImmutable())->setTimestamp((int) $time), $times[0]);
    }

    /**
     * Create Metric Information.
     *
     * @param array<string,array<_Metrics>> $metricData
     * @param string[] $metricNames
     *
     * @throws MetricException
     *
     * @return MetricInformation[]
     */
    private function createMetricInformations(array $metricData, array $metricNames): array
    {
        $metrics = $this->filterMetricsByMetricName($metricData, $metricNames);
        $metricsInformation = [];
        foreach ($metrics as $metric) {
            try {
                $generalInformation = new GeneralInformation(
                    $metric['index_id'],
                    $metric['metric_id'],
                    $metric['metric'],
                    $metric['metric_legend'],
                    $metric['unit'],
                    (bool) $metric['hidden'],
                    $metric['legend'],
                    (bool) $metric['virtual'],
                    (bool) $metric['stack'],
                    $metric['ds_order']
                );
                $dataSource = new DataSource(
                    $metric['ds_data']['ds_min'] !== null ? (int) $metric['ds_data']['ds_min'] : null,
                    $metric['ds_data']['ds_max'] !== null ? (int) $metric['ds_data']['ds_max'] : null,
                    $metric['ds_data']['ds_minmax_int'] !== null ? (int) $metric['ds_data']['ds_minmax_int'] : null,
                    $metric['ds_data']['ds_last'] !== null ? (int) $metric['ds_data']['ds_last'] : null,
                    $metric['ds_data']['ds_average'] !== null ? (int) $metric['ds_data']['ds_average'] : null,
                    $metric['ds_data']['ds_total'] !== null ? (int) $metric['ds_data']['ds_total'] : null,
                    $metric['ds_data']['ds_tickness'],
                    (int) $metric['ds_data']['ds_color_line_mode'],
                    $metric['ds_data']['ds_color_line'],
                );
                $thresholdInformation = new ThresholdInformation(
                    $metric['warn'] !== null ? (float) $metric['warn'] : null,
                    $metric['warn_low'] !== null ? (float) $metric['warn_low'] : null,
                    $metric['crit'] !== null ? (float) $metric['crit'] : null,
                    $metric['crit_low'] !== null ? (float) $metric['crit_low'] : null,
                    $metric['ds_color_area_warn'] ?? $metric['ds_data']['ds_color_area_warn'] ?? '',
                    $metric['ds_color_area_crit'] ?? $metric['ds_data']['ds_color_area_crit'] ?? ''
                );
                $realTimeDataInformation = new RealTimeDataInformation(
                    $metric['data'],
                    $metric['prints'],
                    $metric['min'] !== null ? (float) $metric['min'] : null,
                    $metric['max'] !== null ? (float) $metric['max'] : null,
                    $metric['minimum_value'] !== null ? (float) $metric['minimum_value'] : null,
                    $metric['maximum_value'] !== null ? (float) $metric['maximum_value'] : null,
                    $metric['last_value'] !== null ? (float) $metric['last_value'] : null,
                    $metric['average_value'] !== null ? (float) $metric['average_value'] : null
                );
                $metricsInformation[] = new MetricInformation(
                    $generalInformation,
                    $dataSource,
                    $thresholdInformation,
                    $realTimeDataInformation
                );
            } catch (\TypeError|AssertionException $ex) {
                $this->error('Metric data are not correctly formatted', ['trace' => (string) $ex]);

                throw MetricException::invalidMetricFormat();
            }
        }

        return $metricsInformation;
    }
}
