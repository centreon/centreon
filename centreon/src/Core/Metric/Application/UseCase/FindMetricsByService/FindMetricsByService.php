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

namespace Core\Metric\Application\UseCase\FindMetricsByService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindMetricsByService
{
    use LoggerTrait;

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
     * @param int $hostId
     * @param int $serviceId
     * @param FindMetricsByServicePresenterInterface $presenter
     */
    public function __invoke(
        int $hostId,
        int $serviceId,
        FindMetricsByServicePresenterInterface $presenter
    ): void {
        try {
            $this->info('Finding metrics for service', ['id' => $serviceId]);
            if ($this->user->isAdmin()) {
                $metrics = $this->metricRepository->findByHostIdAndServiceId($hostId, $serviceId, $this->requestParameters);
            } else {
                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $metrics = $this->metricRepository->findByHostIdAndServiceIdAndAccessGroups(
                    $hostId,
                    $serviceId,
                    $accessGroups,
                    $this->requestParameters
                );
            }

            if ([] === $metrics) {
                $presenter->presentResponse(new NotFoundResponse('metrics'));
            } else {
                $presenter->presentResponse($this->createResponse($metrics));
            }
        } catch (\Throwable $ex) {
            $this->error('An error occured while finding metrics', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse('An error occured while finding metrics'));
        }
    }

    /**
     * Create Response.
     *
     * @param Metric[] $metrics
     *
     * @return FindMetricsByServiceResponse
     */
    private function createResponse(array $metrics): FindMetricsByServiceResponse
    {
        $response = new FindMetricsByServiceResponse();
        $metricsDto = [];
        foreach ($metrics as $metric) {
            $metricDto = new MetricDto();
            $metricDto->id = $metric->getId();
            $metricDto->unit = $metric->getUnit();
            $metricDto->name = $metric->getName();
            $metricDto->currentValue = $metric->getCurrentValue();
            $metricDto->warningHighThreshold = $metric->getWarningHighThreshold();
            $metricDto->warningLowThreshold = $metric->getWarningLowThreshold();
            $metricDto->criticalHighThreshold = $metric->getCriticalHighThreshold();
            $metricDto->criticalLowThreshold = $metric->getCriticalLowThreshold();
            $metricsDto[] = $metricDto;
        }
        $response->metricsDto = $metricsDto;

        return $response;
    }
}
