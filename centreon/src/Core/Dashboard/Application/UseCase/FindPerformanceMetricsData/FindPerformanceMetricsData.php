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

namespace Core\Dashboard\Application\UseCase\FindPerformanceMetricsData;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Centreon\Domain\Monitoring\Service;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, InvalidArgumentResponse};
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetricsData;
use Core\Metric\Application\Exception\MetricException;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * @phpstan-import-type _MetricData from PerformanceMetricsDataFactory
 */
final class FindPerformanceMetricsData
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly MetricRepositoryInterface $metricRepositoryLegacy,
        private readonly ReadMetricRepositoryInterface $metricRepository,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly DashboardRights $rights
    ) {
    }

    public function __invoke(
        FindPerformanceMetricsDataPresenterInterface $presenter,
        FindPerformanceMetricsDataRequest $request
    ): void {
        try {
            if (! $this->rights->canAccess()) {
                $presenter->presentResponse(new ForbiddenResponse(
                    DashboardException::accessNotAllowed()->getMessage()
                ));

                return;
            }
            if ($this->user->IsAdmin()) {
                $this->info('Retrieving metrics data for admin user', [
                    'user_id' => $this->user->getId(),
                    'metric_names' => implode(', ', $request->metricNames),
                ]);

                $performanceMetricsData = $this->findPerformanceMetricsDataAsAdmin($request);
            } else {
                $this->info('Retrieving metrics data for non admin user', [
                    'user_id' => $this->user->getId(),
                    'metric_names' => implode(', ', $request->metricNames),
                ]);

                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $performanceMetricsData = $this->findPerformanceMetricsDataAsNonAdmin($request, $accessGroups);
            }
            $presenter->presentResponse($this->createResponse($performanceMetricsData));
        } catch (MetricException $ex) {
            $this->error('Metric from RRD are not correctly formatted', ['trace' => (string) $ex]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error('An error occurred while retrieving metrics data', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse('An error occurred while retrieving metrics data'));
        }
    }

    /**
     * find Performance Metrics Data for an admin user.
     *
     * @param FindPerformanceMetricsDataRequest $request
     *
     * @throws MetricException
     * @throws \Throwable
     *
     * @return PerformanceMetricsData
     */
    private function findPerformanceMetricsDataAsAdmin(
        FindPerformanceMetricsDataRequest $request
    ): PerformanceMetricsData {
        $services = $this->metricRepository->findServicesByMetricNamesAndRequestParameters(
            $request->metricNames,
            $this->requestParameters
        );

        return $this->createPerformanceMetricsData($services, $request);
    }

    /**
     * find Performance Metrics Data for an admin user.
     *
     * @param FindPerformanceMetricsDataRequest $request
     * @param AccessGroup[] $accessGroups
     *
     * @throws MetricException
     * @throws \Throwable
     *
     * @return PerformanceMetricsData
     */
    private function findPerformanceMetricsDataAsNonAdmin(
        FindPerformanceMetricsDataRequest $request,
        array $accessGroups
    ): PerformanceMetricsData {
        $services = $this->metricRepository->findServicesByMetricNamesAndAccessGroupsAndRequestParameters(
            $request->metricNames,
            $accessGroups,
            $this->requestParameters
        );

        return $this->createPerformanceMetricsData($services, $request);
    }

    private function createResponse(PerformanceMetricsData $performanceMetricsData): FindPerformanceMetricsDataResponse
    {
        $response = new FindPerformanceMetricsDataResponse();
        $response->base = $performanceMetricsData->getBase();
        $response->metricsInformation = $performanceMetricsData->getMetricsInformation();
        $response->times = $performanceMetricsData->getTimes();

        return $response;
    }

    /**
     * @param Service[] $services
     * @param FindPerformanceMetricsDataRequest $request
     *
     * @throws MetricException|\Exception
     *
     * @return PerformanceMetricsData
     */
    private function createPerformanceMetricsData(
        array $services,
        FindPerformanceMetricsDataRequest $request
    ): PerformanceMetricsData {
        $metricsData = [];
        $this->metricRepositoryLegacy->setContact($this->user);
        foreach ($services as $service) {
            /** @var _MetricData $data */
            $data = $this->metricRepositoryLegacy->findMetricsByService(
                $service,
                $request->startDate,
                $request->endDate
            );
            $metricsData[] = $data;
        }

        return (new PerformanceMetricsDataFactory())
            ->createFromRecords($metricsData, $request->metricNames);
    }
}
