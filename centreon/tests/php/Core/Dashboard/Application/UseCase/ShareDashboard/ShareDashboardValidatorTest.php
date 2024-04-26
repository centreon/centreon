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

namespace Tests\Core\Dashboard\Application\UseCase\ShareDashboard;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboardValidator;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;

beforeEach(function() {
    $this->contact = $this->createMock(ContactInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class);
    $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class);
    $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->validator = new ShareDashboardValidator(
        $this->contact,
        $this->readDashboardRepository,
        $this->readDashboardShareRepository,
        $this->readContactRepository,
        $this->readContactGroupRepository
    );
});

it('should throw a Dashboard Exception when the dashboard does not exist', function () {
    $this->readDashboardRepository
       ->method('existsOne')
       ->willReturn(false);

   $this->validator->validateDashboard(1);
})->throws(DashboardException::theDashboardDoesNotExist(1)->getMessage());

it('should throw a Dashboard Exception when the dashboard is not shared has editor', function () {
    $this->readDashboardRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    $this->readDashboardShareRepository
        ->expects($this->once())
        ->method('existsAsEditor')
        ->willReturn(false);


    $this->validator->validateDashboard(1, false);
})->throws(DashboardException::dashboardAccessRightsNotAllowedForWriting(1)->getMessage());

it('should throw a Dashboard Exception when the contacts does not exists', function() {
   $this->readContactRepository
       ->expects($this->once())
       ->method('exist')
       ->willReturn([1]);


   $this->validator->validateContacts(
       [
           [
               'id' => 1,
               'role' => 'editor'
           ],
           [
               'id' => 2,
               'role' => 'editor'
           ],
           [
               'id' => 3,
               'role' => 'editor'
           ],
       ],
   );
})->throws(DashboardException::theContactsDoNotExist([2,3])->getMessage());

it('should throw a Dashboard Exception when the contacts are duplicated', function () {
    $this->readContactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);

    $this->validator->validateContacts(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
            [
                'id' => 1,
                'role' => 'editor'
            ],
        ],
    );
})->throws(DashboardException::contactForShareShouldBeUnique()->getMessage());

it('should throw a Dashboard Exception when the request contacts does not have Dashboard ACLs', function () {
    $this->readContactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1,2]);

    $this->readDashboardShareRepository
        ->expects($this->once())
        ->method('findContactsWithAccessRightByContactIds')
        ->willReturn([
            new DashboardContactRole(
                1,
                'user',
                'user@email.com',
                [DashboardGlobalRole::Creator]
            )
        ]);

    $this->validator->validateContacts(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
            [
                'id' => 2,
                'role' => 'editor'
            ],
        ],
    );
})->throws(DashboardException::theContactsDoesNotHaveDashboardAccessRights([2])->getMessage());

it('should throw a Dashboard Exception when the request contacts does not have sufficient ACLs level', function () {
        $this->readContactRepository
            ->expects($this->once())
            ->method('exist')
            ->willReturn([1,2]);

        $this->readDashboardShareRepository
            ->expects($this->once())
            ->method('findContactsWithAccessRightByContactIds')
            ->willReturn([
                new DashboardContactRole(
                    1,
                    'user',
                    'user@email.com',
                    [DashboardGlobalRole::Viewer]
                )
            ]);

        $this->validator->validateContacts(
            [
                [
                    'id' => 1,
                    'role' => 'editor'
                ],
            ],
            []
        );
    })->throws(DashboardException::notSufficientAccessRightForUser(1, 'editor')->getMessage());

it('should throw a Dashboard Exception when the request contacts are not members of user Access Groups', function () {
    $this->readContactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);

    $this->readDashboardShareRepository
        ->expects($this->once())
        ->method('findContactsWithAccessRightByContactIds')
        ->willReturn([
            new DashboardContactRole(
                1,
                'user',
                'user@email.com',
                [DashboardGlobalRole::Creator]
            )
        ]);

    $this->validator->validateContacts(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
        ],
        [],
        false
    );
})->throws(DashboardException::userAreNotInAccessGroups([1])->getMessage());

it('should throw a Dashboard Exception when the contact groups do not exist', function () {
    $this->readContactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);


    $this->validator->validateContactGroups(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
            [
                'id' => 2,
                'role' => 'editor'
            ],
            [
                'id' => 3,
                'role' => 'editor'
            ],
        ],
        []
    );
})->throws(DashboardException::theContactGroupsDoNotExist([2,3])->getMessage());

it('should throw a Dashboard Exception when the contact groups are duplicated', function () {
    $this->readContactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);


    $this->validator->validateContactGroups(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
            [
                'id' => 1,
                'role' => 'editor'
            ],

        ],
        []
    );
})->throws(DashboardException::contactGroupForShareShouldBeUnique()->getMessage());

it('should throw a Dashboard Exception when the contact groups does not have Dashboard ACLs', function () {
    $this->readContactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1,2]);

    $this->readDashboardShareRepository
        ->expects($this->once())
        ->method('findContactGroupsWithAccessRightByContactGroupIds')
        ->willReturn([
            new DashboardContactGroupRole(
                1,
                'CG1',
                [DashboardGlobalRole::Creator]
            )
        ]);

    $this->validator->validateContactGroups(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
            [
                'id' => 2,
                'role' => 'editor'
            ]
        ],
        []
    );
})->throws(DashboardException::theContactGroupsDoesNotHaveDashboardAccessRights([2])->getMessage());

it('should throw a Dashboard Exception when the contact groups are not members of user contact groups', function () {
    $this->readContactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);

    $this->readDashboardShareRepository
        ->expects($this->once())
        ->method('findContactGroupsWithAccessRightByContactGroupIds')
        ->willReturn([
            new DashboardContactGroupRole(
                1,
                'CG1',
                [DashboardGlobalRole::Creator]
            )
        ]);

    $this->validator->validateContactGroups(
        [
            [
                'id' => 1,
                'role' => 'editor'
            ],
        ],
        [],
        false
    );
})->throws(DashboardException::contactGroupIsNotInUserContactGroups([1]
)->getMessage());