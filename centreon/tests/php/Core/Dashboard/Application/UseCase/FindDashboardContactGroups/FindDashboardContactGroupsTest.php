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
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\FindDashboardContactGroups;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\FindDashboardContactGroupsResponse;
use Core\Dashboard\Domain\Model\DashboardRights;

beforeEach(function (): void {
    $this->presenter = new FindDashboardContactGroupsPresenterStub();
    $this->useCase = new FindDashboardContactGroups(
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class)
    );
});

it(
    'should present an ErrorResponse if an error is raised',
    function (): void {
        $this->rights->expects($this->once())->method('canAccess')->willThrowException(new \Exception());

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())->toBe(DashboardException::errorWhileRetrieving()->getMessage());
    }
);

it(
    'should present a ForbiddenResponse if the contact is NOT allowed',
    function (): void {
        $this->rights->expects($this->once())->method('canAccess')->willReturn(false);

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())->toBe(DashboardException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindDashboardContactGroupsResponse if the contact is allowed',
    function (): void {
        $this->rights->expects($this->once())->method('canAccess')->willReturn(true);

        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readDashboardShareRepository
            ->expects($this->once())
            ->method('findContactGroupsWithAccessRightByUserAndRequestParameters')
            ->willReturn([]);

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(FindDashboardContactGroupsResponse::class);
    }
);
