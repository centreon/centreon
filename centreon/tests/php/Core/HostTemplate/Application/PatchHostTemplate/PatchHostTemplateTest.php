<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\HostTemplate\Application\UseCase\PatchHostTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\HostMacro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\HostMacro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\HostMacro\Domain\Model\HostMacro;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\PatchHostTemplate\PatchHostTemplate;
use Core\HostTemplate\Application\UseCase\PatchHostTemplate\PatchHostTemplateRequest;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function () {
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class);
    $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class);
    $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->user = $this->createMock(ContactInterface::class);

    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);

    $this->hostTemplate = $this->createMock(HostTemplate::class);
    $this->hostTemplateId = 1;
    $this->checkCommandId = 1;

    $this->useCase = new PatchHostTemplate(
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
        $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->macroA = new HostMacro($this->hostTemplateId, 'macroNameA', 'macroValueA');
    $this->macroA->setOrder(0);
    $this->macroB = new HostMacro($this->hostTemplateId, 'macroNameB', 'macroValueB');
    $this->macroB->setOrder(1);
    $this->commandMacro = new CommandMacro(1, CommandMacroType::Host, 'commandMacroName');
    $this->commandMacros = [
        $this->commandMacro->getName() => $this->commandMacro
    ];
    $this->hostMacros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];
    $this->inheritanceLineIds = [
        $this->hostTemplateId
    ];

    $this->request = new PatchHostTemplateRequest();
    $this->request->macros = [
        [
            'name' =>   $this->macroA->getName(),
            'value' =>  $this->macroA->getValue() . '_edit',
            'is_password' =>  $this->macroA->isPassword(),
            'description' =>  $this->macroA->getDescription()
        ],
        [
            'name' =>   'macroNameC',
            'value' =>  'macroValueC',
            'is_password' =>  false,
            'description' =>  null
        ],
    ];

});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::writeActionsNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::patchHostTemplate()->getMessage());
});

it('should present a NotFoundResponse when the host template does not exist', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host template not found');
});

it('should present a NoContentResponse on success', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostTemplate);
    $this->hostTemplate
        ->expects($this->atLeastOnce())
        ->method('getId')
        ->willReturn($this->hostTemplateId);
    $this->hostTemplate
        ->expects($this->atLeastOnce())
        ->method('getCheckCommandId')
        ->willReturn($this->checkCommandId);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findInheritanceLine')
        ->willReturn($this->inheritanceLineIds);
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostIds')
        ->willReturn($this->hostMacros);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);

    $this->writeHostMacroRepository
        ->expects($this->once())
        ->method('delete');
    $this->writeHostMacroRepository
        ->expects($this->once())
        ->method('add');
    $this->writeHostMacroRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
