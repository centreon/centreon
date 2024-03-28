<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Service\Application\UseCase\AddService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\UseCase\AddService\AddServiceRequest;
use Core\Service\Application\UseCase\AddService\AddServiceValidation;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->validation = new AddServiceValidation(
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->serviceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class),
        $this->performanceGraphRepository = $this->createMock(ReadPerformanceGraphRepositoryInterface::class),
        $this->commandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->timePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class),
        $this->imageRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );
});

it('throws an exception when service name already exists for associated host', function (): void {
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findServiceNamesByHost')
        ->willReturn(new ServiceNamesByHost(1, ['toto']));

    $request = new AddServiceRequest();
    $request->name = ' toto  ';
    $request->hostId = 1;
    $this->validation->assertServiceName($request);
})->throws(
    ServiceException::class,
    ServiceException::nameAlreadyExists('toto', 1)->getMessage()
);

it('throws an exception when parent template ID does not exist', function (): void {
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidServiceTemplate(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('service_template_id', 1)->getMessage()
);

it('throws an exception when command ID does not exist', function (): void {
    $this->commandRepository
        ->expects($this->once())
        ->method('existsByIdAndCommandType')
        ->willReturn(false);

    $this->validation->assertIsValidCommandForOnPremPlatform(1, null);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('check_command_id', 1)->getMessage()
);

it('throws an exception when command ID and service template are not defined', function (): void {
    $this->validation->assertIsValidCommandForOnPremPlatform(null, null);
})->throws(
    ServiceException::class,
    ServiceException::checkCommandCannotBeNull()->getMessage()
);

it('throws an exception when event handler ID does not exist', function (): void {
    $this->commandRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidEventHandler(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('event_handler_command_id', 1)->getMessage()
);

it('throws an exception when check time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidTimePeriod(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('check_timeperiod_id', 1)->getMessage()
);

it('throws an exception when icon ID does not exist', function (): void {
    $this->imageRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(false);

    $this->validation->assertIsValidIcon(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('icon_id', 1)->getMessage()
);

it('throws an exception when notification time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidNotificationTimePeriod(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('notification_timeperiod_id', 1)->getMessage()
);

it('throws an exception when severity ID does not exist', function (): void {
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidSeverity(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('severity_id', 1)->getMessage()
);

it('throws an exception when performance graph ID does not exist', function (): void {
    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidPerformanceGraph(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('graph_template_id', 1)->getMessage()
);

it('throws an exception when host ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidHost(4);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('host_id', 4)->getMessage()
);

it('throws an exception when category ID does not exist with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->willReturn([]);

    $this->validation->assertIsValidServiceCategories([1, 3]);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_categories', [1, 3])->getMessage()
);

it('throws an exception when category ID does not exist with non-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIdsByAccessGroups')
        ->willReturn([]);

    $this->validation->assertIsValidServiceCategories([1, 3]);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_categories', [1, 3])->getMessage()
);

it('throws an exception when group ID does not exist with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([]);

    $this->validation->assertIsValidServiceGroups([1, 2],3);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_groups', [1, 2])->getMessage()
);

it('throws an exception when group ID does not exist with non-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('existByAccessGroups')
        ->willReturn([]);

    $this->validation->assertIsValidServiceGroups([1, 2], 3);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_groups', [1, 2])->getMessage()
);
