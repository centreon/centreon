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

namespace Tests\Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\ParametersValidation;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\ServiceGroupDto;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(closure: function (): void {
    $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
    $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class);
    $this->timePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->serviceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class);
    $this->performanceGraphRepository = $this->createMock(ReadPerformanceGraphRepositoryInterface::class);
    $this->imageRepository = $this->createMock(ReadViewImgRepositoryInterface::class);
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class);
    $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);

    $this->parametersValidation = new ParametersValidation(
        $this->readServiceTemplateRepository,
        $this->readCommandRepository,
        $this->timePeriodRepository,
        $this->serviceSeverityRepository,
        $this->performanceGraphRepository,
        $this->imageRepository,
        $this->readHostTemplateRepository,
        $this->readServiceCategoryRepository,
        $this->readServiceGroupRepository
    );
});

it('should raise an exception when the new name already exist', function (): void {
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with('new_name')
        ->willReturn(true);

    $this->parametersValidation->assertIsValidName('old_name', 'new_name');
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::nameAlreadyExists('new_name')->getMessage()
);

it('should raise an exception when the service template ID does not exist', function (): void {
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidServiceTemplate(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('service_template_id', 1)->getMessage()
);

it('should raise an exception when the command ID does not exist', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('existsByIdAndCommandType')
        ->with(1, CommandType::Check)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidCommand(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('check_command_id', 1)->getMessage()
);

it('should raise an exception when the event handler ID does not exist', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidEventHandler(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('event_handler_command_id', 1)->getMessage()
);

it('should raise an exception when the time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidTimePeriod(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('check_timeperiod_id', 1)->getMessage()
);

it('should raise an exception when the severity ID does not exist', function (): void {
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidSeverity(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('severity_id', 1)->getMessage()
);

it('should raise an exception when the notification time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->with(1)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidNotificationTimePeriod(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('notification_timeperiod_id', 1)->getMessage()
);

it('should raise an exception when the icon ID does not exist', function (): void {
    $this->imageRepository
        ->expects($this->once())
        ->method('existsOne')
        ->with(1)
        ->willReturn(false);

    $this->parametersValidation->assertIsValidIcon(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('icon_id', 1)->getMessage()
);

it('should raise an exception when the host templates IDs does not exist', function (): void {
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with([1, 2])
        ->willReturn([2]);

    $this->parametersValidation->assertHostTemplateIds([1, 2]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('host_templates', [1])->getMessage()
);

it('should raise an exception when the service category IDs do not exist, as an administrator', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with([1, 2])
        ->willReturn([2]);

    $this->parametersValidation->assertServiceCategories([1, 2], $this->contact, []);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_categories', [1])->getMessage()
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
        ->with([1, 2], [3])
        ->willReturn([2]);

    $this->parametersValidation->assertServiceCategories([1, 2], $this->contact, [3]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_categories', [1])->getMessage()
);

it('should raise an exception when the service groups IDs do not exist, as an administrator', function (): void {
    $serviceTemplateId = 1;
    $serviceGroupDtos = [
        new ServiceGroupDto(1, 1),
        new ServiceGroupDto(2, 2),
        new ServiceGroupDto(3, 3),
    ];

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->with([1, 2, 3])
        ->willReturn([2]);

    $this->parametersValidation->assertServiceGroups(
        $serviceGroupDtos,
        $serviceTemplateId,
        $this->contact,
        []
    );
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_groups', [1, 3])->getMessage()
);

it('should raise an exception when the service groups IDs do not exist, as a non-administrator', function (): void {
    $serviceTemplateId = 1;
    $serviceGroupDtos = [
        new ServiceGroupDto(1, 1),
        new ServiceGroupDto(2, 2),
        new ServiceGroupDto(3, 3),
    ];

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('existByAccessGroups')
        ->willReturn([2]);

    $this->parametersValidation->assertServiceGroups(
        $serviceGroupDtos,
        $serviceTemplateId,
        $this->contact,
        []
    );
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_groups', [1, 3])->getMessage()
);

it(
    'should raise an exception when the host template IDs are not linked to service template, as a non-administrator',
    function (): void
    {
        $serviceTemplateId = 1;
        $serviceGroupDtos = [
            new ServiceGroupDto(4, 1),
            new ServiceGroupDto(5, 1),
            new ServiceGroupDto(6, 1),
        ];
        $serviceTemplate = $this->createMock(ServiceTemplate::class);
        $serviceTemplate
            ->expects($this->once())
            ->method('getHostTemplateIds')
            ->willReturn([4, 5]);

        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('existByAccessGroups')
            ->willReturn([1]);

        $this->readServiceTemplateRepository
            ->expects($this->once())
            ->method('findById')
            ->with($serviceTemplateId)
            ->willReturn($serviceTemplate);

        $this->parametersValidation->assertServiceGroups(
            $serviceGroupDtos,
            $serviceTemplateId,
            $this->contact,
            []
        );
    }
)->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::invalidServiceGroupAssociation()->getMessage()
);
