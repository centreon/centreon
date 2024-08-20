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

namespace Tests\Core\Contact\Application\UseCase\FindContactGroups;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Exception\ContactGroupException;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\UseCase\FindContactGroups\FindContactGroups;
use Core\Contact\Application\UseCase\FindContactGroups\FindContactGroupsResponse;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactGroupType;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->requestParameters = new RequestParameters();
    $this->useCase = new FindContactGroups(
        $this->accessGroupRepository,
        $this->contactGroupRepository,
        $this->user,
        $this->requestParameters,
        false,
    );
});

it('should present an ErrorResponse while an exception occurred', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('findAll')
        ->with($this->requestParameters)
        ->willThrowException(new \Exception());

    $presenter = new FindContactGroupsPresenterStub($this->presenterFormatter);
    $this->useCase->__invoke($presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(ContactGroupException::errorWhileSearchingForContactGroups()->getMessage());
});

it('should present an ForbiddenResponse if the user doesnt have the read menu access to contact group', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturnCallback(fn(string $role): bool => match ($role) {
            Contact::ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ, Contact::ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ_WRITE => false,
            default => true,
        });

    $presenter = new FindContactGroupsPresenterStub($this->presenterFormatter);
    $this->useCase->__invoke($presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(ContactGroupException::notAllowed()->getMessage());
});

it('should call the method findAll if the user is admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('findAll')
        ->with($this->requestParameters);

    $presenter = new FindContactGroupsPresenterStub($this->presenterFormatter);
    $this->useCase->__invoke($presenter);
});

it('should call the method findByAccessGroups if the user is not admin', function (): void {
    $this->user
        ->expects($this->any())
        ->method('getId')
        ->willReturn(1);

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ)
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $accessGroupsFound = [new AccessGroup(1, 'fake_name', 'fake_alias')];

    $this->accessGroupRepository
        ->expects($this->any())
        ->method('findByContact')
        ->willReturn($accessGroupsFound);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('findByAccessGroups')
        ->with($accessGroupsFound, $this->requestParameters)
        ->willReturn([]);

    $presenter = new FindContactGroupsPresenterStub($this->presenterFormatter);
    $this->useCase->__invoke($presenter);
});

it('should present a FindContactGroupsResponse when no error occured', function (): void {
    $contactGroup = new ContactGroup(1, 'fake_name', 'fake_alias', 'fake_comments', true, ContactGroupType::Local);
    $this->contactGroupRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([$contactGroup]);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $presenter = new FindContactGroupsPresenterStub($this->presenterFormatter);
    $this->useCase->__invoke($presenter);

    expect($presenter->response)
        ->toBeInstanceOf(FindContactGroupsResponse::class)
        ->and($presenter->response->contactGroups[0])
        ->toBe(
            [
                'id' => $contactGroup->getId(),
                'name' => $contactGroup->getName(),
                'alias' => $contactGroup->getAlias(),
                'comments' => $contactGroup->getComments(),
                'type' => $contactGroup->getType() === ContactGroupType::Local ? 'local' : 'ldap',
                'is_activated' => $contactGroup->isActivated(),
            ]
        );
});
