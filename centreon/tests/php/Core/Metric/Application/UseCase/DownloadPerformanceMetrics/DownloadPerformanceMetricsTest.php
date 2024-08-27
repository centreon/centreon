<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\Metric\Application\UseCase\DownloadPerformanceMetrics;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Domain\RealTime\Model\IndexData;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use DateTimeImmutable;
use Core\Metric\Domain\Model\MetricValue;
use Core\Metric\Domain\Model\PerformanceMetric;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Application\RealTime\Repository\ReadIndexDataRepositoryInterface;
use Core\Application\RealTime\Repository\ReadPerformanceDataRepositoryInterface;
use Core\Metric\Application\UseCase\DownloadPerformanceMetrics\DownloadPerformanceMetricPresenterInterface;
use Core\Metric\Application\UseCase\DownloadPerformanceMetrics\DownloadPerformanceMetrics;
use Core\Metric\Application\UseCase\DownloadPerformanceMetrics\DownloadPerformanceMetricRequest;
use Core\Metric\Application\UseCase\DownloadPerformanceMetrics\DownloadPerformanceMetricResponse;
use Tests\Core\Metric\Infrastructure\API\DownloadPerformanceMetrics\DownloadPerformanceMetricsPresenterStub;

beforeEach(function () {
    $this->hostId = 1;
    $this->serviceId = 2;
    $this->indexId = 15;
});

it('returns an error response if the user does not have access to the correct topology', function () {
    $indexDataRepository = $this->createMock(ReadIndexDataRepositoryInterface::class);
    $metricRepository = $this->createMock(ReadMetricRepositoryInterface::class);
    $performanceDataRepository = $this->createMock(ReadPerformanceDataRepositoryInterface::class);
    $readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class);
    $contact = $this->createMock(ContactInterface::class);
    $contact->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $useCase = new DownloadPerformanceMetrics(
        $indexDataRepository,
        $metricRepository,
        $performanceDataRepository,
        $readAccessGroupRepository,
        $readServiceRepository,
        $contact,
    );
    $performanceMetricRequest = new DownloadPerformanceMetricRequest(
            $this->hostId,
            $this->serviceId,
            new DateTimeImmutable('2022-01-01'),
            new DateTimeImmutable('2023-01-01')
        );
    $presenter = new DownloadPerformanceMetricsPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $useCase($performanceMetricRequest, $presenter);
    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class);
});

it(
    'download file name is properly generated',
    function (string $hostName, string $serviceDescription, string $expectedFileName): void {
        $indexData = new IndexData($hostName, $serviceDescription);

        $indexDataRepository = $this->createMock(ReadIndexDataRepositoryInterface::class);
        $indexDataRepository
            ->expects($this->once())
            ->method('findIndexByHostIdAndServiceId')
            ->with(
                $this->equalTo($this->hostId),
                $this->equalTo($this->serviceId),
            )
            ->willReturn($this->indexId);

        $indexDataRepository
            ->expects($this->once())
            ->method('findHostNameAndServiceDescriptionByIndex')
            ->willReturn($indexData);

        $metricRepository = $this->createMock(ReadMetricRepositoryInterface::class);
        $performanceDataRepository = $this->createMock(ReadPerformanceDataRepositoryInterface::class);
        $presenter = $this->createMock(DownloadPerformanceMetricPresenterInterface::class);
        $readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
        $readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class);
        $contact = $this->createMock(ContactInterface::class);
        $contact->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $performanceMetricRequest = new DownloadPerformanceMetricRequest(
            $this->hostId,
            $this->serviceId,
            new DateTimeImmutable('2022-01-01'),
            new DateTimeImmutable('2023-01-01')
        );

        $useCase = new DownloadPerformanceMetrics(
            $indexDataRepository,
            $metricRepository,
            $performanceDataRepository,
            $readAccessGroupRepository,
            $readServiceRepository,
            $contact,
        );

        $useCase($performanceMetricRequest, $presenter);
    })->with([
        ['Centreon-Server', 'Ping', 'Centreon-Server_Ping'],
        ['',                'Ping', '15'],
        ['Centreon-Server', '',     '15'],
        ['',                '',     '15'],
        ]
);;

it(
    'validate presenter response',
    function (iterable $performanceData, DownloadPerformanceMetricResponse $expectedResponse) {
        $indexDataRepository = $this->createMock(ReadIndexDataRepositoryInterface::class);
        $indexDataRepository
            ->expects($this->once())
            ->method('findIndexByHostIdAndServiceId')
            ->with(
                $this->equalTo($this->hostId),
                $this->equalTo($this->serviceId),
            )
            ->willReturn($this->indexId);
        $indexDataRepository
            ->expects($this->once())
            ->method('findHostNameAndServiceDescriptionByIndex')
            ->willReturn(null);

        $metricRepository = $this->createMock(ReadMetricRepositoryInterface::class);
        $performanceDataRepository = $this->createMock(ReadPerformanceDataRepositoryInterface::class);
        $performanceDataRepository
            ->expects($this->once())
            ->method('findDataByMetricsAndDates')
            ->willReturn($performanceData);

        $presenter = $this->createMock(DownloadPerformanceMetricPresenterInterface::class);
        $readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
        $readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class);
        $contact = $this->createMock(ContactInterface::class);
        $contact->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $presenter
            ->expects($this->once())
            ->method('present')
            ->with($this->equalTo($expectedResponse));

        $performanceMetricRequest = new DownloadPerformanceMetricRequest(
            $this->hostId,
            $this->serviceId,
            new DateTimeImmutable('2022-02-01'),
            new DateTimeImmutable('2023-01-01')
        );

        $useCase = new DownloadPerformanceMetrics(
            $indexDataRepository,
            $metricRepository,
            $performanceDataRepository,
            $readAccessGroupRepository,
            $readServiceRepository,
            $contact,
        );
        $useCase($performanceMetricRequest, $presenter);
    }
)->with([
    [
        [['rta' => 0.01]],
        new DownloadPerformanceMetricResponse(
            [
                new PerformanceMetric(
                    new DateTimeImmutable(),
                    [new MetricValue('rta', 0.001)]
                )
            ],
            '15'
        )
    ],
    [
        [['rta' => 0.01], ['pl' => 0.02]],
        new DownloadPerformanceMetricResponse(
            [
                new PerformanceMetric(
                    new DateTimeImmutable(),
                    [
                        new MetricValue('rta', 0.001),
                        new MetricValue('pl', 0.002),
                    ]
                ),
            ],
            '15'
        )
    ]
]);
