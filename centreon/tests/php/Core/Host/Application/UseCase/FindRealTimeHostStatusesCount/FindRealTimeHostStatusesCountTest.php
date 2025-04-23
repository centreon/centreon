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

namespace Tests\Core\Host\Application\UseCase\FindRealTimeHostStatusesCount;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadRealTimeHostRepositoryInterface;
use Core\Host\Application\UseCase\FindRealTimeHostStatusesCount\FindRealTimeHostStatusesCount;
use Core\Host\Application\UseCase\FindRealTimeHostStatusesCount\FindRealTimeHostStatusesCountResponse;
use Core\Host\Domain\Model\HostStatusesCount;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(closure: function (): void {
    $this->useCase = new FindRealTimeHostStatusesCount(
        $this->user = $this->createMock(ContactInterface::class),
        $this->repository = $this->createMock(ReadRealTimeHostRepositoryInterface::class),
        $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
    );

    $this->presenter = new FindRealTimeHostStatusesCountPresenterStub($this->createMock(PresenterFormatterInterface::class));
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
        ->toBe(HostException::errorWhileRetrievingHostStatusesCount()->getMessage());
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
        ->toBe(HostException::accessNotAllowedForRealTime()->getMessage());
});

it('should present a FindRealTimeHostStatusesCountResponse when ok - ADMIN', function (): void {
    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $statuses = new HostStatusesCount(totalUp: 1, totalDown: 1, totalUnreachable: 1, totalPending: 1);

    $this->repository
        ->expects($this->once())
        ->method('findStatusesByRequestParameters')
        ->willReturn($statuses);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindRealTimeHostStatusesCountResponse::class)
        ->and($this->presenter->response->upStatuses)
        ->toBe($statuses->getTotalUp())
        ->and($this->presenter->response->downStatuses)
        ->toBe($statuses->getTotalDown())
        ->and($this->presenter->response->unreachableStatuses)
        ->toBe($statuses->getTotalUnreachable())
        ->and($this->presenter->response->pendingStatuses)
        ->toBe($statuses->getTotalPending())
        ->and($this->presenter->response->total)
        ->toBe($statuses->getTotal());
});
