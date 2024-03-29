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

namespace Tests\Core\Platform\Application\UseCase\UpdateVersions;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Platform\Application\Repository\ReadUpdateRepositoryInterface;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;
use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;
use Core\Platform\Application\UseCase\UpdateVersions\UpdateVersions;
use Core\Platform\Application\UseCase\UpdateVersions\UpdateVersionsPresenterInterface;
use Core\Platform\Application\Validator\RequirementException;
use Core\Platform\Application\Validator\RequirementValidatorsInterface;
use Pimple\Container;

beforeEach(function (): void {
    $this->centreonModuleService = $this->createMock(CentreonModuleService::class);

    $this->useCase = new UpdateVersions(
        $this->requirementValidators = $this->createMock(RequirementValidatorsInterface::class),
        $this->updateLockerRepository = $this->createMock(UpdateLockerRepositoryInterface::class),
        $this->readVersionRepository = $this->createMock(ReadVersionRepositoryInterface::class),
        $this->readUpdateRepository = $this->createMock(ReadUpdateRepositoryInterface::class),
        $this->writeUpdateRepository = $this->createMock(WriteUpdateRepositoryInterface::class),
        $this->dependencyInjector = new Container([ServiceProvider::CENTREON_MODULE => $this->centreonModuleService]),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->presenter = $this->createMock(UpdateVersionsPresenterInterface::class);
});

it('should present a Forbidden Response when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ForbiddenResponse('Only admin user can perform upgrades'));

    ($this->useCase)($this->presenter);
});

it('should stop update process when an other update is already started', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->updateLockerRepository
        ->expects($this->once())
        ->method('lock')
        ->willReturn(false);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse('Update already in progress'));

    ($this->useCase)($this->presenter);
});

it('should present an error response if a requirement is not validated', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->requirementValidators
        ->expects($this->once())
        ->method('validateRequirementsOrFail')
        ->willThrowException(new RequirementException('Requirement is not validated'));

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse('Requirement is not validated'));

    ($this->useCase)($this->presenter);
});

it('should present an error response if current centreon version is not found', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->updateLockerRepository
        ->expects($this->once())
        ->method('lock')
        ->willReturn(true);

    $this->readVersionRepository
        ->expects($this->once())
        ->method('findCurrentVersion')
        ->willReturn(null);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse('Cannot retrieve the current version'));

    ($this->useCase)($this->presenter);
});

it('should run found updates', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->updateLockerRepository
        ->expects($this->once())
        ->method('lock')
        ->willReturn(true);

    $this->readVersionRepository
        ->expects($this->exactly(2))
        ->method('findCurrentVersion')
        ->will($this->onConsecutiveCalls('22.04.0', '22.10.1'));

    $this->readUpdateRepository
        ->expects($this->once())
        ->method('findOrderedAvailableUpdates')
        ->with('22.04.0')
        ->willReturn(['22.10.0-beta.1', '22.10.0', '22.10.1']);

    $this->centreonModuleService
        ->expects($this->exactly(2))
        ->method('getList')
        ->will(
            $this->onConsecutiveCalls(
                ['module' => []],
                ['widget' => []],
            )
        );


    $this->writeUpdateRepository
        ->expects($this->exactly(3))
        ->method('runUpdate')
        ->withConsecutive(
            [$this->equalTo('22.10.0-beta.1')],
            [$this->equalTo('22.10.0')],
            [$this->equalTo('22.10.1')],
        );

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new NoContentResponse());

    ($this->useCase)($this->presenter);
});
