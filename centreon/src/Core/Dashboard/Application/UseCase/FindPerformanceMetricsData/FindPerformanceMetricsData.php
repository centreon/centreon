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
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetricsData;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;

final class FindPerformanceMetricsData
{
    use LoggerTrait;

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
                $this->info('Retrieving metrics data for admin user', [
                    'user_id' => $this->user->getId(),
                    'metric_ids' => implode(', ', $request->metricIds),
                ]);
                $performanceMetricsData = $this->findPerformanceMetricsDataAsAdmin($request);
                $presenter->presentResponse($this->createResponse($performanceMetricsData));
            }  
                // $this->findPerformanceMetricsDataAsNonAdmin($request);
            
        } catch (\Throwable $ex) {
            $this->error('An error occured while retrieving metrics data', ['trace' => (string) $ex]);
        }
    }

    /**
     * find Performance Metrics Data for an admin user.
     *
     * @param FindPerformanceMetricsDataRequest $request
     *
     * @return PerformanceMetricsData
     */
    private function findPerformanceMetricsDataAsAdmin(
        FindPerformanceMetricsDataRequest $request
    ): PerformanceMetricsData {
        $services = $this->metricRepository->findServicesByMetricIds($request->metricIds);
        $metricsData = [];
        foreach ($services as $service) {
            /**
             * array<int<0, max>, array>.
             */
            $metricsData[] = $this->metricRepositoryLegacy
                ->setContact($this->user)
                ->findMetricsByService($service, $request->startDate, $request->endDate);
        }
        $factory = new PerformanceMetricsDataFactory();

        return $factory->createFromRecords($metricsData, $request->metricIds);
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