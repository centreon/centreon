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

namespace Tests\Core\Metric\Infrastructure\Repository;

use Core\Metric\Application\Exception\MetricsStorageException;
use Core\Metric\Application\Repository\ReadMetricsStorageSettingsRepositoryInterface;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\Model\MetricsDataSource;
use Core\Metric\Domain\Model\MetricsStorageSettings;
use Core\Metric\Domain\TimeSeries\TimeGrid;
use Core\Metric\Infrastructure\Repository\ApiReadMetricTimeSeriesRepository;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @param list<array{id: string, values: list<array{int, string}>}> $series
 */
function victoriaMetricsMatrix(array $series): string
{
    return (string) json_encode([
        'status' => 'success',
        'data' => [
            'resultType' => 'matrix',
            'result' => array_map(
                static fn (array $item): array => ['metric' => ['id' => $item['id']], 'values' => $item['values']],
                $series
            ),
        ],
    ]);
}

/**
 * @param list<int> $ids
 *
 * @return array<int, MetricDataSourceType>
 */
function gauges(array $ids): array
{
    return array_fill_keys($ids, MetricDataSourceType::Gauge);
}

beforeEach(function (): void {
    $this->requests = [];
    $this->answers = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        $answer = array_shift($this->answers) ?? [victoriaMetricsMatrix([]), 200];
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return new MockResponse($answer[0], ['http_code' => $answer[1]]);
    });

    $this->settings = new MetricsStorageSettings(MetricsDataSource::VictoriaMetrics, 'http://vm:8428/');
    $settingsRepository = $this->createMock(ReadMetricsStorageSettingsRepositoryInterface::class);
    $settingsRepository->method('find')->willReturnCallback(fn () => $this->settings);

    $this->repository = new ApiReadMetricTimeSeriesRepository($httpClient, $settingsRepository, 3, 7);
    // rows at 1700000100, 1700000400, 1700000700
    $this->grid = TimeGrid::forPeriod(1_700_000_000, 1_700_000_700, 300);
    $this->requestBody = function (int $index): array {
        parse_str((string) $this->requests[$index]['options']['body'], $body);

        return $body;
    };
});

it('should build the queries of the contract', function (): void {
    expect(ApiReadMetricTimeSeriesRepository::buildQuery('metric_val', [12], 300))
        ->toBe('avg by (id) (avg_over_time(metric_val{id="12"}[300s]))')
        ->and(ApiReadMetricTimeSeriesRepository::buildQuery('metric_val', [12, 13, 14], 60))
        ->toBe('avg by (id) (avg_over_time(metric_val{id=~"12|13|14"}[60s]))')
        ->and(ApiReadMetricTimeSeriesRepository::buildQuery('status_val', [7], 300))
        ->toBe('avg by (id) (avg_over_time(status_val{id="7"}[300s]))');
});

it('should turn the raw counters into per-second rates as RRD does', function (MetricDataSourceType $type, string $query): void {
    expect(ApiReadMetricTimeSeriesRepository::buildQuery('metric_val', [12, 13], 300, $type))->toBe($query);
})->with([
    'GAUGE: average' => [MetricDataSourceType::Gauge, 'avg by (id) (avg_over_time(metric_val{id=~"12|13"}[300s]))'],
    'COUNTER: rate, never negative' => [
        MetricDataSourceType::Counter,
        'avg by (id) (clamp_min(rate(metric_val{id=~"12|13"}[300s]), 0))',
    ],
    'DERIVE: variation per second, may be negative' => [
        MetricDataSourceType::Derive,
        'avg by (id) (delta(metric_val{id=~"12|13"}[300s]) / 300)',
    ],
    'ABSOLUTE: value divided by the interval' => [
        MetricDataSourceType::Absolute,
        'avg by (id) (sum_over_time(metric_val{id=~"12|13"}[300s]) / 300)',
    ],
]);

it('should send one query per data source type', function (): void {
    $this->repository->findMetricSeries(
        [12 => MetricDataSourceType::Gauge, 13 => MetricDataSourceType::Counter, 14 => MetricDataSourceType::Gauge],
        $this->grid
    );

    expect($this->requests)->toHaveCount(2)
        ->and(($this->requestBody)(0)['query'])->toBe('avg by (id) (avg_over_time(metric_val{id=~"12|14"}[300s]))')
        ->and(($this->requestBody)(1)['query'])->toBe('avg by (id) (clamp_min(rate(metric_val{id="13"}[300s]), 0))');
});

it('should map the data source type column', function (int|string|null $column, MetricDataSourceType $type): void {
    expect(MetricDataSourceType::fromColumn($column))->toBe($type);
})->with([
    ['0', MetricDataSourceType::Gauge],
    ['1', MetricDataSourceType::Counter],
    ['2', MetricDataSourceType::Derive],
    ['3', MetricDataSourceType::Absolute],
    [3, MetricDataSourceType::Absolute],
    [null, MetricDataSourceType::Gauge],
    ['9', MetricDataSourceType::Gauge],
]);

it('should read the metrics of a graph in one query_range request', function (): void {
    $this->answers[] = [victoriaMetricsMatrix([
        ['id' => '12', 'values' => [[1_700_000_100, '1.5'], [1_700_000_700, '3']]],
        ['id' => '13', 'values' => [[1_700_000_400, 'NaN'], [1_700_000_700, '+Inf']]],
    ]), 200];

    $series = $this->repository->findMetricSeries(gauges([12, 13, 14]), $this->grid);

    expect($this->requests)->toHaveCount(1)
        ->and($this->requests[0]['method'])->toBe('POST')
        ->and($this->requests[0]['url'])->toBe('http://vm:8428/api/v1/query_range')
        ->and(($this->requestBody)(0))->toBe([
            'query' => 'avg by (id) (avg_over_time(metric_val{id=~"12|13|14"}[300s]))',
            'start' => '1700000100',
            'end' => '1700000700',
            'step' => '300s',
        ])
        ->and($this->requests[0]['options']['timeout'])->toBe(3.0)
        ->and($this->requests[0]['options']['max_duration'])->toBe(7.0)
        ->and(array_keys($series))->toBe([12, 13])
        ->and($series[12][0])->toBe(1.5)
        ->and(is_nan($series[12][1]))->toBeTrue()
        ->and($series[12][2])->toBe(3.0)
        ->and(is_nan($series[13][0]))->toBeTrue()
        ->and(is_nan($series[13][1]))->toBeTrue()
        ->and($series[13][2])->toBe(INF);
});

it('should split the metrics in several queries', function (): void {
    $ids = range(1, ApiReadMetricTimeSeriesRepository::METRICS_PER_QUERY + 1);

    $this->repository->findMetricSeries(gauges($ids), $this->grid);

    expect($this->requests)->toHaveCount(2)
        ->and(($this->requestBody)(1)['query'])->toBe(sprintf(
            'avg by (id) (avg_over_time(metric_val{id="%d"}[300s]))',
            ApiReadMetricTimeSeriesRepository::METRICS_PER_QUERY + 1
        ));
});

it('should send the basic authentication when a user is set', function (): void {
    $this->settings = new MetricsStorageSettings(MetricsDataSource::VictoriaMetrics, 'https://vm.example', 'reader', 's3cr3t');

    $this->repository->findMetricSeries(gauges([12]), $this->grid);

    expect($this->requests[0]['url'])->toBe('https://vm.example/api/v1/query_range')
        ->and($this->requests[0]['options']['normalized_headers']['authorization'][0])
        ->toBe('Authorization: Basic ' . base64_encode('reader:s3cr3t'));
});

it('should not authenticate without a user', function (): void {
    $this->repository->findMetricSeries(gauges([12]), $this->grid);

    expect($this->requests[0]['options']['normalized_headers'])->not->toHaveKey('authorization');
});

it('should read the status of a service, unknown without any value', function (): void {
    $this->answers[] = [victoriaMetricsMatrix([['id' => '7', 'values' => [[1_700_000_400, '75']]]]), 200];

    $status = $this->repository->findStatusSeries(7, $this->grid);
    $missing = $this->repository->findStatusSeries(8, $this->grid);

    expect(($this->requestBody)(0)['query'])->toBe('avg by (id) (avg_over_time(status_val{id="7"}[300s]))')
        ->and(is_nan($status[0]))->toBeTrue()
        ->and($status[1])->toBe(75.0)
        ->and($missing)->toHaveCount(3)
        ->and(is_nan($missing[0]))->toBeTrue();
});

it('should surface the errors of VictoriaMetrics', function (mixed $answer, string $message): void {
    $this->answers[] = $answer;

    expect(fn () => $this->repository->findMetricSeries(gauges([12]), $this->grid))
        ->toThrow(MetricsStorageException::class, $message);
})->with([
    'unreachable' => [
        new TransportException('Could not resolve host: vm'),
        'Request to VictoriaMetrics failed: Could not resolve host: vm',
    ],
    'query refused' => [
        ['{"status":"error","errorType":"422","error":"cannot parse query"}', 422],
        'VictoriaMetrics answered HTTP 422: cannot parse query',
    ],
    'unauthorized' => [['Unauthorized', 401], 'VictoriaMetrics answered HTTP 401'],
    'not JSON' => [['<html></html>', 200], 'Invalid answer of VictoriaMetrics'],
    'series without id' => [
        ['{"status":"success","data":{"resultType":"matrix","result":[{"metric":{},"values":[]}]}}', 200],
        'a series has no "id" label',
    ],
]);

it('should refuse to query without URL', function (): void {
    $this->settings = new MetricsStorageSettings(MetricsDataSource::VictoriaMetrics, ' ');

    expect(fn () => $this->repository->findStatusSeries(7, $this->grid))
        ->toThrow(MetricsStorageException::class, 'its URL is not configured')
        ->and($this->requests)->toBe([]);
});
