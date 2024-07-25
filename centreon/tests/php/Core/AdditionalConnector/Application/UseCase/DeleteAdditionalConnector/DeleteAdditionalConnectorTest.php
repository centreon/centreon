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

namespace Tests\Core\AdditionalConnector\Application\UseCase\DeleteAdditionalConnector;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\Repository\WriteAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\UseCase\DeleteAdditionalConnector\DeleteAdditionalConnector;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new DeleteAdditionalConnector(
        $this->readAdditionalConnectorRepository = $this->createMock(ReadAdditionalConnectorRepositoryInterface::class),
        $this->writeAdditionalConnectorRepository = $this->createMock(WriteAdditionalConnectorRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->flags = new FeatureFlags(false, ''),
        $this->writeVaultAccRepositories = new \ArrayIterator([]),
    );
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);

    $this->testedAdditionalConnector = (new AdditionalConnector(
        id: $this->testedAccId = 1,
        name: 'additionalconnector-name',
        type: Type::VMWARE_V6,
        createdBy: $this->testedAccCreatedBy = 2,
        updatedBy: $this->testedAccCreatedBy,
        createdAt: $this->testedAccCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        updatedAt: $this->testedAccCreatedAt,
        parameters: [],
    ));
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readAdditionalConnectorRepository
        ->expects($this->once())
        ->method('find')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->testedAccId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(AdditionalConnectorException::deleteAdditionalConnector()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->testedAccId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(AdditionalConnectorException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the host template does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readAdditionalConnectorRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(null);

    ($this->useCase)($this->testedAccId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Additional Connector not found');
});

it('should present a NoContentResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
   $this->readAdditionalConnectorRepository
       ->expects($this->once())
       ->method('find')
       ->willReturn($this->testedAdditionalConnector);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->writeAdditionalConnectorRepository
        ->expects($this->once())
        ->method('delete');

    ($this->useCase)($this->testedAccId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
