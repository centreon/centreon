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

namespace Tests\Core\Dashboard\Application\UseCase\FindPerformanceMetrics;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\FindPerformanceMetrics;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\FindPerformanceMetricsResponse;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\ResourceMetricDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function(): void {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readDashboardPerformanceMetric = $this->createMock(ReadDashboardPerformanceMetricRepositoryInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->isCloudPlatform = false;
});

it('should present an ErrorResponse when something occurs in the repository', function(): void {

    $useCase = new FindPerformanceMetrics(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights,
        $this->isCloudPlatform
    );

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readDashboardPerformanceMetric
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception('An error occured'));

    $presenter = new FindPerformanceMetricsPresenterStub();
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe('An error occured while retrieving metrics');
});

it('should present a FindPerformanceMetricsResponse when metrics are found', function(): void {

    $useCase = new FindPerformanceMetrics(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights,
        $this->isCloudPlatform
    );

    $response = [
        new ResourceMetric(
            1,
            'Ping',
            'Centreon-Server',
            3,
            [
                new PerformanceMetric(1,'pl','%', 400.3, null, null, null, null, null, null),
                new PerformanceMetric(2,'rta','ms', 20, 50, null, null, null, null, null),
                new PerformanceMetric(3,'rtmax','ms', null, null, null, null, null, null, null),
                new PerformanceMetric(4,'rtmin','ms', null, null, null, null, null, null, null),
            ]
        ),
        new ResourceMetric(
            2,
            'Traffic',
            'Centreon-Server',
            3,
            [
                new PerformanceMetric(5,'traffic_in','M', null, null, null, null, null, null, null),
                new PerformanceMetric(6,'traffic_out','M', null, null, null, null, null, null, null),
            ]
        )
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readDashboardPerformanceMetric
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willReturn($response);

    $presenter = new FindPerformanceMetricsPresenterStub();
    $useCase($presenter);
    expect($presenter->data)->toBeInstanceOf(FindPerformanceMetricsResponse::class)
        ->and($presenter->data->resourceMetrics)
        ->toBeArray()
        ->and($presenter->data->resourceMetrics[0])
        ->toBeInstanceOf(ResourceMetricDto::class)
        ->and($presenter->data->resourceMetrics[0]->serviceId)->toBe(1)
        ->and($presenter->data->resourceMetrics[0]->resourceName)->toBe('Ping')
        ->and($presenter->data->resourceMetrics[0]->parentName)->toBe('Centreon-Server')
        ->and($presenter->data->resourceMetrics[0]->metrics)->toBe(
            [
                [
                    'id' => 1,
                    'name' => 'pl',
                    'unit' => '%',
                    'warning_high_threshold' => 400.3,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 2,
                    'name' => 'rta',
                    'unit' => 'ms',
                    'warning_high_threshold' => 20.0,
                    'critical_high_threshold' => 50.0,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 3,
                    'name' => 'rtmax',
                    'unit' => 'ms',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 4,
                    'name' => 'rtmin',
                    'unit' => 'ms',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
            ]
        )
        ->and($presenter->data->resourceMetrics[1])
        ->toBeInstanceOf(ResourceMetricDto::class)
        ->and($presenter->data->resourceMetrics[1]->serviceId)->toBe(2)
        ->and($presenter->data->resourceMetrics[1]->resourceName)->toBe('Traffic')
        ->and($presenter->data->resourceMetrics[1]->parentName)->toBe('Centreon-Server')
        ->and($presenter->data->resourceMetrics[1]->metrics)->toBe(
            [
                [
                    'id' => 5,
                    'name' => 'traffic_in',
                    'unit' => 'M',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 6,
                    'name' => 'traffic_out',
                    'unit' => 'M',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
            ]
        );
});

it('should present a FindPerformanceMetricsResponse when metrics are found as non-admin', function(): void {

    $useCase = new FindPerformanceMetrics(
        $this->nonAdminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights,
        $this->isCloudPlatform
    );

    $response = [
        new ResourceMetric(
            1,
            "Ping",
            "Centreon-Server",
            3,
            [
                new PerformanceMetric(1,'pl','%', 400.3, null, null, null, null, null, null),
                new PerformanceMetric(2,'rta','ms', 20, 50, null, null, null, null, null),
                new PerformanceMetric(3,'rtmax','ms', null, null, null, null, null, null, null),
                new PerformanceMetric(4,'rtmin','ms', null, null, null, null, null, null, null),
            ]
        ),
        new ResourceMetric(
            2,
            "Traffic",
            "Centreon-Server",
            3,
            [
                new PerformanceMetric(5,'traffic_in','M', null, null, null, null, null, null, null),
                new PerformanceMetric(6,'traffic_out','M', null, null, null, null, null, null, null),
            ]
        )
    ];

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readDashboardPerformanceMetric
        ->expects($this->once())
        ->method('findByRequestParametersAndAccessGroups')
        ->willReturn($response);

    $presenter = new FindPerformanceMetricsPresenterStub();
    $useCase($presenter);
    expect($presenter->data)->toBeInstanceOf(FindPerformanceMetricsResponse::class)
        ->and($presenter->data->resourceMetrics)
        ->toBeArray()
        ->and($presenter->data->resourceMetrics[0])
        ->toBeInstanceOf(ResourceMetricDto::class)
        ->and($presenter->data->resourceMetrics[0]->serviceId)->toBe(1)
        ->and($presenter->data->resourceMetrics[0]->resourceName)->toBe('Ping')
        ->and($presenter->data->resourceMetrics[0]->parentName)->toBe('Centreon-Server')
        ->and($presenter->data->resourceMetrics[0]->metrics)->toBe(
            [
                [
                    'id' => 1,
                    'name' => 'pl',
                    'unit' => '%',
                    'warning_high_threshold' => 400.3,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 2,
                    'name' => 'rta',
                    'unit' => 'ms',
                    'warning_high_threshold' => 20.0,
                    'critical_high_threshold' => 50.0,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 3,
                    'name' => 'rtmax',
                    'unit' => 'ms',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 4,
                    'name' => 'rtmin',
                    'unit' => 'ms',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
            ]
        )
        ->and($presenter->data->resourceMetrics[1])
        ->toBeInstanceOf(ResourceMetricDto::class)
        ->and($presenter->data->resourceMetrics[1]->serviceId)->toBe(2)
        ->and($presenter->data->resourceMetrics[1]->resourceName)->toBe('Traffic')
        ->and($presenter->data->resourceMetrics[1]->parentName)->toBe('Centreon-Server')
        ->and($presenter->data->resourceMetrics[1]->metrics)->toBe(
            [
                [
                    'id' => 5,
                    'name' => 'traffic_in',
                    'unit' => 'M',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
                [
                    'id' => 6,
                    'name' => 'traffic_out',
                    'unit' => 'M',
                    'warning_high_threshold' => null,
                    'critical_high_threshold' => null,
                    'warning_low_threshold' => null,
                    'critical_low_threshold' => null,
                ],
            ]
        );
});

it('should present a ForbiddenResponse when user has unsufficient rights', function (): void {
    $useCase = new FindPerformanceMetrics(
        $this->nonAdminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights,
        $this->isCloudPlatform
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(false);

    $presenter = new FindPerformanceMetricsPresenterStub();
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
    ->and($presenter->data->getMessage())
    ->toBe(DashboardException::accessNotAllowed()->getMessage());
});
