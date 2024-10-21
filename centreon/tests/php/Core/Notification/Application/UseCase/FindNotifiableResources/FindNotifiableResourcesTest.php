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

namespace Tests\Notification\Application\UseCase\FindNotifiableResources;

use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, NotFoundResponse, NotModifiedResponse};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotifiableResourceRepositoryInterface;
use Core\Notification\Application\UseCase\FindNotifiableResources\{
    FindNotifiableResources,
    FindNotifiableResourcesResponse,
    NotifiableHostDto,
    NotifiableResourceDto
};
use Core\Notification\Domain\Model\{NotifiableHost, NotifiableResource, NotifiableService, ServiceEvent};
use Tests\Core\Notification\Infrastructure\API\FindNotifiableResources\FindNotifiableResourcesPresenterStub;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new FindNotifiableResourcesPresenterStub($this->presenterFormatter);
    $this->readRepository = $this->createMock(ReadNotifiableResourceRepositoryInterface::class);
});

it('should present a Forbidden Response when user doesn\'t have access to endpoint.', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1);
    $requestUid = '';

    $useCase = new FindNotifiableResources($contact, $this->readRepository);

    $useCase($this->presenter, $requestUid);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe(NotificationException::listResourcesNotAllowed()->getMessage());
});

it('should present an Error Response when an unhandled error occurs.', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1);
    $requestUid = '';

    $useCase = new FindNotifiableResources($contact, $this->readRepository);
    $this->readRepository
        ->expects($this->once())
        ->method('findAllForActivatedNotifications')
        ->willThrowException(new \Exception());

    $useCase($this->presenter, $requestUid);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe(NotificationException::errorWhileListingResources()->getMessage());
});

it(
    'should present a Not Modified Response when request UID header is equal to MD5 hash of database query',
    function (iterable $notifiableResources): void {
        $contact = (new Contact())->setAdmin(true)->setId(1);
        $requestUid = '40f7bc75fcc26954c7190dc743d0a9a6';

        $useCase = new FindNotifiableResources($contact, $this->readRepository);
        $this->readRepository
            ->expects($this->once())
            ->method('findAllForActivatedNotifications')
            ->willReturn($notifiableResources);

        $useCase($this->presenter, $requestUid);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(NotModifiedResponse::class);
    }
)->with([
    [
        [
            new NotifiableResource(
            1,
            [
                new NotifiableHost(
                    24,
                    'myHost',
                    'mytHost',
                    [],
                    [new NotifiableService(13, 'Ping', null, [ServiceEvent::Ok])]
                )
            ]
            )
        ]
    ]
]);

it(
    'should present a FindNotifiableResourcesResponse if request UID header isn\'t equal to MD5 hash of database query',
    function (iterable $resources): void {
        $contact = (new Contact())->setAdmin(true)->setId(1);
        $requestUid = '';

        $useCase = new FindNotifiableResources($contact, $this->readRepository);
        $this->readRepository
            ->expects($this->once())
            ->method('findAllForActivatedNotifications')
            ->willReturn($resources);

        $useCase($this->presenter, $requestUid);

        expect($this->presenter->response)
            ->toBeInstanceOf(FindNotifiableResourcesResponse::class)
            ->and($this->presenter->response->uid)
            ->toBe('40f7bc75fcc26954c7190dc743d0a9a6')
            ->and($this->presenter->response->notifiableResources)
            ->toBeArray()
            ->and($this->presenter->response->notifiableResources[0])
            ->toBeInstanceOf(NotifiableResourceDto::class)
            ->and($this->presenter->response->notifiableResources[0]->notificationId)
            ->toBe($resources[0]->getNotificationId())
            ->and($this->presenter->response->notifiableResources[0]->hosts)
            ->toBeArray()
            ->and($this->presenter->response->notifiableResources[0]->hosts[0])
            ->toBeInstanceOf(NotifiableHostDto::class)
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->id)
            ->toBe($resources[0]->getHosts()[0]->getId())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->name)
            ->toBe($resources[0]->getHosts()[0]->getName())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->alias)
            ->toBe($resources[0]->getHosts()[0]->getAlias())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->events)
            ->toBe(0)
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services)
            ->toBeArray()
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->id)
            ->toBe($resources[0]->getHosts()[0]->getServices()[0]->getId())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->name)
            ->toBe($resources[0]->getHosts()[0]->getServices()[0]->getName())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->alias)
            ->toBeNull()
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->events)
            ->toBe(1);
    }
)->with([
    [
        [
            new NotifiableResource(
            1,
            [
                new NotifiableHost(
                    24,
                    'myHost',
                    'mytHost',
                    [],
                    [new NotifiableService(13, 'Ping', null, [ServiceEvent::Ok])]
                )
            ]
            )
        ]
    ]
]);
