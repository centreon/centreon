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

namespace Core\Dashboard\Application\UseCase\FindMetricsTop;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindMetricsTop\Response\MetricInformationDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindMetricsTop
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
     * @param FindMetricsTopPresenterInterface $presenter
     */
    public function __invoke(FindMetricsTopPresenterInterface $presenter): void
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
                $resourceMetrics = $this->dashboardMetricRepository->FindByRequestParametersAndAccessGroups(
                    $this->requestParameters,
                    $accessGroups
                );
            }

            if ([] === $resourceMetrics) {
                $presenter->presentResponse(new NotFoundResponse('metrics'));
            }

            $presenter->presentResponse($this->createResponse($resourceMetrics));
        } catch (\Throwable $ex) {
            $this->error('An error occured while retrieving metrics', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse('An error occured while retrieving top metrics'));

            return;
        }
    }

    /**
     * Create FindMetricsTopResponse DTO.
     *
     * @param ResourceMetric[] $resourceMetrics
     * @return FindMetricsTopResponse
     */
    private function createResponse(array $resourceMetrics): FindMetricsTopResponse
    {
        $response = new FindMetricsTopResponse();
        $response->metricName = $resourceMetrics[0]->getMetrics()[0]->getName();
        $response->metricUnit = $resourceMetrics[0]->getMetrics()[0]->getUnit();
        $response->resourceMetrics = array_map(function ($resourceMetric) {
            $metricInformation = new MetricInformationDto();
            $metric = $resourceMetric->getMetrics()[0];
            $metricInformation->serviceId = $resourceMetric->getServiceId();
            $metricInformation->resourceName = $resourceMetric->getResourceName();
            $metricInformation->currentValue = $metric->getCurrentValue();
            $metricInformation->warningHighThreshold = $metric->getWarningHighThreshold();
            $metricInformation->criticalHighThreshold = $metric->getCriticalHighThreshold();
            $metricInformation->warningLowThreshold = $metric->getWarningLowThreshold();
            $metricInformation->criticalLowThreshold = $metric->getCriticalLowThreshold();
            $metricInformation->minimumValue = $metric->getMinimumValue();
            $metricInformation->maximumValue = $metric->getMaximumValue();

            return $metricInformation;
        }, $resourceMetrics);


        return $response;
    }
}