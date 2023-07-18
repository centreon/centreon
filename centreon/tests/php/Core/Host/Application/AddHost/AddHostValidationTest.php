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

namespace Tests\Core\Host\Application\UseCase\AddHost;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\UseCase\AddHost\AddHostValidation;
use Core\Host\Domain\Model\Host;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {

    $this->validation = new AddHostValidation(
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readViewImgRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->readTimePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class),
        $this->readHostSeverityRepository = $this->createMock(ReadHostSeverityRepositoryInterface::class),
        $this->readTimezoneRepository = $this->createMock(ReadTimezoneRepositoryInterface::class),
        $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class)
    );
});

it('throws an exception when name is already used', function (): void {
    $this->readHostRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validation->assertIsValidName('name test');
})->throws(
    HostException::class,
    HostException::nameAlreadyExists(Host::formatName('name test'), 'name test')->getMessage()
);

it('throws an exception when name is invalid', function (): void {
    $this->readHostRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    $this->validation->assertIsValidName('_Module_test');
})->throws(
    HostException::class,
    HostException::nameIsInvalid()->getMessage()
);

it('throws an exception when monitoring server ID does not exist', function (): void {
    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidMonitoringServer(1);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('monitoringServerId', 1)->getMessage()
);

it('throws an exception when icon ID does not exist', function (): void {
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(false);

    $this->validation->assertIsValidIcon(1);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('iconId', 1)->getMessage()
);

it('throws an exception when time period ID does not exist', function (): void {
    $this->readTimePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidTimePeriod(1);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('timePeriodId', 1)->getMessage()
);

it('throws an exception when severity ID does not exist', function (): void {
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidSeverity(1);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('severityId', 1)->getMessage()
);

it('throws an exception when timezone ID does not exist', function (): void {
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidTimezone(1);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('timezoneId', 1)->getMessage()
);

it('throws an exception when command ID does not exist', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidCommand(1);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('commandId', 1)->getMessage()
);

it('throws an exception when command ID does not exist for a specific type', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('existsByIdAndCommandType')
        ->willReturn(false);

    $this->validation->assertIsValidCommand(1, CommandType::Check);
})->throws(
    HostException::class,
    HostException::idDoesNotExist('commandId', 1)->getMessage()
);

it('throws an exception when category ID does not exist with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([]);

    $this->validation->assertAreValidCategories([1,3]);
})->throws(
    HostException::class,
    HostException::idsDoNotExist('categories', [1,3])->getMessage()
);

it('throws an exception when category ID does not exist with non-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact');
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existByAccessGroups')
        ->willReturn([]);

    $this->validation->assertAreValidCategories([1,3]);
})->throws(
    HostException::class,
    HostException::idsDoNotExist('categories', [1,3])->getMessage()
);

it('throws an exception when parent template ID creates a circular inheritance', function (): void {
    $this->validation->assertAreValidTemplates([1,3], 3);
})->throws(
    HostException::class,
    HostException::circularTemplateInheritance()->getMessage()
);

it('throws an exception when parent template ID does not exist', function (): void {
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([]);

    $this->validation->assertAreValidTemplates([1,3], 4);
})->throws(
    HostException::class,
    HostException::idsDoNotExist('templates', [1,3])->getMessage()
);
