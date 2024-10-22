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

namespace Tests\Core\Dashboard\Application\UseCase\FindMetricsTop;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindMetricsTop\FindMetricsTop;
use Core\Dashboard\Application\UseCase\FindMetricsTop\FindMetricsTopRequest;
use Core\Dashboard\Application\UseCase\FindMetricsTop\FindMetricsTopResponse;
use Core\Dashboard\Application\UseCase\FindMetricsTop\Response\MetricInformationDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Metric\PerformanceMetric;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->nonAdminUser = (new Contact())->setId(1)->setAdmin(false);
    $this->adminUser = (new Contact())->setId(1)->setAdmin(true);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->metricRepository = $this->createMock(ReadDashboardPerformanceMetricRepositoryInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->isCloudPlatform = false;
});

it('should present a ForbiddenResponse when the user does not has sufficient rights', function (): void {
    $presenter = new FindMetricsTopPresenterStub();
    $request =  new FindMetricsTopRequest();
    $request->metricName = "rta";
    $useCase = new FindMetricsTop(
        $this->nonAdminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->metricRepository,
        $this->rights,
        $this->isCloudPlatform
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

it('should present an ErrorResponse when an error occurs', function (): void {
    $presenter = new FindMetricsTopPresenterStub();
    $request =  new FindMetricsTopRequest();
    $request->metricName = "rta";
    $useCase = new FindMetricsTop(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->metricRepository,
        $this->rights,
        $this->isCloudPlatform
    );

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findByRequestParametersAndMetricName')
        ->willThrowException(new \Exception('An error occured'));

    $useCase($presenter, $request);

    $this->expect($presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())->toBe('An error occured while retrieving top metrics');
});

it('should present a NotFoundResponse when no metrics are found', function (): void {
    $presenter = new FindMetricsTopPresenterStub();
    $request =  new FindMetricsTopRequest();
    $request->metricName = "rta";
    $useCase = new FindMetricsTop(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->metricRepository,
        $this->rights,
        $this->isCloudPlatform
    );

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findByRequestParametersAndMetricName')
        ->willReturn([]);

    $useCase($presenter, $request);

    $this->expect($presenter->data)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->data->getMessage())->toBe((new NotFoundResponse('metrics'))->getMessage());
});

it('should take account of access groups when the user is not admin', function (): void {
    $presenter = new FindMetricsTopPresenterStub();
    $request =  new FindMetricsTopRequest();
    $request->metricName = "rta";
    $useCase = new FindMetricsTop(
        $this->nonAdminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->metricRepository,
        $this->rights,
        $this->isCloudPlatform
    );

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findByRequestParametersAndAccessGroupsAndMetricName');

    $useCase($presenter, $request);
});

it('should present a FindMetricsTopResponse when metrics are found', function (): void {
    $presenter = new FindMetricsTopPresenterStub();
    $request =  new FindMetricsTopRequest();
    $request->metricName = "rta";
    $useCase = new FindMetricsTop(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->metricRepository,
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
                new PerformanceMetric(2,'rta','ms', 20, 50, 0, 11.2, 12.2, 8.2, 14.2),
            ]
        ),
        new ResourceMetric(
            2,
            'Ping_1',
            'Centreon-Server',
            3,
            [
                new PerformanceMetric(5,'rta','ms', 20, 50, null, 21.2, 22.2, 21.2, 24.2),
            ]
        ),
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->metricRepository
        ->expects($this->once())
        ->method('findByRequestParametersAndMetricName')
        ->with($this->requestParameters, $request->metricName)
        ->willReturn($response);

    $useCase($presenter, $request);

    $this->expect($presenter->data)
        ->toBeInstanceOf(FindMetricsTopResponse::class);

    $this->expect($presenter->data->metricName)->toBe("rta");
    $this->expect($presenter->data->metricUnit)->toBe("ms");
    $this->expect($presenter->data->resourceMetrics)->toBeArray();
    $metricOne = $presenter->data->resourceMetrics[0];
    $metricTwo = $presenter->data->resourceMetrics[1];

    $this->expect($metricOne)->toBeInstanceOf(MetricInformationDto::class);
    $this->expect($metricOne->serviceId)->toBe(1);
    $this->expect($metricOne->parentId)->toBe(3);
    $this->expect($metricOne->resourceName)->toBe('Ping');
    $this->expect($metricOne->parentName)->toBe('Centreon-Server');
    $this->expect($metricOne->currentValue)->toBe(12.2);
    $this->expect($metricOne->warningHighThreshold)->toBe(20.0);
    $this->expect($metricOne->criticalHighThreshold)->toBe(50.0);
    $this->expect($metricOne->warningLowThreshold)->toBe(0.0);
    $this->expect($metricOne->criticalLowThreshold)->toBe(11.2);
    $this->expect($metricOne->minimumValue)->toBe(8.2);
    $this->expect($metricOne->maximumValue)->toBe(14.2);

    $this->expect($metricTwo)->toBeInstanceOf(MetricInformationDto::class);
    $this->expect($metricTwo->serviceId)->toBe(2);
    $this->expect($metricTwo->parentId)->toBe(3);
    $this->expect($metricTwo->resourceName)->toBe('Ping_1');
    $this->expect($metricTwo->parentName)->toBe('Centreon-Server');
    $this->expect($metricTwo->currentValue)->toBe(22.2);
    $this->expect($metricTwo->warningHighThreshold)->toBe(20.0);
    $this->expect($metricTwo->criticalHighThreshold)->toBe(50.0);
    $this->expect($metricTwo->warningLowThreshold)->toBe(null);
    $this->expect($metricTwo->criticalLowThreshold)->toBe(21.2);
    $this->expect($metricTwo->minimumValue)->toBe(21.2);
    $this->expect($metricTwo->maximumValue)->toBe(24.2);
});