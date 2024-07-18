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

namespace Tests\Core\ServiceTemplate\Application\UseCase\DeleteServiceTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\DeleteServiceTemplate\DeleteServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Tests\Core\ServiceTemplate\Infrastructure\API\DeleteServiceTemplate\DeleteServiceTemplatePresenterStub;

beforeEach(closure: function (): void {
    $this->presenter = new DeleteServiceTemplatePresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new DeleteServiceTemplate(
        $this->readRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->writeRepository = $this->createMock(WriteServiceTemplateRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),
    );
    $this->serviceTemplateLockedFound = new ServiceTemplate(
        1,
        'fake_name',
        'fake_alias',
        ...['isLocked' => true]
    );
    $this->serviceTemplateNotLockedFound = new ServiceTemplate(
        1,
        'fake_name',
        'fake_alias',
        ...['isLocked' => false]
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false],
            ]
        );

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the service template is not found', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with(1)
        ->willReturn(null);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe((new NotFoundResponse('Service template'))->getMessage());
});

it('should present an ErrorResponse when the service template is locked', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($this->serviceTemplateLockedFound->getId())
        ->willReturn($this->serviceTemplateLockedFound);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::cannotBeDelete($this->serviceTemplateLockedFound->getName())->getMessage());
});

it('should present a NoContentResponse when the service template has been deleted', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($this->serviceTemplateNotLockedFound->getId())
        ->willReturn($this->serviceTemplateNotLockedFound);

    $this->writeRepository
        ->expects($this->once())
        ->method('deleteById')
        ->with($this->serviceTemplateNotLockedFound->getId());

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)->toBeInstanceOf(NoContentResponse::class);
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::errorWhileDeleting(new \Exception())->getMessage());
});
