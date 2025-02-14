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

namespace Tests\Core\Service\Application\UseCase\FindRealTimeUniqueServiceNames;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadRealTimeServiceRepositoryInterface;
use Core\Service\Application\UseCase\FindRealTimeUniqueServiceNames\FindRealTimeUniqueServiceNames;
use Core\Service\Application\UseCase\FindRealTimeUniqueServiceNames\FindRealTimeUniqueServiceNamesResponse;
use Tests\Core\Service\Infrastructure\API\FindRealTimeUniqueServiceNames\FindRealTimeUniqueServiceNamesPresenterStub;

beforeEach(function (): void {
    $this->useCase = new FindRealTimeUniqueServiceNames(
        $this->user = $this->createMock(ContactInterface::class),
        $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->repository = $this->createMock(ReadRealTimeServiceRepositoryInterface::class)
    );

    $this->presenter = new FindRealTimeUniqueServiceNamesPresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->repository
        ->expects($this->once())
        ->method('findUniqueServiceNamesByRequestParameters')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::accessNotAllowed()->getMessage());
});

it('should present a FindRealTimeUniqueServiceNamesResponse when everything goes well - ADMIN', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->repository
        ->expects($this->once())
        ->method('findUniqueServiceNamesByRequestParameters')
        ->willReturn(['uniqueServiceName1']);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindRealTimeUniqueServiceNamesResponse::class)
        ->and($this->presenter->response->names[0])
        ->toBe('uniqueServiceName1');
});
