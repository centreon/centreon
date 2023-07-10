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

namespace Tests\Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\MacroDto;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplate;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplateRequest;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
use Exception;

beforeEach(closure: function (): void {
    $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
    $this->writeServiceTemplateRepository = $this->createMock(WriteServiceTemplateRepositoryInterface::class);
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class);
    $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
    $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class);
    $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class);
    $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->presenter = new DefaultPresenter(
        $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new PartialUpdateServiceTemplate(
        $this->readServiceTemplateRepository,
        $this->writeServiceTemplateRepository,
        $this->readHostTemplateRepository,
        $this->readServiceCategoryRepository,
        $this->writeServiceCategoryRepository,
        $this->readAccessGroupRepository,
        $this->readServiceTemplateRepository,
        $this->readServiceMacroRepository,
        $this->writeServiceMacroRepository,
        $this->readCommandMacroRepository,
        $this->contact,
        $this->dataStorageEngine
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false],
            ]
        );

    ($this->useCase)(new PartialUpdateServiceTemplateRequest(1), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::updateNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the service template does not exist', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(null);

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Service template'))->getMessage());
});

it('should present a ConflictResponse when a host template does not exist', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];

    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with($request->hostTemplates)
        ->willReturn([$request->hostTemplates[0]]);

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::idsDoesNotExist('host_templates', [$request->hostTemplates[1]])->getMessage());
});

it('should present a ErrorResponse when an error occurs during host templates unlink', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];

    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with($request->hostTemplates)
        ->willReturn($request->hostTemplates);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id)
        ->willThrowException(new Exception());

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::errorWhileUpdating()->getMessage());
});

it('should present a ErrorResponse when an error occurs during host templates link', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];

    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with($request->hostTemplates)
        ->willReturn($request->hostTemplates);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('linkToHosts')
        ->with($request->id, $request->hostTemplates)
        ->willThrowException(new Exception());

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::errorWhileUpdating()->getMessage());
});

it('should present a NoContentResponse when everything has gone well for an admin user', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(20);
    $request->hostTemplates = [1, 8];
    $request->serviceCategories = [2, 3];
    $request->macros = [
        new MacroDto('MACROA', 'A', false, null),
        new MacroDto('MACROB', 'B1', false, null),
    ];

    $existingServiceTemplate = new ServiceTemplate(
        id: $request->id,
        name: 'fake_name',
        alias: 'fake_alias',
        commandId: 99,
    );

    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn($existingServiceTemplate);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with($request->hostTemplates)
        ->willReturn($request->hostTemplates);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('linkToHosts')
        ->with($request->id, $request->hostTemplates);

    $this->contact
        ->expects($this->exactly(3))
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByService')
        ->with($request->id)
        ->willReturn(
            array_map(
                fn (int $id): ServiceCategory => new ServiceCategory($id, 'name', 'alias'),
                $request->serviceCategories
            )
        );

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with($request->serviceCategories)
        ->willReturn($request->serviceCategories);

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromService')
        ->with($request->id, []);

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService')
        ->with($request->id, []);

    $serviceTemplateInheritances = [
        new ServiceTemplateInheritance(9, $existingServiceTemplate->getId()),
        new ServiceTemplateInheritance(8, 9),
        new ServiceTemplateInheritance(1, 8),
    ];

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findParents')
        ->with($request->id)
        ->willReturn($serviceTemplateInheritances);

    $macroA = new Macro($existingServiceTemplate->getId(), 'MACROA', 'A');
    $macroA->setDescription('');

    $macroB = new Macro($existingServiceTemplate->getId(), 'MACROB', 'B');
    $macroB->setDescription('');

    $this->readServiceMacroRepository
        ->expects($this->once())
        ->method('findByServiceIds')
        ->with($existingServiceTemplate->getId(), 9, 8, 1)
        ->willReturn([$macroA, $macroB]);

    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->with($existingServiceTemplate->getCommandId(), CommandMacroType::Service)
        ->willReturn([]);

    $this->writeServiceMacroRepository
        ->expects($this->once())
        ->method('update')
        ->with(new Macro($existingServiceTemplate->getId(), 'MACROB', 'B1'));

    $this->writeServiceMacroRepository
        ->expects($this->never())
        ->method('add');

    $this->writeServiceMacroRepository
        ->expects($this->never())
        ->method('delete');

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NoContentResponse when everything has gone well for a non-admin user', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];
    $request->serviceCategories = [2, 3];
    $accessGroups = [9, 11];

    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->contact
        ->expects($this->exactly(3))
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->contact)
        ->willReturn($accessGroups);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findAllExistingIds')
        ->with($request->hostTemplates)
        ->willReturn($request->hostTemplates);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findAllExistingIdsByAccessGroups')
        ->with($request->serviceCategories, $accessGroups)
        ->willReturn($request->serviceCategories);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('linkToHosts')
        ->with($request->id, $request->hostTemplates);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByServiceAndAccessGroups')
        ->with($request->id, $accessGroups)
        ->willReturn(
            array_map(
                fn (int $id): ServiceCategory => new ServiceCategory($id, 'name', 'alias'),
                $request->serviceCategories
            )
        );

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromService')
        ->with($request->id, []);

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService')
        ->with($request->id, []);

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
