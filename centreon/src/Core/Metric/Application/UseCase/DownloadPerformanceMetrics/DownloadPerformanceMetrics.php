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

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\RealTime\Repository\ReadIndexDataRepositoryInterface;
use Core\Application\RealTime\Repository\ReadPerformanceDataRepositoryInterface;
use Core\Domain\RealTime\Model\IndexData;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;

final class DownloadPerformanceMetrics
{
    use LoggerTrait;

    /**
     * @param ReadIndexDataRepositoryInterface $indexDataRepository
     * @param ReadMetricRepositoryInterface $metricRepository
     * @param ReadPerformanceDataRepositoryInterface $performanceDataRepository
     */
    public function __construct(
        private ReadIndexDataRepositoryInterface $indexDataRepository,
        private ReadMetricRepositoryInterface $metricRepository,
        private ReadPerformanceDataRepositoryInterface $performanceDataRepository
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
            $this->debug(
                'Retrieve performance metrics',
                [
                    'host_id' => $request->hostId,
                    'service_id' => $request->serviceId,
                ]
            );

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
