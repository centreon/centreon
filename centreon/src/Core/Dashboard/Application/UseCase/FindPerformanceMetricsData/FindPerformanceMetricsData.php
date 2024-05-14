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
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly MetricRepositoryInterface $metricRepositoryLegacy,
        private readonly ReadMetricRepositoryInterface $metricRepository,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly DashboardRights $rights,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(
        FindPerformanceMetricsDataPresenterInterface $presenter,
        FindPerformanceMetricsDataRequest $request
    ): void {
        try {
            if ($this->isUserAdmin()) {
                $this->info('Retrieving metrics data for admin user', [
                    'user_id' => $this->user->getId(),
                    'metric_names' => implode(', ', $request->metricNames),
                ]);

                $performanceMetricsData = $this->findPerformanceMetricsDataAsAdmin($request);
            } elseif ($this->rights->canAccess()) {
                $this->info('Retrieving metrics data for non admin user', [
                    'user_id' => $this->user->getId(),
                    'metric_names' => implode(', ', $request->metricNames),
                ]);

                $accessGroups = $this->accessGroupRepository->findByContact($this->user);
                $performanceMetricsData = $this->findPerformanceMetricsDataAsNonAdmin($request, $accessGroups);
            } else {
                $presenter->presentResponse(new ForbiddenResponse(
                    DashboardException::accessNotAllowed()->getMessage()
                ));

                return;
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
