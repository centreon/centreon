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

namespace Tests\Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Application\Repository\WriteTimePeriodRepositoryInterface;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\AddTimePeriod;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\AddTimePeriodRequest;
use Tests\Core\TimePeriod\Application\UseCase\TimePeriodsPresenterStub;

beforeEach(function () {
    $this->readRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->writeRepository = $this->createMock(WriteTimePeriodRepositoryInterface::class);
    $this->formatter = $this->createMock(JsonFormatter::class);
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->readRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->willThrowException(new \Exception());

    $useCase = new AddTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new TimePeriodsPresenterStub($this->formatter);
    $useCase(new AddTimePeriodRequest(), $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(TimePeriodException::errorWhenAddingTimePeriod()->getMessage());
});

it('should present an ErrorResponse when the name already exists', function () {
    $nameToFind = 'fake_name';
    $this->readRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->with($nameToFind, null)
        ->willReturn(true);

    $request = new AddTimePeriodRequest();
    $request->name = $nameToFind;
    $useCase = new AddTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new TimePeriodsPresenterStub($this->formatter);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(TimePeriodException::nameAlreadyExists($nameToFind)->getMessage());
});