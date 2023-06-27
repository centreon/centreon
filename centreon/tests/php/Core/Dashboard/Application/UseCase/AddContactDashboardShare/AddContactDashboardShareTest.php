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

namespace Tests\Core\Dashboard\Application\UseCase\AddContactDashboardShare;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\AddContactDashboardShare\AddContactDashboardShare;
use Core\Dashboard\Application\UseCase\AddContactDashboardShare\AddContactDashboardShareRequest;
use Core\Dashboard\Application\UseCase\AddContactDashboardShare\AddContactDashboardShareResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

beforeEach(function (): void {
    $this->presenter = new AddContactDashboardSharePresenterStub();
    $this->useCase = new AddContactDashboardShare(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->writeDashboardShareRepository = $this->createMock(WriteDashboardShareRepositoryInterface::class),
        $this->contactRepository = $this->createMock(ContactRepositoryInterface::class),
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

    $this->testedContact = $this->createMock(Contact::class);
    $this->testedContact->method('getId')->willReturn(random_int(1, 1_000_000));
    $this->testedContact->method('getName')->willReturn(uniqid('name_', true));
    $this->testedContact->method('getEmail')->willReturn(uniqid('email_', true));
});

it(
    'should present a ForbiddenResponse when the user has no rights',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(false);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactDashboardShareRequest($contactId = 2, $role = DashboardSharingRole::Viewer),
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
            new AddContactDashboardShareRequest($contactId = $this->testedContact->getId(), $role = DashboardSharingRole::Viewer),
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
        $this->contactRepository->expects($this->once())->method('findById')->willReturn(null);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactDashboardShareRequest($contactId = $this->testedContact->getId(), $role = DashboardSharingRole::Viewer),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::theContactDoesNotExist($contactId)->getMessage());
    }
);

it(
    'should present an ErrorResponse when we failed retrieving the shares after add',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())
            ->method('findById')->willReturn($this->testedContact);
        $this->readDashboardShareRepository->expects($this->once())
            ->method('getOneSharingRoles')->willReturn(
                new DashboardSharingRoles(
                    $this->testedDashboard,
                    null,
                    []
                )
            );

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactDashboardShareRequest($contactId = $this->testedContact->getId(), $role = DashboardSharingRole::Viewer),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileRetrievingJustCreatedShare()->getMessage());
    }
);

it(
    'should present a proper AddContactDashboardShareResponse as an ADMIN',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())
            ->method('findById')->willReturn($this->testedContact);
        $this->readDashboardShareRepository->expects($this->once())
            ->method('getOneSharingRoles')->willReturn(
                new DashboardSharingRoles(
                    $this->testedDashboard,
                    new DashboardContactShare(
                        $this->testedDashboard,
                        $this->testedContact->getId(),
                        $this->testedContact->getName(),
                        $this->testedContact->getEmail(),
                        $role
                    ),
                    []
                )
            );

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactDashboardShareRequest($contactId = $this->testedContact->getId(), $role),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(AddContactDashboardShareResponse::class)
            ->and($this->presenter->data->id)->toBe($this->testedContact->getId())
            ->and($this->presenter->data->name)->toBe($this->testedContact->getName())
            ->and($this->presenter->data->email)->toBe($this->testedContact->getEmail())
            ->and($this->presenter->data->role->name)->toBe($role->name);
    }
)->with([
    ['viewer'],
    ['editor'],
]);

it(
    'should present a proper AddContactDashboardShareResponse as a user with allowed ROLE',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())
            ->method('findById')->willReturn($this->testedContact);
        $this->readDashboardShareRepository->expects($this->once())
            ->method('getOneSharingRoles')->willReturn(
                new DashboardSharingRoles(
                    $this->testedDashboard,
                    new DashboardContactShare(
                        $this->testedDashboard,
                        $this->testedContact->getId(),
                        $this->testedContact->getName(),
                        $this->testedContact->getEmail(),
                        $role
                    ),
                    []
                )
            );

        ($this->useCase)(
            $this->testedDashboard->getId(),
            new AddContactDashboardShareRequest($contactId = $this->testedContact->getId(), $role),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(AddContactDashboardShareResponse::class)
            ->and($this->presenter->data->id)->toBe($this->testedContact->getId())
            ->and($this->presenter->data->name)->toBe($this->testedContact->getName())
            ->and($this->presenter->data->email)->toBe($this->testedContact->getEmail())
            ->and($this->presenter->data->role->name)->toBe($role->name);
    }
)->with([
    ['viewer'],
    ['editor'],
]);
