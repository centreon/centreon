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

namespace Tests\Core\Dashboard\Application\UseCase\PartialUpdateDashboard;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboard;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboardRequest;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\ThumbnailRequestDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Refresh;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;

beforeEach(function (): void {
    $this->presenter = new PartialUpdateDashboardPresenterStub();
    $this->useCase = new PartialUpdateDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->writeDashboardRepository = $this->createMock(WriteDashboardRepositoryInterface::class),
        $this->readDashboardPanelRepository = $this->createMock(ReadDashboardPanelRepositoryInterface::class),
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->writeDashboardPanelRepository = $this->createMock(WriteDashboardPanelRepositoryInterface::class),
        $this->createMock(DataStorageEngineInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->eventDispatcher = $this->createMock(EventDispatcher::class),
        $this->isCloudPlatform = false
    );

    $this->testedPartialUpdateDashboardRequest = new PartialUpdateDashboardRequest();
    $this->testedPartialUpdateDashboardRequest->name = 'updated-dashboard';
    $this->testedPartialUpdateDashboardRequest->thumbnail = new ThumbnailRequestDto(null, __DIR__, 'logo.jpg');
    $this->testedPartialUpdateDashboardRequest->thumbnail->content = file_get_contents(__DIR__ . '/logo.jpg');

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = random_int(1, 1_000_000),
        $this->testedDashboardName = uniqid('name', true),
        $this->testedDashboardCreatedBy = random_int(1, 1_000_000),
        $this->testedDashboardUpdatedBy = random_int(1, 1_000_000),
        $this->testedDashboardCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        $this->testedDashboardUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00'),
        $this->testedDashboardGlobalRefresh = new Refresh(RefreshType::Global, null)
    );
});

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willThrowException(new \Exception());

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileUpdating()->getMessage());
    }
);

it(
    'should present an ErrorResponse with a custom message when a DashboardException is thrown',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')
            ->willThrowException(new DashboardException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($msg);
    }
);

it(
    'should present an InvalidArgumentResponse when a model field value is not valid',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);

        $this->testedPartialUpdateDashboardRequest->name = '';
        $expectedException = AssertionException::notEmptyString('Dashboard::name');

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(false);

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a NoContentResponse as admin',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('update');
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->data;

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should update the updatedAt field',
    function (): void {
        $updatedAt = null;
        $updatedAtBeforeUseCase = $this->testedDashboardUpdatedAt->getTimestamp();

        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->callback(function (Dashboard $dashboard) use (&$updatedAt): bool {
                    $updatedAt = $dashboard->getUpdatedAt();

                    return true;
                })
            );
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedDashboard);

        $timeBeforeUsecase = time();
        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        expect($updatedAt)->not()->toBeNull()
            ->and($updatedAt->getTimestamp())->toBeGreaterThanOrEqual($updatedAtBeforeUseCase)
            ->and($updatedAt->getTimestamp())->toBeGreaterThanOrEqual($timeBeforeUsecase);
    }
);

it(
    'should present a ForbiddenResponse as not allowed user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(false);

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a NoContentResponse as allowed ADMIN user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('update');
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->data;

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should present a ForbiddenResponse as NOT allowed SHARED user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->rights->expects($this->once())
            ->method('canUpdate')->willReturn(false);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->data;

        expect($presentedData)->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a NoContentResponse as allowed SHARED user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('update');
        $this->rights->expects($this->once())
            ->method('canUpdate')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedDashboardId, $this->testedPartialUpdateDashboardRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->data;

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
    }
);
