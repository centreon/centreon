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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Notification\Application\UseCase\FindNotifiableContactGroups;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\UseCase\FindNotifiableContactGroups\FindNotifiableContactGroups;
use Core\Notification\Application\UseCase\FindNotifiableContactGroups\FindNotifiableContactGroupsResponse;
use Tests\Core\Notification\Infrastructure\API\FindNotifiableContactGroups\FindNotifiableContactGroupsPresenterStub;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new FindNotifiableContactGroupsPresenterStub($this->presenterFormatter);
    $this->readRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
});

it('should present a Not Found Response when there are no contact groups.', function (): void {
    $useCase = new FindNotifiableContactGroups($this->readRepository);

    $this->readRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([]);

    $useCase($this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe('Contact Groups not found');
});

it('should present an Error Response when an unhandled error occurs.', function (): void {
    $useCase = new FindNotifiableContactGroups($this->readRepository);

    $this->readRepository
        ->expects($this->once())
        ->method('findAll')
        ->willThrowException(new \Exception());

    $useCase($this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe('Error while retrieving contact groups');
});

it('should present a FindNotifiableContactGroups Response.', function (): void {
    $contactGroups = [
        new ContactGroup(1, 'Administrators'),
        new ContactGroup(2, 'Editors'),
    ];

    $useCase = new FindNotifiableContactGroups($this->readRepository);

    $this->readRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn($contactGroups);

    $useCase($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindNotifiableContactGroupsResponse::class)
        ->and($this->presenter->response->notifiableContactGroups)
        ->toBeArray()
        ->and($this->presenter->response->notifiableContactGroups[0]->id)
        ->toBe(1)
        ->and($this->presenter->response->notifiableContactGroups[0]->name)
        ->toBe('Administrators')
        ->and($this->presenter->response->notifiableContactGroups[1]->id)
        ->toBe(2)
        ->and($this->presenter->response->notifiableContactGroups[1]->name)
        ->toBe('Editors');
});