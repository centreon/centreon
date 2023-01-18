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

use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Application\Common\UseCase\{ConflictResponse, CreatedResponse, ErrorResponse};
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\{ReadTimePeriodRepositoryInterface, WriteTimePeriodRepositoryInterface};
use Core\TimePeriod\Application\UseCase\AddTimePeriod\{AddTimePeriod, AddTimePeriodRequest};
use Core\TimePeriod\Domain\Model\{Day, ExtraTimePeriod, Template, TimePeriod, TimeRange};
use Tests\Core\TimePeriod\Application\UseCase\ExtractResponse;

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
    $presenter = new DefaultPresenter($this->formatter);
    $useCase(new AddTimePeriodRequest(), $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
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
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(TimePeriodException::nameAlreadyExists($nameToFind)->getMessage());
});

it('should present an ErrorResponse when the new time period cannot be found after creation', function () {
    $nameToFind = 'fake_name';
    $this->readRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->with($nameToFind, null)
        ->willReturn(false);

    $this->writeRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with(1)
        ->willReturn(null);

    $request = new AddTimePeriodRequest();
    $request->name = $nameToFind;
    $request->alias = 'fake_alias';
    $useCase = new AddTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(TimePeriodException::errorWhenAddingTimePeriod()->getMessage());
});

it('should present a correct CreatedResponse object after creation', function () {
    $fakeName = 'fake_name';
    $this->readRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->with($fakeName, null)
        ->willReturn(false);

    $this->writeRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $newTimePeriod = new TimePeriod(1, $fakeName, $fakeAlias = 'fake_alias');
    $newTimePeriod->addDay(new Day(1, new TimeRange('00:30-04:00')));
    $template = new Template(1, 'fake_template');
    $newTimePeriod->addTemplate($template);
    $extraPeriod = new ExtraTimePeriod(1, 'monday 1', new TimeRange('00:00-01:00'));
    $newTimePeriod->addExtraTimePeriod($extraPeriod);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with(1)
        ->willReturn($newTimePeriod);

    $request = new AddTimePeriodRequest();
    $request->name = $fakeName;
    $request->alias = 'fake_alias';
    $useCase = new AddTimePeriod($this->readRepository, $this->writeRepository);
    $presenter = new AddTimePeriodsPresenterStub($this->formatter);
    $useCase($request, $presenter);

    expect($presenter->response)->toBeInstanceOf(CreatedResponse::class);
    expect($presenter->response->getResourceId())->toBe($newTimePeriod->getId());

    $payload = $presenter->response->getPayload();
    expect($payload->name)
        ->toBe($newTimePeriod->getName())
        ->and($payload->alias)
        ->toBe($newTimePeriod->getAlias())
        ->and($payload->days)
        ->toBe(ExtractResponse::daysToArray($newTimePeriod))
        ->and($payload->templates)
        ->toBe(ExtractResponse::templatesToArray($newTimePeriod))
        ->and($payload->exceptions)
        ->toBe(ExtractResponse::exceptionsToArray($newTimePeriod));
});
