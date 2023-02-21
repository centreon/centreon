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

namespace Tests\Core\TimePeriod\Application\UseCase\FindTimePeriods;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\{JsonFormatter, PresenterFormatterInterface};
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Application\UseCase\FindTimePeriods\{FindTimePeriods, FindTimePeriodsResponse};
use Core\TimePeriod\Domain\Model\{Template, ExtraTimePeriod, TimePeriod, Day, TimeRange};

beforeEach(function () {
    $this->repository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->requestParameter = $this->createMock(RequestParameters::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, true],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, true],
            ]
        );

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->with($this->requestParameter)
        ->willThrowException(new \Exception());

    $useCase = new FindTimePeriods($this->repository, $this->requestParameter, $this->user);
    $presenter = new DefaultPresenter(
        $this->createMock(JsonFormatter::class)
    );
    $useCase($presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(TimePeriodException::errorWhenSearchingForAllTimePeriods()->getMessage());
});

it('should present an ForbiddenResponse when an user has no rights', function () {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, false],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, false],
            ]
        );

    $useCase = new FindTimePeriods($this->repository, $this->requestParameter, $this->user);
    $presenter = new DefaultPresenter(
        $this->createMock(JsonFormatter::class)
    );
    $useCase($presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe(TimePeriodException::accessNotAllowed()->getMessage());
});

it('should present a FindTimePeriodsResponse when user has read only rights', function () {
    $useCase = new FindTimePeriods($this->repository, $this->requestParameter, $this->user);

    $timePeriod = new TimePeriod(
        1,
        'fakeName',
        'fakeAlias'
    );
    $days = [new Day(1, new TimeRange('00:00-12:00'))];
    $timePeriod->setDays($days);

    $templates = [
        new Template(2, 'fakeAlias2'),
        new Template(3, 'fakeAlias3')
    ];
    $timePeriod->setTemplates($templates);

    $exceptions = new ExtraTimePeriod(1, 'monday 1', new TimeRange('06:00-07:00'));
    $timePeriod->addExtraTimePeriod($exceptions);

    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, true],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, false],
            ]
        );

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->with($this->requestParameter)
        ->willReturn([$timePeriod]);

    $presenter = new FindTimePeriodsPresenterStub($this->presenterFormatter);
    $useCase($presenter);

    expect($presenter->response->timePeriods)->toBeArray()->toHaveCount(1);
    /**
     * @var TimePeriod $timePeriodToBeExpected
     */
    $timePeriodToBeExpected = $presenter->response->timePeriods[0];

    expect($presenter->response)
        ->toBeInstanceOf(FindTimePeriodsResponse::class)
        ->and($timePeriodToBeExpected)->toBe(
            [
                'id' => 1,
                'name' => 'fakeName',
                'alias' => 'fakeAlias',
                'days' => [
                    [
                        'day' => $timePeriod->getDays()[0]->getDay(),
                        'time_range' => (string) $timePeriod->getDays()[0]->getTimeRange()
                    ]
                ],
                'templates' => [
                    [
                        'id' => $timePeriod->getTemplates()[0]->getId(),
                        'alias' => $timePeriod->getTemplates()[0]->getAlias()
                    ],
                    [
                        'id' => $timePeriod->getTemplates()[1]->getId(),
                        'alias' => $timePeriod->getTemplates()[1]->getAlias()
                    ],
                ],
                'exceptions' => [
                    [
                        'id' => $timePeriod->getExtraTimePeriods()[0]->getId(),
                        'day_range' => $timePeriod->getExtraTimePeriods()[0]->getDayRange(),
                        'time_range' => (string) $timePeriod->getExtraTimePeriods()[0]->getTimeRange()
                    ]
                ]
            ]
        );
});


it('should present a FindTimePeriodsResponse when user has read-write rights', function () {
    $useCase = new FindTimePeriods($this->repository, $this->requestParameter, $this->user);

    $timePeriod = new TimePeriod(
        1,
        'fakeName',
        'fakeAlias'
    );
    $days = [new Day(1, new TimeRange('00:00-12:00'))];
    $timePeriod->setDays($days);

    $templates = [
        new Template(2, 'fakeAlias2'),
        new Template(3, 'fakeAlias3')
    ];
    $timePeriod->setTemplates($templates);

    $exceptions = new ExtraTimePeriod(1, 'monday 1', new TimeRange('06:00-07:00'));
    $timePeriod->addExtraTimePeriod($exceptions);

    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ, false],
                [Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE, true],
            ]
        );

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->with($this->requestParameter)
        ->willReturn([$timePeriod]);

    $presenter = new FindTimePeriodsPresenterStub($this->presenterFormatter);
    $useCase($presenter);

    expect($presenter->response->timePeriods)->toBeArray()->toHaveCount(1);
    /**
     * @var TimePeriod $timePeriodToBeExpected
     */
    $timePeriodToBeExpected = $presenter->response->timePeriods[0];

    expect($presenter->response)
        ->toBeInstanceOf(FindTimePeriodsResponse::class)
        ->and($timePeriodToBeExpected)->toBe(
            [
                'id' => 1,
                'name' => 'fakeName',
                'alias' => 'fakeAlias',
                'days' => [
                    [
                        'day' => $timePeriod->getDays()[0]->getDay(),
                        'time_range' => (string) $timePeriod->getDays()[0]->getTimeRange()
                    ]
                ],
                'templates' => [
                    [
                        'id' => $timePeriod->getTemplates()[0]->getId(),
                        'alias' => $timePeriod->getTemplates()[0]->getAlias()
                    ],
                    [
                        'id' => $timePeriod->getTemplates()[1]->getId(),
                        'alias' => $timePeriod->getTemplates()[1]->getAlias()
                    ],
                ],
                'exceptions' => [
                    [
                        'id' => $timePeriod->getExtraTimePeriods()[0]->getId(),
                        'day_range' => $timePeriod->getExtraTimePeriods()[0]->getDayRange(),
                        'time_range' => (string) $timePeriod->getExtraTimePeriods()[0]->getTimeRange()
                    ]
                ]
            ]
        );
});





