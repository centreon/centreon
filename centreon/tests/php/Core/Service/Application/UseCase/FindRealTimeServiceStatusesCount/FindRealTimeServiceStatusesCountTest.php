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

namespace Tests\Core\Service\Application\UseCase\FindRealTimeServiceStatusesCount;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadRealTimeServiceRepositoryInterface;
use Core\Service\Application\UseCase\FindRealTimeServiceStatusesCount\FindRealTimeServiceStatusesCount;
use Core\Service\Application\UseCase\FindRealTimeServiceStatusesCount\FindRealTimeServiceStatusesCountResponse;
use Core\Service\Domain\Model\ServiceStatusesCount;

beforeEach(closure: function (): void {
    $this->useCase = new FindRealTimeServiceStatusesCount(
        $this->user = $this->createMock(ContactInterface::class),
        $this->repository = $this->createMock(ReadRealTimeServiceRepositoryInterface::class),
        $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
    );

    $this->presenter = new FindRealTimeServiceStatusesCountPresenterStub($this->createMock(PresenterFormatterInterface::class));
});

it('should present an Error response when something goes wrong in repository', function (): void {
    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $exception = new \Exception();

    $this->repository
        ->expects($this->once())
        ->method('findStatusesByRequestParameters')
        ->willThrowException($exception);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::errorWhileRetrievingServiceStatusesCount()->getMessage());
});

it('should present an Error response when something goes wrong with RequestParameters', function (): void {
    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->repository
        ->expects($this->once())
        ->method('findStatusesByRequestParameters')
        ->willThrowException(new RequestParametersTranslatorException());

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class);
});

it('should present a Forbidden response when user does not have sufficient rights', function (): void {
    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::accessNotAllowedForRealTime()->getMessage());
});

it('should present a FindRealTimeServiceStatusesCountResponse when ok - ADMIN', function (): void {
    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $statuses = new ServiceStatusesCount(
        totalOk: 1,
        totalWarning: 1,
        totalUnknown: 1,
        totalCritical: 1,
        totalPending: 1,
    );

    $this->repository
        ->expects($this->once())
        ->method('findStatusesByRequestParameters')
        ->willReturn($statuses);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindRealTimeServiceStatusesCountResponse::class)
        ->and($this->presenter->response->okStatuses)
        ->toBe($statuses->getTotalOk())
        ->and($this->presenter->response->warningStatuses)
        ->toBe($statuses->getTotalWarning())
        ->and($this->presenter->response->unknownStatuses)
        ->toBe($statuses->getTotalUnknown())
        ->and($this->presenter->response->criticalStatuses)
        ->toBe($statuses->getTotalCritical())
        ->and($this->presenter->response->pendingStatuses)
        ->toBe($statuses->getTotalPending())
        ->and($this->presenter->response->total)
        ->toBe($statuses->getTotal());
});
