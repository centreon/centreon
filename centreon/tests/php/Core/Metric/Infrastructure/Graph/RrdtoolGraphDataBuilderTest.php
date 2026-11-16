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

namespace Tests\Core\Metric\Infrastructure\Graph;

use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\Exception\RpnExpressionException;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\TimeGrid;
use Core\Metric\Infrastructure\Graph\RrdtoolGraphDataBuilder;

/*
 * The fixtures are real outputs of rrdtool 1.7.2 (centreon-web-alma9 image), for the arguments that
 * CentreonGraphNg builds:
 *  - rrdtool-graph.json: metric 1 ("v1", legend Last/Min/Max/Average/Total, min and max as integers),
 *    metric 2 inverted ("DEF:vi2", "CDEF:v2=vi2,-1,*", legend Last/Average) and the virtual metric 5
 *    "CDEF:vv5=v1,v2,+" (legend Last/Max), 2023-11-14 22:13:20 + 23 steps of 300 s, metric 1 missing
 *    on 3 steps;
 *  - rrdtool-divzero.json: "CDEF:vv9=v1,0,/" (legend Last/Average).
 */

/**
 * @return array<string, mixed>
 */
function rrdtoolFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__ . "/fixtures/rrdtool-{$name}.json"), true);
}

/**
 * Time series repository answering the values rrdtool read from the RRD files.
 *
 * @param array<int, list<float>> $series
 */
function fixedTimeSeriesRepository(array $series): FixedTimeSeriesRepository
{
    return new FixedTimeSeriesRepository($series);
}

final class FixedTimeSeriesRepository implements ReadMetricTimeSeriesRepositoryInterface
{
    /** @var list<array{list<int>, int}> */
    public array $calls = [];

    /**
     * @param array<int, list<float>> $series
     */
    public function __construct(private array $series)
    {
    }

    public function findMetricSeries(array $metrics, TimeGrid $grid): array
    {
        $this->calls[] = [
            array_map(static fn (MetricDataSourceType $type): string => $type->name, $metrics),
            $grid->getStep(),
        ];

        return array_intersect_key($this->series, $metrics);
    }

    public function findStatusSeries(int $indexId, TimeGrid $grid): array
    {
        return [];
    }
}

/**
 * @return array<string, mixed>
 */
function fixtureDefinition(): array
{
    $print = static fn (string $label, string $function, string $format = '%.2lf'): array => [
        'label' => $label, 'function' => $function, 'format' => $format,
    ];

    return [
        'start' => 1_700_000_000,
        'end' => 1_700_006_900,
        'metrics' => [['id' => 1, 'invert' => false], ['id' => 2, 'invert' => true]],
        'vmetrics' => [['id' => 5, 'def_type' => 'CDEF', 'rpn' => 'v1,v2,+']],
        'lines' => [
            ['name' => 'v1', 'print_source' => 'v1', 'prints' => [
                $print('Last', 'LAST'),
                $print('Min', 'MINIMUM', '%.0lf'),
                $print('Max', 'MAXIMUM', '%.0lf'),
                $print('Average', 'AVERAGE'),
                $print('Total', 'TOTAL'),
            ]],
            ['name' => 'v2', 'print_source' => 'vi2', 'prints' => [$print('Last', 'LAST'), $print('Average', 'AVERAGE')]],
            ['name' => 'vv5', 'print_source' => 'vv5', 'prints' => [$print('Last', 'LAST'), $print('Max', 'MAXIMUM')]],
        ],
    ];
}

/**
 * Stored values of the fixture: column 1 is metric 1, column 2 is metric 2 inverted.
 *
 * @return array<int, list<float>>
 */
function fixtureStoredSeries(): array
{
    $series = [1 => [], 2 => []];
    foreach (rrdtoolFixture('graph')['data'] as $row) {
        $series[1][] = $row[1] ?? NAN;
        $series[2][] = isset($row[2]) ? -$row[2] : NAN;
    }

    return $series;
}

it('should return what rrdtool returns for the same values', function (): void {
    $rrdtool = rrdtoolFixture('graph');
    $repository = fixedTimeSeriesRepository(fixtureStoredSeries());

    $graph = (new RrdtoolGraphDataBuilder($repository))->build(fixtureDefinition(), 300);

    expect($repository->calls)->toBe([[[1 => 'Gauge', 2 => 'Gauge'], 300]])
        ->and($graph['meta']['start'])->toBe($rrdtool['meta']['start'])
        ->and($graph['meta']['end'])->toBe($rrdtool['meta']['end'])
        ->and($graph['meta']['step'])->toBe($rrdtool['meta']['step'])
        ->and($graph['meta']['legend'])->toBe($rrdtool['meta']['legend'])
        ->and($graph['meta']['gprints'])->toBe($rrdtool['meta']['gprints'])
        ->and(array_column($graph['data'], 0))->toBe(array_column($rrdtool['data'], 0));
    foreach ($rrdtool['data'] as $index => $row) {
        foreach ([1, 2, 3] as $column) {
            if ($row[$column] === null) {
                expect($graph['data'][$index][$column])->toBeNull();
            } else {
                expect($graph['data'][$index][$column])->toEqualWithDelta($row[$column], 1e-8);
            }
        }
    }
});

it('should print infinite and unknown legend values as rrdtool', function (): void {
    $rrdtool = rrdtoolFixture('divzero');
    $definition = [
        'start' => 1_700_000_000,
        'end' => 1_700_001_500,
        'metrics' => [['id' => 1, 'invert' => false]],
        'vmetrics' => [['id' => 9, 'def_type' => 'CDEF', 'rpn' => 'v1,0,/']],
        'lines' => [['name' => 'vv9', 'print_source' => 'vv9', 'prints' => [
            ['label' => 'Last', 'function' => 'LAST', 'format' => '%.2lf'],
            ['label' => 'Average', 'function' => 'AVERAGE', 'format' => '%.2lf'],
        ]]],
    ];
    $repository = fixedTimeSeriesRepository([1 => [1.17, 3.17, 5.17, 7.17, 9.17, 11.17]]);

    $graph = (new RrdtoolGraphDataBuilder($repository))->build($definition, 300);

    expect($graph['meta']['gprints'])->toBe($rrdtool['meta']['gprints'])
        ->and($graph['data'])->toBe($rrdtool['data']);
});

it('should leave unknown a metric without values and draw a VDEF as a constant', function (): void {
    $definition = fixtureDefinition();
    $definition['vmetrics'][] = ['id' => 6, 'def_type' => 'VDEF', 'rpn' => 'v1,MAXIMUM'];
    $definition['lines'] = [
        ['name' => 'v2', 'print_source' => 'vi2', 'prints' => []],
        ['name' => 'vv6', 'print_source' => 'vv6', 'prints' => []],
    ];
    $repository = fixedTimeSeriesRepository([1 => array_fill(0, 24, 4.0)]);

    $graph = (new RrdtoolGraphDataBuilder($repository))->build($definition, 300);

    expect(array_unique(array_column($graph['data'], 1), SORT_REGULAR))->toBe([null])
        ->and(array_unique(array_column($graph['data'], 2)))->toBe([4.0]);
});

it('should read each metric with its data source type', function (): void {
    $repository = fixedTimeSeriesRepository(fixtureStoredSeries());

    (new RrdtoolGraphDataBuilder($repository))->build(fixtureDefinition(), 300, [2 => MetricDataSourceType::Counter]);

    expect($repository->calls)->toBe([[[1 => 'Gauge', 2 => 'Counter'], 300]]);
});

it('should step at the check interval and read nothing without metric', function (): void {
    $repository = fixedTimeSeriesRepository([]);
    $definition = ['start' => 0, 'end' => 3600, 'metrics' => [], 'vmetrics' => [], 'lines' => []];

    $graph = (new RrdtoolGraphDataBuilder($repository))->build($definition, 600);

    expect($repository->calls)->toBe([])
        ->and($graph['meta']['step'])->toBe(600)
        ->and($graph['data'])->toHaveCount(6);
});

it('should refuse a virtual metric it cannot compute', function (): void {
    $definition = fixtureDefinition();
    $definition['vmetrics'] = [['id' => 5, 'def_type' => 'CDEF', 'rpn' => 'v1,3600,TREND']];

    (new RrdtoolGraphDataBuilder(fixedTimeSeriesRepository(fixtureStoredSeries())))->build($definition, 300);
})->throws(RpnExpressionException::class, '"TREND" is not supported');

it('should format the legend values as printf', function (string $format, float $value, string $expected): void {
    expect(RrdtoolGraphDataBuilder::formatPrint($format, $value))->toBe($expected);
})->with([
    ['%.2lf', 45.1666666667, '45.17'],
    ['%.0lf', 1.1666666667, '1'],
    ['%.2lf', -32.5, '-32.50'],
    ['%.2lf', NAN, 'nan'],
    ['%.2lf', INF, 'inf'],
    ['%.2lf', -INF, '-inf'],
]);
