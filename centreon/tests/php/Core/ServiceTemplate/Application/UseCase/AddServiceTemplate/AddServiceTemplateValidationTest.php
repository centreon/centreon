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

namespace Tests\Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplateValidation;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\ServiceGroupDto;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {

    $this->validation = new AddServiceTemplateValidation(
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->serviceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class),
        $this->performanceGraphRepository = $this->createMock(ReadPerformanceGraphRepositoryInterface::class),
        $this->commandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->timePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class),
        $this->imageRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->accessGroups = []
    );

    $this->serviceGroups = [
        new ServiceGroupDto(
            serviceGroupId: 1,
            hostTemplateId: 3,
        ),
        new ServiceGroupDto(
            serviceGroupId: 2,
            hostTemplateId: 3,
        )
    ];

});

it('throws an exception when parent template ID does not exist', function (): void {
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidServiceTemplate(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('service_template_id', 1)->getMessage()
);

it('throws an exception when command ID does not exist', function (): void {
    $this->commandRepository
        ->expects($this->once())
        ->method('existsByIdAndCommandType')
        ->willReturn(false);

    $this->validation->assertIsValidCommand(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('check_command_id', 1)->getMessage()
);

it('throws an exception when event handler ID does not exist', function (): void {
    $this->commandRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidEventHandler(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('event_handler_command_id', 1)->getMessage()
);

it('throws an exception when check time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidTimePeriod(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('check_timeperiod_id', 1)->getMessage()
);

it('throws an exception when icon ID does not exist', function (): void {
    $this->imageRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(false);

    $this->validation->assertIsValidIcon(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('icon_id', 1)->getMessage()
);

it('throws an exception when notification time period ID does not exist', function (): void {
    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidNotificationTimePeriod(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('notification_timeperiod_id', 1)->getMessage()
);

it('throws an exception when severity ID does not exist', function (): void {
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidSeverity(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('severity_id', 1)->getMessage()
);

it('throws an exception when performance graph ID does not exist', function (): void {
    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidPerformanceGraph(1);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idDoesNotExist('graph_template_id', 1)->getMessage()
);

it('throws an exception when host template ID does not exist', function (): void {
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->willReturn([]);

    $this->validation->assertIsValidHostTemplates([1,3], 4);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('host_templates', [1,3])->getMessage()
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

    $this->validation->assertIsValidServiceCategories([1,3]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_categories', [1,3])->getMessage()
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

    $this->validation->assertIsValidServiceCategories([1,3]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_categories', [1,3])->getMessage()
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

    $this->validation->assertIsValidServiceGroups($this->serviceGroups,[3]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_groups', [1, 2])->getMessage()
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

    $this->validation->assertIsValidServiceGroups($this->serviceGroups,[3]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::idsDoNotExist('service_groups', [1, 2])->getMessage()
);

it('throws an exception when host template used in service group associations are not linked to service template', function (): void {
    $this->validation->assertIsValidServiceGroups($this->serviceGroups,[]);
})->throws(
    ServiceTemplateException::class,
    ServiceTemplateException::invalidServiceGroupAssociation()->getMessage()
);
