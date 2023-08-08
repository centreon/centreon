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

namespace Tests\Core\Dashboard\Application\UseCase\FindPerformanceMetricsData;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Monitoring\Host;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Centreon\Domain\Monitoring\Service;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsData;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataRequest;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataResponse;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function () {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->metricRepositoryLegacy = $this->createMock(MetricRepositoryInterface::class);
    $this->metricRepository = $this->createMock(ReadMetricRepositoryInterface::class);
});

it('should present a ForbiddenResponse when the user does not has sufficient rights', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricIds = [1,2,3];
    $useCase = new FindPerformanceMetricsData(
        $this->nonAdminUser,
        $this->metricRepositoryLegacy,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->rights
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(false);

    $useCase($presenter, $request);

    $this->expect($presenter->data)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->data->getMessage())->toBe(DashboardException::accessNotAllowed()->getMessage());

});

it('should present an ErrorResponse when an error occurs', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricIds = [1,2,3];
    $useCase = new FindPerformanceMetricsData(
        $this->nonAdminUser,
        $this->metricRepositoryLegacy,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->rights
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findServicesByMetricIdsAndAccessGroups')
        ->willThrowException(new \Exception());

    $useCase($presenter, $request);

    $this->expect($presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())->toBe('An error occured while retrieving metrics data');
});

it('should get the metrics with access group management when the user is not admin', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricIds = [1,2,3];
    $useCase = new FindPerformanceMetricsData(
        $this->nonAdminUser,
        $this->metricRepositoryLegacy,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->rights
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findServicesByMetricIdsAndAccessGroups');

    $useCase($presenter, $request);
});

it('should get the metrics without access group management when the user is admin', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricIds = [1,2,3];
    $useCase = new FindPerformanceMetricsData(
        $this->adminUser,
        $this->metricRepositoryLegacy,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->rights
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findServicesByMetricIds');

    $useCase($presenter, $request);
});

it('should present a FindPerformanceMetricsDataResponse when metrics are correctly retrieve', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricIds = [1,3];
    $useCase = new FindPerformanceMetricsData(
        $this->adminUser,
        $this->metricRepositoryLegacy,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->rights
    );
    $service = (new Service())
        ->setId(1)
        ->setHost(
            (new Host())->setId(2)
        );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findServicesByMetricIds')
        ->willReturn([$service]);

    $this->metricRepositoryLegacy
        ->expects($this->once())
        ->method('setContact')
        ->with($this->adminUser);

    $this->metricRepositoryLegacy
        ->expects($this->once())
        ->method('findMetricsByService')
        ->with($service, $request->startDate, $request->endDate)
        ->willReturn(
            [
                'global' => [
                    'base' => 1000
                ],
                'metrics' => [
                    [
                        'metric_id' => 1,
                        'metric_name' => 'pl',
                    ],
                    [
                        'metric_id' => 2,
                        'metric_name' => 'rta',
                    ],
                    [
                        'metric_id' => 3,
                        'metric_name' => 'rtmin'
                    ]
                ],
                'times' => [
                    "1690732800",
                    "1690790400"
                ]
            ]
        );

    $useCase($presenter, $request);

    $this->expect($presenter->data)->toBeInstanceOf(FindPerformanceMetricsDataResponse::class);
    $this->expect($presenter->data->base)->toBe(1000);
    $this->expect($presenter->data->metricsData)->toBe([
        [
            'metric_id' => 1,
            'metric_name' => 'pl',
        ],
        [
            'metric_id' => 3,
            'metric_name' => 'rtmin'
        ]
    ]);
    $this->expect($presenter->data->times)->toBe([
        "1690732800",
        "1690790400"
    ]);
});
