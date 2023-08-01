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
use Core\Dashboard\Application\Repository\ReadDashboardPerformanceMetricRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\FindPerformanceMetrics;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function() {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readDashboardPerformanceMetric = $this->createMock(ReadDashboardPerformanceMetricRepositoryInterface::class);
});

it('should present an ErrorResponse when something occurs in the repository', function() {

    $useCase = new FindPerformanceMetrics(
        $this->adminUser,
        $this->requestParameters,
        $this->accessGroupRepository,
        $this->readDashboardPerformanceMetric
    );

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