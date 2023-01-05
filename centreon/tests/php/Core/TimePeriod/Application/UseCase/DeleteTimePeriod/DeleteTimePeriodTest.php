<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Application\Common\UseCase\{ErrorResponse, NoContentResponse, NotFoundResponse};
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\{ReadTimePeriodRepositoryInterface, WriteTimePeriodRepositoryInterface};
use Core\TimePeriod\Application\UseCase\DeleteTimePeriod\DeleteTimePeriod;

beforeEach(function () {
    $this->readRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->writeRepository = $this->createMock(WriteTimePeriodRepositoryInterface::class);
    $this->formatter = $this->createMock(JsonFormatter::class);
});

it('should present a NotFoundResponse error when the time period is not found', function () {
    $timePeriodId = 1;
    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->with($timePeriodId)
        ->willReturn(false);

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Time period'))->getMessage());
});

it('should present an ErrorResponse response when the exception is raised', function () {
    $timePeriodId = 1;
    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->with($timePeriodId)
        ->willReturn(true);

    $this->writeRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::errorOnDelete($timePeriodId)->getMessage());
});

it('should present a NoContentResponse response when the time period is deleted', function () {
    $timePeriodId = 1;
    $this->readRepository
        ->expects($this->once())
        ->method('exists')
        ->with($timePeriodId)
        ->willReturn(true);

    $useCase = new DeleteTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});
