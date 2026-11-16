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

require_once __DIR__ . '/../../../../www/class/centreonGraphService.class.php';

/*
 * Legacy graph classes with the metrics data source switch: VictoriaMetrics (no RRD file, no rrdtool)
 * or RRD with a missing rrdtool binary. The objects are built without their database constructor.
 */

/**
 * @template T of object
 *
 * @param class-string<T> $class
 * @param array<string, mixed> $properties
 *
 * @return T
 */
function legacyGraph(string $class, array $properties): object
{
    $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach ($properties as $name => $value) {
        $property = new \ReflectionProperty($object, $name);
        $property->setValue($object, $value);
    }

    return $object;
}

function callPrivate(object $object, string $method, mixed ...$arguments): mixed
{
    return (new \ReflectionMethod($object, $method))->invoke($object, ...$arguments);
}

beforeEach(function (): void {
    // database answering $row to the index_data lookup
    $this->indexDatabase = function (mixed $row): \CentreonDB {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetch')->willReturn($row);
        $statement->method('rowCount')->willReturn($row === false ? 0 : 1);
        $db = $this->createMock(\CentreonDB::class);
        $db->method('prepare')->willReturn($statement);

        return $db;
    };
});

it('should not look for RRD files when the metrics are in VictoriaMetrics', function (): void {
    $graphNg = legacyGraph(\CentreonGraphNg::class, ['victoriaMetrics' => true, 'dbPath' => '/nonexistent/']);
    $graph = legacyGraph(\CentreonGraph::class, ['victoriaMetrics' => true, 'dbPath' => '/nonexistent/']);

    expect(callPrivate($graphNg, 'checkDBAvailability', 12))->toBe(1)
        ->and(callPrivate($graph, 'checkDBAvailability', 12))->toBe(1);
});

it('should still look for RRD files with the RRD data source', function (): void {
    $graphNg = legacyGraph(\CentreonGraphNg::class, ['dbPath' => '/nonexistent/']);
    $graph = legacyGraph(\CentreonGraph::class, ['dbPath' => '/nonexistent/']);

    expect(callPrivate($graphNg, 'checkDBAvailability', 12))->toBe(0)
        ->and(callPrivate($graph, 'checkDBAvailability', 12))->toBe(0);
});

it('should say a status graph exists when its service has an index in VictoriaMetrics', function (mixed $row, bool $expected): void {
    $graphNg = legacyGraph(\CentreonGraphNg::class, [
        'victoriaMetrics' => true,
        'dbStatusPath' => '/nonexistent',
        'dbCs' => ($this->indexDatabase)($row),
    ]);
    $graph = legacyGraph(\CentreonGraph::class, [
        'victoriaMetrics' => true,
        'dbStatusPath' => '/nonexistent',
        'DBC' => ($this->indexDatabase)($row),
    ]);

    expect($graphNg->statusGraphExists(1, 2))->toBe($expected)
        ->and($graph->statusGraphExists(1, 2))->toBe($expected);
})->with([
    'indexed service' => [['id' => '42'], true],
    'service without graph' => [false, false],
]);

it('should look for the status RRD file with the RRD data source', function (): void {
    $graphNg = legacyGraph(\CentreonGraphNg::class, [
        'dbStatusPath' => '/nonexistent',
        'dbCs' => ($this->indexDatabase)(['id' => '42']),
    ]);

    expect($graphNg->statusGraphExists(1, 2))->toBeFalse();
});

it('should fail clearly when rrdtool is missing', function (): void {
    $graphNg = legacyGraph(\CentreonGraphNg::class, [
        'generalOpt' => ['rrdtool_path_bin' => ['value' => '/nonexistent/rrdtool']],
    ]);
    $status = legacyGraph(\CentreonGraphStatus::class, [
        'generalOpt' => ['rrdtool_path_bin' => '/nonexistent/rrdtool'],
    ]);
    $poller = legacyGraph(\centreonGraphPoller::class, [
        'generalOpt' => ['rrdtool_path_bin' => '/nonexistent/rrdtool', 'debug_rrdtool' => '0', 'debug_path' => '/nonexistent/'],
        'rrdOptions' => [],
        'arguments' => [],
        'metrics' => [],
        'extraDatas' => [],
    ]);

    foreach ([[$graphNg, 'runRrdtool'], [$status, 'getJsonStream'], [$poller, 'getJsonStream']] as [$object, $method]) {
        expect(static fn () => callPrivate($object, $method))
            ->toThrow(\RuntimeException::class, 'rrdtool cannot be run (/nonexistent/rrdtool)');
    }
});

it('should answer that the poller statistics are not available with VictoriaMetrics', function (): void {
    $poller = legacyGraph(\centreonGraphPoller::class, [
        'generalOpt' => ['metrics_data_source' => 'victoria_metrics'],
    ]);

    expect(static fn () => $poller->getGraph(1_700_000_000, 1_700_003_600))
        ->toThrow(\RuntimeException::class, 'The poller statistics graphs are not available');
});

it('should give the curves of a CentreonGraphNg graph in the CentreonGraphService shape', function (): void {
    $converted = \CentreonGraphService::convertGraphNgData([
        'times' => ['1700000300', '1700000600'],
        'metrics' => [
            [
                'metric_id' => 1, 'metric_legend' => 'traffic_in', 'legend' => 'Inbound', 'unit' => 'b/s',
                'virtual' => 0, 'stack' => 0, 'warn' => '800', 'crit' => '900',
                'ds_data' => ['ds_filled' => '1', 'ds_color_line' => '#FF0000', 'ds_invert' => null, 'ds_name' => 'traffic_in'],
                'data' => [1.5, null],
                'prints' => [['Last:1.50'], ['Max:1']],
            ],
            [
                'metric_id' => 2, 'metric_legend' => 'traffic_out', 'legend' => 'Outbound', 'unit' => 'b/s',
                'virtual' => 0, 'stack' => 1, 'warn' => null, 'crit' => null,
                'ds_data' => ['ds_filled' => '0', 'ds_color_line' => '#00FF00', 'ds_invert' => '1', 'ds_name' => ''],
                'data' => [-3.0, -4.0],
                'prints' => [],
            ],
            [
                'vmetric_id' => 5, 'metric_legend' => 'total', 'legend' => 'total', 'unit' => 'b/s',
                'virtual' => 1, 'def_type' => 'CDEF', 'ds_data' => ['ds_color_line' => '#0000FF'],
                'data' => [4.5, null], 'prints' => [],
            ],
            [
                'vmetric_id' => 6, 'metric_legend' => 'peak', 'legend' => 'peak', 'unit' => 'b/s',
                'virtual' => 1, 'def_type' => 'VDEF', 'ds_data' => [], 'data' => [9.0, 9.0], 'prints' => [],
            ],
        ],
    ]);

    expect($converted['metrics'])->toBe([
        [
            'data' => ['1700000300' => 1.5, '1700000600' => null], 'legend' => 'traffic_in', 'graph_type' => 'area',
            'unit' => 'b/s', 'color' => '#FF0000', 'negative' => false,
            'stack' => false, 'crit' => '900', 'warn' => '800', 'metric_name' => 'traffic_in',
        ],
        [
            'data' => ['1700000300' => -3.0, '1700000600' => -4.0], 'legend' => 'traffic_out', 'graph_type' => 'line',
            'unit' => 'b/s', 'color' => '#00FF00', 'negative' => true,
            'stack' => true, 'crit' => null, 'warn' => null, 'metric_name' => 'traffic_out',
        ],
        [
            'data' => ['1700000300' => 4.5, '1700000600' => null], 'legend' => 'total', 'graph_type' => 'line',
            'unit' => 'b/s', 'color' => '#0000FF', 'negative' => false,
        ],
    ])->and($converted['legends'])->toBe([
        'traffic_in' => ['extras' => [['name' => 'Last', 'value' => '1.50'], ['name' => 'Max', 'value' => '1']]],
    ]);
});
