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
                'metrics' => $this->formatMetricsInformation($response->metricsInformation),
                'times' => array_map(fn ($date) => $this->formatDateToIso8601($date), $response->times),
            ]);
        }
    }

    /**
     * format Metrics information to array.
     *
     * @param MetricInformation[] $metricsInformation
     *
     * @return array<string,int|string|null|array<string|null>>
     */
    private function formatMetricsInformation(array $metricsInformation): array
    {
        return array_map( function (MetricInformation $metricInformation) {
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
                    'ds_color_line' => $dataSource->getLineColor(),                ],
                'legend' => $generalInformation->getLegend(),
                'stack' => (int) $generalInformation->isStacked(),
                'warn' => $thresholdInformation->getWarningThreshold(),
                'crit' => $thresholdInformation->getCriticalThreshold(),
                'ds_order' => $generalInformation->getStackingOrder(),
                'data' => $realTimeDataInformation->getValues(),
                'last_value' => $realTimeDataInformation->getLastValue(),
                'minimum_value' => $realTimeDataInformation->getMinimumValue(),
                'maximum_value' => $realTimeDataInformation->getMaximumValue(),
                'average_value' => $realTimeDataInformation->getAverageValue(),
            ];
        }, $metricsInformation);
    }
}
