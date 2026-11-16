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

namespace Core\Metric\Infrastructure\Repository;

use Core\Metric\Application\Exception\MetricsStorageException;
use Core\Metric\Application\Repository\ReadMetricsStorageSettingsRepositoryInterface;
use Core\Metric\Application\Repository\ReadMetricTimeSeriesRepositoryInterface;
use Core\Metric\Domain\Model\MetricDataSourceType;
use Core\Metric\Domain\TimeSeries\TimeGrid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads VictoriaMetrics through its Prometheus API ("/api/v1/query_range"), with the data model of
 * the Broker "victoria_metrics" output (gorgone-rs/docs/victoria-metrics-contract.md in
 * centreon-collect): series "metric_val" labelled "id" = metric_id, "status_val" labelled
 * "id" = index_data.id.
 */
final class ApiReadMetricTimeSeriesRepository implements ReadMetricTimeSeriesRepositoryInterface
{
    /** Metrics read by one query (one regular expression on "id"). */
    public const METRICS_PER_QUERY = 50;

    /**
     * @param HttpClientInterface $httpClient
     * @param ReadMetricsStorageSettingsRepositoryInterface $settingsRepository
     * @param float $timeout idle timeout of a request, in seconds
     * @param float $maxDuration total duration of a request, in seconds
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ReadMetricsStorageSettingsRepositoryInterface $settingsRepository,
        private readonly float $timeout = 10,
        private readonly float $maxDuration = 30,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findMetricSeries(array $metrics, TimeGrid $grid): array
    {
        $idsByType = [];
        foreach ($metrics as $metricId => $type) {
            $idsByType[$type->value][] = $metricId;
        }

        $series = [];
        foreach ($idsByType as $type => $ids) {
            foreach (array_chunk($ids, self::METRICS_PER_QUERY) as $chunk) {
                $query = self::buildQuery('metric_val', $chunk, $grid->getStep(), MetricDataSourceType::from((string) $type));
                foreach ($this->queryRange($query, $grid) as $id => $values) {
                    $series[$id] = $values;
                }
            }
        }

        return $series;
    }

    /**
     * @inheritDoc
     */
    public function findStatusSeries(int $indexId, TimeGrid $grid): array
    {
        $series = $this->queryRange(self::buildQuery('status_val', [$indexId], $grid->getStep()), $grid);

        return $series[$indexId] ?? array_fill(0, $grid->count(), NAN);
    }

    /**
     * Value over each step of the series of the given ids. The raw values of the counters become
     * per-second rates as RRD computes them: COUNTER with rate() (VictoriaMetrics treats a decrease
     * as a reset, so never negative), DERIVE with delta() over the step (may be negative), ABSOLUTE
     * with the sum of the values over the step. The average by "id" merges the series of one id whose
     * other labels changed (renamed host or service, new extra tag).
     *
     * @param 'metric_val'|'status_val' $name
     * @param list<int> $ids
     * @param int $step
     * @param MetricDataSourceType $type
     */
    public static function buildQuery(
        string $name,
        array $ids,
        int $step,
        MetricDataSourceType $type = MetricDataSourceType::Gauge,
    ): string {
        $selector = sprintf(
            '%s{%s}[%ds]',
            $name,
            count($ids) === 1
                ? sprintf('id="%d"', $ids[0])
                : sprintf('id=~"%s"', implode('|', array_map(static fn (int $id): string => (string) $id, $ids))),
            $step
        );

        return sprintf('avg by (id) (%s)', match ($type) {
            MetricDataSourceType::Gauge => sprintf('avg_over_time(%s)', $selector),
            MetricDataSourceType::Counter => sprintf('clamp_min(rate(%s), 0)', $selector),
            MetricDataSourceType::Derive => sprintf('delta(%s) / %d', $selector, $step),
            MetricDataSourceType::Absolute => sprintf('sum_over_time(%s) / %d', $selector, $step),
        });
    }

    /**
     * @param string $query
     * @param TimeGrid $grid
     *
     * @throws MetricsStorageException
     *
     * @return array<int, list<float>> values by "id" label, one per timestamp of the grid
     */
    private function queryRange(string $query, TimeGrid $grid): array
    {
        $settings = $this->settingsRepository->find();
        if ($settings->getVictoriaMetricsUrl() === '') {
            throw MetricsStorageException::missingUrl();
        }

        $options = [
            'body' => [
                'query' => $query,
                'start' => (string) $grid->getFirst(),
                'end' => (string) $grid->getLast(),
                'step' => $grid->getStep() . 's',
            ],
            'timeout' => $this->timeout,
            'max_duration' => $this->maxDuration,
        ];
        if ($settings->getVictoriaMetricsUser() !== '') {
            $options['auth_basic'] = [$settings->getVictoriaMetricsUser(), $settings->getVictoriaMetricsPassword()];
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                $settings->getVictoriaMetricsUrl() . '/api/v1/query_range',
                $options
            );
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (\Throwable $ex) {
            throw MetricsStorageException::requestFailed($ex);
        }

        $body = json_decode($content, true);
        if ($statusCode !== 200) {
            $detail = is_array($body) && is_string($body['error'] ?? null) ? $body['error'] : '';

            throw MetricsStorageException::unexpectedResponse($statusCode, $detail);
        }
        if (! is_array($body) || ($body['status'] ?? null) !== 'success' || ! is_array($body['data']['result'] ?? null)) {
            throw MetricsStorageException::invalidResponse('no "data.result" in a successful answer');
        }

        return self::mapResult($body['data']['result'], $grid);
    }

    /**
     * @param array<mixed> $result "data.result" of a matrix answer
     * @param TimeGrid $grid
     *
     * @throws MetricsStorageException
     *
     * @return array<int, list<float>>
     */
    private static function mapResult(array $result, TimeGrid $grid): array
    {
        $series = [];
        foreach ($result as $item) {
            $id = is_array($item) ? ($item['metric']['id'] ?? null) : null;
            if (! is_array($item) || ! is_string($id) || ! ctype_digit($id) || ! is_array($item['values'] ?? null)) {
                throw MetricsStorageException::invalidResponse('a series has no "id" label or no values');
            }
            $values = array_fill(0, $grid->count(), NAN);
            foreach ($item['values'] as $point) {
                if (! is_array($point) || ! isset($point[0], $point[1]) || ! is_numeric($point[0])) {
                    throw MetricsStorageException::invalidResponse('a point is not [timestamp, value]');
                }
                $index = $grid->indexOf((int) round((float) $point[0]));
                if ($index !== null) {
                    $values[$index] = self::toFloat($point[1]);
                }
            }
            $series[(int) $id] = array_values($values);
        }

        return $series;
    }

    /**
     * Prometheus values are strings, "NaN", "+Inf" and "-Inf" included.
     */
    private static function toFloat(mixed $value): float
    {
        return match (true) {
            is_numeric($value) => (float) $value,
            $value === '+Inf', $value === 'Inf' => INF,
            $value === '-Inf' => -INF,
            default => NAN,
        };
    }
}
