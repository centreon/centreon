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

namespace Tests\Core\Service\Application\UseCase\PartialUpdateService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateServiceRequest;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateServiceValidation;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(closure: function (): void {
    $this->validation = new PartialUpdateServiceValidation(
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->serviceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class),
        $this->performanceGraphRepository = $this->createMock(ReadPerformanceGraphRepositoryInterface::class),
        $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->timePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class),
        $this->imageRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );
});

it('should raise an exception when the new name already exists', function (): void {
    $serviceNamesByHost = new ServiceNamesByHost(hostId: 1, servicesName: ['new_name']);
    $request = new PartialUpdateServiceRequest();
    $request->hostId = 1;
    $request->name = 'new_name';
    $service = new Service(id: 2, name: 'old_name', hostId: 1);

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findServiceNamesByHost')
        ->willReturn($serviceNamesByHost);

    $this->validation->assertIsValidName($request->name, $service);
})->throws(
    ServiceException::class,
    ServiceException::nameAlreadyExists('new_name', 1)->getMessage()
);

it('should raise an exception when the service template ID does not exist', function (): void {
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidTemplate(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('service_template_id', 1)->getMessage()
);

it('should raise an exception when the command ID does not exist', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('existsByIdAndCommandType')
        ->willReturn(false);

    $this->validation->assertIsValidCommand(1, 2);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('check_command_id', 1)->getMessage()
);

it('should raise an exception when the event handler ID does not exist', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->validation->assertIsValidEventHandler(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('event_handler_command_id', 1)->getMessage()
);

it('should raise an exception when the time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->validation->assertIsValidTimePeriod(1, 'check_timeperiod_id');
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('check_timeperiod_id', 1)->getMessage()
);

it('should raise an exception when the severity ID does not exist', function (): void {
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->validation->assertIsValidSeverity(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('severity_id', 1)->getMessage()
);

it('should raise an exception when the notification time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->validation->assertIsValidTimePeriod(1, 'notification_timeperiod_id');
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('notification_timeperiod_id', 1)->getMessage()
);

it('should raise an exception when the icon ID does not exist', function (): void {
    $this->imageRepository
        ->expects($this->once())
        ->method('existsOne')
        ->with(1)
        ->willReturn(false);

    $this->validation->assertIsValidIcon(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('icon_id', 1)->getMessage()
);

it('should raise an exception when the host ID does not exist, as an administrator', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidHost(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('host_id', 1)->getMessage()
);

it('should raise an exception when the host ID does not exist, as a non-administrator', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readHostRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(false);

    $this->validation->assertIsValidHost(1);
})->throws(
    ServiceException::class,
    ServiceException::idDoesNotExist('host_id', 1)->getMessage()
);

it('should raise an exception when the service category IDs do not exist, as an administrator', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->willReturn([2]);

    $this->validation->assertAreValidCategories([1, 2]);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_categories', [1])->getMessage()
);

it('should raise an exception when the service category IDs do not exist, as a non-administrator', function ():
void {

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIdsByAccessGroups')
        ->willReturn([2]);

    $this->validation->assertAreValidCategories([1, 2]);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_categories', [1])->getMessage()
);

it('should raise an exception when the service group IDs do not exist, as an administrator', function (): void {
    $serviceGroupIds = [1, 2, 3];

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([2]);

    $this->validation->assertAreValidGroups($serviceGroupIds);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_groups', [1, 3])->getMessage()
);

it('should raise an exception when the service group IDs do not exist, as a non-administrator', function (): void {
    $serviceGroupIds = [1, 2, 3];

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('existByAccessGroups')
        ->willReturn([2]);

    $this->validation->assertAreValidGroups($serviceGroupIds);
})->throws(
    ServiceException::class,
    ServiceException::idsDoNotExist('service_groups', [1, 3])->getMessage()
);
