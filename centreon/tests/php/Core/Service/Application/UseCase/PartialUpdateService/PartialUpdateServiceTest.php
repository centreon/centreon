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

namespace Tests\Core\ServiceTemplate\Application\UseCase\PartialUpdateService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Application\UseCase\PartialUpdateService\MacroDto;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateService;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateServiceRequest;
use Core\Service\Application\UseCase\PartialUpdateService\PartialUpdateServiceValidation;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Exception;

beforeEach(closure: function (): void {
    $this->presenter = new DefaultPresenter(
        $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new PartialUpdateService(
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),
        $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->writeServiceGroupRepository = $this->createMock(WriteServiceGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->validation = $this->createMock(PartialUpdateServiceValidation::class),
        $this->optionService = $this->createMock(OptionService::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->isCloudPlatform = false,
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
    );

    $this->service = new Service(id: 1, name: 'service-name', hostId: 2);

    // Settup request
    $this->request = new PartialUpdateServiceRequest();
    $this->request->hostId = 3;
    $this->request->name = 'service-name-edited';
    $this->request->commandId = 8;
    $this->request->graphTemplateId = 9;
    $this->request->eventHandlerId = 10;
    $this->request->checkTimePeriodId = 11;
    $this->request->notificationTimePeriodId = 12;
    $this->request->iconId = 13;
    $this->request->severityId = 14;

    // Settup categories
    $this->categoryA = new ServiceCategory(4, 'cat-name-A', 'cat-alias-A');
    $this->categoryB = new ServiceCategory(5, 'cat-name-B', 'cat-alias-B');

    $this->request->categories = [$this->categoryB->getId()];

    // Settup groups
    $this->groupA = new ServiceGroup(6, 'grp-name-A', 'grp-alias-A', null, 'comment A', true);
    $this->groupB = new ServiceGroup(7, 'grp-name-B', 'grp-alias-B', null, 'comment B', true);

    $this->request->groups = [$this->groupB->getId()];

    // Settup macros
    $this->macroA = new Macro($this->service->getId(), 'macroNameA', 'macroValueA');
    $this->macroA->setOrder(0);
    $this->macroB = new Macro($this->service->getId(), 'macroNameB', 'macroValueB');
    $this->macroB->setOrder(1);
    $this->commandMacro = new CommandMacro(1, CommandMacroType::Service, 'commandMacroName');
    $this->commandMacros = [
        $this->commandMacro->getName() => $this->commandMacro,
    ];
    $this->macros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];
    $this->parentTemplates = [15, 16];
    $this->inheritance = [
        new ServiceInheritance($this->parentTemplates[0], $this->service->getId()),
        new ServiceInheritance($this->parentTemplates[1], $this->parentTemplates[0]),
    ];

    $this->request->template = $this->parentTemplates[0];
    $this->request->macros = [
        new MacroDto(
            name: $this->macroA->getName(),
            value: $this->macroA->getValue() . '_edit',
            isPassword: $this->macroA->isPassword(),
            description: $this->macroA->getDescription()
        ),
        new MacroDto(
            name: 'macroNameC',
            value: 'macroValueC',
            isPassword: false,
            description: null
        ),
    ];
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)(new PartialUpdateServiceRequest(), $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::editNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willThrowException(new Exception);

    ($this->useCase)(new PartialUpdateServiceRequest(), $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::errorWhileEditing()->getMessage());
});

it('should present a NotFoundResponse when the service does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)(new PartialUpdateServiceRequest(), $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Service'))->getMessage());
});

it('should present a ConflictResponse when the host ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->service);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidHost')
        ->willThrowException(
            ServiceException::idDoesNotExist('host_id', $this->request->hostId)
        );

    ($this->useCase)($this->request, $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::idDoesNotExist('host_id', $this->request->hostId)->getMessage());
});

it('should present a ConflictResponse when a category does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->service);

    // Service
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions');
    $this->writeServiceRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidCategories')
        ->willThrowException(ServiceException::idsDoNotExist('categories', $this->request->categories));

    ($this->useCase)($this->request, $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::idsDoNotExist('categories', $this->request->categories)->getMessage());
});

it('should present a ConflictResponse when a group does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(3))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->service);

    // Service
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions');
    $this->writeServiceRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn([$this->categoryA]);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService');
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromService');

    // Groups
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidGroups')
        ->willThrowException(ServiceException::idsDoNotExist('groups', $this->request->groups));

    ($this->useCase)($this->request, $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::idsDoNotExist('groups', $this->request->groups)->getMessage());
});

it('should present a NoContentResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(4))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->service);

    // Service
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions');

    $this->validation->expects($this->once())->method('assertIsValidHost');
    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidGraphTemplate');
    $this->validation->expects($this->once())->method('assertIsValidEventHandler');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->once())->method('assertIsValidIcon');
    $this->validation->expects($this->once())->method('assertIsValidSeverity');

    $this->writeServiceRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->validation->expects($this->once())->method('assertAreValidCategories');
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn([$this->categoryA]);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService');
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromService');

    // Groups
    $this->validation->expects($this->once())->method('assertAreValidGroups');
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn([$this->groupA]);
    $this->writeServiceGroupRepository
        ->expects($this->once())
        ->method('unlink');
    $this->writeServiceGroupRepository
        ->expects($this->once())
        ->method('link');

    // Macros
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($this->inheritance);
    $this->readServiceMacroRepository
        ->expects($this->once())
        ->method('findByServiceIds')
        ->willReturn($this->macros);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);
    $this->writeServiceMacroRepository
        ->expects($this->once())
        ->method('delete');
    $this->writeServiceMacroRepository
        ->expects($this->once())
        ->method('add');
    $this->writeServiceMacroRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->service->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});
