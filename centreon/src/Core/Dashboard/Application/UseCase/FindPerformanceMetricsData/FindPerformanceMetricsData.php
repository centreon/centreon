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

namespace Core\Dashboard\Application\UseCase\FindPerformanceMetricsData;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetricsData;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;

final class FindPerformanceMetricsData
{
    public function __construct(
        private readonly ContactInterface $user,
        private readonly MetricRepositoryInterface $metricRepositoryLegacy,
        private readonly ReadMetricRepositoryInterface $metricRepository
    ) {
    }

    public function __invoke(
        FindPerformanceMetricsDataPresenterInterface $presenter,
        FindPerformanceMetricsDataRequest $request
    ): void {
        try {
            if ($this->user->IsAdmin()) {
                $performanceMetricsData = $this->findPerformanceMetricsDataAsAdmin($request);
                $presenter->presentResponse($this->createResponse($performanceMetricsData));
            } else {
                // $this->findPerformanceMetricsDataAsNonAdmin($request);
            }
        } catch(\Throwable $ex) {

        }
    }

    /**
     * Undocumented function
     *
     * @param FindPerformanceMetricsDataRequest $request
     * @return PerformanceMetricsData
     */
    private function findPerformanceMetricsDataAsAdmin(
        FindPerformanceMetricsDataRequest $request
    ): PerformanceMetricsData {
        $services = $this->metricRepository->findServicesByMetricIds($request->metricIds);
        $metricsData = [];
        foreach($services as $service) {
            $metricsData[] = $this->metricRepositoryLegacy
                ->setContact($this->user)
                ->findMetricsByService($service, $request->startDate, $request->endDate);
        }
        $factory = new PerformanceMetricsDataFactory();
        $metricsData = $factory->createFromRecords($metricsData, $request->metricIds);
        return $metricsData;
    }

    private function createResponse(PerformanceMetricsData $performanceMetricsData): FindPerformanceMetricsDataResponse
    {
        $response = new FindPerformanceMetricsDataResponse();
        $response->base = $performanceMetricsData->getBase();
        $response->metricsData = $performanceMetricsData->getMetricsData();
        $response->times = $performanceMetricsData->getTimes();

        return $response;
    }
}