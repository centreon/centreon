<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\TimePeriod\Application\UseCase\DeleteTimePeriod;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, NoContentResponse, NotFoundResponse};
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\{ReadTimePeriodRepositoryInterface, WriteTimePeriodRepositoryInterface};
use Core\TimePeriod\Application\UseCase\DeleteTimePeriod\DeleteTimePeriod;

beforeEach(function (): void {
    $this->readRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->writeRepository = $this->createMock(WriteTimePeriodRepositoryInterface::class);
    $this->formatter = $this->createMock(JsonFormatter::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $timePeriodId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::editNotAllowed()->getMessage());
});

it('should present a NotFoundResponse error when the time period is not found', function (): void {
    $timePeriodId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->with($timePeriodId)
        ->willReturn(false);

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Time period'))->getMessage());
});

it('should present an ErrorResponse response when the exception is raised', function (): void {
    $timePeriodId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->with($timePeriodId)
        ->willReturn(true);

    $this->writeRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::errorOnDelete($timePeriodId)->getMessage());
});

it('should present a NoContentResponse response when the time period is deleted', function (): void {
    $timePeriodId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->with($timePeriodId)
        ->willReturn(true);

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});
