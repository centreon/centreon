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

namespace Tests\Core\TimePeriod\Application\UseCase\FindTimePeriod;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, NotFoundResponse};
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\{ReadTimePeriodRepositoryInterface, WriteTimePeriodRepositoryInterface};
use Core\TimePeriod\Application\UseCase\FindTimePeriod\{FindTimePeriod, FindTimePeriodResponse};
use Core\TimePeriod\Domain\Model\{Day, ExtraTimePeriod, Template, TimePeriod, TimeRange};
use Tests\Core\TimePeriod\Application\UseCase\ExtractResponse;

beforeEach(function () {
    $this->readRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->writeRepository = $this->createMock(WriteTimePeriodRepositoryInterface::class);
    $this->formatter = $this->createMock(JsonFormatter::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present an ErrorResponse response when the exception is raised', function () {
    $timePeriodId = 1;

    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, true],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, true],
            ]
        );

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($timePeriodId)
        ->willThrowException(new \Exception());

    $useCase = new FindTimePeriod($this->readRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::errorWhenSearchingForTimePeriod($timePeriodId)->getMessage());
});

it('should present a NotFoundResponse when the time period is not found', function () {
    $timePeriodId = 1;

    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, true],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, true],
            ]
        );

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($timePeriodId)
        ->willReturn(null);

    $useCase = new FindTimePeriod($this->readRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Time period'))->getMessage());
});

it('should present an Forbidden response when user has insufficient rights', function () {
    $timePeriodId = 1;

    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, false],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, false],
            ]
        );

    $useCase = new FindTimePeriod($this->readRepository, $this->user);
    $presenter = new DefaultPresenter($this->formatter);
    $useCase($timePeriodId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe(TimePeriodException::accessNotAllowed()->getMessage());
});

it('should present a FindTimePeriodResponse if the time response is found and user has read only rigths', function () {
    $timePeriod = new TimePeriod(1, 'fake_name', 'fake_alias');
    $timePeriod->addDay(new Day(1, new TimeRange('00:30-04:00')));
    $timePeriod->addTemplate(new Template(1, 'fake_template'));
    $timePeriod->addExtraTimePeriod(
        new ExtraTimePeriod(1, 'monday 1', new TimeRange('00:00-01:00'))
    );

    $timePeriodId = 1;

    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, true],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, false],
            ]
        );

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($timePeriodId)
        ->willReturn($timePeriod);

    $useCase = new FindTimePeriod($this->readRepository, $this->user);
    $presenter = new FindTimePeriodPresenterStub($this->formatter);
    $useCase($timePeriodId, $presenter);

    $response = $presenter->response;
    expect($response)
        ->toBeInstanceOf(FindTimePeriodResponse::class)
        ->and($response->id)
        ->toBe($timePeriod->getId())
        ->and($response->name)
        ->toBe($timePeriod->getName())
        ->and($response->alias)
        ->toBe($timePeriod->getAlias())
        ->and($response->days)
        ->toBe(ExtractResponse::daysToArray($timePeriod))
        ->and($response->templates)
        ->toBe(ExtractResponse::templatesToArray($timePeriod))
        ->and($response->exceptions)
        ->toBe(ExtractResponse::exceptionsToArray($timePeriod));
});


it('should present a FindTimePeriodResponse if the time response is found and user has read-write rigths', function () {
    $timePeriod = new TimePeriod(1, 'fake_name', 'fake_alias');
    $timePeriod->addDay(new Day(1, new TimeRange('00:30-04:00')));
    $timePeriod->addTemplate(new Template(1, 'fake_template'));
    $timePeriod->addExtraTimePeriod(
        new ExtraTimePeriod(1, 'monday 1', new TimeRange('00:00-01:00'))
    );

    $timePeriodId = 1;

    $this->user
    ->expects($this->atMost(2))
    ->method('hasTopologyRole')
    ->willReturnMap(
        [
            [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, false],
            [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, true],
        ]
    );

    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($timePeriodId)
        ->willReturn($timePeriod);

    $useCase = new FindTimePeriod($this->readRepository, $this->user);
    $presenter = new FindTimePeriodPresenterStub($this->formatter);
    $useCase($timePeriodId, $presenter);

    $response = $presenter->response;
    expect($response)
        ->toBeInstanceOf(FindTimePeriodResponse::class)
        ->and($response->id)
        ->toBe($timePeriod->getId())
        ->and($response->name)
        ->toBe($timePeriod->getName())
        ->and($response->alias)
        ->toBe($timePeriod->getAlias())
        ->and($response->days)
        ->toBe(ExtractResponse::daysToArray($timePeriod))
        ->and($response->templates)
        ->toBe(ExtractResponse::templatesToArray($timePeriod))
        ->and($response->exceptions)
        ->toBe(ExtractResponse::exceptionsToArray($timePeriod));
});
