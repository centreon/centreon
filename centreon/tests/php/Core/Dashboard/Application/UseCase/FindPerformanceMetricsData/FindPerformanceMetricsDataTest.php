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
use Centreon\Domain\Monitoring\Service;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Metric\Domain\Model\MetricInformation\MetricInformation;
use Core\Metric\Domain\Model\MetricInformation\GeneralInformation;
use Core\Metric\Domain\Model\MetricInformation\ThresholdInformation;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsData;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataRequest;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataResponse;

beforeEach(function () {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->metricRepositoryLegacy = $this->createMock(MetricRepositoryInterface::class);
    $this->metricRepository = $this->createMock(ReadMetricRepositoryInterface::class);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
});

it('should present a ForbiddenResponse when the user does not has sufficient rights', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricNames = ["rta"];
    $useCase = new FindPerformanceMetricsData(
        $this->nonAdminUser,
        $this->requestParameters,
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
    $request->metricNames = ["rta","pl"];
    $useCase = new FindPerformanceMetricsData(
        $this->nonAdminUser,
        $this->requestParameters,
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
        ->method('findServicesByMetricNamesAndAccessGroupsAndRequestParameters')
        ->willThrowException(new \Exception());

    $useCase($presenter, $request);

    $this->expect($presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())->toBe('An error occurred while retrieving metrics data');
});

it('should get the metrics with access group management when the user is not admin', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricNames = ["rta","pl"];
    $useCase = new FindPerformanceMetricsData(
        $this->nonAdminUser,
        $this->requestParameters,
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
        ->method('findServicesByMetricNamesAndAccessGroupsAndRequestParameters');

    $useCase($presenter, $request);
});

it('should get the metrics without access group management when the user is admin', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricNames = ["rta","pl"];
    $useCase = new FindPerformanceMetricsData(
        $this->adminUser,
        $this->requestParameters,
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
        ->method('findServicesByMetricNamesAndRequestParameters');

    $useCase($presenter, $request);
});

it('should present a FindPerformanceMetricsDataResponse when metrics are correctly retrieve', function () {
    $presenter = new FindPerformanceMetricsDataPresenterStub();
    $request =  new FindPerformanceMetricsDataRequest(new \DateTime(), new \DateTime());
    $request->metricNames = ["pl"];
    $useCase = new FindPerformanceMetricsData(
        $this->adminUser,
        $this->requestParameters,
        $this->metricRepositoryLegacy,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->rights
    );
    $service = (new Service())
        ->setId(1)
        ->setHost(
            (new Host())->setId(2)->setName('myHost')
        );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findServicesByMetricNamesAndRequestParameters')
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
                    'base' => 1000,
                    'title' => 'Ping graph on myHost',
                    'host_name' => 'myHost'
                ],
                'metrics' => [
                    [
                        'index_id' => 1,
                        'metric_id' => 1,
                        'metric' => 'pl',
                        'metric_legend' => 'pl',
                        'unit' => '%',
                        'hidden' => 0,
                        'legend' => 'Packet-Loss',
                        'virtual' => 0,
                        'stack' => 0,
                        'ds_order' => 1,
                        'ds_data' => [
                            'ds_min' => null,
                            'ds_max' => null,
                            'ds_minmax_int' => null,
                            'ds_last' => null,
                            'ds_average' => null,
                            'ds_total' => null,
                            'ds_tickness' => 1,
                            'ds_color_line_mode' => '0',
                            'ds_color_line' => '#f0f'
                        ],
                        'warn' => null,
                        'warn_low' => null,
                        'crit' => null,
                        'crit_low' => null,
                        'ds_color_area_warn' => '#f0f',
                        'ds_color_area_crit' => '#f0f',
                        'data' => [0,0,0,null],
                        'prints' => [['Min:0.0'],['Average:0.0']],
                        'min' => null,
                        'max' => null,
                        'last_value' => null,
                        'minimum_value' => null,
                        'maximum_value' => null,
                        'average_value' => null
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
    $this->expect($presenter->data->metricsInformation[0])->toBeInstanceOf(MetricInformation::class)
        ->and($presenter->data->metricsInformation[0]->getGeneralInformation())
        ->toBeInstanceOf(GeneralInformation::class)
        ->and($presenter->data->metricsInformation[0]->getThresholdInformation())
        ->toBeInstanceOf(ThresholdInformation::class);
    $this->expect($presenter->data->times)->toBeArray()
        ->and($presenter->data->times[0])->toBeInstanceOf(\DateTimeImmutable::class)
        ->and($presenter->data->times[1])->toBeInstanceOf(\DateTimeImmutable::class);
});
