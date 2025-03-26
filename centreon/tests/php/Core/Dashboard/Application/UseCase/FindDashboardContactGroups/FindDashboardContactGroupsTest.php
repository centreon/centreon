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

namespace Tests\Core\Dashboard\Application\UseCase\FindDashboardContactGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\FindDashboardContactGroups;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\FindDashboardContactGroupsResponse;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->contact = $this->createMock(ContactInterface::class);
    $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class);
    $this->readAccessgroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);

    $this->useCaseOnPremise = new FindDashboardContactGroups(
        $this->requestParameters,
        $this->rights,
        $this->contact,
        $this->readDashboardShareRepository,
        $this->readAccessgroupRepository,
        false
    );

    $this->useCaseCloud = new FindDashboardContactGroups(
        $this->requestParameters,
        $this->rights,
        $this->contact,
        $this->readDashboardShareRepository,
        $this->readAccessgroupRepository,
        true
    );
});

it(
    'should return an ErrorResponse if an error is raised',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactGroupsWithAccessRightByRequestParameters')
            ->willThrowException(new \Exception());

        $response = ($this->useCaseOnPremise)();

        expect($response)->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())
            ->toBe(DashboardException::errorWhileSearchingSharableContactGroups()->getMessage());
    }
);

it(
    'should return a FindDashboardContactGroupsResponse if no error is raised - AS ADMIN - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $contactGroupRole = new DashboardContactGroupRole(
            contactGroupId: 1,
            contactGroupName: 'name',
            roles: [DashboardGlobalRole::Creator]
        );

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactGroupsWithAccessRightByRequestParameters')
            ->willReturn([$contactGroupRole]);

        $response = ($this->useCaseOnPremise)();
        $contactGroups = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactGroupsResponse::class)
            ->and($contactGroups[0]->id)
            ->toBe($contactGroupRole->getContactGroupId())
            ->and($contactGroups[0]->name)
            ->toBe($contactGroupRole->getContactGroupName())
            ->and($contactGroups[0]->mostPermissiveRole)
            ->toBe($contactGroupRole->getMostPermissiveRole());
    }
);

it(
    'should return a FindDashboardContactGroupsResponse if no error is raised - AS USER - OnPremise',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $contactGroupRole = new DashboardContactGroupRole(
            contactGroupId: 1,
            contactGroupName: 'name',
            roles: [DashboardGlobalRole::Creator]
        );

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactGroupsWithAccessRightByUserAndRequestParameters')
            ->willReturn([$contactGroupRole]);

        $response = ($this->useCaseOnPremise)();
        $contactGroups = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactGroupsResponse::class)
            ->and($contactGroups[0]->id)
            ->toBe($contactGroupRole->getContactGroupId())
            ->and($contactGroups[0]->name)
            ->toBe($contactGroupRole->getContactGroupName())
            ->and($contactGroups[0]->mostPermissiveRole)
            ->toBe($contactGroupRole->getMostPermissiveRole());
    }
);

it(
    'should return a FindDashboardContactGroupsResponse if no error is raised - AS ADMIN - Cloud',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(true);

        $contactGroupRole = new DashboardContactGroupRole(
            contactGroupId: 1,
            contactGroupName: 'name',
            roles: [DashboardGlobalRole::Viewer]
        );

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactGroupsByRequestParameters')
            ->willReturn([$contactGroupRole]);

        $response = ($this->useCaseCloud)();
        $contactGroups = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactGroupsResponse::class)
            ->and($contactGroups[0]->id)
            ->toBe($contactGroupRole->getContactGroupId())
            ->and($contactGroups[0]->name)
            ->toBe($contactGroupRole->getContactGroupName())
            ->and($contactGroups[0]->mostPermissiveRole)
            ->toBe($contactGroupRole->getMostPermissiveRole());
    }
);

it(
    'should return a FindDashboardContactGroupsResponse if no error is raised - AS USER - Cloud',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $contactGroupRole = new DashboardContactGroupRole(
            contactGroupId: 1,
            contactGroupName: 'name',
            roles: [DashboardGlobalRole::Viewer]
        );

        $this->readDashboardShareRepository->expects($this->once())
            ->method('findContactGroupsByUserAndRequestParameters')
            ->willReturn([$contactGroupRole]);

        $response = ($this->useCaseCloud)();
        $contactGroups = $response->getData();

        expect($response)->toBeInstanceOf(FindDashboardContactGroupsResponse::class)
            ->and($contactGroups[0]->id)
            ->toBe($contactGroupRole->getContactGroupId())
            ->and($contactGroups[0]->name)
            ->toBe($contactGroupRole->getContactGroupName())
            ->and($contactGroups[0]->mostPermissiveRole)
            ->toBe($contactGroupRole->getMostPermissiveRole());
    }
);
