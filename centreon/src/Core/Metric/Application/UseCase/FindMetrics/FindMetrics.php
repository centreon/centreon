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

namespace Core\Metric\Application\UseCase\FindMetrics;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\AggregateResourceMetrics;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;


final class FindMetrics
{
    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadMetricRepositoryInterface $metricRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadMetricRepositoryInterface $metricRepository
    ) {
    }

    /**
     * @param FindMetricsPresenterInterface $presenter
     */
    public function __invoke(FindMetricsPresenterInterface $presenter): void
    {
        if ($this->user->isAdmin()) {
            $resourceMetrics = $this->metricRepository->findFilteredMetricsWithCount();
        } else {
            $accessGroups = $this->accessGroupRepository->findByContact($this->user);
            $resourceMetrics = $this->metricRepository->findFilteredMetricsWithCountAndAccessGroups($accessGroups);
        }
        $presenter->presentResponse($this->createResponse($resourceMetrics));
    }

    /**
     * @param AggregateResourceMetrics $aggregateResourceMetrics
     * @return FindMetricsResponse
     */
    private function createResponse(AggregateResourceMetrics $aggregateResourceMetrics): FindMetricsResponse
    {
        $response = new FindMetricsResponse();
        $response->count = $aggregateResourceMetrics->getCount();
        if ($aggregateResourceMetrics->getCount() < AggregateResourceMetrics::MAXIMUM_METRICS_COUNT) {
            foreach($aggregateResourceMetrics->getResourceMetrics() as $resourceMetrics) {
                $response->resourceMetrics[] = [
                    "serviceId" => $resourceMetrics->getServiceId(),
                    "resourceName" => $resourceMetrics->getResourceName(),
                    "metrics" => array_map(function (Metric $metric) {
                        return [
                            "id" => $metric->getId(),
                            "name" => $metric->getName(),
                        ];
                    }, $resourceMetrics->getMetrics())
                ];
            }
        }

        return $response;
    }
}