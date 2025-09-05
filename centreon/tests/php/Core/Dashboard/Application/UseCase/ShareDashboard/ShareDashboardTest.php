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
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboard;
use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboardRequest;
use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboardValidator;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->rights = $this->createMock(DashboardRights::class);
    $this->validator = $this->createMock(ShareDashboardValidator::class);
    $this->writeDashboardShareRepository = $this->createMock(WriteDashboardShareRepositoryInterface::class);
    $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->useCase = new ShareDashboard(
        $this->rights,
        $this->validator,
        $this->writeDashboardShareRepository,
        $this->readContactRepository,
        $this->readAccessGroupRepository,
        $this->readContactGroupRepository,
        $this->contact,
        $this->dataStorageEngine,
        $this->isCloudPlatform = false
    );
});

it('should present a Not Found Response when the dashboard does not exist', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;

    $this->rights->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateDashboard')
        ->willThrowException(DashboardException::theDashboardDoesNotExist($request->dashboardId));

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(NotFoundResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::theDashboardDoesNotExist($request->dashboardId)->getMessage());
});

it('should present a Forbidden Response when the dashboard is not shared with the user', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;

    $this->rights->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(false);

    $this->validator
        ->expects($this->once())
        ->method('validateDashboard')
        ->willThrowException(DashboardException::dashboardAccessRightsNotAllowedForWriting($request->dashboardId));

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(ForbiddenResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::dashboardAccessRightsNotAllowedForWriting($request->dashboardId)->getMessage());
});

it('should present an Invalid Argument Response when the edited contacts do not exist', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contacts = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
    ];

    $this->rights->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateContactsForOnPremise')
        ->willThrowException(DashboardException::theContactsDoNotExist([1]));

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::theContactsDoNotExist([1])->getMessage());
});

it('should present an Invalid Argument Response when the contacts are duplicated', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contacts = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
        [
            'id' => 1,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateContactsForOnPremise')
        ->willThrowException(DashboardException::contactForShareShouldBeUnique());

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::contactForShareShouldBeUnique()->getMessage());
});

it('should present an Invalid Argument Response when the users have insufficient ACLs', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contacts = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
        [
            'id' => 2,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateContactsForOnPremise')
        ->willThrowException(DashboardException::theContactGroupsDoesNotHaveDashboardAccessRights([2]));

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::theContactGroupsDoesNotHaveDashboardAccessRights([2])->getMessage());
});

it(
    'should present an Invalid Argument Response when the user is not admin '
    . 'and the request users are not members of his access groups',
    function (): void {
        $request = new ShareDashboardRequest();
        $request->dashboardId = 1;
        $request->contacts = [
            [
                'id' => 1,
                'role' => 'editor',
            ],
            [
                'id' => 2,
                'role' => 'editor',
            ],
        ];

        $this->rights
            ->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->validator
            ->expects($this->once())
            ->method('validateContactsForOnPremise')
            ->willThrowException(DashboardException::userAreNotInAccessGroups([2]));

        $response = ($this->useCase)($request);

        expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(DashboardException::userAreNotInAccessGroups([2])->getMessage());
    }
);

it('should present an Invalid Argument Response when the edited contact groups do not exist', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contactGroups = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateContactGroupsForOnPremise')
        ->willThrowException(DashboardException::theContactGroupsDoNotExist([1]));

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::theContactGroupsDoNotExist([1])->getMessage());
});

it('should present an Invalid Argument Response when the contact groups are duplicated', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contactGroups = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
        [
            'id' => 1,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateContactGroupsForOnPremise')
        ->willThrowException(DashboardException::contactGroupForShareShouldBeUnique());

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::contactGroupForShareShouldBeUnique()->getMessage());
});

it('should present an Invalid Argument Response when the contact groups have insufficient ACLs', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contactGroups = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
        [
            'id' => 2,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('validateContactGroupsForOnPremise')
        ->willThrowException(DashboardException::theContactGroupsDoesNotHaveDashboardAccessRights([2]));

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::theContactGroupsDoesNotHaveDashboardAccessRights([2])->getMessage());
});

it('should present an Invalid Argument Response when the user is not admin '
    . 'and the request contact groups are not members of his contact groups',
    function (): void {
        $request = new ShareDashboardRequest();
        $request->dashboardId = 1;
        $request->contactGroups = [
            [
                'id' => 1,
                'role' => 'editor',
            ],
            [
                'id' => 2,
                'role' => 'editor',
            ],
        ];

        $this->rights
            ->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->validator
            ->expects($this->once())
            ->method('validateContactGroupsForOnPremise')
            ->willThrowException(DashboardException::contactGroupIsNotInUserContactGroups([2]));

        $response = ($this->useCase)($request);

        expect($response)->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(DashboardException::contactGroupIsNotInUserContactGroups([2])->getMessage());
});

it ('should present an Error Response when an unhandled error occurs', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contactGroups = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
        [
            'id' => 2,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(false);

    $this->readContactRepository
        ->expects($this->once())
        ->method('findContactIdsByAccessGroups')
        ->willThrowException(new \Exception());

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(DashboardException::errorWhileUpdatingDashboardShare()->getMessage());
});

it('should present a No Content Response when no error occurs', function (): void {
    $request = new ShareDashboardRequest();
    $request->dashboardId = 1;
    $request->contactGroups = [
        [
            'id' => 1,
            'role' => 'editor',
        ],
        [
            'id' => 2,
            'role' => 'editor',
        ],
    ];

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->writeDashboardShareRepository
        ->expects($this->once())
        ->method('deleteDashboardShares');

    $this->writeDashboardShareRepository
        ->expects($this->once())
        ->method('addDashboardContactShares');

    $this->writeDashboardShareRepository
        ->expects($this->once())
        ->method('addDashboardContactGroupShares');

    $response = ($this->useCase)($request);

    expect($response)->toBeInstanceOf(NoContentResponse::class);
});
