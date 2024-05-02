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

namespace Tests\Core\Dashboard\Application\UseCase\DeleteContactDashboardShare;

use Centreon\Domain\Contact\Contact;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Domain\Model\Refresh;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\DeleteContactDashboardShare\DeleteContactDashboardShare;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(closure: function (): void {
    $this->presenter = new DeleteContactDashboardSharePresenterStub();
    $this->useCase = new DeleteContactDashboardShare(
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->writeDashboardShareRepository = $this->createMock(WriteDashboardShareRepositoryInterface::class),
        $this->contactRepository = $this->createMock(ContactRepositoryInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class),
        $this->isCloudPlatform = false
    );

    $this->testedDashboard = new Dashboard(
        random_int(1, 1_000_000),
        uniqid('dashboard_', true),
        null,
        null,
        new \DateTimeImmutable(),
        new \DateTimeImmutable(),
        new Refresh(RefreshType::Global, null),
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
        $this->rights->expects($this->once())->method('canCreate')->willReturn(false);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContact->getId(),
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
            $this->testedContact->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present a NotFoundResponse when the contact does not exist',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())->method('findById')->willReturn(null);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContact->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present a NotFoundResponse when the share does not exist',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())->method('findById')
            ->willReturn($this->testedContact);
        $this->writeDashboardShareRepository->expects($this->once())->method('deleteContactShare')
            ->willReturn(false);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContact->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present a proper NoContentResponse as an ADMIN',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())->method('findById')
            ->willReturn($this->testedContact);
        $this->writeDashboardShareRepository->expects($this->once())->method('deleteContactShare')
            ->willReturn(true);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContact->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should present a proper NoContentResponse as a user with allowed ROLE',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canCreate')->willReturn(true);
        $this->rights->expects($this->once())->method('canDeleteShare')->willReturn(true);
        $this->readContactRepository->expects($this->once())->method('existInAccessGroups')
            ->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())->method('findById')
            ->willReturn($this->testedContact);
        $this->writeDashboardShareRepository->expects($this->once())->method('deleteContactShare')
            ->willReturn(true);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContact->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should present a proper ForbiddenResponse as a user with NOT allowed ROLE',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canCreate')->willReturn(true);
        $this->rights->expects($this->once())->method('canDeleteShare')->willReturn(false);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->contactRepository->expects($this->once())->method('findById')
            ->willReturn($this->testedContact);
        $this->writeDashboardShareRepository->expects($this->never())->method('deleteContactShare');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContact->getId(),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
);
