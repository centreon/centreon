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

namespace Tests\Core\HostTemplate\Application\UseCase\DeleteHostTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\DeleteHostTemplate\DeleteHostTemplate;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;

beforeEach(function (): void {
    $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class);
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->user = $this->createMock(ContactInterface::class);
    $this->hostTemplate = $this->createMock(HostTemplate::class);
    $this->hostTemplateId = 1;
    $this->useCase = new DeleteHostTemplate(
        $this->writeHostTemplateRepository,
        $this->readHostTemplateRepository,
        $this->user,
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
    );
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostTemplate);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->hostTemplateId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::deleteHostTemplate()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->hostTemplateId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the host template does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->hostTemplateId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host template not found');
});

it('should present a NoContentResponse on success', function (): void {
    $hostTemplateId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostTemplate);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('delete');

    ($this->useCase)($hostTemplateId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
