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

use Core\Metric\Application\Exception\MetricsStorageException;
use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\Exception\RpnExpressionException;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\Consolidation;
use Core\Metric\Domain\TimeSeries\RpnExpression;
use Core\Metric\Domain\TimeSeries\TimeGrid;

/**
 * Computes a graph from a time series database and returns it as "rrdtool graph --imgformat=JSONTIME"
 * does, so that CentreonGraphNg formats it exactly as the graphs read from the RRD files.
 *
 * @phpstan-type _Definition array{
 *     start: int,
 *     end: int,
 *     metrics: list<array{id: int, invert: bool}>,
 *     vmetrics: list<array{id: int, def_type: string, rpn: string}>,
 *     lines: list<array{name: string, print_source: string, prints: list<array{label: string, function: string, format: string}>}>
 * }
 * @phpstan-type _RrdtoolJson array{
 *     meta: array{start: int, end: int, step: int, legend: list<string>, gprints: list<array<string, string>>},
 *     data: list<list<float|string|null>>
 * }
 */
final class RrdtoolGraphDataBuilder
{
    public function __construct(private readonly ReadMetricTimeSeriesRepositoryInterface $timeSeriesRepository)
    {
    }

    /**
     * @param _Definition $definition graph definition (CentreonGraphNg::getDataDefinition())
     * @param int $minimumStep shortest step, in seconds (check interval of the service)
     * @param array<int, MetricDataSourceType> $dataSourceTypes by metric id, GAUGE when absent
     * @param int|null $now value of the RPN operator NOW
     *
     * @throws MetricsStorageException
     * @throws RpnExpressionException
     * @throws \InvalidArgumentException
     *
     * @return _RrdtoolJson
     */
    public function build(array $definition, int $minimumStep, array $dataSourceTypes = [], ?int $now = null): array
    {
        $grid = TimeGrid::forPeriod($definition['start'], $definition['end'], $minimumStep);
        $series = $this->computeCurves($definition, $grid, $dataSourceTypes, $now);

        $legend = [];
        $gprints = [];
        foreach ($definition['lines'] as $line) {
            $legend[] = $line['name'];
            $gprints[] = ['line' => $line['name']];
            foreach ($line['prints'] as $print) {
                $value = Consolidation::compute(
                    $print['function'],
                    $series[$line['print_source']] ?? [],
                    $grid->getStep()
                );
                $gprints[] = ['gprint' => $print['label'] . ':' . self::formatPrint($print['format'], $value)];
            }
        }

        $data = [];
        foreach ($grid->getTimestamps() as $index => $timestamp) {
            $row = [(string) $timestamp];
            foreach ($definition['lines'] as $line) {
                $value = $series[$line['name']][$index] ?? NAN;
                $row[] = is_finite($value) ? $value : null;
            }
            $data[] = $row;
        }

        return [
            'meta' => [
                'start' => $grid->getFirst(),
                'end' => $grid->getLast(),
                'step' => $grid->getStep(),
                'legend' => $legend,
                'gprints' => $gprints,
            ],
            'data' => $data,
        ];
    }

    /**
     * printf of a legend value, written as the C library of rrdtool writes the special values.
     *
     * @param string $format "%.2lf" or "%.0lf"
     * @param float $value
     */
    public static function formatPrint(string $format, float $value): string
    {
        return match (true) {
            is_nan($value) => 'nan',
            $value === INF => 'inf',
            $value === -INF => '-inf',
            default => sprintf(str_replace('lf', 'f', $format), $value),
        };
    }

    /**
     * Curves by rrdtool name: "v<metric_id>" (inverted when asked), "vi<metric_id>" (stored values of
     * an inverted metric), "vv<vmetric_id>".
     *
     * @param _Definition $definition
     * @param TimeGrid $grid
     * @param array<int, MetricDataSourceType> $dataSourceTypes
     * @param int|null $now
     *
     * @throws MetricsStorageException
     * @throws RpnExpressionException
     *
     * @return array<string, list<float>>
     */
    private function computeCurves(array $definition, TimeGrid $grid, array $dataSourceTypes, ?int $now): array
    {
        $metrics = [];
        foreach ($definition['metrics'] as $metric) {
            $metrics[$metric['id']] = $dataSourceTypes[$metric['id']] ?? MetricDataSourceType::Gauge;
        }
        $fetched = $metrics === [] ? [] : $this->timeSeriesRepository->findMetricSeries($metrics, $grid);

        $unknown = array_fill(0, $grid->count(), NAN);
        $series = [];
        foreach ($definition['metrics'] as $metric) {
            $values = $fetched[$metric['id']] ?? $unknown;
            if ($metric['invert']) {
                $series['vi' . $metric['id']] = $values;
                $values = array_map(static fn (float $value): float => $value * -1, $values);
            }
            $series['v' . $metric['id']] = $values;
        }

        foreach ($definition['vmetrics'] as $vmetric) {
            $expression = new RpnExpression($vmetric['rpn']);
            $series['vv' . $vmetric['id']] = $vmetric['def_type'] === 'VDEF'
                ? array_fill(0, $grid->count(), $expression->evaluateVdef($series, $grid->getStep()))
                : $expression->evaluate($series, $grid, $now);
        }

        return $series;
    }
}
