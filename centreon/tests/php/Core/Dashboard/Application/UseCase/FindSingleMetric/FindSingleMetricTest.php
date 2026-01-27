<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Dashboard\Application\UseCase\FindSingleMetric;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Dashboard\Application\UseCase\FindSingleMetric\{
    FindSingleMetric,
    FindSingleMetricPresenterInterface,
    FindSingleMetricRequest,
    FindSingleMetricResponse
};
use Core\Metric\Application\Repository\ReadMetricRepositoryInterface;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Repository\ReadRealTimeServiceRepositoryInterface;
use Mockery;

beforeEach(function (): void {
    $this->contact = Mockery::mock(ContactInterface::class);
    $this->metricRepo = Mockery::mock(ReadMetricRepositoryInterface::class);
    $this->serviceRepo = Mockery::mock(ReadRealTimeServiceRepositoryInterface::class);
    $this->accessGroupRepo = Mockery::mock(ReadAccessGroupRepositoryInterface::class);
    $this->requestParameters = Mockery::mock(RequestParametersInterface::class);
    $this->contact->shouldReceive('getId')->andReturn(1);

    $this->presenter = new class () implements FindSingleMetricPresenterInterface {
        public mixed $response = null;

        public function presentResponse(mixed $response): void
        {
            $this->response = $response;
        }
    };
});

it('returns a FindSingleMetricResponse for an admin user', function (): void {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(true);
    $hostId = 10;
    $serviceId = 20;
    $this->serviceRepo
        ->shouldReceive('exists')
        ->once()
        ->with($serviceId, $hostId)
        ->andReturn(true);

    $metric = new Metric(
        id: 1,
        name: 'cpu'
    );
    $metric->setCurrentValue(12.34);
    $metric->setUnit('%');

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->with($hostId, $serviceId, 'cpu', $this->requestParameters)
        ->andReturn($metric);

    $this->accessGroupRepo->shouldNotReceive('findByContact');

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->serviceRepo,
        $this->accessGroupRepo,
        $this->requestParameters,
    );

    $useCase(new FindSingleMetricRequest(10, 20, 'cpu'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindSingleMetricResponse::class)
        ->and($this->presenter->response->id)->toBe(1)
        ->and($this->presenter->response->name)->toBe('cpu')
        ->and($this->presenter->response->currentValue)->toBe(12.34);
});

it('passes access groups to the repository for non-admin users', function (): void {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(false);
    $hostId = 11;
    $serviceId = 22;
    $this->serviceRepo
        ->shouldReceive('exists')
        ->once()
        ->with($serviceId, $hostId)
        ->andReturn(true);

    $fakeGroups = ['g1', 'g2'];
    $this->accessGroupRepo
        ->shouldReceive('findByContact')
        ->once()
        ->with($this->contact)
        ->andReturn($fakeGroups);

    $metric = new Metric(
        id: 2,
        name: 'mem'
    );
    $metric->setUnit('MB');
    $metric->setCurrentValue(256.0);

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->with($hostId, $serviceId, 'mem', $this->requestParameters, $fakeGroups)
        ->andReturn($metric);

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->serviceRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(11, 22, 'mem'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindSingleMetricResponse::class)
        ->and($this->presenter->response->id)->toBe(2)
        ->and($this->presenter->response->name)->toBe('mem');
});

it('returns NotFoundResponse when service does not exist', function (): void {
    $hostId = 5;
    $serviceId = 6;
    $this->serviceRepo
        ->shouldReceive('exists')
        ->once()
        ->with($serviceId, $hostId)
        ->andReturn(false);

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->serviceRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest($hostId, $serviceId, 'disk'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response->getMessage())->toBe('Service not found');
});

it('maps a "not found" exception to NotFoundResponse', function (): void {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(true);
    $this->serviceRepo->shouldReceive('exists')->once()->andReturn(true);

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->andReturn(null);

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->serviceRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(1, 2, 'foo'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class);
});

it('maps other exceptions to ErrorResponse', function (): void {
    $this->contact->shouldReceive('isAdmin')->once()->andReturn(true);
    $this->serviceRepo->shouldReceive('exists')->once()->andReturn(true);

    $this->metricRepo
        ->shouldReceive('findSingleMetricValue')
        ->once()
        ->andThrow(new RepositoryException(
            "Error retrieving metric 'foo' for host 1, service 2",
            ['metricName' => 'bar', 'hostId' => 1, 'serviceId' => 2]
        ));

    $useCase = new FindSingleMetric(
        $this->contact,
        $this->metricRepo,
        $this->serviceRepo,
        $this->accessGroupRepo,
        $this->requestParameters
    );

    $useCase(new FindSingleMetricRequest(1, 2, 'bar'), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class);
});

it('throws InvalidArgumentException for non-positive hostId', function (): void {
    $this->expectException(\InvalidArgumentException::class);
    new FindSingleMetricRequest(0, 1, 'metric');
});

it('throws InvalidArgumentException for non-positive serviceId', function (): void {
    $this->expectException(\InvalidArgumentException::class);
    new FindSingleMetricRequest(1, 0, 'metric');
});

it('throws InvalidArgumentException for empty metricName', function (): void {
    $this->expectException(\InvalidArgumentException::class);
    new FindSingleMetricRequest(1, 2, '');
});
