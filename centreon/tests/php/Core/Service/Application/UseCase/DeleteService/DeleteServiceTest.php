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

namespace Tests\Core\Service\Application\UseCase\DeleteService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Application\UseCase\DeleteService\DeleteService;

beforeEach(closure: function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new DeleteService(
        $this->readRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->writeRepository = $this->createMock(WriteServiceRepositoryInterface::class),
        $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->storageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the service is not found', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Service'))->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceException::errorWhileDeleting(new \Exception())->getMessage());
});

it('should present a NoContentResponse when the service has been deleted', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findMonitoringServerId')
        ->willReturn(1);

    $this->writeRepository
        ->expects($this->once())
        ->method('delete')
        ->with(1);

    $this->writeMonitoringServerRepository
        ->expects($this->once())
        ->method('notifyConfigurationChange');

    ($this->useCase)(1, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
