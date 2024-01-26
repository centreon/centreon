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

namespace Tests\Core\Dashboard\Application\UseCase\PartialUpdateContactGroupDashboardShare;

use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Domain\Model\Refresh;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\PartialUpdateContactGroupDashboardShare\PartialUpdateContactGroupDashboardShare;
use Core\Dashboard\Application\UseCase\PartialUpdateContactGroupDashboardShare\PartialUpdateContactGroupDashboardShareRequest;

beforeEach(closure: function (): void {
    $this->presenter = new PartialUpdateContactGroupDashboardSharePresenterStub();
    $this->useCase = new PartialUpdateContactGroupDashboardShare(
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
        new Refresh(RefreshType::Global, null),
    );

    $this->testedContactGroup = $this->createMock(ContactGroup::class);
    $this->testedContactGroup->method('getId')->willReturn(random_int(1, 1_000_000));
    $this->testedContactGroup->method('getName')->willReturn(uniqid('name_', true));
});

it(
    'should present a ForbiddenResponse when the user has no rights',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())->method('canCreate')->willReturn(false);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
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
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
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
        $this->readContactGroupRepository->expects($this->once())->method('find')->willReturn(null);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
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
        $this->readContactGroupRepository->expects($this->once())->method('find')
            ->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->once())->method('updateContactGroupShare')
            ->willReturn(false);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
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
        $this->readContactGroupRepository->expects($this->once())->method('find')
            ->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->once())->method('updateContactGroupShare')
            ->willReturn(true);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
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
        $this->rights->expects($this->once())->method('canUpdateShare')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->readContactGroupRepository->expects($this->once())->method('find')
            ->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->once())->method('updateContactGroupShare')
            ->willReturn(true);

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
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
        $this->rights->expects($this->once())->method('canUpdateShare')->willReturn(false);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->readContactGroupRepository->expects($this->once())->method('find')
            ->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->never())->method('updateContactGroupShare');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(DashboardSharingRole::Viewer),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a proper NoContentResponse and not update the role when missing role value in the request',
    function (): void {
        $this->rights->expects($this->once())->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);
        $this->readContactGroupRepository->expects($this->once())->method('find')
            ->willReturn($this->testedContactGroup);
        $this->writeDashboardShareRepository->expects($this->never())->method('updateContactGroupShare');

        ($this->useCase)(
            $this->testedDashboard->getId(),
            $this->testedContactGroup->getId(),
            new PartialUpdateContactGroupDashboardShareRequest(new NoValue()),
            $this->presenter
        );

        expect($this->presenter->data)->toBeInstanceOf(NoContentResponse::class);
    }
);
