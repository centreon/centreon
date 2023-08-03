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
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\ResourceMetricDTO;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function() {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readDashboardPerformanceMetric = $this->createMock(ReadDashboardPerformanceMetricRepositoryInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
});

it('should present an ErrorResponse when something occurs in the repository', function() {

    $useCase = new FindPerformanceMetrics(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readDashboardPerformanceMetric
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception('An error occured'));

    $presenter = new FindDashboardPerformanceMetricsPresenterStub();
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe('An error occured while retrieving metrics');
});

it('should present a FindPerformanceMetricsResponse when metrics are found', function() {

    $useCase = new FindPerformanceMetrics(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights
    );

    $response = [
        new ResourceMetric(
            1,
            "Centreon-Server_Ping",
            [
                new PerformanceMetric(1,'pl','%'),
                new PerformanceMetric(2,'rta','ms'),
                new PerformanceMetric(3,'rtmax','ms'),
                new PerformanceMetric(4,'rtmin','ms'),
            ]
        ),
        new ResourceMetric(
            2,
            "Centreon-Server_Traffic",
            [
                new PerformanceMetric(5,'traffic_in','M'),
                new PerformanceMetric(6,'traffic_out','M'),
            ]
        )
    ];

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readDashboardPerformanceMetric
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willReturn($response);

    $presenter = new FindDashboardPerformanceMetricsPresenterStub();
    $useCase($presenter);
    expect($presenter->data)->toBeInstanceOf(FindPerformanceMetricsResponse::class)
        ->and($presenter->data->resourceMetrics)
        ->toBeArray()
        ->and($presenter->data->resourceMetrics[0])
        ->toBeInstanceOf(ResourceMetricDTO::class)
        ->and($presenter->data->resourceMetrics[0]->serviceId)->toBe(1)
        ->and($presenter->data->resourceMetrics[0]->resourceName)->toBe('Centreon-Server_Ping')
        ->and($presenter->data->resourceMetrics[0]->metrics)->toBe(
            [
                [
                    'id' => 1,
                    'name' => 'pl',
                    'unit' => '%'
                ],
                [
                    'id' => 2,
                    'name' => 'rta',
                    'unit' => 'ms'
                ],
                [
                    'id' => 3,
                    'name' => 'rtmax',
                    'unit' => 'ms'
                ],
                [
                    'id' => 4,
                    'name' => 'rtmin',
                    'unit' => 'ms'
                ],
            ]
        )
        ->and($presenter->data->resourceMetrics[1])
        ->toBeInstanceOf(ResourceMetricDTO::class)
        ->and($presenter->data->resourceMetrics[1]->serviceId)->toBe(2)
        ->and($presenter->data->resourceMetrics[1]->resourceName)->toBe('Centreon-Server_Traffic')
        ->and($presenter->data->resourceMetrics[1]->metrics)->toBe(
            [
                [
                    'id' => 5,
                    'name' => 'traffic_in',
                    'unit' => 'M'
                ],
                [
                    'id' => 6,
                    'name' => 'traffic_out',
                    'unit' => 'M'
                ],
            ]
        );
});

it('should present a FindPerformanceMetricsResponse when metrics are found as non-admin', function() {

    $useCase = new FindPerformanceMetrics(
        $this->nonAdminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights
    );

    $response = [
        new ResourceMetric(
            1,
            "Centreon-Server_Ping",
            [
                new PerformanceMetric(1,'pl','%'),
                new PerformanceMetric(2,'rta','ms'),
                new PerformanceMetric(3,'rtmax','ms'),
                new PerformanceMetric(4,'rtmin','ms'),
            ]
        ),
        new ResourceMetric(
            2,
            "Centreon-Server_Traffic",
            [
                new PerformanceMetric(5,'traffic_in','M'),
                new PerformanceMetric(6,'traffic_out','M'),
            ]
        )
    ];

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readDashboardPerformanceMetric
        ->expects($this->once())
        ->method('FindByRequestParametersAndAccessGroups')
        ->willReturn($response);

    $presenter = new FindDashboardPerformanceMetricsPresenterStub();
    $useCase($presenter);
    expect($presenter->data)->toBeInstanceOf(FindPerformanceMetricsResponse::class)
        ->and($presenter->data->resourceMetrics)
        ->toBeArray()
        ->and($presenter->data->resourceMetrics[0])
        ->toBeInstanceOf(ResourceMetricDTO::class)
        ->and($presenter->data->resourceMetrics[0]->serviceId)->toBe(1)
        ->and($presenter->data->resourceMetrics[0]->resourceName)->toBe('Centreon-Server_Ping')
        ->and($presenter->data->resourceMetrics[0]->metrics)->toBe(
            [
                [
                    'id' => 1,
                    'name' => 'pl',
                    'unit' => '%'
                ],
                [
                    'id' => 2,
                    'name' => 'rta',
                    'unit' => 'ms'
                ],
                [
                    'id' => 3,
                    'name' => 'rtmax',
                    'unit' => 'ms'
                ],
                [
                    'id' => 4,
                    'name' => 'rtmin',
                    'unit' => 'ms'
                ],
            ]
        )
        ->and($presenter->data->resourceMetrics[1])
        ->toBeInstanceOf(ResourceMetricDTO::class)
        ->and($presenter->data->resourceMetrics[1]->serviceId)->toBe(2)
        ->and($presenter->data->resourceMetrics[1]->resourceName)->toBe('Centreon-Server_Traffic')
        ->and($presenter->data->resourceMetrics[1]->metrics)->toBe(
            [
                [
                    'id' => 5,
                    'name' => 'traffic_in',
                    'unit' => 'M'
                ],
                [
                    'id' => 6,
                    'name' => 'traffic_out',
                    'unit' => 'M'
                ],
            ]
        );
});

it('should present a ForbiddenResponse when user has unsufficient rights', function () {
    $useCase = new FindPerformanceMetrics(
        $this->nonAdminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric,
        $this->rights
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(false);

    $presenter = new FindDashboardPerformanceMetricsPresenterStub();
    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
    ->and($presenter->data->getMessage())
    ->toBe(DashboardException::accessNotAllowed()->getMessage());
});