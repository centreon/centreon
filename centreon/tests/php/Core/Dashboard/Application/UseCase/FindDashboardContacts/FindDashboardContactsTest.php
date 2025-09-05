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

namespace Tests\Core\Dashboard\Application\UseCase\FindDashboardContacts;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\FindDashboardContacts;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\FindDashboardContactsResponse;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\Response\ContactsResponseDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->contact = $this->createMock(ContactInterface::class);
    $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);

    $this->useCaseOnPremise = new FindDashboardContacts(
        $this->requestParameters,
        $this->rights,
        $this->contact,
        $this->readDashboardShareRepository,
        $this->readAccessGroupRepository,
        $this->readContactRepository,
        $this->readContactGroupRepository,
        $this->isCloudPlatform = false
    );

    $this->useCaseCloud = new FindDashboardContacts(
        $this->requestParameters,
        $this->rights,
        $this->contact,
        $this->readDashboardShareRepository,
        $this->readAccessGroupRepository,
        $this->readContactRepository,
        $this->readContactGroupRepository,
        $this->isCloudPlatform = true
    );
});

it(
    'should return an ErrorResponse if an error is raised during user search - AS ADMIN - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightByRequestParameters')
            ->willThrowException(new \Exception());

        $response = ($this->useCaseOnPremise)();

        expect($response)->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())->toBe(DashboardException::errorWhileSearchingSharableContacts()->getMessage());
    }
);

it(
    'should return an ErrorResponse if an error is raised during admin search - AS ADMIN - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightByRequestParameters')
            ->willReturn([]);

        $this->readContactRepository->expects($this->once())
            ->method('findAdminWithRequestParameters')
            ->willThrowException(new \Exception());

        $response = ($this->useCaseOnPremise)();

        expect($response)->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())->toBe(DashboardException::errorWhileSearchingSharableContacts()->getMessage());
    }
);

it(
    'should return a FindDashboardContactsResponse if no error is raised - AS ADMIN - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $userDashboardRole = new DashboardContactRole(
            contactId: 1,
            contactName: 'test',
            contactEmail: 'email',
            roles: [DashboardGlobalRole::Creator]
        );

        $adminContact = (new Contact())
            ->setAdmin(true)
            ->setName('adminUser')
            ->setId(2)
            ->setEmail('email');

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightByRequestParameters')
            ->willReturn([$userDashboardRole]);

        $this->readContactRepository->expects($this->once())
            ->method('findAdminWithRequestParameters')
            ->willReturn([$adminContact]);

        $response = ($this->useCaseOnPremise)();

        $contacts = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactsResponse::class)
            ->and($contacts[0])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[0]->id)->toBe(1)
            ->and($contacts[0]->name)->toBe('test')
            ->and($contacts[0]->email)->toBe('email')
            ->and($contacts[0]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator)
            ->and($contacts[1])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[1]->id)->toBe(2)
            ->and($contacts[1]->name)->toBe('adminUser')
            ->and($contacts[1]->email)->toBe('email')
            ->and($contacts[1]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator);
    }
);

it(
    'should return an ErrorResponse if an error is raised during user access group search - AS USER - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readAccessGroupRepository->expects($this->once())
            ->method('findByContact')
            ->willThrowException(new \Exception());

        $response = ($this->useCaseOnPremise)();

        expect($response)->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())->toBe(DashboardException::errorWhileSearchingSharableContacts()->getMessage());
    }
);

it(
    'should return an ErrorResponse if an error is raised during user search - AS USER - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readAccessGroupRepository->expects($this->any())
            ->method('findByContact')
            ->willReturn([new AccessGroup(1, 'name', 'alias')]);

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightByACLGroupsAndRequestParameters')
            ->willThrowException(new \Exception());

        $response = ($this->useCaseOnPremise)();

        expect($response)->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())->toBe(DashboardException::errorWhileSearchingSharableContacts()->getMessage());
    }
);

it(
    'should return a FindDashboardContactsResponse if no error is raised - AS USER - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readAccessGroupRepository->expects($this->any())
            ->method('findByContact')
            ->willReturn([new AccessGroup(1, 'name', 'alias')]);

        $userDashboardRole = new DashboardContactRole(
            contactId: 1,
            contactName: 'test',
            contactEmail: 'email',
            roles: [DashboardGlobalRole::Creator]
        );

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightByACLGroupsAndRequestParameters')
            ->willReturn([$userDashboardRole]);

        $response = ($this->useCaseOnPremise)();

        $contacts = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactsResponse::class)
            ->and($contacts[0])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[0]->id)->toBe(1)
            ->and($contacts[0]->name)->toBe('test')
            ->and($contacts[0]->email)->toBe('email')
            ->and($contacts[0]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator);
    }
);

it(
    'should return a FindDashboardContactsResponse if no error is raised - AS ADMIN - Cloud',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $userDashboardRoles = [
            new DashboardContactRole(
                contactId: 1,
                contactName: 'test',
                contactEmail: 'email',
                roles: [DashboardGlobalRole::Creator]
            ),
            new DashboardContactRole(
                contactId: 2,
                contactName: 'test-cloud-admin',
                contactEmail: 'email',
                roles: [DashboardGlobalRole::Administrator]
            ),
        ];

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightByRequestParameters')
            ->willReturn($userDashboardRoles);

        $response = ($this->useCaseCloud)();

        $contacts = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactsResponse::class)
            ->and($contacts[0])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[0]->id)->toBe(1)
            ->and($contacts[0]->name)->toBe('test')
            ->and($contacts[0]->email)->toBe('email')
            ->and($contacts[0]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator)
            ->and($contacts[1])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[1]->id)->toBe(2)
            ->and($contacts[1]->name)->toBe('test-cloud-admin')
            ->and($contacts[1]->email)->toBe('email')
            ->and($contacts[1]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator);
    }
);

it(
    'should return a FindDashboardContactsResponse if no error is raised - AS USER - Cloud',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readContactGroupRepository->expects($this->once())
            ->method('findAllByUserId')
            ->willReturn([new ContactGroup(1, 'name', 'alias')]);

        $userDashboardRoles = [
            new DashboardContactRole(
                contactId: 1,
                contactName: 'test',
                contactEmail: 'email',
                roles: [DashboardGlobalRole::Creator]
            ),
            new DashboardContactRole(
                contactId: 2,
                contactName: 'test-cloud-admin',
                contactEmail: 'email',
                roles: [DashboardGlobalRole::Administrator]
            ),
        ];

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactsWithAccessRightsByContactGroupsAndRequestParameters')
            ->willReturn($userDashboardRoles);

        $response = ($this->useCaseCloud)();

        $contacts = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactsResponse::class)
            ->and($contacts[0])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[0]->id)->toBe(1)
            ->and($contacts[0]->name)->toBe('test')
            ->and($contacts[0]->email)->toBe('email')
            ->and($contacts[0]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator)
            ->and($contacts[1])->toBeInstanceOf(ContactsResponseDto::class)
            ->and($contacts[1]->id)->toBe(2)
            ->and($contacts[1]->name)->toBe('test-cloud-admin')
            ->and($contacts[1]->email)->toBe('email')
            ->and($contacts[1]->mostPermissiveRole)->toBe(DashboardGlobalRole::Creator);
    }
);

