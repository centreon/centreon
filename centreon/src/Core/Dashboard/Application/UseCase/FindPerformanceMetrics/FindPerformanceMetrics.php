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
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final readonly class FindPerformanceMetrics
{
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ContactInterface $user
     * @param RequestParametersInterface $requestParameters
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadDashboardPerformanceMetricRepositoryInterface $dashboardMetricRepository
     * @param DashboardRights $rights
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private ContactInterface $user,
        private RequestParametersInterface $requestParameters,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private ReadDashboardPerformanceMetricRepositoryInterface $dashboardMetricRepository,
        private DashboardRights $rights,
        private bool $isCloudPlatform
    ) {
    }

    /**
     * @param FindPerformanceMetricsPresenterInterface $presenter
     */
    public function __invoke(FindPerformanceMetricsPresenterInterface $presenter): void
    {
        try {
            if ($this->isUserAdmin()) {
                $resourceMetrics = $this->dashboardMetricRepository->findByRequestParameters($this->requestParameters);
            } elseif ($this->rights->canAccess()) {
                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $resourceMetrics = $this->dashboardMetricRepository->findByRequestParametersAndAccessGroups(
                    $this->requestParameters,
                    $accessGroups
                );
            } else {
                $presenter->presentResponse(new ForbiddenResponse(
                    DashboardException::accessNotAllowed()->getMessage()
                ));

                return;
            }

            $presenter->presentResponse($this->createResponse($resourceMetrics));
        } catch (\Throwable $e) {
            $presenter->presentResponse(
                new ErrorResponse(
                    message: 'An error occured while retrieving metrics',
                    context: [
                        'request_parameters' => $this->requestParameters->toArray(),
                        'user_id' => $this->user->getId(),
                        'is_admin' => $this->user->isAdmin(),
                        'access_groups' => $accessGroups ?? null,
                    ],
                    exception: $e
                )
            );

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

    /**
     * @throws \Throwable
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->rights->hasAdminRole()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform;
    }
}
