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
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindPerformanceMetrics
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param RequestParametersInterface $requestParameters
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadDashboardPerformanceMetricRepositoryInterface $dashboardMetricRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadDashboardPerformanceMetricRepositoryInterface $dashboardMetricRepository,
    ) {
    }

    /**
     * @param FindPerformanceMetricsPresenterInterface $presenter
     */
    public function __invoke(FindPerformanceMetricsPresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $this->info('find metrics for admin user');
                $resourceMetrics = $this->dashboardMetricRepository->findByRequestParameters($this->requestParameters);
            } else {
                $this->info('find metrics for non-admin user');
                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $resourceMetrics = $this->dashboardMetricRepository->FindByRequestParametersAndAccessGroups($this->requestParameters, $accessGroups);
            }
        } catch (\Throwable $ex) {
            $this->error('An error occured while retrieving metrics', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse('An error occured while retrieving metrics'));

            return;
        }

        $presenter->presentResponse($this->createResponse($resourceMetrics));
    }

    /**
     * Create Response.
     *
     * @param ResourceMetric[] $resourceMetrics
     *
     * @return FindPerformanceMetricsResponse
     */
    private function createResponse(array $resourceMetrics): FindPerformanceMetricsResponse
    {
        $response = new FindPerformanceMetricsResponse();
        $resourceMetricsResponse = [];
        foreach ($resourceMetrics as $resourceMetric) {
            $resourceMetricDTO = new ResourceMetricDTO();
            $resourceMetricDTO->serviceId = $resourceMetric->getServiceId();
            $resourceMetricDTO->resourceName = $resourceMetric->getResourceName();
            $resourceMetricDTO->metrics = array_map(
                fn (PerformanceMetric $metric) => [
                    'id' => $metric->getId(),
                    'name' => $metric->getName(),
                    'unit' => $metric->getUnit(),
                ],
                $resourceMetric->getMetrics()
            );
            $resourceMetricsResponse[] = $resourceMetricDTO;
        }
        $response->resourceMetrics = $resourceMetricsResponse;

        return $response;
    }
}