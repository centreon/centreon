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

namespace Core\Dashboard\Infrastructure\API\FindPerformanceMetricsData;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataPresenterInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataResponse;
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Metric\Domain\Model\MetricInformation\MetricInformation;
use Symfony\Component\HttpFoundation\JsonResponse;
use function array_map;

class FindPerformanceMetricsDataPresenter extends AbstractPresenter implements FindPerformanceMetricsDataPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
        if ($presenterFormatter instanceof JsonFormatter) {
            $presenterFormatter->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRESERVE_ZERO_FRACTION);
        }
    }

    public function presentResponse(FindPerformanceMetricsDataResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'base' => $response->base,
                'metrics' => array_map($this->formatMetricInformation(...), $response->metricsInformation),
                'times' => array_map(fn($date) => $this->formatDateToIso8601($date), $response->times),
            ]);
        }
    }

    /**
     * Format Metric information to array.
     *
     * @param MetricInformation $metricInformation
     *
     * @return array{
     *     metric_id: int,
     *     metric: string,
     *     metric_legend: string,
     *     unit: string,
     *     min: float|null,
     *     max: float|null,
     *     ds_data: array{
     *         ds_color_line: string,
     *         ds_color_area: string|null,
     *         ds_filled: bool,
     *         ds_invert: bool,
     *         ds_legend: string|null,
     *         ds_stack: bool,
     *         ds_order: int|null,
     *         ds_transparency: float|null,
     *         ds_color_line_mode: int
     *     },
     *     legend: string,
     *     stack: int<0,1>,
     *     warning_high_threshold: float|null,
     *     critical_high_threshold: float|null,
     *     warning_low_threshold: float|null,
     *     critical_low_threshold: float|null,
     *     ds_order: int,
     *     data: array<float|null>,
     *     last_value: float|null,
     *     minimum_value: float|null,
     *     maximum_value: float|null,
     *     average_value: float|null,
     * }
     */
    private function formatMetricInformation(MetricInformation $metricInformation): array
    {
        $generalInformation = $metricInformation->getGeneralInformation();
        $dataSource = $metricInformation->getDataSource();
        $thresholdInformation = $metricInformation->getThresholdInformation();
        $realTimeDataInformation = $metricInformation->getRealTimeDataInformation();

        return [
            'metric_id' => $generalInformation->getId(),
            'metric' => $generalInformation->getName(),
            'metric_legend' => $generalInformation->getAlias(),
            'unit' => $generalInformation->getUnit(),
            'min' => $realTimeDataInformation->getMinimumValueLimit(),
            'max' => $realTimeDataInformation->getMaximumValueLimit(),
            'ds_data' => [
                'ds_color_line' => $dataSource->getLineColor(),
                'ds_color_area' => $dataSource->getColorArea(),
                'ds_filled' => $dataSource->isFilled(),
                'ds_invert' => $dataSource->isInverted(),
                'ds_legend' => $dataSource->getLegend(),
                'ds_stack' => $dataSource->isStacked(),
                'ds_order' => $dataSource->getOrder(),
                'ds_transparency' => $dataSource->getTransparency(),
                'ds_color_line_mode' => $dataSource->getColorMode(),
            ],
            'legend' => $generalInformation->getLegend(),
            'stack' => (int) $generalInformation->isStacked(),
            'warning_high_threshold' => $thresholdInformation->getWarningThreshold(),
            'critical_high_threshold' => $thresholdInformation->getCriticalThreshold(),
            'warning_low_threshold' => $thresholdInformation->getWarningLowThreshold(),
            'critical_low_threshold' => $thresholdInformation->getCriticalLowThreshold(),
            'ds_order' => $generalInformation->getStackingOrder(),
            'data' => $realTimeDataInformation->getValues(),
            'last_value' => $realTimeDataInformation->getLastValue(),
            'minimum_value' => $realTimeDataInformation->getMinimumValue(),
            'maximum_value' => $realTimeDataInformation->getMaximumValue(),
            'average_value' => $realTimeDataInformation->getAverageValue(),
        ];
    }
}
