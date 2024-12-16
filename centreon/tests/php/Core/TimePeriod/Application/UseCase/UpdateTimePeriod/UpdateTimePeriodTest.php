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

namespace Tests\Core\TimePeriod\Application\UseCase\UpdateTimePeriod;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\{ConflictResponse, ErrorResponse, NoContentResponse, NotFoundResponse};
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\{ReadTimePeriodRepositoryInterface, WriteTimePeriodRepositoryInterface};
use Core\TimePeriod\Application\UseCase\UpdateTimePeriod\{UpdateTimePeriod, UpdateTimePeriodRequest};
use Core\TimePeriod\Domain\Model\TimePeriod;

beforeEach(function (): void {
    $this->readRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->writeRepository = $this->createMock(WriteTimePeriodRepositoryInterface::class);
    $this->formatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present an ErrorResponse when an exception is raised', function (): void {
    $request = new UpdateTimePeriodRequest();
    $request->id = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willThrowException(new \Exception());

    $presenter = new DefaultPresenter($this->formatter);
    $useCase = new UpdateTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::errorOnUpdate($request->id)->getMessage());
});

it('should present an ForbiddenResponse when user has insufficient rigths', function (): void {
    $request = new UpdateTimePeriodRequest();
    $request->id = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $presenter = new DefaultPresenter($this->formatter);
    $useCase = new UpdateTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::editNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the time period to update is not found', function (): void {
    $request = new UpdateTimePeriodRequest();
    $request->id = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(null);

    $presenter = new DefaultPresenter($this->formatter);
    $useCase = new UpdateTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Time period'))->getMessage());
});

it('should present an ErrorResponse when the time period name already exists', function (): void {
    $request = new UpdateTimePeriodRequest();
    $request->id = 1;
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';

    $timePeriod = new TimePeriod($request->id = 1, $request->name, $request->alias);

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn($timePeriod);

    $this->readRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->with($request->name, $request->id)
        ->willReturn(true);

    $presenter = new DefaultPresenter($this->formatter);
    $useCase = new UpdateTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::nameAlreadyExists($request->name)->getMessage());
});

it('should present a NoContentResponse after update', function (): void {
    $request = new UpdateTimePeriodRequest();
    $request->id = 1;
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';

    $timePeriod = new TimePeriod($request->id = 1, $request->name, $request->alias);

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn($timePeriod);

    $this->readRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->with($request->name, $request->id)
        ->willReturn(false);

    $presenter = new DefaultPresenter($this->formatter);
    $useCase = new UpdateTimePeriod($this->readRepository, $this->writeRepository, $this->user);
    $useCase($request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});
