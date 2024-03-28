<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
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
     * @param DashboardRights $rights
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadDashboardPerformanceMetricRepositoryInterface $dashboardMetricRepository,
        private readonly DashboardRights $rights,
    ) {
    }

    /**
     * @param FindPerformanceMetricsPresenterInterface $presenter
     */
    public function __invoke(FindPerformanceMetricsPresenterInterface $presenter): void
    {
        try {
            if (! $this->rights->canAccess()) {
                $presenter->presentResponse(new ForbiddenResponse(
                    DashboardException::accessNotAllowed()->getMessage()
                ));

                return;
            }
            if ($this->user->isAdmin()) {
                $this->info('find metrics for admin user');

                $resourceMetrics = $this->dashboardMetricRepository->findByRequestParameters($this->requestParameters);
            } else {
                $this->info('find metrics for non-admin user');

                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $resourceMetrics = $this->dashboardMetricRepository->findByRequestParametersAndAccessGroups(
                    $this->requestParameters,
                    $accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($resourceMetrics));
        } catch (\Throwable $ex) {
            $this->error('An error occured while retrieving metrics', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse('An error occured while retrieving metrics'));

            return;
        }

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
            $resourceMetricDto = new ResourceMetricDto();
            $resourceMetricDto->serviceId = $resourceMetric->getServiceId();
            $resourceMetricDto->resourceName = $resourceMetric->getResourceName();
            $resourceMetricDto->parentName = $resourceMetric->getParentName();
            $resourceMetricDto->parentId = $resourceMetric->getParentId();
            $resourceMetricDto->metrics = array_map(
                fn (PerformanceMetric $metric) => [
                    'id' => $metric->getId(),
                    'name' => $metric->getName(),
                    'unit' => $metric->getUnit(),
                    'warning_high_threshold' => $metric->getWarningHighThreshold(),
                    'critical_high_threshold' => $metric->getCriticalHighThreshold(),
                    'warning_low_threshold' => $metric->getWarningLowThreshold(),
                    'critical_low_threshold' => $metric->getCriticalLowThreshold(),
                ],
                $resourceMetric->getMetrics()
            );
            $resourceMetricsResponse[] = $resourceMetricDto;
        }
        $response->resourceMetrics = $resourceMetricsResponse;

        return $response;
    }
}
