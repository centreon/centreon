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

namespace Tests\Core\Metric\Application\UseCase\FindMetricsByService;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Application\UseCase\FindMetricsByService\FindMetricsByService;
use Core\Metric\Application\UseCase\FindMetricsByService\FindMetricsByServiceResponse;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->metricRepository = $this->createMock(ReadMetricRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->hostId = 1;
    $this->serviceId = 2;
    $this->metrics = [
        (new Metric(1, 'mymetric'))
            ->setUnit('ms')
            ->setCurrentValue(1.5)
            ->setWarningHighThreshold(100)
            ->setWarningLowThreshold(50)
            ->setCriticalHighThreshold(300)
            ->setCriticalLowThreshold(100),
        (new Metric(2, 'anothermetric'))
            ->setUnit('%')
            ->setCurrentValue(10)
            ->setWarningHighThreshold(50)
            ->setWarningLowThreshold(0)
            ->setCriticalHighThreshold(100)
            ->setCriticalLowThreshold(50)
    ];
});

it('should present a NotFoundResponse when no metrics could be found as admin', function (): void {
    $this->metricRepository
        ->expects($this->once())
        ->method('findByHostIdAndServiceId')
        ->willReturn([]);

    $useCase = new FindMetricsByService(
        $this->adminUser,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->requestParameters
    );
    $presenter = new FindMetricsByServicePresenterStub();
    $useCase($this->hostId, $this->serviceId, $presenter);

    expect($presenter->data)->toBeInstanceOf(NotFoundResponse::class)->and($presenter->data->getMessage())->toBe('metrics not found');
});

it('should present a NotFoundResponse when no metrics could be found as non-admin', function (): void {
    $this->metricRepository
        ->expects($this->once())
        ->method('findByHostIdAndServiceIdAndAccessGroups')
        ->willReturn([]);

    $useCase = new FindMetricsByService(
        $this->nonAdminUser,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->requestParameters
    );
    $presenter = new FindMetricsByServicePresenterStub();
    $useCase($this->hostId, $this->serviceId, $presenter);

    expect($presenter->data)->toBeInstanceOf(NotFoundResponse::class)->and($presenter->data->getMessage())->toBe('metrics not found');
});

it('should present an ErrorResponse when an error occured as admin', function (): void {
    $this->metricRepository
        ->expects($this->once())
        ->method('findByHostIdAndServiceId')
        ->willThrowException(new \Exception());

    $useCase = new FindMetricsByService(
        $this->adminUser,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->requestParameters
    );
    $presenter = new FindMetricsByServicePresenterStub();
    $useCase($this->hostId, $this->serviceId, $presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)->and($presenter->data->getMessage())->toBe('An error occured while finding metrics');
});

it('should present an ErrorResponse when an error occured as non-admin', function (): void {
    $this->metricRepository
        ->expects($this->once())
        ->method('findByHostIdAndServiceIdAndAccessGroups')
        ->willThrowException(new \Exception());

    $useCase = new FindMetricsByService(
        $this->nonAdminUser,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->requestParameters
    );
    $presenter = new FindMetricsByServicePresenterStub();
    $useCase($this->hostId, $this->serviceId, $presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)->and($presenter->data->getMessage())->toBe('An error occured while finding metrics');
});

it('should present an FindMetricsByServiceResponse when metrics are correctly found as admin', function (): void {
    $this->metricRepository
        ->expects($this->once())
        ->method('findByHostIdAndServiceId')
        ->willReturn($this->metrics);

    $useCase = new FindMetricsByService(
        $this->adminUser,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->requestParameters
    );
    $presenter = new FindMetricsByServicePresenterStub();
    $useCase($this->hostId, $this->serviceId, $presenter);

    expect($presenter->data)->toBeInstanceOf(FindMetricsByServiceResponse::class)
        ->and($presenter->data->metricsDto[0]->id)->toBe(1)
        ->and($presenter->data->metricsDto[0]->name)->toBe('mymetric')
        ->and($presenter->data->metricsDto[0]->unit)->toBe('ms')
        ->and($presenter->data->metricsDto[0]->currentValue)->toBe(1.5)
        ->and($presenter->data->metricsDto[0]->warningHighThreshold)->toBe(100.0)
        ->and($presenter->data->metricsDto[0]->warningLowThreshold)->toBe(50.0)
        ->and($presenter->data->metricsDto[0]->criticalHighThreshold)->toBe(300.0)
        ->and($presenter->data->metricsDto[0]->criticalLowThreshold)->toBe(100.0)
        ->and($presenter->data->metricsDto[1]->id)->toBe(2)
        ->and($presenter->data->metricsDto[1]->name)->toBe('anothermetric')
        ->and($presenter->data->metricsDto[1]->unit)->toBe('%')
        ->and($presenter->data->metricsDto[1]->currentValue)->toBe(10.0)
        ->and($presenter->data->metricsDto[1]->warningHighThreshold)->toBe(50.0)
        ->and($presenter->data->metricsDto[1]->warningLowThreshold)->toBe(0.0)
        ->and($presenter->data->metricsDto[1]->criticalHighThreshold)->toBe(100.0)
        ->and($presenter->data->metricsDto[1]->criticalLowThreshold)->toBe(50.0);
});

it('should present an FindMetricsByServiceResponse when metrics are correctly found as non-admin', function (): void {
    $this->metricRepository
        ->expects($this->once())
        ->method('findByHostIdAndServiceIdAndAccessGroups')
        ->willReturn($this->metrics);

    $useCase = new FindMetricsByService(
        $this->nonAdminUser,
        $this->metricRepository,
        $this->accessGroupRepository,
        $this->requestParameters
    );
    $presenter = new FindMetricsByServicePresenterStub();
    $useCase($this->hostId, $this->serviceId, $presenter);

    expect($presenter->data)->toBeInstanceOf(FindMetricsByServiceResponse::class)
        ->and($presenter->data->metricsDto[0]->id)->toBe(1)
        ->and($presenter->data->metricsDto[0]->name)->toBe('mymetric')
        ->and($presenter->data->metricsDto[0]->unit)->toBe('ms')
        ->and($presenter->data->metricsDto[0]->currentValue)->toBe(1.5)
        ->and($presenter->data->metricsDto[0]->warningHighThreshold)->toBe(100.0)
        ->and($presenter->data->metricsDto[0]->warningLowThreshold)->toBe(50.0)
        ->and($presenter->data->metricsDto[0]->criticalHighThreshold)->toBe(300.0)
        ->and($presenter->data->metricsDto[0]->criticalLowThreshold)->toBe(100.0)
        ->and($presenter->data->metricsDto[1]->id)->toBe(2)
        ->and($presenter->data->metricsDto[1]->name)->toBe('anothermetric')
        ->and($presenter->data->metricsDto[1]->unit)->toBe('%')
        ->and($presenter->data->metricsDto[1]->currentValue)->toBe(10.0)
        ->and($presenter->data->metricsDto[1]->warningHighThreshold)->toBe(50.0)
        ->and($presenter->data->metricsDto[1]->warningLowThreshold)->toBe(0.0)
        ->and($presenter->data->metricsDto[1]->criticalHighThreshold)->toBe(100.0)
        ->and($presenter->data->metricsDto[1]->criticalLowThreshold)->toBe(50.0);
});