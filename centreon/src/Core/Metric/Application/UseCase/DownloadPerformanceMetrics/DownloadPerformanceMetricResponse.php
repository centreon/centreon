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

namespace Core\Metric\Application\UseCase\DownloadPerformanceMetrics;

use Core\Metric\Domain\Model\PerformanceMetric;

class DownloadPerformanceMetricResponse
{
    /** @var PerformanceMetric[] */
    public iterable $performanceMetrics = [];

    public string $filename;

    /**
     * @param iterable $performanceMetrics
     * @param string $filename
     */
    public function __construct(iterable $performanceMetrics, string $filename)
    {
        $this->performanceMetrics = $this->performanceMetricToArray($performanceMetrics);
        $this->filename = $filename;
    }

    /**
     * @param iterable<PerformanceMetric> $performanceMetrics
     *
     * @return iterable<mixed>
     */
    private function performanceMetricToArray(iterable $performanceMetrics): iterable
    {
        foreach ($performanceMetrics as $performanceMetric) {
            yield $this->formatPerformanceMetric($performanceMetric);
        }
    }

    /**
     * @param PerformanceMetric $performanceMetric
     *
     * @return array<string, mixed>
     */
    private function formatPerformanceMetric(PerformanceMetric $performanceMetric): array
    {
        $formattedData = [
            'time' => $performanceMetric->getDateValue()->getTimestamp(),
            'humantime' => $performanceMetric->getDateValue()->format('Y-m-d H:i:s'),
        ];

        foreach ($performanceMetric->getMetricValues() as $metricValue) {
            $formattedData[$metricValue->getName()] = sprintf('%f', $metricValue->getValue());
        }

        return $formattedData;
    }
}
