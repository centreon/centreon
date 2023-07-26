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
use Core\Notification\Domain\Model\{NotifiableHost, NotifiableResource, NotifiableService, NotificationServiceEvent};
use Tests\Core\Notification\Infrastructure\API\FindNotifiableResources\FindNotifiableResourcesPresenterStub;

beforeEach(function () {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new FindNotifiableResourcesPresenterStub($this->presenterFormatter);
    $this->readRepository = $this->createMock(ReadNotifiableResourceRepositoryInterface::class);
});

it('should present a Forbidden Response when user doesn\'t have access to endpoint.', function () {
    $contact = (new Contact())->setAdmin(false)->setId(1);
    $requestUid = '';

    $useCase = new FindNotifiableResources($contact, $this->readRepository);

    $useCase($this->presenter, $requestUid);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe(NotificationException::listResourcesNotAllowed()->getMessage());
});

it('should present a Not Found Response when there\'re no notifiable resources.', function () {
    $contact = (new Contact())->setAdmin(true)->setId(1);
    $requestUid = '';

    $useCase = new FindNotifiableResources($contact, $this->readRepository);
    $this->readRepository
        ->expects($this->once())
        ->method('findAllForActivatedNotifications')
        ->willReturn([]);

    $useCase($this->presenter, $requestUid);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->responseStatus->getMessage())
        ->toBe('Notifiable resources not found');
});

it('should present an Error Response when an unhandled error occurs.', function () {
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
    function () {
        $contact = (new Contact())->setAdmin(true)->setId(1);
        $requestUid = 'a21cafb4c405e6997671a02e578b9b1e';

        $service = new NotifiableService(13, 'Ping', null, [NotificationServiceEvent::Ok]);
        $host = new NotifiableHost(24, 'myHost', 'myHost', [], [$service]);
        $resource = new NotifiableResource(1, [$host]);
        $result = [$resource];

        $useCase = new FindNotifiableResources($contact, $this->readRepository);
        $this->readRepository
            ->expects($this->once())
            ->method('findAllForActivatedNotifications')
            ->willReturn($result);

        $useCase($this->presenter, $requestUid);

        expect($this->presenter->responseStatus)
            ->toBeInstanceOf(NotModifiedResponse::class);
    }
);

it(
    'should present a FindNotifiableResourcesResponse if request UID header isn\'t equal to MD5 hash of database query',
    function () {
        $contact = (new Contact())->setAdmin(true)->setId(1);
        $requestUid = '';

        $service = new NotifiableService(13, 'Ping', null, [NotificationServiceEvent::Ok]);
        $host = new NotifiableHost(24, 'myHost', 'myHost', [], [$service]);
        $resource = new NotifiableResource(1, [$host]);
        $result = [$resource];

        $useCase = new FindNotifiableResources($contact, $this->readRepository);
        $this->readRepository
            ->expects($this->once())
            ->method('findAllForActivatedNotifications')
            ->willReturn($result);

        $useCase($this->presenter, $requestUid);

        expect($this->presenter->response)
            ->toBeInstanceOf(FindNotifiableResourcesResponse::class)
            ->and($this->presenter->response->uid)
            ->toBe(\hash('md5', \json_encode($result, JSON_THROW_ON_ERROR)))
            ->and($this->presenter->response->notifiableResources)
            ->toBeArray()
            ->and($this->presenter->response->notifiableResources[0])
            ->toBeInstanceOf(NotifiableResourceDto::class)
            ->and($this->presenter->response->notifiableResources[0]->notificationId)
            ->toBe($resource->getNotificationId())
            ->and($this->presenter->response->notifiableResources[0]->hosts)
            ->toBeArray()
            ->and($this->presenter->response->notifiableResources[0]->hosts[0])
            ->toBeInstanceOf(NotifiableHostDto::class)
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->id)
            ->toBe($host->getId())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->name)
            ->toBe($host->getName())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->alias)
            ->toBe($host->getAlias())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->events)
            ->toBe(0)
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services)
            ->toBeArray()
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->id)
            ->toBe($service->getId())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->name)
            ->toBe($service->getName())
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->alias)
            ->toBeNull()
            ->and($this->presenter->response->notifiableResources[0]->hosts[0]->services[0]->events)
            ->toBe(1);
    }
);
