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

namespace Tests\Core\Dashboard\Application\UseCase\AddContactGroupDashboardShare;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\AddContactGroupDashboardShare\AddContactGroupDashboardShare;
use Core\Dashboard\Application\UseCase\AddContactGroupDashboardShare\AddContactGroupDashboardShareRequest;
use Core\Dashboard\Application\UseCase\AddContactGroupDashboardShare\AddContactGroupDashboardShareResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

beforeEach(function (): void {
    $this->presenter = new AddContactGroupDashboardSharePresenterStub();
    $this->useCase = new AddContactGroupDashboardShare(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->writeDashboardShareRepository = $this->createMock(WriteDashboardShareRepositoryInterface::class),
        $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );

    $this->testedDashboard = new Dashboard(
        random_int(1, 1_000_000),
        uniqid('dashboard_', true),
        '',
        null,
        null,
        new \DateTimeImmutable(),
        new \DateTimeImmutable(),
    );

    $this->testedContactGroup = $this->createMock(ContactGroup::class);
    $this->testedContactGroup->method('getId')->willReturn(random_int(1, 1_000_000));
    $this->testedContactGroup->method('getName')->willReturn(uniqid('name_', true));
});

it(
    'should present a ForbiddenResponse when the user has no rights',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(false);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactGroupDashboardShareRequest($contactId = 2, $role = DashboardSharingRole::Viewer),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a NotFoundResponse when the dashboard does not exist',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())->method('findOne')->willReturn(null);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactGroupDashboardShareRequest($contactId = $this->testedContactGroup->getId(), $role = DashboardSharingRole::Viewer),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present an ErrorResponse when the contact does not exist',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->readContactGroupRepository->expects($this->once())->method('find')->willReturn(null);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactGroupDashboardShareRequest($contactId = $this->testedContactGroup->getId(), $role = DashboardSharingRole::Viewer),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::theContactGroupDoesNotExist($contactId)->getMessage());
    }
);

it(
    'should present a proper AddContactGroupDashboardShareResponse as an ADMIN',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->readContactGroupRepository->expects($this->once())
            ->method('find')->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->once())
            ->method('upsertShareWithContactGroup');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactGroupDashboardShareRequest($contactId = $this->testedContactGroup->getId(), $role),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(AddContactGroupDashboardShareResponse::class)
            ->and($this->presenter->data->id)->toBe($this->testedContactGroup->getId())
            ->and($this->presenter->data->name)->toBe($this->testedContactGroup->getName())
            ->and($this->presenter->data->role->name)->toBe($role->name);
    }
)->with([
    ['viewer'],
    ['editor'],
]);

it(
    'should present a proper AddContactGroupDashboardShareResponse as a user with allowed ROLE',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(true);
        $this->rights->expects($this->once())->method('canCreateShare')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->readContactGroupRepository->expects($this->once())
            ->method('find')->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->once())
            ->method('upsertShareWithContactGroup');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactGroupDashboardShareRequest($contactId = $this->testedContactGroup->getId(), $role),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(AddContactGroupDashboardShareResponse::class)
            ->and($this->presenter->data->id)->toBe($this->testedContactGroup->getId())
            ->and($this->presenter->data->name)->toBe($this->testedContactGroup->getName())
            ->and($this->presenter->data->role->name)->toBe($role->name);
    }
)->with([
    ['viewer'],
    ['editor'],
]);

it(
    'should present a ForbiddenResponse as a user with NOT allowed ROLE',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(true);
        $this->rights->expects($this->once())->method('canCreateShare')->willReturn(false);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->writeDashboardShareRepository->expects($this->never())
            ->method('upsertShareWithContactGroup');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactGroupDashboardShareRequest($contactId = $this->testedContactGroup->getId(), $role),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
)->with([
    ['viewer'],
    ['editor'],
]);
