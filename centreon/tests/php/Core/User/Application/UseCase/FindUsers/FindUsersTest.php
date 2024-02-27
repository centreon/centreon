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

namespace Tests\Core\User\Application\UseCase\FindUsers;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\User\Application\Exception\UserException;
use Core\User\Application\Repository\ReadUserRepositoryInterface;
use Core\User\Application\UseCase\FindUsers\FindUsers;
use Core\User\Application\UseCase\FindUsers\FindUsersResponse;
use Core\User\Domain\Model\User;
use Tests\Core\User\Infrastructure\API\FindUsers\FindUsersPresenterStub;

beforeEach(function (): void {
    $this->presenter = new FindUsersPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindUsers(
        $this->readUserRepository = $this->createMock(ReadUserRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->isCloudPlatform = false,
    );

    $this->contact = new User(
        1,
        'alias',
        'name',
        'email',
        true,
        User::THEME_LIGHT,
        User::USER_INTERFACE_DENSITY_COMPACT,
        true
    );
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(UserException::errorWhileSearching(new \Exception())->getMessage());
    }
);

it(
    'should present an ForbiddenResponse when non-admin user doesn\'t have sufficient rights',
    function (): void {

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->readAccessGroupRepository
            ->expects($this->exactly(2))
            ->method('findByContact')
            ->willReturn([]);

        $this->user
            ->expects($this->exactly(3))
            ->method('hasTopologyRole')
            ->willReturn(false);

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(UserException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present an ErrorResponse when an exception of type RequestParametersTranslatorException is thrown',
    function (): void {

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $exception = new RequestParametersTranslatorException('Error');

        $this->readUserRepository
            ->expects($this->once())
            ->method('findAllByRequestParameters')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($exception->getMessage());
    }
);

it(
    'should present a valid response when the user has access to all users',
    function (): void {

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->readUserRepository
            ->expects($this->once())
            ->method('findAllByRequestParameters')
            ->willReturn([$this->contact]);

        ($this->useCase)($this->presenter);

        $response = $this->presenter->data;
        expect($response)->toBeInstanceOf(FindUsersResponse::class)
            ->and($response->users[0]->id)->toBe($this->contact->getId())
            ->and($response->users[0]->name)->toBe($this->contact->getName());
    }
);

it(
    'should present a valid response when the user has restricted access to users',
    function (): void {

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->readAccessGroupRepository
             ->expects($this->exactly(2))
             ->method('findByContact')
             ->willReturn([]);

        $this->user
            ->expects($this->exactly(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_HOME_DASHBOARD_ADMIN, false],
                    [Contact::ROLE_CONFIGURATION_CONTACTS_READ, true],
                ]
            );

        $this->readUserRepository
            ->expects($this->once())
            ->method('findByAccessGroupsAndRequestParameters')
            ->willReturn([$this->contact]);

        ($this->useCase)($this->presenter);

        $response = $this->presenter->data;
        expect($response)->toBeInstanceOf(FindUsersResponse::class)
            ->and($response->users[0]->id)->toBe($this->contact->getId())
            ->and($response->users[0]->name)->toBe($this->contact->getName());
    }
);
