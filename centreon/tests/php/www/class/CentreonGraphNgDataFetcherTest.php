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

namespace Tests\www\class;

use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\TimeSeries\TimeGrid;
use Core\Metric\Infrastructure\Graph\RrdtoolGraphDataBuilder;

/*
 * Same graph read by rrdtool from RRD files and through a data fetcher (the VictoriaMetrics path) fed
 * with the values rrdtool read: CentreonGraphNg must answer the same document. Needs rrdtool.
 */

const GRAPH_START = 1_700_000_000;
const GRAPH_END = 1_700_006_900;

function findRrdtool(): ?string
{
    foreach (['/usr/bin/rrdtool', '/usr/local/bin/rrdtool'] as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * RRD files of metric 1 (traffic_in, 3 steps missing) and metric 2 (traffic_out), 300 s step.
 */
function createRrdFiles(string $rrdtool, string $directory): void
{
    foreach ([1, 2] as $metricId) {
        exec(sprintf(
            '%s create %s --start %d --step 300 DS:value:GAUGE:600:U:U RRA:AVERAGE:0.5:1:2000',
            $rrdtool,
            escapeshellarg("{$directory}/{$metricId}.rrd"),
            GRAPH_START - 300
        ));
    }
    $updates = [1 => [], 2 => []];
    for ($row = 0; $row < 24; $row++) {
        $timestamp = GRAPH_START + $row * 300;
        if (! in_array($row, [10, 11, 12], true)) {
            $updates[1][] = sprintf('%d:%.1f', $timestamp, $row * 2 + 0.5);
        }
        $updates[2][] = sprintf('%d:%d', $timestamp, 100 - $row);
    }
    foreach ($updates as $metricId => $values) {
        exec(sprintf('%s update %s %s', $rrdtool, escapeshellarg("{$directory}/{$metricId}.rrd"), implode(' ', $values)));
    }
}

/**
 * Curve settings of giv_components_template.
 *
 * @return array<string, mixed>
 */
function curveTemplate(string $name, int $order, string $legend, array $flags): array
{
    return array_merge([
        'compo_id' => $order, 'name' => $name, 'ds_order' => (string) $order, 'ds_hidecurve' => null,
        'ds_name' => $name, 'ds_color_line' => '#FF0000', 'ds_color_line_mode' => '0',
        'ds_color_area' => '#FF0000', 'ds_color_area_warn' => null, 'ds_color_area_crit' => null,
        'ds_filled' => '1', 'ds_max' => null, 'ds_min' => null, 'ds_minmax_int' => null,
        'ds_average' => null, 'ds_last' => null, 'ds_total' => null, 'ds_tickness' => '1',
        'ds_transparency' => '80', 'ds_invert' => null, 'ds_legend' => $legend, 'ds_jumpline' => '0',
        'ds_stack' => null, 'default_tpl1' => null, 'comment' => null, 'host_id' => null, 'service_id' => null,
    ], $flags);
}

/**
 * CentreonGraphNg without database: the rows it would read are given.
 */
function buildGraph(string $rrdtool, string $directory, ?\Closure $fetcher): \CentreonGraphNg
{
    $graph = (new \ReflectionClass(\CentreonGraphNg::class))->newInstanceWithoutConstructor();
    $set = static function (string $property, mixed $value) use ($graph): void {
        (new \ReflectionProperty(\CentreonGraphNg::class, $property))->setValue($graph, $value);
    };
    $call = static function (string $method, mixed ...$arguments) use ($graph): mixed {
        return (new \ReflectionMethod(\CentreonGraphNg::class, $method))->invoke($graph, ...$arguments);
    };

    $set('metricUtils', \MetricUtils::getInstance());
    $set('dbPath', $directory . '/');
    $set('generalOpt', [
        'debug_path' => ['value' => '/nonexistent/'],
        'debug_rrdtool' => ['value' => '0'],
        'rrdtool_path_bin' => ['value' => $rrdtool],
    ]);
    $set('rrdCachedOptions', []);
    $set('componentsDsCache', [
        curveTemplate('traffic_in', 1, 'Inbound Traffic', [
            'ds_min' => '1', 'ds_max' => '1', 'ds_minmax_int' => '1', 'ds_last' => '1', 'ds_average' => '1', 'ds_total' => '1',
        ]),
        curveTemplate('traffic_out', 2, 'Outbound Traffic', ['ds_invert' => '1', 'ds_last' => '1', 'ds_average' => '1']),
        curveTemplate('traffic_total', 3, '', ['ds_last' => '1', 'ds_max' => '1']),
    ]);
    if ($fetcher !== null) {
        $graph->setDataFetcher($fetcher);
    }

    $metric = static fn (int $id, string $name): array => [
        'index_id' => 7, 'host_id' => 14, 'service_id' => 26, 'metric_id' => $id, 'metric_name' => $name,
        'unit_name' => 'b/s', 'min' => '0', 'max' => '1000', 'warn' => '800', 'warn_low' => null,
        'crit' => '900', 'crit_low' => null,
    ];
    $call('addIndexId', 7);
    $call('addRealMetric', $metric(1, 'traffic_in'));
    $call('addRealMetric', $metric(2, 'traffic_out'));
    $call('addVirtualMetric', [
        'vmetric_id' => 5, 'index_id' => 7, 'vmetric_name' => 'traffic_total', 'unit_name' => 'b/s',
        'warn' => null, 'crit' => null, 'def_type' => '0', 'rpn_function' => 'traffic_in,traffic_out,+',
        'hidden' => '0',
    ]);

    // getGraph() without the database reads (index data, graph template)
    $set('extraDatas', [
        'host_name' => 'router', 'service_description' => 'Traffic', 'title' => 'Traffic graph on router',
        'start' => GRAPH_START, 'end' => GRAPH_END,
    ]);
    $graph->setRRDOption('start', (string) GRAPH_START);
    $graph->setRRDOption('end', (string) GRAPH_END);
    $graph->init();
    $call('initCurveList');
    $graph->createLegend();

    return $graph;
}

/**
 * CentreonGraphNg::formatByMetrics() reads "metric_id" on virtual metrics, which have none: a
 * warning on both paths, as in production.
 *
 * @return array<string, mixed>
 */
function readGraph(\CentreonGraphNg $graph): array
{
    set_error_handler(static fn (int $level): bool => $level === E_WARNING);
    try {
        return $graph->getJsonStream();
    } finally {
        restore_error_handler();
    }
}

/**
 * Same keys in the same order, same strings and integers, floats equal to 1e-8.
 */
function expectSameDocument(mixed $actual, mixed $expected, string $path = ''): void
{
    if (is_array($expected)) {
        expect($actual)->toBeArray();
        expect(array_keys($actual))->toBe(array_keys($expected), "keys of {$path}");
        foreach ($expected as $key => $value) {
            expectSameDocument($actual[$key], $value, "{$path}.{$key}");
        }
    } elseif (is_float($expected)) {
        expect($actual)->toBeFloat()->toEqualWithDelta($expected, 1e-8, $path);
    } else {
        expect($actual)->toBe($expected, $path);
    }
}

beforeEach(function (): void {
    $this->rrdtool = findRrdtool();
    if ($this->rrdtool === null) {
        $this->markTestSkipped('rrdtool is not installed');
    }
    $this->directory = sys_get_temp_dir() . '/graph-ng-' . bin2hex(random_bytes(4));
    mkdir($this->directory);
    createRrdFiles($this->rrdtool, $this->directory);
});

afterEach(function (): void {
    if (isset($this->directory)) {
        array_map('unlink', glob($this->directory . '/*.rrd') ?: []);
        rmdir($this->directory);
    }
});

it('should answer the same document from rrdtool and from a data fetcher', function (): void {
    $fromRrd = readGraph(buildGraph($this->rrdtool, $this->directory, null));
    expect($fromRrd['times'])->toHaveCount(24)
        ->and($fromRrd['metrics'])->toHaveCount(3);

    // values stored in the time series database: those rrdtool read, metric 2 not inverted
    $stored = [
        1 => array_map(static fn (?float $value): float => $value ?? NAN, $fromRrd['metrics'][0]['data']),
        2 => array_map(static fn (?float $value): float => $value === null ? NAN : -$value, $fromRrd['metrics'][1]['data']),
    ];
    $repository = new class ($stored) implements ReadMetricTimeSeriesRepositoryInterface {
        /**
         * @param array<int, list<float>> $stored
         */
        public function __construct(private array $stored)
        {
        }

        public function findMetricSeries(array $metrics, TimeGrid $grid): array
        {
            return $this->stored;
        }

        public function findStatusSeries(int $indexId, TimeGrid $grid): array
        {
            return [];
        }
    };
    $builder = new RrdtoolGraphDataBuilder($repository);
    $fetcher = static fn (array $definition): array => $builder->build($definition, 300);

    $fromFetcher = readGraph(buildGraph($this->rrdtool, $this->directory . '/missing', $fetcher));

    expectSameDocument($fromFetcher, $fromRrd);
    expect(json_encode($fromFetcher['global']))->toBe(json_encode($fromRrd['global']))
        ->and(array_column($fromFetcher['metrics'], 'prints'))->toBe(array_column($fromRrd['metrics'], 'prints'))
        ->and($fromRrd['metrics'][0]['prints'])->toBe([
            ['Last:45.17'], ['Min:1'], ['Max:45'], ['Average:22.92'], ['Total:130650.00'],
        ]);
});
