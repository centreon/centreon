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

use Adaptation\Database\Connection\Model\ConnectionConfig;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\TimeGrid;
use Core\Metric\Infrastructure\Graph\RrdtoolGraphDataBuilder;
use Core\Metric\Infrastructure\Graph\TimeSeriesGraphReader;

beforeEach(function (): void {
    $this->db = $this->createMock(DatabaseConnection::class);
    $this->db->method('getConnectionConfig')->willReturn(
        new ConnectionConfig('localhost', 'user', 'password', 'centreon', 'centreon_storage')
    );
    $this->timeSeries = $this->createMock(ReadMetricTimeSeriesRepositoryInterface::class);
    $this->reader = new TimeSeriesGraphReader(
        $this->db,
        $this->timeSeries,
        new RrdtoolGraphDataBuilder($this->timeSeries)
    );
    $this->statement = $this->createMock(\PDOStatement::class);
    $this->db->method('prepare')->willReturn($this->statement);
});

/**
 * @param list<int> $ids
 *
 * @return array<string, mixed>
 */
function graphDefinition(array $ids): array
{
    return [
        'start' => 1_700_000_000,
        'end' => 1_700_003_600,
        'metrics' => array_map(static fn (int $id): array => ['id' => $id, 'invert' => false], $ids),
        'vmetrics' => [],
        'lines' => array_map(
            static fn (int $id): array => ['name' => 'v' . $id, 'print_source' => 'v' . $id, 'prints' => []],
            $ids
        ),
    ];
}

it('should read the metrics with their data source types and the shortest check interval', function (): void {
    $this->statement->method('fetchAll')->willReturn([
        ['metric_id' => '4', 'data_source_type' => '1', 'check_interval' => '5', 'interval_length' => '60'],
        ['metric_id' => '9', 'data_source_type' => null, 'check_interval' => '2', 'interval_length' => '60'],
    ]);
    $this->timeSeries->expects($this->once())
        ->method('findMetricSeries')
        ->with(
            [4 => MetricDataSourceType::Counter, 9 => MetricDataSourceType::Gauge],
            $this->callback(static fn (TimeGrid $grid): bool => $grid->getStep() === 120)
        )
        ->willReturn([4 => array_fill(0, 31, 1.5)]);

    $graph = $this->reader->fetchGraphData(graphDefinition([4, 9]));

    expect($graph['meta']['step'])->toBe(120)
        ->and($graph['meta']['legend'])->toBe(['v4', 'v9'])
        ->and($graph['data'][0])->toBe([(string) $graph['meta']['start'], 1.5, null]);
});

it('should use a minute step when the check interval is unknown', function (): void {
    $this->statement->method('fetchAll')->willReturn([
        ['metric_id' => '4', 'data_source_type' => '0', 'check_interval' => null, 'interval_length' => null],
    ]);
    $this->timeSeries->method('findMetricSeries')->willReturn([]);

    expect($this->reader->fetchGraphData(graphDefinition([4]))['meta']['step'])
        ->toBe(TimeSeriesGraphReader::DEFAULT_CHECK_INTERVAL);
});

it('should not query the database for a graph without metric', function (): void {
    $this->db->expects($this->never())->method('prepare');
    $this->timeSeries->expects($this->never())->method('findMetricSeries');

    expect($this->reader->fetchGraphData(graphDefinition([]))['meta']['legend'])->toBe([]);
});

it('should give the status rows of an index on its check interval', function (): void {
    $this->statement->method('fetch')->willReturn(['check_interval' => '10', 'interval_length' => '60']);
    $this->timeSeries->expects($this->once())
        ->method('findStatusSeries')
        ->with(12, $this->callback(static fn (TimeGrid $grid): bool => $grid->getStep() === 600))
        ->willReturn([100.0, NAN, 75.0, 0.0, 100.0, 100.0, 100.0]);

    $rows = $this->reader->findStatusRows(12, 1_700_000_000, 1_700_003_600);

    expect($rows)->toHaveCount(7)
        ->and($rows[0])->toBe(['1700000400', 100.0])
        ->and($rows[1])->toBe(['1700001000', null])
        ->and($rows[3])->toBe(['1700002200', 0.0]);
});
