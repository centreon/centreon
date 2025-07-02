<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Application\UseCase\FindSingleMetric;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindSingleMetric
{
    /**
     * @param ContactInterface $user
     * @param ReadMetricRepositoryInterface $metricRepository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        private ContactInterface $user,
        private ReadMetricRepositoryInterface $metricRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private RequestParametersInterface $requestParameters
    ) {
    }

    /**
     * @param FindSingleMetricRequest $request
     * @param FindSingleMetricPresenterInterface $presenter
     */
    public function __invoke(
        FindSingleMetricRequest $request,
        FindSingleMetricPresenterInterface $presenter
    ): void {
        try {
            if ($this->user->isAdmin()) {
                $metric = $this->metricRepository->findSingleMetricValue(
                    $request->hostId,
                    $request->serviceId,
                    $request->metricName,
                    $this->requestParameters
                );
            } else {
                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $metric = $this->metricRepository->findSingleMetricValue(
                    $request->hostId,
                    $request->serviceId,
                    $request->metricName,
                    $this->requestParameters,
                    $accessGroups
                );
            }

            if ($metric !== null) {
                $presenter->presentResponse(
                    $this->createResponse($metric)
                );
            } else {
                $presenter->presentResponse(new NotFoundResponse(
                    'Metric not found',
                    [
                        'host_id' => $request->hostId,
                        'service_id' => $request->serviceId,
                        'metric_name' => $request->metricName,
                        'user_id' => $this->user->getId(),
                    ]
                ));
            }
        } catch (RepositoryException|\Throwable $exception) {
            $presenter->presentResponse(new ErrorResponse(
                'Error while retrieving metric : ' . $exception->getMessage(),
                [
                    'host_id' => $request->hostId,
                    'service_id' => $request->serviceId,
                    'metric_name' => $request->metricName,
                    'user_id' => $this->user->getId(),
                ],
                $exception
            ));
        }
    }

    /**
     * Create Response.
     *
     * @param Metric $metric
     *
     * @return FindSingleMetricResponse
     */
    private function createResponse(Metric $metric): FindSingleMetricResponse
    {
        $dto = new MetricDto();
        $dto->id = $metric->getId();
        $dto->name = $metric->getName();
        $dto->unit = $metric->getUnit();
        $dto->currentValue = $metric->getCurrentValue();
        $dto->warningHighThreshold = $metric->getWarningHighThreshold();
        $dto->warningLowThreshold = $metric->getWarningLowThreshold();
        $dto->criticalHighThreshold = $metric->getCriticalHighThreshold();
        $dto->criticalLowThreshold = $metric->getCriticalLowThreshold();

        return new FindSingleMetricResponse($dto);
    }
}