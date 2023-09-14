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

namespace Tests\Core\Dashboard\Application\UseCase\FindContactDashboardShares;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindContactDashboardShares\FindContactDashboardShares;
use Core\Dashboard\Application\UseCase\FindContactDashboardShares\FindContactDashboardSharesResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

beforeEach(function (): void {
    $this->presenter = new FindContactDashboardSharesPresenterStub();
    $this->useCase = new FindContactDashboardShares(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
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
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present a proper FindContactDashboardSharesResponse as an ADMIN',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->readDashboardShareRepository->expects($this->once())
            ->method('findDashboardContactSharesByRequestParameter')->willReturn([
                new DashboardContactShare(
                    $this->testedDashboard,
                    $this->testedContact->getId(),
                    $this->testedContact->getName(),
                    $this->testedContact->getEmail(),
                    $role
                ),
            ]
            );

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(FindContactDashboardSharesResponse::class)
            ->and($this->presenter->data->shares)->toHaveCount(1)
            ->and($this->presenter->data->shares[0]->id)->toBe($this->testedContact->getId())
            ->and($this->presenter->data->shares[0]->name)->toBe($this->testedContact->getName())
            ->and($this->presenter->data->shares[0]->email)->toBe($this->testedContact->getEmail())
            ->and($this->presenter->data->shares[0]->role->name)->toBe($role->name);
    }
)->with([
    ['viewer'],
    ['editor'],
]);

it(
    'should present a proper FindContactDashboardSharesResponse as a user with allowed ROLE',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(true);
        $this->rights->expects($this->once())->method('canAccessShare')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->readDashboardShareRepository->expects($this->once())
            ->method('findDashboardContactSharesByRequestParameter')->willReturn([
                new DashboardContactShare(
                    $this->testedDashboard,
                    $this->testedContact->getId(),
                    $this->testedContact->getName(),
                    $this->testedContact->getEmail(),
                    $role
                ),
            ]
            );

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(FindContactDashboardSharesResponse::class)
            ->and($this->presenter->data->shares)->toHaveCount(1)
            ->and($this->presenter->data->shares[0]->id)->toBe($this->testedContact->getId())
            ->and($this->presenter->data->shares[0]->name)->toBe($this->testedContact->getName())
            ->and($this->presenter->data->shares[0]->email)->toBe($this->testedContact->getEmail())
            ->and($this->presenter->data->shares[0]->role->name)->toBe($role->name);
    }
)->with([
    ['viewer'],
    ['editor'],
]);

it(
    'should present a proper ForbiddenResponse as a user with NOT allowed ROLE',
    function (string $roleString): void {
        $role = DashboardSharingRoleConverter::fromString($roleString);
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canAccess')->willReturn(true);
        $this->rights->expects($this->once())->method('canAccessShare')->willReturn(false);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->readDashboardShareRepository->expects($this->never())
            ->method('findDashboardContactSharesByRequestParameter');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
)->with([
    ['viewer'],
    ['editor'],
]);
