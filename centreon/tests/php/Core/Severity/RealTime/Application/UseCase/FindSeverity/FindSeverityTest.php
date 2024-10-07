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

namespace Tests\Core\Severity\RealTime\Application\UseCase\FindSeverity;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Domain\RealTime\Model\Icon;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Severity\RealTime\Application\Repository\ReadSeverityRepositoryInterface;
use Core\Severity\RealTime\Application\UseCase\FindSeverity\FindSeverity;
use Core\Severity\RealTime\Application\UseCase\FindSeverity\FindSeverityResponse;
use Core\Severity\RealTime\Domain\Model\Severity;

beforeEach(function (): void {

    $this->user = $this->createMock(ContactInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->repository = $this->createMock(ReadSeverityRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new FindSeverityPresenterStub($this->presenterFormatter);

    $this->icon = (new Icon())
    ->setId(1)
    ->setName('icon-name')
    ->setUrl('ppm/icon-name.png');

    $this->severity = new Severity(1, 'name', 50, Severity::HOST_SEVERITY_TYPE_ID, $this->icon);

});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->repository
    ->expects($this->once())
    ->method('findAllByTypeId')
    ->with(Severity::HOST_SEVERITY_TYPE_ID)
    ->willThrowException(new \Exception());

    $useCase = new FindSeverity($this->repository,$this->user, $this->readAccessGroupRepository);
    $useCase(Severity::HOST_SEVERITY_TYPE_ID, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe('An error occured while retrieving severities');
});

it('should present a FindSeverityResponse as admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->repository
    ->expects($this->once())
    ->method('findAllByTypeId')
    ->willReturn([$this->severity]);

    $useCase = new FindSeverity($this->repository,$this->user, $this->readAccessGroupRepository);
    $useCase(Severity::HOST_SEVERITY_TYPE_ID, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindSeverityResponse::class)
        ->and($this->presenter->response->severities[0])->toBe(
            [
                'id' => 1,
                'name' => 'name',
                'level' => 50,
                'type' => 'host',
                'icon' => [
                    'id' => 1,
                    'name' => 'icon-name',
                    'url' => 'ppm/icon-name.png'
                ]
            ]
        );
});

it('should present a FindSeverityResponse as non-admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
    ->willReturn([]);
    $this->repository
    ->expects($this->once())
    ->method('findAllByTypeIdAndAccessGroups')
    ->willReturn([$this->severity]);

    $useCase = new FindSeverity($this->repository,$this->user, $this->readAccessGroupRepository);
    $useCase(Severity::HOST_SEVERITY_TYPE_ID, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindSeverityResponse::class)
        ->and($this->presenter->response->severities[0])->toBe(
            [
                'id' => 1,
                'name' => 'name',
                'level' => 50,
                'type' => 'host',
                'icon' => [
                    'id' => 1,
                    'name' => 'icon-name',
                    'url' => 'ppm/icon-name.png'
                ]
            ]
        );
});
