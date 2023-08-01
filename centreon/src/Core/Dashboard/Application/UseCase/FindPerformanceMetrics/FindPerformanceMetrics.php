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

namespace Core\Dashboard\Application\UseCase\FindPerformanceMetrics;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;


final class FindPerformanceMetrics
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadMetricRepositoryInterface $metricRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadDashboardPerformanceMetricRepositoryInterface $dashboardMetricRepository,
    ) {
    }

    /**
     * @param FindMetricsPresenterInterface $presenter
     */
    public function __invoke(FindPerformanceMetricsPresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $resourceMetrics = $this->dashboardMetricRepository->findByRequestParametersWithCount($this->requestParameters);
            } else {
                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $resourceMetrics = $this->dashboardMetricRepository->FindByRequestParametersAndAccessGroupsWithCount($this->requestParameters, $accessGroups);
            }
        } catch (\Throwable $ex) {
            $this->error('An error occured while retrieving metrics', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse('An error occured while retrieving metrics'));
            return;
        }

        $presenter->presentResponse($this->createResponse($resourceMetrics));
    }

    /**
     * @param ResourceMetric[] $resourceMetrics
     * @return FindMetricsResponse
     */
    private function createResponse(array $resourceMetrics): FindPerformanceMetricsResponse
    {
        $response = new FindPerformanceMetricsResponse();
        $resourceMetricsResponse = [];
        foreach($resourceMetrics as $resourceMetric) {
            $resourceMetricDTO = new ResourceMetricDTO();
            $resourceMetricDTO->serviceId = $resourceMetric->getServiceId();
            $resourceMetricDTO->resourceName = $resourceMetric->getResourceName();
            $resourceMetricDTO->metrics = array_map(function (PerformanceMetric $metric) {
                return [
                    "id" => $metric->getId(),
                    "name" => $metric->getName(),
                    "unit" => $metric->getUnit()
                ];
            }, $resourceMetric->getMetrics());
            $resourceMetricsResponse[] = $resourceMetricDTO;
        }
        $response->resourceMetrics = $resourceMetricsResponse;
        return $response;
    }
}