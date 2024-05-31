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

namespace Core\Metric\Application\UseCase\DownloadPerformanceMetrics;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\RealTime\Repository\ReadIndexDataRepositoryInterface;
use Core\Application\RealTime\Repository\ReadPerformanceDataRepositoryInterface;
use Core\Domain\RealTime\Model\IndexData;
use Core\Metric\Application\Exception\MetricException;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;

final class DownloadPerformanceMetrics
{
    use LoggerTrait;

    /**
     * @param ReadIndexDataRepositoryInterface $indexDataRepository
     * @param ReadMetricRepositoryInterface $metricRepository
     * @param ReadPerformanceDataRepositoryInterface $performanceDataRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadServiceRepositoryInterface $readServiceRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadIndexDataRepositoryInterface $indexDataRepository,
        readonly private ReadMetricRepositoryInterface $metricRepository,
        readonly private ReadPerformanceDataRepositoryInterface $performanceDataRepository,
        readonly private ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        readonly private ReadServiceRepositoryInterface $readServiceRepository,
        readonly private ContactInterface $user,
    ) {
    }

    /**
     * @param DownloadPerformanceMetricRequest $request
     * @param DownloadPerformanceMetricPresenterInterface $presenter
     */
    public function __invoke(
        DownloadPerformanceMetricRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_MONITORING_PERFORMANCES_RW)
                && ! $this->user->hasTopologyRole(Contact::ROLE_MONITORING_RESOURCES_STATUS_RW)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to download the performance metrics",
                    [
                        'user_id' => $this->user->getId(),
                        'host_id' => $request->hostId,
                        'service_id' => $request->serviceId]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(MetricException::downloadNotAllowed())
                );

                return;
            }

            $this->info(
                'Retrieve performance metrics',
                [
                    'host_id' => $request->hostId,
                    'service_id' => $request->serviceId,
                ]
            );

            if (! $this->user->isAdmin()) {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $this->debug('Filtering by access groups', ['access_groups' => $accessGroups]);
                $isServiceExists = $this->readServiceRepository->existsByAccessGroups(
                    $request->serviceId,
                    $accessGroups
                );
                if (! $isServiceExists) {
                    $this->error(
                        'Service not found',
                        ['host_id' => $request->hostId, 'service_id' => $request->serviceId]
                    );
                    $presenter->present(new NotFoundResponse('Service'));

                    return;
                }
            }
            $index = $this->indexDataRepository->findIndexByHostIdAndServiceId($request->hostId, $request->serviceId);
            $metrics = $this->metricRepository->findMetricsByIndexId($index);

            $performanceMetrics = $this->performanceDataRepository->findDataByMetricsAndDates(
                $metrics,
                $request->startDate,
                $request->endDate
            );

            $fileName = $this->generateDownloadFileNameByIndex($index);
            $this->info('Filename used to download metrics', ['filename' => $fileName]);
            $presenter->present(new DownloadPerformanceMetricResponse($performanceMetrics, $fileName));
        } catch (\Throwable $ex) {
            $this->error(
                'Impossible to retrieve performance metrics',
                [
                    'host_id' => $request->hostId,
                    'service_id' => $request->serviceId,
                    'error_message' => $ex->__toString(),
                ]
            );
            $presenter->setResponseStatus(
                new ErrorResponse('Impossible to retrieve performance metrics')
            );
        }
    }

    /**
     * @param int $index
     *
     * @return string
     */
    private function generateDownloadFileNameByIndex(int $index): string
    {
        $indexData = $this->indexDataRepository->findHostNameAndServiceDescriptionByIndex($index);

        if (! ($indexData instanceof IndexData)) {
            return (string) $index;
        }

        $hostName = $indexData->getHostName();
        $serviceDescription = $indexData->getServiceDescription();

        if ($hostName !== '' && $serviceDescription !== '') {
            return sprintf('%s_%s', $hostName, $serviceDescription);
        }

        return (string) $index;
    }
}
